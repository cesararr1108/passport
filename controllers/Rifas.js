/* =====================================================================
   RIFAS - DINÁMICAS DEL EVENTO
   - Dinámica #1 Ruleta            (antes de las 12:00 venta actual; después, corte de las 12:00)
   - Dinámica #2 iPhone 18 Pro      (antes de las 12:00 venta actual; después, corte de las 12:00)
   - Dinámica #3 iPhone 18 / bono   (desde las 6:00 pm, compras del día)
   - Sorteo de electrodomésticos    (desde las 6:00 pm, compras del día)
   Las reglas (montos, labs, horas) vienen de models/RifasConfig.php
   ===================================================================== */
var URL_RIFAS = '../models/Rifas.php';
var URL_PDF_BOLETA = '../resources/pdf/pdf_boleta_dinamica.php';
var EV_VERSION = 'rifas-dinamicas-v3';
var EV_CONFIG = null;   // reglas y horarios
var EV_DATOS = null;    // última consulta de cliente

$(function(){
	console.log('Rifas.js ' + EV_VERSION);
	$('#evVersion').text(EV_VERSION);
	$('#TxtPassportRifas').on('keypress', function(e){
		if(e.which == 13){ ConsultarCliente(); }
	}).focus();
	$('#btnConsultar').on('click', ConsultarCliente);
	$('#n_lab_sin_compas').on('click', VerLabsSinCompra);
	$('#btnRefrescarGanadores').on('click', CargarGanadores);
	$('#evFiltroDinamica').on('change', CargarGanadores);

	$(document).on('click', '.ev-registrar', function(){ Registrar($(this).data('din')); });
	$(document).on('click', '.ev-redimir',   function(){ Redimir($(this).data('id'), $(this).data('pend'), $(this).data('nombre')); });
	$(document).on('click', '.ev-imprimir',  function(){ ImprimirBoletas($(this).data('id')); });
	$(document).on('click', '#btnEspejoGenerar', GenerarEspejo);

	$('a[data-toggle="tab"]').on('shown.bs.tab', function(e){
		var tab = $(e.target).attr('href');
		if(tab == '#tabGanadores'){ CargarGanadores(); }
		if(tab == '#tabEspejo'){ CargarEspejo(); }
	});

	CargarConfig(function(){
		var u = $.trim($('#GetUsuario').val());
		if(u != '' && u != '0'){
			$('#TxtPassportRifas').val(u);
			ConsultarCliente();
		}
	});
	// Refresca horarios (abre/cierra dinámicas) cada minuto
	setInterval(function(){ CargarConfig(); }, 60000);
});

/* ---------------------------- utilidades ---------------------------- */
function EvPost(data, ok){
	$.ajax({
		url: URL_RIFAS,
		type: 'POST',
		data: data,
		dataType: 'json',
		cache: false
	}).done(function(r){
		if(!r || r.ok === false){
			EvAviso('warning', 'Atención:', (r && r.msg) ? r.msg : 'No fue posible procesar la solicitud');
			return;
		}
		ok(r);
	}).fail(function(xhr){
		console.log(xhr.responseText);
		var detalle = $.trim($('<div/>').html(xhr.responseText || '').text()).substring(0, 300);
		EvAviso('error', 'Error del servidor:', detalle != '' ? detalle : 'No fue posible comunicarse con el servidor (' + xhr.status + ')');
	});
}

function EvConfirmar(titulo, texto, fn){
	if(window.confirm(titulo + '\n\n' + texto)){
		fn();
	}
}

function EvAviso(tipo, titulo, texto, sinPopup){
	var clase = tipo == 'success' ? 'alert-success' : (tipo == 'error' ? 'alert-danger' : 'alert-warning');
	$('#evMensaje').html(
		'<div class="alert ' + clase + ' alert-dismissible" style="font-size:18px;">' +
		  '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
		  '<b>' + Esc(titulo) + '</b> ' + Esc(texto) +
		'</div>');
	if(!sinPopup && typeof swal == 'function'){
		setTimeout(function(){ swal(titulo, texto, tipo); }, 400);
	}
}

function Moneda(n){
	n = Math.round(parseFloat(n) || 0);
	var s = Math.abs(n).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
	return (n < 0 ? '-$' : '$') + s;
}

function Esc(s){
	return $('<div/>').text(s === null || s === undefined ? '' : s).html();
}

