<?php
/* =====================================================================
   REPORTE DEL EVENTO - clientes registrados (presencial / virtual)
   Complementa models/Reportes.php (op 1 = ventas por zona, sin cambios).
   ===================================================================== */

// Tablas de pedidos que suman la compra del cliente en el evento
function rpTablasPedidos(){
	return array('T_PEDIDOS');
}

ob_start();
require_once 'funciones.php';
session_start();
conectar();

function rpJson($data){
	while(ob_get_level() > 0){ ob_end_clean(); }
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($data);
	exit;
}

function rpUtf8($v){
	return (is_string($v) && !preg_match('//u', $v)) ? utf8_encode($v) : $v;
}

function rpRows($sql){
	$q = mssql_query($sql);
	if($q === false){
		rpJson(array('ok' => false, 'msg' => 'Error en base de datos: '.rpUtf8(mssql_get_last_message())));
	}
	$rows = array();
	if(is_resource($q)){
		while($r = mssql_fetch_assoc($q)){
			foreach($r as $k => $v){
				$r[$k] = is_string($v) ? trim(rpUtf8($v)) : $v;
			}
			$rows[] = $r;
		}
		mssql_free_result($q);
	}
	return $rows;
}

$op = isset($_POST['op']) ? $_POST['op'] : '';

switch($op){

	/* Un registro por cliente registrado (T_USUARIOS, TIPO_USUARIO = 2) con
	   su tipo de registro, oficina, zona y compra en el evento.
	   El controlador arma con esto: P/V por oficina, con/sin compra y la
	   lista de clientes sin compras. */
	case 'CLIENTES_REGISTRADOS':
		$ped = array();
		foreach(rpTablasPedidos() as $t){
			$ped[] = "SELECT PASSPORT, VALOR_TOTAL FROM ".$t;
		}
		$rows = rpRows("SET NOCOUNT ON;
			WITH U AS (
				SELECT
					CODIGO_SAP = LTRIM(RTRIM(U.CODIGO_SAP)),
					-- Si tiene varios registros, prima el presencial ('P' < 'V')
					TIPO       = MIN(NULLIF(UPPER(LTRIM(RTRIM(ISNULL(U.MOD_REGISTRO, '')))), ''))
				FROM T_USUARIOS U
				WHERE U.TIPO_USUARIO = 2
				  AND ISNULL(LTRIM(RTRIM(U.CODIGO_SAP)), '') <> ''
				GROUP BY LTRIM(RTRIM(U.CODIGO_SAP))
			), V AS (
				SELECT PASSPORT = LTRIM(RTRIM(X.PASSPORT)), VENTA = SUM(X.VALOR_TOTAL)
				FROM (".implode(" UNION ALL ", $ped).") X
				GROUP BY LTRIM(RTRIM(X.PASSPORT))
			)
			SELECT
				sap      = U.CODIGO_SAP,
				nombre   = ISNULL(NULLIF(LTRIM(T.RAZON_COMERCIAL), ''), ISNULL(T.NOMBRES, '')),
				ciudad   = ISNULL(T.CIUDAD, ''),
				oficina  = ISNULL(CONVERT(varchar(10), T.OFICINA_VENTAS), ''),
				zona     = ISNULL(CONVERT(varchar(10), T.ZONA_VENTAS), ''),
				vendedor = ISNULL(Z.ZONA_DESCRIPCION, ''),
				tipo     = ISNULL(U.TIPO, ''),
				venta    = ISNULL(V.VENTA, 0)
			FROM U
			OUTER APPLY (
				SELECT TOP 1 RAZON_COMERCIAL, NOMBRES, CIUDAD, OFICINA_VENTAS, ZONA_VENTAS
				FROM T_TERCEROS
				WHERE CODIGO_SAP = U.CODIGO_SAP
			) T
			LEFT JOIN T_ZONAS_VENTAS Z ON Z.ZONA_VENTAS = T.ZONA_VENTAS
			LEFT JOIN V ON V.PASSPORT = U.CODIGO_SAP
			ORDER BY oficina, zona, nombre");
		rpJson(array('ok' => true, 'rows' => $rows));
	break;

	default:
		rpJson(array('ok' => false, 'msg' => 'Operación inválida'));
}
?>
