/**
 * PPV2 — Botón flotante de filtros del Directorio.
 *
 * QUÉ ARREGLA
 * Listeo trae un `.sticky-filter-button` que debería aparecer al hacer scroll,
 * pero está roto por dos motivos (medidos en producción 2026-07-26):
 *   1. Nace con `opacity: 0` y depende de que un JS del tema le ponga la clase
 *      `btn-visible`; esa clase NUNCA llega (el script no actúa).
 *   2. Su `position: sticky` está cancelado porque un ancestro
 *      (`.full-page-content-container`) tiene `overflow: hidden` — con eso el
 *      sticky deja de anclarse a la ventana y se va con el scroll (se midió en
 *      -566px, fuera de pantalla).
 * Además su `top: 10px` lo habría dejado detrás del header fijo (80px).
 *
 * QUÉ HACE
 * Reutiliza el MISMO botón de Listeo (no duplica lógica: el clic sigue siendo
 * el `.enable-filters-button` nativo, así que abrir/cerrar y el estado del
 * cajón móvil quedan consistentes) y solo se encarga de MOSTRARLO:
 * añade la clase `pp-fabfiltros-visible` al <html> cuando se pasa del umbral
 * de scroll. La posición (fijo, abajo a la izquierda) y el aspecto viven en
 * style.css.
 */
(function () {
	'use strict';

	var UMBRAL = 300;                       // px de scroll para que aparezca
	var CLASE  = 'pp-fabfiltros-visible';

	/**
	 * La visibilidad se aplica INLINE sobre el botón (con !important), no solo
	 * con la clase en <html>: en pruebas, la regla
	 * `html.pp-fabfiltros-visible .sticky-filter-button { opacity: 1 }` no
	 * llegaba a imponerse (el resto del bloque CSS sí aplica — posición, tamaño,
	 * píldora—; solo la opacidad por clase se resistía). El estilo inline con
	 * !important es el nivel más alto de la cascada y no depende de esa guerra.
	 */
	function pintar(fab, visible) {
		if (visible) {
			fab.style.setProperty('opacity', '1', 'important');
			fab.style.setProperty('transform', 'none', 'important');
			fab.style.setProperty('pointer-events', 'auto', 'important');
		} else {
			fab.style.setProperty('opacity', '0', 'important');
			fab.style.setProperty('transform', 'translateY(14px)', 'important');
			fab.style.setProperty('pointer-events', 'none', 'important');
		}
	}

	function init() {
		// NO se comprueba aquí que exista `.sticky-filter-button`: Listeo lo
		// inyecta por JS y puede no estar todavía en el DOM en este momento
		// (pasaba: init() salía antes de registrar los listeners y la clase
		// nunca se aplicaba). Como el único trabajo es marcar el <html>, el
		// CSS ya se encarga de que la clase no tenga efecto si no hay botón.
		var raiz = document.documentElement;
		var ultimo = null;

		// OJO: en esta plantilla el scroll NO siempre es el de la ventana. El
		// layout de mapa+listados hace scroll DENTRO de `.full-page-container`
		// (tiene overflow-y:auto), así que `window.pageYOffset` se queda en 0 y
		// escuchar solo `window` no dispararía nunca. Se vigilan ambos y se toma
		// el desplazamiento mayor.
		var caja = document.querySelector('.full-page-container') ||
		           document.querySelector('.full-page-content-container');

		function desplazamiento() {
			var y = window.pageYOffset || raiz.scrollTop || document.body.scrollTop || 0;
			if (caja && caja.scrollTop > y) { y = caja.scrollTop; }
			return y;
		}

		function actualizar() {
			var visible = desplazamiento() > UMBRAL;
			if (visible === ultimo) { return; }     // evita tocar el DOM en cada píxel
			ultimo = visible;
			raiz.classList.toggle(CLASE, visible);
			// El botón lo inyecta Listeo y puede llegar tarde: se busca en cada
			// cambio de estado (barato: solo al cruzar el umbral, no por píxel).
			var fab = document.querySelector('.sticky-filter-button');
			if (fab) { pintar(fab, visible); }
		}

		// passive: no bloquea el scroll. Throttle con setTimeout y NO con
		// requestAnimationFrame: rAF depende de que el navegador esté pintando
		// frames (pestañas en segundo plano o entornos sin render lo congelan
		// y el botón no aparecería nunca); un temporizador corre siempre.
		var pendiente = false;
		function alScrollear() {
			if (pendiente) { return; }
			pendiente = true;
			window.setTimeout(function () { pendiente = false; actualizar(); }, 100);
		}

		window.addEventListener('scroll', alScrollear, { passive: true });
		if (caja) { caja.addEventListener('scroll', alScrollear, { passive: true }); }

		actualizar();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
