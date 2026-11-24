import{a as e,d as t,i as n,l as r,n as i,o as a,r as o,s,t as c,u as l}from"./ui.CJdB5Grl.js";import{c as u,h as d,i as f,l as p,n as m,t as h,u as g}from"./format.BIwoWcf9.js";import{t as _}from"./task-form.DUqiWS-b.js";var v,y,b=new Map,x=1,S=1,C=0,w=0,T=``,E=``,D=``,O=document.getElementById(`projects-grid`),k=document.getElementById(`projects-summary`),A=document.getElementById(`projects-budget`),j=document.getElementById(`project-search`),M=document.getElementById(`project-status`),N=document.getElementById(`project-owner`),P=document.getElementById(`projects-pagination`);function F(){k.textContent=`${C} ${C===1?`proyecto`:`proyectos`}`,A.textContent=C>0?`Presupuesto filtrado: ${f(w)}`:``,C===0?(O.className=`grid gap-4`,O.innerHTML=`<div class="card">${n(T||E||D?`Sin proyectos que coincidan`:`Todavía no hay proyectos`,T||E||D?`Ajusta los filtros para ver otros resultados.`:`Convierte un lead ganado en proyecto o crea uno desde cero.`,`<button type="button" class="btn btn-primary btn-sm" data-empty-new>Nuevo proyecto</button>`)}</div>`):(O.className=`grid gap-4 md:grid-cols-2 2xl:grid-cols-3`,O.innerHTML=Array.from(b.values()).map(t=>{let n=p[t.status]??`background:#F1EFEA;color:#5E6472`,r=Number(t.progress)||0;return`
              <article class="card flex flex-col p-5 transition hover:border-accent/30 hover:shadow-soft">
                <div class="flex items-start justify-between gap-2">
                  <span class="badge" style="${n}">${e(u[t.status]??t.status)}</span>
                  <div class="flex gap-0.5">
                    <button type="button" data-edit="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar proyecto"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                    <button type="button" data-delete="${t.id}" class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600" aria-label="Eliminar proyecto"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                  </div>
                </div>

                <button type="button" data-detail="${t.id}" class="mt-3 text-left">
                  <h2 class="line-clamp-2 font-display text-[17px] font-bold leading-snug hover:text-accent-dark">${e(t.name)}</h2>
                  <p class="mt-1 truncate text-[12px] text-muted">${e(t.contact_name||t.contact_company||`Sin cliente`)}${t.lead_title?` · desde lead`:``}</p>
                </button>

                <div class="mt-4 flex items-center justify-between text-[12px]">
                  <span class="font-display text-lg font-bold">${f(t.budget)}</span>
                  <span class="text-muted">${t.tasks_done}/${t.tasks_total} tareas</span>
                </div>

                <div class="mt-3">
                  <div class="flex items-center justify-between text-[10px] font-semibold uppercase tracking-wide text-muted"><span>Avance</span><span>${r}%</span></div>
                  <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-line/70"><span class="block h-full rounded-full bg-accent transition-all" style="width:${r}%"></span></div>
                </div>

                <div class="mt-4 flex items-center justify-between gap-2 border-t border-line/70 pt-3">
                  <div class="flex items-center gap-1.5 text-[11px] text-muted">
                    ${c(t.owner_name||`?`,t.owner_color,24)}
                    <span class="max-w-[100px] truncate">${e(t.owner_name||`Sin asignar`)}</span>
                  </div>
                  <span class="text-[11px] text-muted">${t.end_date?e(m(t.end_date,t.status===`completado`?`completada`:void 0)):`Sin fecha fin`}</span>
                </div>
              </article>`}).join(``)),O.querySelectorAll(`[data-detail]`).forEach(e=>{e.addEventListener(`click`,()=>void B(Number(e.dataset.detail)))}),O.querySelectorAll(`[data-edit]`).forEach(e=>{let t=b.get(Number(e.dataset.edit));t&&e.addEventListener(`click`,()=>R(t))}),O.querySelectorAll(`[data-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void z(Number(e.dataset.delete)))}),O.querySelectorAll(`[data-empty-new]`).forEach(e=>{e.addEventListener(`click`,()=>R())}),P.classList.toggle(`hidden`,C===0),P.classList.toggle(`flex`,C>0),document.getElementById(`projects-caption`).textContent=`Mostrando ${(x-1)*12+1}–${Math.min(x*12,C)} de ${C}`,document.getElementById(`projects-indicator`).textContent=`${x} / ${S}`,document.getElementById(`projects-prev`).disabled=x<=1,document.getElementById(`projects-next`).disabled=x>=S}async function I(){try{let e=await l(`/projects`,{params:{q:T,status:E,owner_id:D,page:x,per_page:12}});b=new Map(e.data.map(e=>[e.id,e])),C=Number(e.meta.total??0),S=Number(e.meta.pages??1),w=e.data.reduce((e,t)=>e+Number(t.budget),0),F()}catch(n){O.innerHTML=`<div class="card px-5 py-10 text-center text-sm text-muted">${e(t(n))}</div>`}}function L(t,n,r){return`<option value="">${r}</option>`+t.map(t=>`<option value="${t.id}" ${t.id===n?`selected`:``}>${e(t.label)}</option>`).join(``)}function R(n,i){let o=!!n,c=n,d=c?.status??`planificado`,f=c?.owner_id??v.user.id,p=c?.lead_id??i?.leadId??null,m=c?.contact_id??i?.contactId??null,h=c?.name??i?.name??``,g=c?.budget??i?.budget??0;a({title:o?`Editar proyecto`:`Nuevo proyecto`,subtitle:`Planifica la entrega, el presupuesto y el equipo implicado.`,width:`min(94vw, 760px)`,body:`
          <form id="project-form" class="space-y-4">
            <div>
              <label class="field-label" for="project-name">Nombre del proyecto *</label>
              <input id="project-name" name="name" class="input" maxlength="160" value="${e(h)}" placeholder="Ej. Implantación de auditoría" required autofocus />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label" for="project-form-status">Estado</label>
                <select id="project-form-status" name="status" class="select">
                  ${[`planificado`,`en_progreso`,`en_pausa`,`completado`,`cancelado`].map(e=>`<option value="${e}" ${d===e?`selected`:``}>${u[e]}</option>`).join(``)}
                </select>
              </div>
              <div>
                <label class="field-label" for="project-budget">Presupuesto (€)</label>
                <input id="project-budget" name="budget" type="number" min="0" step="0.01" class="input" value="${Number(g)}" />
              </div>
              <div>
                <label class="field-label" for="project-owner">Responsable</label>
                <select id="project-owner" name="owner_id" class="select">
                  ${y.users.map(t=>`<option value="${t.id}" ${t.id===f?`selected`:``}>${e(t.name)}</option>`).join(``)}
                </select>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label" for="project-contact">Contacto</label>
                <select id="project-contact" name="contact_id" class="select">
                  ${L(y.contacts.map(e=>({id:e.id,label:e.name+(e.company?` · `+e.company:``)})),m,`Sin contacto`)}
                </select>
              </div>
              <div>
                <label class="field-label" for="project-lead">Lead de origen</label>
                <select id="project-lead" name="lead_id" class="select">
                  ${L(y.leads.map(e=>({id:e.id,label:e.title??``})),p,`Sin lead`)}
                </select>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label" for="project-start">Inicio</label>
                <input id="project-start" name="start_date" type="date" class="input" value="${e(c?.start_date??``)}" />
              </div>
              <div>
                <label class="field-label" for="project-end">Fin previsto</label>
                <input id="project-end" name="end_date" type="date" class="input" value="${e(c?.end_date??``)}" />
              </div>
              <div>
                <label class="field-label" for="project-progress">Avance <output id="progress-output" class="text-accent-dark">${Number(c?.progress??0)}%</output></label>
                <input id="project-progress" name="progress" type="range" min="0" max="100" step="5" value="${Number(c?.progress??0)}" class="mt-3 w-full accent-[#FF5A36]" />
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label" for="project-template">Plantilla de tareas</label>
                <select id="project-template" name="template_id" class="select">
                  <option value="">Sin plantilla</option>
                </select>
                <p class="mt-1 text-[10px] text-muted">Se aplica al crear el proyecto.</p>
              </div>
              <div>
                <label class="field-label" for="project-budgeted-hours">Horas presupuestadas</label>
                <input id="project-budgeted-hours" name="budgeted_hours" type="number" min="0" step="0.5" class="input" value="${c?.budgeted_hours??``}" placeholder="Ej. 40" />
              </div>
              <div>
                <label class="field-label" for="project-hourly-rate">Tarifa por hora (€)</label>
                <input id="project-hourly-rate" name="hourly_rate" type="number" min="0" step="0.01" class="input" value="${c?.hourly_rate??``}" placeholder="Ej. 45" />
              </div>
            </div>

            <div>
              <label class="field-label" for="project-description">Descripción</label>
              <textarea id="project-description" name="description" class="textarea" rows="3" placeholder="Alcance, entregables, hitos…">${e(c?.description??``)}</textarea>
            </div>
          </form>
        `,footer:`
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="project-form" class="btn btn-primary" data-submit>${o?`Guardar cambios`:`Crear proyecto`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let n=e.querySelector(`#project-progress`);n?.addEventListener(`input`,()=>{let t=e.querySelector(`#progress-output`);t&&(t.textContent=`${n.value}%`)});let i=e.querySelector(`#project-form`);i?.addEventListener(`submit`,async n=>{n.preventDefault();let a=e.querySelector(`[data-submit]`);if(!a||!i)return;let u=new FormData(i),d={name:u.get(`name`),status:u.get(`status`),budget:u.get(`budget`),owner_id:u.get(`owner_id`),contact_id:u.get(`contact_id`)||null,lead_id:u.get(`lead_id`)||null,start_date:u.get(`start_date`),end_date:u.get(`end_date`),progress:u.get(`progress`),template_id:u.get(`template_id`)||null,budgeted_hours:u.get(`budgeted_hours`)||null,hourly_rate:u.get(`hourly_rate`)||null,description:u.get(`description`)};s(a,!0);try{o&&c?(await l(`/projects/${c.id}`,{method:`PATCH`,body:d}),r(`Proyecto actualizado.`)):(await l(`/projects`,{method:`POST`,body:d}),r(`Proyecto creado.`)),e.close(),await I()}catch(e){r(t(e),`error`),s(a,!1)}})}})}async function z(e){let n=b.get(e);if(await i({title:`Eliminar proyecto`,message:`Se moverá «${n?.name??`este proyecto`}» a la papelera junto con sus tareas asociadas. Podrás restaurarlo desde Administración.`,confirmLabel:`Eliminar proyecto`}))try{await l(`/projects/${e}`,{method:`DELETE`}),r(`Proyecto eliminado.`),await I()}catch(e){r(t(e),`error`)}}async function B(n){try{let{project:t,tasks:r,activities:i}=(await l(`/projects/${n}`)).data,o=Number(t.progress)||0;a({title:t.name,subtitle:[t.contact_name,t.lead_title?`Lead: ${t.lead_title}`:``].filter(Boolean).join(` · `)||`Proyecto`,width:`min(94vw, 720px)`,body:`
            <span class="hidden" data-project-id="${t.id}"></span>
            <div class="flex flex-wrap items-center gap-2">
              <span class="badge" style="${p[t.status]??``}">${e(u[t.status]??t.status)}</span>
              <span class="ml-auto font-display text-2xl font-bold">${f(t.budget)}</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-3">
              <div class="rounded-2xl border border-line bg-cream/50 p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Responsable</p>
                <p class="mt-1.5 truncate text-[13px] font-semibold">${e(t.owner_name||`Sin asignar`)}</p>
              </div>
              <div class="rounded-2xl border border-line bg-cream/50 p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Inicio</p>
                <p class="mt-1.5 text-[13px] font-semibold">${e(h(t.start_date))}</p>
              </div>
              <div class="rounded-2xl border border-line bg-cream/50 p-3.5">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Fin previsto</p>
                <p class="mt-1.5 text-[13px] font-semibold">${e(h(t.end_date))}</p>
              </div>
            </div>

            <div class="mt-4">
              <div class="flex items-center justify-between text-[11px] font-semibold uppercase tracking-wide text-muted"><span>Avance del proyecto</span><span>${o}%</span></div>
              <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-line/70"><span class="block h-full rounded-full bg-accent" style="width:${o}%"></span></div>
            </div>

            ${t.description?`<div class="mt-4 rounded-2xl border border-line p-4"><p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Descripción</p><p class="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-ink/80">${e(t.description)}</p></div>`:``}

            <div class="mt-6 flex items-center justify-between">
              <h3 class="font-display text-base font-bold">Tareas (${t.tasks_done}/${t.tasks_total})</h3>
              <button type="button" class="text-[11px] font-semibold text-accent-dark hover:underline" data-add-task>Añadir tarea</button>
            </div>
            <div class="mt-2 space-y-1">
              ${r.length===0?`<p class="rounded-2xl border border-dashed border-line px-4 py-4 text-[12px] text-muted">Sin tareas asociadas todavía.</p>`:r.map(t=>`
                <div class="flex items-center gap-3 rounded-xl px-1 py-2">
                  <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${t.status===`completada`?`border-emerald-500 bg-emerald-500 text-white`:`border-line`}">
                    ${t.status===`completada`?`<svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>`:``}
                  </span>
                  <span class="min-w-0 flex-1 text-[12.5px] ${t.status===`completada`?`text-muted line-through`:``}">${e(t.title)}</span>
                  <span class="shrink-0 text-[10px] uppercase tracking-wide text-muted">${e(t.due_date?h(t.due_date):`Sin fecha`)}</span>
                </div>`).join(``)}
            </div>

            <div class="mt-6">
              <h3 class="font-display text-base font-bold">Actividad</h3>
              <div class="mt-3 space-y-3">
                ${i.length===0?`<p class="text-[12px] text-muted">Sin actividad registrada.</p>`:i.map(t=>`<div class="flex gap-2.5">${c(t.user_name||`Sistema`,t.avatar_color,26)}<div><p class="text-[12px]"><span class="font-semibold">${e(t.user_name||`Sistema`)}</span> <span class="text-muted">${e(t.description)}</span></p><p class="mt-0.5 text-[10px] text-muted/70">${e(g(t.created_at))}</p></div></div>`).join(``)}
              </div>
            </div>
          `,footer:`
            <button type="button" class="btn btn-danger mr-auto" data-delete-detail>Eliminar</button>
            <button type="button" class="btn btn-secondary" data-edit-detail>Editar proyecto</button>
            <button type="button" class="btn btn-primary" data-add-task-footer>Añadir tarea</button>
          `,onMount:e=>{let n=()=>{_({projectId:t.id,onSaved:()=>{e.close(),I()}})};e.querySelector(`[data-edit-detail]`)?.addEventListener(`click`,()=>{e.close(),R(t)}),e.querySelector(`[data-add-task]`)?.addEventListener(`click`,n),e.querySelector(`[data-add-task-footer]`)?.addEventListener(`click`,n),e.querySelector(`[data-delete-detail]`)?.addEventListener(`click`,async()=>{e.close(),await z(t.id)})}})}catch(e){r(t(e),`error`)}}async function V(){let n=await d();if(!n)return;v=n;try{y=(await l(`/meta`)).data,N.innerHTML=`<option value="">Todos los responsables</option>`+y.users.map(t=>`<option value="${t.id}">${e(t.name)}</option>`).join(``)}catch(e){r(t(e),`error`);return}let i=new URLSearchParams(window.location.search);await I(),i.get(`new`)===`1`&&R(void 0,{leadId:Number(i.get(`lead`))||void 0,contactId:Number(i.get(`contact`))||void 0,name:i.get(`name`)??void 0,budget:Number(i.get(`budget`))||void 0});let a=Number(i.get(`project`));a>0&&await B(a)}j.addEventListener(`input`,o(()=>{T=j.value.trim(),x=1,I()},350)),M.addEventListener(`change`,()=>{E=M.value,x=1,I()}),N.addEventListener(`change`,()=>{D=N.value,x=1,I()}),document.getElementById(`projects-prev`)?.addEventListener(`click`,()=>{x>1&&(--x,I())}),document.getElementById(`projects-next`)?.addEventListener(`click`,()=>{x<S&&(x+=1,I())}),document.querySelectorAll(`[data-new-project]`).forEach(e=>{e.addEventListener(`click`,()=>R())}),V();