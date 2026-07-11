/* migrado de functions.php::ppv2_signin_feedback_into_view() 2026-07-10
   Se imprime en TODAS las páginas del front (sin condición PHP); el propio JS limita a móvil (max-width: 768px). */
document.addEventListener('DOMContentLoaded', function () {
	if (!window.matchMedia || !window.matchMedia('(max-width: 768px)').matches) return;

	function isShown(el) {
		return el && el.offsetParent !== null &&
			(el.textContent || '').trim().length > 0;
	}
	function bringIntoView(el) {
		if (!isShown(el)) return;
		try {
			el.scrollIntoView({ block: 'center', behavior: 'smooth' });
		} catch (e) {
			el.scrollIntoView();
		}
	}

	// Observa el diálogo de acceso; cuando aparece una .notification dentro de
	// los formularios de login/registro (o cambia), la lleva a la vista.
	function watchDialog(dialog) {
		if (!dialog || dialog.__ppv2FeedbackWatched) return;
		dialog.__ppv2FeedbackWatched = true;
		var obs = new MutationObserver(function () {
			var notes = dialog.querySelectorAll('form#login .notification, form#register .notification');
			for (var i = 0; i < notes.length; i++) {
				if (isShown(notes[i])) { bringIntoView(notes[i]); break; }
			}
		});
		obs.observe(dialog, { attributes: true, childList: true, subtree: true, characterData: true });
	}

	// El diálogo se mueve dentro de .mfp-content al abrirse (Magnific inline).
	// Observamos el body para engancharlo cuando aparezca.
	var existing = document.getElementById('sign-in-dialog');
	if (existing) watchDialog(existing);
	var bodyObs = new MutationObserver(function () {
		var d = document.querySelector('.mfp-content #sign-in-dialog') || document.getElementById('sign-in-dialog');
		if (d) watchDialog(d);
	});
	bodyObs.observe(document.body, { childList: true, subtree: true });
});
