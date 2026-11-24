import { o as openModal, l as toast, n as confirmDialog, a as esc, t as avatar } from "./ui.CJdB5Grl.js";
import { i as money, t as formatDate, u as relativeTime } from "./format.BIwoWcf9.js";
import { boot, currentUser, mountAttachments, observeDialogs, request } from "./crm-extras.js";

const MONTHS = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
const WEEKDAYS = ["Lun", "Mar", "Mié", "Jue", "Vie", "Sáb", "Dom"];

const table = document.getElementById("tasks-table");
const pagination = document.getElementById("tasks-pagination");
const calendar = document.getElementById("tasks-calendar");
const calendarButton = document.getElementById("calendar-toggle");
const viewTabs = document.querySelectorAll("[data-view]");

let meta = { users: [], projects: [] };
let calendarActive = false;
let mode = "month";
let anchor = new Date();
let owner = "";
let project = "";
let openTasks = null;

function iso(date) {
  const copy = new Date(date);
  copy.setMinutes(copy.getMinutes() - copy.getTimezoneOffset());
  return copy.toISOString().slice(0, 10);
}

function localToday() {
  return iso(new Date());
}

function startOfWeek(date) {
  const copy = new Date(date);
  const day = (copy.getDay() + 6) % 7;
  copy.setDate(copy.getDate() - day);
  copy.setHours(0, 0, 0, 0);
  return copy;
}

function addDays(date, days) {
  const copy = new Date(date);
  copy.setDate(copy.getDate() + days);
  return copy;
}

function range() {
  if (mode === "week") {
    const start = startOfWeek(anchor);
    return { start, end: addDays(start, 6) };
  }
  const first = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
  const start = startOfWeek(first);
  const last = new Date(anchor.getFullYear(), anchor.getMonth() + 1, 0);
  const end = addDays(startOfWeek(last), 6);
  return { start, end };
}

function taskChip(task) {
  const overdue = task.due_date && task.due_date < localToday() && task.status !== "completada";
  return `<button type="button" data-calendar-task="${task.id}"
    class="w-full truncate rounded-lg border px-1.5 py-1 text-left text-[10px] font-semibold transition hover:border-accent/50 ${overdue ? "border-red-200 bg-red-50 text-red-700" : "border-line bg-cream/60 text-ink"}"
    title="${esc(task.title)}">${esc(task.title)}</button>`;
}

