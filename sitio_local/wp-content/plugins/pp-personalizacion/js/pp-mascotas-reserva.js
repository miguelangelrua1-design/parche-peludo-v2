/**
 * PP Mascotas — paso "Mascota" al inicio del popup de reserva
 * (Listeo Booking Plus), inyectado por JS sin tocar el plugin de reservas.
 *
 * Cómo funciona:
 * - Al abrir el popup, una pantalla propia ("gate") cubre el modal y pide
 *   elegir la mascota (tarjetas) o crear una nueva, ANTES de ver el paso
 *   de Fecha y hora. Las vistas originales de Booking Plus no se alteran:
 *   al continuar, se destapa el flujo normal.
 * - El valor elegido vive en un input oculto dentro de un contenedor
 *   .lbp-custom-booking-fields: lbp-booking.js barre esos campos y los
 *   envía en el POST de lbp_submit_booking, así llega solo al servidor.
 * - En el paso Confirmar se muestra un resumen "Reserva para: X" con botón
 *   Cambiar (reabre el gate).
 * - PP_MASC_RESERVA.precioMascota indica si este listado usará los datos
 *   de la mascota para calcular el precio (futuro; aún sin efecto).
 */
(function ($) {
	'use strict';

	$(function () {
		if (typeof PP_MASC_RESERVA === 'undefined') { return; }

		var D = PP_MASC_RESERVA;
		var $modal = $('#lbp-booking-modal');
		if (!$modal.length) { return; }

		var elegida = null;          // mascota seleccionada {id,nombre,especie,raza,foto}
		var gateResuelto = false;    // ya se continuó en esta apertura del modal

		function esc(s) { return $('<span>').text(s || '').html(); }

		function detalle(m) {
			return (m.especie === 'gato' ? 'Gato' : 'Perro') + (m.raza ? ' · ' + esc(m.raza) : '');
		}

		function avatar(m) {
			if (m.foto) { return '<img src="' + esc(m.foto) + '" alt="">'; }
			return '<span class="pp-gate__huella">🐾</span>';
		}

		/* =====================================================================
		   GATE (pantalla inicial de mascota)
		   ================================================================== */

		function cardMascota(m) {
			return '<button type="button" class="pp-gate__card" data-id="' + m.id + '">' +
				'<span class="pp-gate__avatar">' + avatar(m) + '</span>' +
				'<span class="pp-gate__nombre">' + esc(m.nombre) + '</span>' +
				'<span class="pp-gate__detalle">' + detalle(m) + '</span>' +
			'</button>';
		}

		function opcionesRazas(especie) {
			var html = '<option value="" disabled selected>Selecciona la raza…</option>';
			(D.razas[especie] || []).forEach(function (r) {
				html += '<option value="' + esc(r) + '">' + esc(r) + '</option>';
			});
			return html;
		}

		// Barra lateral que replica la del popup de Booking Plus (mismo color
		// vía var(--lbp-popup-sidebar)); los pasos Fecha y hora / Confirmar se
		// muestran solo de forma visual.
		var sidebarHtml =
			'<aside class="pp-gate__sidebar">' +
				'<span class="pp-gate__sb-eyebrow">Reserva</span>' +
				'<h4 class="pp-gate__sb-titulo">' + esc(D.listado || '') + '</h4>' +
				'<div class="pp-gate__sb-pasos">' +
					'<span class="pp-gate__sb-paso activo"><i>🐾</i> Mascota</span>' +
					'<span class="pp-gate__sb-paso"><i>1</i> Fecha y hora</span>' +
					'<span class="pp-gate__sb-paso"><i>2</i> Confirmar</span>' +
				'</div>' +
			'</aside>';

		var cerrarHtml = '<button type="button" class="pp-gate__x" aria-label="Cerrar">&times;</button>';

		var gateHtml;
		if (!D.logueado) {
			gateHtml =
				'<div class="pp-gate" style="display:none">' +
					cerrarHtml +
					sidebarHtml +
					'<div class="pp-gate__contenido">' +
						'<div class="pp-gate__panel pp-gate__panel--login">' +
							'<div class="pp-gate__icono">🐾</div>' +
							'<h3 class="pp-gate__titulo">¿Para cuál mascota es la reserva?</h3>' +
							'<p class="pp-gate__sub">Inicia sesión para elegir o registrar a tu peludo y continuar con la reserva.</p>' +
							'<a href="#sign-in-dialog" class="popup-with-zoom-anim pp-gate__btn pp-gate__btn--primario">Iniciar sesión</a>' +
							'<button type="button" class="pp-gate__continuar-invitado">Continuar sin mascota</button>' +
						'</div>' +
					'</div>' +
				'</div>';
		} else {
			var cards = '';
			D.mascotas.forEach(function (m) { cards += cardMascota(m); });

			gateHtml =
				'<div class="pp-gate" style="display:none">' +
					cerrarHtml +
					sidebarHtml +
					'<div class="pp-gate__contenido">' +
					'<div class="pp-gate__panel">' +
						'<h3 class="pp-gate__titulo">🐾 ¿Para cuál de tus peludos es la reserva?</h3>' +
						'<p class="pp-gate__sub">Elige tu mascota o crea una nueva sin salir de la reserva.</p>' +

						'<div class="pp-gate__cards">' + cards +
							'<button type="button" class="pp-gate__card pp-gate__card--nueva">' +
								'<span class="pp-gate__avatar"><span class="pp-gate__mas">+</span></span>' +
								'<span class="pp-gate__nombre">Nueva mascota</span>' +
								'<span class="pp-gate__detalle">Créala aquí mismo</span>' +
							'</button>' +
						'</div>' +

						'<div class="pp-gate__form" style="display:none">' +
							'<h4 class="pp-gate__form-titulo">Nueva mascota</h4>' +
							'<div class="pp-gate__grid">' +
								'<label class="pp-gate__campo pp-gate__campo--full"><span>Nombre <b>*</b></span>' +
									'<input type="text" id="pp-mr-nombre" placeholder="Ej: Max" maxlength="60">' +
								'</label>' +
								'<label class="pp-gate__campo"><span>Especie <b>*</b></span>' +
									'<span class="pp-gate__pills" id="pp-mr-especie">' +
										'<button type="button" data-v="perro">🐶 Perro</button>' +
										'<button type="button" data-v="gato">🐱 Gato</button>' +
									'</span>' +
								'</label>' +
								'<label class="pp-gate__campo"><span>Sexo <b>*</b></span>' +
									'<span class="pp-gate__pills" id="pp-mr-sexo">' +
										'<button type="button" data-v="macho">♂ Macho</button>' +
										'<button type="button" data-v="hembra">♀ Hembra</button>' +
									'</span>' +
								'</label>' +
								'<label class="pp-gate__campo pp-gate__campo--full"><span>Raza <b>*</b></span>' +
									'<select id="pp-mr-raza" disabled><option value="" disabled selected>Elige primero la especie…</option></select>' +
								'</label>' +
								'<label class="pp-gate__campo"><span>Fecha de nacimiento <b>*</b></span>' +
									'<input type="date" id="pp-mr-fecha" max="' + D.hoy + '">' +
								'</label>' +
								'<label class="pp-gate__campo"><span>Peso (en kilos) <b>*</b></span>' +
									'<input type="number" id="pp-mr-peso" placeholder="0.0" min="0.1" max="150" step="0.1">' +
								'</label>' +
								'<label class="pp-gate__campo pp-gate__campo--full"><span>Tipo de pelaje <b>*</b></span>' +
									'<span class="pp-gate__pills" id="pp-mr-pelaje">' +
										'<button type="button" data-v="corto">Corto</button>' +
										'<button type="button" data-v="largo">Largo</button>' +
									'</span>' +
								'</label>' +
							'</div>' +
							'<p class="pp-gate__nota">La foto de perfil la puedes añadir luego desde <em>Mis Mascotas</em>.</p>' +
							'<span class="pp-gate__error"></span>' +
							'<div class="pp-gate__form-acciones">' +
								'<button type="button" class="pp-gate__btn pp-gate__cancelar">Cancelar</button>' +
								'<button type="button" class="pp-gate__btn pp-gate__btn--primario pp-gate__guardar">Guardar mascota</button>' +
							'</div>' +
						'</div>' +

						'<div class="pp-gate__pie">' +
							'<button type="button" class="pp-gate__btn pp-gate__btn--primario pp-gate__continuar" disabled>Siguiente</button>' +
							'<span class="pp-gate__eleccion"></span>' +
						'</div>' +
					'</div>' +

					'<div class="lbp-custom-booking-fields pp-gate__datos">' +
						'<input type="hidden" name="pp_mascota_check" value="1">' +
						'<input type="hidden" name="pp_mascota_reserva" id="pp_mascota_reserva" value="">' +
					'</div>' +
					'</div>' +
				'</div>';
		}

		$modal.append(gateHtml);
		var $gate = $modal.find('.pp-gate');

		// X de cierre: usa el propio cierre del popup de Booking Plus.
		$gate.on('click', '.pp-gate__x', function () {
			var $cierre = $modal.find('.lbp-modal-close').first();
			if ($cierre.length) {
				$cierre.trigger('click');
			} else if ($.magnificPopup) {
				$.magnificPopup.close();
			}
		});

		// Botón "Atrás" en el paso Fecha y hora (Booking Plus en modo "listing"
		// no pinta el "Back" nativo, que iría al paso de recursos). Vuelve al
		// gate de Mascota. La fila .lbp-step-actions ya es flex space-between →
		// Atrás queda a la izquierda y "Siguiente" a la derecha, misma fila.
		function ppInyectarAtras() {
			var $next = $modal.find('.lbp-step-1 .lbp-step-actions .lbp-btn-next[data-step="2"]').first();
			if (!$next.length) { $next = $modal.find('.lbp-btn-next[data-step="2"]').first(); }
			if (!$next.length) { return; }
			var $acciones = $next.closest('.lbp-step-actions');
			if (!$acciones.length) { return; }
			if ($acciones.find('.pp-gate-volver').length) { return; } // ya inyectado
			if ($acciones.find('.lbp-btn-back').length) { return; }   // hay "Back" nativo → no duplicar
			// OJO data-step="1": Booking Plus tiene un manejador delegado sobre
			// .lbp-btn-back que navega a parseInt(data('step')) — sin el atributo
			// da NaN → goToStep(NaN) OCULTA TODOS los pasos y la modal queda
			// vacía al volver del gate. Con "1" su manejador re-muestra el paso
			// Fecha y hora (queda debajo del gate) y ambos comportamientos
			// se componen sin conflicto.
			$acciones.prepend(
				'<button type="button" class="button lbp-btn-back pp-gate-volver" data-step="1">' +
					'<i class="fa fa-arrow-left"></i> Atrás' +
				'</button>'
			);
			// Marca para forzar la fila también en móvil (Booking Plus apila los
			// botones con column-reverse en pantallas pequeñas).
			$acciones.addClass('pp-acciones-fila');
		}
		ppInyectarAtras();

		// Clic en "Atrás" → reabrir el gate de Mascota. Si el cliente cambia de
		// mascota, el filtrado de servicios se recalcula (evento
		// pp:mascota-elegida, ver pp-servicios-reserva.js); si no la cambia, la
		// selección de servicios se mantiene tal cual.
		$(document).on('click', '.pp-gate-volver', function (e) {
			e.preventDefault();
			mostrarGate();
		});

		/* ---------- Resumen en el paso Confirmar ---------- */
		var $msgRow = $('#lbp-message').closest('.lbp-form-row');
		if ($msgRow.length && D.logueado) {
			$msgRow.before(
				'<div class="lbp-form-row lbp-form-row-full pp-chip-fila" style="display:none">' +
					'<div class="pp-chip">🐾 Reserva para: <strong class="pp-chip__nombre"></strong> <span class="pp-chip__det"></span>' +
					'<button type="button" class="pp-chip__cambiar">Cambiar</button></div>' +
				'</div>'
			);
		}

		function actualizarChip() {
			var $fila = $('.pp-chip-fila');
			if (!$fila.length) { return; }
			if (elegida) {
				$fila.find('.pp-chip__nombre').text(elegida.nombre);
				$fila.find('.pp-chip__det').text('(' + (elegida.especie === 'gato' ? 'Gato' : 'Perro') + (elegida.raza ? ' · ' + elegida.raza : '') + ')');
				$fila.show();
			} else {
				$fila.hide();
			}
		}

		$(document).on('click', '.pp-chip__cambiar', function () {
			mostrarGate();
		});

		/* ---------- Mostrar / ocultar el gate ---------- */
		function mostrarGate() {
			$gate.show();
			gateResuelto = false;
		}
		function ocultarGate() {
			$gate.hide();
			gateResuelto = true;
		}

		// Vigilar la apertura del modal. Magnific Popup añade/quita su
		// contenedor (.mfp-wrap) en <body>, así que un MutationObserver sobre
		// el body detecta abrir y cerrar. (No usamos setInterval: otro script
		// de la página limpia los timers y lo mataba.)
		var modalAbierto = false;
		function revisarModal() {
			var visible = $modal.is(':visible');
			if (visible && !modalAbierto) {
				modalAbierto = true;
				ppInyectarAtras(); // los pasos del modal ya están renderizados
					if (!gateResuelto) { mostrarGate(); }
			} else if (!visible && modalAbierto) {
				modalAbierto = false;
				gateResuelto = false; // en la próxima apertura, mostrar de nuevo
			}
		}
		new MutationObserver(revisarModal).observe(document.body, { childList: true });
		// Respaldo: los disparadores conocidos del popup.
		$(document).on('click', '.lbp-book-now-btn, a[href="#lbp-booking-modal"], .lbp-bpw-week__day, [data-lbp-open]', function () {
			setTimeout(revisarModal, 120);
			setTimeout(revisarModal, 600);
		});

		if (!D.logueado) {
			$gate.on('click', '.pp-gate__continuar-invitado', ocultarGate);
			return;
		}

		/* ---------- Selección de mascota ---------- */
		function elegir(m, $card) {
			elegida = m;
			$('#pp_mascota_reserva').val(String(m.id));
			$gate.find('.pp-gate__card').removeClass('activa');
			if ($card && $card.length) { $card.addClass('activa'); }
			$gate.find('.pp-gate__continuar').prop('disabled', false);
			$gate.find('.pp-gate__eleccion').html('Elegiste a <strong>' + esc(m.nombre) + '</strong>');
			actualizarChip();
			// Avisar al resto de módulos (p. ej. el filtrado de servicios por
			// mascota de pp-servicios-reserva.js) qué mascota quedó elegida.
			$(document).trigger('pp:mascota-elegida', [m]);
		}

		$gate.on('click', '.pp-gate__card:not(.pp-gate__card--nueva)', function () {
			var id = String($(this).data('id'));
			var m = D.mascotas.filter(function (x) { return String(x.id) === id; })[0];
			if (m) {
				elegir(m, $(this));
				$gate.find('.pp-gate__form').slideUp(120);
			}
		});

		$gate.on('click', '.pp-gate__continuar', ocultarGate);

		/* ---------- Formulario de creación ---------- */
		var nueva = { especie: '', sexo: '', pelaje: '' };

		function abrirForm() {
			$gate.find('.pp-gate__form').slideDown(150);
		}
		$gate.on('click', '.pp-gate__card--nueva', abrirForm);
		$gate.on('click', '.pp-gate__cancelar', function () {
			$gate.find('.pp-gate__form').slideUp(150);
			$gate.find('.pp-gate__error').text('');
		});

		// Sin mascotas: abrir el formulario de una vez.
		if (!D.mascotas.length) {
			$gate.find('.pp-gate__sub').text('Aún no tienes mascotas registradas: crea la primera aquí mismo y sigue con tu reserva.');
			abrirForm();
		}

		// Pills de especie, sexo y pelaje.
		$gate.on('click', '.pp-gate__pills button', function () {
			var $b = $(this), grupo = $b.parent().attr('id');
			$b.addClass('activa').siblings().removeClass('activa');
			if ('pp-mr-especie' === grupo) {
				nueva.especie = $b.data('v');
				$('#pp-mr-raza').prop('disabled', false).html(opcionesRazas(nueva.especie));
			} else if ('pp-mr-sexo' === grupo) {
				nueva.sexo = $b.data('v');
			} else if ('pp-mr-pelaje' === grupo) {
				nueva.pelaje = $b.data('v');
			}
		});

		$gate.on('click', '.pp-gate__guardar', function () {
			var $btn = $(this);
			var $error = $gate.find('.pp-gate__error');
			var datos = {
				nombre:           $.trim($('#pp-mr-nombre').val() || ''),
				especie:          nueva.especie,
				sexo:             nueva.sexo,
				pelaje:           nueva.pelaje,
				fecha_nacimiento: $('#pp-mr-fecha').val() || '',
				raza:             $('#pp-mr-raza').val() || '',
				peso:             $('#pp-mr-peso').val() || ''
			};

			var falta = [];
			if (!datos.nombre) { falta.push('el nombre'); }
			if (!datos.especie) { falta.push('la especie'); }
			if (!datos.sexo) { falta.push('el sexo'); }
			if (!datos.raza) { falta.push('la raza'); }
			if (!datos.fecha_nacimiento) { falta.push('la fecha de nacimiento'); }
			if (!datos.peso || parseFloat(datos.peso) <= 0) { falta.push('el peso'); }
			if (!datos.pelaje) { falta.push('el tipo de pelaje'); }
			if (falta.length) { $error.text('Completa ' + falta.join(', ') + '.'); return; }
			if (datos.fecha_nacimiento > D.hoy) { $error.text('La fecha de nacimiento no puede ser futura.'); return; }

			$error.text('');
			$btn.prop('disabled', true).text('Guardando…');

			$.post(D.ajax_url, $.extend({ action: 'pp_mascota_crear_rapida', nonce: D.nonce }, datos))
				.done(function (r) {
					if (r && r.success) {
						var m = r.data;
						m.foto = '';
						D.mascotas.push(m);
						var $card = $(cardMascota(m));
						$gate.find('.pp-gate__card--nueva').before($card);
						elegir(m, $card);
						$gate.find('.pp-gate__form').slideUp(150);
						$('#pp-mr-nombre, #pp-mr-fecha, #pp-mr-peso').val('');
					} else {
						$error.text((r && r.data && r.data.message) ? r.data.message : 'No pudimos guardar la mascota.');
					}
				})
				.fail(function () { $error.text('Error de conexión. Inténtalo de nuevo.'); })
				.always(function () { $btn.prop('disabled', false).text('Guardar mascota'); });
		});
	});
})(jQuery);
