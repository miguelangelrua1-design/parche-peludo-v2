# Instructivo — Construcción del catálogo de Parche Peludo usando Laika como referente

> Guía técnica y funcional para replicar productos en la tienda WooCommerce de Parche Peludo
> tomando **Laika** (laika.com.co) como fuente de referencia, adaptando el contenido a la marca
> Parche Peludo y aplicando SEO correcto. Pensada para ejecutarse desde AntiGravity sobre el
> tema hijo `listeo-child` y un script publicador en el webroot.
>
> **Estado de referencia:** WordPress + WooCommerce, tema hijo `listeo-child`, SEO con **Rank Math
> 1.0.273**. Probado con 2 productos Royal Canin (local + producción) el 2026-07-04/05.

---

## 0. Principios (leer antes de empezar)

1. **Laika es REFERENTE, no fuente de copia.** La información de producto (nombre, marca,
   presentaciones, composición, ficha técnica) es común del distribuidor/marca y se puede usar como
   base fáctica. Pero **los textos de venta (título comercial y descripción) se REESCRIBEN** con la
   voz de Parche Peludo — nunca se copian literalmente (Google penaliza el contenido duplicado y hay
   riesgo de derechos sobre el texto redactado por Laika).
2. **Imágenes:** se reutilizan las de Laika (decisión de negocio: se consideran material de
   marca/producto sin derechos exclusivos de Laika). Se descargan a la biblioteca de medios propia.
3. **Precio:** se usa el precio **sin membresía** de Laika (`price.sale`) como punto de partida.
4. **Datos técnicos (composición, análisis garantizado, peso):** son fácticos → se pueden reproducir
   tal cual en la ficha técnica.
5. **Editar solo el tema hijo** (`listeo-child`) y el **plugin propio**; nunca el tema padre ni
   Listeo core. El publicador es un script temporal en el webroot que se **borra tras usarse**.

---

## 1. Fuente de datos — API interna de Laika

Laika es un frontend Next.js; los productos NO están en el HTML, se cargan por una API proxy pública
(sin login). Usar siempre el host del sitio (`https://laika.com.co`) con la ruta `/api/proxy/...` y
cabecera `User-Agent: Mozilla/5.0`. (El host directo `api.laika.com.co` responde 404.)

### 1.1 Endpoints

| Propósito | Endpoint | Devuelve |
|---|---|---|
| Búsqueda / listado | `GET /api/proxy/v1/products/search?q={texto}&pet={1\|2}&page={n}&limit={n}` | `{ products:[...], filters:[...] }` |
| Detalle de producto | `GET /api/proxy/v1/products/slug/{slug}` | Objeto completo del producto (ver 1.3) |
| Categorías raíz | `GET /api/proxy/v1/home/main-categories` | `{ "1": Perros, "2": Gatos }` |
| Árbol de categorías | `GET /api/proxy/v1/categories/web` | categorías/subcategorías |

- `pet`: `1` = Perro, `2` = Gato.
- El bloque `filters[].values[].count` de `search` da el total por faceta (marca, categoría…).
  Sirve para dimensionar el catálogo antes de recorrerlo.
- **Recorrer todo el catálogo:** paginar `search` por `pet` y por categoría/marca (con `page`+`limit`)
  hasta agotar; acumular slugs únicos; luego pedir el detalle de cada slug.

### 1.2 Cómo obtener el slug

Cada item de `products[]` en `search` trae `slug`. También es el último segmento de la URL de la
ficha en laika.com.co (ej. `laika.com.co/royal-canin-mini-puppy` → slug `royal-canin-mini-puppy`).

### 1.3 Campos del detalle (`/products/slug/{slug}`) y a qué mapean en Parche Peludo

