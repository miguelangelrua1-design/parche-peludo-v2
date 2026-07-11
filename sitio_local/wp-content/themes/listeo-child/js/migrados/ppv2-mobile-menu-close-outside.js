/* Migrado de functions.php::ppv2_mobile_menu_close_outside() 2026-07-10 (wp_footer, prio 121).
   Condición de páginas: global (sin condición). */
(function () {
	document.addEventListener('click', function (e) {
		if (!document.body.classList.contains('mobile-nav-open')) return;
		if (e.target.closest('.mobile-navigation-wrapper')) return; // dentro del panel
		if (e.target.closest('.mmenu-trigger, .menu-icon-toggle, .desktop-mmenu-trigger')) return; // el toggle ya lo maneja el tema
		document.body.classList.remove('mobile-nav-open');
	});
})();
