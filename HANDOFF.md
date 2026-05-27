# Handoff: Detalle de Listado V2 — estado al 2026-05-27

**Audiencia:** AntiGravity (continuación del desarrollo del detalle de listado).
**Último commit relevante:** `b4f4550 — Feat: fase mobile single-listing + ajustes finos UX y portabilidad`.

---

## 1. Resumen del estado

La plantilla **single-listing** (`/listado/{slug}/`) está rediseñada al ~95% según los prototipos Stitch
del directorio `Diseño/Stitch/`. El listado de prueba es **Naturalia**
(`http://parche-peludo-local.local/listado/naturalia/`).

Todo el código vive en el **tema hijo**:
- `sitio_local/wp-content/themes/listeo-child/style.css` (~3140 líneas)
- `sitio_local/wp-content/themes/listeo-child/functions.php` (~500 líneas)

**Regla inviolable:** no se toca el tema padre `listeo` (v2.0.37), ni Elementor, ni WooCommerce,
ni el core de WP. Toda personalización es CSS + JS-via-`wp_footer` en el tema hijo.

---

## 2. Componentes ya implementados

### Desktop (validado visualmente)
- **Cabecera:** meta-top (Servicio Verificado + estrellas + ❤ favorito), título, Prestado por + chip-logo dinámico, fila ciudad + dirección, tooltip del badge corregido.
- **Galería bento:** ratio 3:1 con overlay "+N fotos" sobre la última imagen.
- **Descripción:** título inyectado vía JS.
- **Planes y Precios:** título renombrado desde "Precios" vía JS.
- **Sobre {Nombre}:** sección extraída del overview de Listeo, título dinámico con el nombre del listado.
- **Preguntas frecuentes:** reordenado al final del bloque de contenido, título renombrado desde "FAQ".
- **Opiniones de Google:** Fase 7 con tarjetas blancas, hover translateY(-2px), estrellas marigold, summary colapsable con botón "Leer más opiniones / Cerrar opiniones", manejo de reviews sin texto (`<p>` vacío → `.ppv2-empty-p` colapsa el grid).
- **Añadir Opinión:** colapsable como pill, formulario completo restilado al expandir, sub-ratings en grid, botón "Enviar Mensaje" pill teal-parche.
- **Sidebar:** Reservar (boxed-widget), Enviar Mensaje (colapsable con chevron), Ver horarios (colapsable con badge "AHORA ABIERTO/CERRADO"). Sticky con `top: 96/128px` y `padding-top: 17px` para mantener alineación natural con el bloque del título.
- **Barra interna de Listeo** (Resumen | Galería | Precios...): oculta.

### Mobile (`@media max-width: 767px`) — implementado, falta validación visual fina
- Galería como **primer elemento** (flex column + `order: -1`).
- Galería **full-bleed** (100% del ancho, sin padding, sin border-radius).
- `.container.directorio` con padding-top/lateral en 0 (kill del espacio vacío entre menú y galería + lateral).
- Padding lateral **simétrico 8px** en secciones de texto (titlebar, listing-section, etc.).
- Sidebar sin sticky, baja como bloque plano debajo del contenido (`margin-top: 32px`).
- Tipografías y spacings calcados de las clases Tailwind del prototipo:
  - Título H1: 40px, font-weight 900, line-height 1.1
  - Section headings: 24px (text-2xl)
  - Sections separadas por 48px (mb-12)
  - Meta-top con gap-2 (8px)
- "Sobre {Nombre}" en 1 columna en mobile.
- Opiniones: summary apilado, toggle full-width, cards más compactas, botones bottom stack vertical.

**Verificación mobile pendiente:** Miguel pidió iterar lado-a-lado con el prototipo
(`Diseño/Stitch/pagina_servicio_detalle.html`). Última instrucción suya:
*"haz que la visualización en móvil sea exactamente igual al prototipo móvil"*.
Cualquier diferencia milimétrica encontrada al testear en DevTools mobile (iPhone 12 Pro o Pixel 5)
debería corregirse iterativamente.

---

## 3. Convenciones y patrones

