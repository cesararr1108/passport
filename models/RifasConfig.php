<?php
/* =====================================================================
   CONFIGURACIÓN DE LAS DINÁMICAS DEL EVENTO
   Todos los montos, horas y cantidades se cambian aquí.
   (PHP 5.4: las constantes no pueden ser arreglos, por eso las reglas
    de cada dinámica se devuelven desde la función evDinamicas()).
   ===================================================================== */

// Tabla de pedidos del día del evento
define('EV_TABLA_PEDIDOS', 'T_PEDIDOS');

// Filtro adicional sobre los pedidos (alias P). Ej: "P.CONFIRMADO = 'S'"
define('EV_PEDIDOS_WHERE', '1 = 1');

// Fecha del evento AAAA-MM-DD. Vacío = fecha actual del servidor SQL
define('EV_FECHA_EVENTO', '');

// Las dinámicas de la mañana sólo se pueden registrar HASTA esta hora (no hay espejo)
define('EV_HORA_CORTE_MANANA', '12:00');

// Las dinámicas de la tarde se habilitan A PARTIR de esta hora
define('EV_HORA_INICIO_TARDE', '18:00');

// Grupo especial de clientes (T_TERCEROS.GRUPO1)
define('EV_GRUPO_ESPECIAL', '100');

// El cliente debe estar registrado en el evento (T_USUARIOS) para ganar
define('EV_REQUIERE_REGISTRO', true);
define('EV_TIPO_USUARIO_CLIENTE', 2);

// true  = quien gana UNA dinámica de la mañana ya no participa en la OTRA de la mañana
// false = la exclusión es por dinámica (gana la ruleta y aún puede ganar el iPhone Pro)
define('EV_MANANA_EXCLUYENTE', false);

// true  = el NIT del tercero debe ser igual al de T_RIFAS
// false = se usa LIKE '%NIT%' (comportamiento anterior)
define('EV_NIT_EXACTO', false);

/* ---------------------------------------------------------------------
   Reglas por dinámica.
   ventana : 'manana' (registro habilitado sólo hasta EV_HORA_CORTE_MANANA)
             'tarde'  (desde EV_HORA_INICIO_TARDE, compras de todo el día)
   reglas  : por grupo (GRUPO1 del cliente). '*' = demás grupos
     total_min   compra total mínima (todos los laboratorios)
     lab_min     compra mínima en cada laboratorio participante
     n_labs      cantidad de laboratorios participantes que deben cumplir lab_min
     boletas     boletas que gana al cumplir
     extra_desde / extra_cada : +1 boleta por cada 'extra_cada' por encima de 'extra_desde'
                  (0 = sin boletas adicionales)
   --------------------------------------------------------------------- */
function evDinamicas(){
	return array(
		'RULETA' => array(
			'nombre'  => 'Dinámica #1 - Ruleta',
			'premio'  => 'RULETA',
			'ventana' => 'manana',
			'reglas'  => array(
				'*' => array('total_min' => 0, 'lab_min' => 100000, 'n_labs' => 5,
				             'boletas' => 1, 'extra_desde' => 0, 'extra_cada' => 0)
			)
		),
		'IPHONE_PRO' => array(
			'nombre'  => 'Dinámica #2 - iPhone 18 Pro',
			'premio'  => 'RIFA IPHONE 18 PRO',
			'ventana' => 'manana',
			'reglas'  => array(
				EV_GRUPO_ESPECIAL => array('total_min' => 2000000, 'lab_min' => 100000, 'n_labs' => 10,
				             'boletas' => 1, 'extra_desde' => 3000000, 'extra_cada' => 1000000),
				'*' => array('total_min' => 8000000, 'lab_min' => 1000000, 'n_labs' => 5,
				             'boletas' => 1, 'extra_desde' => 0, 'extra_cada' => 0)
			)
		),
		'IPHONE_BONO' => array(
			'nombre'  => 'Dinámica #3 - iPhone 18 y bono $3.000.000',
			'premio'  => 'RIFA IPHONE 18 / BONO $3.000.000',
			'ventana' => 'tarde',
			'reglas'  => array(
				EV_GRUPO_ESPECIAL => array('total_min' => 6000000, 'lab_min' => 100000, 'n_labs' => 20,
				             'boletas' => 1, 'extra_desde' => 0, 'extra_cada' => 0),
				'*' => array('total_min' => 15000000, 'lab_min' => 1000000, 'n_labs' => 10,
				             'boletas' => 1, 'extra_desde' => 0, 'extra_cada' => 0)
			)
		),
		'ELECTRO' => array(
			'nombre'  => 'Sorteo de electrodomésticos',
			'premio'  => 'SORTEO ELECTRODOMESTICOS',
			'ventana' => 'tarde',
			'reglas'  => array(
				'*' => array('total_min' => 1000000, 'lab_min' => 0, 'n_labs' => 0,
				             'boletas' => 1, 'extra_desde' => 0, 'extra_cada' => 0)
			)
		)
	);
}
