# INSTRUCTIVO DE COLABORACIÓN Y MIGRACIÓN V2
## Proyecto: Parche Peludo V2 (WordPress Local + Control de Versiones + Red de Seguridad)
> **Fecha:** Mayo de 2026  
> **Destinatario:** Claude Code, AntiGravity y Miguel (Propietario del Proyecto)

Este documento contiene toda la información técnica, lógica, rutas, y protocolos de trabajo del entorno seguro local de **Parche Peludo V2**. Su propósito es servir de **fuente de verdad unificada** para que tanto **Claude Code** como **AntiGravity** puedan trabajar en equipo de forma coordinada, simultánea y sin riesgos.

---

## 1. El Equipo de Trabajo (Roles)
1.  **Miguel (Product Owner):** Dirección de marca, validación estética y administración de LocalWP / Elementor. No es desarrollador, por lo que las instrucciones deben ser sencillas e interactivas.
2.  **AntiGravity (AI Design Architect):** Administrador del Manual de Identidad Visual (`DESIGN.md`), sincronizador del sistema de diseño en Stitch y curador de tokens CSS.
3.  **Claude Code (AI Core Developer):** Constructor de lógica PHP, optimizador de consultas, integrador local y depurador de código en el Tema Hijo.

---

## 2. Estructura y Rutas del Entorno Local (Windows)

Debido a que el nombre de usuario de Windows de Miguel (`Miguel Ángel Rúa`) contiene acentos y espacios, configuramos un entorno altamente robusto que esquiva las limitaciones de codificación de Node.js y MySQL:

*   **Workspace de Desarrollo (Donde operan Claude Code y AntiGravity):**
    `c:\Users\Miguel Ángel Rúa\Dropbox\PC\Desktop\AntiGravity\Parche Peludo`
*   **Lanzador Seguro de LocalWP (Batch Script):**
    `c:\Users\Miguel Ángel Rúa\Dropbox\PC\Desktop\AntiGravity\Parche Peludo\Lanzar_Local.bat`
    *   *Nota técnica:* Este script detiene instancias en segundo plano, crea y redirige las variables globales `%USERPROFILE%`, `%APPDATA%` y `%LOCALAPPDATA%` hacia `C:\LocalWPData\AppData` para que MySQL y Electron no se estrellen con los acentos. **LocalWP siempre debe abrirse desde este archivo.**
*   **Ruta Real de Instalación de LocalWP (Disco C - Libre de Acentos):**
    `C:\Users\Public\parche-peludo-local` (Cumple con la regla de LocalWP de estar en `C:\Users` pero libre de caracteres especiales).
*   **Portal de Archivos (Directory Junction Link):**
    `c:\Users\Miguel Ángel Rúa\Dropbox\PC\Desktop\AntiGravity\Parche Peludo\sitio_local`
    *   *Nota técnica:* Esta es una unión de carpetas de Windows (`mklink /J`) que apunta directamente a `C:\Users\Public\parche-peludo-local\app\public`. Permite que Claude Code y AntiGravity editen los archivos activos de WordPress de forma instantánea a través del Workspace de desarrollo.
*   **Ruta del Tema Hijo (Listeo Child) para Ediciones:**
    `sitio_local/wp-content/themes/listeo-child/`

---

## 3. Repositorio de GitHub y Control de Versiones

El proyecto tiene una red de seguridad basada en Git para control de versiones y rollback inmediato:

*   **Ruta de GitHub (Repositorio Remoto Privado):**
    `https://github.com/miguelangelrua1-design/parche-peludo-v2.git`
