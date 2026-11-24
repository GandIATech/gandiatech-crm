import{a as e,c as t,d as n,i as r,l as i,n as a,o,r as s,s as c,t as l,u}from"./ui.CJdB5Grl.js";import{d,h as f,i as p,t as m,u as h}from"./format.BIwoWcf9.js";import{t as g}from"./task-form.DUqiWS-b.js";var _,v,y=new Map,b=1,x=1,S=0,C=0,w=``,T=``,E=``,D=document.getElementById(`leads-table`),O=document.getElementById(`leads-summary`),k=document.getElementById(`leads-value`),A=document.getElementById(`leads-pagination`),j=document.getElementById(`lead-search`),M=document.getElementById(`filter-status`),N=document.getElementById(`filter-owner`);function P(){M.innerHTML=`<option value="">Todos los estados</option>`+v.statuses.map(t=>`<option value="${t.id}">${e(t.name)}</option>`).join(``),N.innerHTML=`<option value="">Todos los responsables</option>`+v.users.map(t=>`<option value="${t.id}">${e(t.name)}</option>`).join(``),M.value=T,N.value=E}function F(t){return t.owner_name?`<span class="flex items-center gap-2">${l(t.owner_name,t.owner_color,28)}<span class="max-w-[110px] truncate text-[12px]">${e(t.owner_name)}</span></span>`:`<span class="text-[12px] text-muted">Sin asignar</span>`}function I(t){let n=(v.statuses.find(e=>e.id===t.status_id)??v.statuses[0])?.color??`#5E6472`,r=v.statuses.map(n=>`<option value="${n.id}" ${n.id===t.status_id?`selected`:``}>${e(n.name)}</option>`).join(``);return`<select data-status-lead="${t.id}" class="max-w-[132px] cursor-pointer appearance-none rounded-full border-0 py-1.5 pl-3 pr-7 text-[11px] font-semibold focus:ring-2 focus:ring-accent/20" style="background:${n}1A;color:${n};background-image:url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='10' height='10' viewBox='0 0 24 24' fill='none' stroke='${encodeURIComponent(n)}' stroke-width='2.5' stroke-linecap='round'%3E%3Cpolyline points='6 9 12 15 18 9'/%3E%3C/svg%3E&quot;);background-repeat:no-repeat;background-position:right 8px center">${r}</select>`}function L(){O.textContent=`${S} ${S===1?`lead`:`leads`}`,k.textContent=`Valor filtrado: ${p(C)}`,S===0?D.innerHTML=r(w||T||E?`Sin resultados`:`Todavía no hay leads`,w||T||E?`Prueba con otros filtros o crea un lead nuevo.`:`Registra tu primera oportunidad y empieza a mover el pipeline.`,`<button type="button" class="btn btn-primary btn-sm" data-empty-new>Nuevo lead</button>`):(D.innerHTML=`
          <table class="data-table">
            <thead>
              <tr>
                <th>Lead</th>
                <th>Contacto</th>
                <th>Estado</th>
                <th>Valor</th>
                <th>Responsable</th>
                <th>Tareas</th>
                <th>Actualizado</th>
                <th class="text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              ${Array.from(y.values()).map(t=>`
                  <tr>
                    <td class="max-w-[280px]">
                      <button type="button" data-detail="${t.id}" class="truncate text-left font-semibold hover:text-accent-dark">${e(t.title)}</button>
                      <p class="mt-0.5 truncate text-[11px] text-muted">${e(t.company||t.contact_company||`Sin empresa`)}</p>
                    </td>
                    <td class="max-w-[210px]">
                      <p class="truncate text-[12px] font-medium">${e(t.contact_name||`—`)}</p>
                      <p class="mt-0.5 truncate text-[11px] text-muted">${e(t.contact_email||t.contact_phone||``)}</p>
                    </td>
                    <td>${I(t)}</td>
                    <td class="whitespace-nowrap font-semibold tabular-nums">${p(t.value)}</td>
                    <td>${F(t)}</td>
                    <td>${t.open_tasks>0?`<span class="badge bg-sand text-[#8A5A00]">${t.open_tasks} abierta${t.open_tasks===1?``:`s`}</span>`:`<span class="text-[11px] text-muted">—</span>`}</td>
                    <td class="whitespace-nowrap text-[11px] text-muted">${e(h(t.updated_at))}</td>
                    <td>
                      <div class="flex justify-end gap-1">
                        <button type="button" data-edit="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar lead"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                        <button type="button" data-delete="${t.id}" class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600" aria-label="Eliminar lead"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                      </div>
                    </td>
                  </tr>`).join(``)}
            </tbody>
          </table>`,D.querySelectorAll(`[data-detail]`).forEach(e=>{e.addEventListener(`click`,()=>void H(Number(e.dataset.detail)))}),D.querySelectorAll(`[data-edit]`).forEach(e=>{e.addEventListener(`click`,()=>{let t=y.get(Number(e.dataset.edit));t&&B(t)})}),D.querySelectorAll(`[data-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void V(Number(e.dataset.delete)))}),D.querySelectorAll(`[data-status-lead]`).forEach(e=>{e.addEventListener(`change`,async()=>{let t=Number(e.dataset.statusLead);try{await u(`/leads/${t}`,{method:`PATCH`,body:{status_id:Number(e.value)}}),i(`Estado del lead actualizado.`),await R()}catch(e){i(n(e),`error`),await R()}})})),D.querySelectorAll(`[data-empty-new]`).forEach(e=>{e.addEventListener(`click`,()=>B())}),A.classList.toggle(`hidden`,S===0),A.classList.toggle(`flex`,S>0),document.getElementById(`pagination-caption`).textContent=`Mostrando ${(b-1)*20+1}–${Math.min(b*20,S)} de ${S}`,document.getElementById(`page-indicator`).textContent=`${b} / ${x}`,document.getElementById(`page-prev`).disabled=b<=1,document.getElementById(`page-next`).disabled=b>=x}async function R(){try{let e=await u(`/leads`,{params:{q:w,status_id:T,owner_id:E,page:b,per_page:20}});y=new Map(e.data.map(e=>[e.id,e])),S=Number(e.meta.total??0),x=Number(e.meta.pages??1),C=Number(e.meta.total_value??0),L()}catch(t){D.innerHTML=`<div class="px-5 py-10 text-center text-sm text-muted">${e(n(t))}</div>`}}function z(t){return`<option value="">Sin contacto</option>`+v.contacts.map(n=>`<option value="${n.id}" ${n.id===t?`selected`:``}>${e(n.name)}${n.company?` · `+e(n.company):``}</option>`).join(``)}function B(t){let r=!!t,a=v.statuses.map(n=>`<option value="${n.id}" ${(t?.status_id??v.statuses[0]?.id)===n.id?`selected`:``}>${e(n.name)}</option>`).join(``),s=v.users.map(n=>`<option value="${n.id}" ${(t?.owner_id??_.user.id)===n.id?`selected`:``}>${e(n.name)}</option>`).join(``),l=Array.from(new Set([...d,t?.source].filter(Boolean))).map(n=>`<option value="${e(n)}" ${t?.source===n?`selected`:``}>${e(n)}</option>`).join(``);o({title:r?`Editar lead`:`Nuevo lead`,subtitle:r?`Actualiza la oportunidad y su siguiente paso.`:`Registra una nueva oportunidad comercial.`,width:`min(94vw, 760px)`,body:`
          <form id="lead-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="field-label" for="lead-title">Título del lead *</label>
                <input id="lead-title" name="title" class="input" maxlength="160" value="${e(t?.title??``)}" placeholder="Ej. Auditoría de costes logísticos" required autofocus />
              </div>
              <div>
                <label class="field-label" for="lead-contact">Contacto</label>
                <select id="lead-contact" name="contact_id" class="select">${z(t?.contact_id??null)}</select>
              </div>
              <div>
                <label class="field-label" for="lead-company">Empresa</label>
                <input id="lead-company" name="company" class="input" maxlength="160" value="${e(t?.company??``)}" placeholder="Nombre de la empresa" />
              </div>
            </div>

            <details class="rounded-2xl border border-line bg-cream/50 px-4 py-3" >
              <summary class="cursor-pointer text-[12px] font-semibold text-muted">Crear un contacto nuevo</summary>
              <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <input name="new_contact_name" class="input" placeholder="Nombre y apellidos" maxlength="120" />
                <input name="new_contact_email" type="email" class="input" placeholder="correo@empresa.com" />
                <input name="new_contact_phone" class="input" placeholder="Teléfono" maxlength="40" />
                <input name="new_contact_company" class="input" placeholder="Empresa" maxlength="160" />
              </div>
              <p class="mt-2 text-[11px] text-muted">Si rellenas el nombre, se creará el contacto y se asignará a este lead.</p>
            </details>

            <div class="grid gap-4 sm:grid-cols-3">
              <div>
                <label class="field-label" for="lead-value">Valor (€)</label>
                <input id="lead-value" name="value" type="number" min="0" step="0.01" class="input" value="${Number(t?.value??0)}" />
              </div>
              <div>
                <label class="field-label" for="lead-status">Estado</label>
                <select id="lead-status" name="status_id" class="select">${a}</select>
              </div>
              <div>
                <label class="field-label" for="lead-owner">Responsable</label>
                <select id="lead-owner" name="owner_id" class="select">${s}</select>
              </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label" for="lead-source">Origen</label>
                <select id="lead-source" name="source" class="select"><option value="">Sin especificar</option>${l}</select>
              </div>
              <div>
                <label class="field-label" for="lead-close">Cierre previsto</label>
                <input id="lead-close" name="expected_close_date" type="date" class="input" value="${e(t?.expected_close_date??``)}" />
              </div>
            </div>

            <div>
              <label class="field-label" for="lead-notes">Notas</label>
              <textarea id="lead-notes" name="notes" class="textarea" rows="3" placeholder="Contexto, necesidades, próximos pasos…">${e(t?.notes??``)}</textarea>
            </div>
          </form>
        `,footer:`
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="lead-form" class="btn btn-primary" data-submit>${r?`Guardar cambios`:`Crear lead`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let a=e.querySelector(`#lead-form`);a?.addEventListener(`submit`,async o=>{o.preventDefault();let s=e.querySelector(`[data-submit]`);if(!s||!a)return;let l=new FormData(a);c(s,!0);try{let n=l.get(`contact_id`)?Number(l.get(`contact_id`)):null,a=String(l.get(`new_contact_name`)||``).trim();a&&(n=(await u(`/contacts`,{method:`POST`,body:{name:a,email:l.get(`new_contact_email`),phone:l.get(`new_contact_phone`),company:l.get(`new_contact_company`),owner_id:l.get(`owner_id`)}})).data.id,v=(await u(`/meta`)).data);let o={title:l.get(`title`),contact_id:n,company:l.get(`company`),value:l.get(`value`),status_id:l.get(`status_id`),source:l.get(`source`),owner_id:l.get(`owner_id`),expected_close_date:l.get(`expected_close_date`),notes:l.get(`notes`)};r&&t?(await u(`/leads/${t.id}`,{method:`PATCH`,body:o}),i(`Lead actualizado correctamente.`)):(await u(`/leads`,{method:`POST`,body:o}),i(`Lead creado correctamente.`)),e.close(),await R()}catch(e){i(n(e),`error`),c(s,!1)}})}})}async function V(e){let t=y.get(e);if(await a({title:`Eliminar lead`,message:`Se moverá «${t?.title??`este lead`}» a la papelera. Podrás restaurarlo desde Administración.`,confirmLabel:`Eliminar lead`}))try{await u(`/leads/${e}`,{method:`DELETE`}),i(`Lead movido a la papelera.`),await R()}catch(e){i(n(e),`error`)}}async function H(r){try{let{lead:n,tasks:i,projects:a,activities:s}=(await u(`/leads/${r}`)).data,c=i.filter(e=>e.status!==`completada`);o({title:n.title,subtitle:`${n.contact_name||`Sin contacto`}${n.contact_company?` · `+n.contact_company:``}`,width:`min(94vw, 720px)`,body:`
            <div class="flex flex-wrap items-center gap-2">
              ${t(n.status_name||`Sin estado`,n.status_color)}
              <span class="badge bg-cream text-muted">${e(n.source||`Sin origen`)}</span>
              <span class="ml-auto font-display text-2xl font-bold">${p(n.value)}</span>
            </div>

            <div class="mt-5 grid gap-3 sm:grid-cols-2">
              <div class="rounded-2xl border border-line bg-cream/50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Contacto</p>
                <p class="mt-2 text-sm font-semibold">${e(n.contact_name||`Sin contacto asignado`)}</p>
                ${n.contact_position?`<p class="text-[12px] text-muted">${e(n.contact_position)}</p>`:``}
                <div class="mt-3 space-y-1.5 text-[12px] text-muted">
                  ${n.contact_email?`<p>${e(n.contact_email)}</p>`:``}
                  ${n.contact_phone?`<p>${e(n.contact_phone)}</p>`:``}
                </div>
              </div>
              <div class="rounded-2xl border border-line bg-cream/50 p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Seguimiento</p>
                <p class="mt-2 text-sm font-semibold">${e(n.owner_name||`Sin responsable`)}</p>
                <p class="text-[12px] text-muted">Cierre previsto: ${e(m(n.expected_close_date))}</p>
                <p class="mt-2 text-[11px] text-muted">Creado ${e(h(n.created_at))}</p>
              </div>
            </div>

            ${n.notes?`<div class="mt-4 rounded-2xl border border-line p-4"><p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Notas</p><p class="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-ink/80">${e(n.notes)}</p></div>`:``}

            <div class="mt-6 flex items-center justify-between">
              <h3 class="font-display text-base font-bold">Tareas (${c.length} abiertas)</h3>
              <button type="button" class="text-[11px] font-semibold text-accent-dark hover:underline" data-add-task>Añadir tarea</button>
            </div>
            <div class="mt-2 space-y-1">
              ${i.length===0?`<p class="rounded-2xl border border-dashed border-line px-4 py-4 text-[12px] text-muted">Sin tareas vinculadas a este lead.</p>`:i.map(t=>`
                        <div class="flex items-center gap-3 rounded-xl px-1 py-2">
                          <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full border ${t.status===`completada`?`border-emerald-500 bg-emerald-500 text-white`:`border-line`}">
                            ${t.status===`completada`?`<svg class="h-2.5 w-2.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6 9 17l-5-5"/></svg>`:``}
                          </span>
                          <span class="min-w-0 flex-1 text-[12.5px] ${t.status===`completada`?`text-muted line-through`:``}">${e(t.title)}</span>
                          <span class="shrink-0 text-[10px] uppercase tracking-wide text-muted">${e(t.due_date?m(t.due_date):`Sin fecha`)}</span>
                        </div>`).join(``)}
            </div>

            ${a.length>0?`<div class="mt-6"><h3 class="font-display text-base font-bold">Proyectos vinculados</h3><div class="mt-2 space-y-1">${a.map(t=>`<a href="/app/projects/?project=${t.id}" class="flex items-center justify-between rounded-xl border border-line px-3 py-2.5 text-[12px] transition hover:bg-cream"><span class="font-semibold">${e(t.name)}</span><span class="text-muted">${p(t.budget)}</span></a>`).join(``)}</div></div>`:``}

            <div class="mt-6">
              <h3 class="font-display text-base font-bold">Actividad</h3>
              <div class="mt-3 space-y-3">
                ${s.length===0?`<p class="text-[12px] text-muted">Sin movimientos registrados.</p>`:s.map(t=>`<div class="flex gap-2.5">${l(t.user_name||`Sistema`,t.avatar_color,26)}<div><p class="text-[12px]"><span class="font-semibold">${e(t.user_name||`Sistema`)}</span> <span class="text-muted">${e(t.description)}</span></p><p class="mt-0.5 text-[10px] text-muted/70">${e(h(t.created_at))}</p></div></div>`).join(``)}
              </div>
            </div>
          `,footer:`
            <button type="button" class="btn btn-danger mr-auto" data-delete-detail>Eliminar</button>
            <a href="/app/projects/?new=1&lead=${n.id}&contact=${n.contact_id??``}&name=${encodeURIComponent(`Proyecto · `+n.title)}&budget=${n.value}" class="btn btn-secondary">Convertir en proyecto</a>
            <button type="button" class="btn btn-primary" data-edit-detail>Editar lead</button>
          `,onMount:e=>{e.querySelector(`[data-edit-detail]`)?.addEventListener(`click`,()=>{e.close(),B(n)}),e.querySelector(`[data-add-task]`)?.addEventListener(`click`,()=>{g({leadId:n.id,onSaved:()=>void R()})}),e.querySelector(`[data-delete-detail]`)?.addEventListener(`click`,async()=>{e.close(),await V(n.id)})}})}catch(e){i(n(e),`error`)}}async function U(){let e=await f();if(!e)return;_=e;try{v=(await u(`/meta`)).data,P()}catch(e){i(n(e),`error`);return}let t=new URLSearchParams(window.location.search);w=t.get(`q`)??``,j.value=w,await R();let r=Number(t.get(`lead`));r>0&&await H(r)}j.addEventListener(`input`,s(()=>{w=j.value.trim(),b=1,R()},350)),M.addEventListener(`change`,()=>{T=M.value,b=1,R()}),N.addEventListener(`change`,()=>{E=N.value,b=1,R()}),document.getElementById(`clear-filters`)?.addEventListener(`click`,()=>{j.value=``,M.value=``,N.value=``,w=``,T=``,E=``,b=1,R()}),document.getElementById(`page-prev`)?.addEventListener(`click`,()=>{b>1&&(--b,R())}),document.getElementById(`page-next`)?.addEventListener(`click`,()=>{b<x&&(b+=1,R())}),document.querySelectorAll(`[data-new-lead]`).forEach(e=>{e.addEventListener(`click`,()=>B())}),U();