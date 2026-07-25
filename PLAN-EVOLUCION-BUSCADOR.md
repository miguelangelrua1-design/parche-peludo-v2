# Plan de evolución del Buscador — Parche Peludo

> Redactado por Claude Code el 2026-07-18. Planeación completa de la evolución del
> buscador: arquitectura, modelo de datos, conexiones con lo existente, fases de
> implementación con su lógica, riesgos y métricas. Complementa (no reemplaza) el
> encargo `PROMPT-NORMALIZACION-BUSCADOR.md` que ejecutará AntiGravity.

---

## 1. Visión

El buscador ya tiene una **capa de presentación** completa (tabs Tienda|Directorio con
contexto de sección, sugerencias con foto/precio/conteos, "Buscando…", puentes cruzados
con conteo real, textos por sección). Esta evolución construye la **capa de motor**:
que encuentre mejor (normalización, sinónimos), ordene mejor (relevancia, stock, foto),
aprenda de los usuarios (registro de búsquedas) y se administre sin código (paneles).

**Principios que gobiernan todo el plan:**

1. **Un solo pipeline de consulta.** Toda mejora del "encontrar" se aplica en filtros de
   `WP_Query` (`posts_search` / `posts_clauses`) para que las TRES superficies —
   sugerencias, resultados de Tienda, resultados de Directorio — se comporten idéntico.
   Nunca una mejora en una sola capa (lección: sugerencias que muestran y página que niega).
2. **Sin costos recurrentes ni licencias.** Todo propio (lección del chat AI Pro).
3. **Todo apagable.** Cada función con su interruptor; producción nunca queda rehén.
4. **Datos antes que intuición.** El registro de búsquedas (Fase 1) alimenta las
   decisiones de todas las fases siguientes.

---

## 2. Arquitectura y reparto de responsabilidades

### Módulo nuevo: "Buscador" en pp-personalizacion

- Archivo principal: `pp-personalizacion/includes/buscador.php` (+ subarchivos si crece:
  `buscador-admin.php`, `buscador-ranking.php`…), registrado como los demás módulos.
- Submenú admin: **pp-personalizacion → Buscador**, con pestañas:
  `Registro` · `Sinónimos` · `Redirecciones` · `Ajustes` (interruptores).
- Interruptores (opciones): `pp_buscador_registro`, `pp_buscador_ranking`,
  `pp_buscador_sinonimos`, `pp_buscador_quisiste_decir`, `pp_buscador_populares`,
  `pp_buscador_redirecciones` + master `pp_buscador_activo`.

### Lo que permanece en el tema hijo (presentación)

- JS del header (`js/migrados/ppv2-header-suggest.js`): tabs, sugerencias, conteos,
  estados. Se le harán ampliaciones puntuales (populares, beacon, grupo Categorías).
- Endpoint `ppv2_header_suggest()` y `ppv2_cross_search_count()` (functions.php):
  se mantienen donde están, pero **heredan** el pipeline del módulo vía filtros.
- Plantillas sobrescritas (`listeo-core/archive/no-found.php`,
  `woocommerce/loop/no-products-found.php`, `woocommerce.php`): ganan llamadas
  opcionales al módulo (`function_exists`) para "¿Quisiste decir…?".

### Regla de convivencia plugin ↔ tema

El tema NUNCA asume que el módulo existe: toda llamada va protegida con
`function_exists()`. Si pp-personalizacion se desactiva, el buscador vuelve a su
comportamiento actual sin romperse. Y el módulo NUNCA imprime UI del header: expone
funciones y endpoints, el tema decide cómo pintarlos.

---

## 3. Modelo de datos

| Almacén | Tipo | Contenido |
|---|---|---|
| `wp_pp_busquedas` | **Tabla nueva** (dbDelta) | `id, termino, termino_norm, ambito` (tienda/directorio/adopcion/mascotas-perdidas), `resultados` (int), `origen` (resultados/sugerencia-click/puente-click), `fecha`. **Sin datos personales** (ni IP ni usuario). Purga automática > 180 días (cron diario). |
| `pp_buscador_sinonimos` | Option | Grupos de equivalencia, uno por línea: `comida, alimento, concentrado`. Editable en panel. Se entrega con diccionario semilla del dominio mascotas. |
| `pp_buscador_redirecciones` | Option | `término => URL`, uno por línea: `adoptar | /adopcion/`. |
| `pp_buscador_indice_palabras` | Option (o tabla si crece) | Palabras únicas (≥4 letras, normalizadas) de títulos de productos y listados, con frecuencia. Base del "¿Quisiste decir…?". Regenerado por cron diario + al guardar productos (diferido). ~1.500 palabras con el catálogo actual. |
| `pp_buscador_dict_ver` | Option (entero) | **Versión del diccionario**: se incrementa al guardar sinónimos/redirecciones. Forma parte de las claves de caché → invalida `ppv2_suggest_*` y `ppv2_xcount_*` sin borrarlos uno a uno. |

