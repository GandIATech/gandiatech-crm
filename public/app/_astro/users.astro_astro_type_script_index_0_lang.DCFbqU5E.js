import{a as e,d as t,i as n,l as r,n as i,o as a,r as o,s,t as c,u as l}from"./ui.CJdB5Grl.js";import{h as u,t as d,u as f}from"./format.BIwoWcf9.js";var p,m=[],h=``,g=document.getElementById(`users-table`),_=document.getElementById(`users-summary`),v=document.getElementById(`user-search`);function y(){if(!h)return m;let e=h.toLowerCase();return m.filter(t=>t.name.toLowerCase().includes(e)||t.email.toLowerCase().includes(e))}function b(){let t=y();_.textContent=`${t.length} ${t.length===1?`usuario`:`usuarios`}`;let r=m.filter(e=>e.role===`admin`&&Number(e.is_active)===1).length;document.getElementById(`users-roles`).textContent=`${r} administrador${r===1?``:`es`}`,t.length===0?g.innerHTML=n(`Sin usuarios`,h?`No hay coincidencias con esa búsqueda.`:`Crea el primer usuario del equipo.`):(g.innerHTML=`
        <table class="data-table">
          <thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Último acceso</th><th>Alta</th><th class="text-right">Acciones</th></tr></thead>
          <tbody>
            ${t.map(t=>`
                <tr class="${Number(t.is_active)===0?`opacity-60`:``}">
                  <td>
                    <div class="flex items-center gap-3">
                      ${c(t.name,t.avatar_color,36)}
                      <div class="min-w-0">
                        <p class="truncate text-[13px] font-semibold">${e(t.name)}${t.id===p.user.id?`<span class="ml-1.5 text-[10px] font-medium text-muted">(tú)</span>`:``}</p>
                        <p class="truncate text-[11px] text-muted">${e(t.email)}</p>
                      </div>
                    </div>
                  </td>
                  <td><span class="badge ${t.role===`admin`?`bg-accent-soft text-accent-dark`:(Number(t.is_director)===1?`bg-sand/70 text-[#8A5A00]`:`bg-sky/50 text-[#1E4C82]`)}">${t.role===`admin`?`Administrador`:(Number(t.is_director)===1?`Director`:`Estándar`)}</span></td>
                  <td><span class="badge ${Number(t.is_active)===1?`bg-mint/60 text-[#1C6B45]`:`bg-line/60 text-muted`}">${Number(t.is_active)===1?`Activo`:`Inactivo`}</span></td>
                  <td class="text-[11px] text-muted">${t.last_login_at?e(f(t.last_login_at)):`Nunca`}</td>
                  <td class="whitespace-nowrap text-[11px] text-muted">${e(d(t.created_at))}</td>
                  <td>
                    <div class="flex justify-end gap-1">
                      <button type="button" data-edit="${t.id}" class="icon-btn h-8 w-8" aria-label="Editar usuario"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21.17 6.83a2.83 2.83 0 0 0-4-4L3 16.83V21h4.17Z"/><path d="m15 5 4 4"/></svg></button>
                      <button type="button" data-delete="${t.id}" ${t.id===p.user.id?`disabled title="No puedes eliminar tu cuenta"`:``} class="icon-btn h-8 w-8 hover:border-red-200 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30" aria-label="Eliminar usuario"><svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6"/><path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/></svg></button>
                    </div>
                  </td>
                </tr>`).join(``)}
          </tbody>
        </table>`,g.querySelectorAll(`[data-edit]`).forEach(e=>{let t=m.find(t=>t.id===Number(e.dataset.edit));t&&e.addEventListener(`click`,()=>S(t))}),g.querySelectorAll(`[data-delete]`).forEach(e=>{e.addEventListener(`click`,()=>void C(Number(e.dataset.delete)))}))}async function x(){try{m=(await l(`/users`,{params:{all:1}})).data,b()}catch(n){g.innerHTML=`<div class="px-5 py-10 text-center text-sm text-muted">${e(t(n))}</div>`}}function S(n){let i=!!n;a({title:i?`Editar usuario`:`Nuevo usuario`,subtitle:i?`Actualiza el perfil, el rol o la contraseña.`:`Crea una cuenta de acceso al CRM.`,width:`min(94vw, 620px)`,body:`
          <form id="user-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
              <div>
                <label class="field-label" for="user-name">Nombre *</label>
                <input id="user-name" name="name" class="input" maxlength="120" value="${e(n?.name??``)}" placeholder="Nombre y apellidos" required autofocus />
              </div>
              <div>
                <label class="field-label" for="user-email">Correo *</label>
                <input id="user-email" name="email" type="email" class="input" value="${e(n?.email??``)}" placeholder="nombre@gandia.tech" required />
              </div>
              <div>
                <label class="field-label" for="user-role">Rol</label>
                <select id="user-role" name="role" class="select" ${n?.id===p.user.id?`disabled`:``}>
                  <option value="user" ${!n||(n.role!==`admin`&&Number(n.is_director)!==1)?`selected`:``}>Estándar</option>
                  <option value="director" ${n&&n.role!==`admin`&&Number(n.is_director)===1?`selected`:``}>Director</option>
                  <option value="admin" ${n?.role===`admin`?`selected`:``}>Administrador</option>
                </select>
                ${n?.id===p.user.id?`<input type="hidden" name="role" value="admin" />`:``}
              </div>
              <div>
                <label class="field-label" for="user-active">Estado</label>
                <select id="user-active" name="is_active" class="select" ${n?.id===p.user.id?`disabled`:``}>
                  <option value="1" ${!n||Number(n.is_active)===1?`selected`:``}>Activo</option>
                  <option value="0" ${n&&Number(n.is_active)===0?`selected`:``}>Inactivo</option>
                </select>
                ${n?.id===p.user.id?`<input type="hidden" name="is_active" value="1" />`:``}
              </div>
              <div class="sm:col-span-2">
                <label class="field-label" for="user-password">${i?`Nueva contraseña`:`Contraseña *`}</label>
                <input id="user-password" name="password" type="password" class="input" minlength="12" maxlength="72" autocomplete="new-password" placeholder="${i?`Déjala vacía para no cambiarla`:`Mínimo 12 caracteres`}" ${i?``:`required`} />
              </div>
              <div class="sm:col-span-2">
                <label class="field-label" for="user-color">Color del avatar</label>
                <input id="user-color" name="avatar_color" type="color" value="${e(n?.avatar_color??`#FF5A36`)}" class="h-10 w-20 cursor-pointer rounded-xl border border-line bg-white p-1" />
              </div>
            </div>
          </form>
        `,footer:`
          <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
          <button type="submit" form="user-form" class="btn btn-primary" data-submit>${i?`Guardar cambios`:`Crear usuario`}</button>
        `,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close());let a=e.querySelector(`#user-form`);a?.addEventListener(`submit`,async o=>{o.preventDefault();let c=e.querySelector(`[data-submit]`);if(!c||!a)return;let u=new FormData(a),d={name:u.get(`name`),email:u.get(`email`),role:u.get(`role`),is_active:Number(u.get(`is_active`)??1),avatar_color:u.get(`avatar_color`)},f=String(u.get(`password`)||``);f&&(d.password=f),s(c,!0);try{i&&n?(await l(`/users/${n.id}`,{method:`PATCH`,body:d}),r(`Usuario actualizado.`)):(await l(`/users`,{method:`POST`,body:d}),r(`Usuario creado.`)),e.close(),await x()}catch(e){r(t(e),`error`),s(c,!1)}})}})}async function C(e){let n=m.find(t=>t.id===e);if(await i({title:`Eliminar usuario`,message:`Se eliminará la cuenta de «${n?.name??`este usuario`}». Sus registros se mantendrán, pero perderá el acceso.`,confirmLabel:`Eliminar usuario`}))try{await l(`/users/${e}`,{method:`DELETE`}),r(`Usuario eliminado.`),await x()}catch(e){r(t(e),`error`)}}async function w(){let e=await u();e&&(p=e,p.user.role===`admin`?await x():window.location.replace(`/app/`))}v.addEventListener(`input`,o(()=>{h=v.value.trim(),b()},200)),document.getElementById(`new-user`)?.addEventListener(`click`,()=>S()),w();