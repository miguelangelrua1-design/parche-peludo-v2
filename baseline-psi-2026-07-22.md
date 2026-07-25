# Informe Baseline de Rendimiento (PageSpeed Insights API v5) - Parche Peludo

**Fecha de Medicion:** 2026-07-22  
**Metodologia:** Mediana de 3 corridas por URL + estrategia (mobile y desktop) via API v5 oficial con API Key.  
**Total mediciones:** 8 URLs x 2 estrategias x 3 corridas = 48 ejecuciones de Lighthouse.  

---

## 1. Resumen Ejecutivo

### Estado General Core Web Vitals en Movil
- **LCP (Carga del elemento principal):** Malo (>4,0s): 8 | Mejorable (2,5-4,0s): 0 | Bueno (<=2,5s): 0
- **CLS (Estabilidad visual):** Malo (>0,25): 3 | Mejorable (0,10-0,25): 18 | Bueno (<=0,10): 4
- **TBT (Proxy de interactividad INP):** Malo (>600ms): 0 | Mejorable (200-600ms): 5 | Bueno (<=200ms): 3

> [!IMPORTANT]
> **Diagnostico General y Problema Dominante:**
> 1. **Imagenes JPG servidas sin formato WebP/AVIF y sin Lazy-Loading:** Las imagenes JPG pesadas (~140-300 KB) servidas sin compresion moderna son el factor principal que penaliza el LCP en movil (promedio de 10 a 17 segundos en red movil simulada por Lighthouse).
> 2. **Imagenes sin atributos de dimensiones (CLS elevado):** Paginas como /directorio/, /producto/ y /publicacion/ registran un CLS muy alto (>0.34 a 0.63) debido a carruseles, widgets de Elementor e imagenes sin atributos explicitos width y height.
> 3. **Recursos CSS/JS no utilizados y bloqueantes:** Elementor + tema Listeo + WooCommerce cargan mas de 1,2 MB de CSS y JS no utilizados en movil, afectando el FCP y el tiempo de bloqueo.
> 4. **Datos de campo CrUX:** Dado que el sitio es nuevo y con trafico reciente, **no existen datos de campo suficientes en CrUX** ('sin datos de CrUX'). Todas las metricas representan datos de laboratorio rigurosos obtenidos via Lighthouse.

---

## 2. Tabla Comparativa Movil vs. Escritorio (Medianas)

