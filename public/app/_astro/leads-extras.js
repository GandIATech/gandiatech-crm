import { o as openModal, l as toast, n as confirmDialog, a as esc, t as avatar } from "./ui.CJdB5Grl.js";
import { i as money, t as formatDate, u as relativeTime } from "./format.BIwoWcf9.js";
import { boot, currentUser, mountAttachments, observeDialogs, request } from "./crm-extras.js";

const LOST_REASONS = ["Precio elevado", "Eligió competidor", "Sin respuesta", "Proyecto cancelado", "Otro"];
const INTERACTION_LABELS = { llamada: "Llamada", reunion: "Reunión", correo: "Correo", nota: "Nota comercial" };

const table = document.getElementById("leads-table");
const pagination = document.getElementById("leads-pagination");
const kanban = document.getElementById("leads-kanban");
const search = document.getElementById("lead-search");
const statusFilter = document.getElementById("filter-status");
const ownerFilter = document.getElementById("filter-owner");
const switchButtons = document.querySelectorAll("[data-leads-view]");

let meta = { statuses: [] };
let view = "table";
let loading = false;

function localToday() {
  const now = new Date();
  now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
  return now.toISOString().slice(0, 10);
}

function setView(next) {
  view = next;
  const isKanban = next === "kanban";
  table?.classList.toggle("hidden", isKanban);
  pagination?.classList.toggle("hidden", isKanban);
  kanban?.classList.toggle("hidden", !isKanban);
  switchButtons.forEach((button) => {
    const active = button.dataset.leadsView === next;
    button.classList.toggle("bg-white", active);
    button.classList.toggle("text-ink", active);
    button.classList.toggle("shadow-sm", active);
    button.classList.toggle("text-muted", !active);
  });
  if (isKanban) {
    renderKanban();
  }
}

async function fetchAllLeads() {
  const filters = {
    q: search?.value.trim() ?? "",
    status_id: statusFilter?.value ?? "",
    owner_id: ownerFilter?.value ?? "",
  };
  const all = [];
  let page = 1;
  let pages = 1;
  do {
    const result = await request("/leads", { params: { ...filters, page, per_page: 100 } });
    all.push(...result.data);
    pages = Number(result.meta.pages ?? 1);
    page++;
  } while (page <= pages && page <= 20);
  return all;
}

function kanbanCard(lead) {
  const owner = lead.owner_name
    ? `<span class="flex items-center gap-1.5">${avatar(lead.owner_name, lead.owner_color, 22)}<span class="truncate text-[11px] text-muted" style="max-width:90px">${esc(lead.owner_name)}</span></span>`
    : '<span class="text-[11px] text-muted">Sin asignar</span>';
  return `
    <article draggable="true" data-kanban-card="${lead.id}" data-kanban-status="${lead.status_id ?? ""}"
      class="cursor-grab rounded-2xl border border-line bg-white p-3 shadow-sm transition hover:border-accent/40 active:cursor-grabbing">
      <button type="button" data-kanban-open="${lead.id}" class="block w-full text-left">
        <p class="line-clamp-2 text-[13px] font-semibold leading-snug hover:text-accent-dark">${esc(lead.title)}</p>
        <p class="mt-1 truncate text-[11px] text-muted">${esc(lead.company || lead.contact_company || "Sin empresa")}</p>
      </button>
      <div class="mt-2.5 flex items-center justify-between gap-2">
        <span class="text-[13px] font-bold tabular-nums">${money(lead.value)}</span>
        <div class="flex items-center gap-1.5">
          ${Number(lead.open_tasks) > 0 ? `<span class="badge bg-sand text-[10px] text-[#8A5A00]">${lead.open_tasks} tarea${Number(lead.open_tasks) === 1 ? "" : "s"}</span>` : ""}
          ${owner}
        </div>
      </div>
      <div class="mt-2 flex items-center justify-between border-t border-line/70 pt-2 text-[10px] text-muted">
        <span>${esc(lead.expected_close_date ? formatDate(lead.expected_close_date) : "Sin cierre previsto")}</span>
        <span>${esc(relativeTime(lead.updated_at))}</span>
      </div>
    </article>`;
}

