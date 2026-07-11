/* migrado de functions.php::ppv2_validacion_html5_es() 2026-07-10 (script original con id="ppv2-validacion-es")
   Se imprime en TODAS las páginas del front; condición PHP: if ( is_admin() ) return; (no en wp-admin). */
(function () {
	function mensaje(el) {
		var v = el.validity;
		if (!v || v.valid) { return ''; }
		if (v.valueMissing) { return 'Por favor completa este campo.'; }
		if (v.typeMismatch && el.type === 'email') { return 'Ingresa un correo electrónico válido.'; }
		if (v.typeMismatch && el.type === 'url') { return 'Ingresa una dirección web válida.'; }
		if (v.typeMismatch) { return 'El formato no es válido.'; }
		if (v.patternMismatch) { return 'El formato no coincide con el solicitado.'; }
		if (v.tooShort) { return 'Escribe al menos ' + el.minLength + ' caracteres.'; }
		if (v.tooLong) { return 'El texto es demasiado largo.'; }
		if (v.rangeUnderflow) { return 'El valor mínimo es ' + el.min + '.'; }
		if (v.rangeOverflow) { return 'El valor máximo es ' + el.max + '.'; }
		if (v.stepMismatch) { return 'Ingresa un valor válido.'; }
		if (v.badInput) { return 'Ingresa un valor válido.'; }
		return 'Ingresa un valor válido.';
	}
	// Al fallar la validación: mensaje en español.
	document.addEventListener('invalid', function (e) {
		var el = e.target;
		if (el && el.setCustomValidity) { el.setCustomValidity(mensaje(el)); }
	}, true);
	// Al escribir/cambiar: limpiar el mensaje para no bloquear el reenvío.
	['input', 'change'].forEach(function (ev) {
		document.addEventListener(ev, function (e) {
			var el = e.target;
			if (el && el.setCustomValidity) { el.setCustomValidity(''); }
		}, true);
	});
})();