| # | Tipo de Pagina | Estrategia | Score Perf | LCP (s) | CLS | TBT (ms) | FCP (s) | TTFB (s) |
|---|---|---|---|---|---|---|---|---|
| 1 | **Portada** | Movil | 48 [Malo] | 12 s [Malo] | 0.012 [Bueno] | 336 ms [Mejorable] | 10.35 s | 0.02 s |
| | | Escritorio | 77 [Mejorable] | 2.1 s [Bueno] | 0.002 [Bueno] | 133 ms [Bueno] | 1.88 s | 0.06 s |
| 2 | **Directorio (busqueda de servicios)** | Movil | 32 [Malo] | 17.1 s [Malo] | 0.342 [Malo] | 318 ms [Mejorable] | 10.2 s | 0.07 s |
| | | Escritorio | 54 [Mejorable] | 2.2 s [Bueno] | 0.223 [Mejorable] | 296 ms [Mejorable] | 1.84 s | 0.01 s |
| 3 | **Tienda** | Movil | 53 [Mejorable] | 10.73 s [Malo] | 0.01 [Bueno] | 195 ms [Bueno] | 9.9 s | 0.05 s |
| | | Escritorio | 81 [Mejorable] | 2.08 s [Bueno] | 0.003 [Bueno] | 9 ms [Bueno] | 1.84 s | 0.01 s |
| 4 | **Landing de Servicios** | Movil | 50 [Mejorable] | 13.13 s [Malo] | 0.13 [Mejorable] | 80 ms [Bueno] | 11.55 s | 0.03 s |
| | | Escritorio | 57 [Mejorable] | 2.4 s [Bueno] | 0.076 [Bueno] | 379 ms [Mejorable] | 2.1 s | 0.07 s |
| 5 | **Categoria de producto** | Movil | 33 [Malo] | 11.25 s [Malo] | 0.03 [Bueno] | 299 ms [Mejorable] | 9.9 s | 0.07 s |
| | | Escritorio | 77 [Mejorable] | 2.07 s [Bueno] | 0.114 [Mejorable] | 13 ms [Bueno] | 1.84 s | 0.02 s |
| 6 | **Ficha de producto (PDP)** | Movil | 35 [Malo] | 15.9 s [Malo] | 0.492 [Malo] | 3 ms [Bueno] | 10.35 s | 0.02 s |
| | | Escritorio | 46 [Malo] | 2.12 s [Bueno] | 0.421 [Malo] | 270 ms [Mejorable] | 1.88 s | 0.14 s |
| 7 | **Ficha de publicacion** | Movil | 29 [Malo] | 14.18 s [Malo] | 0.633 [Malo] | 224 ms [Mejorable] | 10.05 s | 0.05 s |
| | | Escritorio | 47 [Malo] | 2.1 s [Bueno] | 0.37 [Malo] | 327 ms [Mejorable] | 1.86 s | 0.01 s |
| 8 | **Blog** | Movil | 53 [Mejorable] | 10.58 s [Malo] | 0.022 [Bueno] | 205 ms [Mejorable] | 9 s | 0.08 s |
| | | Escritorio | 48 [Malo] | 2.28 s [Bueno] | 0.011 [Bueno] | 726 ms [Malo] | 2.16 s | 0.02 s |

---

## 3. Detalle por Plantilla y Oportunidades Clave

### 3.1 Portada
**URL:** `https://parchepeludo.com/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **48/100** | **77/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 12 s | 2.1 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.012 | 0.002 | <= 0,10 |
| **TBT (Total Blocking Time)** | 336 ms | 133 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 10.35 s | 1.88 s | <= 1,8 s |
| **Speed Index** | 10.35 s | 1.98 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.02 s | 0.06 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 264 KiB | 1800 ms | 264 KB |
| **Reduce unused JavaScript** | Est savings of 516 KiB | 1800 ms | 516 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Reduce unused CSS** | **1800 ms** | 264 KB |
| 2 | **Minify JavaScript** | **600 ms** | 83 KB |
| 3 | **Reduce unused JavaScript** | **1800 ms** | 516 KB |
| 4 | **Minify CSS** | **600 ms** | 80 KB |

### 3.2 Directorio (busqueda de servicios)
**URL:** `https://parchepeludo.com/directorio/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **32/100** | **54/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 17.1 s | 2.2 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.342 | 0.223 | <= 0,10 |
| **TBT (Total Blocking Time)** | 318 ms | 296 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 10.2 s | 1.84 s | <= 1,8 s |
| **Speed Index** | 10.2 s | 2.44 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.07 s | 0.01 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 261 KiB | 1050 ms | 261 KB |
| **Reduce unused JavaScript** | Est savings of 431 KiB | 1800 ms | 431 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Reduce unused CSS** | **1050 ms** | 261 KB |
| 2 | **Minify CSS** | **300 ms** | 82 KB |
| 3 | **Reduce unused JavaScript** | **1800 ms** | 431 KB |
| 4 | **Minify JavaScript** | **750 ms** | 83 KB |

