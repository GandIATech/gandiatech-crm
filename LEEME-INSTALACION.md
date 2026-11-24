# Instalación de GandiaTech CRM

Guía para poner en marcha el paquete distribuible en un hosting compartido o un
servidor propio. Si ya tienes el CRM instalado y quieres actualizarlo, salta al
apartado **Actualizar una instalación existente**.

## 1. Contenido del paquete

```
.htaccess        Reglas de Apache (seguridad, rutas y cabeceras)
router.php       Router para el servidor embebido de PHP (solo desarrollo/pruebas)
app/             Interfaz web (HTML, CSS y JavaScript ya compilados)
api/             Front controller de la API
install/         Asistente de instalación (se autodestruye al terminar)
src/             Núcleo de la aplicación
vendor/          Dependencias de Composer (PHPMailer)
database/        Esquemas SQL de SQLite y MySQL + migraciones
config/          Configuración local (vacío salvo la plantilla de ejemplo)
data/            Base de datos, copias de seguridad y adjuntos (vacío)
LEEME.md         Esta guía
SEGURIDAD.md     Recomendaciones de endurecimiento
README.md        Descripción funcional del producto
CHANGELOG.md     Historial de versiones
LICENSE          Licencia MIT
```

## 2. Requisitos

- **PHP 8.2 o superior** (no funciona con 8.1 ni anteriores).
- Extensiones obligatorias: `pdo`, `openssl`, `mbstring`, `fileinfo` y `session`.
- Motor de base de datos: `pdo_sqlite` (opción por defecto, no requiere nada más)
  o `pdo_mysql` para MySQL/MariaDB 5.7+ / 10.3+.
- `sodium` recomendado: sin esta extensión, el comprobador de actualizaciones del
  panel no puede verificar la firma del paquete y queda desactivado.
- Apache con `mod_rewrite` (se incluye `.htaccess`) o Nginx.
- Permisos de escritura para el usuario de PHP en `config/` durante la
  instalación y en `data/` de forma permanente.

Comprobación rápida en el servidor:

```bash
php -v
php -m | grep -Ei 'pdo|sqlite|mysql|openssl|mbstring|fileinfo|sodium'
```

## 3. Subir los archivos

1. Descomprime el ZIP en tu ordenador.
2. Sube el **contenido** de `gandiatech-crm-basico-X.Y.Z/` (no la carpeta) a la
   raíz del dominio o subdominio, por FTP/SFTP o con el gestor de archivos del
   hosting.
3. La instalación está pensada para la **raíz de un dominio o subdominio**. Si la
   colocas en una subcarpeta, tendrás que ajustar `RewriteBase` y las redirecciones
   de `.htaccess` (ver *Problemas frecuentes*).

### Permisos recomendados

```bash
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 775 config
chmod 775 data
```

El instalador crea `config/config.php` con permisos `0640`. En la mayoría de
hostings compartidos basta con que `config/` y `data/` pertenezcan al mismo
usuario que ejecuta PHP.

## 4. Ejecutar el asistente

1. Abre `https://tu-dominio/install/`. Mientras no exista `config/config.php`, la
   raíz redirige automáticamente ahí.
2. **Paso 1 — Base de datos**
   - *SQLite*: no hay que configurar nada; el fichero se crea en
     `data/crm.sqlite`. Puedes indicar otra ruta relativa dentro de la instalación.
   - *MySQL/MariaDB*: introduce host, puerto, nombre de la base de datos, usuario y
     contraseña. Crea antes la base de datos y un usuario con permisos sobre ella
     (por ejemplo con `utf8mb4_unicode_ci`). El asistente prueba la conexión antes
     de continuar.
3. **Paso 2 — Empresa y administrador**: nombre de la empresa, nombre, correo y
   contraseña del administrador. La contraseña debe tener **entre 12 y 72
   caracteres** y no puede contener el correo ni el nombre del usuario.
4. **Paso 3 — Instalar**: se crean las tablas, el usuario administrador y la
   configuración.

Al terminar, el instalador **borra su propia carpeta** `install/`. Si por
permisos no pudo hacerlo, elimínala tú por FTP: dejarla accesible no permite
reinstalar (la configuración ya existe), pero no aporta nada.

Accede al CRM en `https://tu-dominio/app/`.

## 5. Comprobar que la instalación es correcta

- `https://tu-dominio/api/health` debe responder
  `{"ok":true,"data":{"status":"ok","version":"1.0.1",...}}`.
- `https://tu-dominio/app/` muestra la pantalla de acceso.
- El inicio de sesión con el administrador creado funciona y el panel carga datos.
- En **Configuración → Correo** puedes configurar el SMTP y enviar una prueba.

## 6. Nginx