function renderCalendar(rows) {
  const { start, end } = range();
  const byDate = new Map();
  rows.forEach((task) => {
    if (!task.due_date) return;
    if (!byDate.has(task.due_date)) byDate.set(task.due_date, []);
    byDate.get(task.due_date).push(task);
  });

  const label =
    mode === "month"
      ? `${MONTHS[anchor.getMonth()]} ${anchor.getFullYear()}`
      : `${formatDate(iso(start))} – ${formatDate(iso(end))}`;

  let grid = "";
  if (mode === "month") {
    const days = Math.round((end - start) / 86400000) + 1;
    const weeks = Math.ceil(days / 7);
    grid = `<div class="border-b border-line bg-cream/50" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr))">${WEEKDAYS.map((day) => `<div class="px-2 py-2 text-center text-[10px] font-bold uppercase tracking-wide text-muted">${day}</div>`).join("")}</div>`;
    let cells = "";
    for (let index = 0; index < weeks * 7; index++) {
      const day = addDays(start, index);
      const key = iso(day);
      const tasks = byDate.get(key) ?? [];
      const isCurrentMonth = day.getMonth() === anchor.getMonth();
      const isToday = key === localToday();
      cells += `<div class="border-b border-r border-line p-1.5" style="min-height:104px" ${isCurrentMonth ? "bg-white" : "bg-cream/30"}">
        <div class="mb-1 flex items-center justify-between">
          <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10.5px] font-bold ${isToday ? "bg-accent text-white" : isCurrentMonth ? "text-ink" : "text-muted/60"}">${day.getDate()}</span>
          ${tasks.length > 2 ? `<span class="text-[9px] text-muted">${tasks.length}</span>` : ""}
        </div>
        <div class="space-y-1">${tasks.slice(0, 3).map(taskChip).join("")}${tasks.length > 3 ? `<p class="px-1 text-[9px] text-muted">+${tasks.length - 3} más</p>` : ""}</div>
      </div>`;
    }
    grid += `<div class="border-l border-line" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr))">${cells}</div>`;
  } else {
    let columns = "";
    for (let index = 0; index < 7; index++) {
      const day = addDays(start, index);
      const key = iso(day);
      const tasks = byDate.get(key) ?? [];
      const isToday = key === localToday();
      columns += `<div class="border-r border-line p-2" style="min-height:280px">
        <div class="mb-2 flex items-center justify-between border-b border-line pb-2">
          <div><p class="text-[10px] font-bold uppercase tracking-wide text-muted">${WEEKDAYS[index]}</p><p class="text-[14px] font-bold ${isToday ? "text-accent-dark" : ""}">${day.getDate()}</p></div>
          <span class="text-[10px] text-muted">${tasks.length}</span>
        </div>
        <div class="space-y-1.5">${tasks.map(taskChip).join("") || '<p class="text-[10px] text-muted">Sin tareas</p>'}</div>
      </div>`;
    }
    grid = `<div class="border-l border-line" style="display:grid;grid-template-columns:repeat(7,minmax(0,1fr))">${columns}</div>`;
  }

  calendar.innerHTML = `
    <div class="mb-3 flex flex-wrap items-center gap-2">
      <div class="flex items-center gap-1">
        <button type="button" class="btn btn-secondary btn-sm !px-2" data-cal-prev aria-label="Anterior">←</button>
        <button type="button" class="btn btn-secondary btn-sm" data-cal-today>Hoy</button>
        <button type="button" class="btn btn-secondary btn-sm !px-2" data-cal-next aria-label="Siguiente">→</button>
      </div>
      <p class="font-display text-[15px] font-bold capitalize">${esc(label)}</p>
      <div class="ml-auto flex flex-wrap items-center gap-2">
        <div class="flex gap-1 rounded-full border border-line bg-cream/70 p-1">
          <button type="button" class="view-tab ${mode === "month" ? "bg-white text-ink shadow-sm" : "text-muted"}" data-cal-mode="month">Mes</button>
          <button type="button" class="view-tab ${mode === "week" ? "bg-white text-ink shadow-sm" : "text-muted"}" data-cal-mode="week">Semana</button>
        </div>
        <select class="select min-w-[150px]" data-cal-owner>
          <option value="">Todo el equipo</option>
          ${(meta.users ?? []).map((user) => `<option value="${user.id}" ${String(owner) === String(user.id) ? "selected" : ""}>${esc(user.name)}</option>`).join("")}
        </select>
        <select class="select min-w-[160px]" data-cal-project>
          <option value="">Todos los proyectos</option>
          ${(meta.projects ?? []).map((item) => `<option value="${item.id}" ${String(project) === String(item.id) ? "selected" : ""}>${esc(item.name)}</option>`).join("")}
        </select>
      </div>
    </div>
    <div class="overflow-x-auto rounded-2xl border border-line">${grid}</div>`;

  calendar.querySelector("[data-cal-prev]")?.addEventListener("click", () => {
    anchor = mode === "month" ? new Date(anchor.getFullYear(), anchor.getMonth() - 1, 1) : addDays(anchor, -7);
    loadCalendar();
  });
  calendar.querySelector("[data-cal-next]")?.addEventListener("click", () => {
    anchor = mode === "month" ? new Date(anchor.getFullYear(), anchor.getMonth() + 1, 1) : addDays(anchor, 7);
    loadCalendar();
  });
  calendar.querySelector("[data-cal-today]")?.addEventListener("click", () => {
    anchor = new Date();
    loadCalendar();
  });
  calendar.querySelectorAll("[data-cal-mode]").forEach((button) => {
    button.addEventListener("click", () => {
      mode = button.dataset.calMode;
      loadCalendar();
    });
  });
  calendar.querySelector("[data-cal-owner]")?.addEventListener("change", (event) => {
    owner = event.target.value;
    loadCalendar();
  });
  calendar.querySelector("[data-cal-project]")?.addEventListener("change", (event) => {
    project = event.target.value;
    loadCalendar();
  });
  calendar.querySelectorAll("[data-calendar-task]").forEach((button) => {
    button.addEventListener("click", () => {
      window.location.href = `/app/tasks/?view=all&task=${button.dataset.calendarTask}`;
    });
  });
}

