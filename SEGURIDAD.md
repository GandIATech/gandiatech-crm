# Seguridad en instalaciones de GandiaTech CRM

Esta guía complementa a `SECURITY.md` (política de reporte de vulnerabilidades) y
está dirigida a quien administra una instalación: qué protecciones ya trae el CRM,
qué debes configurar en el servidor y cómo reaccionar ante un incidente.

## 1. Protecciones incluidas en la aplicación

| Área | Medida |
| --- | --- |
| Contraseñas | Argon2id cuando el servidor lo soporta; bcrypt como alternativa. Mínimo 12 caracteres, máximo 72, sin el correo ni el nombre del usuario. |
| Sesiones | Caducidad por inactividad a los **5 minutos** y caducidad absoluta a las **12 horas**. Cookies de sesión `HttpOnly`, `SameSite=Lax` y `Secure` cuando hay HTTPS. |
| Fuerza bruta | Bloqueo tras 5 intentos fallidos por cuenta+IP, 10 por cuenta y 20 por IP dentro de la ventana de conteo (`login_attempts`). |
| CSRF | Token por sesión en todos los formularios y peticiones de escritura. |
| SQL | PDO con consultas preparadas en todas las operaciones. |
| XSS | Escape de salida y CSP `default-src 'self'` enviada por PHP y por `.htaccess`. |
| Cabeceras | `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`, `Permissions-Policy`, `Cross-Origin-Opener-Policy`, `X-Robots-Tag: noindex` y `Strict-Transport-Security` cuando la petición es HTTPS. |
| Adjuntos | Lista blanca de extensiones (PDF, JPG, PNG, GIF, WebP, ZIP) y validación del MIME real con `finfo`. Se guardan fuera de la raíz web pública, en `data/uploads`, con nombre aleatorio. |
| Roles | Administrador, director y comercial, con visibilidad limitada por propietario. |
| Secretos | La contraseña SMTP se guarda cifrada con AES-256-GCM; la clave se deriva de `app_key`. |
| Actualizaciones | Solo HTTPS, con límites de tamaño y verificación de la firma Ed25519 del manifiesto y del SHA-256 del paquete antes de entregarlo. |
| Acceso a ficheros | `.htaccess` deniega `src/`, `vendor/`, `config/`, `data/` y `database/`, los ficheros que empiezan por punto y extensiones `.md`, `.sql`, `.sqlite`, `.log`, `.ini`, `.sh`, `.bak` y `.phar`. |

## 2. Lista de comprobación del servidor

1. **HTTPS obligatorio.** Sirve el CRM solo por HTTPS y con un certificado válido.
   Si hay proxy inverso, asegúrate de que `HTTPS` llega correctamente para que se
   emita HSTS.
2. **PHP actualizado.** PHP 8.2 o superior, con los parches de seguridad al día.
3. **Carpetas privadas bloqueadas.** En Apache lo hace el `.htaccess` incluido.
   En Nginx debes añadir:
   ```nginx
   location ~ ^/(src|vendor|config|data|database)(/|$) { deny all; }
   location ~* \.(md|sql|sqlite|sqlite3|log|ini|sh|bak|phar)$ { deny all; }
   location ~ /\. { deny all; }
   ```
4. **Permisos mínimos.** Directorios `755`, ficheros `644`, `config/config.php`
   `640`, y `config/` y `data/` escribibles solo por el usuario de PHP.
5. **Instalador eliminado.** Comprueba que `install/` ya no existe; si quedó, bórrala.
6. **Depuración desactivada.** `debug` debe ser `false` en `config/config.php`.
7. **`app_key` única por instalación.** 64 caracteres hexadecimales generados con
   `php -r "echo bin2hex(random_bytes(32));"`. Guárdala en la copia de seguridad:
   si la pierdes o la cambias, hay que volver a introducir la contraseña SMTP en
   Configuración → Correo (está cifrada con ella).