async function renderKanban() {
  if (!kanban || loading) return;
  loading = true;
  kanban.innerHTML = '<div class="p-6 text-sm text-muted">Cargando tablero…</div>';
  try {
    const leads = await fetchAllLeads();
    const statuses = [...(meta.statuses ?? [])].sort(
      (a, b) => Number(a.position) - Number(b.position) || Number(a.id) - Number(b.id)
    );
    const columns = statuses.map((status) => {
      const items = leads.filter((lead) => Number(lead.status_id) === Number(status.id));
      const total = items.reduce((sum, lead) => sum + Number(lead.value || 0), 0);
      return `
        <section class="card flex flex-col p-3" style="width:286px;flex:0 0 286px" data-kanban-column="${status.id}">
          <header class="flex items-start justify-between gap-2 border-b border-line pb-3">
            <div class="min-w-0">
              <div class="flex items-center gap-2">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:${esc(status.color)}"></span>
                <h2 class="truncate font-display text-[14px] font-bold">${esc(status.name)}</h2>
                ${Number(status.is_lost) ? '<span class="badge bg-cream text-[9px] text-muted">Perdido</span>' : ""}
              </div>
              <p class="mt-1 text-[11px] text-muted">${items.length} lead${items.length === 1 ? "" : "s"} · ${money(total)}</p>
            </div>
            <span class="rounded-full bg-cream px-2 py-1 text-[11px] font-bold tabular-nums">${items.length}</span>
          </header>
          <div class="mt-2 flex flex-1 flex-col gap-2" style="min-height:120px" data-kanban-drop="${status.id}">
            ${items.length === 0 ? '<p class="rounded-xl border border-dashed border-line px-3 py-6 text-center text-[11px] text-muted">Sin leads</p>' : items.map(kanbanCard).join("")}
          </div>
        </section>`;
    });

    kanban.innerHTML = `
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-[11px] text-muted">Arrastra una tarjeta a otra columna para cambiar el estado.</p>
        <p class="text-[11px] font-semibold text-muted">${leads.length} leads · ${money(leads.reduce((sum, lead) => sum + Number(lead.value || 0), 0))}</p>
      </div>
      <div style="display:flex;gap:12px;align-items:flex-start;overflow-x:auto;padding-bottom:8px;min-width:0">
        ${columns.join("")}
      </div>`;

    wireKanban();
  } catch (error) {
    kanban.innerHTML = `<div class="p-6 text-sm text-red-600">${esc(error.message)}</div>`;
  } finally {
    loading = false;
  }
}

function wireKanban() {
  kanban.querySelectorAll("[data-kanban-open]").forEach((button) => {
    button.addEventListener("click", () => {
      const id = Number(button.dataset.kanbanOpen);
      window.location.href = `/app/leads/?lead=${id}`;
    });
  });

  kanban.querySelectorAll("[data-kanban-card]").forEach((card) => {
    card.addEventListener("dragstart", (event) => {
      event.dataTransfer.setData("text/plain", card.dataset.kanbanCard);
      event.dataTransfer.effectAllowed = "move";
      card.style.opacity = "0.5";
    });
    card.addEventListener("dragend", () => {
      card.style.opacity = "";
    });
  });

  kanban.querySelectorAll("[data-kanban-drop]").forEach((column) => {
    column.addEventListener("dragover", (event) => {
      event.preventDefault();
      column.style.background = "rgba(255,90,54,0.04)";
    });
    column.addEventListener("dragleave", () => {
      column.style.background = "";
    });
    column.addEventListener("drop", async (event) => {
      event.preventDefault();
      column.style.background = "";
      const leadId = Number(event.dataTransfer.getData("text/plain"));
      const statusId = Number(column.dataset.kanbanDrop);
      if (!leadId || !statusId) return;
      const status = (meta.statuses ?? []).find((item) => Number(item.id) === statusId);
      await moveLead(leadId, statusId, status);
    });
  });
}

async function moveLead(leadId, statusId, status) {
  let lostReason = "";
  if (Number(status?.is_lost) === 1) {
    lostReason = await askLostReason(status.name);
    if (lostReason === null) return;
  }
  try {
    await request(`/leads/${leadId}`, {
      method: "PATCH",
      body: { status_id: statusId, ...(lostReason ? { lost_reason: lostReason } : {}) },
    });
    toast(lostReason ? `Lead movido a ${status.name} (${lostReason}).` : "Estado del lead actualizado.");
    await renderKanban();
    search?.dispatchEvent(new Event("input"));
  } catch (error) {
    toast(error.message, "error");
    await renderKanban();
  }
}

function askLostReason(statusName) {
  return new Promise((resolve) => {
    let settled = false;
    const dialog = openModal({
      title: "Motivo de pérdida",
      subtitle: `El lead pasará a «${statusName}». Indica por qué se ha perdido.`,
      width: "min(94vw, 460px)",
      body: `
        <form id="lost-reason-form" class="space-y-4">
          <div>
            <label class="field-label" for="lost-reason-select">Motivo</label>
            <select id="lost-reason-select" name="reason" class="select">
              ${LOST_REASONS.map((reason) => `<option value="${esc(reason)}">${esc(reason)}</option>`).join("")}
            </select>
          </div>
          <div id="lost-reason-other-wrap" class="hidden">
            <label class="field-label" for="lost-reason-other">Detalle</label>
            <input id="lost-reason-other" class="input" maxlength="120" placeholder="Describe el motivo" />
          </div>
        </form>`,
      footer: `
        <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
        <button type="submit" form="lost-reason-form" class="btn btn-primary">Guardar motivo</button>`,
      onMount: (element) => {
        const form = element.querySelector("#lost-reason-form");
        const select = element.querySelector("#lost-reason-select");
        const otherWrap = element.querySelector("#lost-reason-other-wrap");
        select?.addEventListener("change", () => {
          otherWrap?.classList.toggle("hidden", select.value !== "Otro");
        });
        element.querySelector("[data-cancel]")?.addEventListener("click", () => {
          if (!settled) {
            settled = true;
            resolve(null);
          }
          element.close();
        });
        element.addEventListener("close", () => {
          if (!settled) {
            settled = true;
            resolve(null);
          }
        });
        form?.addEventListener("submit", (event) => {
          event.preventDefault();
          const reason = select?.value ?? "Otro";
          const other = element.querySelector("#lost-reason-other")?.value.trim() ?? "";
          if (reason === "Otro" && other === "") {
            toast("Escribe el motivo de pérdida.", "error");
            return;
          }
          settled = true;
          resolve(reason === "Otro" ? other : reason);
          element.close();
        });
      },
    });
    return dialog;
  });
}