| Campo Laika | Contenido | Uso en Parche Peludo |
|---|---|---|
| `name` | Nombre (ej. "Royal Canin - Mini Puppy") | Base del **título** (se normaliza, ver §2.1) |
| `description` | Descripción corta/comercial | Insumo para reescribir la **descripción** |
| `feature` | Características / composición / análisis | **Ficha técnica** (composición, análisis) |
| `benefit` | Beneficios | Insumo para descripción + fila "Beneficios clave" |
| `brand.name` | Marca | Atributo `pa_marca` + ficha + título/SEO |
| `pet.name` | Perro / Gato | Atributo `pa_especie` + categoría |
| `category.name`, `subcategory.name` | Categoría / subcategoría | Mapear a `product_cat` propia |
| `image.url` | Imagen principal | Imagen destacada |
| `price.sale` | **Precio sin membresía** | Precio del producto/variante |
| `price.final` | Precio con promoción | (referencia; no se publica) |
| `price.priceForMember` | Precio de miembro | (ignorar) |
| `references[]` | **Variantes** (presentaciones) | Variaciones WooCommerce |
| `references[].sku` | Código de barras / SKU | SKU de la variación |
| `references[].name` | Nombre de la presentación (ej. "2 KG", "ÚNICA") | Valor del atributo de variación + `pa_peso` |
| `references[].weight` | Peso (kg) | Peso de la variación / ficha |
| `references[].stock` | Stock | Stock de la variación |
| `references[].price.sale` | Precio sin membresía de esa presentación | Precio de la variación |
| `references[].images[]` | Imágenes de la presentación | Galería + imagen de la variación |

---

## 2. Reconstrucción del contenido (título, descripción, ficha técnica)

> **Regla de oro:** el contenido comercial lo **redacta el modelo** (no el script) por cada producto,
> adaptando y reestructurando lo de Laika. El script solo publica el HTML ya redactado. Así se
> garantiza criterio editorial y se evita el duplicado.

### 2.1 Título del producto

- Partir de `name` y **normalizar**: quitar el guion decorativo y espacios dobles.
  `"Royal Canin - Mini Puppy"` → **`Royal Canin Mini Puppy`**.
- Formato: `{Marca} {Línea/Producto}`. Mantener la marca al inicio (bien para SEO y para el filtro
  de marca).
- Sin ALL CAPS, sin signos innecesarios. Capitalización normal.

### 2.2 Descripción (post_content) — voz Parche Peludo

**Voz de marca:** cálida, cercana, colombiana; trata al animal como "tu peludo / tu cachorro / tu
michi / tu lomito"; comunidad ("En el Parche…"). Nunca sonar a ficha corporativa copiada.

**Estructura recomendada (HTML):**
1. **Párrafo de apertura** con gancho emocional + a quién está dirigido el producto (especie, etapa,
   tamaño de raza). Reformular la idea de `description`, no copiarla.
2. **1–2 párrafos** de beneficios/uso, combinando y parafraseando `description` + `benefit`
   (digestión, defensas, piel/pelaje, etc.).
3. **Lista `<ul>` "¿Por qué nos encanta?"** con 3–5 bullets de beneficios concretos.

**Reglas anti-duplicado:**
- Reescribir con otras palabras y otro orden; no reutilizar frases completas de Laika.
- Que la descripción sea **sustancialmente distinta** al original y aporte voz propia.
- Consistencia terminológica (ej. "croqueta", "peludito", "el Parche").

### 2.3 Descripción corta (post_excerpt)

- 1–2 frases (≈120–160 caracteres) que resuman el producto y su beneficio principal.
- **Importante para SEO:** la plantilla de metadescripción de Rank Math es `%excerpt%`, así que si no
  se define una metadescripción propia, **este texto será la metadescripción** (ver §4.2). Redactarlo
  pensando en eso: atractivo, con el beneficio clave.

### 2.4 Ficha técnica (pestaña propia)

- Se guarda como HTML de tabla en el meta **`_pp_ficha_tecnica`** del producto.
- La pestaña "Ficha técnica" ya existe en `listeo-child/functions.php`
  (`pp_tab_ficha_tecnica` / `pp_tab_ficha_tecnica_contenido`, prioridad 15, entre Descripción e
  Información adicional). Si el producto no tiene ese meta, la pestaña no aparece.
- **Contenido** (tabla clave→valor). Campos estándar:
  `Marca, Especie, Raza (si aplica), Etapa de vida, Tamaño de raza (si aplica), Tipo de alimento,
  Presentaciones, Peso, Energía metabolizable (si aplica), Análisis garantizado (si aplica),
  Beneficios clave, Composición`.
- La **composición** y el **análisis garantizado** salen casi literales de `feature` (son datos
  fácticos). Beneficios clave = resumen corto de `benefit`.
- NO repetir la tabla dentro de la descripción (evita duplicado visual).