---

## 4. Mapa de conexiones (qué toca a qué)

```
                            ┌──────────────────────────────────┐
                            │  MÓDULO BUSCADOR (pp-personaliz.) │
                            │                                  │
 usuario escribe ──┐        │  • posts_search  (normaliza +    │
                   ▼        │    sinónimos, de AntiGravity/F2) │
 [sugerencias]──WP_Query──▶ │  • posts_clauses (ranking F1)    │──▶ resultados
 [pág. Tienda]──WP_Query──▶ │  • pp_buscador_log() (F1)        │    coherentes
 [pág. Direct.]─WP_Query──▶ │  • pp_buscador_quisiste_decir()  │    en las 3
 [puentes count]─WP_Query─▶ │  • endpoints: populares, beacon  │    superficies
                            └──────────────────────────────────┘
```

Cambios de integración concretos (una línea cada uno, pero críticos):

1. **`ppv2_header_suggest()` y `ppv2_cross_search_count()`** pasan de `get_posts()` a
   `WP_Query` con `suppress_filters => false` — sin esto, los filtros del módulo NO
   aplican a sugerencias ni puentes (gotcha documentado también en el encargo de
   AntiGravity, que puede ejecutarlo primero: no chocan, es el mismo cambio).
2. **Claves de caché** de sugerencias y puentes incorporan `pp_buscador_dict_ver`.
3. **Plantillas no-found** llaman `pp_buscador_quisiste_decir()` si existe.
4. **JS del header**: beacon de registro, populares al enfocar, grupo "Categorías".
5. **Ámbito para el log**: se reutiliza `pp_listados_contexto_tipo()` (módulo Listados)
   — misma fuente de verdad que ya usan las plantillas.

### Decisión importante: el registro se hace por BEACON desde el navegador

LiteSpeed cachea las páginas de resultados: un hook PHP de registro **no se ejecutaría
en los hits de caché** (la mayoría del tráfico repetido) y subcontaría sistemáticamente.
Por eso: el servidor imprime en la página un dato (`data-ppv2-total`) con el total de
resultados, y un mini-JS envía `navigator.sendBeacon()` al endpoint de registro. El
beacon corre siempre, con o sin caché, no bloquea la navegación, y de paso permite
registrar los clics en sugerencias y en puentes (intención fuerte). Deduplicación en
servidor: mismo término+ámbito en <10 s no se duplica.

---

## 5. Fases de implementación

> Orden diseñado para que cada fase entregue valor sola y la siguiente construya encima.
> **Prerequisito de la Fase 1: que AntiGravity entregue la normalización** (o se absorbe
> su encargo dentro de la Fase 1 si se decide no esperar — ambos caminos previstos).

### FASE 1 — Fundación: módulo + registro + ranking

**1a. Esqueleto del módulo Buscador** — registro en pp-personalizacion, panel con
pestañas, interruptores, tabla `wp_pp_busquedas` (dbDelta), cron de purga.

**1b. Registro de búsquedas** — beacon JS (child) + endpoint de ingesta (módulo) +
pestaña "Registro" con: top 20 más buscadas (30 días), top 20 **sin resultados** (30
días), tabla reciente filtrable, export CSV. KPI base: **% de búsquedas con 0 resultados**.

**1c. Ranking de relevancia** — filtro `posts_clauses` (solo front, solo product/listing,
solo si hay término y el usuario NO eligió otro orden en el dropdown de Woo):

```
ORDEN: 1) título coincide exacto → 2) título EMPIEZA por el término →
       3) título contiene → 4) solo descripción
DESEMPATES (productos): con stock antes que agotados · con foto antes que sin foto
```

**Criterios de aceptación F1:** buscar una marca la muestra de primera (hoy sale por
"orden predeterminado"); productos agotados/sin foto bajan; el panel muestra las
búsquedas de una sesión de prueba; el % cero-resultados queda medible; el dropdown de
orden manual de Woo sigue mandando cuando el usuario lo usa.

### FASE 2 — Administrable: sinónimos + populares

**2a. Diccionario de sinónimos** — pestaña "Sinónimos" (textarea, un grupo por línea),
seed inicial del dominio (`comida=alimento=concentrado`, `champú=shampoo`,
`guardería=cuidador=hotel canino`, `arena=arenero`, `correa=tralla`…). Aplicación en
`posts_search`: expansión por palabra — `"comida gato"` → `(comida|alimento|concentrado)
Y (gato)`. Al guardar: `pp_buscador_dict_ver++` (invalida cachés al vuelo).
El flujo de trabajo queda cerrado: **la pestaña Registro muestra qué falló → se agrega
el sinónimo en la pestaña de al lado → efecto inmediato.**

