import { o as openModal, l as toast, a as esc } from "./ui.CJdB5Grl.js";
import { boot, request, upload } from "./crm-extras.js";

const MAX_BYTES = 5 * 1024 * 1024;

const KINDS = {
  contacts: {
    title: "Importar contactos desde CSV",
    endpoint: "/contacts/import",
    required: "name",
    duplicates: true,
    noun: "contactos",
    refresh: "contact-search",
    fields: [
      ["name", "Nombre", true],
      ["name_secondary", "Apellidos (se une al nombre)"],
      ["email", "Correo"],
      ["phone", "Teléfono"],
      ["company", "Empresa"],
      ["position", "Cargo"],
      ["notes", "Notas"],
    ],
  },
  leads: {
    title: "Importar leads desde CSV",
    endpoint: "/leads/import",
    required: "title",
    duplicates: false,
    noun: "leads",
    refresh: "lead-search",
    fields: [
      ["title", "Título", true],
      ["company", "Empresa"],
      ["value", "Valor (€)"],
      ["status", "Estado (por nombre)"],
      ["source", "Origen"],
      ["expected_close_date", "Cierre previsto"],
      ["contact_email", "Correo del contacto"],
      ["notes", "Notas"],
    ],
  },
};

let session = null;
let metaPromise = null;

function fieldOptions(config, selected) {
  return (
    `<option value="">— No importar —</option>` +
    config.fields
      .map(
        ([key, label, required]) =>
          `<option value="${key}" ${key === selected ? "selected" : ""}>${esc(label)}${required ? " *" : ""}</option>`
      )
      .join("")
  );
}

function previewTable(headers, rows) {
  if (headers.length === 0) return "";
  return `
    <div class="overflow-x-auto rounded-xl border border-line">
      <table class="min-w-full text-left text-[11.5px]">
        <thead class="bg-cream/70 text-[10px] uppercase tracking-[0.08em] text-muted">
          <tr>${headers.map((header) => `<th class="whitespace-nowrap px-3 py-2 font-bold">${esc(header)}</th>`).join("")}</tr>
        </thead>
        <tbody class="divide-y divide-line/70">
          ${
            rows.length === 0
              ? `<tr><td class="px-3 py-3 text-muted" colspan="${headers.length}">Sin filas de datos.</td></tr>`
              : rows
                  .map(
                    (row) =>
                      `<tr>${headers
                        .map(
                          (_, index) =>
                            `<td class="max-w-[220px] truncate whitespace-nowrap px-3 py-1.5 text-ink/80">${esc(row[index] || "—")}</td>`
                        )
                        .join("")}</tr>`
                  )
                  .join("")
          }
        </tbody>
      </table>
    </div>`;
}

function mappingList(config, headers, suggested) {
  const suggestedByColumn = new Map();
  Object.entries(suggested ?? {}).forEach(([field, index]) => suggestedByColumn.set(Number(index), field));
  return `
    <div class="space-y-1.5">
      ${headers
        .map(
          (header, index) => `
          <div class="flex items-center gap-2 rounded-xl border border-line px-3 py-2">
            <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold" title="${esc(header)}">${esc(header)}</span>
            <svg class="h-3.5 w-3.5 shrink-0 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14"/><path d="m13 6 6 6-6 6"/></svg>
            <select class="select w-[200px] shrink-0 !py-1.5 text-[12px]" data-map-column="${index}" aria-label="Campo de la base de datos para ${esc(header)}">
              ${fieldOptions(config, suggestedByColumn.get(index) ?? "")}
            </select>
          </div>`
        )
        .join("")}
    </div>`;
}

function ownerOptions() {
  const users = session?.meta?.users ?? [];
  const currentId = Number(session?.user?.id ?? 0);
  return users
    .map(
      (user) =>
        `<option value="${user.id}" ${Number(user.id) === currentId ? "selected" : ""}>${esc(user.name)}</option>`
    )
    .join("");
}

async function loadMeta() {
  if (!metaPromise) {
    metaPromise = request("/meta")
      .then((result) => {
        session.meta = result.data;
        return result.data;
      })
      .catch(() => null);
  }
  return metaPromise;
}