function enhanceLeadDialog(dialog) {
  const projectLink = dialog.querySelector('a[href*="lead="]');
  if (!projectLink) return;
  let leadId = 0;
  try {
    leadId = Number(new URL(projectLink.getAttribute("href"), window.location.origin).searchParams.get("lead"));
  } catch {
    leadId = 0;
  }
  if (!leadId) return;

  const body = dialog.querySelectorAll(".dialog-panel > div")[1];
  if (!body) return;

  const activityHeading = Array.from(body.querySelectorAll("h3")).find(
    (heading) => heading.textContent.trim() === "Actividad"
  );
  const section = document.createElement("div");
  section.className = "mt-6 rounded-2xl border border-line p-4";
  section.innerHTML = `
    <h3 class="font-display text-base font-bold">Registrar interacción</h3>
    <p class="mt-0.5 text-[11px] text-muted">Añade una llamada, reunión, correo o nota al historial comercial.</p>
    <form class="mt-3 space-y-3" data-interaction-form>
      <div class="grid gap-3 sm:grid-cols-2">
        <div>
          <label class="field-label">Tipo</label>
          <select name="type" class="select">
            ${Object.entries(INTERACTION_LABELS).map(([value, label]) => `<option value="${value}">${label}</option>`).join("")}
          </select>
        </div>
        <div>
          <label class="field-label">Fecha</label>
          <input name="date" type="date" class="input" value="${localToday()}" />
        </div>
      </div>
      <div>
        <label class="field-label">Descripción</label>
        <textarea name="description" class="textarea" rows="2" maxlength="2000" placeholder="Resumen de la conversación y próximos pasos…" required></textarea>
      </div>
      <div class="flex justify-end">
        <button type="submit" class="btn btn-primary btn-sm">Registrar interacción</button>
      </div>
    </form>`;
  if (activityHeading?.parentElement) {
    body.insertBefore(section, activityHeading.parentElement);
  } else {
    body.appendChild(section);
  }

  const form = section.querySelector("[data-interaction-form]");
  form?.addEventListener("submit", async (event) => {
    event.preventDefault();
    const submit = form.querySelector('[type="submit"]');
    const data = new FormData(form);
    const description = String(data.get("description") ?? "").trim();
    if (description === "") {
      toast("Escribe una descripción.", "error");
      return;
    }
    submit.disabled = true;
    try {
      const { data: activity } = await request(`/leads/${leadId}/activities`, {
        method: "POST",
        body: {
          type: data.get("type"),
          date: data.get("date"),
          description,
        },
      });
      toast("Interacción registrada.");
      form.reset();
      form.querySelector('[name="date"]').value = localToday();
      const timeline = activityHeading?.parentElement?.querySelector(".mt-3");
      if (timeline) {
        const entry = document.createElement("div");
        entry.className = "flex gap-2.5";
        entry.innerHTML = `${avatar(currentUser()?.name ?? "Sistema", currentUser()?.avatar_color ?? "#5E6472", 26)}
          <div>
            <p class="text-[12px]"><span class="font-semibold">${esc(currentUser()?.name ?? "Sistema")}</span>
              <span class="text-muted">${esc(INTERACTION_LABELS[activity.type] ?? "Interacción")}: ${esc(activity.description)}</span></p>
            <p class="mt-0.5 text-[10px] text-muted/70">${esc(formatDate(activity.date))}</p>
          </div>`;
        timeline.prepend(entry);
      }
    } catch (error) {
      toast(error.message, "error");
    } finally {
      submit.disabled = false;
    }
  });

  mountAttachments(dialog, "lead", leadId);
}

async function init() {
  await boot();
  try {
    meta = (await request("/meta")).data;
  } catch (error) {
    toast(error.message, "error");
    return;
  }

  switchButtons.forEach((button) => {
    button.addEventListener("click", () => setView(button.dataset.leadsView));
  });
  setView("table");

  let timer = null;
  const refreshKanban = () => {
    if (view !== "kanban") return;
    window.clearTimeout(timer);
    timer = window.setTimeout(renderKanban, 250);
  };
  search?.addEventListener("input", refreshKanban);
  statusFilter?.addEventListener("change", refreshKanban);
  ownerFilter?.addEventListener("change", refreshKanban);
  document.getElementById("clear-filters")?.addEventListener("click", refreshKanban);

  observeDialogs(enhanceLeadDialog);
}

init();