*   **Estrategia de Tracking con `.gitignore`:**
    Para mantener el repositorio ultra-ligero y rápido, **está prohibido rastrear el Core de WordPress, plugins de terceros o respaldos pesados**.
    *   **Archivos Rastreados:**
        *   `DESIGN.md` (Manual de Marca V2).
        *   `INSTRUCTIVO-MIGRACION-V2.md` (Este documento).
        *   `sitio_local/wp-content/themes/listeo-child/*` (Toda la hoja de estilos personalizada `style.css` y la lógica `functions.php` del Tema Hijo).
    *   **Archivos Ignorados:**
        *   `_parchepeludo.com/` (Respaldo original de Hostinger cargado por el usuario).
        *   Todos los demás archivos en `/sitio_local/` que no pertenezcan al tema hijo.

---

## 4. Integración con el Sistema de Diseño (Stitch)

*   **Proyecto Activo en Stitch:** `projects/6074646157084450893` ("Parche Peludo V2")
*   **Asset del Sistema de Diseño:** `assets/4575240644177642851`
*   **Manual de Marca Unificado:** El archivo [DESIGN.md](file:///c:/Users/Miguel%20%C3%81ngel%20R%C3%BAa/Dropbox/PC/Desktop/AntiGravity/Parche%20Peludo/DESIGN.md) contiene la guía completa de colores (Teal Parche `#79C8D0`), tipografías (`Ubuntu`, `Caveat`), sombras, radios de borde y espaciado. Las Secciones 23 y 24 documentan respectivamente el mapeo del sitio real y las directrices locales de desarrollo.

---

## 5. Metodología de Trabajo Seguro en local

Para no dañar el constructor visual **Elementor** y conservar los derechos de la plantilla premium **Listeo**, se ha definido el siguiente protocolo de edición:

1.  **Definición Global de Estilos (Elementor):**
    Los colores y fuentes de la marca V2 (Teal, Outfit, Inter) se definen de manera global en la sección *Site Settings* de Elementor. Así, cualquier bloque nuevo heredará los tokens de marca nativamente.
2.  **No Modificar Archivos del Tema Padre:**
    Queda terminantemente prohibido hacer cambios sobre los archivos del tema padre `listeo`. Toda personalización de plantillas o CSS se realiza exclusivamente en `listeo-child`.
3.  **Coexistencia con Elementor (Clases Personalizadas):**
    En lugar de hacer overrides inline agresivos, Claude y AntiGravity generarán clases CSS limpias basadas en los tokens de marca en `style.css` (por ejemplo, `.tarjeta-premium-v2`). Estas clases se asignarán en el campo "Clases CSS" de los bloques de Elementor.
4.  **Flujo Seguro de Edición de Páginas:**
    Antes de realizar cambios sobre la página principal (Home), se creará un clon de prueba (ej. `/home-v2-pruebas`) para trabajar con total seguridad. Una vez aprobado en local, se asigna como oficial.
5.  **Flujo de Git en Equipo:**
    Antes de iniciar cualquier rediseño, las IAs debemos:
    *   Verificar que no haya cambios sin confirmar (`git status`).
    *   Crear una rama de características: `git checkout -b feature-nombre-de-tarea`.
    *   Hacer commits pequeños y claros tras probar cada cambio localmente en el navegador.

---

## 6. Siguientes Pasos Inmediatos para Claude Code

Cuando se abra la consola de **Claude Code** en este Workspace (`c:\Users\Miguel Ángel Rúa\Dropbox\PC\Desktop\AntiGravity\Parche Peludo`), Claude debe leer este instructivo y:

1.  Confirmar que reconoce las rutas del Tema Hijo (`sitio_local/wp-content/themes/listeo-child/`).
2.  Leer [DESIGN.md](file:///c:/Users/Miguel%20%C3%81ngel%20R%C3%BAa/Dropbox/PC/Desktop/AntiGravity/Parche%20Peludo/DESIGN.md) para sincronizar las variables CSS de marca definidas en el `:root`.
3.  Coordinar con Miguel y AntiGravity para iniciar el desarrollo del primer componente visual V2 seleccionado (por ejemplo, la optimización visual de las **Tarjetas de Listado Premium V2** o el **Hero Section V2**).

¡Estamos listos para el despegue de **Parche Peludo V2**! 🐾🚀
