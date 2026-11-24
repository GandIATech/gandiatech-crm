import{a as e,c as t,d as n,i as r,l as i,t as a,u as o}from"./ui.CJdB5Grl.js";import{a as s,h as c,i as l,n as u,r as d,u as f}from"./format.BIwoWcf9.js";function p(){let e=new Date().getHours();return e<13?`Buenos días`:e<20?`Buenas tardes`:`Buenas noches`}function m(e){let t=e.stats;document.getElementById(`hero-pipeline`).textContent=s(t.pipeline_value),document.getElementById(`kpi-pipeline`).textContent=l(t.pipeline_value),document.getElementById(`kpi-leads-open`).textContent=`${t.open_leads} leads abiertos · ${t.leads_total} en total`,document.getElementById(`kpi-won`).textContent=l(t.won_value),document.getElementById(`kpi-won-count`).textContent=`${t.won_leads} leads ganados`,document.getElementById(`kpi-projects`).textContent=String(t.projects_active),document.getElementById(`kpi-projects-total`).textContent=`${t.projects_total} proyectos en total`,document.getElementById(`kpi-tasks`).textContent=String(t.tasks_pending),document.getElementById(`kpi-tasks-mine`).textContent=`${t.my_tasks} asignadas a ti`;let n=document.getElementById(`kpi-overdue-badge`);t.tasks_overdue>0&&n&&(n.textContent=`${t.tasks_overdue} vencidas`,n.classList.remove(`hidden`)),h(e.pipeline),g(e.upcoming_tasks),_(e.recent_leads),v(e.activity)}function h(t){let n=document.getElementById(`pipeline-content`);if(!n)return;let i=t.reduce((e,t)=>e+Number(t.count),0);if(i===0){n.innerHTML=r(`Pipeline vacío`,`Crea tu primer lead para empezar a medir el embudo comercial.`,`<a href="/app/leads/" class="btn btn-primary btn-sm">Crear lead</a>`);return}let a=Math.max(...t.map(e=>Number(e.value)),1);n.innerHTML=`
        <div class="flex h-3 w-full gap-0.5 overflow-hidden rounded-full bg-line/60">${t.filter(e=>Number(e.count)>0).map(t=>`<span class="h-full transition-all" style="width:${Number(t.count)/i*100}%;background:${t.color}" title="${e(t.name)}: ${t.count}"></span>`).join(``)}</div>
        <div class="mt-5 space-y-1">
          ${t.map(t=>`
              <div class="group flex items-center gap-3 rounded-2xl px-2.5 py-2.5 transition hover:bg-cream">
                <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:${t.color}"></span>
                <span class="min-w-0 flex-1">
                  <span class="flex items-baseline justify-between gap-3">
                    <span class="truncate text-[13px] font-semibold">${e(t.name)}</span>
                    <span class="shrink-0 text-[12px] font-semibold tabular-nums">${l(t.value)}</span>
                  </span>
                  <span class="mt-1.5 flex items-center gap-2">
                    <span class="h-1 flex-1 overflow-hidden rounded-full bg-line/70"><span class="block h-full rounded-full" style="width:${Math.max(3,Number(t.value)/a*100)}%;background:${t.color}"></span></span>
                    <span class="w-14 shrink-0 text-right text-[10px] font-medium uppercase tracking-wide text-muted">${t.count} ${Number(t.count)===1?`lead`:`leads`}</span>
                  </span>
                </span>
              </div>`).join(``)}
        </div>`}function g(t){let a=document.getElementById(`upcoming-tasks`);a&&(t.length===0?a.innerHTML=r(`Sin tareas próximas`,`Tu equipo está al día. Crea una tarea para el siguiente paso.`):(a.innerHTML=`
        <div class="space-y-1">
          ${t.map(t=>`
              <div class="group flex items-start gap-3 rounded-2xl px-2.5 py-2.5 transition hover:bg-cream">
                <button type="button" data-complete-task="${t.id}" class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 border-line text-transparent transition hover:border-accent hover:text-accent" aria-label="Completar tarea">
                  <svg class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                </button>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-[13px] font-semibold leading-snug">${e(t.title)}</p>
                  <p class="mt-1 flex flex-wrap items-center gap-1.5 text-[11px] ${d(t.due_date,t.status)?`text-red-600`:`text-muted`}">
                    <span>${e(u(t.due_date,t.status))}</span>
                    <span class="text-line">·</span>
                    <span class="capitalize">${e(t.priority)}</span>
                    ${t.assigned_name?`<span class="text-line">·</span><span class="truncate">${e(t.assigned_name)}</span>`:``}
                  </p>
                </div>
              </div>`).join(``)}
        </div>
        <a href="/app/tasks/" class="mt-3 flex items-center justify-center gap-1 rounded-2xl border border-dashed border-line py-2.5 text-[11px] font-semibold text-muted transition hover:border-accent/40 hover:text-ink">Ver todas las tareas <span aria-hidden="true">→</span></a>`,a.querySelectorAll(`[data-complete-task]`).forEach(e=>{e.addEventListener(`click`,async()=>{e.disabled=!0;try{await o(`/tasks/${e.dataset.completeTask}`,{method:`PATCH`,body:{status:`completada`}}),i(`Tarea completada.`),await y()}catch(t){i(n(t),`error`),e.disabled=!1}})})))}function _(n){let i=document.getElementById(`recent-leads`);i&&(i.innerHTML=n.length===0?r(`Todavía no hay leads`,`Registra la primera oportunidad para activar tu pipeline.`,`<a href="/app/leads/" class="btn btn-primary btn-sm">Nuevo lead</a>`):`
        <table class="data-table min-w-[620px]">
          <thead><tr><th>Lead</th><th>Estado</th><th>Valor</th><th>Actualizado</th></tr></thead>
          <tbody>
            ${n.map(n=>`
                <tr>
                  <td>
                    <a href="/app/leads/?lead=${n.id}" class="font-semibold hover:text-accent-dark">${e(n.title)}</a>
                    <p class="mt-0.5 text-[11px] text-muted">${e(n.contact_name||n.contact_company||`Sin contacto`)}</p>
                  </td>
                  <td>${t(n.status_name||`Sin estado`,n.status_color)}</td>
                  <td class="font-semibold tabular-nums">${l(n.value)}</td>
                  <td class="text-[12px] text-muted">${e(f(n.updated_at))}</td>
                </tr>`).join(``)}
          </tbody>
        </table>`)}function v(t){let n=document.getElementById(`activity-feed`);n&&(n.innerHTML=t.length===0?r(`Sin actividad todavía`,`Los movimientos del equipo aparecerán aquí.`):`
        <ol class="relative space-y-4 before:absolute before:bottom-2 before:left-[17px] before:top-2 before:w-px before:bg-line">
          ${t.map(t=>`
              <li class="relative flex gap-3">
                ${a(t.user_name||`Sistema`,t.avatar_color,36)}
                <div class="min-w-0 flex-1">
                  <p class="text-[12px] leading-snug"><span class="font-semibold">${e(t.user_name||`Sistema`)}</span> <span class="text-muted">${e(t.description)}</span></p>
                  <p class="mt-1 text-[10px] font-medium uppercase tracking-wide text-muted/70">${e(f(t.created_at))}</p>
                </div>
              </li>`).join(``)}
        </ol>`)}async function y(){let e=await c();if(!e)return;let t=e.user.name.split(/\s+/)[0];document.getElementById(`greeting`).textContent=p(),document.getElementById(`greeting-name`).textContent=t;try{m((await o(`/dashboard`)).data)}catch(e){i(n(e),`error`)}}y();