/* Migrado de functions.php::ppv2_message_form_labels() 2026-07-10 (wp_footer, prio 101).
   Condición de páginas: solo si is_singular('listing') (página individual de listado). */
document.addEventListener('DOMContentLoaded', function () {
	var form = document.querySelector('.message-vendor .wpcf7-form');
	if (!form) { return; }
	var fields = [
		{ sel: 'input[type="text"]',  text: 'Nombre completo' },
		{ sel: 'input[type="email"]', text: 'Correo electrónico' },
		{ sel: 'input[type="tel"]',   text: 'Celular' },
		{ sel: 'textarea',            text: 'Mensaje' }
	];
	fields.forEach(function (f) {
		var el = form.querySelector(f.sel);
		if (!el) { return; }
		var wrap = el.closest('.wpcf7-form-control-wrap') || el;
		var container = wrap.parentNode || wrap;
		if (container.querySelector('.ppv2-field-label')) { return; } // ya tiene label
		var lab = document.createElement('label');
		lab.className = 'ppv2-field-label';
		lab.textContent = f.text;
		if (el.id) { lab.setAttribute('for', el.id); }
		container.insertBefore(lab, wrap);
	});
});
