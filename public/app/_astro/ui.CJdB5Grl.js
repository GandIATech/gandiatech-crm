var e=``;function t(t){e=t}var n=class extends Error{status;fields;constructor(e,t=400,n={}){super(e),this.name=`ApiError`,this.status=t,this.fields=n}};async function r(t,r={}){let i=r.method??`GET`,a=new URL(`/api${t}`,window.location.origin);if(r.params)for(let[e,t]of Object.entries(r.params))t!=null&&t!==``&&a.searchParams.set(e,String(t));let o={Accept:`application/json`};r.body!==void 0&&(o[`Content-Type`]=`application/json`),e&&(o[`X-CSRF-Token`]=e);let s;try{s=await fetch(a.toString(),{method:i,credentials:`same-origin`,headers:o,body:r.body!==void 0&&r.body!==null?JSON.stringify(r.body):void 0})}catch{throw new n(`No se pudo conectar con el servidor. Comprueba tu conexión.`,0)}let c={};try{c=await s.json()}catch{throw new n(`La respuesta del servidor no es válida.`,s.status)}if(s.status===401&&!r.noRedirect)throw window.location.assign(`/app/login/?expired=1`),new n(`La sesión ha expirado.`,401);if(!s.ok||c.ok===!1)throw new n(c.error?.message??`No se pudo completar la operación.`,s.status,c.error?.fields??{});return{data:c.data,meta:c.meta??{}}}function i(e){if(e instanceof n){let t=Object.values(e.fields)[0];if(t)return t}return e instanceof Error?e.message:`Ha ocurrido un error inesperado.`}function a(e){return String(e??``).replaceAll(`&`,`&amp;`).replaceAll(`<`,`&lt;`).replaceAll(`>`,`&gt;`).replaceAll(`"`,`&quot;`).replaceAll(`'`,`&#039;`)}function o(e,t=`success`){let n=document.getElementById(`toast-root`);n||(n=document.createElement(`div`),n.id=`toast-root`,n.className=`pointer-events-none fixed right-4 top-4 z-[120] flex w-[min(92vw,380px)] flex-col gap-2`,document.body.appendChild(n));let r={success:`background:#D9F2E4;color:#1C6B45`,error:`background:#FFE6DE;color:#C7381A`,info:`background:#DCEBFF;color:#1E4C82`},i={success:`Listo`,error:`Error`,info:`Aviso`},o=document.createElement(`div`);o.className=`toast`,o.innerHTML=`
    <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-[11px] font-bold" style="${r[t]}">${i[t].charAt(0)}</span>
    <div class="min-w-0 flex-1">
      <p class="font-semibold">${a(i[t])}</p>
      <p class="mt-0.5 text-[13px] leading-snug text-muted">${a(e)}</p>
    </div>
    <button type="button" class="text-muted transition hover:text-ink" aria-label="Cerrar aviso">
      <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
    </button>
  `,o.querySelector(`button`)?.addEventListener(`click`,()=>o.remove()),n.appendChild(o),window.setTimeout(()=>{o.style.opacity=`0`,o.style.transition=`opacity 200ms ease`,window.setTimeout(()=>o.remove(),220)},4200)}function s(e){let t=document.getElementById(`modal-root`)??document.body,n=document.createElement(`dialog`);return n.className=`crm-dialog`,n.style.width=e.width??`min(94vw, 640px)`,n.innerHTML=`
    <div class="dialog-panel">
      <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
        <div class="min-w-0">
          <h2 class="font-display text-xl font-bold">${a(e.title)}</h2>
          ${e.subtitle?`<p class="mt-1 text-[13px] text-muted">${a(e.subtitle)}</p>`:``}
        </div>
        <button type="button" data-modal-close class="icon-btn h-9 w-9 shrink-0" aria-label="Cerrar">
          <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>
        </button>
      </div>
      <div class="max-h-[68vh] overflow-y-auto px-5 py-5 sm:px-6">${e.body}</div>
      ${e.footer?`<div class="flex flex-wrap justify-end gap-2 border-t border-line bg-cream/60 px-5 py-4 sm:px-6">${e.footer}</div>`:``}
    </div>
  `,t.appendChild(n),n.querySelectorAll(`[data-modal-close]`).forEach(e=>{e.addEventListener(`click`,()=>n.close())}),n.addEventListener(`click`,e=>{e.target===n&&n.close()}),n.addEventListener(`close`,()=>n.remove(),{once:!0}),n.showModal(),e.onMount?.(n),n}function c(e){return new Promise(t=>{let n=!1;s({title:e.title,body:`<p class="text-sm leading-relaxed text-muted">${a(e.message)}</p>`,footer:`
        <button type="button" class="btn btn-secondary" data-cancel>Cancelar</button>
        <button type="button" class="btn ${e.danger===!1?`btn-primary`:`bg-red-600 text-white hover:bg-red-700`}" data-confirm>${a(e.confirmLabel??`Eliminar`)}</button>
      `,width:`min(94vw, 460px)`,onMount:e=>{e.querySelector(`[data-cancel]`)?.addEventListener(`click`,()=>e.close()),e.querySelector(`[data-confirm]`)?.addEventListener(`click`,()=>{n=!0,t(!0),e.close()}),e.addEventListener(`close`,()=>{n||t(!1)})}}).addEventListener(`cancel`,()=>{n||t(!1)})})}function l(e,t,n=`Guardando…`){t?(e.dataset.originalHtml=e.innerHTML,e.disabled=!0,e.innerHTML=`<span class="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent"></span><span>${a(n)}</span>`):(e.disabled=!1,e.dataset.originalHtml&&(e.innerHTML=e.dataset.originalHtml,delete e.dataset.originalHtml))}function u(e,t,n=``){return`
    <div class="flex flex-col items-center justify-center px-6 py-14 text-center">
      <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-accent-soft text-accent-dark">
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 16 12 14 15 10 15 8 12 2 12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/></svg>
      </span>
      <h3 class="mt-4 font-display text-lg font-bold">${a(e)}</h3>
      <p class="mt-1 max-w-sm text-sm text-muted">${a(t)}</p>
      ${n?`<div class="mt-5">${n}</div>`:``}
    </div>
  `}function d(e,t,n=34){let r=t&&/^#[0-9a-fA-F]{6}$/.test(t)?t:`#FF5A36`,i=String(e||`?`).trim().split(/\s+/).slice(0,2).map(e=>e.charAt(0).toUpperCase()).join(``);return`<span class="inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white" style="width:${n}px;height:${n}px;background:${r};font-size:${Math.max(10,Math.round(n/2.8))}px" title="${a(e)}">${a(i)}</span>`}function f(e,t){let n=t&&/^#[0-9a-fA-F]{6}$/.test(t)?t:`#5E6472`;return`<span class="badge" style="background:${n}1A;color:${n}"><span class="h-1.5 w-1.5 rounded-full" style="background:${n}"></span>${a(e||`Sin estado`)}</span>`}function p(e,t=300){let n;return(...r)=>{window.clearTimeout(n),n=window.setTimeout(()=>e(...r),t)}}export{a,f as c,i as d,t as f,u as i,o as l,c as n,s as o,p as r,l as s,d as t,r as u};