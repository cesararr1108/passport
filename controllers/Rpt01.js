/* =====================================================================
   REPORTE DE VENTAS DEL EVENTO
   - Dashboard: KPIs, ventas por sede, registro P/V por sede, top 10 zonas con menos venta
   - Ventas por zona (detalle por vendedor)
   - Clientes registrados sin compras
   Fuentes: ../models/Reportes.php (op 1, V_CUMPLIMIENTO)
            ../models/ReportesEvento.php (CLIENTES_REGISTRADOS)
   ===================================================================== */

// Venta del evento anterior por sede (para el % de crecimiento)
var RP_VENTA_ANTERIOR = {
	'2100': 1401414841,   // MEDELLIN
	'2200': 4367843015,   // BOGOTA
	'2300': 547283097,    // CALI
	'2400': 7098195       // BARRANQUILLA
};

var RP_SEDES = {
	'2100': { nombre: 'MEDELLIN',     color: '#27AE60' },
	'2200': { nombre: 'BOGOTA',       color: '#F1C40F' },
	'2300': { nombre: 'CALI',         color: '#3498DB' },
	'2400': { nombre: 'BARRANQUILLA', color: '#E74C3C' }
};

var RP = { zonas: [], clientes: [], sedes: [], vendedores: {} };

$(function(){
	Reportes();

	$('#Refrescar').click(Reportes);

	$('#Exportar').click(function(){
		var id = $('.tab-content > .tab-pane.active').attr('id');
		var nombres = { tabDashboard: 'Dashboard evento', tabZonas: 'Ventas por zonas', tabSinCompra: 'Clientes sin compras' };
		exportar_final(id, nombres[id] || 'Reporte');
	});

	$('#rpFiltroSede, #rpFiltroTipo').change(PintarSinCompra);
	$('#rpFiltroTexto').on('keyup', PintarSinCompra);
	$(document).on('click', '.rp-ver-sin', function(e){ e.preventDefault(); $('a[href="#tabSinCompra"]').tab('show'); });
});

/* ------------------------------ utilidades ------------------------------ */
function Num(v){ v = parseFloat(v); return isNaN(v) ? 0 : v; }
function Mon(v){ return formatNum(Math.round(Num(v)), '$'); }
function Ent(v){ return String(Math.round(Num(v))).replace(/\B(?=(\d{3})+(?!\d))/g, '.'); }
function Pct(a, b){ return b > 0 ? Math.round((a * 100 / b) * 100) / 100 : 0; }
function Esc(s){
	return String(s === null || s === undefined ? '' : s)
		.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}
function SedeNombre(cod, desc){ return RP_SEDES[cod] ? RP_SEDES[cod].nombre : (desc || (cod ? cod : 'SIN SEDE')); }
function SedeColor(cod){ return RP_SEDES[cod] ? RP_SEDES[cod].color : '#95A5A6'; }
function SedePunto(cod){ return '<span class="rp-sede" style="background:' + SedeColor(cod) + '"></span>'; }
function OficinaDeZona(zona){ zona = String(zona || ''); return zona.length >= 2 ? zona.substring(0, 2) + '00' : ''; }

function Barra(p){
	var cls = p >= 100 ? 'progress-bar-success' : (p >= 70 ? 'progress-bar-warning' : 'progress-bar-danger');
	return '<div class="progress rp-progress"><div class="progress-bar ' + cls + '" style="width:' + Math.min(p, 100) + '%; min-width:3em;">' + p + '%</div></div>';
}
function Crec(p){
	return '<span class="' + (p >= 0 ? 'rp-ok' : 'rp-bad') + '"><span class="fa fa-caret-' + (p >= 0 ? 'up' : 'down') + '"></span> ' + p + '%</span>';
}
function Kpi(col, titulo, valor, sub, icono, color){
	return '<div class="' + col + '"><div class="rp-kpi" style="border-top:4px solid ' + color + ';">' +
	         '<small><span class="fa ' + icono + '"></span> ' + titulo + '</small>' +
	         '<div class="rp-val">' + valor + '</div>' +
	         '<div class="rp-sub">' + (sub || '') + '</div>' +
	       '</div></div>';
}

