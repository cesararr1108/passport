<?php
   /* require("../models/funciones.php");
    session_start();
    Redireccionar();*/
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<link type="text/css" rel="stylesheet" href="../resources/css/bootstrap.min.css"/>
<link type="text/css" rel="stylesheet" href="../resources/css/BootstrapTabs.css?<?php echo rand()?>"/>
<link type="text/css" rel="stylesheet" href="../resources/css/menu/font-awesome.min.css"/>
<link type="text/css" rel="stylesheet" href="../resources/css/start/jquery-ui-1.9.2.custom.css"/>
<link type="text/css" rel="stylesheet" href="../lib/SweetAlert/sweet-alert.css"/>
<link type="text/css" rel="stylesheet" href="../resources/css/Animate.css?<?php  echo(rand()); ?>" >

<script type="text/javascript" src="../lib/js/jquery-2.1.1.min.js"></script>
<script type="text/javascript" src="../lib/js/jquery-ui-1.9.2.custom.js"></script>
<script type="text/javascript" src="../lib/js/bootstrap.min.js"></script>
<script type="text/javascript" src="../lib/js/funciones.js?<?php echo rand()?>"></script>
<script type="text/javascript" src="../lib/SweetAlert/sweet-alert.min.js"></script>
<script type="text/javascript" src="../controllers/Rifas.js?<?php echo rand();?>"></script>
<title>Rifas</title>
<style>
	body{ padding-top: 10px; }
	.ev-reglas .panel-body{ min-height: 120px; font-size: 13px; }
	.ev-reglas .panel-heading small{ font-weight: normal; }
	.ev-card{ border-radius: 6px; padding: 10px 14px; margin-bottom: 12px; border: 1px solid #ddd; background: #fafafa; }
	.ev-card h4{ margin-top: 4px; }
	.ev-card .ev-big{ font-size: 24px; font-weight: bold; }
	.ev-ok{ border-left: 6px solid #5cb85c; }
	.ev-no{ border-left: 6px solid #d9534f; }
	.ev-wait{ border-left: 6px solid #f0ad4e; }
	.ev-done{ border-left: 6px solid #337ab7; }
	.ev-off{ border-left: 6px solid #999; }
	.ev-progress{ height: 22px; margin-bottom: 6px; }
	.ev-progress .progress-bar{ line-height: 22px; font-weight: bold; }
	.ev-din{ min-height: 270px; }
	.table td, .table th{ vertical-align: middle !important; }
	.nav-tabs > li > a{ font-weight: bold; }
	#ResultadosRifas tr.success > td{ background-color: #dff0d8 !important; color: #2b542c; font-weight: bold; }
	#evReloj{ font-size: 16px; }
</style>
</head>
<body oncontextmenu="return false;">

<input id="TipoU"      type="hidden" value="<?php if(!empty($_SESSION["Ses_Rol"])){ echo $_SESSION["Ses_Rol"]; }else{echo 0;}  ?>" readonly />
<input id="GetUsuario" type="hidden" value="<?php if(!empty($_GET["C0d1g0"])){ echo htmlspecialchars($_GET["C0d1g0"]); }else{echo 0;}  ?>" readonly>

<div class="container-fluid">

  <div class="row">
    <div class="col-md-12">
      <span class="pull-right text-muted" id="evReloj"></span>
    </div>
  </div>

  <!-- ===================== REGLAS (se pintan desde RifasConfig.php) ===================== -->
  <div class="row ev-reglas" id="evReglas"></div>

  <ul class="nav nav-tabs" role="tablist">
    <li class="active"><a href="#tabCliente"   data-toggle="tab"><span class="fa fa-search"></span> Consulta cliente</a></li>
    <li><a href="#tabGanadores" data-toggle="tab"><span class="fa fa-trophy"></span> Ganadores y boletas</a></li>
  </ul>

  <div class="tab-content" style="padding-top:15px;">

    <!-- ===================== CONSULTA CLIENTE ===================== -->
    <div class="tab-pane active" id="tabCliente">
      <div class="row">
        <div class="col-md-4">
          <div class="input-group">
            <span class="input-group-addon"><b>CÓDIGO SAP</b></span>
            <input type="text" class="form-control input-lg" id="TxtPassportRifas" autocomplete="off" maxlength="10"/>
            <span class="input-group-btn">
              <button class="btn btn-primary btn-lg" id="btnConsultar"><span class="fa fa-search"></span></button>
            </span>
          </div>
        </div>
        <div class="col-md-8">
          <h3 id="SpClienteRifas" style="margin-top:6px;"></h3>
        </div>
      </div>

      <div id="evMensaje" style="margin-top:15px;"></div>

      <div id="evResultado" style="display:none; margin-top:15px;">
        <div class="row">
          <div class="col-md-3 col-sm-6">
            <div class="ev-card"><small>COMPRA TOTAL</small><div class="ev-big" id="SpCompras"></div></div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="ev-card"><small>COMPRA LAB. PARTICIPANTES</small><div class="ev-big text-danger" id="comprasLabPart"></div></div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="ev-card">
              <small>LABORATORIOS CON / SIN COMPRA</small><br>
              <button class="btn btn-success" id="n_lab_compas" title="Laboratorios con compra"></button> /
              <button class="btn btn-danger"  id="n_lab_sin_compas" title="Ver laboratorios donde no ha comprado"></button>
            </div>
          </div>
          <div class="col-md-3 col-sm-6">
            <div class="ev-card"><small>REGISTRO / GRUPO</small><div id="evRegistrado" style="font-size:16px;"></div></div>
          </div>
        </div>

        <!-- Una tarjeta por dinámica -->
        <div class="row" id="evDinamicasCliente"></div>

        <h3 class="text-center">DETALLE DE COMPRAS POR LABORATORIO PATROCINADOR</h3>
        <div class="table-responsive">
          <table class="table table-striped table-condensed table-bordered">
            <thead>
              <tr class="info">
                <th>#</th>
                <th>NIT</th>
                <th>LABORATORIO</th>
                <th class="text-right">COMPRA TOTAL</th>
                <th class="text-center">&ge; $100.000</th>
                <th class="text-center">&ge; $1.000.000</th>
              </tr>
            </thead>
            <tbody id="ResultadosRifas"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ===================== GANADORES ===================== -->
    <div class="tab-pane" id="tabGanadores">
      <div class="row" style="margin-bottom:10px;">
        <div class="col-md-4">
          <select class="form-control" id="evFiltroDinamica"><option value="">Todas las dinámicas</option></select>
        </div>
        <div class="col-md-8">
          <button class="btn btn-default" id="btnRefrescarGanadores"><span class="fa fa-refresh"></span> Refrescar</button>
        </div>
      </div>
      <div class="row" id="evResumenGanadores"></div>
      <div class="table-responsive">
        <table class="table table-striped table-condensed">
          <thead>
            <tr>
              <th>#</th><th>DINÁMICA</th><th>CÓDIGO</th><th>CLIENTE</th><th>GRUPO</th>
              <th class="text-right">COMPRA AL GANAR</th><th class="text-center">LABS</th>
              <th class="text-center">BOLETAS</th><th class="text-center">REDIMIDAS</th>
              <th>FECHA</th><th>USUARIO</th><th></th>
            </tr>
          </thead>
          <tbody id="evGanadores"></tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<div id="ModalFaltantes" class="modal fade bd-example-modal-lg" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">LABORATORIOS DONDE NO HAS COMPRADO</h4>
      </div>
      <div class="modal-body" id="DivLabSinCompras"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-info" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<div id="ModalPDF" class="modal fade bd-example-modal-lg" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">BOLETAS</h4>
      </div>
      <div class="modal-body" id="dvPDF"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-info" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
<small id="evVersion" class="text-muted" style="position:fixed; right:6px; bottom:4px;"></small>
</body>
</html>
