import{a as e,d as t,i as n,l as r,n as i,o as a,s as o,u as s}from"./ui.CJdB5Grl.js";import{h as c,u as l}from"./format.BIwoWcf9.js";var u=document.getElementById(`mail-form`),d=null,f=[];function p(e){return document.getElementById(e)}function m(t){let r=document.getElementById(`mail-logs`);if(t.length===0){r.innerHTML=n(`Sin envíos todavía`,`Los correos de notificación y las pruebas aparecerán aquí.`);return}let i={enviado:`bg-mint/60 text-[#1C6B45]`,fallido:`bg-red-50 text-red-600`,omitido:`bg-cream text-muted`};r.innerHTML=`
        <div class="space-y-1">
          ${t.map(t=>`
            <div class="rounded-xl px-2.5 py-2.5 transition hover:bg-cream/70">
              <div class="flex items-start justify-between gap-2">
                <p class="min-w-0 flex-1 truncate text-[12px] font-semibold">${e(t.subject)}</p>
                <span class="badge shrink-0 ${i[t.status]??`bg-cream text-muted`}">${e(t.status)}</span>
              </div>
              <p class="mt-1 truncate text-[11px] text-muted">${e(t.recipient)} · ${e(l(t.created_at))}</p>
              ${t.error?`<p class="mt-1 line-clamp-2 text-[10px] leading-snug text-red-500">${e(t.error)}</p>`:``}
            </div>`).join(``)}
        </div>`}function h(e){d=e;let t=e.mail;p(`app-name`).value=t.app_name,p(`from-name`).value=t.from_name,p(`mail-host`).value=t.host,p(`mail-port`).value=String(t.port),p(`mail-username`).value=t.username,p(`mail-password`).value=``,p(`mail-password`).placeholder=t.has_password?`•••••••• (guardada)`:`Contraseña o API key`,p(`from-email`).value=t.from_email,p(`mail-encryption`).value=t.encryption,p(`notifications-enabled`).checked=t.notifications_enabled,p(`clear-password`).checked=!1,p(`clear-password-wrap`).classList.toggle(`hidden`,!t.has_password),p(`password-hint`).textContent=t.has_password?`Hay una contraseña guardada. Déjala vacía para conservarla.`:`Aún no hay contraseña guardada.`;let n=document.getElementById(`mail-state`),r=!(!t.host||!t.from_email||!t.has_password&&t.username);n.textContent=r?`Configurado`:`Pendiente`,n.className=`badge ${r?`bg-mint/60 text-[#1C6B45]`:`bg-sand text-[#8A5A00]`}`;let i=p(`test-email`);i.value||c().then(e=>{e&&(i.value=e.user.email)}),m(e.logs)}async function g(){try{h((await s(`/settings/mail`)).data)}catch(e){r(t(e),`error`)}}function _(){let t=document.getElementById(`statuses-list`);f.length===0?t.innerHTML=n(`Sin estados`,`Crea al menos un estado para clasificar los leads.`):(t.innerHTML=`
        <div class="space-y-1">
          ${f.map(t=>`
            <div class="flex flex-wrap items-center gap-3 rounded-2xl px-3 py-2.5 transition hover:bg-cream/60">
              <span class="h-3 w-3 shrink-0 rounded-full" style="background:${t.color}"></span>
              <span class="min-w-0 flex-1 text-[13px] font-semibold">${e(t.name)}</span>
              ${Number(t.is_won)===1?`<span class="badge bg-mint/60 text-[#1C6B45]">Ganado</span>`:Number(t.is_lost)===1?`<span class="badge bg-cream text-muted">Perdido</span>`:`<span class="badge bg-sky/50 text-[#1E4C82]">Abierto</span>`}
              <span class="w-20 text-right text-[11px] text-muted">${t.leads_count} ${Number(t.leads_count)===1?`lead`:`leads`}</span>
              <div class="flex gap-1">
                <button type="button" data-status-edit="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar estado"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                <button type="button" data-status-delete="${t.id}" class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600" aria-label="Eliminar estado"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
              </div>
            </div>`).join(``)}
        </div>`,t.querySelectorAll(`[data-status-edit]`).forEach(e=>{let t=f.find(t=>t.id===Number(e.dataset.statusEdit));t&&e.addEventListener(`click`,()=>y(t))}),t.querySelectorAll(`[data-status-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void b(Number(e.dataset.statusDelete)))}))}async function v(){try{f=(await s(`/lead-statuses`)).data.sort((e,t)=>Number(e.position)-Number(t.position)||e.id-t.id),_()}catch(e){r(t(e),`error`)}}function y(n){let i=!!n,c=Number(n?.is_won)===1?`won`:Number(n?.is_lost)===1?`lost`:`open`;a({title:i?`Editar estado`:`Nuevo estado`,subtitle:`Nombre, color y comportamiento dentro del embudo.`,width:`min(94vw, 500px)`,body:`
          <form id="status-form" class="space-y-4">
            <div>
              <label class="field-label" for="status-name">Nombre *</label>
              <input id="status-name" name="name" class="input" maxlength="60" value="${e(n?.name??``)}" placeholder="Ej. Negociación" required autofocus />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label" for="status-color">Color</label>
                <input id="status-color" name="color" type="color" value="${/^#[0-9a-fA-F]{6}$/.test(n?.color??``)?n.color:`#FF5A36`}" class="h-10 w-full cursor-pointer rounded-xl border border-line bg-white p-1" />
              </div>
              <div>
                <label class="field-label" for="status-position">Orden</label>
                <input id="status-position" name="position" type="number" min="0" class="input" value="${Number(n?.position??f.length*10)}" />
              </div>
            </div>
            <div>
              <label class="field-label" for="status-result">Resultado</label>
              <select id="status-result" name="result" class="select">
                <option value="open" ${c===`open`?`selected`:``}>Abierto (en negocio)</option>
                <option value="won" ${c===`won`?`selected`:``}>Ganado (cierra positivamente)</option>
                <option value="lost" ${c===`lost`?`selected`:``}>Perdido (cierra negativamente)</option>
              </select>
              <p class="mt-1.5 text-[11px] text-muted">Solo puede haber un estado marcado como ganado y uno como perdido.</p>
            </div>
          </form>
        `,footer:`
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="status-form" class="btn btn-primary" data-submit>${i?`Guardar cambios`:`Crear estado`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let a=e.querySelector(`#status-form`);a?.addEventListener(`submit`,async c=>{c.preventDefault();let l=e.querySelector(`[data-submit]`);if(!l||!a)return;let u=new FormData(a),d=String(u.get(`result`)),f={name:u.get(`name`),color:u.get(`color`),position:u.get(`position`),is_won:+(d===`won`),is_lost:+(d===`lost`)};o(l,!0);try{i&&n?(await s(`/lead-statuses/${n.id}`,{method:`PATCH`,body:f}),r(`Estado actualizado.`)):(await s(`/lead-statuses`,{method:`POST`,body:f}),r(`Estado creado.`)),e.close(),await v()}catch(e){r(t(e),`error`),o(l,!1)}})}})}async function b(e){let n=f.find(t=>t.id===e);if(await i({title:`Eliminar estado`,message:`Se eliminará el estado «${n?.name??``}». Solo es posible si no tiene leads asignados.`,confirmLabel:`Eliminar estado`}))try{await s(`/lead-statuses/${e}`,{method:`DELETE`}),r(`Estado eliminado.`),await v()}catch(e){r(t(e),`error`)}}u.addEventListener(`submit`,async e=>{e.preventDefault();let n=u.querySelector(`[data-save]`);if(!n)return;let i=new FormData(u);o(n,!0);try{h((await s(`/settings/mail`,{method:`PATCH`,body:{app_name:i.get(`app_name`),mail_from_name:i.get(`mail_from_name`),mail_host:i.get(`mail_host`),mail_port:i.get(`mail_port`),mail_username:i.get(`mail_username`),mail_password:i.get(`mail_password`),mail_from_email:i.get(`mail_from_email`),mail_encryption:i.get(`mail_encryption`),notifications_enabled:i.get(`notifications_enabled`)===`on`,clear_password:i.get(`clear_password`)===`on`}})).data),r(`Configuración de correo guardada.`)}catch(e){r(t(e),`error`)}finally{o(n,!1)}}),u.addEventListener(`reset`,()=>{d&&window.setTimeout(()=>h(d),0)}),document.getElementById(`test-form`)?.addEventListener(`submit`,async e=>{e.preventDefault();let n=e.currentTarget,i=n.querySelector(`[data-test]`);if(!i)return;let a=new FormData(n);o(i,!0,`Enviando…`);try{let e=await s(`/settings/mail/test`,{method:`POST`,body:{to:a.get(`to`)}});r(e.data.message),await g()}catch(e){r(t(e),`error`),await g()}finally{o(i,!1)}}),document.getElementById(`refresh-logs`)?.addEventListener(`click`,()=>void g()),document.getElementById(`new-status`)?.addEventListener(`click`,()=>y());async function x(){let e=await c();e&&(e.user.role===`admin`?(await g(),await v()):window.location.replace(`/app/`))}x();