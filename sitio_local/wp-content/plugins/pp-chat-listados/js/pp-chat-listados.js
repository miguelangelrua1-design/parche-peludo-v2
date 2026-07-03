/**
 * PPV2 — Chat de creación de listados (asistente guiado, sin IA).
 * Recolecta los datos del negocio conversando y crea el borrador vía AJAX
 * (action ppv2_chat_listado_create). Si el usuario no ha iniciado sesión,
 * guarda las respuestas en localStorage y las retoma tras el login.
 */
(function ($) {
	'use strict';

	var cfg = window.ppv2ChatListado || {};
	var STORAGE_KEY = 'ppv2ChatListado';

	var $root, $messages, $inputArea;
	var answers = {};          // {key: {value, label}}
	var stepIndex = 0;
	var returnToSummary = false;

	/* ------------------------------------------------------------------ */
	/* Guion de la conversación                                            */
	/* ------------------------------------------------------------------ */

	var STEPS = [
		{
			key: 'title',
			type: 'text',
			required: true,
			question: '¡Hola! 🐾 Soy el asistente de Parche Peludo y te ayudo a crear el listado de tu negocio en un par de minutos. Para empezar: ¿cómo se llama tu negocio?',
			questionEdit: '¿Cómo se llama tu negocio?',
			placeholder: 'Ej: Veterinaria Patitas Felices',
			summaryLabel: 'Nombre'
		},
		{
			key: 'category',
			type: 'terms',
			data: function () { return cfg.categories || []; },
			question: '¡Buen nombre! ¿En qué categoría encaja mejor lo que haces?',
			questionEdit: '¿En qué categoría encaja mejor tu negocio?',
			summaryLabel: 'Categoría'
		},
		{
			key: 'region',
			type: 'terms',
			data: function () { return cfg.regions || []; },
			question: '¿En qué ciudad o zona atiendes a los peludos?',
			questionEdit: '¿En qué ciudad o zona atiendes?',
			summaryLabel: 'Ubicación'
		},
		{
			key: 'address',
			type: 'text',
			optional: true,
			question: '¿Cuál es la dirección de tu negocio? Si atiendes a domicilio puedes omitir este paso.',
			questionEdit: '¿Cuál es la dirección?',
			placeholder: 'Ej: Calle 10 # 43A-25, local 2',
			summaryLabel: 'Dirección'
		},
		{
			key: 'phone',
			type: 'text',
			optional: true,
			inputMode: 'tel',
			question: '¿A qué número de teléfono te pueden contactar los tutores?',
			questionEdit: '¿Número de teléfono de contacto?',
			placeholder: 'Ej: 300 123 4567',
			summaryLabel: 'Teléfono'
		},
		{
			key: 'whatsapp',
			type: 'whatsapp',
			optional: true,
			question: '¿Tienes WhatsApp para recibir mensajes?',
			questionEdit: '¿Cuál es tu WhatsApp?',
			placeholder: 'Ej: 300 123 4567',
			summaryLabel: 'WhatsApp'
		},
		{
			key: 'email',
			type: 'text',
			optional: true,
			inputMode: 'email',
			validate: function (v) {
				return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v) ? '' : 'Mmm, ese correo no se ve completo. Revísalo e inténtalo de nuevo 🙂';
			},
			question: '¿Un correo electrónico de contacto? (también puedes omitirlo)',
			questionEdit: '¿Correo electrónico de contacto?',
			placeholder: 'Ej: hola@minegocio.com',
			summaryLabel: 'Correo'
		},
		{
			key: 'instagram',
			type: 'text',
			optional: true,
			question: '¿Tienes Instagram? Pega el enlace o escribe tu usuario.',
			questionEdit: '¿Tu Instagram?',
			placeholder: 'Ej: @patitasfelices',
			summaryLabel: 'Instagram'
		},
		{
			key: 'website',
			type: 'text',
			optional: true,
			inputMode: 'url',
			question: '¿Y sitio web?',
			questionEdit: '¿Tu sitio web?',
			placeholder: 'Ej: https://minegocio.com',
			summaryLabel: 'Sitio web'
		},
		{
			key: 'description',
			type: 'textarea',
			required: true,
			minLength: 40,
			question: 'Ya casi 🎉 Ahora cuéntales a los padres y madres de mascotas sobre tu negocio: qué haces, qué te hace especial, tu experiencia… (mínimo un par de frases)',
			questionEdit: 'Cuéntanos sobre tu negocio (será la descripción del listado).',
			placeholder: 'Ej: Somos una peluquería canina con 5 años de experiencia…',
			summaryLabel: 'Descripción'
		}
	];

	/* ------------------------------------------------------------------ */
	/* Utilidades de UI                                                    */
	/* ------------------------------------------------------------------ */

	function scrollToBottom() {
		$messages.stop().animate({ scrollTop: $messages[0].scrollHeight }, 250);
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
		}, 450 + Math.min(text.length * 4, 500));
		return dfd.promise();
	}

	function userSay(text) {
		var $bubble = $('<div>').addClass('ppv2-chat-msg ppv2-chat-user');
		$('<div>').addClass('ppv2-chat-bubble').text(text).appendTo($bubble);
		$messages.append($bubble);
		scrollToBottom();
	}

	function clearInputArea() {
		$inputArea.empty();
	}

	function showTextInput(step, onSubmit) {
		clearInputArea();
		var isArea = step.type === 'textarea';
		var $field = isArea
			? $('<textarea>').attr('rows', 3)
			: $('<input>').attr('type', 'text');
		$field.addClass('ppv2-chat-field')
			.attr('placeholder', step.placeholder || 'Escribe aquí…');
		if (step.inputMode) {
			$field.attr('inputmode', step.inputMode);
		}
		var $send = $('<button>').attr('type', 'button').addClass('ppv2-chat-send').text('Enviar');
		var $row = $('<div>').addClass('ppv2-chat-inputrow').append($field, $send);
		$inputArea.append($row);

		if (step.optional) {
			var $skip = $('<button>').attr('type', 'button').addClass('ppv2-chat-skip').text('Omitir este paso');
			$inputArea.append($skip);
			$skip.on('click', function () {
				userSay('(omitido)');
				onSubmit('');
			});
		}

		function submit() {
			var value = $.trim($field.val());
			if (!value) {
				if (step.optional) { return; }
				$field.addClass('ppv2-chat-field-error');
				setTimeout(function () { $field.removeClass('ppv2-chat-field-error'); }, 800);
				return;
			}
			if (step.minLength && value.length < step.minLength) {
				botSay('Un poquito más 🙏 — con dos o tres frases es suficiente para que los tutores te conozcan.', true);
				return;
			}
			if (step.validate) {
				var err = step.validate(value);
				if (err) { botSay(err, true); return; }
			}
			userSay(value);
			onSubmit(value);
		}

		$send.on('click', submit);
		$field.on('keydown', function (e) {
			if (e.key === 'Enter' && !isArea) { e.preventDefault(); submit(); }
			if (e.key === 'Enter' && isArea && (e.ctrlKey || e.metaKey)) { e.preventDefault(); submit(); }
		});
		$field.trigger('focus');
	}

	function showButtons(options, onPick) {
		clearInputArea();
		var $wrap = $('<div>').addClass('ppv2-chat-options');
		options.forEach(function (opt) {
			$('<button>')
				.attr('type', 'button')
				.addClass('ppv2-chat-option' + (opt.cls ? ' ' + opt.cls : ''))
				.text(opt.label)
				.on('click', function () { onPick(opt); })
				.appendTo($wrap);
		});
		$inputArea.append($wrap);
		scrollToBottom();
	}

	/* ------------------------------------------------------------------ */
	/* Pasos                                                               */
	/* ------------------------------------------------------------------ */

	function saveState() {
		try {
			window.localStorage.setItem(STORAGE_KEY, JSON.stringify({ answers: answers, ts: (typeof Date.now === 'function' ? Date.now() : 0) }));
		} catch (e) { /* almacenamiento no disponible */ }
	}

	function clearState() {
		try { window.localStorage.removeItem(STORAGE_KEY); } catch (e) { /* noop */ }
	}

	function setAnswer(key, value, label) {
		answers[key] = { value: value, label: label !== undefined ? label : value };
		saveState();
	}

	function nextStep() {
		if (returnToSummary) {
			returnToSummary = false;
			showSummary();
			return;
		}
		stepIndex++;
		if (stepIndex >= STEPS.length) {
			showSummary();
		} else {
			runStep(STEPS[stepIndex]);
		}
	}

	function runStep(step, editing) {
		var question = editing && step.questionEdit ? step.questionEdit : step.question;
		botSay(question).then(function () {
			if (step.type === 'terms') {
				runTermsStep(step);
			} else if (step.type === 'whatsapp') {
				runWhatsappStep(step);
			} else {
				showTextInput(step, function (value) {
					setAnswer(step.key, value, value || '—');
					nextStep();
				});
			}
		});
	}

	function runTermsStep(step) {
		var tree = step.data();
		if (!tree.length) {
			setAnswer(step.key, 0, '—');
			nextStep();
			return;
		}
		showButtons(
			tree.map(function (t) { return { label: t.name, term: t }; }),
			function (opt) {
				userSay(opt.term.name);
				var kids = opt.term.children || [];
				if (!kids.length) {
					setAnswer(step.key, opt.term.id, opt.term.name);
					nextStep();
					return;
				}
				botSay('¿Algo más específico dentro de ' + opt.term.name + '?').then(function () {
					var options = kids.map(function (k) { return { label: k.name, term: k }; });
					options.push({ label: 'Solo ' + opt.term.name, term: opt.term, cls: 'ppv2-chat-option-alt' });
					showButtons(options, function (sub) {
						userSay(sub.term.id === opt.term.id ? 'Solo ' + opt.term.name : sub.term.name);
						var label = sub.term.id === opt.term.id ? opt.term.name : opt.term.name + ' › ' + sub.term.name;
						setAnswer(step.key, sub.term.id, label);
						nextStep();
					});
				});
			}
		);
	}

	function runWhatsappStep(step) {
		var phone = answers.phone && answers.phone.value;
		if (!phone) {
			showTextInput(step, function (value) {
				setAnswer(step.key, value, value || '—');
				nextStep();
			});
			return;
		}
		showButtons([
			{ label: 'Es el mismo teléfono', val: phone },
			{ label: 'Es otro número', val: null },
			{ label: 'No tengo WhatsApp', val: '' }
		], function (opt) {
			userSay(opt.label);
			if (opt.val === null) {
				showTextInput(step, function (value) {
					setAnswer(step.key, value, value || '—');
					nextStep();
				});
				return;
			}
			setAnswer(step.key, opt.val, opt.val || '—');
			nextStep();
		});
	}

	/* ------------------------------------------------------------------ */
	/* Resumen, corrección y envío                                         */
	/* ------------------------------------------------------------------ */

	function summaryText() {
		var lines = [];
		STEPS.forEach(function (step) {
			var a = answers[step.key];
			if (a && a.value !== '' && a.value !== 0) {
				lines.push(step.summaryLabel + ': ' + a.label);
			}
		});
		return lines;
	}

	function showSummary() {
		clearInputArea();
		botSay('¡Esto va quedando muy bien! 🎉 Revisa el resumen de tu listado:').then(function () {
			var $card = $('<div>').addClass('ppv2-chat-summary');
			summaryText().forEach(function (line) {
				var parts = line.split(/: (.+)/);
				$('<div>').addClass('ppv2-chat-summary-row')
					.append($('<strong>').text(parts[0] + ': '))
					.append(document.createTextNode(parts[1] || ''))
					.appendTo($card);
			});
			$messages.append($card);
			scrollToBottom();
			showButtons([
				{ label: '✅ Crear mi listado', action: 'create', cls: 'ppv2-chat-option-primary' },
				{ label: '✏️ Corregir algo', action: 'fix' }
			], function (opt) {
				if (opt.action === 'create') {
					userSay('✅ Crear mi listado');
					createListing();
				} else {
					userSay('✏️ Corregir algo');
					showFixMenu();
				}
			});
		});
	}

	function showFixMenu() {
		botSay('Claro, ¿qué quieres corregir?').then(function () {
			var options = STEPS.map(function (step, i) {
				return { label: step.summaryLabel, idx: i };
			});
			options.push({ label: '↩ Volver al resumen', idx: -1, cls: 'ppv2-chat-option-alt' });
			showButtons(options, function (opt) {
				userSay(opt.label);
				if (opt.idx === -1) {
					showSummary();
					return;
				}
				returnToSummary = true;
				stepIndex = opt.idx;
				runStep(STEPS[opt.idx], true);
			});
		});
	}

	function loginButtons() {
		var $headerSignIn = $('a.sign-in').first();
		showButtons([
			{ label: '🔑 Iniciar sesión o crear cuenta', action: 'login', cls: 'ppv2-chat-option-primary' }
		], function () {
			saveState();
			if ($headerSignIn.length) {
				$headerSignIn.trigger('click');
			} else if (cfg.loginUrl) {
				window.location.href = cfg.loginUrl;
			}
		});
	}

	function createListing() {
		clearInputArea();
		if (!cfg.loggedIn) {
			botSay('¡Ya casi está! 🐶 Para guardar tu listado necesitas una cuenta (así podrás editarlo cuando quieras). Inicia sesión o regístrate — tus respuestas quedan guardadas y al volver solo confirmas.').then(loginButtons);
			return;
		}
		botSay('Creando tu listado…', true);
		var payload = {
			action: 'ppv2_chat_listado_create',
			nonce: cfg.nonce,
			title: answers.title ? answers.title.value : '',
			description: answers.description ? answers.description.value : '',
			category: answers.category ? answers.category.value : 0,
			region: answers.region ? answers.region.value : 0,
			address: answers.address ? answers.address.value : '',
			phone: answers.phone ? answers.phone.value : '',
			whatsapp: answers.whatsapp ? answers.whatsapp.value : '',
			email: answers.email ? answers.email.value : '',
			instagram: answers.instagram ? answers.instagram.value : '',
			website: answers.website ? answers.website.value : ''
		};
		$.post(cfg.ajaxUrl, payload)
			.done(function (res) {
				if (res && res.success) {
					clearState();
					botSay('¡Listo! 🎉 Tu borrador quedó creado. Ahora falta lo mejor: agregar fotos y tus horarios de atención. Al enviarlo, nuestro equipo lo revisará y lo publicará.').then(function () {
						showButtons([
							{ label: '📸 Completar mi listado (fotos y horarios)', url: res.data.continue_url, cls: 'ppv2-chat-option-primary' }
						], function (opt) {
							window.location.href = opt.url;
						});
					});
					return;
				}
				var code = res && res.data && res.data.code;
				if (code === 'login') {
					cfg.loggedIn = false;
					createListing();
					return;
				}
				botSay((res && res.data && res.data.message) || 'Algo no salió bien 😿. Inténtalo de nuevo en un momento.', true);
				retryButton();
			})
			.fail(function () {
				botSay('No pude conectarme con el sitio 😿. Revisa tu conexión e inténtalo de nuevo.', true);
				retryButton();
			});
	}

	function retryButton() {
		showButtons([{ label: '🔄 Reintentar', cls: 'ppv2-chat-option-primary' }], function () {
			createListing();
		});
	}

	/* ------------------------------------------------------------------ */
	/* Arranque                                                            */
	/* ------------------------------------------------------------------ */

	function restoreState() {
		try {
			var raw = window.localStorage.getItem(STORAGE_KEY);
			if (!raw) { return false; }
			var saved = JSON.parse(raw);
			if (!saved || !saved.answers || !saved.answers.title || !saved.answers.description) { return false; }
			answers = saved.answers;
			return true;
		} catch (e) {
			return false;
		}
	}

	$(function () {
		$root = $('#ppv2-chat-listado');
		if (!$root.length) { return; }

		var $header = $('<div>').addClass('ppv2-chat-header')
			.append($('<span>').addClass('ppv2-chat-header-icon').text('🐾'))
			.append($('<div>')
				.append($('<strong>').text('Asistente Parche Peludo'))
				.append($('<small>').text('Crea tu listado conversando')));
		$messages = $('<div>').addClass('ppv2-chat-messages');
		$inputArea = $('<div>').addClass('ppv2-chat-input');
		$root.append($header, $messages, $inputArea);

		if (restoreState()) {
			stepIndex = STEPS.length;
			botSay('¡Hola de nuevo! 👋 Guardé las respuestas de tu listado. Revísalas y, si todo está bien, lo creamos de una.').then(showSummary);
		} else {
			runStep(STEPS[0]);
		}
	});

})(window.jQuery);
