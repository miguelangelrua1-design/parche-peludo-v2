# Encargo para AntiGravity — Normalización del buscador (Parche Peludo)

> Documento de encargo redactado por Claude Code el 2026-07-18, a partir de un análisis
> de los 949 productos publicados en el sitio local. Todos los números de este documento
> salen de consultas reales a la base de datos, no de estimaciones.

---

## 1. El problema, en una frase

El buscador compara **letra por letra**, así que quien escribe `hills` no encuentra
`Hill's Science Diet` (51 productos), y quien escribe `N&D` no encuentra `N&amp;D`
(43 productos). No hay ninguna capa de normalización.

**Objetivo del encargo:** que el buscador ignore diferencias de puntuación, formato de
números y plurales al comparar, sin cambiar el contenido del catálogo y sin romper nada
de lo ya construido.

> ⚠️ Fuera de alcance en este encargo: **sinónimos** (`comida` = `alimento`) y
> **tolerancia a errores de tipeo** (`hils`). Son un segundo trabajo, posterior a este.

---

## 2. Qué hay que normalizar (con evidencia del catálogo)

### A. Puntuación que debe ignorarse al comparar

| # | Elemento | Productos afectados | Ejemplo real | Hoy pasa |
|---|---|---|---|---|
| A1 | Apóstrofo recto `'` y curvo `’` | **77** | `Hill's Science Diet`, `Dr. Clauder's` | `hills` → 0 resultados |
| A2 | Entidad HTML `&amp;` | **43** | `N&amp;D Ancestral`, `Small &amp; Mini` | `N&D` → 0 resultados |
| A3 | Ampersand `&` ↔ `y` / `and` | 43 | `Small & Mini` | `small y mini` → 0 |
| A4 | Guion `-` | 32 | nombres compuestos | falla si se omite |
| A5 | Punto `.` | **76** | `Dr. Clauder's`, `2.5 kg` | `dr clauder` → 0 |
| A6 | Paréntesis `( )` | 208 | `Cama Acolchada (Demo)` | ruido al comparar |
| A7 | Slash `/` y signo `+` | 25 + 25 | `Senior 7+` | menor, pero incluir |

**A2 es el más crítico y el menos evidente:** esos 43 productos tienen el código HTML
`&amp;` guardado literalmente en el título, así que **hoy son prácticamente inencontrables**
escribiendo el nombre de la marca de forma natural.

### B. Números y unidades (catálogo 100 % en un solo formato)

| # | Elemento | Evidencia | Hoy pasa |
|---|---|---|---|
| B1 | Separador decimal | **65** productos usan punto (`2.5 kg`); **0** usan coma | En Colombia se escribe `2,5` → **0 resultados** |
| B2 | Espacio antes de la unidad | **150** productos escriben `3 kg`; **0** escriben `3kg` | Quien busca `3kg` (lo natural al teclear) → **0 resultados** |
| B3 | Variantes de unidad | 132 productos con gramos | `kg`/`kgs`/`kilos`, `g`/`gr`/`gramos` deben equivaler |

B1 y B2 son casos donde **el 100 % del catálogo está en un formato y el usuario escribe
en el otro**. Son victorias grandes y baratas.

### C. Morfología del español

| # | Elemento | Ejemplo | Hoy pasa |
|---|---|---|---|
| C1 | Plural / singular | producto `Juguete Mordedor` | `juguetes` → 0 resultados (al revés sí funciona) |

Basta una regla simple de sufijos (`-s`, `-es`); **no** hace falta un lematizador.

### D. Lo que YA funciona — no romper

| Elemento | Estado |
|---|---|
| **Tildes** (`nutricion` encuentra `Nutrición`) | ✅ Funciona por la colación de la base de datos |
| **Mayúsculas/minúsculas** | ✅ Funciona |
| Espacios dobles / sobrantes | ✅ Catálogo limpio (0 casos), conviene contemplarlo por robustez |