function HoraAmPm(h){
	var p = h.split(':'), hh = parseInt(p[0], 10);
	return (hh > 12 ? hh - 12 : hh) + ':' + p[1] + (hh >= 12 ? ' pm' : ' am');
}

function Barra(valor, meta, clase, texto){
	var pct = meta > 0 ? Math.min(100, Math.round(valor * 100 / meta)) : 100;
	return '<div class="progress ev-progress">' +
	         '<div class="progress-bar progress-bar-' + (valor >= meta ? 'success' : clase) + '" style="width:' + Math.max(pct, 15) + '%">' +
	           (texto || (valor + ' / ' + meta)) +
	         '</div>' +
	       '</div>';
}

function NombreDinamica(key){
	var n = key;
	if(EV_CONFIG){ $.each(EV_CONFIG.dinamicas, function(i, d){ if(d.key == key){ n = d.nombre; } }); }
	return n;
}

// Texto de una regla: "Compra total >= $X y >= $Y en N labs"
function TextoRegla(r){
	var t = [];
	if(r.total_min > 0){ t.push('Compra total &ge; <b>' + Moneda(r.total_min) + '</b>'); }
	if(r.n_labs > 0){ t.push('&ge; <b>' + Moneda(r.lab_min) + '</b> en <b>' + r.n_labs + '</b> labs participantes'); }
	var s = t.join(' y ') + '. ' + r.boletas + ' boleta';
	if(r.extra_cada > 0){ s += ' + 1 por cada ' + Moneda(r.extra_cada) + ' adicional después de ' + Moneda(r.extra_desde); }
	return s + '.';
}

/* ---------------------------- configuración ---------------------------- */
function CargarConfig(fn){
	EvPost({ op: 'EV_CONFIG' }, function(r){
		var primera = EV_CONFIG === null;
		EV_CONFIG = r;
		$('.evHoraCorte').text(HoraAmPm(r.hora_corte));
		$('#evTabCorte').text(HoraAmPm(r.hora_corte));
		$('#evReloj').html('<span class="fa fa-clock-o"></span> Hora servidor: <b>' + Esc(r.ahora.substring(11, 16)) + '</b>' +
			' &nbsp; Mañana: ' + (r.manana_abierta ? '<span class="label label-success">VENTA ACTUAL</span>'
				: (r.espejo ? '<span class="label label-info">CON CORTE ' + HoraAmPm(r.hora_corte) + '</span>'
				            : '<span class="label label-danger">FALTA HACER EL CORTE</span> <button class="btn btn-danger btn-xs" id="btnEspejoGenerar">Hacer corte</button>')) +
			' &nbsp; Tarde: ' + (r.tarde_abierta ? '<span class="label label-success">ABIERTA</span>' : '<span class="label label-default">DESDE ' + HoraAmPm(r.hora_tarde) + '</span>'));

		if(primera){
			var html = '', opts = '<option value="">Todas las dinámicas</option>';
			var colores = ['info', 'primary', 'success', 'warning'];
			$.each(r.dinamicas, function(i, d){
				var hor = d.ventana == 'manana' ? 'Hasta las ' + HoraAmPm(r.hora_corte) : 'Desde las ' + HoraAmPm(r.hora_tarde);
				html += '<div class="col-md-3"><div class="panel panel-' + colores[i % colores.length] + '">' +
				          '<div class="panel-heading"><b>' + Esc(d.nombre) + '</b><br><small>' + hor + '</small></div>' +
				          '<div class="panel-body">';
				$.each(d.reglas, function(j, g){
					var grp = g.grupo == '*' ? (d.reglas.length > 1 ? 'Demás grupos' : 'Todos') : 'Grupo ' + Esc(g.grupo);
					html += '<p><span class="label label-default">' + grp + '</span> ' + TextoRegla(g) + '</p>';
				});
				html += '</div></div></div>';
				opts += '<option value="' + Esc(d.key) + '">' + Esc(d.nombre) + '</option>';
			});
			$('#evReglas').html(html);
			$('#evFiltroDinamica').html(opts);
		}
		if(fn){ fn(); }
	});
}

