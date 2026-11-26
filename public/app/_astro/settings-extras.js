import { l as toast, n as confirmDialog, a as esc } from "./ui.CJdB5Grl.js";
import { u as relativeTime } from "./format.BIwoWcf9.js";
import { boot, currentUser, request, upload } from "./crm-extras.js";

const FIELD_LABELS = {
  name: "Nombre *",
  name_secondary: "Apellidos (se une al nombre)",
  email: "Correo",
  phone: "Teléfono",
  company: "Empresa",
  notes: "Notas",
};

const HEADER_HINTS = {
  name: ["nombre", "name", "contacto", "cliente"],
  name_secondary: ["apellidos", "apellido", "surname", "lastname"],
  email: ["correo", "email", "e-mail", "mail"],
  phone: ["telefono", "teléfono", "phone", "movil", "móvil"],
  company: ["empresa", "company", "organizacion", "organización"],
  notes: ["notas", "notes", "observaciones", "comentarios"],
};

let csvHeaders = [];
let trashRows = [];
let trashFilter = "";

function normalize(value) {
  return String(value)
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .trim();
}

function parseDelimitedLine(line, delimiter) {
  const cells = [];
  let current = "";
  let quoted = false;
  for (let index = 0; index < line.length; index++) {
    const char = line[index];
    if (char === '"') {
      if (quoted && line[index + 1] === '"') {
        current += '"';
        index++;
      } else {
        quoted = !quoted;
      }
    } else if (char === delimiter && !quoted) {
      cells.push(current.trim());
      current = "";
    } else {
      current += char;
    }
  }
  cells.push(current.trim());
  return cells;
}

async function readHeaders(file) {
  const text = await file.text();
  const firstLine = text.split(/\r?\n/).find((line) => line.trim() !== "") ?? "";
  const delimiter = (firstLine.match(/;/g)?.length ?? 0) >= (firstLine.match(/,/g)?.length ?? 0) ? ";" : ",";
  return parseDelimitedLine(firstLine.replace(/^\uFEFF/, ""), delimiter).map(
    (header, index) => header || `Columna ${index + 1}`
  );
}

function renderMapping() {
  const container = document.getElementById("contacts-import-mapping");
  if (!container) return;
  container.innerHTML = Object.entries(FIELD_LABELS)
    .map(([key, label]) => {
      const suggested = csvHeaders.findIndex((header) =>
        HEADER_HINTS[key].some((hint) => normalize(header).includes(normalize(hint)))
      );
      return `<div>
        <label class="field-label">${label}</label>
        <select class="select" data-map-field="${key}">
          <option value="">— No importar —</option>
          ${csvHeaders
            .map(
              (header, index) =>
                `<option value="${index}" ${index === suggested ? "selected" : ""}>${esc(header)}</option>`
            )
            .join("")}
        </select>
      </div>`;
    })
    .join("");
}

