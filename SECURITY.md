# Política de seguridad

## Versiones soportadas

Solo la última versión publicada en la rama `main` y en
[Releases](../../releases) recibe correcciones de seguridad.

| Versión | Soporte |
| :--- | :--- |
| 1.x | ✅ |
| < 1.0 | ❌ |

## Cómo reportar una vulnerabilidad

**No abras un issue público.** Los issues públicos quedan visibles para
cualquiera y ponen en riesgo a las instalaciones existentes.

Usa el canal privado de GitHub:

1. Entra en la pestaña **Security** del repositorio.
2. Pulsa **Report a vulnerability**.
3. Describe el problema, los pasos para reproducirlo, la versión afectada y,
   si es posible, una propuesta de corrección.

Si no puedes usar ese canal, abre un issue indicando únicamente que necesitas
un medio de contacto privado, sin detalles técnicos.

## Qué incluir en el informe

- Versión del CRM y de PHP.
- Motor de base de datos (SQLite o MySQL).
- Tipo de vulnerabilidad (XSS, CSRF, IDOR, inyección SQL, etc.).
- Pasos concretos de reproducción.
- Impacto que estimas.
- Cualquier prueba de concepto, sin datos reales de terceros.

## Compromiso de respuesta

Se intentará confirmar la recepción en un plazo de 72 horas y publicar una
corrección en la siguiente revisión. Las vulnerabilidades confirmadas se
documentarán en el `CHANGELOG` una vez exista versión corregida.

## Buenas prácticas para instalaciones

- Mantén PHP actualizado (8.2 o superior).
- Sirve el CRM exclusivamente por HTTPS.
- Restringe el acceso a `config/` y `data/` mediante las reglas `.htaccess`
  incluidas, o su equivalente en Nginx.
- Haz copias de seguridad periódicas y prueba su restauración.
- Elimina `public/install/` si por algún motivo no se borró tras instalar.
- No publiques tu `config/config.php` ni tu base de datos.
