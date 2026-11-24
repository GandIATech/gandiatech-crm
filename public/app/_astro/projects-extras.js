import { o as openModal, l as toast, n as confirmDialog, a as esc, t as avatar } from "./ui.CJdB5Grl.js";
import { i as money, t as formatDate, u as relativeTime } from "./format.BIwoWcf9.js";
import { boot, currentUser, mountAttachments, observeDialogs, request } from "./crm-extras.js";

let templates = null;
let meta = null;

function localToday() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  return now.toISOString().slice(0, 10);
}

async function loadTemplates() {
  if (!templates) {
    templates = await request("/task-templates")
      .then((response) => response.data)
      .catch(() => []);
  }
  return templates;
}

async function loadMeta() {
  if (!meta) {
    meta = await request("/meta")
      .then((response) => response.data)
      .catch(() => ({ users: [] }));
  }
  return meta;
}

function hours(value) {
  const number = Number(value) || 0;
  return `${number.toLocaleString("es-ES", { maximumFractionDigits: 2 })} h`;
}

function metric(label, value, tone = "") {
  return `
    <div class="rounded-xl border border-line bg-white px-3 py-2.5">
      <p class="text-[10px] font-bold uppercase tracking-[0.08em] text-muted">${label}</p>
      <p class="mt-1 text-[14px] font-bold tabular-nums ${tone}">${value}</p>
    </div>`;
}

function renderProfitability(container, time) {
  const budgeted = Number(time.budgeted_hours) || 0;
  const logged = Number(time.hours_logged) || 0;
  const percent = budgeted > 0 ? Math.min(100, Math.round((logged / budgeted) * 100)) : 0;
  const deviation = Number(time.cost_deviation) || 0;
  const deviationTone = deviation < 0 ? "text-red-600" : "text-ink";
  container.innerHTML = `
    <div class="rounded-2xl border border-line bg-cream/50 p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="font-display text-base font-bold">Rentabilidad</h3>
          <p class="mt-0.5 text-[11px] text-muted">Horas presupuestadas frente a horas reales y coste de desarrollo.</p>
        </div>
        <span class="badge ${budgeted > 0 && logged > budgeted ? "bg-red-50 text-red-600" : "bg-mint/60 text-ink"}">
          ${budgeted > 0 ? `${percent}% consumido` : "Sin presupuesto de horas"}
        </span>
      </div>
      <div class="mt-3 grid gap-2" style="grid-template-columns:repeat(2,minmax(0,1fr))">
        ${metric("Horas reales", hours(logged))}
        ${metric("Horas presupuestadas", budgeted > 0 ? hours(budgeted) : "—")}
        ${metric("Coste real", Number(time.hourly_rate) > 0 ? money(time.labor_cost) : "Sin tarifa")}
        ${metric("Desviación coste", Number(time.hourly_rate) > 0 ? money(deviation) : "—", deviationTone)}
      </div>
      ${
        budgeted > 0
          ? `<div class="mt-3">
              <div class="h-2 overflow-hidden rounded-full bg-line/70"><span class="block h-full rounded-full ${logged > budgeted ? "bg-red-500" : "bg-accent"}" style="width:${percent}%"></span></div>
              <p class="mt-1.5 text-[10px] text-muted">${hours(Math.max(0, budgeted - logged))} disponibles · tarifa ${Number(time.hourly_rate) > 0 ? money(time.hourly_rate) + "/h" : "no definida"}</p>
            </div>`
          : ""
      }
    </div>`;
}

