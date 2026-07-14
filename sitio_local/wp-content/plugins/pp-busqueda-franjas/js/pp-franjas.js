/**
 * Filtros personalizados — panel de Disponibilidad.
 *
 * Flujo: el usuario elige el SERVICIO (catálogo de Tipos de Servicio, con
 * ícono por tipo) y, según su modo de reserva, el panel muestra:
 *   - franja: fecha + hora (Adiestramiento, Peluquería…)
 *   - rango:  entrada + salida (Guardería/Cuidador — estadías)
 * Sin servicio elegido, el modo es franja y busca en todo lo reservable.
 *
 * El selector es un desplegable PROPIO (no un <select> nativo) porque el
 * requisito es mostrar un ícono por cada tipo de servicio, algo que las
 * <option> nativas no soportan. En desktop el panel es una sola fila.
 *
 * Regla de oro: NADA se busca hasta pulsar "Buscar disponibilidad"
 * (decisión de performance). El filtro persiste ante otros filtros porque
 * los valores viajan como inputs ocultos DENTRO de #listeo_core-search-form
 * (el AJAX nativo de Listeo serializa ese formulario), hasta pulsar Quitar.
 *
 * Datos: window.PP_FRANJAS { horaMin, horaMax, paso, fechaMin, fechaMax,
 *   reemplazo, servicios{slug:{nombre,modo,icono}}, iconoTodos, oferta[],
 *   modo, servicio, fecha, hora, entrada, salida }
 */
