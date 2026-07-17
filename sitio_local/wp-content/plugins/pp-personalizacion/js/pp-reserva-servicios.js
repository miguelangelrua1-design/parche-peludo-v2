/**
 * Paso "Servicios" del popup de reserva (flujo servicio → agenda).
 *
 * Overlay propio dentro de #lbp-booking-modal, mismo patrón (y mismas
 * clases CSS) que el gate de Mascota: Mascota → SERVICIOS → Fecha y hora
 * → Confirmar. En este paso el cliente elige tipo de servicio, profesional
 * (si el tipo tiene) y servicios; el acordeón nativo de servicios se MUEVE
 * aquí (sus handlers son delegados en document, siguen funcionando).
 *
 * La agenda que carga después es nativa de Booking Plus: al confirmar el
 * paso se dispara el click sobre la tarjeta de recurso nativa (oculta) del
 * profesional/agenda elegido → selectResource() hace todo (ajustes del
 * recurso vía AJAX, filtrado por recurso, goToStep(1)).
 *
 * DEGRADACIÓN: cualquier pieza nativa ausente → el overlay no se monta y
 * el flujo nativo queda intacto. Sin excepciones.
 */
(function ($) {
	'use strict';

	$(function () {
		var D = window.PP_RS;
		var $modal = $('#lbp-booking-modal');

		// Guardas de arranque: sin datos, sin modal o sin tipologías → nativo.
		if (!D || !D.activo || !$modal.length || !D.tipologias || !D.tipologias.length) {
			return;
		}

		// Señal para otros módulos (pp-servicios-reserva desactiva sus tabs).
		window.PP_RS_ACTIVO = true;

		function esc(s) {
			return String(s || '').replace(/[&<>"']/g, function (c) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
			});
		}

		/* ================= Estado ================= */
		var tipoElegido = null;     // objeto de D.tipologias
		var profElegido = null;     // { id, nombre } | null
		var resuelto = false;       // el paso ya se completó en esta apertura

		/* ================= Datos derivados ================= */

		// Profesionales por tipología: los de la tipología exacta + los "todos" ('').
		function profesionalesDe(slug) {
			var out = [];
			$('#lbp-booking-modal .lbp-resource-card').each(function () {
				var id = parseInt($(this).attr('data-resource-id'), 10);
				if (!id || !(id in D.profesionales)) { return; }
				var t = D.profesionales[id];
				if (t === '' || t === slug) {
					out.push({
						id: id,
						nombre: $(this).find('.lbp-resource-info h4').first().text() || ('#' + id),
						sub: $(this).find('.lbp-resource-subtitle').first().text() || '',
						img: $(this).find('img').first().attr('src') || ''
					});
				}
			});
			return out;
		}

		// ¿El popup está en modo recursos? (paso 0 nativo presente)
		function hayPasoRecursos() {
			return $('#lbp-booking-modal .lbp-step-0').length > 0;
		}

		/* ================= Overlay (reutiliza el diseño del gate) ================= */

		var sidebarHtml =
			'<aside class="pp-gate__sidebar">' +
				'<span class="pp-gate__sb-eyebrow">Reserva</span>' +
				'<h4 class="pp-gate__sb-titulo">' + esc($('#lbp-booking-modal .lbp-sidebar-header h3').first().text() || '') + '</h4>' +
				'<div class="pp-gate__sb-pasos">' +
					'<span class="pp-gate__sb-paso completado"><i>🐾</i> Mascota</span>' +
					'<span class="pp-gate__sb-paso activo"><i>🧴</i> Servicios</span>' +
					'<span class="pp-gate__sb-paso"><i>1</i> Fecha y hora</span>' +
					'<span class="pp-gate__sb-paso"><i>2</i> Confirmar</span>' +
				'</div>' +
			'</aside>';

		var overlayHtml =
			'<div class="pp-gate pp-rs" style="display:none">' +
				'<button type="button" class="pp-gate__x pp-rs__x" aria-label="Cerrar">&times;</button>' +
				sidebarHtml +
				'<div class="pp-gate__contenido">' +
					'<div class="pp-gate__panel">' +
						'<h3 class="pp-gate__titulo">🧴 ¿Qué servicio necesitas?</h3>' +
						'<p class="pp-gate__sub">Elige el tipo de servicio' + (Object.keys(D.profesionales || {}).length ? ', el profesional' : '') + ' y lo que deseas reservar.</p>' +
						'<div class="pp-rs__tabs" role="tablist"></div>' +
						'<div class="pp-rs__profes" style="display:none">' +
							'<h4 class="pp-rs__subtitulo">¿Con quién?</h4>' +
							'<div class="pp-gate__cards pp-rs__profes-cards"></div>' +
						'</div>' +
						'<div class="pp-rs__servicios" style="display:none"></div>' +
						'<div class="pp-rs__acciones lbp-step-actions pp-acciones-fila">' +
							'<button type="button" class="button lbp-btn-back pp-rs__atras" data-step="1"><i class="fa fa-arrow-left"></i> Atrás</button>' +
							'<span class="pp-rs__eleccion pp-gate__eleccion"></span>' +
							'<button type="button" class="pp-gate__btn pp-gate__btn--primario pp-rs__siguiente" disabled>Siguiente</button>' +
						'</div>' +
					'</div>' +
				'</div>' +
			'</div>';

		var $overlay = null;
		var $accordeonMovido = null;

		function montar() {
			if ($overlay) { return true; }
			// Mismo contenedor que el gate de Mascota: el modal directamente.
			$overlay = $(overlayHtml);
			$modal.append($overlay);

			// Mover el acordeón nativo de servicios a nuestro paso. Handlers
			// delegados en document → siguen vivos tras el movimiento.
			var $secc = $modal.find('.lbp-services-section').first();
			if ($secc.length) {
				$accordeonMovido = $secc;
				// Se muestra al elegir el tipo de servicio (primero el tipo).
				$overlay.find('.pp-rs__servicios').append($secc);
				// Dentro del paso Servicios el acordeón vive abierto.
				$secc.find('.lbp-panel-dropdown').addClass('pp-rs__abierto');
			}

			pintarTabs();
			return true;
		}

		function pintarTabs() {
			var $tabs = $overlay.find('.pp-rs__tabs');
			$tabs.empty();
			D.tipologias.forEach(function (t, i) {
				$tabs.append(
					'<button type="button" class="pp-rs__tab" role="tab" data-i="' + i + '">' +
						esc(t.nombre) +
					'</button>'
				);
			});
			// Única tipología → se asume sola (mismo criterio que en el form).
			if (D.tipologias.length === 1) {
				elegirTipo(0);
			}
		}

		function elegirTipo(i) {
			if (tipoElegido && D.tipologias[i] && tipoElegido.slug === D.tipologias[i].slug) {
				return; // mismo tipo: no re-disparar (desmarcaría servicios elegidos)
			}
			tipoElegido = D.tipologias[i];
			profElegido = null;
			$overlay.find('.pp-rs__tab').removeClass('activo').filter('[data-i="' + i + '"]').addClass('activo');
			$overlay.find('.pp-rs__servicios').show();

			// Servicios visibles: SOLO los del tipo elegido (clase con !important,
			// gana a los .show()/.hide() inline del filtrado nativo por recurso).
			var permitidos = {};
			(tipoElegido.indices || []).forEach(function (n) { permitidos[n] = true; });
			$('.lbp-single-service').each(function () {
				var idx = parseInt($(this).attr('data-service-index'), 10);
				var fuera = !(idx in permitidos);
				$(this).toggleClass('pp-rs-oculta-tab', fuera);
				if (fuera) {
					// Desmarcar servicios de otros tipos (recalcula el precio nativo).
					$(this).find('.lbp-service-checkbox:checked').prop('checked', false).trigger('change');
				}
			});

			// Profesionales del tipo.
			var profes = profesionalesDe(tipoElegido.slug);
			var $zona = $overlay.find('.pp-rs__profes');
			var $cards = $zona.find('.pp-rs__profes-cards').empty();
			if (profes.length) {
				profes.forEach(function (p) {
					$cards.append(
						'<button type="button" class="pp-gate__card pp-rs__prof" data-id="' + p.id + '">' +
							'<span class="pp-gate__avatar">' + (p.img ? '<img src="' + esc(p.img) + '" alt="">' : '<span class="pp-gate__mas">👤</span>') + '</span>' +
							'<span class="pp-gate__nombre">' + esc(p.nombre) + '</span>' +
							'<span class="pp-gate__detalle">' + esc(p.sub || 'Profesional') + '</span>' +
						'</button>'
					);
				});
				$zona.show();
				// Un solo profesional → se asume, sin preguntar.
				if (profes.length === 1) {
					elegirProf(profes[0], $cards.find('.pp-rs__prof').first());
				}
			} else {
				$zona.hide();
				// Sin profesionales: cargar YA la agenda del tipo (recurso
				// auto-agenda) o la del listado (tarjeta 0) en segundo plano.
				dispararRecurso();
			}

			refrescarEstado();
		}

		function elegirProf(p, $card) {
			if (profElegido && profElegido.id === p.id) {
				return; // mismo profesional: no re-disparar
			}
			profElegido = p;
			$overlay.find('.pp-rs__prof').removeClass('activa');
			if ($card && $card.length) { $card.addClass('activa'); }
			// Cargar la agenda del profesional YA (en segundo plano): el
			// filtrado nativo por recurso desmarca servicios, así que debe
			// correr ANTES de que el cliente marque los suyos.
			dispararRecurso();
			refrescarEstado();
		}

		/**
		 * Selecciona el recurso nativo correspondiente a la elección actual.
		 * selectResource() de Booking Plus hace todo: ajustes del recurso,
		 * filtrado de servicios y goToStep(1) — el paso 1 queda activo
		 * DEBAJO de nuestro overlay mientras el cliente sigue eligiendo.
		 * Después reponemos el filtro por tipo (el nativo re-muestra todo).
		 */
		function dispararRecurso() {
			if (!hayPasoRecursos() || !tipoElegido) { return; }
			var destino = recursoDestino();
			var $card = destino > 0
				? $modal.find('.lbp-resource-card[data-resource-id="' + destino + '"]').first()
				: tarjetaCero();
			if (!$card.length) { return; } // degradación: paso nativo visible al cerrar
			$card.trigger('click');
			// El filtrado nativo corre tras un AJAX; reaplicar el filtro por
			// tipo cuando termine (dos reintentos cubren el caso lento).
			setTimeout(reaplicarFiltroTipo, 400);
			setTimeout(reaplicarFiltroTipo, 1500);
		}

		function refrescarEstado() {
			var listo = !!tipoElegido;
			var texto = '';
			if (tipoElegido) {
				var profes = profesionalesDe(tipoElegido.slug);
				if (profes.length && !profElegido) { listo = false; }
				texto = '<strong>' + esc(tipoElegido.nombre) + '</strong>' +
					(profElegido ? ' con <strong>' + esc(profElegido.nombre) + '</strong>' : '');
			}
			$overlay.find('.pp-rs__eleccion').html(texto);
			$overlay.find('.pp-rs__siguiente').prop('disabled', !listo);
		}

		/* ================= Transición al calendario ================= */

		// Recurso destino: profesional > agenda del tipo > agenda general > 0.
		// La agenda general existe cuando el listado tiene agendas separadas y
		// este tipo no tiene la suya: con recursos presentes, "recurso 0" para
		// Booking Plus significa "que CUALQUIER recurso esté libre", lo que
		// mostraría la disponibilidad de agendas ajenas. Solo se usa el 0
		// cuando el listado no tiene recursos (calendario del negocio).
		function recursoDestino() {
			if (profElegido) { return profElegido.id; }
			if (tipoElegido && tipoElegido.agenda) { return tipoElegido.agenda; }
			if (D.agendaGeneral) { return parseInt(D.agendaGeneral, 10) || 0; }
			return 0;
		}

		// Tarjeta nativa oculta con data-resource-id=0: dispara la rama
		// "listing defaults" de selectResource() (agenda del listado).
		function tarjetaCero() {
			var $paso0 = $modal.find('.lbp-step-0');
			if (!$paso0.length) { return $(); }
			var $c = $paso0.find('.lbp-resource-card[data-resource-id="0"]');
			if (!$c.length) {
				$c = $('<div class="lbp-resource-card" data-resource-id="0" style="display:none"><div class="lbp-resource-info"><h4></h4></div></div>');
				$paso0.append($c);
			}
			return $c;
		}

		function irAlCalendario() {
			if (!tipoElegido) { return; }

			// La agenda ya se disparó al elegir tipo/profesional. Si el paso 1
			// aún no está activo (AJAX de ajustes del recurso en curso), se
			// espera un momento; tope de 5 s y se continúa igual (degradación:
			// el paso nativo que esté activo queda visible).
			var $paso1 = $modal.find('.lbp-step[data-step="1"]');
			if (!hayPasoRecursos() || !$paso1.length || $paso1.hasClass('active')) {
				reaplicarFiltroTipo();
				ajustarResumen();
				ocultarOverlay();
				return;
			}

			var listoEn = null;
			var vigia = new MutationObserver(function () {
				if ($paso1.hasClass('active')) { terminar(); }
			});
			function terminar() {
				if (listoEn) { clearTimeout(listoEn); listoEn = null; }
				vigia.disconnect();
				// Si Booking Plus aún no avanzó (su AJAX de ajustes del recurso
				// va lento), forzarlo por su propia vía: el botón Siguiente del
				// paso 0 ya está habilitado al haber recurso elegido. Sin esto
				// el cliente aterrizaría en el paso 0 CRUDO (tarjetas de
				// agendas internas), que es justo lo que este paso evita.
				if (!$paso1.hasClass('active')) {
					var $next0 = $modal.find('.lbp-step-0 .lbp-btn-next:not(:disabled)').first();
					if ($next0.length) { $next0.trigger('click'); }
				}
				reaplicarFiltroTipo();
				ajustarResumen();
				ocultarOverlay();
			}
			vigia.observe($paso1[0], { attributes: true, attributeFilter: ['class'] });
			// 12 s: margen amplio para el AJAX de ajustes del recurso incluso en
			// servidores lentos (en local saturado tarda >10 s).
			listoEn = setTimeout(terminar, 12000);
			dispararRecurso(); // reintento por si el primer disparo no ocurrió
		}

		// Las agendas automáticas son un detalle interno: el resumen no debe
		// decir "Profesional: Agenda general". Solo se muestra esa línea
		// cuando el cliente eligió una persona de verdad.
		function ajustarResumen() {
			var $fila = $modal.find('.lbp-summary-resource');
			if (!$fila.length) { return; }
			if (profElegido) {
				$fila.show();
				$modal.find('#lbp-summary-resource-name').text(profElegido.nombre);
			} else {
				$fila.hide();
			}
		}

		// El filtrado nativo por recurso re-muestra TODOS los servicios con
		// .show() inline; nuestra clase por tipo (display:none !important)
		// debe seguir puesta después.
		function reaplicarFiltroTipo() {
			if (!tipoElegido) { return; }
			var permitidos = {};
			(tipoElegido.indices || []).forEach(function (n) { permitidos[n] = true; });
			$('.lbp-single-service').each(function () {
				var idx = parseInt($(this).attr('data-service-index'), 10);
				$(this).toggleClass('pp-rs-oculta-tab', !(idx in permitidos));
			});
		}

		/* ================= Mostrar / ocultar ================= */

		function mostrarOverlay() {
			if (!montar()) { return; }
			$overlay.show();
			resuelto = false;
		}
		function ocultarOverlay() {
			if ($overlay) { $overlay.hide(); }
			resuelto = true;
			actualizarSidebarNativo();
		}

		/* ================= Sidebar nativo (pasos 1 y 2) ================= */

		// Con el flujo activo, el sidebar nativo debe contar la historia
		// completa: Mascota ✓ / Servicios ✓ / Fecha y hora / Confirmar.
		function actualizarSidebarNativo() {
			var $pasos = $modal.find('.lbp-steps-nav').first();
			if (!$pasos.length) {
				$pasos = $modal.find('.lbp-step-indicator').first().parent();
			}
			if (!$pasos.length || $pasos.find('.pp-rs-ind').length) { return; }

			// El paso 0 nativo (recurso) queda representado por "Servicios".
			var $ind0 = $pasos.find('.lbp-step-indicator[data-step="0"]');
			if ($ind0.length) {
				$ind0.find('.lbp-step-label').text('Servicios');
			} else {
				$pasos.prepend(
					'<div class="lbp-step-indicator completed pp-rs-ind pp-rs-ind--servicios">' +
						'<span class="lbp-step-num">🧴</span>' +
						'<span class="lbp-step-label">Servicios</span>' +
					'</div>'
				);
			}
			$pasos.prepend(
				'<div class="lbp-step-indicator completed pp-rs-ind">' +
					'<span class="lbp-step-num">🐾</span>' +
					'<span class="lbp-step-label">Mascota</span>' +
				'</div>'
			);
		}

		/* ================= Eventos ================= */

		$(document).on('click', '.pp-rs__tab', function () {
			elegirTipo(parseInt($(this).attr('data-i'), 10));
		});
		$(document).on('click', '.pp-rs__prof', function () {
			if (!tipoElegido) { return; }
			var id = parseInt($(this).attr('data-id'), 10);
			var lista = profesionalesDe(tipoElegido.slug).filter(function (p) { return p.id === id; });
			if (lista.length) { elegirProf(lista[0], $(this)); }
		});
		$(document).on('click', '.pp-rs__siguiente', function () {
			irAlCalendario();
		});

		// Atrás (paso Servicios) → reabrir el gate de Mascota.
		$(document).on('click', '.pp-rs__atras', function (ev) {
			ev.stopPropagation(); // que el handler nativo de .lbp-btn-back no navegue
			if ($overlay) { $overlay.hide(); }
			var $gateMascota = $('.pp-gate').not('.pp-rs').first();
			if ($gateMascota.length) { $gateMascota.show(); } else { mostrarOverlay(); }
		});

		// X del overlay → cerrar el popup completo (mismo gesto del gate).
		$(document).on('click', '.pp-rs__x', function () {
			var $x = $modal.find('.lbp-modal-close, .mfp-close').first();
			if ($x.length) { $x.trigger('click'); }
		});

		// Entrada a NUESTRO paso: cuando el gate de Mascota se resuelve.
		// Es por ESTADO, no por evento: en vez de escuchar su botón Siguiente
		// (frágil: depende de la propagación del click y del orden de scripts),
		// se vigila que el gate quede oculto con el modal abierto. Así entra
		// igual si el cliente pulsa Siguiente, "Continuar sin mascota", o si el
		// gate se cierra por cualquier otro camino.
		function vigilarGateMascota() {
			var $gate = $('.pp-gate').not('.pp-rs').first();
			if (!$gate.length || $gate.data('ppRsVigilado')) { return; }
			$gate.data('ppRsVigilado', true);
			var obs = new MutationObserver(function () {
				if (!modalAbierto || resuelto) { return; }
				// Gate oculto + modal abierto + paso sin resolver = nos toca.
				if (!$gate.is(':visible') && $modal.is(':visible') && !$overlayVisible()) {
					mostrarOverlay();
				}
			});
			obs.observe($gate[0], { attributes: true, attributeFilter: ['style', 'class'] });
		}
		function $overlayVisible() {
			return $overlay && $overlay.is(':visible');
		}

		// Respaldo por evento (si el gate se ocultara sin cambiar atributos).
		$(document).on('click', '.pp-gate__continuar, .pp-gate__continuar-invitado', function () {
			setTimeout(mostrarOverlay, 30);
		});

		// Cambio de mascota a mitad de camino → al confirmarla, este paso se
		// reabre (los servicios pueden cambiar con la mascota) y la elección
		// de servicios del tipo se conserva si sigue visible.
		$(document).on('pp:mascota-elegida', function () {
			if (resuelto) { resuelto = false; }
		});

		// "Atrás" del paso Fecha y hora: con el flujo activo debe volver a
		// SERVICIOS. Cubre los dos orígenes del botón: el inyectado por el
		// gate de mascota (.pp-gate-volver, modo listing) y el NATIVO de
		// Booking Plus con data-step="0" (modo recursos: iría al paso 0
		// crudo de tarjetas). El Atrás de Confirmar (data-step="1") no se
		// toca. Captura nativa → corre antes que los handlers jQuery.
		document.addEventListener('click', function (ev) {
			if (!ev.target || !ev.target.closest) { return; }
			var btn = ev.target.closest('.pp-gate-volver, #lbp-booking-modal .lbp-btn-back[data-step="0"]');
			if (!btn) { return; }
			if (btn.classList.contains('pp-rs__atras')) { return; } // el nuestro tiene su propio handler
			ev.stopPropagation();
			ev.preventDefault();
			mostrarOverlay();
		}, true);

		/* ================= Sidebar del gate de Mascota ================= */

		// Insertar "Servicios" en la réplica de pasos del gate de Mascota.
		function parcharSidebarGate() {
			$('.pp-gate').not('.pp-rs').find('.pp-gate__sb-pasos').each(function () {
				var $p = $(this);
				if ($p.find('.pp-rs-paso').length) { return; }
				var $mascota = $p.find('.pp-gate__sb-paso').first();
				$('<span class="pp-gate__sb-paso pp-rs-paso"><i>🧴</i> Servicios</span>').insertAfter($mascota);
			});
		}

		/* ================= Vigilancia de apertura del modal ================= */

		// Mismo mecanismo del gate de Mascota: observar el body (Magnific
		// añade/quita .mfp-wrap) + respaldo en los disparadores conocidos.
		var modalAbierto = false;
		function revisar() {
			var visible = $modal.is(':visible');
			if (visible && !modalAbierto) {
				modalAbierto = true;
				parcharSidebarGate();
				montar();
				vigilarGateMascota();
				// Sin gate de Mascota (módulo apagado o sin montar) este paso
				// es la primera pantalla del flujo.
				if (!resuelto && !$('.pp-gate').not('.pp-rs').length) {
					mostrarOverlay();
				}
			} else if (!visible && modalAbierto) {
				modalAbierto = false;
				resuelto = false;
				tipoElegido = null;
				profElegido = null;
				if ($overlay) {
					$overlay.find('.pp-rs__tab').removeClass('activo');
					$overlay.find('.pp-rs__prof').removeClass('activa');
					$overlay.find('.pp-rs__eleccion').empty();
					$overlay.find('.pp-rs__siguiente').prop('disabled', true);
					$overlay.hide();
				}
			}
		}
		new MutationObserver(revisar).observe(document.body, { childList: true });
		$(document).on('click', '.lbp-book-now-btn, a[href="#lbp-booking-modal"], .lbp-bpw-week__day, [data-lbp-open]', function () {
			setTimeout(revisar, 120);
			setTimeout(revisar, 600);
		});
	});
})(jQuery);