function renderTrash() {
  const list = document.getElementById("trash-list");
  if (!list) return;
  const rows = trashRows.filter((row) => !trashFilter || row.type === trashFilter);
  if (rows.length === 0) {
    list.innerHTML = '<p class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-[12px] text-muted">La papelera está vacía.</p>';
    return;
  }
  list.innerHTML = rows
    .map(
      (row) => `
      <div class="flex flex-wrap items-center gap-3 rounded-xl border border-line px-3.5 py-3" data-trash-row>
        <span class="badge bg-cream text-[10px] text-muted">${esc(row.type_label)}</span>
        <div class="min-w-0 flex-1">
          <p class="truncate text-[12.5px] font-semibold">${esc(row.label || `#${row.id}`)}</p>
          <p class="text-[10px] text-muted">Eliminado ${esc(relativeTime(row.deleted_at))}</p>
        </div>
        <div class="flex gap-1.5">
          <button type="button" class="btn btn-secondary btn-sm" data-trash-action="restore" data-type="${esc(row.type)}" data-id="${row.id}">Restaurar</button>
          <button type="button" class="btn btn-danger btn-sm" data-trash-action="purge" data-type="${esc(row.type)}" data-id="${row.id}">Borrar definitivo</button>
        </div>
      </div>`
    )
    .join("");

  list.querySelectorAll("[data-trash-action]").forEach((button) => {
    button.addEventListener("click", async () => {
      const purge = button.dataset.trashAction === "purge";
      if (purge) {
        const confirmed = await confirmDialog({
          title: "Borrar definitivamente",
          message: "El elemento y sus datos asociados se eliminarán para siempre. Esta acción no se puede deshacer.",
          confirmLabel: "Borrar definitivo",
        });
        if (!confirmed) return;
      }
      button.disabled = true;
      try {
        await request("/admin/trash", {
          method: "POST",
          body: { type: button.dataset.type, id: Number(button.dataset.id), action: purge ? "purge" : "restore" },
        });
        toast(purge ? "Elemento eliminado definitivamente." : "Elemento restaurado.");
        await loadTrash();
      } catch (error) {
        toast(error.message, "error");
        button.disabled = false;
      }
    });
  });
}

async function loadTrash() {
  const list = document.getElementById("trash-list");
  if (!list) return;
  list.innerHTML = '<p class="p-4 text-[12px] text-muted">Cargando papelera…</p>';
  try {
    trashRows = (await request("/admin/trash")).data;
    renderTrash();
  } catch (error) {
    list.innerHTML = `<p class="p-4 text-[12px] text-red-600">${esc(error.message)}</p>`;
  }
}

function initImport() {
  const form = document.getElementById("contacts-import-form");
  const fileInput = document.getElementById("contacts-import-file");
  const status = document.getElementById("contacts-import-status");
  const mapping = document.getElementById("contacts-import-mapping");
  if (!form || !fileInput || !mapping) return;

  fileInput.addEventListener("change", async () => {
    const file = fileInput.files?.[0];
    if (!file) return;
    try {
      csvHeaders = await readHeaders(file);
      if (csvHeaders.length === 0) {
        mapping.innerHTML = "";
        toast("No se han detectado columnas en el CSV.", "error");
        return;
      }
      renderMapping();
      if (status) status.textContent = `${csvHeaders.length} columnas detectadas. Revisa el mapeo y pulsa importar.`;
    } catch {
      toast("No se pudo leer el CSV.", "error");
    }
  });

  form.addEventListener("submit", async (event) => {
    event.preventDefault();
    const file = fileInput.files?.[0];
    if (!file) {
      toast("Selecciona un archivo CSV.", "error");
      return;
    }
    const mappingData = {};
    mapping.querySelectorAll("[data-map-field]").forEach((select) => {
      if (select.value !== "") {
        mappingData[select.dataset.mapField] = Number(select.value);
      }
    });
    if (mappingData.name === undefined && mappingData.name_secondary === undefined) {
      toast("Indica qué columna contiene el nombre.", "error");
      return;
    }
    const submit = form.querySelector('[type="submit"]');
    if (submit) submit.disabled = true;
    if (status) status.textContent = "Importando contactos…";
    const data = new FormData();
    data.append("file", file);
    data.append("mapping", JSON.stringify(mappingData));
    try {
      const result = await upload("/admin/import-contacts", data);
      const messages = [`${result.imported} contactos importados`, `${result.skipped} omitidos`];
      if (result.errors?.length) {
        messages.push(`${result.errors.length} con incidencias`);
      }
      if (status) status.textContent = messages.join(" · ");
      toast(`Importación completada: ${messages.join(", ")}.`);
      form.reset();
      mapping.innerHTML = "";
      csvHeaders = [];
    } catch (error) {
      if (status) status.textContent = error.message;
      toast(error.message, "error");
    } finally {
      if (submit) submit.disabled = false;
    }
  });
}

function formatUpdateSize(bytes) {
  const size = Number(bytes) || 0;
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 / 1024).toFixed(1)} MB`;
}

function hideApplyBox() {
  document.getElementById("updates-apply-box")?.classList.add("hidden");
  const forceWrap = document.getElementById("updates-force-wrap");
  forceWrap?.classList.add("hidden");
  forceWrap?.classList.remove("flex");
  const progress = document.getElementById("updates-progress");
  if (progress) progress.textContent = "";
}

async function loadUpdates(force) {
  const state = document.getElementById("updates-state");
  const message = document.getElementById("updates-message");
  const details = document.getElementById("updates-details");
  const download = document.getElementById("updates-download");
  const button = document.getElementById("updates-check");
  if (!state || !message || !details || !download) return;

  if (button) button.disabled = true;
  hideApplyBox();
  message.textContent = "Consultando la última versión publicada…";
  try {
    const payload = await request(`/updates/check${force ? "?force=1" : ""}`);
    const data = payload.data;
    state.textContent = `Versión ${data.current}`;

    if (!data.available) {
      state.className = "badge bg-cream text-muted";
      message.textContent = `Estás al día. La última versión publicada es la ${data.latest}.`;
      if (data.warning) message.textContent += ` ${data.warning}`;
      details.classList.add("hidden");
      download.classList.add("hidden");
      return;
    }

    state.className = "badge bg-accent-soft text-accent-dark";
    state.textContent = `Nueva versión ${data.latest}`;
    details.classList.remove("hidden");
    const latest = document.getElementById("updates-latest");
    if (latest) latest.textContent = `Nueva versión ${data.latest} disponible`;

    const artifact = data.delta || data.full || {};
    const meta = [
      `Paquete: ${formatUpdateSize(artifact.size)}`,
      artifact.files ? `${artifact.files} archivos` : "",
      `PHP mínimo: ${data.min_php}`,
      `Esquema: ${data.current_db_version}${data.db_version ? ` → ${data.db_version}` : ""}`,
    ].filter(Boolean);
    if (data.checked_at) meta.push(`Comprobado ${relativeTime(data.checked_at)}`);
    const metaLine = document.getElementById("updates-meta");
    if (metaLine) metaLine.textContent = meta.join(" · ");

    const changelog = document.getElementById("updates-changelog");
    if (changelog) {
      if (data.changelog_url) {
        changelog.href = data.changelog_url;
        changelog.classList.remove("hidden");
      } else {
        changelog.classList.add("hidden");
      }
    }

    download.href = "/api/updates/download";
    download.classList.remove("hidden");
    if (data.available && data.can_apply) {
      const box = document.getElementById("updates-apply-box");
      const summary = document.getElementById("updates-apply-summary");
      box?.classList.remove("hidden");
      const deleted = data.delta?.deleted ? ` y se eliminarán ${data.delta.deleted}` : "";
      if (summary) {
        summary.textContent = data.delta
          ? `Hay un paquete preparado desde tu versión ${data.current}: ${artifact.files ?? 0} archivos${deleted}.`
          : `Se aplicarán ${artifact.files ?? 0} archivos del paquete completo.`;
      }
      message.textContent = data.php_ok
        ? "Pulsa «Actualizar ahora» y el CRM se actualizará en este hosting. No tienes que descargar ni subir nada."
        : `La nueva versión requiere PHP ${data.min_php}, pero este servidor usa ${data.current_php}. Actualiza PHP antes de instalar.`;
    } else {
      message.textContent = data.php_ok
        ? `La actualización automática no está disponible: ${data.apply_reason || "este servidor no permite escribir en la carpeta del CRM."} Puedes descargar el paquete verificado y subirlo manualmente.`
        : `La nueva versión requiere PHP ${data.min_php}, pero este servidor usa ${data.current_php}. Actualiza PHP antes de instalar.`;
    }
    if (data.warning) message.textContent += ` ${data.warning}`;
  } catch (error) {
    state.className = "badge bg-cream text-muted";
    message.textContent = error.message;
    details.classList.add("hidden");
    download.classList.add("hidden");
    hideApplyBox();
    toast(error.message, "error");
  } finally {
    if (button) button.disabled = false;
  }
}

async function applyUpdate() {
  const password = document.getElementById("updates-password");
  const apply = document.getElementById("updates-apply");
  const progress = document.getElementById("updates-progress");
  const force = document.getElementById("updates-force");
  const forceWrap = document.getElementById("updates-force-wrap");
  if (!password || !apply) return;
  if (!password.value) {
    if (progress) progress.textContent = "Introduce tu contraseña para confirmar.";
    password.focus();
    return;
  }

  const confirmed = await confirmDialog({
    title: "Actualizar el CRM",
    message: "Se hará una copia de seguridad y el CRM estará en mantenimiento unos segundos. ¿Continuar?",
    confirmLabel: "Actualizar",
  });
  if (!confirmed) return;

  apply.disabled = true;
  password.disabled = true;
  if (progress) progress.textContent = "Descargando y aplicando la actualización. No cierres esta página…";
  try {
    const payload = await request("/updates/apply", {
      method: "POST",
      body: { password: password.value, force: Boolean(force?.checked) },
    });
    const result = payload.data;
    if (progress) progress.textContent = `Actualizado a la versión ${result.to}. Recargando…`;
    toast(`CRM actualizado a la versión ${result.to}.`);
    setTimeout(() => window.location.reload(), 1500);
  } catch (error) {
    if (progress) progress.textContent = error.message;
    if (String(error.message).includes("modificados") && forceWrap) {
      forceWrap.classList.remove("hidden");
      forceWrap.classList.add("flex");
    }
    toast(error.message, "error");
  } finally {
    apply.disabled = false;
    password.disabled = false;
    password.value = "";
  }
}

function initUpdates() {
  const panel = document.getElementById("updates-panel");
  if (!panel || currentUser()?.role !== "admin") return;
  document.getElementById("updates-check")?.addEventListener("click", () => loadUpdates(true));
  document.getElementById("updates-apply")?.addEventListener("click", applyUpdate);
  document.getElementById("updates-password")?.addEventListener("keydown", (event) => {
    if (event.key === "Enter") applyUpdate();
  });
  loadUpdates(false);
}

async function init() {
  await boot();
  initImport();
  initUpdates();

  document.getElementById("trash-filter")?.addEventListener("change", (event) => {
    trashFilter = event.target.value;
    renderTrash();
  });
  document.getElementById("trash-refresh")?.addEventListener("click", () => loadTrash());
  await loadTrash();
}

init();
