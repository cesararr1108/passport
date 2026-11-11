<?php
 ob_start();
 require_once 'funciones.php';
 session_start();
 conectar();

 require_once 'RifasConfig.php';

 /* =====================================================================
    UTILIDADES
    ===================================================================== */
 function evJson($data){
	 // Descarta avisos/notice de PHP que dañarían el JSON
	 while(ob_get_level() > 0){ ob_end_clean(); }
	 header('Content-Type: application/json; charset=utf-8');
	 echo json_encode($data);
	 exit;
 }

 function evError($msg = ''){
	 if($msg == ''){
		 $msg = 'Error en base de datos: '.evUtf8(mssql_get_last_message());
	 }
	 evJson(array('ok' => false, 'msg' => $msg));
 }

 function evUtf8($v){
	 if(is_string($v) && !preg_match('//u', $v)){
		 return utf8_encode($v);
	 }
	 return $v;
 }

 function evEsc($v){
	 return str_replace("'", "''", trim((string)$v));
 }

 // Número sin separadores para concatenar en SQL
 function evNum($n){
	 return number_format((float)$n, 0, '.', '');
 }

 function evRows($sql){
	 $q = mssql_query($sql);
	 if($q === false){
		 evError();
	 }
	 $rows = array();
	 if(is_resource($q)){
		 while($r = mssql_fetch_assoc($q)){
			 foreach($r as $k => $v){
				 $r[$k] = is_string($v) ? trim(evUtf8($v)) : $v;
			 }
			 $rows[] = $r;
		 }
		 mssql_free_result($q);
	 }
	 return $rows;
 }

 function evPass(){
	 $p = isset($_POST['Pass']) ? trim($_POST['Pass']) : '';
	 if(!preg_match('/^[A-Za-z0-9\-]{1,10}$/', $p)){
		 evError('Código SAP inválido');
	 }
	 return $p;
 }

 function evDinamicaPost(){
	 $d  = isset($_POST['dinamica']) ? trim($_POST['dinamica']) : '';
	 $ds = evDinamicas();
	 if(!isset($ds[$d])){
		 evError('Dinámica inválida');
	 }
	 return $d;
 }

 function evUsuario(){
	 $u = '';
	 if(!empty($_SESSION['Ses_Usuario'])){
		 $u = $_SESSION['Ses_Usuario'];
	 }
	 return evEsc(substr($u, 0, 16));
 }

 // Fecha ISO 'AAAA-MM-DD HH:MM:SS' -> expresión datetime de SQL Server.
 // Se usa el estilo 126 (con 'T') porque no depende del idioma/DATEFORMAT del login.
 function evSqlFecha($iso){
	 return "CONVERT(datetime, '".str_replace(' ', 'T', evEsc($iso))."', 126)";
 }

 function evJoinNit(){
	 return EV_NIT_EXACTO ? "LTRIM(RTRIM(T.NIT)) = R.NIT" : "T.NIT LIKE '%' + R.NIT + '%'";
 }

 /* =====================================================================
    CONTEXTO DE TIEMPO + ESPEJO DE LAS 12:00
    ===================================================================== */
 function evContexto(){
	 static $ctx = null;
	 if($ctx !== null){ return $ctx; }

	 $r     = evRows("SELECT ahora = CONVERT(varchar(19), GETDATE(), 120)");
	 $ahora = $r[0]['ahora'];                       // AAAA-MM-DD HH:MM:SS
	 $fecha = EV_FECHA_EVENTO != '' ? EV_FECHA_EVENTO : substr($ahora, 0, 10);
	 $corte = $fecha.' '.EV_HORA_CORTE_MANANA.':00';
	 $tarde = $fecha.' '.EV_HORA_INICIO_TARDE.':00';

	 $ctx = array(
		 'ahora'          => $ahora,
		 'fecha'          => $fecha,
		 'corte'          => $corte,
		 'tarde'          => $tarde,
		 'manana_abierta' => $ahora < $corte,
		 'tarde_abierta'  => $ahora >= $tarde,
		 'espejo'         => null
	 );

	 // Pasado el corte, el espejo se genera automáticamente la primera vez
	 $ctx['espejo'] = evEspejoEstado($corte);
	 if(!$ctx['manana_abierta'] && $ctx['espejo'] === null){
		 $ctx['espejo'] = evEspejoGenerar($corte);
	 }
	 return $ctx;
 }

 function evEspejoEstado($corte){
	 $r = evRows("SELECT
					fecha_corte      = CONVERT(varchar(19), FECHA_CORTE, 120),
					fecha_generacion = CONVERT(varchar(19), FECHA_GENERACION, 120),
					clientes         = CLIENTES,
					usuario          = USUARIO
				FROM T_RIFA_ESPEJO_CONTROL
				WHERE FECHA_CORTE = ".evSqlFecha($corte));
	 return count($r) > 0 ? $r[0] : null;
 }

 /* CTEs:  R   = laboratorios participantes (T_RIFAS)
           LAB = terceros (T_TERCEROS) que pertenecen a cada laboratorio participante
           C   = compras por cliente y laboratorio participante
                 COMPRA    = todo el día
                 COMPRA_AM = antes del corte de la mañana */
 function evSqlCte($filtroPedidos, $corte){
	 return "
		WITH R AS (
			SELECT
				NIT     = LTRIM(RTRIM(NIT)),
				NOMBRES = MAX(NOMBRES)
			FROM T_RIFAS
			WHERE ISNULL(LTRIM(RTRIM(NIT)), '') <> ''
			GROUP BY LTRIM(RTRIM(NIT))
		), LAB AS (
			SELECT DISTINCT
				T.CODIGO_SAP,
				R.NIT
			FROM T_TERCEROS T
			INNER JOIN R ON ".evJoinNit()."
		), C AS (
			SELECT
				P.PASSPORT,
				LAB.NIT,
				COMPRA    = SUM(P.VALOR_TOTAL),
				COMPRA_AM = SUM(CASE WHEN P.FECHA_HORA < ".evSqlFecha($corte)." THEN P.VALOR_TOTAL ELSE 0 END)
			FROM ".EV_TABLA_PEDIDOS." P
			INNER JOIN T_MATERIALES M ON M.CODIGO_MATERIAL = P.CODIGO
			INNER JOIN LAB            ON LAB.CODIGO_SAP    = M.CODIGO_SAP
			WHERE (".$filtroPedidos.") AND (".EV_PEDIDOS_WHERE.")
			GROUP BY
				P.PASSPORT,
				LAB.NIT
		) ";
 }

 // Toma la "foto" de las compras al corte (una sola vez; seguro ante dos usuarios simultáneos)
 function evEspejoGenerar($corte){
	 $fc = evSqlFecha($corte);
	 evRows("SET NOCOUNT ON; SET XACT_ABORT ON;
		BEGIN TRAN;
		IF NOT EXISTS (SELECT 1 FROM T_RIFA_ESPEJO_CONTROL WITH (UPDLOCK, HOLDLOCK) WHERE FECHA_CORTE = ".$fc.")
		BEGIN
			INSERT INTO T_RIFA_ESPEJO_CONTROL (FECHA_CORTE, USUARIO) VALUES (".$fc.", '".evUsuario()."');

			".evSqlCte("P.FECHA_HORA < ".$fc, $corte)."
			INSERT INTO T_RIFA_ESPEJO_LABS (FECHA_CORTE, PASSPORT, NIT, COMPRA)
			SELECT ".$fc.", C.PASSPORT, C.NIT, C.COMPRA
			FROM C
			WHERE C.COMPRA <> 0;

			INSERT INTO T_RIFA_ESPEJO (FECHA_CORTE, PASSPORT, GRUPO, COMPRA_TOTAL, COMPRA_LABS, N_LABS)
			SELECT
				".$fc.",
				X.PASSPORT,
				(SELECT TOP 1 T.GRUPO1 FROM T_TERCEROS T WHERE T.CODIGO_SAP = X.PASSPORT),
				X.TOTAL,
				ISNULL(L.COMPRA, 0),
				ISNULL(L.N, 0)
			FROM (
				SELECT P.PASSPORT, TOTAL = SUM(P.VALOR_TOTAL)
				FROM ".EV_TABLA_PEDIDOS." P
				WHERE P.FECHA_HORA < ".$fc." AND (".EV_PEDIDOS_WHERE.")
				GROUP BY P.PASSPORT
			) X
			LEFT JOIN (
				SELECT PASSPORT, COMPRA = SUM(COMPRA), N = SUM(CASE WHEN COMPRA > 0 THEN 1 ELSE 0 END)
				FROM T_RIFA_ESPEJO_LABS
				WHERE FECHA_CORTE = ".$fc."
				GROUP BY PASSPORT
			) L ON L.PASSPORT = X.PASSPORT;

			UPDATE T_RIFA_ESPEJO_CONTROL
			SET CLIENTES = (SELECT COUNT(*) FROM T_RIFA_ESPEJO WHERE FECHA_CORTE = ".$fc.")
			WHERE FECHA_CORTE = ".$fc.";
		END
		COMMIT;
		SELECT n = 1");
	 return evEspejoEstado($corte);
 }

 /* =====================================================================
    EVALUACIÓN DE LAS DINÁMICAS
    ===================================================================== */
 function evRegla($din, $grupo){
	 return isset($din['reglas'][$grupo]) ? $din['reglas'][$grupo] : $din['reglas']['*'];
 }

 // Boletas = base + 1 por cada 'extra_cada' completo por encima de 'extra_desde'
 function evBoletas($regla, $total){
	 $b = (int)$regla['boletas'];
	 if($regla['extra_cada'] > 0 && $total >= $regla['extra_desde'] + $regla['extra_cada']){
		 $b += (int)floor(($total - $regla['extra_desde']) / $regla['extra_cada']);
	 }
	 return $b;
 }

 // Monto total con el que se gana la siguiente boleta adicional (0 = no aplica)
 function evSiguienteBoleta($regla, $total){
	 if($regla['extra_cada'] <= 0){ return 0; }
	 $n = $total > $regla['extra_desde'] ? floor(($total - $regla['extra_desde']) / $regla['extra_cada']) : 0;
	 return $regla['extra_desde'] + ($n + 1) * $regla['extra_cada'];
 }

 function evEvaluar($pass){
	 $p   = evEsc($pass);
	 $ctx = evContexto();

	 $cli = evRows("SELECT TOP 1
						codigo_sap      = CODIGO_SAP,
						nombres         = NOMBRES,
						razon_comercial = RAZON_COMERCIAL,
						grupo           = ISNULL(GRUPO1, '')
					FROM T_TERCEROS
					WHERE CODIGO_SAP = '".$p."'");
	 if(count($cli) == 0){
		 evError('El código SAP '.$pass.' no existe');
	 }
	 $grupo = trim($cli[0]['grupo']);

	 // Después del corte, las compras de la mañana salen del espejo
	 $usarEspejo = !$ctx['manana_abierta'] && $ctx['espejo'] !== null;
	 $fc = evSqlFecha($ctx['corte']);

	 $labs = evRows(evSqlCte("P.PASSPORT = '".$p."'", $ctx['corte'])."
		SELECT
			nit       = R.NIT,
			nombres   = R.NOMBRES,
			compra    = ISNULL(C.COMPRA, 0),
			compra_am = ".($usarEspejo ? "ISNULL(E.COMPRA, 0)" : "ISNULL(C.COMPRA_AM, 0)")."
		FROM R
		LEFT JOIN C ON C.NIT = R.NIT
		".($usarEspejo ? "LEFT JOIN T_RIFA_ESPEJO_LABS E ON E.FECHA_CORTE = ".$fc." AND E.PASSPORT = '".$p."' AND E.NIT = R.NIT" : "")."
		ORDER BY
			ISNULL(C.COMPRA, 0) DESC,
			R.NOMBRES");

	 $tot = evRows("SELECT
						total    = ISNULL(SUM(P.VALOR_TOTAL), 0),
						total_am = ".($usarEspejo
							? "ISNULL((SELECT TOP 1 E.COMPRA_TOTAL FROM T_RIFA_ESPEJO E WHERE E.FECHA_CORTE = ".$fc." AND E.PASSPORT = '".$p."'), 0)"
							: "ISNULL(SUM(CASE WHEN P.FECHA_HORA < ".$fc." THEN P.VALOR_TOTAL END), 0)")."
					FROM ".EV_TABLA_PEDIDOS." P
					WHERE P.PASSPORT = '".$p."' AND (".EV_PEDIDOS_WHERE.")");
	 $total   = (float)$tot[0]['total'];
	 $totalAm = (float)$tot[0]['total_am'];

	 $compraLabs = 0; $compraLabsAm = 0; $labsConCompra = 0;
	 foreach($labs as $i => $l){
		 $labs[$i]['compra']    = (float)$l['compra'];
		 $labs[$i]['compra_am'] = (float)$l['compra_am'];
		 $compraLabs   += $labs[$i]['compra'];
		 $compraLabsAm += $labs[$i]['compra_am'];
		 if($labs[$i]['compra'] > 0){ $labsConCompra++; }
	 }

	 $usu = evRows("SELECT registros = COUNT(*)
					FROM T_USUARIOS
					WHERE CODIGO_SAP = '".$p."' AND TIPO_USUARIO = ".(int)EV_TIPO_USUARIO_CLIENTE);
	 $registrado = (int)$usu[0]['registros'] > 0;

	 $gan = evRows("SELECT
						id                = ID,
						dinamica          = DINAMICA,
						compra_total      = COMPRA_TOTAL,
						n_labs            = N_LABS,
						boletas           = BOLETAS,
						boletas_redimidas = BOLETAS_REDIMIDAS,
						redimido          = CAST(REDIMIDO AS int),
						fecha             = CONVERT(varchar(19), FECHA_HORA, 120),
						fecha_redencion   = CONVERT(varchar(19), FECHA_REDENCION, 120)
					FROM T_RIFA_DINAMICAS_GANADORES
					WHERE PASSPORT = '".$p."'");
	 $ganadas = array();
	 foreach($gan as $g){
		 foreach(array('id', 'n_labs', 'boletas', 'boletas_redimidas', 'redimido') as $k){ $g[$k] = (int)$g[$k]; }
		 $g['compra_total'] = (float)$g['compra_total'];
		 $ganadas[$g['dinamica']] = $g;
	 }

	 $dinamicas = array();
	 foreach(evDinamicas() as $key => $d){
		 $am      = $d['ventana'] == 'manana';
		 $regla   = evRegla($d, $grupo);
		 $campo   = $am ? 'compra_am' : 'compra';
		 $tVent   = $am ? $totalAm : $total;
		 $lVent   = $am ? $compraLabsAm : $compraLabs;
		 $abierta = $am ? $ctx['manana_abierta'] : $ctx['tarde_abierta'];

		 $labsCumple = 0;
		 foreach($labs as $l){
			 if($l[$campo] > 0 && $l[$campo] >= $regla['lab_min']){ $labsCumple++; }
		 }
		 $cumpleTotal = $tVent >= $regla['total_min'];
		 $cumpleLabs  = $labsCumple >= $regla['n_labs'];
		 $cumple      = $cumpleTotal && $cumpleLabs;
		 $boletas     = $cumple ? evBoletas($regla, $tVent) : 0;
		 $ganador     = isset($ganadas[$key]) ? $ganadas[$key] : null;

		 // Exclusión entre las dos dinámicas de la mañana (opcional)
		 $excluido = false;
		 if(EV_MANANA_EXCLUYENTE && $am && $ganador === null){
			 foreach(evDinamicas() as $k2 => $d2){
				 if($k2 != $key && $d2['ventana'] == 'manana' && isset($ganadas[$k2])){ $excluido = true; }
			 }
		 }

		 $okRegistro = $registrado || !EV_REQUIERE_REGISTRO;
		 $adicionales = 0;
		 if($ganador !== null && $abierta && $boletas > $ganador['boletas']){
			 $adicionales = $boletas - $ganador['boletas'];
		 }

		 if($ganador !== null){
			 $estado = $adicionales > 0 && $okRegistro ? 'ADICIONALES' : 'GANADOR';
		 }elseif($excluido){
			 $estado = 'EXCLUIDO';
		 }elseif($am && !$abierta){
			 $estado = 'CERRADA';
		 }elseif(!$am && !$abierta){
			 $estado = 'NO_HABILITADA';
		 }elseif($cumple && !$okRegistro){
			 $estado = 'NO_REGISTRADO';
		 }elseif($cumple){
			 $estado = 'CUMPLE';
		 }else{
			 $estado = 'PENDIENTE';
		 }

		 $dinamicas[] = array(
			 'key'            => $key,
			 'nombre'         => $d['nombre'],
			 'premio'         => $d['premio'],
			 'ventana'        => $d['ventana'],
			 'abierta'        => $abierta,
			 'regla'          => $regla,
			 'grupo_especial' => isset($d['reglas'][$grupo]),
			 'compra_total'   => $tVent,
			 'compra_labs'    => $lVent,
			 'labs_cumple'    => $labsCumple,
			 'cumple_total'   => $cumpleTotal,
			 'cumple_labs'    => $cumpleLabs,
			 'cumple'         => $cumple,
			 'boletas'        => $boletas,
			 'siguiente'      => $cumple ? evSiguienteBoleta($regla, $tVent) : 0,
			 'falta_total'    => max(0, $regla['total_min'] - $tVent),
			 'falta_labs'     => max(0, $regla['n_labs'] - $labsCumple),
			 'excluido'       => $excluido,
			 'ganador'        => $ganador,
			 'adicionales'    => $adicionales,
			 'puede_registrar'=> $abierta && $cumple && $okRegistro && !$excluido && ($ganador === null || $adicionales > 0),
			 'estado'         => $estado
		 );
	 }

	 return array(
		 'ok'              => true,
		 'ahora'           => $ctx['ahora'],
		 'corte'           => $ctx['corte'],
		 'tarde'           => $ctx['tarde'],
		 'usa_espejo'      => $usarEspejo,
		 'cliente'         => $cli[0],
		 'grupo'           => $grupo,
		 'registrado'      => $registrado,
		 'compra_total'    => $total,
		 'compra_total_am' => $totalAm,
		 'compra_labs'     => $compraLabs,
		 'compra_labs_am'  => $compraLabsAm,
		 'labs'            => $labs,
		 'n_labs'          => count($labs),
		 'labs_con_compra' => $labsConCompra,
		 'dinamicas'       => $dinamicas
	 );
 }

 function evBuscarDinamica($ev, $key){
	 foreach($ev['dinamicas'] as $d){
		 if($d['key'] == $key){ return $d; }
	 }
	 return null;
 }

 function evNombreCliente($c){
	 return $c['razon_comercial'] != '' ? $c['razon_comercial'] : $c['nombres'];
 }

 switch($_POST["op"]){
	 case 1:{
	 		 $sql="SELECT
			        T.razon_comercial,
					T.nombres,
					compras=ISNULL((
					   SELECT
						  SUM(P.VALOR_TOTAL)
					   FROM T_PEDIDOS P
					   WHERE P.PASSPORT=T.CODIGO_SAP
					),0)+ISNULL((
					   SELECT
						  SUM(P.VALOR_TOTAL)
					   FROM T_PEDIDOS_DIA1 P
					   WHERE P.PASSPORT=T.CODIGO_SAP
					),0)+ISNULL((
					   SELECT
						  SUM(P.VALOR_TOTAL)
					   FROM T_PEDIDOS_DIA2 P
					   WHERE P.PASSPORT=T.CODIGO_SAP
					),0)+ISNULL((
					   SELECT
						  SUM(P.VALOR_TOTAL)
					   FROM T_PEDIDOS_DIA3 P
					   WHERE P.PASSPORT=T.CODIGO_SAP
					),0),
					boletas_imp=isnull((
						select top 1
						  boletas1
						from t_usuarios
						where codigo_sap=t.codigo_sap
					),0)
			 	   FROM T_TERCEROS T
				   WHERE
				    T.CODIGO_SAP='".$_POST["Pass"]."' ";
			 $Q=GenerarArray($sql,"");
			 echo json_encode($Q);
	 mssql_close();
	 }break;
	 case 2:{

	   $sql ="SELECT
				 R.nit,
				 R.grupo_articulos,
				 R.nombres,
				 R.monto,
				 CASE
				  WHEN R.TIPO = 'C' THEN 'SORTEO'
				  WHEN R.TIPO = 'M' THEN 'MOTO'
				  WHEN R.TIPO = 'B' THEN 'BONO'
				 END AS tipo,
				compras = ISNULL((SELECT
									   SUM(P.VALOR_TOTAL)
									 FROM T_PEDIDOS P
									 INNER JOIN T_MATERIALES M ON P.CODIGO = M.CODIGO_MATERIAL
									 INNER JOIN T_TERCEROS   T ON T.CODIGO_SAP = M.CODIGO_SAP
									 WHERE
									 P.PASSPORT='".$_POST["Pass"]."' AND
									 T.NIT LIKE('%'+R.NIT+'%')
									),0),
				ngrupo
				FROM T_RIFAS R

				ORDER BY
				ngrupo,
				R.tipo,
                R.nombres";

		$Q=GenerarArray($sql,"");
		echo json_encode($Q);
	 }break;
	 case 3:
	     $sql ="SELECT
				  SUM(P.VALOR_TOTAL) AS total
				FROM T_PEDIDOS P
				INNER JOIN T_MATERIALES M ON P.CODIGO = M.CODIGO_MATERIAL
				INNER JOIN T_TERCEROS   T ON T.CODIGO_SAP = M.CODIGO_SAP
				WHERE
					P.PASSPORT='".trim($_POST["Pass"])."' ";
		 $q = GenerarArray($sql,"");
		echo json_encode($q);
	break;
	case 4:
	 $sql ="SELECT
				P.PASSPORT,
				T.NOMBRES,
				SUM(P.VALOR_TOTAL) AS VALOR
				FROM T_PEDIDOS P

				INNER JOIN T_TERCEROS T ON T.CODIGO_SAP = P.CODIGO_SAP
				WHERE
				CAST(P.FECHA_HORA AS TIME) BETWEEN '13:00' AND '17:00' AND
				P.PASSPORT = '".$_POST['pass']."'
				GROUP BY
				P.PASSPORT,
				T.NOMBRES";
	  $q = GenerarArray($sql,"");
	  echo json_encode($q);
	break;
	case 5:
	 $sql ="SELECT
				compras_g = isnull((select sum(valor_total) from t_pedidos where passport= o.passport),0),
				compras_m = ISNULL((select sum(valor_total) from t_pedidos where passport= '0005' and
							  cast(fecha_hora as time)>=CAST('07:00:00' as time) and
							  cast(fecha_hora as time)<=CAST('10:00:00' as time)),0),
				compras_t = ISNULL((select sum(valor_total) from t_pedidos where passport= '0005' and
							cast(fecha_hora as time)>=CAST('15:00:00' as time) and
							cast(fecha_hora as time)<=CAST('20:00:00' as time)),0)
				FROM T_USUARIOS O
				WHERE
				O.PASSPORT= '".$_POST['Pass']."' and
				boletas3=0";
	  $q = GenerarArray($sql,"");
	  echo json_encode($q);
	break;

	 case "S_CLIENTE_PED":
		 $sql="SELECT
					V.PASSPORT,
					SUM(v.VALOR_TOTAL)
					FROM T_PEDIDOS V
					INNER JOIN T_MATERIALES M ON 	 M.CODIGO_MATERIAL	=V.CODIGO
					INNER JOIN T_TERCEROS   T ON	 T.CODIGO_SAP		=M.CODIGO_SAP
					INNER JOIN T_RIFAS  	R ON	 T.NIT LIKE '%'+R.NIT+'%'
					GROUP BY
					V.PASSPORT
					ORDER BY
					SUM(V.VALOR_TOTAL) ASC";
		 $q = GenerarArray($sql,"");
	     echo json_encode($q);
	break;
	case "I_BOLETAS":
			 $insert="INSERT INTO T_BOLETAS_TOTALES(PASSPORT,CANT,COMPRA,CANT_BOL)
					VALUES('".$_POST["passport"]."',
					        ".$_POST["cant"].",
							".$_POST["compra"].",
							".$_POST["cantbol"].") ";
			 $query=mssql_query($insert);
			 if($query){
				 echo 1;
			 }else{echo 0;}
	break;


	/* =====================================================================
	   DINÁMICAS DEL EVENTO
	   ===================================================================== */

	// Reglas, horarios y estado del espejo
	case "EV_CONFIG":
		 $ctx = evContexto();
		 $ds  = array();
		 foreach(evDinamicas() as $k => $d){
			 $reglas = array();
			 foreach($d['reglas'] as $g => $r){
				 $r['grupo'] = $g;
				 $reglas[] = $r;
			 }
			 $ds[] = array('key' => $k, 'nombre' => $d['nombre'], 'premio' => $d['premio'],
			               'ventana' => $d['ventana'], 'reglas' => $reglas);
		 }
		 evJson(array(
			 'ok'             => true,
			 'ahora'          => $ctx['ahora'],
			 'corte'          => $ctx['corte'],
			 'tarde'          => $ctx['tarde'],
			 'hora_corte'     => EV_HORA_CORTE_MANANA,
			 'hora_tarde'     => EV_HORA_INICIO_TARDE,
			 'manana_abierta' => $ctx['manana_abierta'],
			 'tarde_abierta'  => $ctx['tarde_abierta'],
			 'grupo_especial' => EV_GRUPO_ESPECIAL,
			 'espejo'         => $ctx['espejo'],
			 'dinamicas'      => $ds
		 ));
	break;

	// Consulta completa de un cliente
	case "EV_CONSULTA":
		 evJson(evEvaluar(evPass()));
	break;

	// Registra al cliente como ganador de una dinámica (o le suma las boletas adicionales)
	case "EV_REGISTRAR":
		 $pass = evPass();
		 $key  = evDinamicaPost();
		 $ev   = evEvaluar($pass);
		 $d    = evBuscarDinamica($ev, $key);

		 if($d['ganador'] !== null && $d['adicionales'] <= 0){
			 evError('El cliente ya ganó '.$d['nombre'].' ('.$d['ganador']['fecha'].') con '.$d['ganador']['boletas'].' boleta(s)');
		 }
		 if(!$d['abierta']){
			 evError($d['ventana'] == 'manana'
				 ? $d['nombre'].' cerró a las '.EV_HORA_CORTE_MANANA
				 : $d['nombre'].' se habilita a partir de las '.EV_HORA_INICIO_TARDE);
		 }
		 if($d['excluido']){
			 evError('El cliente ya ganó otra dinámica de la mañana');
		 }
		 if(!$d['cumple']){
			 evError('El cliente no cumple: compra $'.number_format($d['compra_total'], 0, ',', '.').
			         ' de $'.number_format($d['regla']['total_min'], 0, ',', '.').' y '.
			         $d['labs_cumple'].' de '.$d['regla']['n_labs'].' laboratorios con $'.
			         number_format($d['regla']['lab_min'], 0, ',', '.'));
		 }
		 if(EV_REQUIERE_REGISTRO && !$ev['registrado']){
			 evError('El cliente no está registrado en el evento (T_USUARIOS)');
		 }

		 $p   = evEsc($pass);
		 $k   = evEsc($key);
		 $ctx = evContexto();

		 if($d['ganador'] !== null){
			 // Ya ganó y, antes del cierre, compró más: se actualizan sus boletas
			 $r = evRows("SET NOCOUNT ON;
				UPDATE T_RIFA_DINAMICAS_GANADORES SET
					BOLETAS      = ".(int)$d['boletas'].",
					COMPRA_TOTAL = ".evNum($d['compra_total']).",
					COMPRA_LABS  = ".evNum($d['compra_labs']).",
					N_LABS       = ".(int)$d['labs_cumple'].",
					REDIMIDO     = 0
				WHERE ID = ".(int)$d['ganador']['id']." AND BOLETAS < ".(int)$d['boletas'].";
				SELECT n = @@ROWCOUNT");
			 if((int)$r[0]['n'] == 0){ evError('No se pudieron actualizar las boletas'); }
			 evJson(array('ok' => true, 'id' => (int)$d['ganador']['id'], 'boletas' => (int)$d['boletas'],
			              'msg' => 'Se sumaron '.$d['adicionales'].' boleta(s). Total: '.$d['boletas']));
		 }

		 // Exclusión entre dinámicas de la mañana dentro de la misma sentencia
		 $excl = '';
		 if(EV_MANANA_EXCLUYENTE && $d['ventana'] == 'manana'){
			 $otras = array();
			 foreach(evDinamicas() as $k2 => $d2){
				 if($k2 != $key && $d2['ventana'] == 'manana'){ $otras[] = "'".evEsc($k2)."'"; }
			 }
			 if(count($otras) > 0){
				 $excl = " AND NOT EXISTS (SELECT 1 FROM T_RIFA_DINAMICAS_GANADORES WITH (UPDLOCK, HOLDLOCK)
				                           WHERE PASSPORT = '".$p."' AND DINAMICA IN (".implode(',', $otras)."))";
			 }
		 }
		 // Por si el cierre ocurre entre la evaluación y el insert
		 $ventana = $d['ventana'] == 'manana'
			 ? " AND GETDATE() < ".evSqlFecha($ctx['corte'])
			 : " AND GETDATE() >= ".evSqlFecha($ctx['tarde']);

		 $r = evRows("SET NOCOUNT ON;
			INSERT INTO T_RIFA_DINAMICAS_GANADORES
				(DINAMICA, PASSPORT, NOMBRES, GRUPO, COMPRA_TOTAL, COMPRA_LABS, N_LABS, BOLETAS, USUARIO)
			SELECT
				'".$k."',
				'".$p."',
				'".evEsc(substr(evNombreCliente($ev['cliente']), 0, 120))."',
				'".evEsc($ev['grupo'])."',
				".evNum($d['compra_total']).",
				".evNum($d['compra_labs']).",
				".(int)$d['labs_cumple'].",
				".(int)$d['boletas'].",
				'".evUsuario()."'
			WHERE
				NOT EXISTS (SELECT 1 FROM T_RIFA_DINAMICAS_GANADORES WITH (UPDLOCK, HOLDLOCK)
				            WHERE DINAMICA = '".$k."' AND PASSPORT = '".$p."')".$excl.$ventana.";
			SELECT id = CAST(SCOPE_IDENTITY() AS int)");
		 if(empty($r[0]['id'])){
			 evError('No se registró: el cliente ya ganó esta dinámica o la dinámica cerró');
		 }
		 evJson(array('ok' => true, 'id' => (int)$r[0]['id'], 'boletas' => (int)$d['boletas'],
		              'msg' => 'Ganador registrado en '.$d['nombre'].' con '.$d['boletas'].' boleta(s)'));
	break;

	// Entrega (redime) boletas de un ganador. cantidad vacía = todas las pendientes
	case "EV_REDIMIR":
		 $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
		 if($id <= 0){ evError('Ganador inválido'); }
		 $g = evRows("SELECT boletas = BOLETAS, redimidas = BOLETAS_REDIMIDAS FROM T_RIFA_DINAMICAS_GANADORES WHERE ID = ".$id);
		 if(count($g) == 0){ evError('Ganador no encontrado'); }
		 $pend = (int)$g[0]['boletas'] - (int)$g[0]['redimidas'];
		 if($pend <= 0){ evError('El cliente ya redimió todas sus boletas ('.$g[0]['boletas'].')'); }
		 $cant = isset($_POST['cantidad']) && $_POST['cantidad'] !== '' ? (int)$_POST['cantidad'] : $pend;
		 if($cant <= 0 || $cant > $pend){ evError('Cantidad inválida. Boletas pendientes: '.$pend); }

		 $r = evRows("SET NOCOUNT ON;
			UPDATE T_RIFA_DINAMICAS_GANADORES SET
				BOLETAS_REDIMIDAS = BOLETAS_REDIMIDAS + ".$cant.",
				REDIMIDO          = CASE WHEN BOLETAS_REDIMIDAS + ".$cant." >= BOLETAS THEN 1 ELSE 0 END,
				FECHA_REDENCION   = GETDATE(),
				USUARIO_REDENCION = '".evUsuario()."'
			WHERE ID = ".$id." AND BOLETAS_REDIMIDAS = ".(int)$g[0]['redimidas']." AND BOLETAS_REDIMIDAS + ".$cant." <= BOLETAS;
			SELECT n = @@ROWCOUNT");
		 if((int)$r[0]['n'] == 0){ evError('Las boletas cambiaron mientras se redimían. Consulte de nuevo.'); }
		 $desde = (int)$g[0]['redimidas'] + 1;
		 evJson(array('ok' => true, 'id' => $id, 'desde' => $desde, 'hasta' => $desde + $cant - 1,
		              'msg' => 'Boletas redimidas: '.$cant));
	break;

	// Listado de ganadores (filtro opcional por dinámica) + resumen
	case "EV_GANADORES":
		 $key = isset($_POST['dinamica']) ? trim($_POST['dinamica']) : '';
		 $ds  = evDinamicas();
		 $w   = isset($ds[$key]) ? "WHERE G.DINAMICA = '".evEsc($key)."'" : "";
		 $rows = evRows("SELECT
							id                = G.ID,
							dinamica          = G.DINAMICA,
							passport          = G.PASSPORT,
							nombres           = G.NOMBRES,
							grupo             = G.GRUPO,
							compra_total      = G.COMPRA_TOTAL,
							compra_labs       = G.COMPRA_LABS,
							n_labs            = G.N_LABS,
							boletas           = G.BOLETAS,
							boletas_redimidas = G.BOLETAS_REDIMIDAS,
							redimido          = CAST(G.REDIMIDO AS int),
							usuario           = G.USUARIO,
							fecha             = CONVERT(varchar(19), G.FECHA_HORA, 120),
							fecha_redencion   = CONVERT(varchar(19), G.FECHA_REDENCION, 120)
						FROM T_RIFA_DINAMICAS_GANADORES G
						".$w."
						ORDER BY G.DINAMICA, G.ID");
		 $res = evRows("SELECT
							dinamica  = DINAMICA,
							ganadores = COUNT(*),
							boletas   = SUM(BOLETAS),
							redimidas = SUM(BOLETAS_REDIMIDAS)
						FROM T_RIFA_DINAMICAS_GANADORES
						GROUP BY DINAMICA");
		 evJson(array('ok' => true, 'rows' => $rows, 'resumen' => $res));
	break;

	// Genera el espejo de las 12:00 manualmente (solo después del corte)
	case "EV_ESPEJO_GENERAR":
		 $ctx = evContexto();
		 if($ctx['manana_abierta']){
			 evError('El espejo se genera después de las '.EV_HORA_CORTE_MANANA);
		 }
		 evJson(array('ok' => true, 'espejo' => evEspejoGenerar($ctx['corte'])));
	break;

	// Clientes del espejo con su estado en las dinámicas de la mañana
	case "EV_ESPEJO_LISTA":
		 $ctx = evContexto();
		 if($ctx['espejo'] === null){
			 evJson(array('ok' => true, 'espejo' => null, 'rows' => array()));
		 }
		 $fc = evSqlFecha($ctx['corte']);
		 $am = array();
		 foreach(evDinamicas() as $k => $d){
			 if($d['ventana'] == 'manana'){ $am[] = "'".evEsc($k)."'"; }
		 }
		 $rows = evRows("SELECT
							passport     = E.PASSPORT,
							nombres      = (SELECT TOP 1 ISNULL(NULLIF(LTRIM(T.RAZON_COMERCIAL), ''), T.NOMBRES)
							                FROM T_TERCEROS T WHERE T.CODIGO_SAP = E.PASSPORT),
							grupo        = E.GRUPO,
							compra_total = E.COMPRA_TOTAL,
							compra_labs  = E.COMPRA_LABS,
							n_labs       = E.N_LABS,
							ganadas      = (SELECT COUNT(*) FROM T_RIFA_DINAMICAS_GANADORES G
							                WHERE G.PASSPORT = E.PASSPORT AND G.DINAMICA IN (".(count($am) ? implode(',', $am) : "''")."))
						FROM T_RIFA_ESPEJO E
						WHERE E.FECHA_CORTE = ".$fc."
						ORDER BY E.COMPRA_TOTAL DESC");
		 evJson(array('ok' => true, 'espejo' => $ctx['espejo'], 'rows' => $rows));
	break;
 }
?>