El paquete incluye reglas para Apache. En Nginx, el bloque equivalente mínimo es:

```nginx
server {
    listen 443 ssl;
    server_name crm.example.com;
    root /var/www/crm;
    index index.php;

    # Carpetas privadas: nunca accesibles por HTTP.
    location ~ ^/(src|vendor|config|data|database|install)(/|$) { deny all; }

    # Ficheros sensibles.
    location ~* \.(md|sql|sqlite|sqlite3|log|ini|sh|bak|phar)$ { deny all; }
    location ~ /\. { deny all; }

    location /api/ {
        try_files $uri /api/index.php?$query_string;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root/api/index.php;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location / {
        try_files $uri $uri/ =404;
    }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }
}
```

Con Nginx, `install/` no se autoprotege: bórrala tras instalar.

## 7. Configuración manual (sin asistente)

Si necesitas preparar la instalación por adelantado:

1. Copia `config/config.example.php` a `config/config.php`.
2. Genera una clave propia:
   `php -r "echo bin2hex(random_bytes(32));"`
3. Sustituye el valor de `app_key` por esa clave y ajusta `db`:

```php
return [
    'app_key' => 'la-clave-de-64-caracteres-hexadecimales',
    'app_url' => 'https://crm.example.com',
    'debug' => false,
    'installed' => true,
    'db' => [
        'driver' => 'sqlite',            // o 'mysql'
        'path' => 'data/crm.sqlite',     // solo SQLite
    ],
];
```

4. Carga el esquema (`database/schema.sql` para SQLite o
   `database/schema.mysql.sql` para MySQL) e inserta el usuario administrador.
   La vía recomendada sigue siendo el asistente: crea el usuario con la política
   de contraseñas correcta y aplica las migraciones.

## 8. Actualizar una instalación existente

1. **Copia de seguridad** de `config/` y `data/` (o usa *Configuración → Copias
   de seguridad*).
2. Sustituye en el servidor las carpetas `app/`, `api/`, `src/`, `database/` y
   `vendor/` por las del nuevo ZIP. **No** sobrescribas `config/` ni `data/`.
3. Recarga la página: las migraciones de base de datos pendientes se aplican
   automáticamente al arrancar.
4. Alternativa: los administradores pueden descargar el paquete verificado desde
   **Configuración → Actualizaciones**, que comprueba la firma Ed25519 y el
   SHA-256 del ZIP antes de entregarlo.

El ZIP publicado incluye `latest.json` y su firma en cada Release; el panel solo
acepta paquetes cuya firma y checksum coincidan.

## 9. Problemas frecuentes

| Síntoma | Causa habitual | Solución |
| --- | --- | --- |
| Error 500 al abrir `/install/` | PHP < 8.2 o falta una extensión | Revisa `php -v` y `php -m`; activa `pdo_sqlite`/`pdo_mysql`, `mbstring`, `fileinfo`, `openssl` |
| «No se pudo escribir config/config.php» | `config/` sin permisos de escritura | `chmod 775 config` y comprueba el propietario |
| «La carpeta ... no tiene permisos de escritura» | `data/` no escribible | `chmod 775 data` y `data/uploads` |
| La raíz redirige siempre a `/install/` | No se creó `config/config.php` | Repite el asistente o crea la configuración manualmente |
| `/install/` redirige a `/app/` | Ya hay configuración | Es el comportamiento normal; elimina `install/` si quedó |
| Error 404 en `/api/...` | `mod_rewrite` desactivado | Actívalo en Apache o usa el bloque Nginx de arriba |
| CSS o JS no cargan en una subcarpeta | `RewriteBase /` no coincide | Ajusta `RewriteBase` y las redirecciones de `.htaccess`, o instala en un subdominio |
| Conexión MySQL rechazada | Credenciales o privilegios | Verifica usuario/contraseña y `GRANT` sobre la base de datos |
| El comprobador de actualizaciones falla | Falta `sodium` o no hay salida HTTPS | Activa `sodium`; el CRM exige HTTPS para consultar el manifiesto |
| Correos no enviados | SMTP mal configurado | Revisa host, puerto, cifrado y credenciales en Configuración → Correo |

## 10. Después de instalar

- Sirve el CRM **solo por HTTPS** y comprueba que `Strict-Transport-Security` se
  envía correctamente.
- Confirma que `config/` y `data/` no son accesibles desde el navegador
  (`https://tu-dominio/config/config.php` debe dar 403 o 404).
- Elimina `install/` si el asistente no lo hizo.
- Revisa `SEGURIDAD.md` y aplica la lista de comprobación.
- Programa copias de seguridad periódicas y prueba una restauración.

Para reportar un problema de seguridad, sigue el canal privado indicado en
`SECURITY.md`. No publiques detalles en issues abiertos.