/* ---------------------------- consulta cliente ---------------------------- */
function ConsultarCliente(){
	var pass = $.trim($('#TxtPassportRifas').val());
	if(pass == ''){
		swal('Atención', 'Digite el código SAP del cliente', 'warning');
		return;
	}
	$('#btnConsultar').prop('disabled', true);
	$('#evMensaje').html('');
	EvPost({ op: 'EV_CONSULTA', Pass: pass }, function(r){
		EV_DATOS = r;
		PintarCliente(r);
	});
	setTimeout(function(){ $('#btnConsultar').prop('disabled', false); }, 800);
}

function ConsultarClienteActual(){
	if(!EV_DATOS){ return; }
	EvPost({ op: 'EV_CONSULTA', Pass: EV_DATOS.cliente.codigo_sap }, function(r){
		EV_DATOS = r;
		PintarCliente(r);
	});
}

function PintarCliente(r){
	var c = r.cliente;
	$('#SpClienteRifas').html(Esc(c.codigo_sap) + ' - ' + Esc(c.razon_comercial || c.nombres) +
		(c.razon_comercial && c.nombres && c.razon_comercial != c.nombres ? ' <small>(' + Esc(c.nombres) + ')</small>' : ''));
	$('#SpCompras').text(Moneda(r.compra_total));
	$('#comprasLabPart').text(Moneda(r.compra_labs));
	$('#SpComprasAm').text(Moneda(r.compra_total_am));
	$('#SpLabsAm').text(Moneda(r.compra_labs_am));
	$('#evFuenteCorte').html(r.usa_espejo ? '<span class="label label-info">CORTE</span>' : '<span class="label label-warning">VENTA ACTUAL</span>');
	$('#n_lab_compas').text(r.labs_con_compra);
	$('#n_lab_sin_compas').text(r.n_labs - r.labs_con_compra);
	$('#evRegistrado').html((r.registrado
		? '<span class="label label-success">REGISTRADO</span>'
		: '<span class="label label-danger">NO REGISTRADO</span>') +
		' <span class="label label-default">GRUPO ' + Esc(r.grupo || '-') + '</span>');

	var html = '';
	$.each(r.dinamicas, function(i, d){ html += TarjetaDinamica(d); });
	$('#evDinamicasCliente').html(html);
	PintarDetalleLabs(r);
	$('#evResultado').show();
}

