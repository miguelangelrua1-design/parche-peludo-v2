/**
 * PP Mascotas — interacciones del formulario "Mis Mascotas".
 *
 * - La lista de razas se filtra según la especie elegida (perro/gato),
 *   siempre en orden alfabético (viene ordenada desde PHP vía PP_MASCOTAS).
 * - Vista previa de la foto de perfil al seleccionarla.
 * - Galería: vista previa de fotos nuevas con opción de quitarlas antes de
 *   enviar (se maneja con DataTransfer), quitar fotos ya guardadas y tope
 *   de 10 fotos en total.
 * - Confirmación antes de eliminar una mascota.
 */
(function () {
	'use strict';

	var form = document.getElementById('pp-masc-form');

	/* ---------- Razas según especie ---------- */
	function filtrarRazas() {
		var select = document.getElementById('pp_raza');
		if (!select || typeof PP_MASCOTAS === 'undefined') { return; }

		var especieInput = document.querySelector('input[name="pp_especie"]:checked');
		var especie = especieInput ? especieInput.value : '';
		var valorActual = select.value || select.getAttribute('data-valor') || '';

		// Reconstruimos las opciones solo con las razas de la especie elegida.
		select.innerHTML = '';
		var placeholder = document.createElement('option');
		placeholder.value = '';
		placeholder.disabled = true;
		placeholder.selected = true;
		placeholder.textContent = especie ? 'Selecciona una raza…' : 'Selecciona primero la especie…';
		select.appendChild(placeholder);

		if (!especie || !PP_MASCOTAS.razas[especie]) { return; }

		PP_MASCOTAS.razas[especie].forEach(function (raza) {
			var opt = document.createElement('option');
			opt.value = raza;
			opt.textContent = raza;
			if (raza === valorActual) {
				opt.selected = true;
				placeholder.selected = false;
			}
			select.appendChild(opt);
		});

		// Raza "heredada": la mascota tiene una raza que ya no está en el
		// catálogo (se eliminó desde el administrador). La conservamos.
		if (valorActual && !Array.prototype.some.call(select.options, function (o) { return o.value === valorActual; })) {
			var legado = document.createElement('option');
			legado.value = valorActual;
			legado.textContent = valorActual;
			legado.selected = true;
			placeholder.selected = false;
			select.appendChild(legado);
		}
	}

	document.querySelectorAll('input[name="pp_especie"]').forEach(function (radio) {
		radio.addEventListener('change', filtrarRazas);
	});
	filtrarRazas();

	/* ---------- Vista previa de la foto de perfil ---------- */
	var inputFoto = document.getElementById('pp_foto');
	if (inputFoto) {
		inputFoto.addEventListener('change', function () {
			var preview = document.getElementById('pp-foto-preview');
			var hint = document.getElementById('pp-foto-hint');
			if (this.files && this.files[0]) {
				preview.src = URL.createObjectURL(this.files[0]);
				preview.style.display = '';
				if (hint) { hint.style.display = 'none'; }
			}
		});
	}

	/* ---------- Galería: nuevas fotos con vista previa y tope de 10 ---------- */
	var inputGaleria = document.getElementById('pp_galeria');
	var contNuevas = document.getElementById('pp-galeria-nuevas');
	var contActual = document.getElementById('pp-galeria-actual');
	var almacen = (typeof DataTransfer !== 'undefined') ? new DataTransfer() : null;

	function fotosActualesActivas() {
		if (!contActual) { return 0; }
		return contActual.querySelectorAll('.pp-masc-galeria__item:not(.pp-masc-galeria__item--fuera)').length;
	}

	function pintarNuevas() {
		if (!contNuevas || !almacen) { return; }
		contNuevas.innerHTML = '';
		Array.prototype.forEach.call(almacen.files, function (file, indice) {
			var item = document.createElement('span');
			item.className = 'pp-masc-galeria__item';

			var img = document.createElement('img');
			img.src = URL.createObjectURL(file);
			img.alt = '';

			var quitar = document.createElement('button');
			quitar.type = 'button';
			quitar.className = 'pp-masc-galeria__quitar';
			quitar.title = 'Quitar esta foto';
			quitar.innerHTML = '&times;';
			quitar.addEventListener('click', function () {
				var nuevo = new DataTransfer();
				Array.prototype.forEach.call(almacen.files, function (f, i) {
					if (i !== indice) { nuevo.items.add(f); }
				});
				almacen = nuevo;
				inputGaleria.files = almacen.files;
				pintarNuevas();
			});

			item.appendChild(img);
			item.appendChild(quitar);
			contNuevas.appendChild(item);
		});
	}

	if (inputGaleria && almacen) {
		inputGaleria.addEventListener('change', function () {
			var max = (typeof PP_MASCOTAS !== 'undefined' && PP_MASCOTAS.maxGaleria) ? PP_MASCOTAS.maxGaleria : 10;
			var espacio = max - fotosActualesActivas() - almacen.files.length;
			var rechazadas = 0;

			Array.prototype.forEach.call(this.files, function (file) {
				if (espacio > 0) {
					almacen.items.add(file);
					espacio--;
				} else {
					rechazadas++;
				}
			});

			this.files = almacen.files;
			pintarNuevas();

			if (rechazadas > 0) {
				window.alert('Solo puedes tener hasta ' + max + ' fotos en la galería. Se omitieron ' + rechazadas + ' foto(s).');
			}
		});
	}

	/* ---------- Quitar fotos ya guardadas (marca para eliminar al guardar) ---------- */
	if (contActual && form) {
		contActual.querySelectorAll('.pp-masc-galeria__item').forEach(function (item) {
			var boton = item.querySelector('.pp-masc-galeria__quitar');
			if (!boton) { return; }
			boton.addEventListener('click', function () {
				var id = item.getAttribute('data-id');
				var marcada = item.classList.toggle('pp-masc-galeria__item--fuera');
				var existente = form.querySelector('input[name="pp_galeria_eliminar[]"][value="' + id + '"]');
				if (marcada && !existente) {
					var hidden = document.createElement('input');
					hidden.type = 'hidden';
					hidden.name = 'pp_galeria_eliminar[]';
					hidden.value = id;
					form.appendChild(hidden);
					boton.title = 'Recuperar esta foto';
				} else if (!marcada && existente) {
					existente.remove();
					boton.title = 'Quitar de la galería';
				}
			});
		});
	}

	/* ---------- Evitar doble envío: desactivar Guardar al primer clic ---------- */
	if (form) {
		form.addEventListener('submit', function () {
			var btn = form.querySelector('button[type="submit"]');
			if (btn && !btn.disabled) {
				btn.disabled = true;
				btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Guardando…';
			}
		});
	}

	/* ---------- Confirmación al eliminar la mascota ---------- */
	var formEliminar = document.getElementById('pp-masc-eliminar');
	if (formEliminar) {
		formEliminar.addEventListener('submit', function (e) {
			if (!window.confirm('¿Seguro que quieres eliminar esta mascota? Esta acción no se puede deshacer.')) {
				e.preventDefault();
				return;
			}
			var btn = formEliminar.querySelector('button[type="submit"]');
			if (btn) { btn.disabled = true; }
		});
	}
})();