---

## 3. Replicación de imágenes

### 3.1 Origen y descarga

- Las imágenes están en el CDN `https://static.laika.digital/products*/...` (cargan sin bloqueo
  hotlink). Se descargan a la biblioteca de medios propia con:
  ```php
  require_once ABSPATH . 'wp-admin/includes/media.php';
  require_once ABSPATH . 'wp-admin/includes/file.php';
  require_once ABSPATH . 'wp-admin/includes/image.php';
  $att_id = media_sideload_image( $url, $product_id, $titulo_desc, 'id' ); // devuelve attachment ID
  ```
- **Caché por URL dentro del producto:** mantener un mapa `url => att_id` para no descargar dos veces
  la misma foto (la imagen principal suele repetirse en la galería y en una variante).

### 3.2 Asignación

- **Imagen destacada:** `$product->set_image_id( $att_id_principal )` (usar `image.url`).
- **Galería:** `$product->set_gallery_image_ids( [ ...att_ids ] )` (usar las de la presentación
  principal; 4–8 imágenes es razonable).
- **Imagen por variación:** `$variation->set_image_id( $att_id )` (primera imagen de
  `references[].images[]`).

### 3.3 ⚠️ Deduplicación al escalar (pendiente de implementar)

`media_sideload_image` **NO deduplica** contra la biblioteca: cada reejecución vuelve a subir las
fotos → duplicados. Al escalar a muchos productos, antes de subir:
- Calcular un hash/nombre canónico de la URL (ej. el nombre de archivo de Laika) y **buscar si ya
  existe** un attachment con ese `_source_url`/nombre; si existe, **reutilizar su ID** en vez de
  volver a descargar.
- Guardar en meta del attachment la URL de origen (ej. `update_post_meta($att_id,'_pp_src_url',$url)`)
  para poder buscarlo en corridas futuras.

---

## 4. SEO / metainformación (Rank Math)

> Rank Math guarda la meta por producto en **postmeta**. Hoy los productos usan las plantillas por
> defecto (`pt_product_title = %title% %sep% %sitename%`, `pt_product_description = %excerpt%`). Para
> control total y evitar metadescripciones autogeneradas, el publicador debe **escribir meta propia**.

### 4.1 Meta título (`rank_math_title`)

- **Clave postmeta:** `rank_math_title`.
- **Longitud:** ≤ **60 caracteres** (para que no se corte en Google).
- **Fórmula:** `{Nombre del producto} {Presentación si cabe} - {Marca} | Parche Peludo`
  - Ej.: `Royal Canin Mini Puppy - Alimento Cachorro | Parche Peludo` (58).
- Incluir la **palabra clave principal al inicio** (nombre + marca). No repetir palabras.
- Se pueden usar variables de Rank Math (`%title%`, `%sep%`, `%sitename%`) pero para control fino es
  preferible texto literal calculado por el modelo.
  ```php
  update_post_meta( $pid, 'rank_math_title', $meta_titulo ); // texto plano ≤60
  ```

### 4.2 Meta descripción (`rank_math_description`)

- **Clave postmeta:** `rank_math_description`.
- **Longitud:** **150–160 caracteres**, única por producto.
- **Estructura:** beneficio principal + para quién es + llamada a la acción suave.
  - Ej.: `Alimento Royal Canin Mini Puppy para cachorros de razas pequeñas. Refuerza defensas y
    cuida su digestión. Cómpralo online en Parche Peludo. 🐾` (ajustar a ≤160, sin emoji si se
    prefiere).
- Debe contener la palabra clave de forma natural. No repetir literal el meta título.
  ```php
  update_post_meta( $pid, 'rank_math_description', $meta_desc ); // 150-160 chars
  ```
- Si NO se define, Rank Math usa `%excerpt%` (la descripción corta). Por eso, como mínimo, la
  **descripción corta debe estar bien redactada** (§2.3).

### 4.3 Palabra clave objetivo (`rank_math_focus_keyword`)

- **Clave postmeta:** `rank_math_focus_keyword`.
- Valor = término de búsqueda principal en minúsculas (ej. `royal canin mini puppy`).
- Debe aparecer en: título del producto, meta título, meta descripción, primer párrafo de la
  descripción, y al menos un `alt` de imagen.
  ```php
  update_post_meta( $pid, 'rank_math_focus_keyword', $focus_kw );
  ```

