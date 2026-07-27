/* migrado de functions.php::ppv2_listing_mobile_bottom_bar() 2026-07-10
   Solo se imprime si is_singular('listing'). El HTML del bottom sheet (#ppv2-reservar-sheet) se queda en PHP. */
document.addEventListener('DOMContentLoaded', function () {
	// Barra nativa de Listeo: cambiar "Comenzando desde $40.000" -> "desde $40.000".
	// Solo se elimina la palabra "Comenzando"; el precio (dinámico) se conserva.
	// Listeo re-renderiza la sticky footer tarde (en window.load), pisando el
	// cambio; por eso observamos la barra con MutationObserver y re-aplicamos.
	(function () {
		function fixPrice() {
			var el = document.querySelector('.booking-sticky-footer .bsf-left h4');
			if (el && /Comenzando/i.test(el.innerHTML)) {
				el.innerHTML = el.innerHTML.replace(/Comenzando\s+/i, '');
			}
		}
		// Acortar "Reservar ahora" -> "Reservar" (sin tocar posibles iconos hijos).
		function fixReserveText() {
			var a = document.querySelector('.booking-sticky-footer .bsf-right a, .booking-sticky-footer .bsf-right .button');
			if (!a) return;
			for (var i = 0; i < a.childNodes.length; i++) {
				var n = a.childNodes[i];
				if (n.nodeType === 3 && /Reservar\s+ahora/i.test(n.nodeValue)) {
					n.nodeValue = n.nodeValue.replace(/Reservar\s+ahora/i, 'Reservar');
				}
			}
		}
		// ¿Hay sesión iniciada? WordPress marca el body con .logged-in; como
		// segunda señal (por si el HTML llega de la caché de página) se mira
		// si el header muestra el enlace "Iniciar Sesión", que solo existe
		// para visitantes anónimos.
		function haySesion() {
			if (document.body.classList.contains('logged-in')) { return true; }
			return !document.querySelector('a[href="#sign-in-dialog"]');
		}

		// Abre el panel de Iniciar Sesión del tema. Se prefiere pulsar el
		// enlace que ya existe en el header (arrastra el handler del tema y su
		// animación); si no estuviera, se abre Magnific Popup a mano.
		function abrirLogin() {
			var enlace = document.querySelector('a[href="#sign-in-dialog"]');
			if (enlace) { enlace.click(); return true; }
			if (window.jQuery && jQuery.magnificPopup && document.getElementById('sign-in-dialog')) {
				jQuery.magnificPopup.open({
					items: { src: '#sign-in-dialog' },
					type: 'inline',
					mainClass: 'my-mfp-zoom-in',
					removalDelay: 300
				});
				return true;
			}
			return false;
		}

		// Inyectar el botón de Mensaje a la IZQUIERDA de "Reservar". Usa el
		// MISMO icono que el título de la sección Enviar Mensaje (fa-envelope-o,
		// sobre cerrado).
		function injectMessageBtn() {
			var bsfRight = document.querySelector('.booking-sticky-footer .bsf-right');
			if (!bsfRight || bsfRight.querySelector('.ppv2-bsf-message-btn')) return;
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'ppv2-bsf-message-btn';
			btn.setAttribute('aria-label', 'Enviar mensaje');
			btn.innerHTML = '<i class="fa fa-envelope-o" aria-hidden="true"></i>';
			btn.addEventListener('click', function (e) {
				e.preventDefault();
				e.stopImmediatePropagation();
				// Sin sesión el panel de mensaje no sirve (el envío queda sin
				// remitente y el usuario no puede seguir la conversación desde
				// su cuenta): se le ofrece iniciar sesión, que es lo que espera
				// encontrar. Si por lo que sea no se pudiera abrir el login,
				// se cae al panel de mensaje para no dejar el botón muerto.
				if (!haySesion() && abrirLogin()) { return; }
				openSheet('message'); // openSheet está hoisteada en este scope
			});
			bsfRight.insertBefore(btn, bsfRight.firstChild); // izquierda del botón Reservar
		}
		function applyAll() { fixPrice(); fixReserveText(); injectMessageBtn(); }
		function watch(tries) {
			var bar = document.querySelector('.booking-sticky-footer');
			if (bar) {
				applyAll();
				// Re-aplicar si Listeo re-renderiza el contenido de la barra
				// (vuelve a quitar "Comenzando" y re-inyecta el botón de mensaje).
				var obs = new MutationObserver(applyAll);
				obs.observe(bar, { childList: true, subtree: true, characterData: true });
			} else if (tries > 0) {
				setTimeout(function () { watch(tries - 1); }, 100);
			}
		}
		watch(30);
		// Refuerzo: re-aplicar tras window.load (cuando Listeo suele inicializar).
		window.addEventListener('load', function () { setTimeout(applyAll, 100); });
	})();

	var sheet = document.getElementById('ppv2-reservar-sheet');
	var sheetContent = document.getElementById('ppv2-reservar-sheet-content');
	if (!sheet || !sheetContent) return;

	// Estado del sheet: qué widget está dentro, su tipo y posición original.
	// El MISMO panel sirve para "Reservar" y "Enviar Mensaje": al abrir, se
	// mueve el widget nativo correspondiente desde la sidebar; al cerrar, se
	// devuelve a su lugar (restaurando su estado colapsado si lo tenía).
	var currentWidget = null;
	var currentType = null;
	var widgetWasCollapsed = false;
	var originalParent = null;
	var originalNextSibling = null;
	var currentPlaceholder = null;
	var ppv2SheetScrollY = 0; // scroll del body al abrir (se restaura al cerrar)

	function getWidget(type) {
		if (type === 'message') {
			return document.querySelector('.listeo-single-listing-sidebar .listing-widget.message-vendor')
				|| document.querySelector('.listeo-single-listing-sidebar #widget_contact_widget_listeo-3');
		}
		if (type === 'hours') {
			return document.querySelector('.listeo-single-listing-sidebar .listing-widget.opening-hours');
		}
		return document.querySelector('.listeo-single-listing-sidebar .listing-widget.booking-widget')
			|| document.querySelector('.listeo-single-listing-sidebar #widget_booking_listings-3')
			|| document.querySelector('.listeo-single-listing-sidebar .listing-widget.boxed-widget.booking-widget');
	}

	function openSheet(type) {
		type = type || 'booking';
		var widget = getWidget(type);
		if (!widget) {
			console.warn('[ppv2-sheet] widget no encontrado para:', type);
			return;
		}
		// Título del panel según el tipo
		var titleEl = document.getElementById('ppv2-reservar-sheet-title');
		if (titleEl) titleEl.textContent = (type === 'message') ? 'Enviar Mensaje' : (type === 'hours') ? 'Horarios' : 'Reservar';
		// Recordar si el widget estaba colapsado (el de mensaje es colapsable);
		// dentro del sheet lo queremos expandido para ver el formulario.
		widgetWasCollapsed = widget.classList.contains('is-collapsed');
		if (widgetWasCollapsed) widget.classList.remove('is-collapsed');
		// Guardar posición original para restaurar al cerrar
		currentWidget = widget;
		currentType = type;
		originalParent = widget.parentNode;
		originalNextSibling = widget.nextSibling;
		// Placeholder del MISMO alto en el hueco del widget → la fila de botones no
		// se reacomoda al sacarlo (evita el "salto" del otro botón al abrir/cerrar).
		currentPlaceholder = document.createElement('div');
		currentPlaceholder.className = 'ppv2-sheet-placeholder';
		currentPlaceholder.style.height = widget.getBoundingClientRect().height + 'px';
		originalParent.insertBefore(currentPlaceholder, widget);
		// Mover el widget dentro del sheet content
		sheetContent.appendChild(widget);
		// Mostrar el sheet (display:flex) y, en el siguiente frame, añadir
		// .is-open para que el transform animado interpole correctamente.
		sheet.removeAttribute('hidden');
		void sheet.offsetWidth; // reflow
		sheet.classList.add('is-open');
		// Bloqueo de scroll SIN perder la posición: overflow:hidden a secas
		// pierde el scroll en móvil (salto al cerrar). Patrón estándar:
		// body position:fixed (lo pone la clase) desplazado a -scrollY, y al
		// cerrar se restaura el scroll exacto.
		ppv2SheetScrollY = window.scrollY || document.documentElement.scrollTop || 0;
		document.body.style.top = '-' + ppv2SheetScrollY + 'px';
		document.body.classList.add('ppv2-sheet-open');
		// Foco accesible al botón cerrar
		var closeBtn = sheet.querySelector('.ppv2-bottom-sheet__close');
		if (closeBtn) setTimeout(function(){ closeBtn.focus(); }, 320);
	}

	// Exponer la apertura del panel inferior para otros módulos (botón "Horarios").
	window.ppv2OpenSheet = openSheet;

	function closeSheet() {
		sheet.classList.remove('is-open');
		document.body.classList.remove('ppv2-sheet-open');
		// Restaurar el scroll exacto donde estaba al abrir (sin salto).
		document.body.style.top = '';
		window.scrollTo(0, ppv2SheetScrollY || 0);
		// Capturar las referencias LOCALMENTE y limpiar el estado global ya,
		// para evitar una carrera si se abre otro sheet antes de que termine
		// la animación de salida (las variables compartidas no se corromperían).
		var w = currentWidget, op = originalParent, ons = originalNextSibling, wasColl = widgetWasCollapsed, ph = currentPlaceholder;
		currentWidget = null;
		currentType = null;
		originalParent = null;
		originalNextSibling = null;
		widgetWasCollapsed = false;
		currentPlaceholder = null;
		// Esperar a que termine la animación de salida antes de devolver el
		// widget a la sidebar y ocultar el sheet.
		setTimeout(function () {
			if (w && op) {
				// Devolver el widget al hueco exacto del placeholder (sin reflujo).
				if (ph && ph.parentNode) {
					ph.parentNode.insertBefore(w, ph);
					ph.parentNode.removeChild(ph);
				} else if (ons && ons.parentNode === op) {
					op.insertBefore(w, ons);
				} else {
					op.appendChild(w);
				}
				// Restaurar su estado colapsado original (p.ej. el de mensaje
				// vuelve a su pill colapsada en la sidebar).
				if (wasColl) w.classList.add('is-collapsed');
			} else if (ph && ph.parentNode) {
				ph.parentNode.removeChild(ph);
			}
			// Solo ocultar el sheet si no se reabrió mientras tanto.
			if (!sheet.classList.contains('is-open')) {
				sheet.setAttribute('hidden', '');
			}
		}, 340); // un poco más que la duración de la transición (320ms)
	}

	// Interceptar el botón "Reservar ahora" de la BARRA NATIVA de Listeo
	// (.booking-sticky-footer). Por defecto ese enlace hace scroll suave a
	// #booking-widget-anchor; lo reemplazamos por abrir el panel deslizante.
	// Delegación en FASE DE CAPTURA para correr ANTES del handler de scroll
	// de Listeo y poder cancelarlo (stopImmediatePropagation). Funciona aunque
	// la barra se pinte/recargue después del DOMContentLoaded.
	// Booking Plus: la reserva ya NO es el viejo widget inline de Listeo Core
	// (que se movía a un bottom-sheet). Ahora es un botón que abre el modal
	// propio de Booking Plus (#lbp-booking-modal) o, si el usuario NO está
	// logueado, el diálogo de acceso (#sign-in-dialog) / login. El botón
	// "Reservar" de la barra inferior debe reproducir EXACTAMENTE esa acción,
	// así que disparamos por código el botón REAL de Booking Plus de la barra
	// lateral. Así heredamos sin duplicar nada el comportamiento correcto en
	// ambos estados (logueado abre el modal de reserva; no logueado pide login).
	// El binding de Booking Plus es delegado en document, así que un .click()
	// programático lo activa igual que un clic real del usuario.
	function triggerNativeBooking() {
		var realBtn = document.querySelector('.lbp-book-now-btn, .book-now-notloggedin');
		if (realBtn) { realBtn.click(); return true; }
		return false;
	}

	var NATIVE_BAR_SEL = '.booking-sticky-footer a, .booking-sticky-footer .button, [data-ppv2-open-reservar]';
	document.addEventListener('click', function (e) {
		var trigger = e.target.closest(NATIVE_BAR_SEL);
		if (!trigger) return;
		// Solo en móvil (la barra nativa solo aparece en móvil, pero por
		// seguridad respetamos el breakpoint del sheet).
		if (!window.matchMedia('(max-width: 767px)').matches) return;
		e.preventDefault();
		e.stopImmediatePropagation();
		// Disparar el botón real de Booking Plus (modal o login según sesión).
		// Fallback al sheet antiguo solo si no existe el botón (listados sin
		// Booking Plus), para no dejar el botón "Reservar" sin acción.
		if (!triggerNativeBooking()) {
			openSheet('booking');
		}
	}, true); // true = captura

	// Click en backdrop, handle o botón × cierran el sheet
	var closeTriggers = sheet.querySelectorAll('[data-ppv2-close-sheet]');
	closeTriggers.forEach(function (el) {
		el.addEventListener('click', function (e) {
			e.preventDefault();
			closeSheet();
		});
	});

	// Tecla Escape cierra el sheet
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && sheet.classList.contains('is-open')) {
			closeSheet();
		}
	});
});
