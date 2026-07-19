/**
 * PP Chat de Listados v2 — asistente guiado para crear listados.
 *
 * - Tipologías dinámicas: pregunta el tipo entre los PERMITIDOS para el rol
 *   (AJAX ppcl_bootstrap; matriz del módulo Listados).
 * - Guion dinámico: los pasos se generan desde el Editor de Formularios de
 *   Listeo (AJAX ppcl_fields) → agregar/quitar campos en el admin actualiza
 *   el chat sin tocar código.
 * - Imágenes: campos file/files suben fotos (AJAX ppcl_upload) con vista
 *   previa; se guardan en el formato nativo de Listeo.
 * - Modos: "embedded" (dentro de Agregar Listado, botón "Agregar por chat",
 *   Atrás arriba a la izquierda devuelve a la pantalla general) y "page"
 *   (página propia con el shortcode).
 */
(function ($) {
	'use strict';

	var cfg = window.ppv2ChatListado || {};
	var STORAGE_KEY = 'ppv2ChatListado2';

	var $root, $messages, $inputArea;
	var built = false;         // UI construida
	var started = false;       // conversación iniciada
	var chatType = null;       // {slug, name}
	var schema = [];           // guion de campos del tipo elegido
	var queue = [];            // pasos (gates de grupo + campos)
	var queueIndex = -1;
	var answers = {};          // key -> {value, label}
	var returnToSummary = false;

	/* ------------------------------------------------------------------ */
	/* Textos amigables por campo (fallback genérico si no está aquí)      */
	/* ------------------------------------------------------------------ */

	var COPY = {
		listing_title:       'Para empezar: ¿qué título le ponemos? (p. ej. el nombre de tu negocio o de la publicación)',
		listing_description: 'Cuéntales a los padres y madres de mascotas los detalles: ¿de qué se trata, qué lo hace especial? (mínimo un par de frases)',
		_listing_logo:       '¿Tienes un logo o foto de perfil? Súbelo aquí 🖼️',
		_gallery:            '📸 ¡Las fotos son lo que más ayuda! Sube las que quieras (máx. {maxGallery}).',
		listing_category:    '¿En qué categoría encaja mejor?',
		keywords:            '¿Palabras clave para que te encuentren más fácil? (sepáralas con comas)',
		_address:            '¿Cuál es la dirección? Si no aplica, puedes omitirla.',
		_phone:              '¿A qué teléfono te pueden contactar?',
		_whatsapp:           '¿Tienes WhatsApp para recibir mensajes?',
		_email:              '¿Un correo electrónico de contacto?',
		_website:            '¿Tienes sitio web?',
		_instagram:          '¿Tu Instagram? Pega el enlace o escribe el usuario.',
		_tiktok:             '¿TikTok?',
		_facebook:           '¿Facebook?',
		_youtube:            '¿Canal de YouTube?',
		_video:              '¿Un video para mostrar? Pega el enlace (YouTube/Vimeo).',
		_price_min:          'Hablemos de precios 💰 ¿desde cuánto?',
		_price_max:          '¿Y hasta cuánto?',
		region:              '¿En qué ciudad o zona?'
	};

	function questionFor(field) {
		var text = COPY[field.key];
		if (!text) {
			text = field.required ? field.label + ':' : field.label + ' (puedes omitirlo):';
		}
		return text.replace('{maxGallery}', String(cfg.maxGallery || 10));
	}

	/* ------------------------------------------------------------------ */
	/* UI base                                                             */
	/* ------------------------------------------------------------------ */

	function scrollToBottom() {
		if ($messages && $messages.length) {
			$messages.stop().animate({ scrollTop: $messages[0].scrollHeight }, 250);
		}
	}

	function botSay(text, instant) {
		var $bubble = $('<div>').addClass('ppv2-chat-msg ppv2-chat-bot');
		$('<span>').addClass('ppv2-chat-avatar').text('🐾').appendTo($bubble);
		var $body = $('<div>').addClass('ppv2-chat-bubble').appendTo($bubble);
		if (instant) {
			$body.text(text);
			$messages.append($bubble);
			scrollToBottom();
			return $.Deferred().resolve().promise();
		}
		var dfd = $.Deferred();
		$body.html('<span class="ppv2-chat-typing"><i></i><i></i><i></i></span>');
		$messages.append($bubble);
		scrollToBottom();
		setTimeout(function () {
			$body.text(text);
			scrollToBottom();
			dfd.resolve();
		}, 400 + Math.min(text.length * 3, 450));
		return dfd.promise();
	}

	/** Burbuja "pensando" persistente; se cierra con el callback devuelto. */
	function botBusy() {
		var $bubble = $('<div>').addClass('ppv2-chat-msg ppv2-chat-bot');
		$('<span>').addClass('ppv2-chat-avatar').text('🐾').appendTo($bubble);
		var $body = $('<div>').addClass('ppv2-chat-bubble')
			.html('<span class="ppv2-chat-typing"><i></i><i></i><i></i></span>')
			.appendTo($bubble);
		$messages.append($bubble);
		scrollToBottom();
		return function done(text) {
			if (text) { $body.text(text); } else { $bubble.remove(); }
			scrollToBottom();
		};
	}

	function userSay(text) {
		var $bubble = $('<div>').addClass('ppv2-chat-msg ppv2-chat-user');
		$('<div>').addClass('ppv2-chat-bubble').text(text).appendTo($bubble);
		$messages.append($bubble);
		scrollToBottom();
	}

	function clearInputArea() { $inputArea.empty(); }

	function showButtons(options, onPick) {
		clearInputArea();
		var $wrap = $('<div>').addClass('ppv2-chat-options');
		options.forEach(function (opt) {
			$('<button>')
				.attr('type', 'button')
				.addClass('ppv2-chat-option' + (opt.cls ? ' ' + opt.cls : ''))
				.text(opt.label)
				.on('click', function () { onPick(opt, $(this)); })
				.appendTo($wrap);
		});
		$inputArea.append($wrap);
		scrollToBottom();
	}

	function showTextInput(field, onSubmit) {
		clearInputArea();
		var isArea = field.kind === 'textarea';
		var $field = isArea ? $('<textarea>').attr('rows', 3) : $('<input>').attr('type', 'text');
		$field.addClass('ppv2-chat-field').attr('placeholder', field.placeholder || 'Escribe aquí…');
		if (field.kind === 'number') { $field.attr('inputmode', 'decimal'); }
		else if (field.inputMode) { $field.attr('inputmode', field.inputMode); }

		var $send = $('<button>').attr('type', 'button').addClass('ppv2-chat-send').text('Enviar');
		$inputArea.append($('<div>').addClass('ppv2-chat-inputrow').append($field, $send));

		if (!field.required) {
			var $skip = $('<button>').attr('type', 'button').addClass('ppv2-chat-skip').text('Omitir este paso');
			$inputArea.append($skip);
			$skip.on('click', function () { userSay('(omitido)'); onSubmit('', '—'); });
		}

		function submit() {
			var value = $.trim($field.val());
			if (!value) {
				if (!field.required) { return; }
				$field.addClass('ppv2-chat-field-error');
				setTimeout(function () { $field.removeClass('ppv2-chat-field-error'); }, 800);
				return;
			}
			if (field.kind === 'number') {
				var num = value.replace(',', '.').replace(/[^\d.\-]/g, '');
				if (!num || !isFinite(Number(num))) {
					botSay('Ahí necesito solo un número 🙂', true);
					return;
				}
				value = num;
			}
			if (field.key === 'listing_description' && field.required && value.length < 40) {
				botSay('Un poquito más 🙏 — con dos o tres frases es suficiente.', true);
				return;
			}
			if (field.inputMode === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
				botSay('Mmm, ese correo no se ve completo. Revísalo e inténtalo de nuevo 🙂', true);
				return;
			}
			userSay(value);
			onSubmit(value, value);
		}

		$send.on('click', submit);
		$field.on('keydown', function (e) {
			if (e.key === 'Enter' && !isArea) { e.preventDefault(); submit(); }
			if (e.key === 'Enter' && isArea && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submit(); }
		});
		$field.trigger('focus');
	}

	/* ------------------------------------------------------------------ */
	/* AJAX                                                                */
	/* ------------------------------------------------------------------ */

	function api(action, data) {
		return $.post(cfg.ajaxUrl, $.extend({ action: action, nonce: cfg.nonce }, data || {}));
	}

	function handleCommonErrors(res) {
		var code = res && res.data && res.data.code;
		if (code === 'login') { showLoginGate(); return true; }
		if (code === 'role' || code === 'type') {
			botSay((res.data && res.data.message) || 'Tu cuenta no puede publicar este tipo de listado.', true);
			clearInputArea();
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Persistencia (por si el usuario debe iniciar sesión o recarga)      */
	/* ------------------------------------------------------------------ */

	function saveState() {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ type: chatType, answers: answers }));
		} catch (e) { /* sin almacenamiento */ }
	}
	function clearState() {
		try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) { /* noop */ }
	}
	function readState() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) { return null; }
			var saved = JSON.parse(raw);
			return (saved && saved.type && saved.type.slug && saved.answers) ? saved : null;
		} catch (e) { return null; }
	}

	function setAnswer(key, value, label) {
		answers[key] = { value: value, label: label !== undefined ? String(label) : String(value) };
		saveState();
	}

	/* ------------------------------------------------------------------ */
	/* Flujo: tipo → guion dinámico → resumen → crear                      */
	/* ------------------------------------------------------------------ */

	function startConversation() {
		if (!cfg.loggedIn) {
			botSay('¡Hola! 🐾 Para crear un listado necesitas una cuenta. Inicia sesión o regístrate y volvemos aquí de una.').then(showLoginGate);
			return;
		}
		var saved = readState();
		var done = botBusy();
		api('ppcl_bootstrap').done(function (res) {
			done();
			if (!res || !res.success) { if (!handleCommonErrors(res)) { failRetry(startConversation); } return; }
			var types = res.data.types || [];
			if (!types.length) {
				botSay('Tu cuenta aún no tiene tipos de listado disponibles. Escríbenos si crees que es un error 🙏', true);
				return;
			}
			// ¿Hay una conversación guardada de un tipo aún permitido?
			if (saved && types.some(function (t) { return t.slug === saved.type.slug; })) {
				chatType = saved.type;
				answers = saved.answers || {};
				botSay('¡Hola de nuevo! 👋 Guardé tus respuestas de "' + chatType.name + '". Sigamos donde íbamos.').then(function () {
					fetchSchema(function () { showSummary(); });
				});
				return;
			}
			clearState();
			askType(types);
		}).fail(function () { done(); failRetry(startConversation); });
	}

	function askType(types) {
		if (types.length === 1) {
			chatType = types[0];
			saveState();
			botSay('¡Hola! 🐾 Te ayudo a crear tu listado de "' + chatType.name + '" en un par de minutos.').then(beginSchema);
			return;
		}
		botSay('¡Hola! 🐾 Te ayudo a crear tu listado en un par de minutos. Primero: ¿qué quieres publicar?').then(function () {
			showButtons(types.map(function (t) { return { label: t.name, type: t }; }), function (opt) {
				userSay(opt.type.name);
				chatType = { slug: opt.type.slug, name: opt.type.name };
				answers = {};
				saveState();
				beginSchema();
			});
		});
	}

	function beginSchema() {
		fetchSchema(function () {
			buildQueue();
			queueIndex = -1;
			nextStep();
		});
	}

	function fetchSchema(onReady) {
		var done = botBusy();
		api('ppcl_fields', { type: chatType.slug }).done(function (res) {
			done();
			if (!res || !res.success) { if (!handleCommonErrors(res)) { failRetry(function () { fetchSchema(onReady); }); } return; }
			schema = res.data.schema || [];
			if (!schema.length) {
				botSay('Este tipo de listado no tiene campos configurados todavía. Avísale al administrador 🙏', true);
				return;
			}
			onReady();
		}).fail(function () { done(); failRetry(function () { fetchSchema(onReady); }); });
	}

	/** Construye la cola de pasos: intro/puerta por grupo + un paso por campo. */
	function buildQueue() {
		queue = [];
		var byGroup = {};
		schema.forEach(function (f) {
			(byGroup[f.group] = byGroup[f.group] || []).push(f);
		});
		Object.keys(byGroup).forEach(function (g) {
			var fields = byGroup[g];
			var allOptional = fields.every(function (f) { return !f.required; });
			// Sección totalmente opcional y con varios campos → puerta para omitirla completa.
			if (allOptional && fields.length >= 3) {
				queue.push({ gate: true, group: g, groupTitle: fields[0].groupTitle, count: fields.length });
			} else if (fields.length >= 2) {
				queue.push({ intro: true, group: g, groupTitle: fields[0].groupTitle });
			}
			fields.forEach(function (f) { queue.push({ field: f, group: g }); });
		});
	}

	function nextStep() {
		if (returnToSummary) { returnToSummary = false; showSummary(); return; }
		queueIndex++;
		if (queueIndex >= queue.length) { showSummary(); return; }
		var step = queue[queueIndex];

		if (step.intro) {
			botSay('Vamos con "' + step.groupTitle + '" 📋', true);
			nextStep();
			return;
		}
		if (step.gate) {
			botSay('La sección "' + step.groupTitle + '" tiene ' + step.count + ' datos opcionales. ¿La diligenciamos?').then(function () {
				showButtons([
					{ label: 'Sí, vamos', go: true, cls: 'ppv2-chat-option-primary' },
					{ label: 'Omitir sección', go: false, cls: 'ppv2-chat-option-alt' }
				], function (opt) {
					userSay(opt.label);
					if (!opt.go) {
						var group = step.group;
						while (queueIndex + 1 < queue.length && queue[queueIndex + 1].group === group) {
							queueIndex++;
						}
					}
					nextStep();
				});
			});
			return;
		}
		runFieldStep(step.field, false);
	}

	function runFieldStep(field, editing) {
		botSay(questionFor(field)).then(function () {
			var onDone = function (value, label) {
				setAnswer(field.key, value, label);
				nextStep();
			};
			switch (field.kind) {
				case 'terms':        return runTermsStep(field, onDone);
				case 'options':      return runOptionsStep(field, onDone);
				case 'multioptions': return runMultiOptionsStep(field, onDone);
				case 'boolean':      return runBooleanStep(field, onDone);
				case 'image':        return runImagesStep(field, onDone, false);
				case 'images':       return runImagesStep(field, onDone, true);
				default:
					// Conveniencia: WhatsApp puede reusar el teléfono ya dado.
					if (field.key === '_whatsapp' && answers._phone && answers._phone.value) {
						return runWhatsappStep(field, onDone);
					}
					return showTextInput(field, onDone);
			}
		});
	}

	function runTermsStep(field, onDone) {
		var tree = field.tree || [];
		if (!tree.length) { onDone('', '—'); return; }
		var options = tree.map(function (t) { return { label: t.name, term: t }; });
		if (!field.required) { options.push({ label: 'Omitir', skip: true, cls: 'ppv2-chat-option-alt' }); }
		showButtons(options, function (opt) {
			if (opt.skip) { userSay('(omitido)'); onDone('', '—'); return; }
			userSay(opt.term.name);
			var kids = opt.term.children || [];
			if (!kids.length) { onDone(opt.term.id, opt.term.name); return; }
			botSay('¿Algo más específico dentro de ' + opt.term.name + '?').then(function () {
				var subs = kids.map(function (k) { return { label: k.name, term: k }; });
				subs.push({ label: 'Solo ' + opt.term.name, term: opt.term, cls: 'ppv2-chat-option-alt' });
				showButtons(subs, function (sub) {
					var isParent = sub.term.id === opt.term.id;
					userSay(isParent ? 'Solo ' + opt.term.name : sub.term.name);
					onDone(sub.term.id, isParent ? opt.term.name : opt.term.name + ' › ' + sub.term.name);
				});
			});
		});
	}

	function runOptionsStep(field, onDone) {
		var options = Object.keys(field.options).map(function (k) {
			return { label: field.options[k], val: k };
		});
		if (!field.required) { options.push({ label: 'Omitir', skip: true, cls: 'ppv2-chat-option-alt' }); }
		showButtons(options, function (opt) {
			if (opt.skip) { userSay('(omitido)'); onDone('', '—'); return; }
			userSay(opt.label);
			onDone(opt.val, opt.label);
		});
	}

	function runMultiOptionsStep(field, onDone) {
		clearInputArea();
		var selected = {};
		var $wrap = $('<div>').addClass('ppv2-chat-options');
		Object.keys(field.options).forEach(function (k) {
			$('<button>').attr('type', 'button').addClass('ppv2-chat-option')
				.text(field.options[k])
				.on('click', function () {
					selected[k] = !selected[k];
					$(this).toggleClass('ppv2-chat-option-selected', selected[k]);
				})
				.appendTo($wrap);
		});
		var $actions = $('<div>').addClass('ppv2-chat-options ppv2-chat-options-actions');
		$('<button>').attr('type', 'button').addClass('ppv2-chat-option ppv2-chat-option-primary').text('✔ Listo')
			.on('click', function () {
				var keys = Object.keys(selected).filter(function (k) { return selected[k]; });
				if (!keys.length && field.required) { botSay('Elige al menos una opción 🙂', true); return; }
				var labels = keys.map(function (k) { return field.options[k]; });
				userSay(keys.length ? labels.join(', ') : '(omitido)');
				onDone(keys.length ? keys : '', keys.length ? labels.join(', ') : '—');
			}).appendTo($actions);
		$inputArea.append($wrap, $actions);
		scrollToBottom();
	}

	function runBooleanStep(field, onDone) {
		showButtons([
			{ label: 'Sí', val: true, cls: 'ppv2-chat-option-primary' },
			{ label: 'No', val: false }
		], function (opt) {
			userSay(opt.label);
			onDone(opt.val ? true : '', opt.label);
		});
	}

	function runWhatsappStep(field, onDone) {
		var phone = answers._phone.value;
		showButtons([
			{ label: 'Es el mismo teléfono', val: phone },
			{ label: 'Es otro número', other: true },
			{ label: 'No tengo WhatsApp', val: '' }
		], function (opt) {
			userSay(opt.label);
			if (opt.other) { showTextInput(field, onDone); return; }
			onDone(opt.val, opt.val || '—');
		});
	}

	/* --------------------------- Imágenes ------------------------------ */

	function runImagesStep(field, onDone, multiple) {
		clearInputArea();
		var max = multiple ? (cfg.maxGallery || 10) : 1;
		var items = [];
		var prev = answers[field.key] && answers[field.key].value;
		if (prev) {
			(multiple ? prev : [prev]).forEach(function (id) { items.push({ id: id }); });
		}

		var $thumbs = $('<div>').addClass('ppcl-thumbs');
		var $input = $('<input type="file" accept="image/*" hidden>');
		if (multiple) { $input.attr('multiple', 'multiple'); }
		var $add = $('<button>').attr('type', 'button').addClass('ppv2-chat-option')
			.text(multiple ? '📷 Añadir fotos' : '📷 Elegir imagen');
		var $ok = $('<button>').attr('type', 'button').addClass('ppv2-chat-option ppv2-chat-option-primary');
		var $skip = field.required ? null
			: $('<button>').attr('type', 'button').addClass('ppv2-chat-option ppv2-chat-option-alt').text('Omitir');

		function refresh() {
			$ok.text(items.length ? '✔ Listo (' + items.length + (multiple ? ' fotos)' : ' foto)') : '✔ Listo')
				.prop('disabled', !items.length && !!field.required)
				.toggle(!!items.length || !field.required);
			$add.prop('disabled', items.length >= max)
				.text(items.length >= max ? 'Máximo alcanzado' : (multiple ? '📷 Añadir fotos' : (items.length ? '📷 Cambiar imagen' : '📷 Elegir imagen')));
		}

		function addThumb(item) {
			var $t = $('<span>').addClass('ppcl-thumb');
			if (item.thumb) { $t.append($('<img>').attr({ src: item.thumb, alt: '' })); }
			else { $t.text('🖼️'); }
			$('<button type="button" class="ppcl-thumb-x" aria-label="Quitar">×</button>')
				.on('click', function () {
					items = items.filter(function (i) { return i !== item; });
					$t.remove();
					refresh();
				}).appendTo($t);
			$thumbs.append($t);
		}
		items.forEach(addThumb);

		function uploadFiles(files) {
			var list = Array.prototype.slice.call(files).slice(0, max - items.length);
			if (!list.length) { return; }
			if (!multiple) { items = []; $thumbs.empty(); }
			var idx = 0;
			var done = botBusy();
			(function uploadNext() {
				if (idx >= list.length) {
					done('¡Fotos listas! ✅');
					refresh();
					return;
				}
				var file = list[idx];
				if (file.size > (cfg.maxMb || 10) * 1024 * 1024) {
					botSay('"' + file.name + '" pesa más de ' + (cfg.maxMb || 10) + ' MB, la salté 🙈', true);
					idx++; uploadNext(); return;
				}
				var fd = new FormData();
				fd.append('action', 'ppcl_upload');
				fd.append('nonce', cfg.nonce);
				fd.append('file', file);
				$.ajax({ url: cfg.ajaxUrl, method: 'POST', data: fd, processData: false, contentType: false })
					.done(function (res) {
						if (res && res.success) {
							var item = { id: res.data.id, thumb: res.data.thumb };
							items.push(item);
							addThumb(item);
						} else {
							botSay((res && res.data && res.data.message) || 'No pude subir "' + file.name + '" 😿', true);
						}
						idx++; uploadNext();
					})
					.fail(function () {
						botSay('No pude subir "' + file.name + '" 😿 Revisa tu conexión.', true);
						idx++; uploadNext();
					});
			})();
		}

		$add.on('click', function () { $input.trigger('click'); });
		$input.on('change', function () { uploadFiles(this.files); this.value = ''; });
		$ok.on('click', function () {
			if (!items.length) {
				if (field.required) { return; }
				userSay('(sin fotos)');
				onDone('', '—');
				return;
			}
			userSay('📷 ' + items.length + (multiple ? ' foto(s)' : ' foto'));
			var ids = items.map(function (i) { return i.id; });
			onDone(multiple ? ids : ids[0], items.length + (multiple ? ' foto(s)' : ' foto'));
		});
		if ($skip) {
			$skip.on('click', function () { userSay('(omitido)'); onDone('', '—'); });
		}

		var $actions = $('<div>').addClass('ppv2-chat-options ppv2-chat-options-actions').append($add, $ok);
		if ($skip) { $actions.append($skip); }
		$inputArea.append($thumbs, $actions, $input);
		refresh();
		scrollToBottom();
	}

	/* --------------------------- Resumen ------------------------------- */

	function showSummary() {
		clearInputArea();
		botSay('¡Esto va quedando muy bien! 🎉 Revisa el resumen de tu listado:').then(function () {
			var $card = $('<div>').addClass('ppv2-chat-summary');
			$('<div>').addClass('ppv2-chat-summary-row')
				.append($('<strong>').text('Tipo: '))
				.append(document.createTextNode(chatType.name))
				.appendTo($card);
			schema.forEach(function (f) {
				var a = answers[f.key];
				if (a && a.value !== '' && a.value !== null && String(a.label) !== '—') {
					$('<div>').addClass('ppv2-chat-summary-row')
						.append($('<strong>').text(f.label + ': '))
						.append(document.createTextNode(a.label))
						.appendTo($card);
				}
			});
			$messages.append($card);
			scrollToBottom();
			showButtons([
				{ label: '✅ Crear mi listado', action: 'create', cls: 'ppv2-chat-option-primary' },
				{ label: '✏️ Corregir algo', action: 'fix' }
			], function (opt) {
				userSay(opt.label);
				if (opt.action === 'create') { createListing(); } else { showFixMenu(); }
			});
		});
	}

	function showFixMenu() {
		botSay('Claro, ¿qué quieres corregir?').then(function () {
			var options = [{ label: 'Tipo de listado', restart: true }];
			schema.forEach(function (f, i) { options.push({ label: f.label, idx: i }); });
			options.push({ label: '↩ Volver al resumen', back: true, cls: 'ppv2-chat-option-alt' });
			showButtons(options, function (opt) {
				userSay(opt.label);
				if (opt.back) { showSummary(); return; }
				if (opt.restart) {
					botSay('Listo, empecemos de nuevo con otro tipo (tus respuestas de este se descartan).', true);
					chatType = null; answers = {}; schema = []; clearState();
					startConversation();
					return;
				}
				returnToSummary = true;
				runFieldStep(schema[opt.idx], true);
			});
		});
	}

	/* --------------------------- Crear --------------------------------- */

	function showLoginGate() {
		var $headerSignIn = $('a.sign-in').first();
		showButtons([
			{ label: '🔑 Iniciar sesión o crear cuenta', cls: 'ppv2-chat-option-primary' }
		], function () {
			saveState();
			if ($headerSignIn.length) { $headerSignIn.trigger('click'); }
			else if (cfg.loginUrl) { window.location.href = cfg.loginUrl; }
		});
	}

	function failRetry(retryFn) {
		botSay('No pude conectarme con el sitio 😿 Revisa tu conexión e inténtalo de nuevo.', true);
		showButtons([{ label: '🔄 Reintentar', cls: 'ppv2-chat-option-primary' }], function () { retryFn(); });
	}

	function createListing() {
		clearInputArea();
		if (!cfg.loggedIn) { showLoginGate(); return; }
		var done = botBusy();
		var plain = {};
		Object.keys(answers).forEach(function (k) { plain[k] = answers[k].value; });
		api('ppcl_create', { type: chatType.slug, fields: JSON.stringify(plain) })
			.done(function (res) {
				done();
				if (res && res.success) {
					clearState();
					botSay('¡Listo! 🎉 Tu borrador quedó creado. Revísalo en el formulario (ya va con todo lo que me contaste, fotos incluidas), complétalo si falta algo y envíalo. Nuestro equipo lo aprobará para publicarlo.').then(function () {
						showButtons([
							{ label: '📝 Revisar y enviar mi listado', url: res.data.continue_url, cls: 'ppv2-chat-option-primary' }
						], function (opt) { window.location.href = opt.url; });
					});
					return;
				}
				if (handleCommonErrors(res)) { return; }
				botSay((res && res.data && res.data.message) || 'Algo no salió bien 😿 Inténtalo de nuevo en un momento.', true);
				showButtons([{ label: '🔄 Reintentar', cls: 'ppv2-chat-option-primary' }], function () { createListing(); });
			})
			.fail(function () { done(); failRetry(createListing); });
	}

	/* ------------------------------------------------------------------ */
	/* Construcción del contenedor + modos                                 */
	/* ------------------------------------------------------------------ */

	function buildUI() {
		if (built) { return; }
		built = true;

		// "Atrás" arriba a la IZQUIERDA: en modo embebido devuelve a la
		// pantalla general de Agregar Listado; en página propia, atrás.
		var $back = $('<a>')
			.addClass('ppv2-chat-back')
			.attr('href', cfg.backUrl || '#')
			.text('← Atrás')
			.on('click', function (e) {
				if (cfg.mode === 'embedded') {
					e.preventDefault();
					closeEmbedded();
					return;
				}
				if (document.referrer && document.referrer.indexOf(window.location.host) !== -1) {
					e.preventDefault();
					window.history.back();
				}
			});

		var $header = $('<div>').addClass('ppv2-chat-header')
			.append($back)
			.append($('<span>').addClass('ppv2-chat-header-icon').text('🐾'))
			.append($('<div>').addClass('ppv2-chat-header-titles')
				.append($('<strong>').text('Asistente Parche Peludo'))
				.append($('<small>').text('Crea tu listado conversando')));
		$messages = $('<div>').addClass('ppv2-chat-messages');
		$inputArea = $('<div>').addClass('ppv2-chat-input');
		$root.append($header, $messages, $inputArea);
	}

	function openEmbedded() {
		$('#submit-listing-form').attr('hidden', true).hide();
		$('#ppcl-chat-section').attr('hidden', true).hide();
		$root.removeAttr('hidden').show();
		buildUI();
		if (!started) { started = true; startConversation(); }
		if ($root[0] && $root[0].scrollIntoView) {
			$root[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	function closeEmbedded() {
		$root.attr('hidden', true).hide();
		$('#submit-listing-form').removeAttr('hidden').show();
		$('#ppcl-chat-section').removeAttr('hidden').show();
		var $form = $('#submit-listing-form');
		if ($form.length && $form[0].scrollIntoView) {
			$form[0].scrollIntoView({ behavior: 'smooth', block: 'start' });
		}
	}

	$(function () {
		$root = $('#ppv2-chat-listado');
		if (!$root.length) { return; }

		if (cfg.mode === 'embedded') {
			$(document).on('click', '#ppcl-open-chat', openEmbedded);
			// Si venía en medio de una conversación guardada, reabrir directo.
			if (readState()) { openEmbedded(); }
			return;
		}
		buildUI();
		started = true;
		startConversation();
	});

})(window.jQuery);