### 4.4 Texto ALT de las imágenes (`_wp_attachment_image_alt`)

- **Clave postmeta del ATTACHMENT** (no del producto): `_wp_attachment_image_alt`.
- **Hoy está vacío** en los productos publicados → hay que rellenarlo al subir cada imagen.
- **Formato:** describir la imagen de forma natural, incluyendo producto + marca + (presentación si
  aplica). No hacer "keyword stuffing".
  - Imagen principal: `Royal Canin Mini Puppy alimento para cachorros de razas pequeñas`
  - Variante: `Royal Canin Mini Puppy presentación 4 kg`
  - Si hay varias fotos iguales, variar levemente el alt (empaque, croqueta, tabla nutricional…).
  ```php
  update_post_meta( $att_id, '_wp_attachment_image_alt', $alt_texto );
  ```

### 4.5 Datos para rich snippets (schema de producto)

WooCommerce + Rank Math generan automáticamente el **schema Product** (para estrellas/precio en
Google) **si el producto tiene** precio, stock, SKU y marca. Garantizar:
- Precio y stock por variación (ya se hace).
- **SKU** por variación (ya se toma de `references[].sku`).
- **Marca** como atributo global `pa_marca` (ya se asigna) → Rank Math la usa como `brand` del schema.
- Categoría asignada.
- (Opcional) Rank Math permite fijar la marca del snippet con
  `update_post_meta($pid,'rank_math_snippet_product_brand', $marca);` si se quiere forzar.

### 4.6 URL / slug

- Slug limpio basado en la palabra clave (ej. `royal-canin-mini-puppy`). Se define con
  `$product->set_slug()`. Evitar slugs con números o palabras de relleno.

---

## 5. Estructura del producto en WooCommerce (resumen técnico)

Producto **variable** por cada ficha de Laika con >1 presentación (o simple si es 1 sola):

| Elemento | Cómo | Fuente |
|---|---|---|
| Tipo | `WC_Product_Variable` (o `WC_Product_Simple`) | nº de `references` |
| Nombre / slug | `set_name` / `set_slug` | §2.1, §4.6 |
| Descripción / corta | `set_description` / `set_short_description` | §2.2, §2.3 |
| Estado | `set_status('publish')` o `'draft'` para revisión | decisión |
| Categoría | `set_category_ids([term])` por slug `product_cat` | `category`/`subcategory` |
| Atributo de variación | `Presentación` (local, visible, `variation=true`) — ⚠️ ver §5.2, el nombre DEBE quedar en UTF-8 limpio | `references[].name` |
| Atributos filtrables | globales `pa_marca`, `pa_especie`, `pa_etapa-de-vida`, `pa_tipo-de-alimento`, `pa_peso` (visible=false, variation=false) | marca/especie + inferidos |
| Variaciones | `WC_Product_Variation` con `set_regular_price` (=`price.sale`), `set_sku`, `set_manage_stock(true)`, `set_stock_quantity`, `set_image_id` | `references[]` |
| Imágenes | destacada + galería + por variación | §3 |
| Ficha técnica | meta `_pp_ficha_tecnica` (HTML tabla) | §2.4 |
| SEO | `rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`, alt por imagen | §4 |

### 5.1 Atributos filtrables (para el panel de filtros nativo)

- Se crean/usan como **atributos globales** (taxonomías `pa_*`) con `wc_create_attribute` +
  `register_taxonomy` en caliente, y se asignan términos con `wp_set_object_terms`.
- **Marca** y **Especie** vienen directos de Laika. **Etapa de vida** y **Tipo de alimento** NO son
  campos exactos de Laika → se **infieren** del nombre/categoría (reglas abajo). **Peso** = de
  `references[].name`/`weight`.
- Los widgets "Filtrar productos por atributo" del sidebar `sidebar-shop` ya muestran estos
  atributos; el filtro nativo se aplica solo (params `filter_<slug>`), sin código propio.

**Reglas de inferencia (definir y mantener consistentes):**
- **Etapa de vida:** `Cachorro` si el nombre/línea incluye puppy/cachorro/junior; `Senior` si
  senior/mature/+7; si no, `Adulto`.