/* ------------------------------ carga ------------------------------ */
function Reportes(){
	$('#Refrescar').prop('disabled', true).find('.fa').addClass('fa-spin');
	$('#rpMensaje').html('');

	var a = $.ajax({ url: '../models/Reportes.php',       type: 'POST', data: { op: 1 },                      dataType: 'json' });
	var b = $.ajax({ url: '../models/ReportesEvento.php', type: 'POST', data: { op: 'CLIENTES_REGISTRADOS' }, dataType: 'json' });

	$.when(a, b).done(function(ra, rb){
		RP.zonas = ra[0] || [];
		var c = rb[0] || {};
		if(c.ok === false){
			$('#rpMensaje').html('<div class="alert alert-warning">Clientes registrados: ' + Esc(c.msg) + '</div>');
		}
		RP.clientes = c.rows || [];
		Procesar();
		PintarDashboard();
		PintarZonas();
		PintarFiltroSedes();
		PintarSinCompra();
		var d = new Date();
		$('#rpActualizado').text('Actualizado: ' + ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2) + ':' + ('0' + d.getSeconds()).slice(-2));
	}).fail(function(){
		$('#rpMensaje').html('<div class="alert alert-danger"><span class="fa fa-exclamation-triangle"></span> No se pudo cargar la información. Intente de nuevo.</div>');
	}).always(function(){
		$('#Refrescar').prop('disabled', false).find('.fa').removeClass('fa-spin');
	});
}

/* Agrupa zonas y clientes por sede */
function Procesar(){
	var sedes = {}, orden = [];
	function Sede(cod, desc){
		cod = String(cod || '');
		if(!sedes[cod]){
			sedes[cod] = { cod: cod, nombre: SedeNombre(cod, desc), cuota: 0, venta: 0, asistentes: 0,
			               anterior: Num(RP_VENTA_ANTERIOR[cod]), zonas: [],
			               reg_p: 0, reg_v: 0, reg_o: 0, con_compra: 0, sin_compra: 0 };
			orden.push(cod);
		}
		return sedes[cod];
	}

	RP.vendedores = {};
	$.each(RP.zonas, function(i, z){
		z.cuota = Num(z.PRESUPUESTO1) + Num(z.PRESUPUESTO2) + Num(z.PRESUPUESTO3);
		z.venta = Num(z.VALOR0) + Num(z.VALOR1) + Num(z.VALOR2) + Num(z.VALOR3);
		z.cumpl = Pct(z.venta, z.cuota);
		z.oficina = String(z.OFICINA_VENTAS || OficinaDeZona(z.ZONA_VENTAS));
		var s = Sede(z.oficina, z.OFICINA_DESCRIPCION);
		s.cuota += z.cuota;
		s.venta += z.venta;
		s.asistentes += parseInt(z.CLIENTES_ASISTENTES, 10) || 0;
		s.zonas.push(z);
		RP.vendedores[String(z.ZONA_VENTAS)] = z.ZONA_DESCRIPCION;
	});

	$.each(RP.clientes, function(i, c){
		c.oficina = String(c.oficina || OficinaDeZona(c.zona));
		c.venta = Num(c.venta);
		var s = Sede(c.oficina);
		if(c.tipo == 'P'){ s.reg_p++; } else if(c.tipo == 'V'){ s.reg_v++; } else { s.reg_o++; }
		if(c.venta > 0){ s.con_compra++; } else { s.sin_compra++; }
	});

	orden.sort();
	RP.sedes = $.map(orden, function(cod){
		var s = sedes[cod];
		s.cumpl = Pct(s.venta, s.cuota);
		s.crec = s.anterior > 0 ? Math.round(((s.venta - s.anterior) / s.anterior) * 10000) / 100 : 0;
		s.reg = s.reg_p + s.reg_v + s.reg_o;
		return s;
	});
}

function Totales(){
	var t = { cuota: 0, venta: 0, anterior: 0, asistentes: 0, reg_p: 0, reg_v: 0, reg_o: 0, reg: 0, con_compra: 0, sin_compra: 0 };
	$.each(RP.sedes, function(i, s){
		for(var k in t){ t[k] += Num(s[k]); }
	});
	t.cumpl = Pct(t.venta, t.cuota);
	t.crec = t.anterior > 0 ? Math.round(((t.venta - t.anterior) / t.anterior) * 10000) / 100 : 0;
	return t;
}

