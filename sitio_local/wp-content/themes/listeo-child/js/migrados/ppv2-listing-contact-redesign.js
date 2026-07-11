/* migrado de functions.php::ppv2_listing_contact_redesign() 2026-07-10
   Solo se imprime si is_singular('listing'). Reestructura .listing-links-container (contacto + redes + CTA sin sesión). */
(function () {
	function run() {
		var box = document.querySelector('.single-listing .listing-links-container');
		if (!box || box.dataset.ppv2Ct) return;

		/* 1) Filas de CONTACTO (Celular / Correo / Sitio web) */
		var cu = box.querySelector('ul.contact-links');
		if (cu) {
			var h = document.createElement('h3');
			h.className = 'ppv2-ct-h';
			h.textContent = 'Información de Contacto';
			cu.parentNode.insertBefore(h, cu);
			cu.querySelectorAll('li > a').forEach(function (a) {
				var ic = a.querySelector('i');
				var icCls = ic ? ic.className : '';
				var label = /fa-phone/.test(icCls) ? 'Celular'
					: (/envelope/.test(icCls) ? 'Correo' : 'Sitio web');
				var val = a.textContent.trim();
				a.innerHTML =
					'<span class="ppv2-ct-ico">' + (ic ? ic.outerHTML : '') + '</span>' +
					'<span class="ppv2-ct-body">' +
						'<span class="ppv2-ct-label">' + label + '</span>' +
						'<span class="ppv2-ct-value"></span>' +
					'</span>' +
					'<i class="ppv2-ct-chev fa fa-angle-right" aria-hidden="true"></i>';
				a.querySelector('.ppv2-ct-value').textContent = val; // texto seguro
			});
		}

		/* 2) Botones de REDES */
		var su = null;
		box.querySelectorAll('ul.listing-links').forEach(function (u) {
			if (!u.classList.contains('contact-links')) su = u;
		});
		if (su) {
			var hs = document.createElement('h3');
			hs.className = 'ppv2-ct-h ppv2-ct-h-social';
			hs.textContent = 'Encuéntranos en redes';
			su.parentNode.insertBefore(hs, su);
			su.querySelectorAll('li > a').forEach(function (a) {
				if (a.querySelector('.ppv2-soc-launch')) return;
				var l = document.createElement('i');
				l.className = 'ppv2-soc-launch fa fa-external-link';
				l.setAttribute('aria-hidden', 'true');
				a.appendChild(l);
			});
		}

		/* 3) Tarjeta CTA "¿Quieres ver más detalles?" (estado SIN sesión) */
		var p = box.querySelector('p');
		var sign = p && p.querySelector('a.sign-in');
		if (sign) {
			p.classList.add('ppv2-ct-cta');
			var ico = document.createElement('span');
			ico.className = 'ppv2-cta-ico';
			ico.innerHTML = '<i class="fa fa-lock" aria-hidden="true"></i>';
			var body = document.createElement('span');
			body.className = 'ppv2-cta-body';
			var title = document.createElement('strong');
			title.className = 'ppv2-cta-title';
			title.textContent = '¿Quieres ver más detalles?';
			var txt = document.createElement('span');
			txt.className = 'ppv2-cta-text';
			sign.textContent = 'inicia sesión';
			txt.appendChild(document.createTextNode('Por favor '));
			txt.appendChild(sign); // mueve el enlace original → conserva su funcionalidad (popup)
			txt.appendChild(document.createTextNode(' para acceder a la información de contacto y agendar una cita.'));
			body.appendChild(title);
			body.appendChild(txt);
			p.innerHTML = '';
			p.appendChild(ico);
			p.appendChild(body);
		}

		box.dataset.ppv2Ct = '1';
	}
	if (document.readyState !== 'loading') { run(); }
	else { document.addEventListener('DOMContentLoaded', run); }
})();