function timeEntryRow(entry, onDelete, onEdit) {
  const canDelete =
    currentUser()?.role === "admin" || Number(entry.user_id) === Number(currentUser()?.id);
  const row = document.createElement("div");
  row.className = "flex items-start gap-3 rounded-xl border border-line px-3 py-2.5";
  row.innerHTML = `
    ${avatar(entry.user_name || "Sistema", entry.avatar_color || "#5E6472", 28)}
    <div class="min-w-0 flex-1">
      <p class="text-[12.5px] font-semibold">${esc(entry.user_name || "Sistema")} <span class="font-normal text-muted">· ${hours(entry.hours)}</span></p>
      <p class="truncate text-[11px] text-muted">${esc(entry.description || entry.task_title || "Sin descripción")}</p>
      <p class="mt-0.5 text-[10px] text-muted/70">${esc(formatDate(entry.date))} · ${esc(relativeTime(entry.created_at))}</p>
    </div>
    ${canDelete ? '<button type="button" class="icon-btn h-7 w-7" data-time-edit aria-label="Editar imputación"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button><button type="button" class="icon-btn h-7 w-7 hover:border-red-200 hover:text-red-600" data-time-delete aria-label="Eliminar imputación"><svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>' : ""}
    ${entry.task_title ? `<span class="badge bg-sand text-[9px] text-[#8A5A00]">${esc(entry.task_title)}</span>` : ""}`;
  row.querySelector("[data-time-delete]")?.addEventListener("click", () => onDelete(entry));
  row.querySelector("[data-time-edit]")?.addEventListener("click", () => onEdit(entry));
  return row;
}

