import { o as openModal, l as toast } from "./ui.CJdB5Grl.js";
import { request } from "./crm-extras.js";

const TYPES = {
  error: "Error",
  mejora: "Sugerencia",
  pregunta: "Duda",
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
  button.className = "btn btn-secondary mt-4 w-full";
  button.textContent = "Enviar comentarios";
  button.addEventListener("click", openFeedback);
  helpLink.parentElement.insertBefore(button, helpLink);
}

function formBody() {
  return `
    <form id="feedback-form" class="space-y-4">
      <div class="rounded-xl border border-line bg-sand px-3 py-2.5 text-[11px] leading-relaxed text-[#8A5A00]">
        Tu comentario llega por correo al equipo de GandiaTech.
        No incluyas contraseñas ni datos sensibles de clientes.
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
    title: "Enviar comentarios",
    subtitle: "El mensaje llega por correo al equipo de GandiaTech.",
    width: "min(94vw, 560px)",
    body: formBody(),
    footer: `<button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
      <button type="submit" form="feedback-form" class="btn btn-primary" data-submit>Enviar comentario</button>`,
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
          toast("Comentario enviado.");
          openModal({
            title: "Gracias por el comentario",
            subtitle: "Lo hemos recibido por correo.",
            width: "min(94vw, 460px)",
            body: `<p class="text-[13px] leading-relaxed text-ink/80">El equipo de GandiaTech lo revisará y, si hace falta respuesta, escribirá al correo de tu cuenta.</p>`,
            footer: `<button type="button" class="btn btn-primary" data-close>Entendido</button>`,
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
