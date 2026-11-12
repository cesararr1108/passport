<?php session_start(); ?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Ventas del evento</title>
<link type="text/css" rel="stylesheet" href="../resources/css/bootstrap.min.css"/>
<link type="text/css" rel="stylesheet" href="../resources/css/menu/font-awesome.min.css"/>
<link type="text/css" rel="stylesheet" href="../resources/css/BootstrapTabs.css?<?php echo rand()?>"/>
<link type="text/css" rel="stylesheet" href="../resources/css/start/jquery-ui-1.9.2.custom.css"/>
<link type="text/css" rel="stylesheet" href="../lib/SweetAlert/sweet-alert.css"/>

<script type="text/javascript" src="../lib/js/jquery-2.1.1.min.js"></script>
<script type="text/javascript" src="../lib/js/jquery-ui-1.9.2.custom.js"></script>
<script type="text/javascript" src="../lib/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../lib/js/funciones.js?<?php echo rand()?>"></script>
<script type="text/javascript" src="../lib/SweetAlert/sweet-alert.min.js"></script>
<script type="text/javascript" src="../controllers/Rpt01.js?<?php echo rand()?>"></script>
<style>
	body{ padding: 10px 0; background: #f4f6f9; }
	.nav-tabs > li > a{ font-weight: bold; }
	.tab-content{ background: #fff; border: 1px solid #ddd; border-top: 0; padding: 15px; }
	.rp-kpi{ background: #fff; border: 1px solid #e1e5eb; border-radius: 8px; padding: 12px 14px; margin-bottom: 15px; min-height: 104px; }
	.rp-kpi small{ color: #777; font-weight: bold; letter-spacing: .4px; }
	.rp-kpi .rp-val{ font-size: 24px; font-weight: bold; margin-top: 4px; }
	.rp-kpi .rp-sub{ color: #777; font-size: 12px; }
	.rp-titulo{ font-size: 14px; font-weight: bold; color: #555; letter-spacing: .5px; margin: 10px 0 8px; padding-bottom: 4px; border-bottom: 2px solid #e1e5eb; }
	.rp-sede{ display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
	.table > thead > tr > th{ background: #34495e; color: #fff; white-space: nowrap; font-size: 12px; }
	.table td, .table th{ vertical-align: middle !important; }
	.table tfoot td{ font-weight: bold; background: #ecf0f1; }
	.rp-num{ text-align: right; white-space: nowrap; }
	.rp-ok{ color: #27ae60; font-weight: bold; }
	.rp-bad{ color: #c0392b; font-weight: bold; }
	.rp-progress{ margin: 0; height: 18px; min-width: 110px; }
	.rp-progress .progress-bar{ line-height: 18px; font-size: 11px; font-weight: bold; }
	.rp-grupo td{ font-weight: bold; color: #fff; }
	.rp-subtotal td{ font-weight: bold; background: #f7f7f7; }
	#rpActualizado{ color: #777; margin-left: 10px; }
	.rp-toolbar{ margin-bottom: 10px; }
	.rp-filtro{ margin-bottom: 6px; }

	/* ---------- tablet ---------- */
	@media (max-width: 991px){
		.rp-kpi{ min-height: 0; }
		.rp-kpi .rp-val{ font-size: 21px; }
	}

	/* ---------- celular ---------- */
	@media (max-width: 767px){
		body{ padding-top: 6px; }
		.container-fluid{ padding-left: 8px; padding-right: 8px; }
		.tab-content{ padding: 10px 8px; }
		.rp-toolbar .btn{ display: block; width: 100%; margin-bottom: 6px; }
		#rpActualizado{ display: block; margin: 0 0 6px; text-align: center; }
		.rp-filtro.text-right{ text-align: left; }

		/* pestañas en una sola línea con scroll horizontal */
		.nav-tabs{ display: flex; flex-wrap: nowrap; overflow-x: auto; overflow-y: hidden; -webkit-overflow-scrolling: touch; }
		.nav-tabs > li{ float: none; flex: 0 0 auto; }
		.nav-tabs > li > a{ padding: 8px 10px; font-size: 13px; white-space: nowrap; }

		/* KPIs de a dos por fila */
		#rpKpis > div{ width: 50%; float: left; padding-left: 5px; padding-right: 5px; }
		#rpKpis{ margin-left: -5px; margin-right: -5px; }
		.rp-kpi{ padding: 8px 10px; margin-bottom: 10px; }
		.rp-kpi small{ font-size: 10px; }
		.rp-kpi .rp-val{ font-size: 17px; word-break: break-all; }
		.rp-kpi .rp-sub{ font-size: 11px; }
		.rp-titulo{ font-size: 12px; }

		/* cada fila de la tabla se vuelve una tarjeta */
		.table-responsive{ border: 0; margin-bottom: 10px; overflow: visible; }
		.table-responsive .table{ border: 0; margin-bottom: 0; }
		.table-responsive .table thead{ display: none; }
		.table-responsive .table, .table-responsive .table tbody, .table-responsive .table tfoot,
		.table-responsive .table tr, .table-responsive .table td{ display: block; width: 100%; }
		.table-responsive .table tr{ border: 1px solid #dde2e8; border-radius: 6px; margin-bottom: 8px; background: #fff; overflow: hidden; }
		.table-responsive .table td{ border: 0 !important; border-bottom: 1px solid #f0f0f0 !important; text-align: right !important;
			padding: 4px 10px !important; white-space: normal !important; min-height: 28px; font-size: 13px; }
		.table-responsive .table td:last-child{ border-bottom: 0 !important; }
		.table-responsive .table td[data-label]:before{ content: attr(data-label); float: left; font-weight: bold; color: #777;
			font-size: 11px; text-transform: uppercase; padding-right: 10px; text-align: left; }
		.table-responsive .table td:first-child{ background: #f7f9fb; font-size: 14px; font-weight: bold; }
		.table-responsive .table td.rp-ancha{ text-align: left !important; }
		.table-responsive .table tfoot tr{ border-color: #34495e; }
		.rp-progress{ display: inline-block; width: 55%; min-width: 0; vertical-align: middle; }
		.rp-grupo td{ text-align: left !important; font-size: 14px; }
	}
</style>
</head>
<body>
<input type="hidden" id="org" value="<?php if(!empty($_SESSION["pp_ses_NumOrg"])){ echo $_SESSION["pp_ses_NumOrg"]; }else{echo 0;}  ?>" readonly />

<div class="container-fluid">
	<div class="rp-toolbar">
		<button id="Exportar" class="btn btn-sm btn-success"><span class="fa fa-file-excel-o"></span> Exportar pestaña a Excel</button>
		<button id="Refrescar" class="btn btn-sm btn-info"><span class="fa fa-refresh"></span> Actualizar info</button>
		<span id="rpActualizado"></span>
	</div>
	<div id="rpMensaje"></div>

	<ul class="nav nav-tabs" role="tablist">
		<li class="active"><a href="#tabDashboard" data-toggle="tab"><span class="fa fa-dashboard"></span> Dashboard</a></li>
		<li><a href="#tabZonas"   data-toggle="tab"><span class="fa fa-map-marker"></span> Ventas por zona</a></li>
		<li><a href="#tabSinCompra" data-toggle="tab"><span class="fa fa-user-times"></span> Clientes sin compras <span class="badge" id="rpBadgeSin"></span></a></li>
	</ul>

	<div class="tab-content">

		<!-- ============================ DASHBOARD ============================ -->
		<div class="tab-pane active" id="tabDashboard">
			<div class="row" id="rpKpis"></div>

			<div class="rp-titulo"><span class="fa fa-building"></span> VENTAS POR SEDE (TODO EL EVENTO, NETO SIN IVA)</div>
			<div class="table-responsive"><table class="table table-bordered table-condensed table-hover" id="rpTablaSedes"></table></div>

			<div class="row">
				<div class="col-lg-5 col-md-12">
					<div class="rp-titulo"><span class="fa fa-users"></span> CLIENTES REGISTRADOS POR SEDE</div>
					<div class="table-responsive"><table class="table table-bordered table-condensed" id="rpTablaRegistro"></table></div>
				</div>
				<div class="col-lg-7 col-md-12">
					<div class="rp-titulo"><span class="fa fa-arrow-down"></span> TOP 10 ZONAS CON MENOS VENTA</div>
					<div class="table-responsive"><table class="table table-bordered table-condensed table-hover" id="rpTablaBottom"></table></div>
				</div>
			</div>
		</div>

		<!-- ============================ ZONAS ============================ -->
		<div class="tab-pane" id="tabZonas">
			<div class="table-responsive"><table class="table table-bordered table-condensed table-hover" id="rpTablaZonas"></table></div>
		</div>

		<!-- ============================ SIN COMPRAS ============================ -->
		<div class="tab-pane" id="tabSinCompra">
			<div class="row" style="margin-bottom:10px;">
				<div class="col-md-3 col-sm-6 rp-filtro"><select class="form-control input-sm" id="rpFiltroSede"></select></div>
				<div class="col-md-2 col-sm-6 rp-filtro">
					<select class="form-control input-sm" id="rpFiltroTipo">
						<option value="">Todos los tipos</option>
						<option value="P">Presencial</option>
						<option value="V">Virtual</option>
					</select>
				</div>
				<div class="col-md-4 col-sm-8 rp-filtro"><input type="text" class="form-control input-sm" id="rpFiltroTexto" placeholder="Buscar código SAP, cliente, zona o vendedor..."></div>
				<div class="col-md-3 col-sm-4 text-right rp-filtro" style="padding-top:6px;"><b id="rpConteoSin"></b></div>
			</div>
			<div class="table-responsive"><table class="table table-bordered table-condensed table-striped" id="rpTablaSin"></table></div>
		</div>

	</div>
</div>
</body>
</html>
