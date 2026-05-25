# Parche Peludo V2 — Workspace de Desarrollo

Directorio/comunidad/marketplace de servicios para mascotas en Colombia, sobre **WordPress +
tema Listeo**. Este es el **workspace de trabajo** de Claude Code y AntiGravity; el sitio real
vive en LocalWP y se edita a través del junction `sitio_local`.

## Fuentes de verdad (leer antes de trabajar)

- **`DESIGN.md`** — Manual de identidad de marca V2 (colores, tipografía, espaciado, componentes,
  voz y tono). Es la autoridad para cualquier decisión visual. Secciones 23–24: mapeo del sitio
  real y guía de desarrollo seguro.
- **`INSTRUCTIVO-MIGRACION-V2.md`** — Protocolos de colaboración, rutas del entorno y flujo de Git.

## Equipo y roles

- **Miguel** — Product Owner. Dirección de marca y validación estética. **No es desarrollador**:
  dale instrucciones sencillas, paso a paso e interactivas; nunca asumas conocimiento técnico.
- **AntiGravity** — AI Design Architect. Dueño de `DESIGN.md`, sistema de diseño en Stitch y tokens CSS.
- **Claude Code** — AI Core Developer. Lógica PHP, CSS, integraciones y depuración en el **tema hijo**.

## Rutas del entorno (Windows)

- **Workspace (aquí):** `C:\Users\Miguel Ángel Rúa\Dropbox\PC\Desktop\AntiGravity\Parche Peludo`
- **Instalación real de LocalWP:** `C:\Users\Public\parche-peludo-local` (en `C:\Users` y sin
  acentos, requisito de LocalWP). Web root: `…\app\public`.
- **Junction `sitio_local`** → `C:\Users\Public\parche-peludo-local\app\public`. Editar archivos
  WP a través de esta unión refleja los cambios en el sitio activo al instante.
- **Único lugar donde editar:** `sitio_local/wp-content/themes/listeo-child/`
  (`style.css` = tokens de marca + clases premium; `functions.php` = enqueue y hooks).
- **Lanzador de LocalWP:** `Lanzar_Local.bat`. **LocalWP SIEMPRE debe abrirse desde este .bat**
  (redirige `%USERPROFILE%`/`%APPDATA%`/`%LOCALAPPDATA%` a `C:\LocalWPData\AppData` para que
  MySQL/Electron no fallen por los acentos del nombre de usuario). No se arranca por terminal.

## Qué NO tocar

- **Tema padre `listeo`** (`2.0.37`) — prohibido editarlo. Toda personalización va al tema hijo.
- **Core de WordPress** (`wp-admin`, `wp-includes`, `wp-*.php`) y **plugins de terceros**
  (Elementor, WooCommerce, MercadoPago, Rank Math, LiteSpeed, ecosistema Listeo…).
- Plugins `*.bak` (residuos de Hostinger, desactivados).
- `wp-config.php` (credenciales/salts locales).

## Sistema de diseño — reglas operativas

- **Color:** primario Teal Parche `--teal-parche #79C8D0` (+ `--teal-deep/-soft/-mist/-ink`).
  90% del diseño = teal + blanco + ink; acentos (`--marigold`, `--coral`, `--orange`) como
  especias, máximo uno por pieza. Texto sobre teal siempre blanco. Detalle completo en `DESIGN.md` §3.
- **Tipografía ACTIVA:** **Ubuntu** (display/UI) + **Caveat** (acento manual, nunca en UI funcional).
  Coincide con producción y con lo ya implementado en el tema hijo.
  > ⚠️ Contradicción a resolver con Miguel: `DESIGN.md` §23.3/§24.2 e `INSTRUCTIVO` §5.1 mencionan
  > `Outfit`/`Inter` para V2, pero §1–22 (y §22 "qué evitar") **prohíben Inter** y fijan Ubuntu.
  > El código y producción usan Ubuntu/Caveat → se mantiene eso salvo que Miguel decida lo contrario.
- **Siempre usar tokens** `var(--…)` (espaciado base 4px `--s-*`, radios `--r-*`, sombras
  `--shadow-*`, transiciones `--ease-*`/`--dur-*`), nunca valores hardcodeados.
- Mantener los `!important` de las utilidades del hijo: ganan especificidad sobre Listeo/Elementor.

## Próxima Tarea Prioritaria: Home V2 (Diseños Stitch)

- **Diseños de referencia:** Ubicados en `Diseño/Stitch/landing_desktop.html` (escritorio) y `Diseño/Stitch/landing_mobile.html` (móvil). Analizar su estructura, clases Tailwind y HTML para replicar su aspecto visual de forma idéntica.
- **Constructor obligatorio:** Se debe utilizar **Elementor** de forma visual en la instalación de WordPress local.
- **Protocolo de Seguridad (Obligatorio):**
  1. **PROHIBIDO** reemplazar o alterar la página de Home/Inicio que está activa en producción y local.
  2. Crear una **nueva página** en la base de datos de WordPress llamada **"Home V2 Pruebas"** (con el slug `/home-v2-pruebas`).
  3. Realizar el 100% del desarrollo y pruebas sobre esta nueva página clonada.
  4. La asignación como página de inicio oficial solo ocurrirá cuando Miguel Ángel Rúa valide la estética y dé su aprobación explícita.

## Metodología de maquetación limpia

1. **Elementor — estilos globales:** definir colores/fuentes de marca en *Site Settings* (Ajustes del Sitio) de Elementor para que los bloques nuevos hereden los tokens nativamente.
2. **Clases, no inline:** crear clases limpias en `style.css` (p. ej. `.tarjeta-premium-v2`, `.btn-marca-primario`, `.badge-verificado-v2`) y asignarlas en el campo "Clases CSS" de Elementor. Evitar overrides inline agresivos.
3. **Probar en el navegador** cada cambio antes de darlo por bueno.


## Git (red de seguridad)

- Repo: `https://github.com/miguelangelrua1-design/parche-peludo-v2.git` (privado). Rama: `main`.
- Solo se rastrea: `DESIGN.md`, `INSTRUCTIVO-MIGRACION-V2.md`, este `CLAUDE.md`, el kit de marca
  (`Diseño/…`) y `sitio_local/wp-content/themes/listeo-child/*`. Todo lo demás de `sitio_local/`,
  el core de WP, plugins, backups y `_parchepeludo.com/` están ignorados (ver `.gitignore`).
- **Flujo:** `git status` limpio → `git checkout -b feature-<tarea>` → commits pequeños y claros
  tras probar cada cambio en el navegador. No commitear nada fuera del scope rastreado.

## Voz y contenido

- **Español (Colombia)**, tuteo. La marca habla como un amigo del parque canino.
- Léxico: **"peludos"**, **"padres/madres de mascotas"** o **"tutores"** — nunca "dueños".
  Sentence case (no ALL CAPS), precios en formato `$10.000`. Detalle en `DESIGN.md` §20.

## Cómo correr el sitio

Abrir LocalWP **desde `Lanzar_Local.bat`** y arrancar el sitio "Parche Peludo". Acceso a BD
(`local` / `localhost`) vía Adminer u "Open shell" de LocalWP.
