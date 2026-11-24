# GandiaTech CRM

CRM ligero y autoalojado para pymes y equipos comerciales. Escrito en PHP y
pensado para funcionar en cualquier hosting compartido, sin frameworks, sin
Node.js y sin servicios externos obligatorios.

- **Backend:** PHP 8.2+ con PDO.
- **Base de datos:** SQLite (por defecto) o MySQL/MariaDB.
- **Frontend:** HTML, CSS y JavaScript sin proceso de compilación.
- **Idioma de la interfaz:** español.

## Características

- Contactos, leads y proyectos con tareas relacionadas.
- Estados de lead configurables, motivos de pérdida y seguimiento comercial.
- Panel con métricas, actividad reciente y próximas tareas.
- Imputación de horas por tarea y proyecto, con rentabilidad y desviación.
- Plantillas de tareas que se aplican al crear un proyecto.
- Papelera con restauración y eliminación definitiva.
- Adjuntos en leads, proyectos y tareas.
- Roles de administrador, director y comercial, con visibilidad por propietario.
- Notificaciones internas y por correo SMTP (PHPMailer).
- Importación de contactos desde CSV y exportación de leads, contactos y proyectos.
- Copias de seguridad de la base de datos.
- Instalador web que se autodestruye al completar la instalación.

## Requisitos

- PHP 8.2 o superior con las extensiones `pdo`, `openssl`, `mbstring`,
  `fileinfo` y `session`.
- `pdo_sqlite` para SQLite o `pdo_mysql` para MySQL/MariaDB.
- Apache con `mod_rewrite` (se incluye `.htaccess`) o Nginx.
- Permisos de escritura en `config/` durante la instalación y en `data/`
  para la base de datos, las copias de seguridad y los adjuntos.

## Instalación

1. Descarga el ZIP de la última versión desde **Releases**.
2. Descomprímelo en tu hosting. La carpeta resultante contiene `app/`, `api/`,
   `install/`, `src/`, `database/` y `vendor/`.
3. Configura el dominio o subdominio para que apunte a esa carpeta.
4. Abre `https://tu-dominio/install/` y sigue el asistente.
5. Al terminar, el instalador elimina su propia carpeta. Accede al CRM en
   `https://tu-dominio/app/`.

En Apache, los archivos `.htaccess` incluidos protegen `config/` y `data/`.
En Nginx, bloquea el acceso directo a esas dos carpetas.

### Desarrollo local

Desde esta copia del código fuente:

```bash
php -S 0.0.0.0:8002 router.php
```

Abre `http://localhost:8002/install/` la primera vez. El router sirve el
contenido de `public/` y delega `/api/*` en el front controller de la API.

## Estructura del proyecto

```
config/          Configuración local (no se versiona)
database/        Esquemas SQL para SQLite y MySQL
data/            Base de datos, copias de seguridad y adjuntos
public/app/      Interfaz web
public/api/      Front controller de la API
public/install/  Asistente de instalación
src/             Núcleo: autenticación, seguridad, base de datos y endpoints
src/api/         Controladores de la API
vendor/          Dependencias de Composer (PHPMailer)
```

## Actualizaciones

Cada versión se publica como un ZIP en **Releases**, con su checksum SHA-256
y un manifiesto `latest.json`. La actualización consiste en reemplazar
`src/`, `public/`, `database/` y `vendor/`, conservando `config/` y `data/`.
Las migraciones de base de datos se aplican automáticamente al arrancar.

Haz siempre una copia de seguridad de `config/` y `data/` antes de actualizar.

## Seguridad

Consulta [SECURITY.md](SECURITY.md) para reportar vulnerabilidades de forma
privada. No publiques detalles de seguridad en issues abiertos.

## Licencia

[MIT](LICENSE). Puedes usar, modificar y redistribuir el proyecto, incluso con
fines comerciales, conservando el aviso de copyright y la licencia.