function TarjetaDinamica(d){
	var r = d.regla, html = '', clase, botones = '';
	var nombre = EV_DATOS.cliente.codigo_sap + ' - ' + (EV_DATOS.cliente.razon_comercial || EV_DATOS.cliente.nombres);
	var tituloCompra = d.ventana == 'manana' ? (EV_DATOS.usa_espejo ? 'Compra al corte' : 'Compra hasta ahora') : 'Compra total';

	html += '<small>' + (d.grupo_especial ? 'Regla grupo ' + Esc(EV_DATOS.grupo) : 'Regla general') + ': ' + TextoRegla(r) + '</small>';
	if(r.total_min > 0){
		html += tituloCompra + ':' + Barra(d.compra_total, r.total_min, 'warning', Moneda(d.compra_total) + ' / ' + Moneda(r.total_min));
	}
	if(r.n_labs > 0){
		html += 'Labs con ' + Moneda(r.lab_min) + ':' + Barra(d.labs_cumple, r.n_labs, 'warning');
	}

	var g = d.ganador;
	switch(d.estado){
		case 'GANADOR':
		case 'ADICIONALES':
			clase = 'ev-done';
			html += '<div class="alert alert-info" style="margin:0 0 6px 0;"><b>GANÓ</b> ' + g.boletas + ' boleta(s) el ' + Esc(g.fecha) +
			        ' con ' + Moneda(g.compra_total) + '.<br>Redimidas: <b>' + g.boletas_redimidas + ' de ' + g.boletas + '</b>' +
			        (g.fecha_redencion ? ' (' + Esc(g.fecha_redencion) + ')' : '') + '</div>';
			if(d.estado == 'ADICIONALES'){
				html += '<div class="alert alert-success" style="margin:0 0 6px 0;">Con su compra actual gana <b>' + d.adicionales + '</b> boleta(s) adicional(es)</div>';
			}
			break;
		case 'EXCLUIDO':
			clase = 'ev-no';
			html += '<div class="alert alert-danger" style="margin:0;">Ya ganó otra dinámica de la mañana</div>';
			break;
		case 'SIN_CORTE':
			clase = 'ev-off';
			html += '<div class="alert alert-danger" style="margin:0 0 6px 0;">Ya pasaron las ' + HoraAmPm(EV_CONFIG.hora_corte) +
			        '. <b>Falta hacer el corte</b> para validar esta dinámica.</div>';
			botones += '<button class="btn btn-danger btn-lg" id="btnEspejoGenerar"><span class="fa fa-scissors"></span> Hacer corte</button> ';
			break;
		case 'NO_HABILITADA':
			clase = d.cumple ? 'ev-ok' : 'ev-wait';
			html += '<div class="alert alert-' + (d.cumple ? 'success' : 'warning') + '" style="margin:0;">Se habilita a las ' + HoraAmPm(EV_CONFIG.hora_tarde) +
			        (d.cumple ? '. Hoy <b>cumple</b> (' + d.boletas + ' boleta)' : FaltaTexto(d)) + '</div>';
			break;
		case 'NO_REGISTRADO':
			clase = 'ev-no';
			html += '<div class="alert alert-danger" style="margin:0;">Cumple, pero el cliente <b>no está registrado</b> en el evento</div>';
			break;
		case 'CUMPLE':
			clase = 'ev-ok';
			html += '<div class="alert alert-success" style="margin:0 0 6px 0;"><b>¡GANA ' + d.boletas + ' BOLETA(S)!</b>' +
			        (d.siguiente > 0 ? '<br><small>Con ' + Moneda(d.siguiente) + ' gana una boleta más</small>' : '') + '</div>';
			break;
		default:
			clase = 'ev-wait';
			html += '<div class="alert alert-warning" style="margin:0;">' + FaltaTexto(d) + '</div>';
	}

	if(d.puede_registrar){
		botones += '<button class="btn btn-success btn-lg ev-registrar" data-din="' + Esc(d.key) + '">' +
		           '<span class="fa fa-ticket"></span> ' + (g ? 'Sumar ' + d.adicionales + ' boleta(s)' : 'Registrar ganador') + '</button> ';
	}
	if(g && g.boletas_redimidas < g.boletas){
		botones += '<button class="btn btn-primary btn-lg ev-redimir" data-id="' + g.id + '" data-pend="' + (g.boletas - g.boletas_redimidas) +
		           '" data-nombre="' + Esc(nombre) + '"><span class="fa fa-gift"></span> Redimir ' + (g.boletas - g.boletas_redimidas) + '</button> ';
	}
	if(g && g.boletas_redimidas > 0){
		botones += '<button class="btn btn-default btn-lg ev-imprimir" data-id="' + g.id + '"><span class="fa fa-print"></span></button>';
	}

	return '<div class="col-md-3 col-sm-6"><div class="ev-card ev-din ' + clase + '">' +
	         '<h4>' + Esc(d.nombre) + '</h4>' + html +
	         '<div class="text-center" style="margin-top:8px;">' + botones + '</div>' +
	       '</div></div>';
}

function FaltaTexto(d){
	var t = [];
	if(d.falta_total > 0){ t.push('<b>' + Moneda(d.falta_total) + '</b> en compra total'); }
	if(d.falta_labs > 0){ t.push('<b>' + d.falta_labs + '</b> labs con ' + Moneda(d.regla.lab_min)); }
	return t.length ? ' Le faltan ' + t.join(' y ') : '';
}

function PintarDetalleLabs(r){
	var html = '', ok = '<span class="fa fa-check text-success"></span>', no = '<span class="fa fa-times text-danger"></span>';
	$.each(r.labs, function(i, l){
		var c = parseFloat(l.compra) || 0, am = parseFloat(l.compra_am) || 0;
		var fila = c >= 1000000 ? 'success' : (c <= 0 ? 'text-muted' : '');
		html += '<tr class="' + fila + '">' +
		          '<td>' + (i + 1) + '</td>' +
		          '<td>' + Esc(l.nit) + '</td>' +
		          '<td>' + Esc(l.nombres) + '</td>' +
		          '<td class="text-right">' + Moneda(am) + '</td>' +
		          '<td class="text-right">' + Moneda(c) + '</td>' +
		          '<td class="text-center">' + (c >= 100000 ? ok : no) + '</td>' +
		          '<td class="text-center">' + (c >= 1000000 ? ok : no) + '</td>' +
		        '</tr>';
	});
	$('#ResultadosRifas').html(html);
}

