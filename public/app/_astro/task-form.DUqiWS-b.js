import{a as e,d as t,l as n,o as r,s as i,u as a}from"./ui.CJdB5Grl.js";import{p as o}from"./format.BIwoWcf9.js";var s=null;async function c(){return s||(s=(await a(`/meta`)).data,s)}async function l(s={}){let l=await c(),u=l.users.map(t=>`<option value="${t.id}" ${s.assignedTo===t.id?`selected`:``}>${e(t.name??``)}</option>`).join(``),d=l.leads.map(t=>`<option value="${t.id}" ${s.leadId===t.id?`selected`:``}>${e(t.title??``)}</option>`).join(``),f=l.projects.map(t=>`<option value="${t.id}" ${s.projectId===t.id?`selected`:``}>${e(t.name??``)}</option>`).join(``);r({title:`Nueva tarea`,subtitle:`Planifica el siguiente paso y asígnalo a una persona del equipo.`,width:`min(94vw, 620px)`,body:`
      <form id="task-form" class="space-y-4">
        <div>
          <label class="field-label" for="task-title">Título *</label>
          <input id="task-title" name="title" class="input" maxlength="180" placeholder="Ej. Llamar para validar el alcance" required autofocus />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="task-due">Fecha límite</label>
            <input id="task-due" name="due_date" type="date" class="input" value="${o()}" />
          </div>
          <div>
            <label class="field-label" for="task-priority">Prioridad</label>
            <select id="task-priority" name="priority" class="select">
              <option value="baja">Baja</option>
              <option value="media" selected>Media</option>
              <option value="alta">Alta</option>
            </select>
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="task-assigned">Asignada a</label>
            <select id="task-assigned" name="assigned_to" class="select">${u}</select>
          </div>
          <div>
            <label class="field-label" for="task-status">Estado</label>
            <select id="task-status" name="status" class="select">
              <option value="pendiente" selected>Pendiente</option>
              <option value="en_progreso">En progreso</option>
              <option value="completada">Completada</option>
            </select>
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="task-lead">Lead relacionado</label>
            <select id="task-lead" name="lead_id" class="select">
              <option value="">Sin lead</option>
              ${d}
            </select>
          </div>
          <div>
            <label class="field-label" for="task-project">Proyecto</label>
            <select id="task-project" name="project_id" class="select">
              <option value="">Sin proyecto</option>
              ${f}
            </select>
          </div>
        </div>
        <div>
          <label class="field-label" for="task-description">Descripción</label>
          <textarea id="task-description" name="description" class="textarea" rows="3" placeholder="Contexto, acuerdos o siguiente paso…"></textarea>
        </div>
      </form>
    `,footer:`
      <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
      <button type="submit" form="task-form" class="btn btn-primary" data-submit>Crear tarea</button>
    `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let r=e.querySelector(`#task-form`);r?.addEventListener(`submit`,async o=>{o.preventDefault();let c=e.querySelector(`[data-submit]`);if(!c||!r)return;let l=new FormData(r);i(c,!0,`Creando…`);try{await a(`/tasks`,{method:`POST`,body:{title:l.get(`title`),description:l.get(`description`),due_date:l.get(`due_date`),priority:l.get(`priority`),status:l.get(`status`),assigned_to:l.get(`assigned_to`)||null,lead_id:l.get(`lead_id`)||null,project_id:l.get(`project_id`)||null}}),n(`Tarea creada correctamente.`),e.close(),s.onSaved?.()}catch(e){n(t(e),`error`),i(c,!1)}})}})}export{l as t};