**2b. Búsquedas populares al enfocar** — endpoint (top reales con resultados > 0,
caché 1 h) + panel bajo el campo al enfocar vacío ("Lo más buscado"), respetando el
tab activo. Estilo consistente con el desplegable actual.

**Aceptación F2:** buscar `comida` encuentra los "Alimento…" en las 3 superficies (con
conteos y puentes coherentes); un sinónimo agregado en el panel funciona sin tocar
código y sin esperar 10 minutos de caché; al enfocar el campo vacío aparecen populares
reales.

### FASE 3 — Inteligencia: quisiste decir + categorías + redirecciones

**3a. "¿Quisiste decir…?"** — índice de palabras del catálogo (cron) + al caer en 0
resultados, `levenshtein()` contra candidatos (misma inicial, longitud ±2, normalizados)
→ la plantilla no-found muestra "¿Quisiste decir **royal canin**?" enlazando la búsqueda
corregida en el mismo ámbito. El clic se registra (mide si la corrección acierta).

**3b. Categorías en sugerencias** — tercer grupo del desplegable: coincidencias en
`product_cat` / categorías de listado (máx. 3, con enlace al archivo de la categoría).
Escribir "ali" ofrece saltar a **Alimento** completo con sus filtros.

**3c. Redirecciones administrables** — pestaña "Redirecciones": término exacto → URL
(`adoptar | /adopcion/`). Se evalúa al cargar la página de resultados (término
normalizado); redirección 302. Para campañas y atajos de negocio.

**Aceptación F3:** `royal canim` → 0 resultados + sugerencia correcta clicable;
"alim" sugiere la categoría Alimento; una redirección creada en el panel funciona.

### FASE 4 — Condicional (solo si los datos lo piden)

- **Resultados fijados** por término (promociones) — cuando el registro muestre términos
  de alto volumen donde el ranking no da el resultado de negocio deseado.
- **Índice normalizado persistente** (columna/tabla indexable en vez de `REPLACE()` en
  vivo) — cuando el catálogo supere ~10-20 mil productos o la latencia de búsqueda suba.
- **Motor dedicado** (SearchWP/Relevanssi, con auditoría de licencia previa) — solo si
  lo anterior queda corto. Con este plan completo, difícilmente antes de decenas de miles
  de ítems.

---

## 6. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Chocar con el encargo de AntiGravity (normalización) | Coordinación explícita: su entrega va primero (o se absorbe en F1). El cambio `get_posts→WP_Query` está documentado en ambos papeles y es idempotente. |
| Cachés de 10 min haciendo parecer que algo "no funciona" | `pp_buscador_dict_ver` en las claves + recordatorio en cada fase de purgar LiteSpeed. |
| Sesiones paralelas tocando los mismos archivos | Patrón ya probado: releer antes de editar, commits por hunks. El módulo nuevo vive en archivos propios → colisión mínima. |
| Ranking pisando el orden elegido por el usuario | El filtro solo actúa con orderby por defecto/relevancia; el dropdown de Woo manda. |
| Registro creciendo sin control | Purga automática 180 días + deduplicación por ráfaga + sin datos personales. |
| Servidor local saturado para pruebas | Pruebas de motor por consulta directa (MySQL/curl con paciencia) como en toda esta etapa; validación visual de Miguel por fase. |
| Producción a medias entre fases | Cada fase es desplegable completa e independiente; los interruptores permiten activar módulo por módulo en producción. |

---

## 7. Métricas de éxito (salen solas del registro)

- **% de búsquedas con 0 resultados** — el KPI principal; hoy desconocido, se mide desde F1 y debe bajar con F2/F3.
- Top términos sin resultados — la cola de trabajo del diccionario de sinónimos.
- Clics en puentes y en "¿Quisiste decir…?" — miden si los rescates funcionan.
- Términos más buscados — insumo para catálogo, promociones y contenido.

---

## 8. Orden de ejecución y despliegue

1. **AntiGravity**: normalización (encargo ya entregado, con la nota de arquitectura).
2. **Fase 1** (Claude Code): módulo + registro + ranking → validación de Miguel en local
   → producción (zip pp-personalizacion + archivos del hijo + purga LiteSpeed).
3. **Fase 2** → validación → producción. Desde aquí Miguel ya administra sinónimos.
4. **Fase 3** → validación → producción.
5. **Fase 4**: se decide con los datos de los KPIs en la mano, no antes.

Cada fase cierra con: pruebas en las 3 superficies + casos de regresión del buscador
(tabs, conteos, puentes, chip de tipos, filtros AJAX del directorio) + commit descriptivo
+ actualización de la lista de archivos para producción.
