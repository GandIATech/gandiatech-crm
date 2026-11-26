# Plan de corrección — GandiaTech CRM 1.2.0

Basado en la auditoría funcional del 25/11/2026 (hallazgos H01–H06).

> Alcance: corregir los seis hallazgos del informe y dejar la app verificada para un piloto. Las mejoras de producto del informe (modelo económico, entidad Empresa, vistas de próxima acción) **no** forman parte de este plan; se listan al final como fase posterior.

---

## Decisiones previas (antes de tocar código)

**D1 · Fuente del frontend.** En el repositorio solo están los bundles compilados (`public/app/_astro/`), no el proyecto Astro. Hay dos caminos:

- **A (recomendado):** recuperar el proyecto Astro original y hacer los cambios allí; después recompilar. Es la única forma sostenible.
- **B (si no hay fuente):** parchear los bundles y concentrar la lógica nueva en los `*-extras.js`. Funciona, pero cualquier recompilación futura sobrescribe los cambios. En ese caso, anotar cada parche en `docs/parches-frontend.md`.

**D2 · Regla de avance (H02).** Recomendación: **avance calculado cuando el proyecto tiene tareas** (`tareas_completadas / tareas_totales`), y manual solo si no tiene tareas. La API devuelve `progress_mode: "auto" | "manual"` y la interfaz lo indica. No requiere migración: el valor manual guardado se conserva y vuelve a aplicarse si se borran todas las tareas. Alternativa conservadora: dejar el avance manual y corregir solo el texto comercial; no resuelve la incoherencia, así que no la recomiendo.

**D3 · Alcance de H06.** Primera entrega: solo legibilidad (títulos completos, filtros plegables). La agenda móvil diaria/semanal se trata como funcionalidad aparte.

---

## Fase 0 · Preparación y línea base (≈ 0,5 día)

- [ ] Crear rama: `git checkout -b fix/auditoria-2026-11-25`
- [ ] Copiar la base de datos de trabajo: `cp data/crm.sqlite data/crm.sqlite.bak-auditoria` (no commitear)
- [ ] Arrancar en local: `php -S 0.0.0.0:8002 router.php` y abrir `http://localhost:8002/app/`
- [ ] Entrar con un usuario administrador y con uno estándar.
- [ ] Crear datos identificables `QA-FIX` (contacto, lead, proyecto con plantilla de 4 tareas, imputación de 2,5 h) y **anotar los valores actuales**: avance del proyecto, contadores de contactos/leads, historial tras guardar.
- [ ] Ejecutar una pasada de humo por: login, dashboard, leads (tabla y Kanban), contactos, proyectos, tareas, configuración.
- [ ] Registrar la línea base en `QA-FIX/resultados.md` (valor esperado / observado / versión).

**Criterio de salida:** app funcionando en local y reproducción confirmada de H01, H02, H04 y H05 (H03 puede no reproducirse: ya está corregido en `leads-extras.js`).

---

## Fase 1 · H01 — Cerrar oportunidad como «Perdido» (3–5 h)

**Situación:** el servidor ya valida y guarda `lost_reason` (`src/api/leads.php`, ~199–213) y el Kanban ya pide el motivo (`moveLead` + `askLostReason` en `public/app/_astro/leads-extras.js`). Faltan la tabla y el formulario de alta/edición, ambos en el bundle `leads.astro_astro_type_script_index_0_lang.lGN5yifK.js`.

**Pasos:**

- [ ] Extraer la lógica de motivo a una función reutilizable dentro de `leads-extras.js` (ya existe `askLostReason`; exportarla o duplicarla con el mismo comportamiento).
- [ ] **Tabla:** añadir en `leads-extras.js` un listener en fase de captura sobre `#leads-table` para `change` en `[data-status-lead]`, con `stopImmediatePropagation()` para impedir el `PATCH` del bundle (que envía solo `status_id` y provoca el 422).
  - Al cambiar a un estado con `is_lost === 1`: abrir el diálogo de motivo.
  - Enviar `PATCH /leads/{id}` con `{ status_id, lost_reason }`.
  - Cancelar: restaurar el `<select>` al estado anterior sin llamar a la API.
  - Tras guardar: refrescar la tabla disparando `input` sobre `#lead-search` (mismo recurso que ya usa el Kanban) y mostrar toast.