/* ------------------------------ dashboard ------------------------------ */
function PintarDashboard(){
	var t = Totales();
	var col = 'col-lg-2 col-md-4 col-sm-6';

	$('#rpKpis').html(
		Kpi(col, 'VENTA TOTAL EVENTO', Mon(t.venta), 'Neto sin IVA', 'fa-money', '#2C3E50') +
		Kpi(col, 'CUMPLIMIENTO', t.cumpl + '%', 'Cuota ' + Mon(t.cuota) + '<br>' + (t.venta >= t.cuota ? 'Cuota superada' : 'Faltan ' + Mon(t.cuota - t.venta)), 'fa-bullseye', t.cumpl >= 100 ? '#27AE60' : '#E67E22') +
		Kpi(col, 'CRECIMIENTO', Crec(t.crec), 'vs. evento anterior<br>' + Mon(t.anterior), 'fa-line-chart', t.crec >= 0 ? '#27AE60' : '#C0392B') +
		Kpi(col, 'CLIENTES REGISTRADOS', Ent(t.reg),
		    '<span class="fa fa-user"></span> Presencial: <b>' + Ent(t.reg_p) + '</b> &nbsp; <span class="fa fa-laptop"></span> Virtual: <b>' + Ent(t.reg_v) + '</b>' +
		    (t.reg_o > 0 ? '<br>Sin tipo: <b>' + Ent(t.reg_o) + '</b>' : ''), 'fa-users', '#8E44AD') +
		Kpi(col, 'CLIENTES CON COMPRA', Ent(t.con_compra), Pct(t.con_compra, t.reg) + '% de los registrados<br>Asistentes (zonas): <b>' + Ent(t.asistentes) + '</b>', 'fa-shopping-cart', '#16A085') +
		Kpi(col, 'CLIENTES SIN COMPRA', '<span class="rp-bad">' + Ent(t.sin_compra) + '</span>', Pct(t.sin_compra, t.reg) + '% de los registrados<br><a href="#" class="rp-ver-sin">Ver lista</a>', 'fa-user-times', '#C0392B')
	);
	$('#rpBadgeSin').text(t.sin_compra);

	// Ventas por sede
	var h = '<thead><tr><th>SEDE</th><th class="rp-num">VENTA</th><th class="rp-num">CUOTA</th><th>CUMPLIMIENTO</th>' +
	        '<th class="rp-num">FALTAN</th><th class="rp-num">VENTA ANTERIOR</th><th class="rp-num">CRECIMIENTO</th>' +
	        '<th class="rp-num">% DE LA VENTA</th><th class="rp-num">ASISTENTES</th></tr></thead><tbody>';
	$.each(RP.sedes, function(i, s){
		if(s.cuota == 0 && s.venta == 0 && s.anterior == 0){ return; }
		h += '<tr><td><b>' + SedePunto(s.cod) + Esc(s.nombre) + '</b></td>' +
		     '<td class="rp-num"><b>' + Mon(s.venta) + '</b></td>' +
		     '<td class="rp-num">' + Mon(s.cuota) + '</td>' +
		     '<td>' + Barra(s.cumpl) + '</td>' +
		     '<td class="rp-num">' + (s.venta >= s.cuota ? '<span class="rp-ok">Cumplida</span>' : Mon(s.cuota - s.venta)) + '</td>' +
		     '<td class="rp-num">' + Mon(s.anterior) + '</td>' +
		     '<td class="rp-num">' + Crec(s.crec) + '</td>' +
		     '<td class="rp-num">' + Pct(s.venta, t.venta) + '%</td>' +
		     '<td class="rp-num">' + Ent(s.asistentes) + '</td></tr>';
	});
	h += '</tbody><tfoot><tr><td>TOTAL EVENTO</td><td class="rp-num">' + Mon(t.venta) + '</td><td class="rp-num">' + Mon(t.cuota) + '</td>' +
	     '<td>' + Barra(t.cumpl) + '</td><td class="rp-num">' + (t.venta >= t.cuota ? 'Cumplida' : Mon(t.cuota - t.venta)) + '</td>' +
	     '<td class="rp-num">' + Mon(t.anterior) + '</td><td class="rp-num">' + Crec(t.crec) + '</td><td class="rp-num">100%</td>' +
	     '<td class="rp-num">' + Ent(t.asistentes) + '</td></tr></tfoot>';
	$('#rpTablaSedes').html(h);

	// Registro P / V por sede
	var conOtros = t.reg_o > 0;
	h = '<thead><tr><th>SEDE</th><th class="rp-num">PRESENCIAL</th><th class="rp-num">VIRTUAL</th>' +
	    (conOtros ? '<th class="rp-num">SIN TIPO</th>' : '') +
	    '<th class="rp-num">TOTAL</th><th class="rp-num">CON COMPRA</th><th class="rp-num">SIN COMPRA</th></tr></thead><tbody>';
	$.each(RP.sedes, function(i, s){
		if(s.reg == 0){ return; }
		h += '<tr><td><b>' + SedePunto(s.cod) + Esc(s.nombre) + '</b></td>' +
		     '<td class="rp-num">' + Ent(s.reg_p) + ' <small class="text-muted">(' + Pct(s.reg_p, s.reg) + '%)</small></td>' +
		     '<td class="rp-num">' + Ent(s.reg_v) + ' <small class="text-muted">(' + Pct(s.reg_v, s.reg) + '%)</small></td>' +
		     (conOtros ? '<td class="rp-num">' + Ent(s.reg_o) + '</td>' : '') +
		     '<td class="rp-num"><b>' + Ent(s.reg) + '</b></td>' +
		     '<td class="rp-num rp-ok">' + Ent(s.con_compra) + '</td>' +
		     '<td class="rp-num rp-bad">' + Ent(s.sin_compra) + '</td></tr>';
	});
	h += '</tbody><tfoot><tr><td>TOTAL</td><td class="rp-num">' + Ent(t.reg_p) + '</td><td class="rp-num">' + Ent(t.reg_v) + '</td>' +
	     (conOtros ? '<td class="rp-num">' + Ent(t.reg_o) + '</td>' : '') +
	     '<td class="rp-num">' + Ent(t.reg) + '</td><td class="rp-num">' + Ent(t.con_compra) + '</td><td class="rp-num">' + Ent(t.sin_compra) + '</td></tr></tfoot>';
	$('#rpTablaRegistro').html(t.reg > 0 ? h : '<tbody><tr><td class="text-center text-muted">Sin clientes registrados</td></tr></tbody>');

	// Top 10 zonas con menos venta (solo zonas con cuota asignada)
	var bottom = $.grep(RP.zonas, function(z){ return z.cuota > 0; })
		.sort(function(a, b){ return a.venta - b.venta || a.cumpl - b.cumpl; })
		.slice(0, 10);
	h = '<thead><tr><th>#</th><th>ZONA</th><th>VENDEDOR</th><th>SEDE</th><th class="rp-num">VENTA</th><th class="rp-num">CUOTA</th><th>CUMPLIMIENTO</th></tr></thead><tbody>';
	$.each(bottom, function(i, z){
		h += '<tr><td>' + (i + 1) + '</td><td><b>' + Esc(z.ZONA_VENTAS) + '</b></td><td>' + Esc(z.ZONA_DESCRIPCION) + '</td>' +
		     '<td>' + SedePunto(z.oficina) + Esc(SedeNombre(z.oficina, z.OFICINA_DESCRIPCION)) + '</td>' +
		     '<td class="rp-num"><b>' + Mon(z.venta) + '</b></td><td class="rp-num">' + Mon(z.cuota) + '</td><td>' + Barra(z.cumpl) + '</td></tr>';
	});
	$('#rpTablaBottom').html(h + '</tbody>');
}