8. **Sin listados de directorios.** `Options -Indexes` ya viene en `.htaccess`;
   en Nginx, usa `autoindex off;`.
9. **Correo saliente con TLS.** Configura SMTP con STARTTLS o SSL y una cuenta
   dedicada, no la personal del responsable.
10. **Copias de seguridad fuera del servidor.** Descarga `config/` y `data/` (o el
    volcado desde el panel) y verifica que puedes restaurarlas.
11. **Revisión de accesos.** Da de baja a los usuarios que ya no trabajen en la
    empresa y revisa periódicamente los roles administradores.
12. **Restricción de red (opcional).** El CRM no implementa doble factor. Si lo
    expones a Internet, limita el acceso por IP o VPN, o protege la ruta `/app/`
    con autenticación básica del servidor web como segunda barrera.

## 3. Qué NO hacer

- No publiques `config/config.php`, `data/crm.sqlite` ni volcados SQL.
- No compartas capturas con datos de clientes en issues o soporte público.
- No des permisos `777` a `config/` ni a `data/`: escribe solo el usuario de PHP.
- No edites el código del núcleo para "arreglar" algo de seguridad sin revisarlo:
  las actualizaciones sobrescriben `src/`, `app/`, `api/`, `database/` y `vendor/`.
- No instales en HTTP para "probar en producción": el manifiesto de
  actualizaciones exige HTTPS y las credenciales viajan en claro.

## 4. Copias de seguridad

- **SQLite:** copia `data/crm.sqlite` con la aplicación parada (o usa la copia de
  seguridad del panel, que genera un volcado consistente).
- **MySQL:** usa el volcado SQL que ofrece el panel o `mysqldump`.
- Incluye también `config/config.php` y `data/uploads/`.
- Guarda las copias cifradas y fuera del servidor, con retención definida.
- Prueba la restauración al menos una vez; una copia sin probar no es una copia.

## 5. Registro y vigilancia

- **Intentos de acceso:** tabla `login_attempts` (cuenta, IP y fecha). Picos
  repetidos desde una misma IP indican ataque de fuerza bruta.
- **Errores PHP:** `data/php-error.log`. Revisa que no exponga rutas ni datos
  sensibles si el hosting lo hace público; el `.htaccess` ya bloquea `.log`.
- **Actividad funcional:** el panel muestra la actividad reciente y la auditoría
  de entidades (creación, modificación, papelera).
- **Actualizaciones:** el panel indica la versión instalada y si hay una nueva
  versión firmada disponible.

## 6. Respuesta ante un incidente

1. **Contén:** retira el acceso público (mantenimiento, restricción por IP) y
   cambia las contraseñas de los usuarios administradores.
2. **Preserva evidencias:** descarga una copia de `data/`, de los logs del
   servidor y de `login_attempts` antes de limpiar nada.
3. **Rota credenciales:** contraseña SMTP, credenciales de base de datos y
   `app_key` si sospechas que se ha filtrado (recuerda volver a introducir la
   contraseña SMTP después).
4. **Investiga:** revisa usuarios creados, adjuntos subidos, cambios de
   configuración y accesos desde IP desconocidas.
5. **Restaura:** si hay datos alterados, parte de la última copia verificada y
   reaplica las migraciones arrancando la aplicación.
6. **Actualiza:** comprueba que estás en la última versión publicada antes de
   volver a abrir el servicio.
7. **Documenta:** anota fechas, alcance e impacto. Si afecta a datos personales,
   valora las obligaciones de notificación que correspondan (RGPD).

## 7. Reportar una vulnerabilidad

No abras un issue público. Sigue el procedimiento privado descrito en
`SECURITY.md`: pestaña **Security** del repositorio → **Report a vulnerability**.
Incluye versión del CRM y de PHP, motor de base de datos, pasos de reproducción e
impacto estimado, sin datos reales de terceros.
