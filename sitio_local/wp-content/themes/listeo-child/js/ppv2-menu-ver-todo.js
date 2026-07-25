/**
 * PPV2 — "Ver todo X" en los submenús de categorías de la Tienda.
 *
 * PROBLEMA QUE RESUELVE
 * El tema padre (listeo/js/custom.js, ~línea 180) cancela la navegación de
 * cualquier opción de menú que tenga submenú:
 *
 *     $("#mobile-nav .menu-item-has-children > a").on("click", function (ea) {
 *         ea.preventDefault();
 *     });
 *
 * Como en este sitio el menú de ESCRITORIO y el de MÓVIL son el mismo
 * <ul id="mobile-nav"> (no existe el #navigation clásico de Listeo; el header
 * usa .desktop-mmenu-trigger), esa regla deja sin salida a "Perro", "Gato",
 * "Alimento", etc.: solo se puede llegar a las ramas finales. Este script
 * inserta al principio de cada submenú un enlace "Ver todo <categoría>" que
 * apunta a la misma URL que el padre.
 *
 * POR QUÉ EN JS Y NO COMO ÍTEMS DE MENÚ REALES
 * Serían 15 ítems manuales (+135 filas en la BD, leídas en cada visita) y
 * habría que recordar crearlos al añadir categorías. Así: 0 bytes de HTML,
 * 0 consultas y se mantiene solo.
 *
 * ALCANCE
 * Solo ítems de taxonomía product_cat (.menu-item-object-product_cat). Eso
 * excluye a propósito el bloque "Directorio" (son categoria-listado y ya
 * tienen sus "Ver todo" creados a mano) y a "Tienda" (ya tiene el suyo).
 *
 * ORDEN DE EJECUCIÓN
 * Es indiferente si este script corre antes o después de listeo-custom:
 *  - Si corre DESPUÉS, inserta el enlace justo detrás del botón "Atrás".
 *  - Si corre ANTES, lo inserta al principio y el prepend del botón "Atrás"
 *    del tema lo deja igualmente por encima.
 * Aun así se encola con dependencia de 'listeo-custom' para que los handlers
 * de click del tema no lleguen a alcanzar este enlace nuevo.
 */
(function ($) {
	'use strict';

	var TEXTO = 'Ver todo';

	function insertarVerTodo() {
		$('#mobile-nav li.menu-item-object-product_cat.menu-item-has-children').each(function () {
			var $li  = $(this);
			var $sub = $li.children('ul.sub-menu').first();

			// Sin submenú, o ya procesado.
			if (!$sub.length || $sub.children('.ppv2-ver-todo').length) {
				return;
			}

			var $enlacePadre = $li.children('a').first();
			var href         = $enlacePadre.attr('href');
			var nombre       = $.trim($enlacePadre.text());

			if (!href || href === '#' || !nombre) {
				return;
			}

			// Si el submenú ya lleva un enlace a esa misma URL (p. ej. un
			// "Ver todo" creado a mano), no duplicamos.
			var yaExiste = $sub.children('li').children('a').filter(function () {
				return $(this).attr('href') === href;
			}).length;
			if (yaExiste) {
				return;
			}

			var $nuevo = $('<li class="menu-item ppv2-ver-todo"></li>');
			$('<a></a>')
				.attr('href', href)
				.text(TEXTO + ' ' + nombre)
				.appendTo($nuevo);

			var $volver = $sub.children('.sub-menu-back-btn');
			if ($volver.length) {
				$nuevo.insertAfter($volver);
			} else {
				$sub.prepend($nuevo);
			}
		});
	}

	$(function () {
		insertarVerTodo();
	});
})(jQuery);