async function loadCalendar() {
  if (!calendarActive) return;
  calendar.innerHTML = '<div class="p-6 text-sm text-muted">Cargando calendario…</div>';
  const { start, end } = range();
  try {
    const result = await request("/tasks", {
      params: {
        from: iso(start),
        to: iso(end),
        with_due: 1,
        open: 1,
        ...(owner ? { assigned_to: owner } : {}),
        ...(project ? { project_id: project } : {}),
        per_page: 300,
      },
    });
    renderCalendar(result.data);
  } catch (error) {
    calendar.innerHTML = `<p class="p-6 text-sm text-red-600">${esc(error.message)}</p>`;
  }
}

function showCalendar() {
  calendarActive = true;
  table?.classList.add("hidden");
  pagination?.classList.add("hidden");
  calendar?.classList.remove("hidden");
  calendarButton?.classList.remove("btn-secondary");
  calendarButton?.classList.add("btn-primary");
  viewTabs.forEach((tab) => {
    tab.classList.remove("bg-white", "text-ink", "shadow-sm");
    tab.classList.add("text-muted");
  });
  loadCalendar();
}

function hideCalendar() {
  if (!calendarActive) return;
  calendarActive = false;
  calendar?.classList.add("hidden");
  table?.classList.remove("hidden");
  calendarButton?.classList.remove("btn-primary");
  calendarButton?.classList.add("btn-secondary");
}

async function loadOpenTasks() {
  if (openTasks) return openTasks;
  const result = await request("/tasks", { params: { open: 1, per_page: 500 } });
  openTasks = result.data;
  return openTasks;
}

function openTimeModal(presetTaskId = 0) {
  let settledTask = null;
  openModal({
    title: "Imputar horas",
    subtitle: "Registra el tiempo dedicado a un proyecto o tarea.",
    width: "min(94vw, 560px)",
    body: `
      <form id="time-modal-form" class="space-y-4">
        <div>
          <label class="field-label" for="time-task">Tarea (opcional)</label>
          <select id="time-task" name="task_id" class="select"><option value="">Sin tarea concreta</option></select>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="time-project">Proyecto</label>
            <select id="time-project" name="project_id" class="select">
              <option value="">Sin proyecto</option>
              ${(meta.projects ?? []).map((item) => `<option value="${item.id}">${esc(item.name)}</option>`).join("")}
            </select>
          </div>
          <div>
            <label class="field-label" for="time-date">Fecha</label>
            <input id="time-date" name="date" type="date" class="input" value="${localToday()}" required />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="time-hours">Horas *</label>
            <input id="time-hours" name="hours" type="number" min="0.25" max="24" step="0.25" class="input" placeholder="1.5" required />
          </div>
          ${
            currentUser()?.role === "admin"
              ? `<div>
                  <label class="field-label" for="time-user">Usuario</label>
                  <select id="time-user" name="user_id" class="select">${(meta.users ?? []).map((user) => `<option value="${user.id}" ${Number(user.id) === Number(currentUser()?.id) ? "selected" : ""}>${esc(user.name)}</option>`).join("")}</select>
                </div>`
              : ""
          }
        </div>
        <div>
          <label class="field-label" for="time-description">Descripción</label>
          <input id="time-description" name="description" class="input" maxlength="300" placeholder="Qué se ha hecho" />
        </div>
      </form>`,
    footer: `
      <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
      <button type="submit" form="time-modal-form" class="btn btn-primary">Guardar horas</button>`,
    onMount: (element) => {
      const form = element.querySelector("#time-modal-form");
      const taskSelect = element.querySelector("#time-task");
      const projectSelect = element.querySelector("#time-project");
      element.querySelector("[data-cancel]")?.addEventListener("click", () => element.close());

      loadOpenTasks()
        .then((tasks) => {
          taskSelect.innerHTML =
            '<option value="">Sin tarea concreta</option>' +
            tasks
              .map(
                (task) =>
                  `<option value="${task.id}" data-project="${task.project_id ?? ""}" ${Number(task.id) === Number(presetTaskId) ? "selected" : ""}>${esc(task.title)}</option>`
              )
              .join("");
          const selected = taskSelect.selectedOptions[0];
          if (selected?.dataset.project) {
            projectSelect.value = selected.dataset.project;
          }
        })
        .catch(() => {});

      taskSelect?.addEventListener("change", () => {
        const selected = taskSelect.selectedOptions[0];
        if (selected?.dataset.project) {
          projectSelect.value = selected.dataset.project;
        }
      });

      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const submit = element.querySelector('[type="submit"]');
        const data = new FormData(form);
        const selectedTask = taskSelect?.selectedOptions[0];
        settledTask = selectedTask?.value ? Number(selectedTask.value) : null;
        if (!settledTask && !data.get("project_id")) {
          toast("Selecciona un proyecto o una tarea.", "error");
          return;
        }
        submit.disabled = true;
        try {
          await request("/time-entries", {
            method: "POST",
            body: {
              task_id: settledTask,
              project_id: data.get("project_id") || selectedTask?.dataset.project || null,
              date: data.get("date"),
              hours: data.get("hours"),
              description: data.get("description"),
              ...(currentUser()?.role === "admin" ? { user_id: data.get("user_id") } : {}),
            },
          });
          toast("Horas imputadas correctamente.");
          element.close();
        } catch (error) {
          toast(error.message, "error");
        } finally {
          submit.disabled = false;
        }
      });
    },
  });
}

