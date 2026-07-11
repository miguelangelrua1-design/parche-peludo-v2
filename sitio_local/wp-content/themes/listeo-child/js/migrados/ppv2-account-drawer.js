/* Migrado de functions.php::ppv2_account_drawer() 2026-07-10 (wp_footer, prio 120).
   Condición de páginas: global, solo si is_user_logged_in(). */
(function () {
	function setOpen(um, open) {
		// Un solo panel a la vez: al abrir Mi Cuenta, cerrar el panel derecho
		// (Enviar Mensaje / Ver horarios / Carrito) si estuviera abierto.
		if (open && window.ppRDrawer) { window.ppRDrawer.close(); }
		um.classList.toggle('ppv2-acct-open', open);
		document.documentElement.classList.toggle('ppv2-acct-lock', open);
	}
	// Cierre global para que otros paneles puedan cerrar Mi Cuenta al abrirse.
	window.ppCloseAccountDrawer = function () {
		var opened = document.querySelector('#header .user-menu.ppv2-acct-open');
		if (opened) { setOpen(opened, false); }
	};
	// Inyecta la cabecera del drawer (título "Mi Cuenta" + botón ✕) la 1ª vez.
	function ensureHeader(um) {
		var ul = um.querySelector('ul');
		if (!ul || ul.querySelector('.ppv2-acct-header')) return;
		var header = document.createElement('li');
		header.className = 'ppv2-acct-header';
		var title = document.createElement('span');
		title.className = 'ppv2-acct-title';
		title.textContent = 'Mi Cuenta';
		var btn = document.createElement('button');
		btn.type = 'button';
		btn.className = 'ppv2-acct-close';
		btn.setAttribute('aria-label', 'Cerrar');
		btn.innerHTML = '&times;';
		btn.addEventListener('click', function (e) { e.stopPropagation(); setOpen(um, false); });
		header.appendChild(title);
		header.appendChild(btn);
		ul.insertBefore(header, ul.firstChild);
	}
	document.addEventListener('click', function (e) {
		var um = document.querySelector('#header .user-menu');
		if (!um) return;
		// Clic en el avatar → abrir/cerrar el drawer.
		if (e.target.closest('.user-menu .user-name')) {
			e.preventDefault();
			ensureHeader(um);
			setOpen(um, !um.classList.contains('ppv2-acct-open'));
			return;
		}
		// Clic FUERA del drawer (en el velo o la página) → cerrar.
		if (um.classList.contains('ppv2-acct-open') && !e.target.closest('.user-menu ul')) {
			setOpen(um, false);
		}
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') {
			var um = document.querySelector('.user-menu.ppv2-acct-open');
			if (um) setOpen(um, false);
		}
	});
})();