function VerLabsSinCompra(){
	if(!EV_DATOS){ return; }
	var html = '<ul class="list-group">';
	$.each(EV_DATOS.labs, function(i, l){
		if((parseFloat(l.compra) || 0) <= 0){
			html += '<li class="list-group-item"><b>' + Esc(l.nit) + '</b> - ' + Esc(l.nombres) + '</li>';
		}
	});
	html += '</ul>';
	$('#DivLabSinCompras').html(html);
	$('#ModalFaltantes').modal('show');
}

/* ---------------------------- registrar / redimir ---------------------------- */
function Registrar(key){
	if(!EV_DATOS){ return; }
	var c = EV_DATOS.cliente;
	var nombre = c.codigo_sap + ' - ' + (c.razon_comercial || c.nombres);
	EvConfirmar('¿Registrar ganador de ' + NombreDinamica(key) + '?', 'Cliente: ' + nombre, function(){
		$('.ev-registrar').prop('disabled', true);
		EvPost({ op: 'EV_REGISTRAR', Pass: c.codigo_sap, dinamica: key }, function(r){
			EvAviso('success', '¡GANADOR REGISTRADO!', nombre + '. ' + r.msg + '.', true);
			ConsultarClienteActual();
			// Ofrece redimir de una vez
			EvConfirmar('¿Entregar las boletas ahora?', nombre + '\n' + r.msg, function(){
				Redimir(r.id, null, nombre, true);
			});
		});
		setTimeout(function(){ $('.ev-registrar').prop('disabled', false); }, 2000);
	});
}

// pend = boletas pendientes (null = todas, sin preguntar)
function Redimir(id, pend, nombre, sinPreguntar){
	var cant = '';
	if(!sinPreguntar && pend > 1){
		cant = window.prompt('¿Cuántas boletas redime ' + nombre + '? (pendientes: ' + pend + ')', pend);
		if(cant === null){ return; }
		cant = parseInt(cant, 10);
		if(!(cant > 0 && cant <= pend)){ swal('Atención', 'Cantidad inválida', 'warning'); return; }
	}else if(!sinPreguntar && !window.confirm('¿Redimir boleta de ' + nombre + '?')){
		return;
	}
	$('.ev-redimir').prop('disabled', true);
	EvPost({ op: 'EV_REDIMIR', id: id, cantidad: cant }, function(r){
		EvAviso('success', '¡BOLETAS REDIMIDAS!', (nombre || '') + '. ' + r.msg + '.', true);
		ImprimirBoletas(r.id, r.desde, r.hasta);
		if(EV_DATOS){ ConsultarClienteActual(); }
		if($('#tabGanadores').hasClass('active')){ CargarGanadores(); }
	});
	setTimeout(function(){ $('.ev-redimir').prop('disabled', false); }, 2000);
}

// Sin desde/hasta imprime todas las boletas redimidas
function ImprimirBoletas(id, desde, hasta){
	var url = URL_PDF_BOLETA + '?id=' + encodeURIComponent(id) +
	          (desde ? '&desde=' + desde + '&hasta=' + hasta : '') + '&r=' + new Date().getTime();
	$('#dvPDF').html('<iframe width="100%" style="height:400px; border:0;" src="' + url + '"></iframe>');
	$('#ModalPDF').modal('show');
}

