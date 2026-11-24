# Migraciones de base de datos

Las migraciones se aplican automáticamente al arrancar la aplicación, en orden
de versión, y quedan registradas en la tabla `schema_migrations`.

## Reglas

- El esquema inicial (`database/schema.sql` y `schema.mysql.sql`) es la
  **versión 1**. Una base de datos nueva o anterior al sistema de migraciones
  se marca como versión 1 sin ejecutar nada.
- Cada cambio posterior va en un archivo propio:
  `database/migrations/002_descripcion_corta.sql`.
- El número debe ser único, de tres o más dígitos, y creciente.
- El archivo se ejecuta **una sola vez** por instalación. No se debe editar una
  migración ya publicada: si hay que corregirla, se crea la siguiente.
- El SQL debe funcionar tanto en SQLite como en MySQL/MariaDB. Evita sintaxis
  exclusiva de un motor.
- Las migraciones nuevas deben ser compatibles con instalaciones existentes:
  usa `CREATE TABLE IF NOT EXISTS` cuando sea posible y añade columnas con
  valores por defecto.

## Comprobar la versión

```sql
SELECT MAX(version) FROM schema_migrations;
```

La aplicación expone la misma información en `Database::schemaVersion()`.
