/**
 * PP Chat de Listados v2.1 — asistente guiado para crear listados.
 *
 * - Tipologías dinámicas según el rol (AJAX ppcl_bootstrap) con tope de
 *   publicaciones por cuenta (módulo Listados de Personalización Parche).
 * - MODO EXPRÉS: primero solo lo esencial (obligatorios, contacto, fotos,
 *   categorías); al final ofrece "afinar detalles" con el resto de campos
 *   del Editor de Formularios (AJAX ppcl_fields, siempre sincronizado).
 * - Horarios de atención simplificados (días + hora de apertura/cierre) en
 *   los tipos que los manejan; se guardan en el formato nativo por día.
 * - Imágenes con vista previa (AJAX ppcl_upload), barra de progreso de
 *   preguntas y "↩ Corregir anterior" en cualquier momento.
 * - Modos: "embedded" (dentro de Agregar Listado, con Atrás arriba a la
 *   izquierda) y "page" (página propia con el shortcode).
 */
(function ($) {
	'use strict';

	var cfg = window.ppv2ChatListado || {};
	var STORAGE_KEY = 'ppv2ChatListado2';

	var $root, $messages, $inputArea, $progressBar, $progressText, $progressWrap;
	var built = false;
	var started = false;
	var chatType = null;        // {slug, name}
	var schema = [];            // guion completo del tipo
	var hoursSupported = false; // ¿el tipo maneja horarios?
	var queue = [];             // pasos
	var queueIndex = -1;
	var answers = {};           // key -> {value, label}
	var history = [];           // índices de pasos respondidos (para deshacer)
	var returnToSummary = false;

	var DAY_LABELS = {
		monday: 'Lun', tuesday: 'Mar', wednesday: 'Mié', thursday: 'Jue',
		friday: 'Vie', saturday: 'Sáb', sunday: 'Dom'
	};
	var DAY_ORDER = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
	var HOURS_KEY = '_pp_hours';
	var HOURS_LABEL = 'Horario de atención';

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

	/* -------------------------- Progreso ------------------------------- */

	function questionSteps() {
		return queue.filter(function (s) { return s.field || s.hours; });
	}

	function updateProgress() {
		var steps = questionSteps();
		if (!steps.length) { $progressWrap.attr('hidden', true); return; }
		var done = 0, current = 0, i, s;
		for (i = 0; i < queue.length; i++) {
			s = queue[i];
			if (!s.field && !s.hours) { continue; }
			current++;
			if (i < queueIndex) { done = current; }
			if (i === queueIndex) { break; }
		}
		var x = Math.min(current, steps.length);
		$progressWrap.removeAttr('hidden');
		$progressText.text('Pregunta ' + x + ' de ' + steps.length);
		$progressBar.css('width', Math.round(100 * x / steps.length) + '%');
	}

	function hideProgress() { $progressWrap.attr('hidden', true); }

	/* -------------------------- Deshacer ------------------------------- */

	function addUndoLink() {
		if (!history.length) { return; }
		var $undo = $('<button>').attr('type', 'button').addClass('ppv2-chat-skip ppv2-chat-undo')
			.text('↩ Corregir la anterior')
			.on('click', function () {
				var prevIdx = history[history.length - 1];
				var step = queue[prevIdx];
				userSay('↩ Corregir la anterior');
				if (step.hours) {
					runHoursStep(true, function () { presentCurrent(); });
				} else {
					runFieldStep(step.field, true, function () { presentCurrent(); });
				}
			});
		$inputArea.append($undo);
	}

	/* -------------------------- Entradas ------------------------------- */

	function showButtons(options, onPick, withUndo) {
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
		if (withUndo) { addUndoLink(); }
		scrollToBottom();
	}

	function showTextInput(field, onSubmit, withUndo) {
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
		if (withUndo) { addUndoLink(); }

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
		if (code === 'role' || code === 'type' || code === 'limit') {
			botSay((res.data && res.data.message) || 'Tu cuenta no puede publicar este tipo de listado.', true);
			clearInputArea();
			return true;
		}
		return false;
	}

	/* ------------------------------------------------------------------ */
	/* Persistencia                                                        */
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
	/* Flujo: tipo → esenciales → horarios → extras opcionales → resumen   */
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
			if (saved && types.some(function (t) { return t.slug === saved.type.slug && t.remaining !== 0; })) {
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
		var available = types.filter(function (t) { return t.remaining !== 0; });
		if (!available.length) {
			botSay('Ya alcanzaste el número máximo de publicaciones para tu cuenta 🙈 Puedes gestionar las que tienes desde "Mis publicaciones".', true);
			return;
		}
		if (types.length === 1 && available.length === 1) {
			chatType = { slug: available[0].slug, name: available[0].name };
			saveState();
			botSay('¡Hola! 🐾 Te ayudo a crear tu listado de "' + chatType.name + '" en un par de minutos.').then(beginSchema);
			return;
		}
		botSay('¡Hola! 🐾 Te ayudo a crear tu listado en un par de minutos. Primero: ¿qué quieres publicar?').then(function () {
			showButtons(types.map(function (t) {
				return t.remaining === 0
					? { label: t.name + ' (tope alcanzado)', blocked: true, cls: 'ppv2-chat-option-alt' }
					: { label: t.name, type: t };
			}), function (opt) {
				if (opt.blocked) {
					botSay('De ese tipo ya tienes el máximo permitido para tu cuenta 🙈 Elige otro, o gestiona tus publicaciones desde "Mis publicaciones".', true);
					return;
				}
				userSay(opt.type.name);
				chatType = { slug: opt.type.slug, name: opt.type.name };
				answers = {};
				history = [];
				saveState();
				beginSchema();
			});
		});
	}

	function beginSchema() {
		fetchSchema(function () {
			buildQueue();
			queueIndex = -1;
			history = [];
			nextStep();
		});
	}

	function fetchSchema(onReady) {
		var done = botBusy();
		api('ppcl_fields', { type: chatType.slug }).done(function (res) {
			done();
			if (!res || !res.success) { if (!handleCommonErrors(res)) { failRetry(function () { fetchSchema(onReady); }); } return; }
			schema = res.data.schema || [];
			hoursSupported = !!res.data.hours;
			if (!schema.length) {
				botSay('Este tipo de listado no tiene campos configurados todavía. Avísale al administrador 🙏', true);
				return;
			}
			onReady();
		}).fail(function () { done(); failRetry(function () { fetchSchema(onReady); }); });
	}

	/** Añade campos a la cola con sus intros/puertas de grupo. */
	function pushFields(fields, withGates) {
		var byGroup = {};
		fields.forEach(function (f) { (byGroup[f.group] = byGroup[f.group] || []).push(f); });
		Object.keys(byGroup).forEach(function (g) {
			var list = byGroup[g];
			var allOptional = list.every(function (f) { return !f.required; });
			if (withGates && allOptional && list.length >= 3) {
				queue.push({ gate: true, group: g, groupTitle: list[0].groupTitle, count: list.length });
			} else if (list.length >= 2) {
				queue.push({ intro: true, group: g, groupTitle: list[0].groupTitle });
			}
			list.forEach(function (f) { queue.push({ field: f, group: g }); });
		});
	}

	/** Cola del MODO EXPRÉS: esenciales → horarios → puerta de extras. */
	function buildQueue() {
		queue = [];
		pushFields(schema.filter(function (f) { return f.essential; }), false);
		if (hoursSupported) { queue.push({ hours: true, group: '_hours' }); }
		var extras = schema.filter(function (f) { return !f.essential; });
		if (extras.length) { queue.push({ extrasGate: true, count: extras.length }); }
	}

	function presentCurrent() {
		if (queueIndex >= queue.length) { hideProgress(); showSummary(); return; }
		var step = queue[queueIndex];

		if (step.intro) {
			botSay('Vamos con "' + step.groupTitle + '" 📋', true);
			nextStep();
			return;
		}
		if (step.gate) {
			hideProgress();
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
		if (step.extrasGate) {
			hideProgress();
			botSay('¡Lo esencial está listo! 🎉 ¿Quieres afinar detalles? (' + step.count + ' datos opcionales: precios, redes sociales, video…) Siempre podrás completarlos después en el formulario.').then(function () {
				showButtons([
					{ label: '✨ Afinar detalles', go: true },
					{ label: 'No, ver el resumen', go: false, cls: 'ppv2-chat-option-primary' }
				], function (opt) {
					userSay(opt.label);
					if (opt.go) {
						var extras = schema.filter(function (f) { return !f.essential; });
						var resto = queue.slice(queueIndex + 1);
						queue = queue.slice(0, queueIndex + 1);
						pushFields(extras, true);
						queue = queue.concat(resto);
					}
					nextStep();
				});
			});
			return;
		}
		updateProgress();
		if (step.hours) {
			runHoursStep(false, null);
			return;
		}
		runFieldStep(step.field, false, null);
	}

	function nextStep() {
		if (returnToSummary) { returnToSummary = false; hideProgress(); showSummary(); return; }
		queueIndex++;
		presentCurrent();
	}

	/**
	 * Presenta un campo. after: qué hacer al terminar (null = avanzar).
	 * Cuando after es null, el índice actual se registra para "deshacer".
	 */
	function runFieldStep(field, editing, after) {
		var stepIdx = queueIndex;
		botSay(questionFor(field)).then(function () {
			var onDone = function (value, label) {
				setAnswer(field.key, value, label);
				if (after) { after(); return; }
				if (history[history.length - 1] !== stepIdx) { history.push(stepIdx); }
				nextStep();
			};
			var withUndo = !after && !returnToSummary;
			switch (field.kind) {
				case 'terms':        return runTermsStep(field, onDone, withUndo);
				case 'options':      return runOptionsStep(field, onDone, withUndo);
				case 'multioptions': return runMultiOptionsStep(field, onDone);
				case 'boolean':      return runBooleanStep(field, onDone, withUndo);
				case 'image':        return runImagesStep(field, onDone, false, withUndo);
				case 'images':       return runImagesStep(field, onDone, true, withUndo);
				default:
					if (field.key === '_whatsapp' && answers._phone && answers._phone.value) {
						return runWhatsappStep(field, onDone, withUndo);
					}
					return showTextInput(field, onDone, withUndo);
			}
		});
	}

	function runTermsStep(field, onDone, withUndo) {
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
		}, withUndo);
	}

	function runOptionsStep(field, onDone, withUndo) {
		var options = Object.keys(field.options).map(function (k) {
			return { label: field.options[k], val: k };
		});
		if (!field.required) { options.push({ label: 'Omitir', skip: true, cls: 'ppv2-chat-option-alt' }); }
		showButtons(options, function (opt) {
			if (opt.skip) { userSay('(omitido)'); onDone('', '—'); return; }
			userSay(opt.label);
			onDone(opt.val, opt.label);
		}, withUndo);
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

	function runBooleanStep(field, onDone, withUndo) {
		showButtons([
			{ label: 'Sí', val: true, cls: 'ppv2-chat-option-primary' },
			{ label: 'No', val: false }
		], function (opt) {
			userSay(opt.label);
			onDone(opt.val ? true : '', opt.label);
		}, withUndo);
	}

	function runWhatsappStep(field, onDone, withUndo) {
		var phone = answers._phone.value;
		showButtons([
			{ label: 'Es el mismo teléfono', val: phone },
			{ label: 'Es otro número', other: true },
			{ label: 'No tengo WhatsApp', val: '' }
		], function (opt) {
			userSay(opt.label);
			if (opt.other) { showTextInput(field, onDone); return; }
			onDone(opt.val, opt.val || '—');
		}, withUndo);
	}

	/* --------------------------- Horarios ------------------------------ */

	/** '8', '8:30', '8 am', '6:30 pm', '18:00' → 'HH:MM' 24 h (o null). */
	function parseTime(text, isClosing) {
		var m = /^\s*(\d{1,2})(?:[:.](\d{2}))?\s*(a\.?\s*m\.?|p\.?\s*m\.?)?\s*$/i.exec(text);
		if (!m) { return null; }
		var h = parseInt(m[1], 10);
		var min = m[2] ? parseInt(m[2], 10) : 0;
		if (h > 23 || min > 59) { return null; }
		var mer = m[3] ? m[3].toLowerCase().replace(/[^apm]/g, '') : '';
		if (mer === 'pm' && h < 12) { h += 12; }
		if (mer === 'am' && h === 12) { h = 0; }
		// Sin am/pm: al abrir asumimos mañana; al cerrar, tarde/noche.
		if (!mer && h >= 1 && h <= 11 && isClosing) { h += 12; }
		return (h < 10 ? '0' : '') + h + ':' + (min < 10 ? '0' : '') + min;
	}

	function fmt12(hhmm) {
		var p = hhmm.split(':');
		var h = parseInt(p[0], 10);
		var suf = h >= 12 ? 'pm' : 'am';
		var h12 = h % 12; if (h12 === 0) { h12 = 12; }
		return h12 + ':' + p[1] + ' ' + suf;
	}

	function runHoursStep(editing, after) {
		var stepIdx = queueIndex;
		var finish = function (value, label) {
			setAnswer(HOURS_KEY, value, label);
			if (after) { after(); return; }
			if (history[history.length - 1] !== stepIdx) { history.push(stepIdx); }
			nextStep();
		};
		botSay('¿Qué días atiendes? Marca los días y toca Listo 🗓️').then(function () {
			clearInputArea();
			var selected = {};
			var $wrap = $('<div>').addClass('ppv2-chat-options');
			DAY_ORDER.forEach(function (d) {
				$('<button>').attr('type', 'button').addClass('ppv2-chat-option')
					.text(DAY_LABELS[d])
					.on('click', function () {
						selected[d] = !selected[d];
						$(this).toggleClass('ppv2-chat-option-selected', selected[d]);
					})
					.appendTo($wrap);
			});
			var $actions = $('<div>').addClass('ppv2-chat-options ppv2-chat-options-actions');
			$('<button>').attr('type', 'button').addClass('ppv2-chat-option').text('Lun a Vie')
				.on('click', function () {
					['monday','tuesday','wednesday','thursday','friday'].forEach(function (d) { selected[d] = true; });
					$wrap.children().each(function (i) {
						$(this).toggleClass('ppv2-chat-option-selected', !!selected[DAY_ORDER[i]]);
					});
				}).appendTo($actions);
			$('<button>').attr('type', 'button').addClass('ppv2-chat-option ppv2-chat-option-primary').text('✔ Listo')
				.on('click', function () {
					var days = DAY_ORDER.filter(function (d) { return selected[d]; });
					if (!days.length) { botSay('Marca al menos un día 🙂', true); return; }
					userSay(days.map(function (d) { return DAY_LABELS[d]; }).join(', '));
					askOpenTime(days);
				}).appendTo($actions);
			$('<button>').attr('type', 'button').addClass('ppv2-chat-option ppv2-chat-option-alt').text('Omitir')
				.on('click', function () {
					userSay('(omitido)');
					finish('', '—');
				}).appendTo($actions);
			$inputArea.append($wrap, $actions);
			if (!after) { addUndoLink(); }
			scrollToBottom();
		});

		function askOpenTime(days) {
			botSay('¿A qué hora abres? (ej: 8:00 am)').then(function () {
				timeInput(false, function (open) {
					botSay('¿Y a qué hora cierras? (ej: 6:00 pm)').then(function () {
						timeInput(true, function (close) {
							var label = days.map(function (d) { return DAY_LABELS[d]; }).join(', ')
								+ ' · ' + fmt12(open) + ' – ' + fmt12(close);
							finish({ days: days, open: open, close: close }, label);
						});
					});
				});
			});
		}

		function timeInput(isClosing, cb) {
			clearInputArea();
			var $field = $('<input type="text">').addClass('ppv2-chat-field')
				.attr({ placeholder: isClosing ? 'Ej: 6:00 pm' : 'Ej: 8:00 am', inputmode: 'text' });
			var $send = $('<button>').attr('type', 'button').addClass('ppv2-chat-send').text('Enviar');
			$inputArea.append($('<div>').addClass('ppv2-chat-inputrow').append($field, $send));
			function submit() {
				var t = parseTime($field.val(), isClosing);
				if (!t) {
					botSay('No entendí la hora 🙈 Escríbela como "8:00 am" o "18:00".', true);
					return;
				}
				userSay(fmt12(t));
				cb(t);
			}
			$send.on('click', submit);
			$field.on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); submit(); } });
			$field.trigger('focus');
		}
	}

	/* --------------------------- Imágenes ------------------------------ */

	function runImagesStep(field, onDone, multiple, withUndo) {
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
		if (withUndo) { addUndoLink(); }
		refresh();
		scrollToBottom();
	}

	/* --------------------------- Resumen ------------------------------- */

	function showSummary() {
		clearInputArea();
		hideProgress();
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
			var h = answers[HOURS_KEY];
			if (h && h.value && String(h.label) !== '—') {
				$('<div>').addClass('ppv2-chat-summary-row')
					.append($('<strong>').text(HOURS_LABEL + ': '))
					.append(document.createTextNode(h.label))
					.appendTo($card);
			}
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
			if (hoursSupported) { options.push({ label: HOURS_LABEL, hours: true }); }
			options.push({ label: '↩ Volver al resumen', back: true, cls: 'ppv2-chat-option-alt' });
			showButtons(options, function (opt) {
				userSay(opt.label);
				if (opt.back) { showSummary(); return; }
				if (opt.restart) {
					botSay('Listo, empecemos de nuevo con otro tipo (tus respuestas de este se descartan).', true);
					chatType = null; answers = {}; schema = []; history = []; clearState();
					startConversation();
					return;
				}
				if (opt.hours) {
					runHoursStep(true, function () { showSummary(); });
					return;
				}
				runFieldStep(schema[opt.idx], true, function () { showSummary(); });
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
		Object.keys(answers).forEach(function (k) {
			if (k !== HOURS_KEY) { plain[k] = answers[k].value; }
		});
		var payload = { type: chatType.slug, fields: JSON.stringify(plain) };
		if (answers[HOURS_KEY] && answers[HOURS_KEY].value) {
			payload.hours = JSON.stringify(answers[HOURS_KEY].value);
		}
		api('ppcl_create', payload)
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

		$progressText = $('<span>');
		$progressBar = $('<i>');
		$progressWrap = $('<div>').addClass('ppv2-chat-progress').attr('hidden', true)
			.append($('<b>').addClass('ppv2-chat-progress-track').append($progressBar))
			.append($progressText);

		$messages = $('<div>').addClass('ppv2-chat-messages');
		$inputArea = $('<div>').addClass('ppv2-chat-input');
		$root.append($header, $progressWrap, $messages, $inputArea);
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
			if (readState()) { openEmbedded(); }
			return;
		}
		buildUI();
		started = true;
		startConversation();
	});

})(window.jQuery);