> Verificar explícitamente que las tildes siguen funcionando después del cambio: es el
> riesgo de regresión más probable si la normalización se implementa sobre texto crudo.

---

## 3. Dónde vive el código y por dónde entrar

**Único lugar editable:** `sitio_local/wp-content/themes/listeo-child/`
(prohibido tocar el tema padre `listeo`, el core de WordPress y los plugins).

El buscador tiene **tres superficies** que hoy consultan por separado:

1. **Sugerencias del desplegable** → `functions.php`, función `ppv2_header_suggest()`
   (usa `get_posts()` con `'s' => $term`).
2. **Resultados de Tienda** → búsqueda nativa de WooCommerce/WordPress.
3. **Resultados de Directorio** → consulta del plugin Listeo Core.

### Dónde poner el código (decisión de arquitectura 2026-07-18)

Se decidió que el motor del buscador evolucionará como **módulo "Buscador" dentro del
plugin `pp-personalizacion`** (mismo patrón que los módulos Mascotas/Listados, con su
interruptor de apagado). Por tanto:

- **Si el módulo Buscador ya existe** cuando implementes este encargo → pon la
  normalización ahí (`pp-personalizacion/includes/buscador.php` o archivo del módulo).
- **Si aún no existe** → impleméntala en el tema hijo (`listeo-child/inc/`, patrón
  `pp-rendimiento-*.php`) y déjala autocontenida; se migrará al módulo después.
  Cualquiera de los dos caminos es válido — el filtro es autocontenido y moverlo es trivial.

### Recomendación de arquitectura

Implementar la normalización **en un punto único**: el filtro de WordPress
**`posts_search`**, que intercepta la cláusula `WHERE` de cualquier `WP_Query` con
búsqueda. Como las tres superficies terminan en `WP_Query`, un solo filtro las cubre.

> ⚠️ **GOTCHA CRÍTICO — sugerencias y `get_posts()`:** el endpoint de sugerencias
> (`ppv2_header_suggest()` en functions.php del hijo) y el contador de puentes usan
> `get_posts()`, que por defecto pone `suppress_filters = true` → **el filtro
> `posts_search` NO se aplicaría a esas consultas** y la normalización quedaría solo en
> las páginas de resultados (el desalineamiento que el criterio de aceptación 1 prohíbe).
> Solución: en esas funciones, cambiar `get_posts( $args )` por
> `( new WP_Query( $args ) )->posts` (o añadir `'suppress_filters' => false`). Es un
> cambio de una línea por consulta y forma parte de este encargo.

La normalización debe aplicarse **a los dos lados de la comparación**:

- **Al término** que escribe el usuario → en PHP.
- **Al título almacenado** → dentro del SQL, con `REPLACE()` anidados sobre `post_title`
  (y `post_excerpt`/`post_content` si se decide incluirlos).

> Normalizar solo el término **no sirve**: `hills` normalizado sigue siendo `hills`,
> y el título sigue teniendo el apóstrofo. Este es el error clásico a evitar.

**Alcance del filtro (importante):** aplicar solo en el front-end, solo para los tipos
`product` y `listing`, y nunca en el administrador de WordPress (rompería las búsquedas
internas del equipo).

### Nota de rendimiento y escala

`REPLACE(...) LIKE '%término%'` impide el uso de índices, **pero la búsqueda actual ya
usa `LIKE '%...%'`**, así que no hay regresión respecto a hoy. Con 949 productos el costo
es despreciable.

Si el catálogo crece a decenas de miles (viene importación masiva vía Dropi), la
evolución natural es guardar una **versión normalizada del título en un campo indexable**
y buscar contra él. No hace falta ahora; conviene dejar el código preparado para ese salto.

---

## 4. Lo que NO se debe romper (lista de regresión)

El buscador acumula bastante trabajo previo. Verificar que todo esto sigue igual:

1. **Pestañas Tienda | Directorio** — orden, ámbito por sección y elección manual.
2. **Sugerencias agrupadas** con foto, precio y conteo por pestaña (`Tienda · 5`).
3. **Puentes cruzados** de "sin resultados" — dependen de `ppv2_cross_search_count()`,
   que también usa `WP_Query`: sus conteos deben quedar **coherentes** con los resultados
   reales (si las sugerencias muestran Hill's, el puente y la página deben coincidir).
4. **Filtros AJAX del Directorio** — el panel lateral no debe alterarse.
5. **Chip "+" de tipos** (Adopción / Mascotas perdidas) del plugin `pp-personalizacion`.
6. **Cachés existentes:** los transitorios `ppv2_suggest_*` y `ppv2_xcount_*` guardan
   resultados por término durante 10 minutos. Tras desplegar el cambio hay que **purgarlos**,
   o durante 10 minutos se servirán resultados viejos y parecerá que la normalización falló.
7. **Purgar caché de LiteSpeed** al terminar (en local y en producción).

---

## 5. Criterios de aceptación (casos de prueba concretos)

Cada caso debe verificarse **en las tres superficies**: sugerencias, resultados de Tienda
y resultados de Directorio.

| # | Se escribe | Debe encontrar | Hoy da |
|---|---|---|---|
| 1 | `hills` | los 51 `Hill's Science Diet` | 0 |
| 2 | `hill's` | los mismos 51 | 51 ✅ (no romper) |
| 3 | `dr clauder` | `Dr. Clauder's Dog Senior Light` | 0 |
| 4 | `N&D` / `nd ancestral` | los `N&amp;D` | 0 |
| 5 | `small y mini` | `Small &amp; Mini` | 0 |
| 6 | `2,5 kg` | los `2.5 kg` | 0 |
| 7 | `3kg` (pegado) | los `3 kg` | 0 |
| 8 | `juguetes` | `Juguete Mordedor` | 0 |
| 9 | `nutricion` | `Nutrición` | 2 ✅ **(regresión a vigilar)** |
| 10 | `royal canin` | los 84 de la marca | 84 ✅ (no romper) |
| 11 | término inexistente (`zzzqx`) | 0 + pantalla "Sin resultados" con su puente | correcto ✅ |

**Comprobación cruzada obligatoria del caso 1:** al escribir `hills`, la pestaña Tienda
debe mostrar conteo > 0 **y** al presionar Enter la página debe listar los productos. Si
las sugerencias muestran resultados pero la página dice "Sin resultados", la normalización
quedó aplicada en una sola capa — que es justo lo que este encargo busca evitar.

---

## 6. Entrega esperada

- Código en `listeo-child` (`functions.php` o, mejor, un archivo propio en `inc/`
  incluido desde `functions.php`, siguiendo el patrón `pp-rendimiento-*.php` del proyecto).
- Comentarios en español explicando **por qué** se normaliza cada grupo (el catálogo
  cambia; en seis meses nadie recordará el caso `&amp;`).
- **Interruptor de apagado** (constante o filtro) para desactivar la normalización sin
  editar código, por si aparece un efecto inesperado en producción.
- Prueba de los 11 casos de aceptación, con evidencia.
- Commit pequeño y descriptivo en el repositorio del workspace.

---

## 7. Dos observaciones para Miguel (producto, no código)

1. **Los `&amp;` son además un problema de datos.** Esos 43 títulos muestran el código HTML
   en pantalla en algunos contextos. La normalización los hace encontrables, pero lo
   ideal es corregir también los títulos guardados (trabajo aparte, con respaldo previo,
   probablemente relacionado con el importador de Dropi que los generó así).

2. **Siguiente paso natural:** registrar las búsquedas que no arrojan resultados. Con esa
   lista, el diccionario de sinónimos (el trabajo que sigue) se alimenta de lo que la
   gente realmente escribe, en vez de adivinar.
