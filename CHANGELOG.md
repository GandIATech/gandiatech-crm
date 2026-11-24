# Changelog

Todas las versiones relevantes de GandiaTech CRM se documentan aquí.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

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

[1.0.0]: https://github.com/GandIATech/gandiatech-crm/releases/tag/v1.0.0