- **Tipo de alimento:** `Alimento húmedo` si lata/pouch/paté/húmedo; `Snack` si snack/premio/treat;
  si no y es categoría Alimento, `Alimento seco`.
- **Peso:** normalizar `references[].name` a `{n} kg` / `{n} g` (ej. "2 KG" → "2 kg";
  slug de término: `2-kg`, `1-5-kg`).

### 5.2 ⚠️ El atributo "Presentación" y la codificación (BUG REAL 2026-07-13 — leer sí o sí)

El atributo de variación **"Presentación"** es un atributo LOCAL (no taxonomía). WooCommerce guarda
su clave interna como `sanitize_title($nombre)`:

- Nombre `Presentación` (UTF-8 limpio) → clave `presentacion` ✅ → casa con las variaciones (que se
  crean con `set_attributes(['presentacion' => ...])`) → las pastillas de presentación salen en el
  PLP y el "Agregar" por AJAX funciona.
- Nombre `PresentaciÃ³n` (doble-codificado / mojibake) → clave `presentacia%c2%b3n` ❌ → **NO** casa
  con las variaciones → `wc_get_product_variation_attributes()` devuelve vacío → en el PLP el
  producto sale **sin pastillas** y con botón "Ver opciones", y el add-to-cart falla con
  `{error:true}`. Este fue exactamente el bug del lote del 2026-07-13.

**Causa raíz:** el mojibake NO viene del payload (el payload solo trae los valores tipo `"6 lb"`).
Viene de **cómo se GUARDA el archivo publicador PHP**: si el `.php` se escribe con codificación
equivocada (típico en Windows: `Get-Content | Set-Content` de PowerShell sin `-Encoding utf8`, o
copiar/pegar que re-codifica), el literal `'Presentación'` del código fuente queda como
`'PresentaciÃ³n'` (bytes `c3 83 c2 b3`) y de ahí en adelante todo hereda la clave rota.

**REGLA OBLIGATORIA al escribir el publicador (a prueba de codificación):** NO poner el acento como
literal en el código. Usar el escape Unicode de PHP, que produce los bytes UTF-8 correctos sin
importar cómo se guarde el archivo:

```php
// ✅ CORRECTO — inmune a la codificación del archivo (el fuente es ASCII puro):
$presentacion_attr->set_name("Presentaci\u{00F3}n");   // "Presentación"
// y en la ruta REST de producción:
$attr = array('name' => "Presentaci\u{00F3}n", 'variation' => true, /* ... */);

// ❌ EVITAR — un guardado con codificación equivocada lo convierte en 'PresentaciÃ³n':
$presentacion_attr->set_name('Presentación');
```

(La clave de la variación se sigue fijando literal en ASCII: `set_attributes(['presentacion' => $v['presentation']])`.)

Si por algún motivo se deja el literal acentuado, **guardar el `.php` SIEMPRE como UTF-8 sin BOM**
(en PowerShell: `Set-Content -Encoding utf8`; nunca leer+reescribir el archivo con métodos que
re-codifican). Y verificar tras publicar (ver §7, chequeo nuevo).

---

## 6. Flujo de ejecución (publicador)

1. **Capturar** de Laika (search → slugs → detalle) los productos objetivo (por marca/categoría/lote).
2. **Redactar** (modelo) por producto: título normalizado, descripción HTML (voz Parche Peludo),
   descripción corta, ficha técnica (tabla), meta título, meta descripción, focus keyword, y alt por
   imagen.
3. **Publicar** con un script PHP temporal en el webroot (protegido con `?token=`), reejecutable por
   slug (actualiza si existe). El script:
   - crea/actualiza el producto variable, categoría, atributos (Presentación + globales filtrables),
   - sube imágenes (con caché por URL) y **fija el `alt` de cada una**,
   - crea variaciones (precio/sku/stock/imagen),
   - guarda `_pp_ficha_tecnica` y las metas de Rank Math,
   - `WC_Product_Variable::sync($pid)` + `wc_delete_product_transients($pid)` + purga de caché.
4. **Verificar** (§7) y **borrar** el script del servidor.

**Entorno / gotchas:**
- El PC no tiene Node ni Python → usar `curl` (bash) o PowerShell para las llamadas HTTP.
- Local: WooCommerce REST API NO sirve (http, LocalWP no pasa el header Authorization) → usar el
  script en webroot. En producción (https) sí serviría la REST API.