function enhanceTaskDialog(dialog) {
  const form = dialog.querySelector("#task-editor");
  const taskId = Number(form?.dataset.taskId);
  if (!taskId || dialog.dataset.taskExtras) return;
  dialog.dataset.taskExtras = "1";
  const body = dialog.querySelectorAll(".dialog-panel > div")[1];
  if (!body) return;

  const section = document.createElement("div");
  section.className = "mt-6 border-t border-line pt-5";
  section.innerHTML = `
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <h3 class="font-display text-base font-bold">Tiempo imputado</h3>
        <p class="mt-0.5 text-[11px] text-muted">Horas registradas sobre esta tarea.</p>
      </div>
      <button type="button" class="btn btn-secondary btn-sm" data-task-time-add>Imputar horas</button>
    </div>
    <div class="mt-3 space-y-2" data-task-time-list><p class="text-[12px] text-muted">Cargando…</p></div>`;
  body.appendChild(section);
  section.querySelector("[data-task-time-add]")?.addEventListener("click", () => openTimeModal(taskId));

  request(`/tasks/${taskId}`)
    .then((response) => {
      const entries = response.data.time_entries ?? [];
      const list = section.querySelector("[data-task-time-list]");
      list.innerHTML =
        entries.length === 0
          ? '<p class="rounded-xl border border-dashed border-line px-3 py-3 text-[12px] text-muted">Sin horas imputadas.</p>'
          : entries
              .map(
                (entry) => `
                <div class="flex items-center gap-3 rounded-xl border border-line px-3 py-2">
                  ${avatar(entry.user_name || "Sistema", entry.avatar_color || "#5E6472", 26)}
                  <div class="min-w-0 flex-1">
                    <p class="text-[12px] font-semibold">${esc(entry.user_name || "Sistema")} · ${Number(entry.hours)} h</p>
                    <p class="truncate text-[10.5px] text-muted">${esc(entry.description || "Sin descripción")} · ${esc(formatDate(entry.date))}</p>
                  </div>
                </div>`
              )
              .join("");
    })
    .catch(() => {
      section.querySelector("[data-task-time-list]").innerHTML = '<p class="text-[12px] text-muted">No se pudieron cargar las horas.</p>';
    });

  mountAttachments(dialog, "task", taskId);
}

async function init() {
  await boot();
  try {
    meta = (await request("/meta")).data;
  } catch (error) {
    toast(error.message, "error");
  }

  calendarButton?.addEventListener("click", () => {
    if (calendarActive) {
      hideCalendar();
    } else {
      showCalendar();
    }
  });
  viewTabs.forEach((tab) => tab.addEventListener("click", hideCalendar));
  document.getElementById("new-task-full")?.addEventListener("click", () => {
    openTasks = null;
  });
  document.getElementById("time-entry-open")?.addEventListener("click", () => openTimeModal());

  observeDialogs((dialog) => {
    if (dialog.querySelector("#task-editor[data-task-id]")) {
      enhanceTaskDialog(dialog);
    }
  });
}

init();