### CSS
- Todas las reglas single-listing van scoped a **`.single-listing`** (clase del body que WP añade).
- Reglas mobile dentro de `@media (max-width: 767px)`.
- Valores en píxeles directos (sin clamp en mobile) para coincidir con Tailwind del prototipo.
- Tokens de marca: `--teal-parche`, `--teal-deep`, `--teal-soft`, `--teal-mist`, `--teal-ink`, `--ink`, `--ink-muted`, `--marigold`, `--coral`, `--bone`, `--paper`, `--r-md/lg/xl/2xl/pill`, `--shadow-sm/md/lg`.
- Cache-busting automático: `style.css` se enqueue con `filemtime()` como versión (en `functions.php`), así cada edición fuerza recarga sin tocar el header del tema.

### JavaScript (DOM tweaks)
- Todo el JS de tweaks está en **`ppv2_listing_header_reorder()`** dentro de `functions.php`, hooked a `wp_footer` con prioridad 100, guard `is_singular('listing')`.
- Patrones:
  - Inyecciones idempotentes con flag de clase (`.ppv2-overview-headline`, `.ppv2-add-review-btn`, etc.).
  - `MutationObserver` para contenido cargado vía AJAX (Google Reviews, marcado de `<p>` vacíos).
  - Función reutilizable `ppv2MakeWidgetCollapsible(widget, labels)` para sidebar widgets.

### Portabilidad
- **Crítico:** las reglas mobile responsive del Home V2 ahora usan `.ppv2-home-page` (no `.elementor-1555`). Miguel asignó esta clase en Elementor → Ajustes de Página → Avanzado → Clase CSS. Si se clona la Home, hay que volver a aplicar la clase manualmente.

---

## 4. Pendientes conocidos

1. **Validar mobile** lado-a-lado con el prototipo Stitch en DevTools (iPhone 12 Pro recomendado).
2. **Otros tipos de listing** (no solo Naturalia): el rediseño se desarrolló sobre un único listing de prueba; falta verificar que se ve bien con:
   - Listings sin reseñas de Google.
   - Listings sin horario configurado (caso `now-closed` o sin badge — JS ya tiene fallback que inyecta "Ahora Cerrado").
   - Listings con muchas o pocas fotos en la galería (overlay "+N fotos" se calcula dinámicamente).
   - Listings sin logo de proveedor (caso fallback ya manejado).
3. **Tipografía mobile responsive afina:** valores aproximados al prototipo Stitch; pueden necesitar ajuste fino tras revisión de Miguel.
4. **No tocado aún:**
   - Plantilla de búsqueda/directorio en mobile (solo desktop).
   - Dashboard de usuario logueado.
   - Páginas de checkout (WooCommerce + MercadoPago).
   - Forms de envío de mensajes (wpcf7).

---

## 5. Setup para continuar

1. **LocalWP siempre se arranca desde `Lanzar_Local.bat`** (redirige `%USERPROFILE%` a `C:\LocalWPData\AppData` por bug de MySQL con acentos en el nombre de usuario Windows).
2. La home publicada es **"Home V2 Pruebas"** (asignada en Ajustes → Lectura → Página de inicio).
3. Para hard-reload del CSS: `Ctrl+Shift+R`. El cache-busting por `filemtime()` ya bumpea el `?ver=` automáticamente al guardar `style.css`.
4. **Git workflow:**
   - Working branch: `main`.
   - Repo: `https://github.com/miguelangelrua1-design/parche-peludo-v2.git`.
   - Antes de un cambio grande: `git pull origin main`.
   - Commits descriptivos con prefijo `Feat:`, `Fix:`, `Doc:`, `Diseño:` (estilo de Miguel).

---

## 6. Referencias rápidas

| Necesito… | Mira en… |
|---|---|
| Tokens de marca y reglas globales | `style.css` líneas 1-200 |
| Variables CSS (colores, radios, sombras) | `style.css` líneas 20-110 |
| Estilos del directorio (tarjetas listing) | `style.css` líneas 200-300 |
| Hero + secciones del Home | `style.css` líneas 300-1000 |
| Detalle de listado (desktop) | `style.css` líneas 1000-2700 |
| Detalle de listado (mobile) | `style.css` líneas 2750-3140 |
| Manipulaciones DOM del listing | `functions.php` `ppv2_listing_header_reorder()` |
| Prototipos Stitch | `Diseño/Stitch/*.html` |
| Manual de identidad | `DESIGN.md` |
| Protocolos de colaboración | `INSTRUCTIVO-MIGRACION-V2.md` |

---

**Cualquier duda sobre por qué se hizo algo de determinada forma:** revisar el historial de commits con `git log --oneline` y leer el mensaje completo del commit relevante con `git show {hash}`. Los mensajes incluyen el "qué" y el "por qué" de cada cambio.