- Purga de caché tras publicar: `do_action('litespeed_purge_all')`.
- Cache-bust de URLs en pruebas: usar `?nc=` (NO `?m=`, que es query var reservada de WP → 404).
- **Codificación del publicador (crítico):** el atributo "Presentación" debe quedar en UTF-8 limpio
  o los productos variables salen sin pastillas y el add-to-cart falla. Escribir el nombre con
  `"Presentaci\u{00F3}n"` (escape Unicode, no literal acentuado) — ver §5.2. Al guardar el `.php`
  usar UTF-8 sin BOM; en PowerShell `Set-Content -Encoding utf8` y nunca reescribirlo con métodos
  que re-codifiquen.

---

## 7. Checklist de calidad (QA) por producto

**Contenido**
- [ ] Título normalizado, con marca al inicio, sin ALL CAPS.
- [ ] Descripción reescrita con voz Parche Peludo (no copia de Laika), con lista de beneficios.
- [ ] Descripción corta atractiva (sirve de metadescripción de respaldo).
- [ ] Ficha técnica completa en su pestaña (marca, especie, etapa, tipo, presentaciones, peso,
      composición/análisis, beneficios).

**Comercial**
- [ ] **Presentaciones OK (anti-mojibake):** en el PLP el producto variable muestra las **pastillas**
      de presentación (ej. "6 lb / 18 lb") y el botón dice "Agregar", no "Ver opciones". Verificación
      técnica: el meta `_product_attributes` del producto tiene la clave `presentacion` (NO
      `presentacia%c2%b3n`) y `name` = `Presentación` (NO `PresentaciÃ³n`). Ver §5.2.
      SQL rápido: `SELECT post_id FROM wp_postmeta WHERE meta_key='_product_attributes' AND meta_value LIKE BINARY '%presentacia%';` → debe devolver **0 filas**.
- [ ] **Sin duplicados / variable no vacío:** el producto variable quedó con **>0 variaciones**
      (un producto variable con 0 variaciones sale como "Ver opciones" sin pastillas). Si al publicar
      el slug volvió con sufijo `-2`/`-3`, es que se creó un DUPLICADO en vez de actualizar el
      existente → localizar y borrar la copia vacía, conservar la buena. Una corrida interrumpida
      (timeout al subir imágenes, error a mitad) puede dejar ese "variable vacío"; reejecutar el
      publicador POR EL MISMO SLUG debe ACTUALIZAR, no crear otro. SQL de auditoría: buscar productos
      variable con 0 hijos `product_variation`, y títulos repetidos con slugs `-2`.
- [ ] Variantes con precio (sin membresía), SKU y stock correctos.
- [ ] Categoría correcta; atributos filtrables asignados (marca, especie, etapa, tipo, peso).
- [ ] Imágenes: destacada + galería + por variación, sin duplicados en la biblioteca.

**SEO**
- [ ] `rank_math_title` ≤ 60 chars, con palabra clave al inicio.
- [ ] `rank_math_description` 150–160 chars, única, con palabra clave y CTA.
- [ ] `rank_math_focus_keyword` definida y presente en título, meta, 1er párrafo y un alt.
- [ ] `_wp_attachment_image_alt` en TODAS las imágenes (hoy vienen vacías).
- [ ] Slug limpio basado en la palabra clave.
- [ ] Precio + SKU + marca presentes (para el schema Product / rich snippet).

**Cierre**
- [ ] Verificado en local (o en borrador en prod), luego publicado.
- [ ] Script temporal borrado del servidor.
- [ ] Commit + push del tema hijo/plugin si hubo cambios de código.

---

## 8. Referencias del proyecto

- Tema hijo editable: `sitio_local/wp-content/themes/listeo-child/` (pestaña Ficha técnica y widgets
  de filtro ya implementados).
- Categorías `product_cat`: árbol Perro/Gato/Otras especies/Promociones ya creado (local + prod).
- Precio de Laika a usar: `price.sale` (sin membresía).
- SEO: Rank Math 1.0.273 — claves `rank_math_title`, `rank_math_description`,
  `rank_math_focus_keyword`; alt en `_wp_attachment_image_alt` del attachment.
