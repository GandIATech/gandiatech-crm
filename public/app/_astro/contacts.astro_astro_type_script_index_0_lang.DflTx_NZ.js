import{a as e,c as t,d as n,i as r,l as i,n as a,o,r as s,s as c,t as l,u}from"./ui.CJdB5Grl.js";import{h as d,i as f,u as p}from"./format.BIwoWcf9.js";var m,h,g=new Map,_=1,v=1,y=0,b=``,x=``,S=document.getElementById(`contacts-table`),C=document.getElementById(`contacts-summary`),w=document.getElementById(`contact-search`),T=document.getElementById(`contact-owner`),E=document.getElementById(`contacts-pagination`);function D(){C.textContent=`${y} ${y===1?`contacto`:`contactos`}`,document.getElementById(`contacts-leads`).textContent=`${Array.from(g.values()).reduce((e,t)=>e+Number(t.leads_count),0)} leads vinculados`,y===0?S.innerHTML=r(b||x?`Sin resultados`:`Directorio vacío`,b||x?`Prueba con otra búsqueda o responsable.`:`Añade tu primer contacto para vincularlo a leads y proyectos.`,`<button type="button" class="btn btn-primary btn-sm" data-empty-new>Nuevo contacto</button>`):(S.innerHTML=`
          <table class="data-table">
            <thead><tr><th>Contacto</th><th>Empresa</th><th>Datos</th><th>Leads</th><th>Responsable</th><th>Actualizado</th><th class="text-right">Acciones</th></tr></thead>
            <tbody>
              ${Array.from(g.values()).map(t=>`
                  <tr>
                    <td>
                      <button type="button" data-detail="${t.id}" class="flex items-center gap-3 text-left">
                        ${l(t.name,t.owner_color,34)}
                        <span class="min-w-0">
                          <span class="block truncate text-[13px] font-semibold hover:text-accent-dark">${e(t.name)}</span>
                          <span class="block truncate text-[11px] text-muted">${e(t.position||`Sin cargo`)}</span>
                        </span>
                      </button>
                    </td>
                    <td class="max-w-[180px]"><p class="truncate text-[12.5px]">${e(t.company||`—`)}</p></td>
                    <td class="max-w-[200px]">
                      <p class="truncate text-[12px]">${e(t.email||`—`)}</p>
                      <p class="mt-0.5 text-[11px] text-muted">${e(t.phone||``)}</p>
                    </td>
                    <td>${Number(t.leads_count)>0?`<span class="badge bg-sky/60 text-[#1E4C82]">${t.leads_count} ${Number(t.leads_count)===1?`lead`:`leads`}</span>`:`<span class="text-[11px] text-muted">—</span>`}</td>
                    <td>${t.owner_name?`<span class="flex items-center gap-2">${l(t.owner_name,t.owner_color,26)}<span class="max-w-[100px] truncate text-[12px]">${e(t.owner_name)}</span></span>`:`<span class="text-[11px] text-muted">Sin asignar</span>`}</td>
                    <td class="whitespace-nowrap text-[11px] text-muted">${e(p(t.updated_at))}</td>
                    <td>
                      <div class="flex justify-end gap-1">
                        <button type="button" data-edit="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar contacto"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                        <button type="button" data-delete="${t.id}" class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600" aria-label="Eliminar contacto"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                      </div>
                    </td>
                  </tr>`).join(``)}
            </tbody>
          </table>`,S.querySelectorAll(`[data-detail]`).forEach(e=>{e.addEventListener(`click`,()=>void M(Number(e.dataset.detail)))}),S.querySelectorAll(`[data-edit]`).forEach(e=>{let t=g.get(Number(e.dataset.edit));t&&e.addEventListener(`click`,()=>A(t))}),S.querySelectorAll(`[data-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void j(Number(e.dataset.delete)))})),S.querySelectorAll(`[data-empty-new]`).forEach(e=>{e.addEventListener(`click`,()=>A())}),E.classList.toggle(`hidden`,y===0),E.classList.toggle(`flex`,y>0),document.getElementById(`contacts-caption`).textContent=`Mostrando ${(_-1)*20+1}–${Math.min(_*20,y)} de ${y}`,document.getElementById(`contacts-indicator`).textContent=`${_} / ${v}`,document.getElementById(`contacts-prev`).disabled=_<=1,document.getElementById(`contacts-next`).disabled=_>=v}async function O(){try{let e=await u(`/contacts`,{params:{q:b,owner_id:x,page:_,per_page:20}});g=new Map(e.data.map(e=>[e.id,e])),y=Number(e.meta.total??0),v=Number(e.meta.pages??1),D()}catch(t){S.innerHTML=`<div class="px-5 py-10 text-center text-sm text-muted">${e(n(t))}</div>`}}function k(t){return h.users.map(n=>`<option value="${n.id}" ${n.id===t?`selected`:``}>${e(n.name)}</option>`).join(``)}function A(t){let r=!!t;o({title:r?`Editar contacto`:`Nuevo contacto`,subtitle:`Datos de la persona y su relación con la empresa.`,width:`min(94vw, 680px)`,body:`
          <form id="contact-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <div class="sm:col-span-2">
                <label class="field-label" for="contact-name">Nombre y apellidos *</label>
                <input id="contact-name" name="name" class="input" maxlength="120" value="${e(t?.name??``)}" placeholder="Ej. Marta Ferrer" required autofocus />
              </div>
              <div>
                <label class="field-label" for="contact-email">Correo electrónico</label>
                <input id="contact-email" name="email" type="email" class="input" value="${e(t?.email??``)}" placeholder="marta@empresa.com" />
              </div>
              <div>
                <label class="field-label" for="contact-phone">Teléfono</label>
                <input id="contact-phone" name="phone" class="input" maxlength="40" value="${e(t?.phone??``)}" placeholder="+34 600 000 000" />
              </div>
              <div>
                <label class="field-label" for="contact-company">Empresa</label>
                <input id="contact-company" name="company" class="input" maxlength="160" value="${e(t?.company??``)}" placeholder="Nombre de la empresa" />
              </div>
              <div>
                <label class="field-label" for="contact-position">Cargo</label>
                <input id="contact-position" name="position" class="input" maxlength="120" value="${e(t?.position??``)}" placeholder="Ej. Directora de operaciones" />
              </div>
              <div class="sm:col-span-2">
                <label class="field-label" for="contact-owner">Responsable</label>
                <select id="contact-owner" name="owner_id" class="select">${k(t?.owner_id??m.user.id)}</select>
              </div>
              <div class="sm:col-span-2">
                <label class="field-label" for="contact-notes">Notas</label>
                <textarea id="contact-notes" name="notes" class="textarea" rows="3" placeholder="Contexto, preferencias, historial…">${e(t?.notes??``)}</textarea>
              </div>
            </div>
          </form>
        `,footer:`
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="contact-form" class="btn btn-primary" data-submit>${r?`Guardar cambios`:`Crear contacto`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let a=e.querySelector(`#contact-form`);a?.addEventListener(`submit`,async o=>{o.preventDefault();let s=e.querySelector(`[data-submit]`);if(!s||!a)return;let l=new FormData(a),d={name:l.get(`name`),email:l.get(`email`),phone:l.get(`phone`),company:l.get(`company`),position:l.get(`position`),notes:l.get(`notes`),owner_id:l.get(`owner_id`)};c(s,!0);try{r&&t?(await u(`/contacts/${t.id}`,{method:`PATCH`,body:d}),i(`Contacto actualizado.`)):(await u(`/contacts`,{method:`POST`,body:d}),i(`Contacto creado.`)),e.close(),await O()}catch(e){i(n(e),`error`),c(s,!1)}})}})}async function j(e){let t=g.get(e);if(await a({title:`Eliminar contacto`,message:`「${t?.name??`Este contacto`}」 se moverá a la papelera. Los leads vinculados se conservarán y podrás restaurarlo desde Administración.`,confirmLabel:`Eliminar contacto`}))try{await u(`/contacts/${e}`,{method:`DELETE`}),i(`Contacto movido a la papelera.`),await O()}catch(e){i(n(e),`error`)}}async function M(r){try{let{contact:n,leads:i}=(await u(`/contacts/${r}`)).data;o({title:n.name,subtitle:[n.position,n.company].filter(Boolean).join(` · `)||`Contacto`,width:`min(94vw, 660px)`,body:`
            <div class="flex items-center gap-4 rounded-2xl border border-line bg-cream/50 p-4">
              ${l(n.name,n.owner_color,52)}
              <div class="min-w-0 flex-1">
                <p class="font-semibold">${e(n.name)}</p>
                <p class="text-[12px] text-muted">${e(n.position||`Sin cargo`)}${n.company?` · `+e(n.company):``}</p>
              </div>
              <span class="badge bg-white text-muted">${n.leads_count} leads</span>
            </div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2">
              <div class="rounded-2xl border border-line p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Contacto</p>
                <p class="mt-2 text-[13px]">${n.email?`<a href="mailto:${e(n.email)}" class="font-medium hover:text-accent-dark">${e(n.email)}</a>`:`<span class="text-muted">Sin correo</span>`}</p>
                <p class="mt-1 text-[13px]">${n.phone?`<a href="tel:${e(n.phone)}" class="hover:text-accent-dark">${e(n.phone)}</a>`:`<span class="text-muted">Sin teléfono</span>`}</p>
              </div>
              <div class="rounded-2xl border border-line p-4">
                <p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Responsable</p>
                <p class="mt-2 text-[13px] font-medium">${e(n.owner_name||`Sin asignar`)}</p>
                <p class="mt-1 text-[11px] text-muted">Actualizado ${e(p(n.updated_at))}</p>
              </div>
            </div>
            ${n.notes?`<div class="mt-4 rounded-2xl border border-line p-4"><p class="text-[10px] font-bold uppercase tracking-[0.1em] text-muted">Notas</p><p class="mt-2 whitespace-pre-line text-[13px] leading-relaxed text-ink/80">${e(n.notes)}</p></div>`:``}
            <div class="mt-6">
              <h3 class="font-display text-base font-bold">Leads vinculados</h3>
              <div class="mt-2 space-y-1">
                ${i.length===0?`<p class="rounded-2xl border border-dashed border-line px-4 py-4 text-[12px] text-muted">Este contacto todavía no tiene leads.</p>`:i.map(n=>`
                  <a href="/app/leads/?lead=${n.id}" class="flex items-center gap-3 rounded-xl border border-line px-3 py-2.5 transition hover:bg-cream">
                    <span class="min-w-0 flex-1 truncate text-[12.5px] font-semibold">${e(n.title)}</span>
                    ${t(n.status_name||`Sin estado`,n.status_color)}
                    <span class="text-[12px] font-semibold tabular-nums">${f(n.value)}</span>
                  </a>`).join(``)}
              </div>
            </div>
          `,footer:`
            <button type="button" class="btn btn-danger mr-auto" data-delete-detail>Eliminar</button>
            <button type="button" class="btn btn-primary" data-edit-detail>Editar contacto</button>
          `,onMount:e=>{e.querySelector(`[data-edit-detail]`)?.addEventListener(`click`,()=>{e.close(),A(n)}),e.querySelector(`[data-delete-detail]`)?.addEventListener(`click`,async()=>{e.close(),await j(n.id)})}})}catch(e){i(n(e),`error`)}}async function N(){let t=await d();if(!t)return;m=t;try{h=(await u(`/meta`)).data,T.innerHTML=`<option value="">Todos los responsables</option>`+h.users.map(t=>`<option value="${t.id}">${e(t.name)}</option>`).join(``)}catch(e){i(n(e),`error`);return}let r=new URLSearchParams(window.location.search);b=r.get(`q`)??``,w.value=b,await O();let a=Number(r.get(`contact`));a>0&&await M(a)}w.addEventListener(`input`,s(()=>{b=w.value.trim(),_=1,O()},350)),T.addEventListener(`change`,()=>{x=T.value,_=1,O()}),document.getElementById(`contacts-prev`)?.addEventListener(`click`,()=>{_>1&&(--_,O())}),document.getElementById(`contacts-next`)?.addEventListener(`click`,()=>{_<v&&(_+=1,O())}),document.querySelectorAll(`[data-new-contact]`).forEach(e=>{e.addEventListener(`click`,()=>A())}),N();