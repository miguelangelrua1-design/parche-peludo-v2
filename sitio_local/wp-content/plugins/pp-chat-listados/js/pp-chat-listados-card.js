/**
 * PP Chat de Listados — tarjeta "Crear con el chat" en la pantalla
 * "Elige el tipo de listado" (página Agregar Listado de Listeo).
 * Se inyecta junto a las tarjetas nativas (.listing-type) y navega a la
 * página del chat. El clic se captura en fase de captura para que el JS de
 * Listeo (que intercepta .listing-type para seleccionar tipo) no lo anule.
 */
(function () {
	'use strict';

	var cfg = window.ppclCard || {};

	function inject() {
		var container = document.querySelector('.type-selection .listing-type-container');
		if (!container || !cfg.chatUrl || container.querySelector('.ppcl-type-chat')) {
			return;
		}

		var card = document.createElement('a');
		card.href = cfg.chatUrl;
		card.className = 'listing-type ppcl-type-chat';
		card.innerHTML =
			'<span class="ppcl-type-badge">' + (cfg.badge || 'Nuevo') + '</span>' +
			'<span class="listing-type-icon ppcl-type-chat-icon">' +
				'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">' +
					'<path d="M12 3C6.48 3 2 6.94 2 11.8c0 2.16.9 4.14 2.4 5.68-.17 1.24-.65 2.44-1.4 3.42-.14.19-.02.46.21.44 1.85-.13 3.55-.83 4.9-1.86 1.2.4 2.5.62 3.89.62 5.52 0 10-3.94 10-8.8S17.52 3 12 3z"/>' +
					'<circle cx="8" cy="11.8" r="1.15" fill="#fff"/>' +
					'<circle cx="12" cy="11.8" r="1.15" fill="#fff"/>' +
					'<circle cx="16" cy="11.8" r="1.15" fill="#fff"/>' +
				'</svg>' +
			'</span>' +
			'<h3>' + (cfg.title || 'Crear con el chat') + '</h3>' +
			'<p class="ppcl-type-sub">' + (cfg.sub || '') + ' 🐾</p>';

		card.addEventListener('click', function (e) {
			e.preventDefault();
			e.stopPropagation();
			window.location.href = cfg.chatUrl;
		}, true);

		container.appendChild(card);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', inject);
	} else {
		inject();
	}
})();
