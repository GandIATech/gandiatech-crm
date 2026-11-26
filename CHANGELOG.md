# Changelog

Todas las versiones relevantes de GandiaTech CRM se documentan aquí.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

## [1.4.1] - 2026-11-26

### Cambiado

- El acceso a los comentarios de la barra lateral es ahora un botón **Enviar
  comentarios**. El formulario y el envío no cambian.

## [1.4.0] - 2026-11-26

### Añadido

- Actualización en un clic desde **Configuración → Actualizaciones**: el CRM
  descarga un paquete delta acumulado desde la versión instalada, verifica la
  firma Ed25519 y el SHA-256, guarda una copia de los ficheros y de la base de
  datos, aplica las migraciones y comprueba el resultado. Si falla, restaura los
  ficheros anteriores.
- Inventario firmado de ficheros por release y generación automática de deltas
  acumulados desde las últimas doce etiquetas. Las versiones antiguas usan el
  paquete completo como respaldo.
- Modo mantenimiento breve durante la copia y detección de ficheros modificados
  localmente antes de sobrescribirlos.

### Corregido

- La verificación de una actualización delta ya no falla por ficheros locales
  modificados que no forman parte del paquete que se está aplicando.

### Notas de actualización

- Si tu instalación 1.3.0 no muestra el botón **Actualizar ahora**, instala esta
  versión una vez de forma manual. A partir de la 1.4.0 el motor queda en el
  servidor y las siguientes versiones se pueden aplicar desde Configuración.

## [1.3.0] - 2026-11-26

### Añadido

- Botón **Enviar comentarios** en la barra lateral, encima de «Ayuda y
  formación». Abre un formulario (tipo, título y descripción) y envía el mensaje
  por correo al buzón configurado en `feedback_email`, usando el mismo SMTP que
  las notificaciones. Incluye la página, el usuario que lo envía y, si se marca,
  el contexto técnico (versión, PHP y base de datos).
- `Mailer` admite ahora `Reply-To`, de modo que se puede responder directamente
  al usuario que envía el comentario.

### Cambiado

- El avance de un proyecto con tareas se calcula a partir de las tareas
  completadas (`completadas / total`). Si el proyecto no tiene tareas se
  mantiene el valor manual guardado. La API devuelve `progress_mode`
  (`auto` o `manual`) y el formulario deshabilita el control de avance en modo
  automático. Los datos guardados no se migran: el valor manual se conserva y
  vuelve a aplicarse si el proyecto se queda sin tareas.

### Corregido

- La interacción recién guardada en la ficha de un lead muestra el tipo, la
  descripción y la fecha en el historial inmediato, sin necesidad de reabrir la
  ficha.
- La exportación CSV de proyectos usa el mismo criterio de avance que la ficha
  y el listado.

## [1.2.0] - 2026-11-24

### Añadido

- Importación de CSV en **Contactos** y **Leads**: se elige el archivo, se revisa
  una vista previa con las primeras filas y se indica qué columna corresponde a
  cada campo de la base de datos (con propuesta automática según los encabezados).
- La importación admite separadores `;`, `,` y tabulador, archivos en UTF-8 o
  Windows-1252, importes con formato español (`1.234,50`) y fechas `d/m/Y` o
  `Y-m-d`.
- En contactos se puede omitir o actualizar los que ya existen por correo; en
  leads se asigna el estado por nombre (con aviso si no existe) y se vincula el
  contacto por su correo.
- En contactos, si el nombre y los apellidos vienen en columnas separadas se
  mapea «Nombre» y «Apellidos» y se guardan unidos en el nombre del contacto;
  la columna de apellidos se detecta automáticamente.
- Cada importación deja una actividad resumen y muestra al final los registros
  creados, actualizados, omitidos y las incidencias por línea.
- El límite es de 5 MB y 5.000 filas por archivo, con un máximo de 20 incidencias
  por respuesta; la escritura se hace en una única transacción.

### Cambiado

- La importación de contactos de Configuración usa el mismo motor que la de
  Contactos, de modo que ambas comparten validaciones y mapeo de columnas.

### Corregido

- Con el servidor de desarrollo (`php -S … router.php`) la raíz se calculaba a
  partir de la ruta pedida, lo que rompía el inicio de sesión, la ruta de la
  cookie y las redirecciones al instalador (`/app/install/`). Ahora, en ese
  servidor la raíz del CRM es siempre la del paquete.

## [1.1.0] - 2026-11-24

### Añadido

- El asistente comprueba el entorno **antes** de instalar: versión de PHP y SAPI,
  extensiones obligatorias y recomendadas, controladores PDO disponibles,
  permisos reales de escritura (con archivo de prueba), límites de subida y
  memoria, sesiones, reescritura de URLs, HTTPS, `.htaccess` y estado del
  paquete. Cada punto muestra lo detectado y cómo corregirlo; los bloqueantes
  desactivan «Continuar» y se vuelven a validar al enviar la instalación.
- Desplegable con los datos técnicos (rutas, raíz del documento, URL detectada)
  para copiar en un soporte.
