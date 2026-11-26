import { o as openModal, l as toast } from "./ui.CJdB5Grl.js";
import { request } from "./crm-extras.js";

const TYPES = {
  error: "Error",
  mejora: "Mejora",
  pregunta: "Pregunta",
};

function mountButton() {
  if (document.getElementById("report-issue")) return;
  const helpLink = Array.from(document.querySelectorAll("aside a")).find(
    (link) =>
      (link.getAttribute("href") ?? "").endsWith("/app/help/") ||
      link.textContent.includes("Ayuda y formación")
  );
  if (!helpLink) return;

  const button = document.createElement("button");
  button.type = "button";
  button.id = "report-issue";
  button.className =
    "mt-4 flex w-full items-start gap-3 rounded-2xl border border-line bg-white/70 p-4 text-left transition hover:border-accent/40 hover:bg-white";
  button.innerHTML = `
    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-accent-soft text-accent-dark">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="h-4 w-4" aria-hidden="true"><path d="M12 9v4"/><path d="M12 17h.01"/><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/></svg>
    </span>
    <span class="min-w-0">
      <span class="flex items-center gap-1.5 text-xs font-semibold text-ink">Reportar un problema</span>
      <span class="text-[11px] leading-snug text-muted">Cuéntanos qué falla o qué echas en falta</span>
    </span>`;
  button.addEventListener("click", openFeedback);
  helpLink.parentElement.insertBefore(button, helpLink);
}

function formBody() {
  return `
    <form id="feedback-form" class="space-y-4">
      <div class="rounded-xl border border-line bg-sand px-3 py-2.5 text-[11px] leading-relaxed text-[#8A5A00]">
        El reporte se publica como <strong>issue público en GitHub</strong>.
        No incluyas nombres de clientes, correos, importes ni contraseñas.
      </div>
      <div>
        <label class="field-label" for="feedback-type">Tipo</label>
        <select id="feedback-type" name="type" class="select">
          ${Object.entries(TYPES)
            .map(([value, label]) => `<option value="${value}">${label}</option>`)
            .join("")}
        </select>
      </div>
      <div>
        <label class="field-label" for="feedback-title">Título *</label>
        <input id="feedback-title" name="title" class="input" maxlength="140" required
          placeholder="Ej. El contador de leads no coincide al paginar" />
      </div>
      <div>
        <label class="field-label" for="feedback-description">¿Qué ocurre? *</label>
        <textarea id="feedback-description" name="description" class="textarea" rows="5" maxlength="5000" required
          placeholder="Qué esperabas, qué pasó y cómo reproducirlo."></textarea>
        <p class="mt-1 text-[10px] text-muted">Mínimo 15 caracteres.</p>
      </div>
      <label class="flex items-start gap-2 text-[12px]">
        <input type="checkbox" name="include_context" value="1" checked class="mt-0.5" />
        <span>Incluir contexto técnico (versión, PHP, navegador y página).</span>
      </label>
    </form>`;
}

function openFeedback() {
  openModal({
    title: "Reportar un problema",
    subtitle: "El reporte llega al repositorio del proyecto en GitHub.",
    width: "min(94vw, 560px)",
    body: formBody(),
    footer: `<button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
      <button type="submit" form="feedback-form" class="btn btn-primary" data-submit>Enviar reporte</button>`,
    onMount: (dialog) => {
      dialog.querySelector("[data-cancel]")?.addEventListener("click", () => dialog.close());
      const form = dialog.querySelector("#feedback-form");
      form?.addEventListener("submit", async (event) => {
        event.preventDefault();
        const submit = dialog.querySelector("[data-submit]");
        const data = new FormData(form);
        const description = String(data.get("description") ?? "").trim();
        if (description.length < 15) {
          toast("Cuéntanos un poco más: al menos 15 caracteres.", "error");
          return;
        }
        if (submit) submit.disabled = true;
        try {
          const response = await request("/feedback", {
            method: "POST",
            body: {
              type: data.get("type"),
              title: String(data.get("title") ?? "").trim(),
              description,
              page: window.location.pathname,
              include_context: data.get("include_context") === "1",
            },
          });
          dialog.close();
          toast("Reporte enviado a GitHub.");
          openModal({
            title: "Gracias por el reporte",
            subtitle: "Ya está registrado en GitHub.",
            width: "min(94vw, 460px)",
            body: `<p class="text-[13px] leading-relaxed text-ink/80">Puedes seguir el estado de la incidencia en el repositorio del proyecto.</p>`,
            footer: `<button type="button" class="btn btn-secondary" data-close>Cerrar</button>
              <a class="btn btn-primary" href="${response.url}" target="_blank" rel="noopener noreferrer">Abrir issue #${response.number}</a>`,
            onMount: (success) => {
              success.querySelector("[data-close]")?.addEventListener("click", () => success.close());
            },
          });
        } catch (error) {
          toast(error.message, "error");
        } finally {
          if (submit) submit.disabled = false;
        }
      });
    },
  });
}

if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", mountButton);
} else {
  mountButton();
}