- [ ] **Formulario de alta/edición:** ampliar `enhanceLeadDialog()` para insertar un campo condicional «Motivo de pérdida» (select con `LOST_REASONS` + detalle si es «Otro») junto al estado, visible solo si el estado elegido tiene `is_lost`.
  - Interceptar el `submit` del formulario en captura; si el estado es de pérdida y no hay motivo, bloquear el envío y marcar el campo.
  - Añadir `lost_reason` al cuerpo que envía el bundle: alternativa limpia si se recupera la fuente Astro; si no, reenviar el `PATCH` desde extras con el motivo incluido.
- [ ] Unificar el flujo para que tabla, formulario y Kanban usen el mismo diálogo y el mismo payload.
- [ ] Verificar que al reabrir el lead persisten estado y motivo, y que el pipeline abierto ya no lo cuenta.

**Aceptación:** marcar Perdido desde tabla, formulario y Kanban guarda el motivo; recargar mantiene el estado; cancelar no cambia nada; todas las vistas muestran la misma causa; el servidor sigue rechazando un cambio sin motivo.

**Riesgo:** que el bundle registre su propio listener antes que el nuestro. Mitigación: captura en el contenedor y prueba manual de los tres caminos.

---

## Fase 2 · H02 — Coherencia del avance del proyecto (4–8 h)

**Situación:** `progress` es un campo manual (`database/schema.sql:78`, `src/api/projects.php:96,166`). Las consultas ya devuelven `tasks_total` y `tasks_done` tanto en listado (`projects.php:29-30`) como en ficha (`src/api/_shared.php:108-109`).

**Pasos:**

- [ ] Decidir D2 y documentarlo en `CHANGELOG.md` y en la ayuda de la app.
- [ ] Añadir en `src/api/_shared.php` un helper `project_progress(array $project): array` que devuelva `progress` calculado y `progress_mode`:
  - `tasks_total > 0` → `round(tasks_done / tasks_total * 100)`, modo `auto`.
  - `tasks_total = 0` → valor manual guardado, modo `manual`.
- [ ] Aplicar el helper en el listado (`src/api/projects.php`, `array_map` antes de `json_response`) y en `project_full()`.
- [ ] En `PATCH /projects/{id}`: si el proyecto tiene tareas, ignorar `progress` entrante (o responder 422 explicando que es calculado). Mantener el valor almacenado para no perderlo.
- [ ] Frontend (`projects.astro_...C_hzmv9-.js` y `projects-extras.js`):
  - Mostrar etiqueta «Calculado por tareas» o «Manual» junto al avance.
  - Si el modo es `auto`, deshabilitar el slider del formulario y mostrar el valor calculado.
  - En la ficha, usar el mismo criterio que el listado.
- [ ] Revisar el texto comercial de la web si promete recálculo (debe coincidir con la regla elegida).

**Aceptación:** proyecto con 4 tareas, al completar 1 muestra 25 % en ficha y listado, y persiste tras recarga y cambio de sesión; proyecto sin tareas conserva su avance manual y se identifica como tal; completar/reapertar tareas actualiza ambos.

**Riesgo:** proyectos existentes con tareas y avance manual cambiarán de porcentaje. Es el comportamiento deseado, pero debe avisarse en las notas de versión y probarse con datos reales de la demo.

---

## Fase 3 · H04 — Totales globales vs. subtotal de página (2–3 h)

**Situación:** la API ya calcula `leads_count` por contacto (`src/api/contacts.php:18`), pero la interfaz suma solo las filas visibles (`contacts.astro_...DflTx_NZ.js`, función que actualiza `#contacts-leads`: `Array.from(g.values()).reduce(...)`).

**Pasos:**

- [ ] En `src/api/contacts.php` (GET de listado), calcular el total de leads vinculados para **todo el conjunto filtrado**:

  ```sql
  SELECT COUNT(*) FROM leads l
  JOIN contacts c ON c.id = l.contact_id
  WHERE l.deleted_at IS NULL AND <mismo $where de contactos>
  ```

  Devolverlo como `meta.linked_leads` (extender `pagination_meta` o añadirlo después).