/* ---------------------------- ganadores ---------------------------- */
function CargarGanadores(){
	EvPost({ op: 'EV_GANADORES', dinamica: $('#evFiltroDinamica').val() }, function(r){
		var html = '', res = '';
		$.each(r.resumen, function(i, s){
			res += '<div class="col-md-3 col-sm-6"><div class="ev-card ev-done"><small>' + Esc(NombreDinamica(s.dinamica)) + '</small>' +
			       '<div style="font-size:16px;">Ganadores: <b>' + s.ganadores + '</b> &nbsp; Boletas: <b>' + s.boletas + '</b>' +
			       ' &nbsp; Redimidas: <b>' + s.redimidas + '</b></div></div></div>';
		});
		$('#evResumenGanadores').html(res);
		$.each(r.rows, function(i, g){
			var pend = g.boletas - g.boletas_redimidas;
			var nombre = g.passport + ' - ' + g.nombres;
			html += '<tr' + (pend > 0 ? ' class="warning"' : '') + '>' +
			          '<td>' + (i + 1) + '</td>' +
			          '<td>' + Esc(NombreDinamica(g.dinamica)) + '</td>' +
			          '<td>' + Esc(g.passport) + '</td>' +
			          '<td>' + Esc(g.nombres) + '</td>' +
			          '<td>' + Esc(g.grupo) + '</td>' +
			          '<td class="text-right">' + Moneda(g.compra_total) + '</td>' +
			          '<td class="text-center">' + Esc(g.n_labs) + '</td>' +
			          '<td class="text-center">' + Esc(g.boletas) + '</td>' +
			          '<td class="text-center">' + Esc(g.boletas_redimidas) + (g.redimido == 1 ? ' <span class="fa fa-check text-success"></span>' : '') + '</td>' +
			          '<td>' + Esc(g.fecha) + '</td>' +
			          '<td>' + Esc(g.usuario) + '</td>' +
			          '<td style="white-space:nowrap;">' +
			            (pend > 0 ? '<button class="btn btn-xs btn-primary ev-redimir" data-id="' + g.id + '" data-pend="' + pend + '" data-nombre="' + Esc(nombre) + '"><span class="fa fa-gift"></span> Redimir</button> ' : '') +
			            (g.boletas_redimidas > 0 ? '<button class="btn btn-xs btn-default ev-imprimir" data-id="' + g.id + '"><span class="fa fa-print"></span></button>' : '') +
			          '</td>' +
			        '</tr>';
		});
		$('#evGanadores').html(html || '<tr><td colspan="12" class="text-center">Sin ganadores</td></tr>');
	});
}

/* ---------------------------- corte 12:00 ---------------------------- */
function CargarEspejo(){
	EvPost({ op: 'EV_ESPEJO_LISTA' }, function(r){
		var e = r.espejo, html = '';
		if(!e){
			$('#evEspejoEstado').html('<div class="alert alert-warning">' + (EV_CONFIG.manana_abierta
				? 'El corte se hace a partir de las <b>' + HoraAmPm(EV_CONFIG.hora_corte) + '</b>. Hasta entonces las dinámicas de la mañana se validan con la venta actual.'
				: 'Ya pasaron las <b>' + HoraAmPm(EV_CONFIG.hora_corte) + '</b> y no se ha hecho el corte. <button class="btn btn-danger btn-sm" id="btnEspejoGenerar"><span class="fa fa-scissors"></span> Hacer corte</button>') + '</div>');
		}else{
			$('#evEspejoEstado').html('<div class="alert alert-info">Corte de las <b>' + Esc(e.fecha_corte) + '</b>, hecho el ' +
				Esc(e.fecha_generacion) + (e.usuario ? ' por ' + Esc(e.usuario) : '') + ' (' + e.clientes + ' clientes con compra).</div>');
		}
		$.each(r.rows, function(i, x){
			html += '<tr>' +
			          '<td>' + (i + 1) + '</td>' +
			          '<td>' + Esc(x.passport) + '</td>' +
			          '<td>' + Esc(x.nombres) + '</td>' +
			          '<td>' + Esc(x.grupo) + '</td>' +
			          '<td class="text-right">' + Moneda(x.compra_total) + '</td>' +
			          '<td class="text-right">' + Moneda(x.compra_labs) + '</td>' +
			          '<td class="text-center">' + Esc(x.n_labs) + '</td>' +
			          '<td class="text-center">' + (x.ganadas > 0 ? '<span class="label label-primary">' + x.ganadas + '</span>' : '') + '</td>' +
			        '</tr>';
		});
		$('#evEspejoLista').html(html || '<tr><td colspan="8" class="text-center">Sin datos</td></tr>');
	});
}

function GenerarEspejo(){
	EvConfirmar('¿Hacer el corte de las ' + HoraAmPm(EV_CONFIG.hora_corte) + '?',
		'Se guarda la venta de todos los clientes hasta las ' + HoraAmPm(EV_CONFIG.hora_corte) +
		'. Después de esto las dinámicas #1 y #2 se validan con esa información. Se hace una sola vez.', function(){
		EvPost({ op: 'EV_ESPEJO_GENERAR' }, function(r){
			EvAviso('success', 'Corte realizado:', (r.espejo ? r.espejo.clientes + ' clientes con compra' : ''), true);
			CargarConfig();
			CargarEspejo();
			ConsultarClienteActual();
		});
	});
}