### 3.3 Tienda
**URL:** `https://parchepeludo.com/tienda/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **53/100** | **81/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 10.73 s | 2.08 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.01 | 0.003 | <= 0,10 |
| **TBT (Total Blocking Time)** | 195 ms | 9 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 9.9 s | 1.84 s | <= 1,8 s |
| **Speed Index** | 9.9 s | 1.84 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.05 s | 0.01 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 272 KiB | 1350 ms | 272 KB |
| **Reduce unused JavaScript** | Est savings of 482 KiB | 1050 ms | 482 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Reduce unused JavaScript** | **1050 ms** | 482 KB |
| 2 | **Reduce unused CSS** | **1350 ms** | 272 KB |
| 3 | **Minify CSS** | **150 ms** | 82 KB |
| 4 | **Minify JavaScript** | **150 ms** | 81 KB |

### 3.4 Landing de Servicios
**URL:** `https://parchepeludo.com/home-servicios/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **50/100** | **57/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 13.13 s | 2.4 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.13 | 0.076 | <= 0,10 |
| **TBT (Total Blocking Time)** | 80 ms | 379 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 11.55 s | 2.1 s | <= 1,8 s |
| **Speed Index** | 11.55 s | 2.38 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.03 s | 0.07 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 282 KiB | 1800 ms | 282 KB |
| **Reduce unused JavaScript** | Est savings of 482 KiB | 1650 ms | 482 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Minify JavaScript** | **300 ms** | 83 KB |
| 2 | **Reduce unused CSS** | **1800 ms** | 282 KB |
| 3 | **Minify CSS** | **600 ms** | 80 KB |
| 4 | **Reduce unused JavaScript** | **1650 ms** | 482 KB |

### 3.5 Categoria de producto
**URL:** `https://parchepeludo.com/categoria-producto/analgesicos/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **33/100** | **77/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 11.25 s | 2.07 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.03 | 0.114 | <= 0,10 |
| **TBT (Total Blocking Time)** | 299 ms | 13 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 9.9 s | 1.84 s | <= 1,8 s |
| **Speed Index** | 9.9 s | 1.84 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.07 s | 0.02 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 269 KiB | 1050 ms | 269 KB |
| **Reduce unused JavaScript** | Est savings of 480 KiB | 1050 ms | 480 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Reduce unused JavaScript** | **1050 ms** | 480 KB |
| 2 | **Reduce unused CSS** | **1050 ms** | 269 KB |
| 3 | **Minify CSS** | **150 ms** | 80 KB |

### 3.6 Ficha de producto (PDP)
**URL:** `https://parchepeludo.com/producto/royal-canin-mini-puppy/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **35/100** | **46/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 15.9 s | 2.12 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.492 | 0.421 | <= 0,10 |
| **TBT (Total Blocking Time)** | 3 ms | 270 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 10.35 s | 1.88 s | <= 1,8 s |
| **Speed Index** | 10.35 s | 3.04 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.02 s | 0.14 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 266 KiB | 1200 ms | 266 KB |
| **Reduce unused JavaScript** | Est savings of 479 KiB | 2250 ms | 479 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Minify CSS** | **300 ms** | 80 KB |
| 2 | **Minify JavaScript** | **450 ms** | 79 KB |
| 3 | **Reduce unused CSS** | **1200 ms** | 266 KB |
| 4 | **Reduce unused JavaScript** | **2250 ms** | 479 KB |

### 3.7 Ficha de publicacion
**URL:** `https://parchepeludo.com/publicacion/clinica-veterinaria-de-medellin/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **29/100** | **47/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 14.18 s | 2.1 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.633 | 0.37 | <= 0,10 |
| **TBT (Total Blocking Time)** | 224 ms | 327 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 10.05 s | 1.86 s | <= 1,8 s |
| **Speed Index** | 10.05 s | 2.25 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.05 s | 0.01 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 262 KiB | 1500 ms | 262 KB |
| **Reduce unused JavaScript** | Est savings of 495 KiB | 1650 ms | 495 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Minify CSS** | **300 ms** | 80 KB |
| 2 | **Reduce unused JavaScript** | **1650 ms** | 495 KB |
| 3 | **Reduce unused CSS** | **1500 ms** | 262 KB |
| 4 | **Minify JavaScript** | **600 ms** | 85 KB |

### 3.8 Blog
**URL:** `https://parchepeludo.com/blog-mascotas/`