- Guardia de versión de PHP en instalador, API, índice raíz y router: con un PHP
  antiguo se muestra una página (o un JSON) explicativa con la versión detectada
  en lugar de un error 500.
- El paquete de dominio incorpora `index.php` como front controller de la raíz,
  compartido con el paquete de subcarpeta.
- El asistente solo ofrece los controladores de base de datos disponibles.

### Cambiado

- El `.htaccess` del paquete de dominio ya no decide si el CRM está instalado con
  `%{DOCUMENT_ROOT}`: `/install/` siempre entra al asistente, que indica si ya
  hay configuración.
- El instalador se divide en `install/index.php` (compatible con PHP antiguo) e
  `install/instalar.php` (asistente).
- `router.php` carga el bootstrap y usa `app_installed()` y `crm_url()` para las
  redirecciones.
- Guía de instalación: requisitos ajustados (`mbstring` y `sodium` recomendadas),
  nuevo paso de entorno, pruebas locales con `php -S … router.php` y tabla de
  problemas actualizada.

## [1.0.3] - 2026-11-24

### Añadido

- Paquete para publicar el CRM dentro de una subcarpeta de un hosting Apache
  (`/crm`, `/apps/crm`…): `empaquetar-subcarpeta.sh` genera `.htaccess` con
  `RewriteBase`, un `index.php` en la raíz, las URLs del frontend con el prefijo
  y la guía `LEEME-SUBCARPETA.md`.

### Cambiado

- La aplicación calcula la subcarpeta en tiempo de ejecución
  (`crm_base_path()` y `crm_url()`), así que la API, el instalador, las
  redirecciones, la cookie de sesión y los enlaces de las notificaciones
  funcionan bajo un prefijo.
- La raíz del paquete decide entre la aplicación y el instalador desde PHP
  (`index.php`) en lugar de `%{DOCUMENT_ROOT}`: en hostings como IONOS esa
  variable no coincide con la ruta real y toda visita acababa en `/install/`.
- Los modales solo se cierran con la X, los botones de acción o Escape; el clic
  fuera del diálogo ya no los descarta.
- Textos del acceso: se elimina la referencia a «Uso interno» y se habla del
  equipo del cliente en lugar de la marca.

## [1.0.2] - 2026-11-24

### Añadido

- Guía de instalación (`LEEME.md`) dentro del paquete distribuible, con
  requisitos, permisos, pasos del asistente, configuración en Nginx y problemas
  frecuentes.
- Guía de endurecimiento (`SEGURIDAD.md`) con las protecciones incluidas, lista
  de comprobación del servidor, copias de seguridad y respuesta ante incidentes.

### Cambiado

- El empaquetado incluye también `SECURITY.md`, para que las guías del ZIP no
  referencien un fichero ausente.

## [1.0.1] - 2026-11-24

### Añadido

- Comprobador de actualizaciones en Configuración: consulta el último release,
  verifica la firma Ed25519 y el SHA-256 del paquete, y permite descargar el ZIP
  verificado desde el propio panel (solo administradores).
- Sistema de migraciones numeradas con tabla `schema_migrations` y migración
  `002` con índices para tareas por proyecto y horas por usuario.

### Cambiado

- `/api/health` expone la versión instalada de la aplicación.
- El manifiesto `latest.json` publica la versión de esquema requerida.

### Seguridad

- La comprobación de actualizaciones usa una URL fija, exige HTTPS, limita el
  tamaño de las respuestas y rechaza paquetes cuya firma o checksum no coincidan.

## [1.0.0] - 2026-11-24

Primera versión pública.

### Añadido

- Gestión de contactos, leads, proyectos y tareas.
- Estados de lead configurables, motivos de pérdida y pipeline comercial.
- Panel con métricas, actividad reciente y tareas próximas.
- Imputación de horas por tarea y proyecto, con rentabilidad y desviación.
- Plantillas de tareas reutilizables al crear proyectos.
- Papelera con restauración y eliminación definitiva de entidades.
- Adjuntos en leads, proyectos y tareas, con purga de ficheros al eliminar.
- Roles de administrador, director y comercial, con visibilidad por propietario.
- Notificaciones internas y por correo SMTP mediante PHPMailer.
- Importación de contactos desde CSV y exportación de leads, contactos y proyectos.
- Copias de seguridad de la base de datos (SQLite y volcado SQL para MySQL).
- Instalador web con soporte para SQLite y MySQL, que se autodestruye al terminar.
- Sesiones con expiración por inactividad, rotación de versión y Argon2id/bcrypt.
- Protección CSRF, cabeceras de seguridad CSP/HSTS y consultas preparadas PDO.
- Límite de intentos de acceso por cuenta, por cuenta global y por IP.

### Seguridad

- Cierre de sesión correcto tras inactividad sin afectar a usuarios activos.
- Validación del Host en la generación de URLs y del MIME en importaciones CSV.
- Purga en cascada de adjuntos al vaciar proyectos y leads de la papelera.
- Migraciones idempotentes para actualizar instalaciones existentes.

[1.4.1]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.4.1
[1.4.0]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.4.0
[1.3.0]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.3.0
[1.0.1]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.0.1
[1.0.0]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.0.0