(function ($) {
	'use strict';

	/* Montaje INMEDIATO (anti-salto): este script se encola en el footer, así
	   que el DOM de arriba ya existe cuando se ejecuta. Esperar a $(ready)
	   dejaba ver un primer pintado sin el botón/panel (FOUC) y luego el
	   reacomodo. Si por algo el DOM aún no estuviera, cae a DOMContentLoaded. */
	function ppFranjasInit() {
		var D = window.PP_FRANJAS;
		if (!D) { return; }

		// Anti-parpadeo: con la barra activa la fila de botones nace oculta por
		// CSS; se revela cuando TODO está montado (final de este init). Si esta
		// página no monta nada, se revela de inmediato al salir.
		function revelar() {
			$('.filter-button-container, .pp-franjas-topbar').addClass('pp-franjas-listo');
		}

		var $resultados = $('#listeo-listings-container');
		if (!$resultados.length) { revelar(); return; } // esta página no lista resultados

		var SERVICIOS = D.servicios || {};
		var ICONO_TODOS = D.iconoTodos || 'fa-th-large';
		var PARAMS = ['pp_franja_servicio', 'pp_franja_fecha', 'pp_franja_hora', 'pp_franja_entrada', 'pp_franja_salida'];

		/* ---- Barra superior (layout Rover, solo Directorio/Guardería) ----
		   El body lleva .pp-barra-superior (lo decide el servidor según el
		   contexto). La fila del botón "Mostrar Filtros" + panel se muda a una
		   BARRA a todo lo ancho, antes de las columnas resultados|mapa. La
		   página scrollea NORMAL (el CSS del plugin desbloquea el candado del
		   template): header y barra se van con el scroll y el MAPA queda
		   anclado (sticky). Los botones del tema conservan sus eventos: mover
		   un nodo no los pierde. */
		var barraActiva = document.body.classList.contains('pp-barra-superior');
		// La BARRA y el scroll tipo Rover son SOLO desktop: en móvil se conserva
		// el layout nativo del template (mapa arriba con "Mostrar Mapa", luego
		// título y botones) y el panel se abre bajo demanda.
		var esDesktop = window.matchMedia('(min-width: 993px)').matches;
		var $split = $('.full-page-container.full-page-jobs').first();
		if (barraActiva && esDesktop && $split.length) {
			var $fbc = $('.filters-container .filter-button-container').first();
			if ($fbc.length) {
				var $topbar = $('<div class="pp-franjas-topbar"></div>');
				$split.before($topbar);
				$topbar.append($fbc);
			}

			// El botón flotante de filtros del tema se activaba con el scroll
			// INTERNO de la columna (que ya no scrollea): replicamos el mismo
			// disparador sobre el scroll del documento. Su sticky ya lo ancla.
			// Guard de estado: solo se toca el DOM al CRUZAR el umbral, no en
			// cada tick de scroll.
			var $stickyBtn = $('.sticky-filter-button');
			var stickyVisible = null;
			$(window).on('scroll', function () {
				var mostrar = window.scrollY >= 240;
				if (mostrar !== stickyVisible) {
					stickyVisible = mostrar;
					$stickyBtn.toggleClass('btn-visible', mostrar);
				}
			});

			// El mapa se inicializa después: un empujón para que tome el alto.
			setTimeout(function () {
				window.dispatchEvent(new Event('resize'));
			}, 500);
		}

		// Anclaje: con "reemplazo" activo va en el CABEZOTE — la fila del botón
		// "Mostrar Filtros" (ya sea en la barra superior o en su lugar clásico).
		var reemplazo = !!parseInt(D.reemplazo, 10);
		var $cabezote = $('.pp-franjas-topbar .filter-button-container, .filters-container .filter-button-container').first();
		if (reemplazo) {
			// por si un caché viejo lo trae (esté en su sitio clásico o ya movido a la barra)
			$('.filters-container .slider-container, .pp-franjas-topbar .slider-container').remove();
		}

		function esc(s) {
			return String(s || '').replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		// Servicios CON oferta en el contexto actual (categoría o sitio):
		// los demás se muestran deshabilitados como "(sin resultados)".
		var OFERTA = {};
		(D.oferta || []).forEach(function (slug) { OFERTA[slug] = true; });

		/* ---------------- Selector de servicio (desplegable propio) --------- */

		// Lista de opciones en orden: "Todos" + cada tipología del catálogo.
		var OPCIONES = [{ value: '', nombre: 'Todos los servicios', modo: 'franja', icono: ICONO_TODOS, hay: true }];
		Object.keys(SERVICIOS).forEach(function (slug) {
			OPCIONES.push({
				value: slug,
				nombre: SERVICIOS[slug].nombre,
				modo: SERVICIOS[slug].modo || 'franja',
				icono: SERVICIOS[slug].icono || 'fa-paw',
				hay: !!OFERTA[slug]
			});
		});

		function opcionPorValor(v) {
			for (var i = 0; i < OPCIONES.length; i++) {
				if (OPCIONES[i].value === v) { return OPCIONES[i]; }
			}
			return OPCIONES[0];
		}

		function selectorHtml() {
			var items = '';
			OPCIONES.forEach(function (o) {
				var off = !o.hay;
				items += '<li class="ppfs__opt' + (off ? ' ppfs__opt--off' : '') + '"' +
					' role="option" data-value="' + esc(o.value) + '" data-modo="' + esc(o.modo) + '"' +
					(off ? ' aria-disabled="true"' : '') + '>' +
						'<i class="ppfs__optico fa ' + esc(o.icono) + '" aria-hidden="true"></i>' +
						'<span class="ppfs__optnom">' + esc(o.nombre) + '</span>' +
						(off ? '<span class="ppfs__off">(sin resultados)</span>' : '') +
					'</li>';
			});
			return '<div class="pp-franjas__servicio ppfs" data-value="">' +
					'<button type="button" class="ppfs__toggle" aria-haspopup="listbox" aria-expanded="false">' +
						'<i class="ppfs__icon fa ' + esc(ICONO_TODOS) + '" aria-hidden="true"></i>' +
						'<span class="ppfs__label">Todos los servicios</span>' +
						'<i class="ppfs__caret fa fa-angle-down" aria-hidden="true"></i>' +
					'</button>' +
					'<ul class="ppfs__list" role="listbox" hidden>' + items + '</ul>' +
				'</div>';
		}

		function opcionesHora() {
			// Desde la HORA DE INICIO configurada (D.horaMin) hasta el fin del
			// día, con el intervalo configurado (D.paso). La ETIQUETA va en 12 h
			// con a.m./p.m.; el VALOR queda en 24 h (HH:MM), que es lo que lee
			// el motor.
			var out = '<option value="">Hora…</option>';
			var paso = parseInt(D.paso, 10) || 30;
			var ini = (D.horaMin || '08:00').split(':');
			var desde = (parseInt(ini[0], 10) || 0) * 60 + (parseInt(ini[1], 10) || 0);
			for (var m = desde; m < 24 * 60; m += paso) {
				var hh = Math.floor(m / 60), mm = m % 60;
				var val = ('0' + hh).slice(-2) + ':' + ('0' + mm).slice(-2);
				var ampm = hh < 12 ? 'a.m.' : 'p.m.';
				var h12 = hh % 12; if (h12 === 0) { h12 = 12; }
				var lbl = h12 + ':' + ('0' + mm).slice(-2) + ' ' + ampm;
				out += '<option value="' + val + '">' + lbl + '</option>';
			}
			return out;
		}

		/* ---------------- Construcción del panel ---------------- */

		var html =
			'<div class="pp-franjas" role="search" aria-label="Buscar disponibilidad">' +
				'<span class="pp-franjas__titulo"><i class="fa fa-calendar-check-o" aria-hidden="true"></i> Disponibilidad</span>' +
				'<div class="pp-franjas__fila">' +
					selectorHtml() +
					'<span class="pp-franjas__grupo pp-franjas__grupo--franja">' +
						'<input type="date" class="pp-franjas__fecha" min="' + D.fechaMin + '" max="' + D.fechaMax + '" aria-label="Fecha" placeholder="Fecha">' +
						'<select class="pp-franjas__hora" aria-label="Hora">' + opcionesHora() + '</select>' +
					'</span>' +
					'<span class="pp-franjas__grupo pp-franjas__grupo--rango" hidden>' +
						'<input type="date" class="pp-franjas__entrada" min="' + D.fechaMin + '" max="' + D.fechaMax + '" aria-label="Entrada" title="Entrada" placeholder="Entrada">' +
						'<span class="pp-franjas__flecha" aria-hidden="true">→</span>' +
						'<input type="date" class="pp-franjas__salida" min="' + D.fechaMin + '" max="' + D.fechaMax + '" aria-label="Salida" title="Salida" placeholder="Salida">' +
					'</span>' +
					'<button type="button" class="pp-franjas__buscar button"><i class="fa fa-search" aria-hidden="true"></i> Buscar disponibles</button>' +
				'</div>' +
				'<div class="pp-franjas__pie" hidden>' +
					'<span class="pp-franjas__estado" hidden></span>' +
					'<button type="button" class="pp-franjas__quitar" hidden>&times; Quitar</button>' +
				'</div>' +
			'</div>';
		var $panel = $(html);
		// Agrupar título + fila en .pp-franjas__principal (la línea principal);
		// el pie (.pp-franjas__pie) queda aparte, DEBAJO, así no infla el ancho
		// de la línea principal ni descentra el conjunto al aplicar un filtro.
		$panel.find('.pp-franjas__titulo, .pp-franjas__fila')
			.wrapAll('<div class="pp-franjas__principal"></div>');
		if (reemplazo && $cabezote.length) {
			$panel.addClass('pp-franjas--cabezote');
			// El botón "Mostrar Filtros" (componente del tema) y el panel son
			// COMPONENTES DISTINTOS que deben compartir fila: los envolvemos en
			// una fila propia que controlamos (el botón se MUEVE —no se clona—
			// así conserva su comportamiento). No se anida uno dentro del otro.
			var $btn = $cabezote.children('.enable-filters-button').first();
			if ($btn.length) {
				var $row = $('<div class="pp-franjas-row"></div>');
				$btn.before($row);
				$row.append($btn).append($panel);
			} else {
				$cabezote.append($panel);
			}
		} else {
			$resultados.before($panel); // anclaje clásico: encima de los resultados
		}

		var $ppfs   = $panel.find('.ppfs');
		var $toggle = $panel.find('.ppfs__toggle');
		var $lista  = $panel.find('.ppfs__list');
		var $fecha  = $panel.find('.pp-franjas__fecha');
		var $hora   = $panel.find('.pp-franjas__hora');
		var $entrada = $panel.find('.pp-franjas__entrada');
		var $salida  = $panel.find('.pp-franjas__salida');
		var $buscar  = $panel.find('.pp-franjas__buscar');
		var $pie     = $panel.find('.pp-franjas__pie');
		var $quitar  = $panel.find('.pp-franjas__quitar');
		var $estado  = $panel.find('.pp-franjas__estado');

		/* ---- Calendario estilizado (jQuery UI) SOLO en desktop ----
		   En móvil se conserva el <input type=date> nativo (mejor UX táctil, el
		   selector del sistema). En desktop se convierte a texto + datepicker
		   con el look de la marca. Formato del valor: 'yy-mm-dd' = AAAA-MM-DD,
		   exactamente lo que lee el motor. Sin coloreado de disponibilidad
		   (eso era el Nivel B, descartado). */
		if (esDesktop && $.fn && $.fn.datepicker) {
			var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
			var dpBase = {
				dateFormat: 'yy-mm-dd',
				firstDay: 1,           // semana inicia en lunes
				minDate: 0,            // desde hoy (no fechas pasadas)
				maxDate: '+18M',
				monthNames: MESES,
				monthNamesShort: MESES,
				dayNamesMin: ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'],
				showOtherMonths: true,
				selectOtherMonths: true,
				// Marca SOLO nuestro popup para estilarlo sin tocar otros
				// datepickers del tema (jQuery UI comparte un único div).
				// Usamos el selector directo (inst.dpDiv no es fiable entre
				// versiones) y un tick para que el div ya exista/esté armado.
				beforeShow: function () {
					window.setTimeout(function () { $('#ui-datepicker-div').addClass('pp-franjas-dp'); }, 0);
				},
				onClose: function () { $('#ui-datepicker-div').removeClass('pp-franjas-dp'); }
			};
			var ponerCalendario = function ($input, ph, extra) {
				$input.attr({ type: 'text', readonly: 'readonly', placeholder: ph })
					.addClass('pp-franjas__dp')
					.datepicker($.extend({}, dpBase, extra || {}));
			};
			ponerCalendario($fecha, 'Fecha');
			// Rango: la salida nunca antes de la entrada.
			ponerCalendario($entrada, 'Entrada', {
				onSelect: function (d) { $salida.datepicker('option', 'minDate', d || 0); }
			});
			ponerCalendario($salida, 'Salida');
		}

		/* ---------------- Estado del selector ---------------- */

		function getServicio() { return $ppfs.attr('data-value') || ''; }

		function setServicio(slug) {
			var o = opcionPorValor(slug);
			$ppfs.attr('data-value', o.value);
			$toggle.find('.ppfs__icon').attr('class', 'ppfs__icon fa ' + o.icono);
			$toggle.find('.ppfs__label').text(o.nombre);
			$lista.find('.ppfs__opt').removeClass('ppfs__opt--sel')
				.filter('[data-value="' + o.value.replace(/"/g, '\\"') + '"]').addClass('ppfs__opt--sel');
			pintarModo(o.modo);
		}

		function abrir(v) {
			$lista.prop('hidden', !v);
			$toggle.attr('aria-expanded', v ? 'true' : 'false');
			$ppfs.toggleClass('ppfs--open', v);
		}

		$toggle.on('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			abrir($lista.prop('hidden'));
		});
		// Elegir opción (las deshabilitadas no responden).
		$lista.on('click', '.ppfs__opt:not(.ppfs__opt--off)', function () {
			setServicio($(this).attr('data-value'));
			abrir(false);
		});
		// Cerrar al hacer clic fuera o con Escape.
		$(document).on('click', function (e) {
			if (!$(e.target).closest('.ppfs').length) { abrir(false); }
		});
		$(document).on('keydown', function (e) {
			if ('Escape' === e.key) { abrir(false); }
		});

		/* ---------------- Modo según el servicio ---------------- */

		// Cambiar de servicio SOLO cambia los campos visibles: no busca nada.
		function pintarModo(modo) {
			$panel.find('.pp-franjas__grupo--franja').prop('hidden', modo !== 'franja');
			$panel.find('.pp-franjas__grupo--rango').prop('hidden', modo !== 'rango');
		}

		// La salida nunca puede ser anterior a la entrada.
		$entrada.on('change', function () {
			if (this.value) { $salida.attr('min', this.value); }
		});

		/* -------- Inputs ocultos en el formulario de filtros de Listeo -------- */

		function armarForm(valores) {
			var form = document.querySelector('#listeo_core-search-form');
			if (!form) { return false; }
			PARAMS.forEach(function (name) {
				var input = form.querySelector('input[name="' + name + '"]');
				var valor = valores[name] || '';
				if (!valor) {
					if (input) { input.parentNode.removeChild(input); }
					return;
				}
				if (!input) {
					input = document.createElement('input');
					input.type = 'hidden';
					input.name = name;
					form.appendChild(input);
				}
				input.value = valor;
			});
			return true;
		}

		function armarUrl(valores) {
			if (!window.history || !window.history.replaceState) { return; }
			var url = new URL(window.location.href);
			PARAMS.forEach(function (name) {
				if (valores[name]) {
					url.searchParams.set(name, valores[name]);
				} else {
					url.searchParams.delete(name);
				}
			});
			window.history.replaceState({}, '', url.toString());
		}

		function irConParametros(valores) {
			var url = new URL(window.location.href);
			PARAMS.forEach(function (name) {
				if (valores[name]) {
					url.searchParams.set(name, valores[name]);
				} else {
					url.searchParams.delete(name);
				}
			});
			window.location.assign(url.toString());
		}

		function refrescar() {
			$resultados.triggerHandler('update_results', [1, false]);
		}

		function pintarActivo(valores) {
			var activo = !!(valores.pp_franja_fecha || valores.pp_franja_entrada);
			$panel.toggleClass('pp-franjas--activa', activo);
			$pie.prop('hidden', !activo);
			$quitar.prop('hidden', !activo);
			$estado.prop('hidden', !activo);
			if (!activo) { return; }
			var nombre = valores.pp_franja_servicio ? opcionPorValor(valores.pp_franja_servicio).nombre : '';
			var texto;
			if (valores.pp_franja_entrada) {
				texto = (nombre ? nombre + ': ' : 'Disponibles: ') + valores.pp_franja_entrada + ' → ' + valores.pp_franja_salida;
			} else {
				texto = (nombre ? nombre + ': ' : 'Disponibles: ') + valores.pp_franja_fecha + ' a las ' + valores.pp_franja_hora;
			}
			$estado.text(texto);
		}

		function error() {
			$panel.addClass('pp-franjas--error');
			setTimeout(function () { $panel.removeClass('pp-franjas--error'); }, 1200);
		}

		/* -------- Aplicar (solo con el clic del usuario) -------- */

		$buscar.on('click', function () {
			var slug = getServicio();
			var modo = opcionPorValor(slug).modo;
			var valores = { pp_franja_servicio: slug };

			if (modo === 'rango') {
				var ent = $entrada.val(), sal = $salida.val();
				if (!ent || !sal || sal < ent) { error(); return; }
				valores.pp_franja_entrada = ent;
				valores.pp_franja_salida  = sal;
			} else {
				var fecha = $fecha.val(), hora = $hora.val();
				if (!fecha || !hora) { error(); return; }
				valores.pp_franja_fecha = fecha;
				valores.pp_franja_hora  = hora;
			}

			if (armarForm(valores)) {
				armarUrl(valores);
				pintarActivo(valores);
				refrescar();
			} else {
				irConParametros(valores); // sin formulario de filtros: recarga
			}
		});

		$quitar.on('click', function () {
			if (armarForm({})) {
				armarUrl({});
				pintarActivo({});
				refrescar();
			} else {
				irConParametros({});
			}
		});

		/* -------- Estado inicial: la página llegó con filtro en la URL -------- */

		setServicio(D.servicio || ''); // pinta modo y toggle por defecto

		if (D.modo) {
			var iniciales = { pp_franja_servicio: D.servicio || '' };
			if (D.modo === 'rango') {
				$entrada.val(D.entrada);
				$salida.val(D.salida).attr('min', D.entrada);
				iniciales.pp_franja_entrada = D.entrada;
				iniciales.pp_franja_salida  = D.salida;
			} else {
				$fecha.val(D.fecha);
				$hora.val(D.hora);
				iniciales.pp_franja_fecha = D.fecha;
				iniciales.pp_franja_hora  = D.hora;
			}
			armarForm(iniciales); // las próximas búsquedas AJAX lo conservan
			pintarActivo(iniciales);
		}

		/* -------- MÓVIL: placeholder de texto en los <input type=date> --------
		   El selector de fecha nativo (que en móvil se conserva) NO admite un
		   placeholder de texto: vacío se ve en blanco y no dice qué campo es.
		   Truco estándar: el campo nace como type=text (muestra su placeholder
		   "Fecha"/"Entrada"/"Salida"); al enfocarlo pasa a type=date y abre el
		   selector NATIVO; si se deja vacío, vuelve a texto. Así hay título Y se
		   mantiene el picker nativo. En desktop no aplica (usa el datepicker de
		   marca, que ya muestra su placeholder). */
		if (!esDesktop) {
			$panel.find('.pp-franjas__fecha, .pp-franjas__entrada, .pp-franjas__salida').each(function () {
				var input = this;
				if (input.value) { return; }   // ya trae valor: se queda como date con la fecha
				input.type = 'text';           // vacío: type=text para mostrar el título "Fecha"
				input.addEventListener('focus', function onFoco() {
					input.type = 'date';       // al enfocar: selector NATIVO
					input.removeEventListener('focus', onFoco); // ya es date: no volver a texto
					if (typeof input.showPicker === 'function') {
						try { input.showPicker(); } catch (e) {}
					}
				});
			});
		}

		/* -------- MÓVIL: el panel se abre bajo demanda --------
		   Junto a "Mostrar Filtros" aparece el botón "Buscar disponibilidad";
		   al pulsarlo se despliega/cierra el módulo (apilado para móvil).
		   Si la página llegó con un filtro en la URL, abre ya desplegado
		   para que se vea el estado y el botón Quitar. */
		if (barraActiva && !esDesktop) {
			$panel.addClass('pp-franjas--movil');
			// El botón alterna: cerrado "Buscar disponibilidad"; abierto muestra
			// una "✕" y cambia a "Cerrar" para que se entienda cómo ocultarlo.
			var $abrir = $(
				'<button type="button" class="pp-franjas__abrir" aria-expanded="false">' +
					'<span class="pp-franjas__abrir-x" aria-hidden="true">&times;</span>' +
					'<span class="pp-franjas__abrir-lbl">Buscar disponibilidad</span>' +
				'</button>'
			);
			var $mf = $cabezote.find('.enable-filters-button').first();
			if ($mf.length) {
				$abrir.insertAfter($mf);
			} else {
				$panel.before($abrir);
			}
			var panelAbierto = false;
			var setAbierto = function (v) {
				panelAbierto = v;
				$panel.toggleClass('pp-franjas--abierta', v);
				$abrir.toggleClass('pp-franjas__abrir--activo', v)
					.attr('aria-expanded', v ? 'true' : 'false');
				$abrir.find('.pp-franjas__abrir-lbl').text(v ? 'Cerrar disponibilidad' : 'Buscar disponibilidad');
			};
			setAbierto(!!D.modo); // con filtro activo en la URL, abierto
			$abrir.on('click', function () {
				setAbierto(!panelAbierto);
			});
		}

		revelar(); // todo montado: mostrar la fila de botones sin salto
	}

	if ('loading' !== document.readyState) {
		ppFranjasInit(); // footer: el DOM de arriba ya está — montar YA (sin FOUC)
	} else {
		document.addEventListener('DOMContentLoaded', ppFranjasInit);
	}
})(jQuery);