#### Metricas Baseline (Movil vs. Escritorio)
| Metrica | Movil (Mediana) | Escritorio (Mediana) | Umbral Objetivo |
|---|---|---|---|
| **Puntaje Rendimiento** | **53/100** | **48/100** | >= 90 |
| **LCP (Largest Contentful Paint)** | 10.58 s | 2.28 s | <= 2,5 s |
| **CLS (Cumulative Layout Shift)** | 0.022 | 0.011 | <= 0,10 |
| **TBT (Total Blocking Time)** | 205 ms | 726 ms | <= 200 ms |
| **FCP (First Contentful Paint)** | 9 s | 2.16 s | <= 1,8 s |
| **Speed Index** | 9 s | 2.63 s | <= 3,4 s |
| **TTFB (Tiempo de Respuesta Servidor)** | 0.08 s | 0.02 s | <= 0,8 s |

**Datos de Campo (CrUX):** sin datos de CrUX (Pagina y Origen).

#### Diagnosticos de Imagenes y Recursos (Movil)
| Diagnostico | Estado / Mensaje | Ahorro Est. (ms) | Ahorro Est. (KB) |
|---|---|---|---|
| **Image elements do not have explicit `width` and `height`** | Sin datos | - | - |
| **Reduce unused CSS** | Est savings of 256 KiB | 1050 ms | 256 KB |
| **Reduce unused JavaScript** | Est savings of 481 KiB | 750 ms | 481 KB |

#### Top 5 Oportunidades de Mejora (Movil)
| # | Oportunidad | Ahorro Estimado (ms) | Detalle / Ahorro KB |
|---|---|---|---|
| 1 | **Reduce unused CSS** | **1050 ms** | 256 KB |
| 2 | **Minify CSS** | **300 ms** | 80 KB |
| 3 | **Minify JavaScript** | **300 ms** | 81 KB |
| 4 | **Reduce unused JavaScript** | **750 ms** | 481 KB |

---

## 4. Los 5 Arreglos Priorizados (Impacto vs. Esfuerzo)

Con base en la evidencia empirica recolectada a traves de la API de PSI, este es el plan de accion priorizado para la siguiente fase de optimizacion:

| # | Optimizacion Recomendada | Impacto Estimado | Esfuerzo | Donde se resuelve |
|---|---|---|---|---|
| 1 | **Conversion automatica de imagenes a WebP/AVIF** | **Alto** (~1,5s - 3,5s LCP) | Bajo | **LiteSpeed Cache (Plugin / Servidor)** - Activar la conversion automatica en WebP y reemplazo HTML. |
| 2 | **Lazy-Loading de imagenes e IFrames (Offscreen images)** | **Alto** (~1,0s - 2,0s LCP) | Bajo | **LiteSpeed Cache** - Activar Lazy Load de imagenes e IFrames en LiteSpeed. |
| 3 | **Especificar atributos de dimension (width y height) en imagenes de Elementor/Listeo** | **Alto** (Elimina riesgo de CLS en movil) | Medio | **Tema Hijo / Contenido Elementor** - Anadir dimensiones a plantillas de Listeo y widgets de Elementor. |
| 4 | **Optimizacion y minificacion de CSS/JS no utilizado (Render-Blocking)** | **Medio** (~400ms - 900ms FCP/TBT) | Medio | **LiteSpeed Cache** - Configurar combinacion y carga diferida de CSS/JS (JS Defer / CSS Combined). |
| 5 | **Optimizacion de imagenes externas de Google (lh3.googleusercontent.com) en /home-servicios/** | **Medio** (Resuelve LCP/CLS en landing) | Bajo-Medio | **Contenido / Tema Hijo** - Alojar localmente avatar/iconos externos o aplicar preconnect DNS a Google Content. |

---
*Informe generado automaticamente por el script de analisis baseline de Antigravity.*
