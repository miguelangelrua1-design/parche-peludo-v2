/* migrado de functions.php::ppv2_dashboard_hamburger_icon() 2026-07-10
   Se imprime en TODAS las páginas del front (sin condición PHP); solo actúa si existe #header-container.dashboard .mmenu-trigger. */
(function () {
	function run() {
		var t = document.querySelector('#header-container.dashboard .mmenu-trigger');
		if (!t || t.querySelector('.hmb-ico')) return;
		t.innerHTML = '<div class="hmb-ico-wrap"><span class="hmb-ico"></span></div>';
	}
	if (document.readyState !== 'loading') { run(); }
	else { document.addEventListener('DOMContentLoaded', run); }
})();
