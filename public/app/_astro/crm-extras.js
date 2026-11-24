import {
  a as esc,
  l as toast,
  n as confirmDialog,
  t as avatar,
  u as api,
} from "./ui.CJdB5Grl.js";
import {
  g as ensureSession,
  i as money,
  t as formatDate,
  u as relativeTime,
} from "./format.BIwoWcf9.js";

let session = null;

export async function boot() {
  session = await ensureSession();
  return session;
}

export function currentUser() {
  return session?.user ?? null;
}

export async function request(path, options = {}) {
  if (!session) {
    await boot();
  }
  return api(path, options);
}

export async function upload(path, formData) {
  if (!session) {
    await boot();
  }
  const response = await fetch(`/api${path}`, {
    method: "POST",
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-CSRF-Token": session?.csrf ?? "",
    },
    body: formData,
  });
  if (response.status === 401) {
    window.location.assign("/app/login/?expired=1");
    throw new Error("La sesión ha expirado.");
  }
  let payload = {};
  try {
    payload = await response.json();
  } catch {
    throw new Error("La respuesta del servidor no es válida.");
  }
  if (!response.ok || payload.ok === false) {
    throw new Error(payload.error?.message ?? "No se pudo completar la operación.");
  }
  return payload.data;
}

export function download(path) {
  const link = document.createElement("a");
  link.href = `/api${path}`;
  link.download = "";
  document.body.appendChild(link);
  link.click();
  link.remove();
}

export function filesize(bytes) {
  const size = Number(bytes) || 0;
  if (size < 1024) return `${size} B`;
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / (1024 * 1024)).toFixed(1)} MB`;
}

export function attachmentItem(attachment) {
  const canDelete =
    currentUser()?.role === "admin" ||
    Number(attachment.uploaded_by) === Number(currentUser()?.id);
  return `
    <div class="flex items-center gap-3 rounded-xl border border-line px-3 py-2.5" data-attachment-row="${attachment.id}">
      <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-accent-soft text-accent-dark">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/></svg>
      </span>
      <div class="min-w-0 flex-1">
        <a href="/api/attachments/${attachment.id}/download" class="block truncate text-[12.5px] font-semibold hover:text-accent-dark">${esc(attachment.original_name)}</a>
        <p class="text-[10px] text-muted">${filesize(attachment.size)} · ${esc(attachment.uploaded_by_name || "Sistema")} · ${esc(relativeTime(attachment.created_at))}</p>
      </div>
      ${
        canDelete
          ? `<button type="button" class="icon-btn h-7 w-7 hover:border-red-200 hover:text-red-600" data-attachment-delete="${attachment.id}" aria-label="Eliminar adjunto">
              <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg>
            </button>`
          : ""
      }
    </div>`;
}

export async function mountAttachments(dialog, entityType, entityId, position = "end") {
  const body = dialog.querySelectorAll(".dialog-panel > div")[1];
  if (!body) return;
  const wrapper = document.createElement("div");
  wrapper.className = "mt-6 border-t border-line pt-5";
  wrapper.innerHTML = `
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 class="font-display text-base font-bold">Adjuntos</h3>
        <p class="mt-0.5 text-[11px] text-muted">PDF, imágenes o ZIP. Máximo 10 MB por archivo.</p>
      </div>
      <label class="btn btn-secondary btn-sm cursor-pointer">
        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="M17 8l-5-5-5 5"/><path d="M12 3v12"/></svg>
        Subir archivo
        <input type="file" class="hidden" data-attachment-input accept=".pdf,.png,.jpg,.jpeg,.gif,.webp,.zip" />
      </label>
    </div>
    <div class="mt-3 space-y-2" data-attachment-list>
      <p class="text-[12px] text-muted">Cargando adjuntos…</p>
    </div>`;

  if (position === "before-tasks") {
    const headings = Array.from(body.querySelectorAll("h3"));
    const target = headings.find((heading) => heading.textContent.trim().startsWith("Tareas"));
    if (target?.parentElement) {
      body.insertBefore(wrapper, target.parentElement);
    } else {
      body.appendChild(wrapper);
    }
  } else {
    body.appendChild(wrapper);
  }

  const list = wrapper.querySelector("[data-attachment-list]");
  const input = wrapper.querySelector("[data-attachment-input]");

  const refresh = async () => {
    try {
      const rows = (await request(`/attachments?entity_type=${entityType}&entity_id=${entityId}`)).data;
      list.innerHTML =
        rows.length === 0
          ? '<p class="rounded-xl border border-dashed border-line px-3 py-3 text-[12px] text-muted">Sin adjuntos todavía.</p>'
          : rows.map(attachmentItem).join("");
      list.querySelectorAll("[data-attachment-delete]").forEach((button) => {
        button.addEventListener("click", async () => {
          const id = Number(button.dataset.attachmentDelete);
          if (!(await confirmDialog({ title: "Eliminar adjunto", message: "El archivo se eliminará del servidor.", confirmLabel: "Eliminar" }))) return;
          try {
            await request(`/attachments/${id}`, { method: "DELETE" });
            toast("Adjunto eliminado.");
            await refresh();
          } catch (error) {
            toast(error.message, "error");
          }
        });
      });
    } catch (error) {
      list.innerHTML = `<p class="text-[12px] text-red-600">${esc(error.message)}</p>`;
    }
  };

  input?.addEventListener("change", async () => {
    const file = input.files?.[0];
    if (!file) return;
    if (file.size > 10 * 1024 * 1024) {
      toast("El archivo supera el límite de 10 MB.", "error");
      input.value = "";
      return;
    }
    const data = new FormData();
    data.append("entity_type", entityType);
    data.append("entity_id", String(entityId));
    data.append("file", file);
    try {
      await upload("/attachments", data);
      toast("Archivo subido correctamente.");
      input.value = "";
      await refresh();
    } catch (error) {
      toast(error.message, "error");
    }
  });

  await refresh();
}

export function observeDialogs(handler) {
  const root = document.getElementById("modal-root") ?? document.body;
  const run = () => {
    root.querySelectorAll("dialog.crm-dialog").forEach((dialog) => {
      if (dialog.dataset.crmEnhanced) return;
      dialog.dataset.crmEnhanced = "1";
      handler(dialog);
    });
  };
  new MutationObserver(run).observe(root, { childList: true, subtree: true });
  run();
}

export function projectCard(entityType, entityId) {
  return `<span class="hidden" data-entity-type="${entityType}" data-entity-id="${entityId}"></span>`;
}

export { avatar, esc, formatDate, money, relativeTime, toast, confirmDialog };