/* ------------------------------ zonas ------------------------------ */
function PintarZonas(){
	if(RP.zonas.length == 0){
		$('#rpTablaZonas').html('<tbody><tr><td class="text-center">NO EXISTEN RESULTADOS</td></tr></tbody>');
		return;
	}
	var t = Totales();
	var h = '<thead><tr><th>ZONA</th><th>VENDEDOR</th><th class="rp-num">PRESUPUESTO</th><th class="rp-num">VENTA</th>' +
	        '<th>CUMPLIMIENTO</th><th class="rp-num">FALTAN</th><th class="rp-num">CLIENTES ASISTENTES</th></tr></thead><tbody>';
	$.each(RP.sedes, function(i, s){
		if(s.zonas.length == 0){ return; }
		h += '<tr class="rp-grupo"><td colspan="7" style="background:' + SedeColor(s.cod) + ';">' + Esc(s.cod) + ' - ' + Esc(s.nombre) + '</td></tr>';
		var zonas = s.zonas.slice().sort(function(a, b){ return b.cumpl - a.cumpl; });
		$.each(zonas, function(j, z){
			h += '<tr><td><b>' + Esc(z.ZONA_VENTAS) + '</b></td><td>' + Esc(z.ZONA_DESCRIPCION) + '</td>' +
			     '<td class="rp-num">' + Mon(z.cuota) + '</td><td class="rp-num">' + Mon(z.venta) + '</td>' +
			     '<td>' + (z.cuota > 0 ? Barra(z.cumpl) : '<span class="text-muted">Sin presupuesto</span>') + '</td>' +
			     '<td class="rp-num">' + (z.venta >= z.cuota ? '<span class="rp-ok">Cumplida</span>' : Mon(z.cuota - z.venta)) + '</td>' +
			     '<td class="rp-num">' + Ent(z.CLIENTES_ASISTENTES) + '</td></tr>';
		});
		h += '<tr class="rp-subtotal"><td colspan="2">SUBTOTAL ' + Esc(s.nombre) + '</td><td class="rp-num">' + Mon(s.cuota) + '</td>' +
		     '<td class="rp-num">' + Mon(s.venta) + '</td><td>' + Barra(s.cumpl) + '</td>' +
		     '<td class="rp-num">' + (s.venta >= s.cuota ? 'Cumplida' : Mon(s.cuota - s.venta)) + '</td><td class="rp-num">' + Ent(s.asistentes) + '</td></tr>';
	});
	h += '</tbody><tfoot><tr><td colspan="2">TOTAL EVENTO</td><td class="rp-num">' + Mon(t.cuota) + '</td><td class="rp-num">' + Mon(t.venta) + '</td>' +
	     '<td>' + Barra(t.cumpl) + '</td><td class="rp-num">' + (t.venta >= t.cuota ? 'Cumplida' : Mon(t.cuota - t.venta)) + '</td>' +
	     '<td class="rp-num">' + Ent(t.asistentes) + '</td></tr></tfoot>';
	$('#rpTablaZonas').html(h);
}