function openKind(kind) {
  const config = KINDS[kind];
  if (!config) return;

  let file = null;
  let parsed = null;

  const dialog = openModal({
    title: config.title,
    subtitle: "La primera fila del CSV son los encabezados. Después indicas a qué campo va cada columna.",
    width: "min(96vw, 760px)",
    body: `
      <div data-step="file">
        <label class="flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-line bg-cream/40 px-4 py-8 text-center transition hover:border-accent/50 hover:bg-cream/70">
          <svg class="h-7 w-7 text-muted" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5"/><path d="M12 3v12"/></svg>
          <span class="text-[13px] font-semibold">Selecciona el archivo CSV</span>
          <span class="text-[11.5px] text-muted">Separador «;», «,» o tabulador · máximo 5 MB y 5.000 filas</span>
          <input type="file" accept=".csv,text/csv" class="hidden" data-file-input />
        </label>
        <p class="mt-3 text-[11.5px] text-muted" data-file-status>Se admiten archivos con acentos en UTF-8 o Windows-1252.</p>
      </div>
      <div data-step="map" class="hidden">
        <div class="flex flex-wrap items-center gap-2">
          <span class="badge bg-cream text-[11px] text-muted" data-file-summary></span>
          <span class="text-[11px] text-muted" data-map-status></span>
        </div>
        <div class="mt-3" data-preview></div>
        <div class="mt-5">
          <h3 class="font-display text-[15px] font-bold">¿Dónde va cada columna?</h3>
          <p class="mt-0.5 text-[11.5px] text-muted">Hemos propuesto un mapeo según los encabezados. Revísalo antes de importar: los campos con * son obligatorios.</p>
          ${
            config.required === "name"
              ? `<p class="mt-1 text-[11.5px] text-muted">Si el nombre y los apellidos vienen en columnas separadas, asigna «Nombre» a una y «Apellidos» a la otra: se guardarán juntos en el nombre del contacto.</p>`
              : ""
          }
          <div class="mt-3" data-mapping></div>
        </div>
        <div class="mt-5 grid gap-3 sm:grid-cols-2">
          ${
            config.duplicates
              ? `<div>
                   <label class="field-label" for="import-duplicates">Si el correo ya existe</label>
                   <select id="import-duplicates" class="select" data-duplicates>
                     <option value="skip">Omitir la fila</option>
                     <option value="update">Actualizar el contacto</option>
                   </select>
                 </div>`
              : ""
          }
          <div data-owner-wrap class="hidden">
            <label class="field-label" for="import-owner">Responsable de lo importado</label>
            <select id="import-owner" class="select" data-owner></select>
          </div>
        </div>
      </div>
      <div data-step="result" class="hidden"></div>
    `,
    footer: `
      <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
      <button type="button" class="btn btn-secondary hidden" data-back>Volver</button>
      <button type="button" class="btn btn-primary hidden" data-submit disabled>Importar</button>
    `,
    onMount: (element) => {
      const steps = {
        file: element.querySelector('[data-step="file"]'),
        map: element.querySelector('[data-step="map"]'),
        result: element.querySelector('[data-step="result"]'),
      };
      const fileInput = element.querySelector("[data-file-input]");
      const fileStatus = element.querySelector("[data-file-status]");
      const summary = element.querySelector("[data-file-summary]");
      const mapStatus = element.querySelector("[data-map-status]");
      const preview = element.querySelector("[data-preview]");
      const mappingBox = element.querySelector("[data-mapping]");
      const ownerWrap = element.querySelector("[data-owner-wrap]");
      const ownerSelect = element.querySelector("[data-owner]");
      const duplicatesSelect = element.querySelector("[data-duplicates]");
      const cancel = element.querySelector("[data-cancel]");
      const back = element.querySelector("[data-back]");
      const submit = element.querySelector("[data-submit]");

      const showStep = (name) => {
        Object.entries(steps).forEach(([key, node]) => node?.classList.toggle("hidden", key !== name));
        back.classList.toggle("hidden", name !== "map");
        submit.classList.toggle("hidden", name !== "map");
        cancel.textContent = name === "result" ? "Cerrar" : "Cancelar";
      };

      const collectMapping = () => {
        const mapping = {};
        mappingBox.querySelectorAll("[data-map-column]").forEach((select) => {
          if (select.value !== "") mapping[select.value] = Number(select.dataset.mapColumn);
        });
        return mapping;
      };

      const refreshSubmit = () => {
        const mapping = collectMapping();
        const missing = mapping[config.required] === undefined;
        submit.disabled = missing;
        const rows = parsed?.rows ?? 0;
        submit.textContent = rows > 0 ? `Importar ${rows} ${rows === 1 ? "fila" : "filas"}` : "Importar";
        mapStatus.textContent = missing
          ? `Falta indicar la columna de «${config.fields.find(([key]) => key === config.required)[1]}».`
          : "";
        mapStatus.classList.toggle("text-red-600", missing);
        mapStatus.classList.toggle("text-muted", !missing);
      };

      const clearDuplicate = (changed) => {
        if (changed.value === "") return;
        mappingBox.querySelectorAll("[data-map-column]").forEach((select) => {
          if (select !== changed && select.value === changed.value) select.value = "";
        });
      };

      const renderMap = async () => {
        const { headers, suggested, preview: rows, rows: total, delimiter } = parsed;
        summary.textContent = `${headers.length} columnas · ${total} ${total === 1 ? "fila" : "filas"} · separador «${delimiter}»`;
        preview.innerHTML = previewTable(headers, rows);
        mappingBox.innerHTML = mappingList(config, headers, suggested);
        mappingBox.querySelectorAll("[data-map-column]").forEach((select) => {
          select.addEventListener("change", () => {
            clearDuplicate(select);
            refreshSubmit();
          });
        });
        refreshSubmit();
        showStep("map");

        const meta = await loadMeta();
        if (meta && session?.can_view_all && ownerSelect) {
          ownerSelect.innerHTML = ownerOptions();
          ownerWrap.classList.remove("hidden");
        }
      };

      const runPreview = async (chosen) => {
        if (chosen.size > MAX_BYTES) {
          toast("El archivo supera el límite de 5 MB.", "error");
          fileInput.value = "";
          return;
        }
        if (!/\.csv$/i.test(chosen.name)) {
          toast("Selecciona un archivo con extensión .csv.", "error");
          fileInput.value = "";
          return;
        }
        file = chosen;
        fileStatus.textContent = "Leyendo el archivo…";
        const data = new FormData();
        data.append("file", chosen);
        data.append("preview", "1");
        try {
          parsed = await upload(config.endpoint, data);
          if (!parsed.headers?.length) {
            toast("No se han detectado columnas en el CSV.", "error");
            fileStatus.textContent = "Prueba con otro archivo.";
            return;
          }
          fileStatus.textContent = chosen.name;
          await renderMap();
        } catch (error) {
          fileStatus.textContent = error.message;
          toast(error.message, "error");
        }
      };

      fileInput.addEventListener("change", () => {
        const chosen = fileInput.files?.[0];
        if (chosen) void runPreview(chosen);
      });

      cancel.addEventListener("click", () => element.close());

      back.addEventListener("click", () => {
        showStep("file");
        submit.classList.add("hidden");
        fileInput.value = "";
      });

      submit.addEventListener("click", async () => {
        const mapping = collectMapping();
        if (mapping[config.required] === undefined) {
          toast("Indica la columna obligatoria antes de importar.", "error");
          return;
        }
        if (!file) {
          toast("Selecciona de nuevo el archivo CSV.", "error");
          return;
        }
        submit.disabled = true;
        submit.textContent = "Importando…";
        const data = new FormData();
        data.append("file", file);
        data.append("mapping", JSON.stringify(mapping));
        if (config.duplicates && duplicatesSelect) data.append("duplicates", duplicatesSelect.value);
        if (ownerSelect?.value) data.append("owner_id", ownerSelect.value);
        try {
          const result = await upload(config.endpoint, data);
          const parts = [`${result.imported} ${config.noun} importados`];
          if (result.updated > 0) parts.push(`${result.updated} actualizados`);
          if (result.skipped > 0) parts.push(`${result.skipped} omitidos`);
          const issues = [...(result.warnings ?? []), ...(result.errors ?? [])];
          steps.result.innerHTML = `
            <div class="rounded-2xl border border-line bg-cream/50 p-4">
              <p class="font-display text-[15px] font-bold">Importación completada</p>
              <p class="mt-1 text-[12.5px] text-muted">${esc(parts.join(" · "))}.</p>
            </div>
            ${
              issues.length > 0
                ? `<div class="mt-3 rounded-2xl border border-line p-4">
                     <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Incidencias (${issues.length})</p>
                     <ul class="mt-2 space-y-1 text-[12px] text-ink/80">${issues
                       .slice(0, 12)
                       .map((item) => `<li>${esc(item)}</li>`)
                       .join("")}</ul>
                     ${issues.length > 12 ? `<p class="mt-2 text-[11px] text-muted">Y ${issues.length - 12} más.</p>` : ""}
                   </div>`
                : ""
            }`;
          showStep("result");
          submit.classList.add("hidden");
          toast("Importación completada.");
          document.getElementById(config.refresh)?.dispatchEvent(new Event("input"));
        } catch (error) {
          toast(error.message, "error");
          submit.disabled = false;
          refreshSubmit();
        }
      });

      showStep("file");
    },
  });

  return dialog;
}

async function init() {
  session = await boot();
  document.querySelectorAll("[data-import-csv]").forEach((button) => {
    button.addEventListener("click", () => {
      openKind(button.dataset.importCsv);
    });
  });
}

void init();