- [ ] En el frontend, usar `meta.linked_leads` en lugar de la suma de la página; mantener el cálculo local solo como respaldo.
- [ ] Corregir el singular: «1 lead vinculado» / «N leads vinculados».
- [ ] Confirmar que al paginar sin cambiar filtros el total permanece estable y que los filtros (búsqueda, responsable) lo recalculan.

**Aceptación:** añadir un contacto sin leads no altera el total de vinculados; página 1 y 2 muestran el mismo total global; el singular es correcto.

**Riesgo:** bajo. El cambio es aditivo y mantiene el contrato actual (`leads_count` por fila sigue igual).

---

## Fase 4 · H03 — Historial tras guardar (1–2 h, principalmente verificación)

**Situación:** ya corregido en `leads-extras.js` (pinta tipo, descripción y `formatDate(activity.date)`); el servidor devuelve `description` y `date` en el POST de actividades (`src/api/leads.php:39-46`).

**Pasos:**

- [ ] Verificar en la versión 1.2.0 local: registrar una interacción y comparar el timeline inmediato con la ficha reabierta.
- [ ] Si el fallo se reproduce, revisar el orden de carga de `leads-extras.js` frente al bundle y el selector `.mt-3` usado para localizar el timeline.
- [ ] Comparar la versión desplegada en `gandia.tech/crm_demo` con la del repositorio; si la demo está desactualizada, incluirlo en el despliegue.
- [ ] Añadir comprobación de regresión a la matriz de QA.

**Aceptación:** la actividad recién guardada muestra autor, tipo, descripción y fecha útil sin reabrir la ficha.

---

## Fase 5 · H05 — Fallo intermitente de carga de Leads (3–8 h)

**Situación:** `request()` en `ui.CJdB5Grl.js` lanza «La respuesta del servidor no es válida» cuando el cuerpo no es JSON parseable. La carga de leads (`R()` del bundle) deja el mensaje de error sin botón de reintento y sin diagnóstico.

**Pasos:**

- [ ] Reproducir con la pestaña Network y los logs del servidor: anotar URL, estado HTTP y cuerpo recibido cuando falla (probar durante un reinicio horario de la demo si aplica).
- [ ] Añadir logging temporal en el front controller (`public/api/index.php` o `src/Http.php`): método, ruta, estado, duración y bytes de respuesta para respuestas no JSON o 5xx.
- [ ] En el bundle de leads, sustituir el estado de error por uno estable con botón «Reintentar» que vuelva a llamar a la carga.
- [ ] Distinguir en `request()` los casos: sin conexión, 401 (redirección ya existe), respuesta no JSON y error de servidor.
- [ ] Si el origen es el reinicio de la demo, documentarlo; si es sesión o SQLite bloqueado, corregir en servidor.

**Aceptación:** ante un fallo, la página muestra un error claro con «Reintentar» y no se queda en «Cargando leads…»; el reintento recupera el listado; los logs permiten identificar la causa de un fallo real.

**Riesgo:** causa desconocida hasta tener logs. No dar H05 por cerrado solo con el botón de reintento: eso mejora la recuperación, no elimina el fallo.

---

## Fase 6 · H06 — Calendario móvil legible (1–2 h)

- [ ] A 390×844, permitir títulos completos en la vista mensual: celdas con altura mínima, truncado a 2 líneas con `title`/tooltip o desplazamiento horizontal del calendario.
- [ ] Hacer plegables los filtros en móvil y dejar visible el acceso a «Hoy».
- [ ] Mantener Mes/Semana sin cambios en escritorio.

**Aceptación:** a 390 px se reconocen los títulos de las tareas sin abrirlas; los filtros no consumen la primera pantalla.

*(Fuera de esta fase: vista de agenda diaria/semanal como pantalla inicial en móvil, 4–8 h, tratarla como mejora independiente.)*

---

## Fase 7 · QA de regresión (≈ 0,5 día)

Repetir la matriz de la auditoría, con estos mínimos:

- [ ] Alta de contacto con acentos y apóstrofo; total correcto.
- [ ] Alta de oportunidad vinculada con importe 1.234,56 €.
- [ ] Conversión a proyecto con precarga de nombre, contacto, lead e importe.
- [ ] Fechas inválidas rechazadas; plantilla Consultoría genera 4 tareas.
- [ ] Imputación de 2,5 h: coste, consumo y saldo correctos.
- [ ] Completar tarea: desaparece de abiertas, avance actualizado (H02).
- [ ] Perdido con motivo desde tabla, formulario y Kanban; persistencia tras recarga; cancelación sin cambios (H01).
- [ ] Historial inmediato completo (H03).
- [ ] Contadores globales estables al paginar y filtrar (H04).
- [ ] Error de carga simulada (cortar la API o forzar 500): estado de error con reintento (H05).
- [ ] Permisos: usuario estándar no ve configuración ni proyectos ajenos por enlace directo; mismo registro desde otra cuenta.
- [ ] Móvil 390×844 en calendario y navegación.
- [ ] Sin errores en consola del navegador durante el recorrido.

**Criterio de salida:** todos los casos anteriores en verde; los que no se puedan probar se marcan como no verificados, no como aprobados.

---

## Fase 8 · Empaquetado y entrega (1–2 h)

- [ ] Subir `APP_VERSION` en `src/bootstrap.php` (1.2.1 o 1.3.0 según el alcance).
- [ ] Actualizar `CHANGELOG.md` con H01–H06 y la regla de avance elegida.
- [ ] Revisar que `data/crm.sqlite`, backups y logs no entren en el paquete (`.gitignore` / script de empaquetado).
- [ ] Generar el paquete con `./empaquetar-distribucion.sh` y probar la instalación limpia en un directorio aparte.
- [ ] Probar actualización desde 1.2.0 y restauración de copia (aunque el informe no lo exige, es el momento barato de hacerlo).
- [ ] Documentar los parches de bundles si se eligió la opción B de D1.

**No incluye despliegue ni publicación:** requieren autorización expresa y, en el caso de actualizaciones firmadas, la clave correspondiente.

---

## Orden recomendado y estimación

| Fase | Trabajo | Estimación | Depende de |
|---|---|---|---|
| 0 | Preparación y línea base | 0,5 día | — |
| 1 | H01 cierre como Perdido | 3–5 h | Fase 0 |
| 2 | H02 avance coherente | 4–8 h | D2 |
| 3 | H04 totales globales | 2–3 h | Fase 0 |
| 4 | H03 verificación | 1–2 h | Fase 0 |
| 5 | H05 diagnóstico y reintento | 3–8 h | Logs |
| 6 | H06 legibilidad móvil | 1–2 h | — |
| 7 | QA de regresión | 0,5 día | 1–6 |
| 8 | Empaquetado y entrega | 1–2 h | 7 |

**Total:** 2–3 jornadas de desarrollo más media jornada de QA. Si se posponen H05 (hasta tener logs) y H06, el bloque urgente H01 + H02 + H04 + H03 cabe en **1–1,5 jornadas**.

**Ruta crítica:** H01 → QA de leads; H02 → decisión de producto y notas de versión. H05 no debe bloquear la entrega si no hay evidencia de causa, pero sí quedar abierto y documentado.

---

## Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| Editar bundles compilados | Recuperar la fuente Astro; si no, aislar cambios en `*-extras.js` y registrar cada parche |
| Cambio de significado del avance | Mantener el valor manual guardado, mostrar el modo activo y avisar en el CHANGELOG |
| Sin tests automatizados | Matriz manual de la fase 7 y datos `QA-FIX` reproducibles |
| El fallo H05 no se reproduce | Logging temporal y no cerrar el hallazgo sin causa identificada |
| Regresión en el flujo de leads | Probar los tres caminos de cambio de estado y la persistencia tras recarga y con otro rol |

---

## Fuera de alcance (mejoras del informe, fase posterior)

1. Modelo económico explícito (precio de venta, coste/hora, presupuesto de coste, margen).
2. Reglas de conversión de oportunidad a proyecto y prevención de duplicados.
3. Vistas de «próxima acción comercial» (sin tarea, sin contacto reciente, cierre vencido, estancadas).
4. Entidad Empresa para B2B.
5. Pruebas reales de copias/restauración, migración, actualización, adjuntos, SMTP y accesibilidad antes de usar datos reales.
