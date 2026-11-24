import{a as e,d as t,l as n,o as r,s as i,u as a}from"./ui.CJdB5Grl.js";import{g as o,m as s,u as c}from"./format.BIwoWcf9.js";import{t as l}from"./task-form.DUqiWS-b.js";var u=null,d=[],f=0;function p(e){return document.querySelector(e)}function m(e){return e.trim().split(/\s+/).slice(0,2).map(e=>e.charAt(0).toUpperCase()).join(``)}function h(e,x){let t=p(`#profile-name`),n=p(`#profile-panel-name`),r=p(`#profile-panel-email`),i=p(`#profile-avatar`);t&&(t.textContent=e.name),n&&(n.textContent=e.name),r&&(r.textContent=e.email),i&&(i.textContent=m(e.name)||`GT`,i.style.background=e.avatar_color||`#FF5A36`),e.role===`admin`&&document.querySelectorAll(`[data-admin-only]`).forEach(e=>e.removeAttribute(`hidden`)),x&&document.querySelectorAll(`[data-app-name]`).forEach(e=>{e.textContent=x}),document.documentElement.classList.remove(`auth-booting`)}function g(e,t){let n=document.getElementById(e),r=document.getElementById(t);n&&r&&(n.addEventListener(`click`,t=>{t.stopPropagation();let i=r.classList.contains(`hidden`);_(),r.classList.toggle(`hidden`,!i),n.setAttribute(`aria-expanded`,String(i)),i&&e===`notifications-button`&&y()}),r.addEventListener(`click`,e=>e.stopPropagation()))}function _(){for(let[e,t]of[[`notifications-button`,`notifications-panel`],[`profile-button`,`profile-panel`]])document.getElementById(t)?.classList.add(`hidden`),document.getElementById(e)?.setAttribute(`aria-expanded`,`false`)}function v(){let t=document.getElementById(`notifications-list`),n=document.getElementById(`notifications-caption`),r=document.getElementById(`notifications-badge`);t&&n&&r&&(r.textContent=f>9?`9+`:String(f),r.classList.toggle(`hidden`,f===0),n.textContent=f>0?`${f} sin leer · ${d.length} recientes`:`Todo al día`,d.length===0?t.innerHTML=`
      <div class="px-5 py-10 text-center">
        <p class="text-sm font-semibold">Sin notificaciones</p>
        <p class="mt-1 text-xs text-muted">Aquí verás los avisos de leads y tareas.</p>
      </div>`:(t.innerHTML=d.map(t=>`
      <a href="${e(t.link||`/app/tasks/`)}" data-notification="${t.id}" class="flex gap-3 border-b border-line/60 px-4 py-3.5 transition last:border-b-0 hover:bg-cream/70 ${t.read_at?``:`bg-accent-soft/20`}">
        <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full ${t.read_at?`bg-line`:`bg-accent`}"></span>
        <span class="min-w-0">
          <span class="block truncate text-[13px] font-semibold">${e(t.title)}</span>
          <span class="mt-0.5 line-clamp-2 block text-[11px] leading-relaxed text-muted">${e(t.message||``)}</span>
          <span class="mt-1 block text-[10px] font-medium uppercase tracking-wide text-muted/70">${e(c(t.created_at))}</span>
        </span>
      </a>`).join(``),t.querySelectorAll(`[data-notification]`).forEach(e=>{e.addEventListener(`click`,()=>{let t=e.dataset.notification;t&&a(`/notifications/${t}/read`,{method:`POST`}).catch(()=>void 0)})})))}async function y(){try{let e=await a(`/notifications`);d=e.data,f=Number(e.meta.unread??0),v()}catch{}}async function b(e){try{let t=await a(`/tasks`,{params:{open:1,assigned_to:e,per_page:1}}),n=Number(t.meta.total??0),r=document.getElementById(`nav-task-badge`);r&&n>0&&(r.textContent=n>99?`99+`:String(n),r.classList.remove(`hidden`))}catch{}}function x(){if(!u)return;let o=u.user;r({title:`Mi perfil`,subtitle:`Actualiza tus datos y tu contraseña de acceso.`,width:`min(94vw, 560px)`,body:`
      <form id="profile-form" class="space-y-4">
        <div class="flex items-center gap-4">
          <span id="profile-modal-avatar" class="flex h-14 w-14 items-center justify-center rounded-full text-lg font-bold text-white" style="background:${e(o.avatar_color)}">${e(m(o.name))}</span>
          <div class="flex-1">
            <label class="field-label" for="profile-color">Color del avatar</label>
            <input id="profile-color" name="avatar_color" type="color" value="${e(o.avatar_color)}" class="h-10 w-20 cursor-pointer rounded-xl border border-line bg-white p-1" />
          </div>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="profile-user-name">Nombre</label>
            <input id="profile-user-name" name="name" class="input" value="${e(o.name)}" maxlength="120" required />
          </div>
          <div>
            <label class="field-label" for="profile-user-email">Correo</label>
            <input id="profile-user-email" name="email" type="email" class="input" value="${e(o.email)}" required />
          </div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn btn-primary btn-sm" data-profile-submit>Guardar cambios</button>
        </div>
      </form>

      <div class="my-6 h-px bg-line"></div>

      <form id="password-form" class="space-y-4">
        <div>
          <h3 class="font-display text-base font-bold">Cambiar contraseña</h3>
          <p class="mt-0.5 text-xs text-muted">Mínimo 12 caracteres, con letras y al menos un número.</p>
        </div>
        <div>
          <label class="field-label" for="current-password">Contraseña actual</label>
          <input id="current-password" name="current_password" type="password" class="input" autocomplete="current-password" required />
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
          <div>
            <label class="field-label" for="new-password">Nueva contraseña</label>
            <input id="new-password" name="new_password" type="password" class="input" autocomplete="new-password" minlength="12" maxlength="72" required />
          </div>
          <div>
            <label class="field-label" for="new-password-confirm">Repetir contraseña</label>
            <input id="new-password-confirm" name="confirm_password" type="password" class="input" autocomplete="new-password" minlength="12" maxlength="72" required />
          </div>
        </div>
        <div class="flex justify-end">
          <button type="submit" class="btn btn-secondary btn-sm" data-password-submit>Actualizar contraseña</button>
        </div>
      </form>
    `,onMount:e=>{let r=e.querySelector(`#profile-form`),o=e.querySelector(`#profile-color`),s=e.querySelector(`#profile-modal-avatar`);o?.addEventListener(`input`,()=>{s&&(s.style.background=o.value)}),r?.addEventListener(`submit`,async o=>{o.preventDefault();let s=e.querySelector(`[data-profile-submit]`);if(!s||!r)return;let c=new FormData(r);i(s,!0);try{let e=await a(`/auth/profile`,{method:`PATCH`,body:{name:c.get(`name`),email:c.get(`email`),avatar_color:c.get(`avatar_color`)}});u&&(u.user=e.data.user),h(e.data.user),n(`Perfil actualizado.`)}catch(e){n(t(e),`error`)}finally{i(s,!1)}});let c=e.querySelector(`#password-form`);c?.addEventListener(`submit`,async r=>{r.preventDefault();let o=e.querySelector(`[data-password-submit]`);if(!o||!c)return;let s=new FormData(c);if(s.get(`new_password`)!==s.get(`confirm_password`))n(`Las contraseñas nuevas no coinciden.`,`error`);else{i(o,!0,`Actualizando…`);try{await a(`/auth/password`,{method:`POST`,body:{current_password:s.get(`current_password`),new_password:s.get(`new_password`)}}),c.reset(),window.dispatchEvent(new CustomEvent(`crm:password-changed`)),n(`Contraseña actualizada.`)}catch(e){n(t(e),`error`)}finally{i(o,!1)}}})}})}function S(){let e=document.getElementById(`sidebar`),t=document.getElementById(`sidebar-backdrop`),n=()=>{e?.classList.remove(`-translate-x-full`),t?.classList.remove(`hidden`)},r=()=>{e?.classList.add(`-translate-x-full`),t?.classList.add(`hidden`)};document.getElementById(`sidebar-open`)?.addEventListener(`click`,n),document.getElementById(`sidebar-close`)?.addEventListener(`click`,r),t?.addEventListener(`click`,r),window.addEventListener(`resize`,()=>{window.innerWidth>=1024&&r()})}async function C(){try{u=await o()}catch{return}h(u.user,u.app_name),S(),g(`notifications-button`,`notifications-panel`),g(`profile-button`,`profile-panel`),document.addEventListener(`click`,()=>_()),document.addEventListener(`keydown`,e=>{e.key===`Escape`&&_()}),document.getElementById(`open-profile`)?.addEventListener(`click`,()=>{_(),x()}),document.getElementById(`logout-button`)?.addEventListener(`click`,async()=>{try{await a(`/auth/logout`,{method:`POST`})}catch{}s(),window.location.assign(`/app/login/`)}),document.getElementById(`notifications-read-all`)?.addEventListener(`click`,async e=>{e.stopPropagation();try{await a(`/notifications/read-all`,{method:`POST`}),await y()}catch(e){n(t(e),`error`)}}),document.addEventListener(`click`,e=>{e.target.closest(`[data-new-task]`)&&(e.preventDefault(),l({assignedTo:u?.user.id,onSaved:()=>void b(u.user.id)}))}),await y(),await b(u.user.id),window.setInterval(()=>{document.visibilityState===`visible`&&y()},6e4)}C();