/* ------------------------------ sin compras ------------------------------ */
function PintarFiltroSedes(){
	var actual = $('#rpFiltroSede').val() || '';
	var h = '<option value="">Todas las sedes</option>';
	$.each(RP.sedes, function(i, s){
		if(s.sin_compra > 0){ h += '<option value="' + Esc(s.cod) + '">' + Esc(s.nombre) + ' (' + s.sin_compra + ')</option>'; }
	});
	$('#rpFiltroSede').html(h).val(actual);
}

function PintarSinCompra(){
	var sede  = $('#rpFiltroSede').val() || '';
	var tipo  = $('#rpFiltroTipo').val() || '';
	var texto = $.trim($('#rpFiltroTexto').val() || '').toUpperCase();
	var n = 0;

	var h = '<thead><tr><th>#</th><th>CÓDIGO SAP</th><th>CLIENTE</th><th>CIUDAD</th><th>SEDE</th><th>ZONA</th><th>VENDEDOR</th><th>REGISTRO</th></tr></thead><tbody>';
	$.each(RP.clientes, function(i, c){
		if(c.venta > 0){ return; }
		if(sede && c.oficina != sede){ return; }
		if(tipo && c.tipo != tipo){ return; }
		var vend = c.vendedor || RP.vendedores[String(c.zona)] || '';
		if(texto && (c.sap + ' ' + c.nombre + ' ' + c.zona + ' ' + vend + ' ' + c.ciudad).toUpperCase().indexOf(texto) < 0){ return; }
		n++;
		h += '<tr><td>' + n + '</td><td><b>' + Esc(c.sap) + '</b></td><td>' + Esc(c.nombre) + '</td><td>' + Esc(c.ciudad) + '</td>' +
		     '<td>' + SedePunto(c.oficina) + Esc(SedeNombre(c.oficina)) + '</td><td>' + Esc(c.zona) + '</td><td>' + Esc(vend) + '</td>' +
		     '<td>' + (c.tipo == 'P' ? '<span class="label label-primary">PRESENCIAL</span>' :
		               c.tipo == 'V' ? '<span class="label label-info">VIRTUAL</span>' : '<span class="label label-default">SIN TIPO</span>') + '</td></tr>';
	});
	if(n == 0){ h += '<tr><td colspan="8" class="text-center text-muted">No hay clientes registrados sin compras con este filtro</td></tr>'; }
	$('#rpTablaSin').html(h + '</tbody>');
	$('#rpConteoSin').text(n + ' cliente(s) sin compras');
}
