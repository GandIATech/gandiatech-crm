# Changelog

Todas las versiones relevantes de GandiaTech CRM se documentan aquí.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

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

[1.0.1]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.0.1
[1.0.0]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.0.0