function enhanceProjectDialog(dialog) {
  const marker = dialog.querySelector("[data-project-id]");
  if (!marker || dialog.dataset.projectExtras) return;
  dialog.dataset.projectExtras = "1";
  const projectId = Number(marker.dataset.projectId);
  if (!projectId) return;

  const body = dialog.querySelectorAll(".dialog-panel > div")[1];
  if (!body) return;
  const tasksHeading = Array.from(body.querySelectorAll("h3")).find((heading) =>
    heading.textContent.trim().startsWith("Tareas")
  );
  const tasksBlock = tasksHeading?.parentElement ?? null;

  const extras = document.createElement("div");
  extras.className = "mt-6 space-y-4";
  extras.innerHTML = `
    <div data-profitability></div>
    <div class="rounded-2xl border border-line p-4">
      <div class="flex flex-wrap items-center justify-between gap-2">
        <div>
          <h3 class="font-display text-base font-bold">Horas imputadas</h3>
          <p class="mt-0.5 text-[11px] text-muted">Registra el tiempo dedicado para calcular la rentabilidad.</p>
        </div>
      </div>
      <form class="mt-3 grid gap-3 sm:grid-cols-2" data-time-form>
        <div>
          <label class="field-label">Fecha</label>
          <input name="date" type="date" class="input" value="${localToday()}" required />
        </div>
        <div>
          <label class="field-label">Horas</label>
          <input name="hours" type="number" min="0.25" max="24" step="0.25" class="input" placeholder="1.5" required />
        </div>
        <div data-time-user-wrap class="hidden">
          <label class="field-label">Usuario</label>
          <select name="user_id" class="select"></select>
        </div>
        <div class="flex items-end">
          <button type="submit" class="btn btn-primary w-full">Imputar</button>
        </div>
        <div class="sm:col-span-2">
          <label class="field-label">Descripción</label>
          <input name="description" class="input" maxlength="300" placeholder="Qué se ha hecho en este tiempo" />
        </div>
      </form>
      <div class="mt-3 space-y-2" data-time-list>
        <p class="text-[12px] text-muted">Cargando horas…</p>
      </div>
    </div>`;

  if (tasksBlock) {
    body.insertBefore(extras, tasksBlock);
  } else {
    body.appendChild(extras);
  }

  const profitBox = extras.querySelector("[data-profitability]");
  const timeForm = extras.querySelector("[data-time-form]");
  const timeList = extras.querySelector("[data-time-list]");
  const userSelect = extras.querySelector('[name="user_id"]');

  const refresh = async () => {
    try {
      const data = await request(`/projects/${projectId}`);
      const time = data.data.time ?? {};
      renderProfitability(profitBox, time);
      const entries = data.data.time_entries ?? [];
      timeList.innerHTML = "";
      if (entries.length === 0) {
        timeList.innerHTML = '<p class="rounded-xl border border-dashed border-line px-3 py-3 text-[12px] text-muted">Sin horas imputadas todavía.</p>';
      } else {
        entries.forEach((entry) => {
          timeList.appendChild(
            timeEntryRow(
              entry,
              async (item) => {
                if (!(await confirmDialog({ title: "Eliminar imputación", message: `Se eliminarán ${hours(item.hours)} del proyecto.`, confirmLabel: "Eliminar" }))) return;
                try {
                  await request(`/time-entries/${item.id}`, { method: "DELETE" });
                  toast("Imputación eliminada.");
                  await refresh();
                } catch (error) {
                  toast(error.message, "error");
                }
              },
              (item) => {
                openModal({
                  title: "Editar horas",
                  subtitle: `Imputación de ${item.user_name || "usuario"}`,
                  width: "min(94vw, 460px)",
                  body: `<form id="time-edit-form" class="space-y-4">
                    <div class="grid gap-4 sm:grid-cols-2">
                      <div><label class="field-label" for="time-edit-date">Fecha</label><input id="time-edit-date" name="date" type="date" class="input" value="${esc(item.date)}" required /></div>
                      <div><label class="field-label" for="time-edit-hours">Horas</label><input id="time-edit-hours" name="hours" type="number" min="0.25" max="24" step="0.25" class="input" value="${Number(item.hours)}" required /></div>
                    </div>
                    <div><label class="field-label" for="time-edit-description">Descripción</label><input id="time-edit-description" name="description" class="input" maxlength="300" value="${esc(item.description || "")}" /></div>
                  </form>`,
                  footer: `<button type="button" class="btn btn-secondary" data-cancel>Cancelar</button><button type="submit" form="time-edit-form" class="btn btn-primary">Guardar cambios</button>`,
                  onMount: (dialog) => {
                    dialog.querySelector("[data-cancel]")?.addEventListener("click", () => dialog.close());
                    dialog.querySelector("#time-edit-form")?.addEventListener("submit", async (event) => {
                      event.preventDefault();
                      const form = event.currentTarget;
                      const data = new FormData(form);
                      const submit = dialog.querySelector('[type="submit"]');
                      if (submit) submit.disabled = true;
                      try {
                        await request(`/time-entries/${item.id}`, {
                          method: "PATCH",
                          body: { date: data.get("date"), hours: data.get("hours"), description: data.get("description") },
                        });
                        toast("Imputación actualizada.");
                        dialog.close();
                        await refresh();
                      } catch (error) {
                        toast(error.message, "error");
                      } finally {
                        if (submit) submit.disabled = false;
                      }
                    });
                  },
                });
              }
            )
          );
        });
      }
    } catch (error) {
      profitBox.innerHTML = `<p class="text-[12px] text-red-600">${esc(error.message)}</p>`;
      timeList.innerHTML = "";
    }
  };

  timeForm?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = timeForm.querySelector('[type="submit"]');
    const data = new FormData(timeForm);
    submit.disabled = true;
    try {
      await request("/time-entries", {
        method: "POST",
        body: {
          project_id: projectId,
          date: data.get("date"),
          hours: data.get("hours"),
          description: data.get("description"),
          ...(currentUser()?.role === "admin" ? { user_id: data.get("user_id") } : {}),
        },
      });
      toast("Horas imputadas.");
      timeForm.querySelector('[name="hours"]').value = "";
      timeForm.querySelector('[name="description"]').value = "";
      await refresh();
    } catch (error) {
      toast(error.message, "error");
    } finally {
      submit.disabled = false;
    }
  });

  if (currentUser()?.role === "admin") {
    loadMeta().then((data) => {
      const wrap = extras.querySelector("[data-time-user-wrap]");
      wrap?.classList.remove("hidden");
      if (userSelect) {
        userSelect.innerHTML = (data.users ?? [])
          .map((user) => `<option value="${user.id}" ${Number(user.id) === Number(currentUser()?.id) ? "selected" : ""}>${esc(user.name)}</option>`)
          .join("");
      }
    });
  }

  refresh();
  mountAttachments(dialog, "project", projectId);
}

function enhanceProjectForm(dialog) {
  const form = dialog.querySelector("#project-form");
  const select = dialog.querySelector("#project-template");
  if (!form || !select || select.dataset.loaded) return;
  select.dataset.loaded = "1";
  loadTemplates().then((rows) => {
    select.innerHTML =
      '<option value="">Sin plantilla</option>' +
      rows
        .map(
          (template) =>
            `<option value="${template.id}">${esc(template.name)} (${template.items_count} tareas)</option>`
        )
        .join("");
  });
}

async function init() {
  await boot();
  observeDialogs((dialog) => {
    if (dialog.querySelector("#project-form")) {
      enhanceProjectForm(dialog);
    }
    if (dialog.querySelector("[data-project-id]")) {
      enhanceProjectDialog(dialog);
    }
  });
}

init();
