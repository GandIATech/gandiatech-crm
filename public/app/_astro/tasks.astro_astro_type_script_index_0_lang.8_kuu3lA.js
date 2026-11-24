import{a as e,d as t,i as n,l as r,n as i,o as a,r as o,s,t as c,u as l}from"./ui.CJdB5Grl.js";import{f as u,h as d,n as f,o as p,r as m,s as h,u as g}from"./format.BIwoWcf9.js";var _,v,y=new Map,b=`open`,x=1,S=1,C=0,w=``,T=``,E=``,D=document.getElementById(`tasks-table`),O=document.getElementById(`tasks-summary`),k=document.getElementById(`task-search`),A=document.getElementById(`task-priority-filter`),j=document.getElementById(`task-owner-filter`),M=document.getElementById(`tasks-pagination`),N={open:`Tareas abiertas`,overdue:`Tareas vencidas`,completed:`Tareas completadas`,all:`Todas las tareas`};function P(){document.querySelectorAll(`[data-view]`).forEach(e=>{let t=e.dataset.view===b;e.classList.toggle(`bg-white`,t),e.classList.toggle(`text-ink`,t),e.classList.toggle(`shadow-sm`,t),e.classList.toggle(`text-muted`,!t)})}function F(){O.textContent=`${N[b]} · ${C}`,document.getElementById(`tasks-caption`).textContent=w?`Resultados para «${w}»`:`Ordenadas por fecha y prioridad.`;let t=document.getElementById(`overdue-chip`);b===`overdue`&&C>0&&t?(t.textContent=`${C} requieren atención`,t.classList.remove(`hidden`)):t?.classList.add(`hidden`),C===0?D.innerHTML=n(w?`Sin resultados`:b===`completed`?`Sin tareas completadas`:b===`overdue`?`Nada vencido`:`Todo al día`,w?`Prueba con otra búsqueda.`:b===`overdue`?`No hay tareas pendientes con fecha pasada. Buen trabajo.`:`Crea una tarea para organizar el siguiente paso.`,`<button type="button" class="btn btn-primary btn-sm" data-empty-new>Nueva tarea</button>`):(D.innerHTML=`
          <table class="data-table">
            <thead><tr><th class="w-10"></th><th>Tarea</th><th>Vence</th><th>Prioridad</th><th>Asignada</th><th>Relacionada</th><th>Estado</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              ${Array.from(y.values()).map(t=>{let n=t.status===`completada`,r=m(t.due_date,t.status);return`
                    <tr class="${n?`bg-cream/30`:``}">
                      <td>
                        <button type="button" data-toggle="${t.id}" class="flex h-5 w-5 items-center justify-center rounded-full border-2 ${n?`border-emerald-500 bg-emerald-500 text-white`:`border-line text-transparent hover:border-accent hover:text-accent`}" aria-label="${n?`Reabrir tarea`:`Completar tarea`}">
                          <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                        </button>
                      </td>
                      <td class="max-w-[320px]">
                        <button type="button" data-edit="${t.id}" class="block max-w-full truncate text-left text-[13px] font-semibold hover:text-accent-dark ${n?`text-muted line-through`:``}">${e(t.title)}</button>
                        ${t.description?`<p class="mt-0.5 line-clamp-1 text-[11px] text-muted">${e(t.description)}</p>`:``}
                      </td>
                      <td class="whitespace-nowrap text-[11px] ${r?`font-semibold text-red-600`:`text-muted`}">${e(f(t.due_date,t.status))}</td>
                      <td><span class="badge" style="${h[t.priority]??``}">${e(p[t.priority]??t.priority)}</span></td>
                      <td>${t.assigned_name?`<span class="flex items-center gap-2">${c(t.assigned_name,t.assigned_color,26)}<span class="max-w-[110px] truncate text-[12px]">${e(t.assigned_name)}</span></span>`:`<span class="text-[11px] text-muted">Sin asignar</span>`}</td>
                      <td class="max-w-[160px]">
                        ${t.lead_title?`<a href="/app/leads/?lead=${t.lead_id}" class="block truncate text-[11px] font-medium text-muted hover:text-accent-dark">Lead · ${e(t.lead_title)}</a>`:``}
                        ${t.project_name?`<a href="/app/projects/?project=${t.project_id}" class="block truncate text-[11px] font-medium text-muted hover:text-accent-dark">Proyecto · ${e(t.project_name)}</a>`:``}
                        ${!t.lead_title&&!t.project_name?`<span class="text-[11px] text-muted">—</span>`:``}
                      </td>
                      <td><span class="badge ${n?`bg-mint/60 text-[#1C6B45]`:t.status===`en_progreso`?`bg-sky/60 text-[#1E4C82]`:`bg-cream text-muted`}">${e(u[t.status]??t.status)}</span></td>
                      <td>
                        <div class="flex justify-end gap-1">
                          <button type="button" data-edit-icon="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar tarea"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                          <button type="button" data-delete="${t.id}" class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600" aria-label="Eliminar tarea"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                        </div>
                      </td>
                    </tr>`}).join(``)}
            </tbody>
          </table>`,D.querySelectorAll(`[data-toggle]`).forEach(e=>{e.addEventListener(`click`,()=>void L(Number(e.dataset.toggle)))}),D.querySelectorAll(`[data-edit], [data-edit-icon]`).forEach(e=>{let t=Number(e.dataset.edit??e.dataset.editIcon);e.addEventListener(`click`,()=>{let e=y.get(t);e&&z(e)})}),D.querySelectorAll(`[data-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void B(Number(e.dataset.delete)))})),D.querySelectorAll(`[data-empty-new]`).forEach(e=>{e.addEventListener(`click`,()=>z())}),M.classList.toggle(`hidden`,C===0),M.classList.toggle(`flex`,C>0),document.getElementById(`tasks-page-caption`).textContent=`Mostrando ${(x-1)*20+1}–${Math.min(x*20,C)} de ${C}`,document.getElementById(`tasks-indicator`).textContent=`${x} / ${S}`,document.getElementById(`tasks-prev`).disabled=x<=1,document.getElementById(`tasks-next`).disabled=x>=S}async function I(){let n={q:w,priority:T,assigned_to:E,page:x,per_page:20};b===`open`&&(n.open=1),b===`overdue`&&(n.overdue=1),b===`completed`&&(n.status=`completada`);try{let e=await l(`/tasks`,{params:n});y=new Map(e.data.map(e=>[e.id,e])),C=Number(e.meta.total??0),S=Number(e.meta.pages??1),F()}catch(n){D.innerHTML=`<div class="px-5 py-10 text-center text-sm text-muted">${e(t(n))}</div>`}}async function L(e){let n=y.get(e);if(!n)return;let i=n.status===`completada`?`pendiente`:`completada`;try{await l(`/tasks/${e}`,{method:`PATCH`,body:{status:i}}),r(i===`completada`?`Tarea completada.`:`Tarea reabierta.`),await I()}catch(e){r(t(e),`error`)}}function R(t,n,r){return`<option value="">${r}</option>`+t.map(t=>`<option value="${t.value}" ${String(t.value)===String(n??``)?`selected`:``}>${e(t.label)}</option>`).join(``)}function z(n){let i=!!n;a({title:i?`Editar tarea`:`Nueva tarea`,subtitle:i?`Creada por ${n?.creator_name||`el equipo`} · ${g(n?.created_at)}`:`Define el siguiente paso, su fecha y responsable.`,width:`min(94vw, 680px)`,body:`
          <form id="task-editor" data-task-id="${i?n?.id??``:``}" class="space-y-4">
            <div>
              <label class="field-label" for="editor-title">Título *</label>
              <input id="editor-title" name="title" class="input" maxlength="180" value="${e(n?.title??``)}" placeholder="Ej. Preparar propuesta económica" required autofocus />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label" for="editor-due">Fecha límite</label>
                <input id="editor-due" name="due_date" type="date" class="input" value="${e(n?.due_date??``)}" />
              </div>
              <div>
                <label class="field-label" for="editor-priority">Prioridad</label>
                <select id="editor-priority" name="priority" class="select">
                  ${[`baja`,`media`,`alta`].map(e=>`<option value="${e}" ${(n?.priority??`media`)===e?`selected`:``}>${p[e]}</option>`).join(``)}
                </select>
              </div>
              <div>
                <label class="field-label" for="editor-status">Estado</label>
                <select id="editor-status" name="status" class="select">
                  ${[`pendiente`,`en_progreso`,`completada`].map(e=>`<option value="${e}" ${(n?.status??`pendiente`)===e?`selected`:``}>${u[e]}</option>`).join(``)}
                </select>
              </div>
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label" for="editor-assigned">Asignada a</label>
                <select id="editor-assigned" name="assigned_to" class="select">
                  ${R(v.users.map(e=>({value:e.id,label:e.name})),n?.assigned_to??_.user.id,`Sin asignar`)}
                </select>
              </div>
              <div>
                <label class="field-label" for="editor-lead">Lead</label>
                <select id="editor-lead" name="lead_id" class="select">
                  ${R(v.leads.map(e=>({value:e.id,label:e.title??``})),n?.lead_id??null,`Sin lead`)}
                </select>
              </div>
              <div class="sm:col-span-2">
                <label class="field-label" for="editor-project">Proyecto</label>
                <select id="editor-project" name="project_id" class="select">
                  ${R(v.projects.map(e=>({value:e.id,label:e.name??``})),n?.project_id??null,`Sin proyecto`)}
                </select>
              </div>
            </div>
            <div>
              <label class="field-label" for="editor-description">Descripción</label>
              <textarea id="editor-description" name="description" class="textarea" rows="4" placeholder="Detalles, acuerdos o criterios de done…">${e(n?.description??``)}</textarea>
            </div>
          </form>
        `,footer:`
          ${i?`<button type="button" class="btn btn-danger mr-auto" data-delete-editor>Eliminar</button>`:``}
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="task-editor" class="btn btn-primary" data-submit>${i?`Guardar cambios`:`Crear tarea`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close()),e.querySelector(`[data-delete-editor]`)?.addEventListener(`click`,async()=>{n&&(e.close(),await B(n.id))});let a=e.querySelector(`#task-editor`);a?.addEventListener(`submit`,async o=>{o.preventDefault();let c=e.querySelector(`[data-submit]`);if(!c||!a)return;let u=new FormData(a),d={title:u.get(`title`),due_date:u.get(`due_date`),priority:u.get(`priority`),status:u.get(`status`),assigned_to:u.get(`assigned_to`)||null,lead_id:u.get(`lead_id`)||null,project_id:u.get(`project_id`)||null,description:u.get(`description`)};s(c,!0);try{i&&n?(await l(`/tasks/${n.id}`,{method:`PATCH`,body:d}),r(`Tarea actualizada.`)):(await l(`/tasks`,{method:`POST`,body:d}),r(`Tarea creada.`)),e.close(),await I()}catch(e){r(t(e),`error`),s(c,!1)}})}})}async function B(e){let n=y.get(e);if(await i({title:`Eliminar tarea`,message:`Se moverá «${n?.title??`esta tarea`}» a la papelera. Podrás restaurarla desde Administración.`,confirmLabel:`Eliminar tarea`}))try{await l(`/tasks/${e}`,{method:`DELETE`}),r(`Tarea movida a la papelera.`),await I()}catch(e){r(t(e),`error`)}}async function V(e){try{z((await l(`/tasks/${e}`)).data)}catch(e){r(t(e),`error`)}}async function H(){let n=await d();if(!n)return;_=n;try{v=(await l(`/meta`)).data,j.innerHTML=`<option value="">Todo el equipo</option>`+v.users.map(t=>`<option value="${t.id}">${e(t.name)}</option>`).join(``)}catch(e){r(t(e),`error`);return}let i=new URLSearchParams(window.location.search),a=i.get(`view`);a&&[`open`,`overdue`,`completed`,`all`].includes(a)&&(b=a),P(),await I();let o=Number(i.get(`task`));o>0&&await V(o)}document.querySelectorAll(`[data-view]`).forEach(e=>{e.addEventListener(`click`,()=>{b=e.dataset.view??`open`,x=1,P(),I()})}),k.addEventListener(`input`,o(()=>{w=k.value.trim(),x=1,I()},350)),A.addEventListener(`change`,()=>{T=A.value,x=1,I()}),j.addEventListener(`change`,()=>{E=j.value,x=1,I()}),document.getElementById(`tasks-prev`)?.addEventListener(`click`,()=>{x>1&&(--x,I())}),document.getElementById(`tasks-next`)?.addEventListener(`click`,()=>{x<S&&(x+=1,I())}),document.getElementById(`new-task-full`)?.addEventListener(`click`,()=>z()),H();