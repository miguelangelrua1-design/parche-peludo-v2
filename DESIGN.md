# Parche Peludo — Manual de Identidad Gráfica
> Versión 1.0 · 2025 · Colombia  
> Base de referencia para la construcción de prototipos del sitio web.

---

## Tabla de contenido

1. [Propósito y fundamentos](#1-propósito-y-fundamentos)
2. [Personalidad de marca](#2-personalidad-de-marca)
3. [Paleta de colores](#3-paleta-de-colores)
4. [Tipografía](#4-tipografía)
5. [Escala de espaciado](#5-escala-de-espaciado)
6. [Radios de borde](#6-radios-de-borde)
7. [Sombras](#7-sombras)
8. [Movimiento y transiciones](#8-movimiento-y-transiciones)
9. [Logo y marca](#9-logo-y-marca)
10. [Iconografía](#10-iconografía)
11. [Fotografía e imágenes](#11-fotografía-e-imágenes)
12. [Layout y grilla](#12-layout-y-grilla)
13. [Componentes — Navegación](#13-componentes--navegación)
14. [Componentes — Botones](#14-componentes--botones)
15. [Componentes — Tarjetas de listado](#15-componentes--tarjetas-de-listado)
16. [Componentes — Formularios e inputs](#16-componentes--formularios-e-inputs)
17. [Componentes — Badges y etiquetas](#17-componentes--badges-y-etiquetas)
18. [Componentes — Sección Hero](#18-componentes--sección-hero)
19. [Componentes — Estados interactivos](#19-componentes--estados-interactivos)
20. [Voz y tono](#20-voz-y-tono)
21. [Patrones y fondos de marca](#21-patrones-y-fondos-de-marca)
22. [Usos incorrectos — qué evitar](#22-usos-incorrectos--qué-evitar)
23. [Referencia del Sitio Actual y Estrategia de Migración V2](#23-referencia-del-sitio-actual-y-estrategia-de-migración-v2)
24. [Guía de Desarrollo Seguro en Entorno Local V2](#24-guía-de-desarrollo-seguro-en-entorno-local-v2)

---

## 1. Propósito y fundamentos

**Parche Peludo** es un directorio, comunidad y plataforma de servicios para mascotas enfocada en Colombia. Conecta a padres y madres de mascotas con veterinarios, peluquerías, paseadores, adiestradores, fotógrafos, guarderías y experiencias — además de un foro, campañas de adopción y una misión social que destina parte de sus ingresos a fundaciones y refugios.

### Círculo de oro (Simon Sinek)

| Capa | Pregunta | Respuesta |
|---|---|---|
| **¿Por qué?** | Propósito | Mejorar la vida de los peludos y sus papás y mamás. |
| **¿Cómo?** | Método | Contenido de alto valor, buscador, comunidad, apoyo a causas pet, e-commerce. |
| **¿Qué?** | Producto | Portal / directorio / plataforma digital especializado en mascotas. |

### Cascada estratégica

1. **Aspiración** — Ser la comunidad especializada en mascotas más grande de Colombia.
2. **Dónde jugamos** — Área Metropolitana del Valle de Aburrá y Oriente Antioqueño, con expansión nacional.
3. **Cómo ganamos** — Directorio con la mayor información útil, comunidad entre prestadores y padres de mascotas, marketing de contenidos viral y un porcentaje de ingresos a causas pet.
4. **Capacidades clave** — Marca propia, construcción de comunidad, captación y fidelización de prestadores, plataforma web/móvil robusta.
5. **Métricas de gestión** — Usuarios activos, reseñas, reservas, alcance social, prestadores onboardeados, GMV.

### Audiencia principal

- Hombres y mujeres, **25 a 50 años**, clase media y media-alta.
- Viven en zonas urbanas. Tienen uno o más perros/gatos.
- **Digitales**: compran en línea, piden domicilios, reservan por app.
- Dispositivo principal: **móvil**. Mayor actividad: noches y fines de semana.
- Ven a sus mascotas como familia, no como objetos. Están dispuestos a pagar por bienestar, experiencias y tranquilidad.

> **Retrato de usuario**: *"Mi perro no es una mascota, es parte de la familia."*  
> Valentina · 31 años · Medellín · 2 peludos · compra alimento premium, busca adiestrador, planea vacaciones pet-friendly.

---

## 2. Personalidad de marca

Seis rasgos guían cada decisión creativa. Si una pieza no los transmite, no es Parche Peludo.

| Rasgo | Qué significa en la práctica |
|---|---|
| **Cercano** | Hablamos como amigos, no como marca. Tuteamos, usamos modismos colombianos con mesura. |
| **Cálido** | El cariño por los peludos guía todo. La calidez es emocional, nunca cursi. |
| **Confiable** | Verificamos, citamos, explicamos. Preferimos decir "no sé" antes que inventar. |
| **Curioso** | Preguntamos, exploramos, celebramos lo raro. El mundo pet va más allá de lo obvio. |
| **Juguetón** | Con humor, nunca infantil. Un guiño, no un disfraz. |
| **Solidario** | Apoyamos a quienes apoyan a las mascotas. La comunidad es parte de la marca, no un extra. |

**Somos como…** un amigo que sabe de mascotas · un vecino que recomienda de corazón · un cuaderno del mejor veterinario.  
**No somos…** un catálogo frío · un anuncio de tienda · un experto inalcanzable.

---

## 3. Paleta de colores

### 3.1 Color primario — Teal Parche

El teal es el corazón visual de la marca. Casi todo el sistema se construye sobre él.

| Token CSS | Nombre | Hex | Uso principal |
|---|---|---|---|
| `--teal-parche` | **Teal Parche** | `#79C8D0` | Color primario de marca: fondo del logo, botones CTA, backgrounds de hero. Es la única acción de color que puede usarse sola en grandes superficies. |
| `--teal-deep` | Teal Deep | `#4AA5AD` | Estado hover/pressed de botones teal. Texto de acento sobre fondos claros, eyebrows, links. |
| `--teal-soft` | Teal Soft | `#BEE3E7` | Superficies sutiles: separadores de sección, hover de cards en fondos teal, borders de énfasis. |
| `--teal-mist` | Teal Mist | `#E8F4F6` | Near-white: alternancia de secciones, fondos de forms e inputs, cards dentro de un mundo teal. |
| `--teal-ink` | Teal Ink | `#1E5D64` | Texto terciario / íconos sobre fondos claros. Más oscuro y con más autoridad que el teal base. |

**Regla de proporciones:** 90 % de los diseños deben ser teal + blanco + ink. Los acentos se usan como especias, no como protagonistas.

**Ratio de uso recomendado:**

```
Teal Parche  ████████░░░  40 %  (hero, CTAs, backgrounds de sección)
Paper/White  ██████░░░░░  30 %  (superficie base, cuerpo de contenido)
Ink/Neutrals █████░░░░░░  25 %  (texto, bordes, detalles)
Accents       ░░░░░░░░░░░   5 %  (marigold, coral, leaf — uso puntual)
```

### 3.2 Neutros

| Token CSS | Nombre | Hex | Uso |
|---|---|---|---|
| `--paper` | Paper | `#FFFFFF` | Fondo base del sitio, superficies de cards, texto sobre teal. |
| `--bone` | Bone | `#F7F3EC` | Off-white cálido para layouts editoriales (blog, artículos), secciones alternadas. Importado de las referencias Fetch/Pupford. |
| `--ink` | Ink | `#1E2E33` | Texto primario en fondos claros. No es negro puro — tiene una leve saturación teal que mantiene la calidez. |
| `--ink-muted` | Ink Muted | `#5A6B70` | Texto secundario: descripciones, metadatos, subtítulos. |
| `--ink-faint` | Ink Faint | `#8E9A9E` | Texto terciario: placeholders, captions, notas al pie. |
| `--ink-line` | Ink Line | `rgba(30,46,51,.10)` | Bordes estructurales de 1px para separar elementos. |
| `--ink-line-2` | Ink Line 2 | `rgba(30,46,51,.06)` | Hairlines muy sutiles — separadores internos de cards. |

### 3.3 Acentos

Usar con intención y moderación. **Nunca** usar más de un acento distinto en la misma pieza.

| Token CSS | Nombre | Hex | Uso específico |
|---|---|---|---|
| `--marigold` | Marigold | `#FFD166` | Estrellas de calificación, pills "Nuevo", toques de energía solar. |
| `--coral` | Coral | `#E85D75` | Favoritos / corazones, botón de adopción, acentos de amor. |
| `--orange` | Orange | `#F2994A` | CTA secundario de energía, warnings de tono amigable. |
| `--night` | Night | `#2D3A59` | Superficies oscuras (footers, modales noche), nunca para texto. |

### 3.4 Semánticos

| Token CSS | Nombre | Hex | Cuándo usar |
|---|---|---|---|
| `--success` | Éxito | `#14A985` | Confirmaciones, "verificado", "reserva exitosa". |
| `--warning` | Alerta | `#F2994A` | Avisos no críticos, advertencias amistosas. |
| `--danger` | Peligro | `#E85D75` | Errores, acciones destructivas. |
| `--info` | Info | `#4AA5AD` | Mensajes informativos, tooltips, banners neutros. |

### 3.5 Aliases de superficie (para CSS en componentes)

```css
--bg:        var(--paper)      /* fondo base del documento */
--bg-alt:    var(--teal-mist)  /* secciones alternas */
--bg-warm:   var(--bone)       /* zonas editoriales/blog */
--surface:   var(--paper)      /* superficie de cards */
--surface-2: var(--teal-mist)  /* superficie secundaria / inputs */
--fg:        var(--ink)        /* texto principal */
--fg-muted:  var(--ink-muted)  /* texto secundario */
--fg-faint:  var(--ink-faint)  /* texto terciario */
--border:    var(--ink-line)   /* bordes generales */
```

### 3.6 Reglas de uso de color

- **Botones primarios CTA** → fondo `--teal-parche`, texto blanco `--paper`.
- **Botones secundarios** → fondo blanco/transparente, borde `--teal-parche`, texto `--ink`.
- **Fondos de sección principal** → `--teal-parche` (hero, banners) o `--paper` (contenido).
- **Alternancia de secciones** → `--paper` → `--teal-mist` → `--bone` → `--paper`.
- **Texto sobre teal** → siempre `--paper` (#FFFFFF). Nunca texto oscuro directo sobre teal.
- **Gradientes** → Evitar en identidad core. Máximo: tinte muy suave teal de `--teal-mist` a `--paper`. No radiales, no cónicos.
- **Texto de acento** (eyebrows, labels) → `--teal-deep`.
- **Links** → `--teal-deep`, hover a `--ink` con underline.

---

## 4. Tipografía

### 4.1 Familias

| Rol | Familia | Variable CSS | Cómo cargar |
|---|---|---|---|
| **Display + UI** | **Ubuntu** | `--font-display` / `--font-sans` | Google Fonts: `Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700` |
| **Acento manual** | **Caveat** | `--font-hand` | Google Fonts: `Caveat:wght@400;500;600;700` |
| **Código / mono** | System mono | `--font-mono` | `ui-monospace, SFMono-Regular, Menlo, monospace` |

> **Nota de sustitución:** El wordmark y los materiales editoriales usaban originalmente fuentes propietarias no entregadas. Ubuntu es el sustituto más cercano. Si se obtienen los archivos originales `.woff2`, se cargan como `@font-face` con el mismo token `--font-display`.

**Importar en CSS:**
```css
@import url('https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700&family=Caveat:wght@400;500;600;700&display=swap');
```

### 4.2 Pesos

| Token | Valor | Cuándo usarlo |
|---|---|---|
| `--fw-regular` | `400` | Cuerpo de texto largo, párrafos. |
| `--fw-medium` | `500` | Subtítulos, labels de formulario, precio. |
| `--fw-semi` | `600` | Tags, eyebrows, texto de apoyo en negrita. |
| `--fw-bold` | `700` | H3, H4, H5, navegación, botones secundarios. |
| `--fw-black` | `800` | H1, H2, botón primario, wordmark. |

### 4.3 Escala tipográfica

| Token | Tamaño px | Uso principal |
|---|---|---|
| `--fs-xs` | 12 px | Caption, eyebrow, labels de ícono. |
| `--fs-sm` | 14 px | Texto secundario, `body-sm`, metadatos. |
| `--fs-base` | 16 px | Cuerpo de texto principal (`<p>`). |
| `--fs-md` | 18 px | `body-lg`, párrafos destacados. |
| `--fs-lg` | 20 px | H5, subtítulos de sección. |
| `--fs-xl` | 24 px | H4, precios destacados. |
| `--fs-2xl` | 32 px | H3, títulos de card grandes. |
| `--fs-3xl` | 40 px | H2, títulos de sección. |
| `--fs-4xl` | 56 px | H1 en móvil / tablet. |
| `--fs-5xl` | 72 px | H1 en desktop. |
| `--fs-6xl` | 96 px | Display / hero principal. |

### 4.4 Line-heights

| Token | Valor | Cuándo |
|---|---|---|
| `--lh-tight` | `1.1` | H1, H2, displays masivos. |
| `--lh-snug` | `1.25` | H3, H4, H5 — encabezados medianos. |
| `--lh-normal` | `1.5` | Cuerpo de texto estándar. |
| `--lh-relax` | `1.7` | Texto largo, artículos de blog. |

### 4.5 Letter-spacing

| Token | Valor | Cuándo |
|---|---|---|
| `--tracking-tight` | `-0.02em` | Displays grandes, H1, H2. |
| `--tracking-normal` | `0` | Texto base. |
| `--tracking-wide` | `0.04em` | Caption, labels pequeños. |
| `--tracking-caps` | `0.08em` | Eyebrows en uppercase. |

### 4.6 Jerarquía semántica

```
h1 / .h1   → Ubuntu 800, 72 px, lh-tight, tracking-tight
h2 / .h2   → Ubuntu 700, 40 px, lh-snug, tracking-tight
h3 / .h3   → Ubuntu 800, 32 px, lh-snug
h4 / .h4   → Ubuntu 700, 24 px, lh-snug
h5 / .h5   → Ubuntu 700, 20 px, lh-snug
h6 / .h6   → Ubuntu 600, 16 px, uppercase, tracking-caps, color teal-deep
p  / .body → Ubuntu 400, 16 px, lh-normal, text-wrap: pretty
.eyebrow   → Ubuntu 700, 12 px, uppercase, tracking-caps, color teal-deep
.display   → Ubuntu 800, 96 px, lh 0.95, tracking-tight
.hand      → Caveat 600, tamaño libre — solo para acentos decorativos
```

### 4.7 Reglas de uso tipográfico

- **Nunca usar Caveat** en UI funcional (botones, nav, labels, formularios). Solo para toques editoriales o ilustrativos (ej. un "¡Wag-wag!" decorativo).
- **Máximo dos tamaños** en un mismo componente/card.
- **Text-wrap: balance** en títulos; **text-wrap: pretty** en párrafos.
- Los textos sobre fondos teal usan color `#FFFFFF` — **nunca** usar `--ink` sobre teal.
- **Casing:** Sentence case para todo el copy. Uppercase solo en eyebrows/labels de interfaz y hashtags.

---

## 5. Escala de espaciado

Unidad base: **4 px**. Todo espaciado y padding se deriva de este grid.

| Token | Valor | Uso típico |
|---|---|---|
| `--s-1` | 4 px | Micro-gaps entre íconos e inline elements. |
| `--s-2` | 8 px | Gap interno entre elementos relacionados (ícono + label). |
| `--s-3` | 12 px | Padding interno de badges/tags, gaps entre chips. |
| `--s-4` | 16 px | Padding interno de inputs, margin entre párrafos. |
| `--s-5` | 20 px | Margin inferior de H1, gap entre elementos de formulario. |
| `--s-6` | 24 px | Gutter de grilla, padding lateral de nav. |
| `--s-8` | 32 px | Padding de cards, gap entre secciones internas. |
| `--s-10` | 40 px | Padding vertical de secciones compactas. |
| `--s-12` | 48 px | Padding vertical de secciones medias. |
| `--s-16` | 64 px | Padding top/bottom de secciones de contenido. |
| `--s-20` | 80 px | Ritmo vertical entre secciones del sitio (mínimo). |
| `--s-24` | 96 px | Padding de hero section. |
| `--s-32` | 128 px | Espaciado entre bloques de página muy grandes. |

**Ritmo vertical del sitio:** Las secciones alternan con padding vertical de 80–120 px entre ellas.

---

## 6. Radios de borde

El sistema es **redondeado pero no blando**. Nunca rectos, nunca extremos salvo en pill.

| Token | Valor | Aplicar en |
|---|---|---|
| `--r-sm` | 6 px | Inputs, pills de texto pequeño, chips de categoría pequeños. |
| `--r-md` | 12 px | Botones, badges medianos, tooltips. |
| `--r-lg` | 20 px | Cards de listado, modales, paneles laterales. |
| `--r-xl` | 28 px | Bloques hero, feature cards, search box. |
| `--r-2xl` | 40 px | Grandes contenedores decorativos. |
| `--r-pill` | 999 px | Tags de categoría, botones CTA principales, avatares, badges de estado. |

**Regla de consistencia:** Un mismo nivel de UI debe usar siempre el mismo radio. No mezclar `--r-lg` y `--r-xl` en cards del mismo grid.

---

## 7. Sombras

El sistema de sombras es **suave y elevado, nunca duro ni dramático**.

| Token | Valor CSS | Cuándo usar |
|---|---|---|
| `--shadow-sm` | `0 1px 2px rgba(30,46,51,.06), 0 1px 3px rgba(30,46,51,.04)` | Botones secundarios en reposo, elementos flotantes pequeños. |
| `--shadow-md` | `0 4px 12px rgba(30,46,51,.08), 0 2px 4px rgba(30,46,51,.04)` | Cards en reposo, paneles laterales, inputs enfocados. |
| `--shadow-lg` | `0 12px 32px rgba(30,46,51,.10), 0 4px 8px rgba(30,46,51,.06)` | Cards en hover, modales, dropdowns. |
| `--shadow-xl` | `0 24px 48px rgba(30,46,51,.14), 0 8px 16px rgba(30,46,51,.06)` | Modales grandes, drawers, overlays flotantes. |
| `--shadow-teal` | `0 12px 32px rgba(121,200,208,.40)` | **Solo** en botones CTA teal en reposo. No en hover. |
| `--shadow-inner` | `inset 0 0 0 1px rgba(30,46,51,.06)` | Placeholder de imagen sin foto. |

**Regla hover de cards:** Reposo → `--shadow-md`; hover → `--shadow-lg` + `translateY(-3px)`.

---

## 8. Movimiento y transiciones

La marca no tiene un sistema de motion formal, pero el feeling es:
> **Suave, con rebote, nunca snappy. Nunca agresivo.**

| Token | Valor | Usar en |
|---|---|---|
| `--ease-soft` | `cubic-bezier(.2,.8,.2,1)` | Transiciones de UI generales (hover de links, cambio de color). |
| `--ease-out` | `cubic-bezier(.16,1,.3,1)` | Entradas de elementos (cards, modales, paneles). |
| `--dur-fast` | `150ms` | Feedback inmediato: hover de botón, toggle de checkbox. |
| `--dur-base` | `220ms` | Transiciones de color, sombra, transform. |
| `--dur-slow` | `320ms` | Aparición de modales, paneles, tooltips. |

### Micro-interacciones de marca

- **Cards:** hover `translateY(-3px)` + cambio de shadow. `transition: all 220ms ease-soft`.
- **Botón favorito (corazón):** Al activar → `scale(1.08)` + cambio de fondo a `--coral`.
- **Botón primario CTA:** Press → `translateY(1px)` + reducción de shadow.
- **Entradas de listado:** Fade + `translateY(8px)` → `translateY(0)` al aparecer.
- **Spinners de carga:** Paw print rotando o heart-pulse (en lugar de spinners genéricos).

---

## 9. Logo y marca

### 9.1 Versiones autorizadas

El **isotipo** es un corazón con alas — el "parche" que protege y abraza. Existen cuatro versiones autorizadas:

| Versión | Fondo | Uso |
|---|---|---|
| Isotipo teal sobre blanco | Blanco `#FFFFFF` | Uso primario en fondos claros. |
| Isotipo blanco sobre teal | Teal `#79C8D0` | Hero, banners, portadas. |
| Lockup horizontal (ícono + wordmark) | Blanco | Navegación, firmas, materiales formales. |
| Lockup reverse (blanco) | Oscuro `--ink` / fotografía | Footer, overlays oscuros. |

Cualquier otra versión requiere aprobación del equipo de marca.

### 9.2 Área de resguardo

Espacio mínimo en todos los lados = **x**, donde x es la mitad de la altura del isotipo. Ningún otro elemento puede invadir esta zona.

### 9.3 Tamaños mínimos

| Medio | Tamaño mínimo |
|---|---|
| Impresión | 8 mm |
| Impresión recomendada | 16 mm |
| Pantalla | 24 px |
| Favicon / app icon | 16 px (solo isotipo) |

### 9.4 Usos incorrectos del logo

- ❌ Cambiar los colores del logo.
- ❌ Distorsionar las proporciones (escalar solo en un eje).
- ❌ Aplicar sombras, biseles o efectos 3D.
- ❌ Usar sobre fondos con poca contraste (ej. teal sobre foto saturada sin overlay).
- ❌ Rotar el logo.
- ❌ Usar solo el wordmark sin el isotipo en materiales de marca (siempre van juntos).
- ❌ Abreviar a "PP" o "PA" en comunicaciones.

### 9.5 Nombres autorizados

- **"Parche Peludo"** — nombre completo, siempre con ambas mayúsculas.
- **"Parche"** — en segundas menciones dentro de un mismo texto.
- **"#ElParche"** — en redes sociales y hashtags.
- ❌ Nunca: "Parche Peludo App", "PP", "ParchePeludo" (sin espacio).

---

## 10. Iconografía

Parche Peludo no tiene un set de íconos propio. El sistema usa **Lucide** (CDN, MIT, trazo de 2px redondeado) como estándar — su estilo amigable y redondeado coincide con la voz de marca.

```html
<script src="https://unpkg.com/lucide@latest"></script>
<i data-lucide="paw-print"></i>
```

### Íconos de categoría clave

| Categoría | Ícono Lucide |
|---|---|
| Veterinaria / Salud | `stethoscope`, `heart-pulse` |
| Peluquería | `scissors` |
| Fotografía | `camera` |
| Paseo / Ejercicio | `footprints` |
| Guardería / Hotel | `home` |
| Adiestramiento | `award` |
| Tienda | `shopping-bag` |
| Adopción | `heart` |
| Foro / Comunidad | `message-circle` |
| Favoritos | `heart` (filled via color) |
| Verificado | `check-circle` |
| Ubicación | `map-pin` |
| Búsqueda | `search` |
| Calificación | `star` (filled `--marigold`) |

### Reglas de uso de íconos

- **Peso de trazo:** 2px, terminaciones redondeadas (`stroke-linecap: round`, `stroke-linejoin: round`).
- **Color:** `--teal-ink` sobre fondos claros; `--paper` sobre fondos teal.
- **Tamaño en UI:** 16–20 px para íconos inline; 24–32 px para íconos de acción; 40–64 px para íconos de categoría en grids.
- **Íconos en grids de categoría:** Sobre fondo `--teal-mist` o `--bone`, radio `--r-lg`, padding `--s-4`.
- **Emoji:** Evitar en interfaz funcional. Se permiten en copy (🐾 🐶 🐱 ❤️ ✨) pero no como íconos de navegación o botones.
- **Unicode como ícono:** Nunca. Siempre SVG.

---

## 11. Fotografía e imágenes

### 11.1 Dirección de imagen

- **Cálida, brillante, alto contraste.** Retriever dorado, mestizos, gatos con ojos grandes. Mascotas de frente a cámara.
- **Close-ups de patas, lenguas, narices.** Energía de amistad y ternura.
- **Humanos sonrientes** abrazando a sus mascotas. Luz natural, tonos de piel cálidos.
- **Editorial / fashion applied a mascotas:** lentes de sol en perros, portadas de revista — juguetón, humanizador.

### 11.2 Qué evitar en fotografía

- ❌ Stock frío y genérico.
- ❌ Imágenes clínicas o tristes (veterinario en quirófano, mascotas enfermas).
- ❌ Fondos blancos de estudio sin personalidad.
- ❌ Imágenes sombrías o de bajo contraste.

### 11.3 Placeholder de imagen

Cuando no hay fotografía disponible, usar el **fondo teal `#79C8D0` con el isotipo blanco centrado**. Este es el placeholder oficial y reconocible de la marca.

```css
/* Placeholder de imagen de listing */
background: var(--teal-parche);
display: flex;
align-items: center;
justify-content: center;

/* Dentro: */
<img src="logo-mark-white.png" style="width: 72px; opacity: .9;">
```

### 11.4 Overlays sobre fotografía

- Overlay teal sobre foto hero: `background: rgba(121,200,208,.45)`.
- Elementos frosted sobre foto: `backdrop-filter: blur(20px); background: rgba(255,255,255,.7)`.
- Nunca overlay oscuro puro — preferir el overlay teal para mantener la identidad.

---

## 12. Layout y grilla

### 12.1 Contenedores

| Token | Valor | Usar para |
|---|---|---|
| `--container` | 1200 px | Ancho máximo del contenido principal del sitio. |
| `--container-sm` | 720 px | Artículos de blog, formularios, páginas de detalle. |
| `--gutter` | 24 px (`--s-6`) | Padding lateral del contenedor en pantalla. |

```css
.container {
  max-width: var(--container);
  margin: 0 auto;
  padding: 0 var(--gutter);
}
```

### 12.2 Grilla de cards

- **4 columnas** en desktop (≥1200px) — `gap: 24px`
- **3 columnas** en tablet (768–1199px)
- **2 columnas** en móvil grande (480–767px)
- **1 columna** en móvil pequeño (<480px)

### 12.3 Secciones de página

Las secciones del sitio siguen un ritmo de alternancia:

```
1. Nav sticky            — blanco, 72px de altura
2. Hero                  — fondo teal-mist → paper (gradiente muy suave)
3. Categorías            — paper
4. Listados destacados   — paper
5. Bloque "Únete"        — teal-parche (fondo de color)
6. Testimonios           — bone
7. Blog                  — paper
8. Footer                — ink (#1E2E33, oscuro)
```

---

## 13. Componentes — Navegación

### Nav sticky

```
Altura:           72px  (--nav-h)
Fondo:            rgba(255,255,255,.92) + backdrop-filter: blur(10px)
Borde inferior:   1px solid var(--ink-line-2)
Posición:         sticky top:0, z-index:40
Max-width:        1200px centrado
Padding lateral:  24px
```

**Elementos de nav (de izquierda a derecha):**

| Elemento | Estilo |
|---|---|
| Logo + wordmark | Isotipo teal, wordmark Ubuntu 900, 20px, `--ink`. Gap 10px. |
| Links de navegación | Ubuntu 600, 15px, `--ink`. Gap 28px entre links. Sin underline en reposo. |
| "Iniciar sesión" | Ubuntu 700, 14px, `--ink`. Solo texto, sin borde. |
| Botón "Unirme al Parche" | Ubuntu 800, 14px, `--paper`. Fondo `--teal-parche`. Pill (`border-radius: 999px`). Padding `10px 18px`. `--shadow-teal`. |

**Hover de links de nav:** color `--teal-deep`, sin underline.

---

## 14. Componentes — Botones

### Botón primario (CTA principal)

```css
font-family:   var(--font-sans);
font-weight:   var(--fw-black);    /* 800 */
font-size:     var(--fs-sm);       /* 14px — aumentar a 16px en hero */
padding:       10px 20px;
border-radius: var(--r-pill);      /* 999px */
background:    var(--teal-parche);
color:         var(--paper);
border:        none;
box-shadow:    var(--shadow-teal);
cursor:        pointer;
transition:    all var(--dur-base) var(--ease-soft);
```

**Estados del botón primario:**

| Estado | Cambio |
|---|---|
| Reposo | `background: #79C8D0`, `shadow-teal` |
| Hover | `background: var(--teal-deep)` → `#4AA5AD`, sombra se reduce |
| Press/Active | `translateY(1px)`, sombra mínima |
| Disabled | `opacity: .5`, `cursor: not-allowed` |

### Botón secundario (outline)

```css
background:    transparent;
border:        2px solid var(--teal-parche);
color:         var(--ink);
border-radius: var(--r-pill);
padding:       9px 20px;

/* Hover */
background:    var(--teal-mist);
```

### Botón de adopción / acento coral

```css
background:    var(--coral);  /* #E85D75 */
color:         var(--paper);
border-radius: var(--r-pill);
/* Hover: background ligeramente más oscuro */
```

### Reglas generales de botones

- **Nunca** usar `border-radius` recto en botones. Mínimo `--r-md` (12px).
- Siempre `cursor: pointer`.
- El texto de los botones va en **sentence case**, nunca ALL CAPS.
- Los botones CTA llevan verbos activos: *Buscar · Reservar · Unirme · Adoptar · Explorar*.
- Tamaño mínimo de área clickeable (especialmente en móvil): **44px de altura**.

---

## 15. Componentes — Tarjetas de listado

La card de listado es el componente más importante del sitio.

### Anatomía de la card

```
┌──────────────────────────────┐
│  FOTO  (4:3 · overflow:hidden) │  ← placeholder teal + isotipo si sin foto
│  [Badge verificado] [Fav ♡]   │
│  [• Ahora Abierto / Cerrado] │
├──────────────────────────────┤
│  CATEGORÍA (eyebrow, teal)   │
│  Nombre del servicio (h3)    │
│  Descripción breve           │
│  [Tag] [Tag] [Tag]           │
│  ──────────────────          │
│  Empieza desde $10.000   ★4.8│
└──────────────────────────────┘
```

### Estilos de la card

```css
/* Contenedor */
background:    var(--paper);
border-radius: var(--r-lg);       /* 20px */
border:        1px solid var(--ink-line-2);
box-shadow:    var(--shadow-md);
overflow:      hidden;
cursor:        pointer;
transition:    all 250ms cubic-bezier(.2,.8,.2,1);

/* Hover */
transform:     translateY(-3px);
box-shadow:    var(--shadow-lg);
```

### Elementos internos de la card

| Elemento | Estilo |
|---|---|
| Foto | `aspect-ratio: 4/3`, `object-fit: cover`, width 100%. |
| Badge "Verificado" | Fondo `--paper`, texto `--teal-ink`, `font-weight: 800`, `font-size: 11px`, `border-radius: 999px`. |
| Badge "Reserva Instantánea" | Fondo `--ink`, texto blanco, mismo tamaño. |
| Badge estado (Abierto/Cerrado) | Fondo semi-transparente verde claro o rojo claro. Dot de color a la izquierda. |
| Botón favorito (♡) | Fondo blanco, 36×36px, `border-radius: 999px`, color `--coral`. Activo: fondo `--coral`, texto blanco, `scale(1.08)`. |
| Eyebrow categoría | `font-size: 11px`, `letter-spacing: .08em`, uppercase, `color: --teal-deep`, `font-weight: 800`. |
| Título | `font-size: 18px`, `font-weight: 900`, `color: --ink`. |
| Descripción | `font-size: 13px`, `color: --fg-muted`, `line-height: 1.4`. |
| Tags de servicio | `font-size: 11px`, `font-weight: 700`, `padding: 3px 9px`, `border-radius: 999px`, `background: --teal-mist`, `color: --teal-ink`. |
| Precio | `font-size: 13px`, `font-weight: 800`, `color: --ink`. |
| Rating | `font-size: 13px`, `font-weight: 700`. Estrella en `--marigold`. |

---

## 16. Componentes — Formularios e inputs

### Input de búsqueda (hero)

```css
/* Wrapper */
background:    var(--paper);
border:        2px solid var(--teal-parche);
border-radius: var(--r-pill);    /* 999px */
padding:       4px;
box-shadow:    var(--shadow-md);
display:       flex;
align-items:   center;

/* Input interno */
border:        none;
outline:       none;
padding:       14px 12px;
font-size:     15px;
font-weight:   500;
background:    transparent;
flex:          1;

/* Botón de búsqueda (dentro del pill) */
background:    var(--teal-parche);
color:         var(--paper);
border-radius: var(--r-pill);
padding:       12px 26px;
font-weight:   800;
font-size:     15px;
border:        none;
```

### Inputs de formulario estándar

```css
background:    var(--surface-2);   /* --teal-mist */
border:        1px solid var(--ink-line);
border-radius: var(--r-sm);        /* 6px */
padding:       12px 16px;
font-size:     var(--fs-base);     /* 16px */
color:         var(--fg);
transition:    border-color var(--dur-base) var(--ease-soft),
               box-shadow var(--dur-base) var(--ease-soft);

/* Focus */
border-color:  var(--teal-parche);
box-shadow:    0 0 0 3px rgba(121,200,208,.20);
outline:       none;

/* Placeholder */
color:         var(--fg-faint);    /* --ink-faint */

/* Error */
border-color:  var(--danger);
box-shadow:    0 0 0 3px rgba(232,93,117,.15);
```

### Labels

```css
font-size:     var(--fs-sm);    /* 14px */
font-weight:   var(--fw-semi);  /* 600 */
color:         var(--fg);
margin-bottom: var(--s-2);     /* 8px */
display:       block;
```

---

## 17. Componentes — Badges y etiquetas

### Tipos de badge

| Badge | Fondo | Texto | Uso |
|---|---|---|---|
| **Verificado** | `--paper` | `--teal-ink` | Listado revisado por el equipo. |
| **Reserva Instantánea** | `--ink` | `--paper` | Disponible sin esperar confirmación. |
| **Ahora Abierto** | `#E6F6EC` (verde muy claro) | `#1F6B3E` | Estado en tiempo real. |
| **Ahora Cerrado** | `#FBEBE7` (rojo muy claro) | `#8A2A1A` | Estado en tiempo real. |
| **Destacado** | `--marigold` | `--ink` | Listado premium/patrocinado. |
| **Nuevo** | `--marigold` | `--ink` | Servicio recién agregado. |

### Estilo base de badge

```css
font-size:     11px;
font-weight:   800;
padding:       5px 10px;
border-radius: var(--r-pill);   /* 999px */
display:       inline-flex;
align-items:   center;
gap:           6px;
white-space:   nowrap;
```

### Tags de categoría / chips

```css
font-size:     11px;
font-weight:   700;
padding:       3px 9px;
border-radius: var(--r-pill);
background:    var(--teal-mist);
color:         var(--teal-ink);
border:        1px solid var(--teal-soft);
```

---

## 18. Componentes — Sección Hero

### Estructura

```
Eyebrow:   "Directorio · Comunidad · #ElParche"
H1:        "Encuentra lo mejor para tu peludo."
Subtítulo: "Veterinarios, paseadores, peluquerías, fotógrafos…"
Search:    [🔍 Input de búsqueda pill ––––––––––— [Buscar]]
Tags:      Más buscados: [Veterinario] [Peluquería] [Paseos] …
```

### Estilos hero

```css
/* Fondo sutil — no gradiente agresivo */
background: linear-gradient(180deg, var(--teal-mist) 0%, var(--paper) 100%);
padding:    72px 24px 80px;
text-align: center;

/* H1 */
font-size:  64px;
font-weight: 900;
line-height: 1.05;
letter-spacing: -.02em;

/* La palabra "peludo" en H1 */
font-style: italic;
color:      var(--teal-deep);

/* Subtítulo */
font-size:  19px;
color:      var(--fg-muted);
max-width:  640px;
margin:     0 auto 28px;

/* Quick tags "Más buscados" */
font-size:  13px;
padding:    6px 12px;
border-radius: 999px;
background: var(--paper);
border:     1px solid var(--ink-line);
color:      var(--ink);
```

---

## 19. Componentes — Estados interactivos

### Resumen de hover/press por tipo de elemento

| Elemento | Hover | Press |
|---|---|---|
| Link de texto | Color `--teal-deep` + underline offset 3px | — |
| Botón primario | `background: --teal-deep`, sombra reduce | `translateY(1px)`, sombra mínima |
| Botón secundario | `background: --teal-mist` | `translateY(1px)` |
| Card de listado | `translateY(-3px)` + `shadow-lg` + `scale(1.005)` | — |
| Tag / chip | `background: --teal-soft`, `border-color: --teal-parche` | — |
| Botón favorito | — | `scale(1.08)` + fondo `--coral` (toggle) |
| Link de nav | Color `--teal-deep` | — |

### Selección de texto

```css
::selection {
  background: var(--teal-soft);
  color:      var(--ink);
}
```

---

## 20. Voz y tono

### Idioma y persona

- **Español (Colombia)** es el idioma primario.
- Tú, nunca usted. La marca habla como un amigo en el parque canino.
- Segunda persona del plural ("nosotros") cuando la marca habla de sí misma.

### Lexicón de marca

Siempre preferir estas palabras — son el alma del brand:

| ✅ Usar | ❌ Evitar |
|---|---|
| **Peludos** (mascotas, con cariño) | "mascotas" en exceso |
| **Padres / mamás / papás de mascotas** | "dueños" |
| **Parche** (crew, comunidad) | abreviaturas fuera de contexto |
| **Tutores** (en copy formal/directorio) | lenguaje corporativo frío |
| **Bienestar, comunidad, propósito** | — |
| Diminutivos con mesura: *peluditos*, *peludines* | diminutivos en exceso |

### Hashtags (PascalCase siempre)

`#ElParche` · `#PropósitoPeludo` · `#PadresPeludos` · `#AdoptaNoCompres`

### Casing y puntuación

- **Sentence case** para todos los headlines y cuerpo. No ALL CAPS (salvo eyebrows de interfaz en CSS).
- **Titlecase** para nombres de producto: *Parche Peludo, El Parche, Foro Mascotas, Directorio, Adopción*.
- Signos de exclamación e interrogación de apertura: `¡...!` y `¿...?` (español correcto).
- Precios en formato colombiano: `$10.000`, `$165.000` (punto como separador de miles).
- Ellipsis para calidez: "Todos queremos que nuestros peludos nos acompañen toda la vida…"

### Reglas de redacción

```
✅ Sí                                  ❌ No
──────────────────────────────────────────────────────
Tuteamos: "Tu peludo", "tu vet"        "Estimado usuario"
Colombianismos con mesura              Jerga forzada o extrema
Frases cortas. Punto > coma.           Párrafos largos y densos
Verbos activos: Reserva, Descubre      "Los mejores del mercado"
Datos concretos cuando sumen           Clichés publicitarios
Humor tierno, nunca a costa de otros   Burla o ironía negativa
```

### Ajuste de tono por contexto

| Contexto | Tono |
|---|---|
| Redes sociales | Cómplice, ágil, con humor. |
| Contenido educativo | Paciente, claro, confiable. |
| Soporte / atención | Empático, resolutivo, sin rodeos. |
| Errores y alertas | Directo, breve, sin culpar. |
| Correos de bienvenida | Cálido, entusiasta, personal. |

### Ejemplos de copy listo para usar

| Contexto | Copy |
|---|---|
| Hero headline | *"Encuentra lo mejor para tu peludo."* |
| Hero sub | *"El directorio de servicios, experiencias y comunidad de peludos más grande de Colombia."* |
| CTA primario | **Unirme al Parche** |
| CTA secundario | **Explora el directorio** · **Entra al Foro** |
| Email bienvenida | *"Hola, parce. Bienvenido al Parche. Aquí están los prestadores más recomendados cerca de tu casa para empezar."* |
| Estado vacío | *"Por acá todavía no hay prestadores. Cuéntanos qué buscas y te avisamos cuando llegue uno."* |
| Error de pago | *"No pudimos procesar el pago. Revisa los datos de tu tarjeta o intenta con otra."* |
| Push de reserva | *"Tu cita con Juan (paseador) quedó confirmada para mañana 7:00 am."* |

---

## 21. Patrones y fondos de marca

### Patrón de huella de pata

El motivo de la huella 🐾 se usa como patrón de fondo decorativo:
- **Sobre fondos teal:** opacidad ~10%, color blanco.
- **Sobre fondos oscuros (--ink):** opacidad ~8%, color blanco.
- **Nunca sobre fondos blancos** — pierde impacto.
- Se aplica como `background-image` repetida o como pseudo-elemento.
- Escala libre, ángulo libre, pero manteniendo el mismo tamaño y densidad dentro de una misma pieza.

### Modo de uso de superficies

| Superficie | Cuándo |
|---|---|
| Full-bleed teal | Hero sections, portadas, banners de sección, tarjetas sociales. |
| Full-bleed fotografía | Cuando la foto es el protagonista con overlay teal. |
| Paper/blanco | Contenido principal del directorio, listados. |
| Bone/crema | Blog, artículos, contenido largo, editorial. |
| Ink oscuro | Footer, páginas de autenticación nocturna (opcional). |

---

## 22. Usos incorrectos — qué evitar

### Color

- ❌ Gradientes radiales, cónicos o agresivos en superficies grandes.
- ❌ Más de un color de acento (marigold, coral, orange) in la misma pieza.
- ❌ Texto `--ink` directamente sobre fondo `--teal-parche`.
- ❌ Inventar colores nuevos no listados en el sistema de tokens.
- ❌ Overlay blanco muy intenso sobre teal (pierde el color de marca).

### Tipografía

- ❌ Usar Caveat/hand en UI funcional (botones, nav, inputs).
- ❌ Mezclar más de 2 tamaños tipográficos distintos en un mismo card.
- ❌ ALL CAPS en copy extenso.
- ❌ Fuentes no autorizadas (nunca Inter, Roboto, Arial a menos que sea sistema de respaldo de emergencia).
- ❌ `font-weight: 400` en botones o labels de interfaz (mínimo 600).

### Componentes

- ❌ Bordes rectos (`border-radius: 0`) en cards, botones o inputs.
- ❌ Sombras muy marcadas tipo `drop-shadow(4px 4px 0 black)`.
- ❌ Botones sin estado hover visible.
- ❌ Imágenes en cards sin `object-fit: cover`.
- ❌ Cards sin `overflow: hidden` (rompe el border-radius en la foto).

### Logo

- ❌ Recolorizar el logo.
- ❌ Usar solo el wordmark sin isotipo.
- ❌ Colocar el logo sobre fondos de bajo contraste sin overlay.
- ❌ Distorsionar proporciones.
- ❌ Usar "PP" o "ParchePeludo" (sin espacio) como abreviación.

### Voz

- ❌ "Dueño/a" — siempre "padre/madre de mascota" o "tutor/a".
- ❌ Jerga veterinaria sin traducir.
- ❌ Clichés publicitarios ("líderes del mercado", "solución integral").
- ❌ Humor a costa de otros (nunca burla o ironía negativa).
- ❌ Mayúsculas sostenidas para énfasis en copy.

---

## Tokens CSS — referencia rápida

```css
/* Importar en el <head> de cada página */
<link rel="stylesheet" href="colors_and_type.css">

/* O copiar el @import directamente: */
@import url('https://fonts.googleapis.com/css2?family=Ubuntu:ital,wght@0,300;0,400;0,500;0,700;1,400;1,700&family=Caveat:wght@400;500;600;700&display=swap');
```

Todos los tokens están definidos en `:root` en `colors_and_type.css`. Usar siempre `var(--nombre-token)` en lugar de valores hardcoded — esto garantiza coherencia y facilita ajustes globales.

---

## 23. Referencia del Sitio Actual y Estrategia de Migración V2

### 23.1 Referencia de Producción y Plantilla
*   **Sitio Web Original:** [Parche Peludo (Live)](https://parchepeludo.com/)
*   **Base Tecnológica Original:** WordPress + Plantilla [Listeo Directory & Listings](https://themeforest.net/item/listeo-directory-listings-wordpress-theme/23239259).
*   **Color de Acento en Producción:** Exactamente `#79c8d0` (Teal), definido en la plantilla original como `--primary-color: #79c8d0`.
*   **Fuente Core en Producción:** `Ubuntu` (Google Fonts).

### 23.2 Mapeo y Arquitectura de la Página Principal (Home)
El sitio original se estructuraba sobre Elementor con los siguientes componentes esenciales, que deben migrarse con alta fidelidad y mejoras estéticas notables en la versión V2:

#### 1. Navegación e Identidad (Header Nav)
*   **Logo original:** Logotipo circular en color blanco (`cropped-Logo-Parche-Peludo-Circulo.webp`).
*   **Enlaces activos:** Enlaces a Directorio, Planes, Foro, Blog y Contacto.
*   **CTA original:** Botón destacado en barra de navegación para registro/acceso.

#### 2. Hero Section
*   **Título Principal:** *"Servicios para tu mascota a domicilio"* (Ubuntu, peso 600, color #ffffff).
*   **Subtítulo:** *"En Parche Peludo cuidamos a tu mascota con el mismo amor y dedicación con el que tú lo haces."*
*   **Imagen de fondo:** Fotografía de un perro retriever y un gato sobre fondo blanco/natural (`fondo-mascotas-2.jpg`).
*   **Acción Hero:** Botón de llamada a la acción *"Ver Servicios"*.

#### 3. Carrusel de Categorías Core (Servicios)
El carrusel dinámico original contiene las siguientes tarjetas de servicios a domicilio:
1.  **Peluquería y baño:** *"Aseo para tu peludo con cuidado y amor, a domicilio"* (Fondo: `servicios-mascotas-banner.jpg`).
2.  **Adiestramiento:** *"Educación para tu peludo en el que aprenderá y se divertirá, a domicilio"* (Fondo: `categoria-adiestramiento.jpg`).
3.  **Paseos:** *"Dale bienestar a tu peludo a través de paseos grupales o individuales"* (Fondo: `categoria-paseadores-2.jpg`).
4.  **Cuidador:** *"Cuidado seguro de tu peludo en casa o guardería"* (Fondo: `categoria-guarderia.jpg`).
5.  **Fotografía y videos:** *"La mejor fotografía profesional para tu peludo"* (Fondo: `5b15d377-2dea-48f7-a8bb-64224832baa4.jpeg` - *Nota: corregido el copy original de pastelería*).
6.  **Repostería:** *"La mejor repostería para tu peludo y sus amigos"* (Fondo: `Reposteria-Mascotas.jpg`).
7.  **Fiestas:** *"Celebra con tu peludo su cumpleaños, su adopción u otro evento especial"* (Fondo: `experiencias-mascotas-banner.webp`).

#### 4. Propuesta de Valor ("Únete a #ElParche")
*   **CTA Principal:** *"Únete a #ElParche"* (Subtítulo: *"Si prestas servicios o tienes un negocio para mascotas, Parche Peludo es tu lugar. Llega a miles de personas"*).
*   **Pestañas/Flujos:**
    *   *Llega a muchas más personas 🚀:* *"Crece tu alcance o el de tu negocio llegando a miles de Padres de Mascotas..."* (Botón: "Probar gratis 3 meses").
    *   *¿Eres Independiente?:* *"Llega a miles de padres de mascotas con Parche Peludo"* (Fondo: `categoria-veterinarios.jpg`).
    *   *¿Eres Negocio?:* *"Llega a miles de padres de mascotas con Parche Peludo"* (Fondo: `centro-canino.jpg`).

#### 5. Pilares y Compromiso Social
*   **¿Quiénes somos?:** *"Somos una comunidad especializada en mascotas y sus humanos"*
*   **Nuestro propósito:** *"Conectamos padres de mascotas con un amplio ecosistema de aliados, servicios y experiencias..."*
*   **Compromiso social:** *"Una parte de nuestros ingresos es dedicado a alimento y salud para animales que lo necesitan"* (Fondo: `apoyo-mascotas-parche-peludo.jpg`).

#### 6. Footer (Estructura de Enlaces)
*   **Enlaces de Interés:** Inscribirme como Independiente · Inscribirme como Negocio · Foro · Blog Mascotas.
*   **Información Legal y Enlaces 2:** Nosotros · Contacto · Términos y Condiciones · Política de Tratamiento de Datos Personales.
*   **Contacto:** Medellín, Colombia · Celular: 3012773594 · Correo: atencion@parchepeludo.com
*   **Redes Sociales:** Instagram (`@parche_peludo`) y TikTok (`@parchepeludo`).

#### 7. Chatbot de Asistente IA (Apolo)
*   Widget interactivo flotante en la esquina inferior derecha.
*   **Nombre:** *"Apolo"* (Avatar del bulldog francés).
*   **Voz de Marca:** *"¡Hola! ¿Te puedo ayudar?"*

### 23.3 Estrategia de Optimización V2
Al reconstruir este ecosistema en Stitch V2, implementaremos las siguientes optimizaciones clave de UI/UX premium:
1.  **Limpieza Tipográfica y de Grillas:** Reemplazo de los layouts densos de Elementor por una grilla moderna basada en flexbox con gutters de 24px estables, alineada con la escala de espaciados base 4px de la marca.
2.  **Higiene Visual en las Cards:** Las tarjetas de servicios y planes se optimizan con un ratio de 4:3 estricto en imágenes, bordes de 20px (`--r-lg`), y sombras difusas elegantes (`--shadow-md` a `--shadow-lg` en hover).
3.  **Mapeo Tipográfico Moderno:** Elevación de la tipografía general a `Outfit` para títulos con tracking estrecho (`-0.02em`) para mayor impacto visual, e `Inter` para texto de lectura para excelente legibilidad.
4.  **Lucide Icons nativos:** Sustitución de íconos genéricos por un ecosistema Lucide unificado de 2px de grosor de trazo para mantener la consistencia amigable.
5.  **Micro-animaciones fluidas:** Transiciones suaves de 220ms (`--ease-soft`) en hover de cards, inputs y CTAs principales.

---

## 24. Guía de Desarrollo Seguro en Entorno Local V2

### 24.1 Uso de Tema Hijo (Child Theme)
Para garantizar la durabilidad de las personalizaciones visuales de la versión V2, todas las ediciones de plantillas de página, lógica PHP personalizada y reglas CSS se realizarán exclusivamente dentro del tema hijo (`listeo-child`). Esto permite actualizar el tema principal (*Listeo*) en producción sin riesgo de sobreescribir ni perder el desarrollo de marca.

*   **Estructura básica del Child Theme:**
    *   `style.css`: Hereda los estilos del tema padre y define las clases de diseño premium personalizadas.
    *   `functions.php`: Encola de manera limpia los estilos y permite añadir ganchos (*hooks*) de WordPress para lógica personalizada.

### 24.2 Integración y Coexistencia con Elementor
*   **Ajustes Globales (Site Settings):** Los colores y fuentes de la marca (Outfit, Inter, Teal #79C8D0, etc.) se definen a nivel global en la configuración del sitio de Elementor. Esto asegura que cualquier nuevo bloque visual mantenga los tokens de diseño de forma nativa sin configuraciones individuales manuales.
*   **Páginas Clonadas para Desarrollo:** Para editar de manera segura con Elementor sin afectar el servicio activo, se trabajará siempre sobre páginas clonadas de pruebas (por ejemplo, `/home-v2-pruebas`). Una vez validadas y aprobadas, se asignan como oficiales.

### 24.3 Control de Versiones con Git
El uso de Git actúa como la red de seguridad del proyecto, registrando cada avance y permitiendo retroceder en caso de errores de código de forma inmediata.
*   **Flujo recomendado:**
    1.  `git init` en el directorio raíz.
    2.  Registrar el estado inicial limpio en un commit (`git add .` y `git commit -m "Estado inicial limpio de V1"`).
    3.  Crear ramas de características (`branches`) para desarrollos específicos (ej. `git branch feature-child-theme`).
    4.  Hacer commits frecuentes por cada componente o ajuste visual funcionando.

### 24.4 Estrategia de Publicación y Migración en Hostinger
La migración de local a producción en Hostinger se realiza en tres fases controladas:
1.  **Local Development:** Edición y pruebas en tu computador.
2.  **Staging Environment:** Clon privado y seguro en la nube de Hostinger (por ejemplo, `pruebas.parchepeludo.com`) para pruebas reales en servidores.
3.  **Production Release:** Empuje (*push*) de los cambios aprobados en Staging al sitio en vivo con un solo clic en Hostinger, tras realizar una copia de seguridad manual de respaldo.

---

*Manual de identidad gráfica Parche Peludo · v1.0 · 2025*  
*Contacto de marca: atencion@parchepeludo.com*  
*Este documento es la base para prototipos web. Verificar contra la versión más reciente del Brand Manual antes de producir piezas finales.*
