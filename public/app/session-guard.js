/* Cierre de sesión por inactividad y aviso de contraseña temporal. */
(function () {
  'use strict';
  if (window.__crmSessionGuard) return;
  window.__crmSessionGuard = true;

  var IDLE_MS = 5 * 60 * 1000;
  var WARN_BEFORE_MS = 60 * 1000;
  var HEARTBEAT_MS = 60 * 1000;
  var lastActivity = Date.now();
  var warning = false;
  var loggingOut = false;
  var csrf = '';
  var warningBox = null;
  var countdown = null;
  var mustChangeBox = null;

  function api(path, options) {
    options = options || {};
    var headers = { Accept: 'application/json' };
    if (options.body !== undefined) headers['Content-Type'] = 'application/json';
    if (csrf) headers['X-CSRF-Token'] = csrf;
    return fetch('/api' + path, {
      method: options.method || 'GET',
      credentials: 'same-origin',
      headers: headers,
      body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
      keepalive: !!options.keepalive
    }).then(function (response) {
      return response.json().catch(function () { return {}; }).then(function (payload) {
        if (response.status === 401) {
          var error = new Error('session_expired');
          error.status = 401;
          throw error;
        }
        if (!response.ok || payload.ok === false) {
          var apiError = new Error((payload.error && payload.error.message) || 'No se pudo completar la operación.');
          apiError.status = response.status;
          throw apiError;
        }
        return payload;
      });
    });
  }

  function goToLogin() {
    if (loggingOut) return;
    loggingOut = true;
    window.location.replace('/app/login/?expired=1');
  }

  function expire() {
    if (loggingOut) return;
    loggingOut = true;
    if (warningBox) warningBox.remove();
    var finish = function () {
      try { localStorage.setItem('crm:logout', String(Date.now())); } catch (e) {}
      window.location.replace('/app/login/?expired=1');
    };
    var timeout = new Promise(function (resolve) { setTimeout(resolve, 1500); });
    var logout = api('/auth/logout', { method: 'POST', keepalive: true }).catch(function () {});
    Promise.race([logout, timeout]).then(finish, finish);
  }

  function showMustChange() {
    if (mustChangeBox || document.getElementById('crm-password-warning')) return;
    mustChangeBox = document.createElement('div');
    mustChangeBox.id = 'crm-password-warning';
    mustChangeBox.setAttribute('role', 'alert');
    mustChangeBox.style.cssText = 'position:fixed;left:50%;bottom:18px;transform:translateX(-50%);z-index:130;width:min(92vw,620px);display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:10px;border:1px solid #F5C9BC;border-radius:16px;background:#FFF3EF;color:#7A2A17;padding:12px 14px;box-shadow:0 14px 34px -20px #1E223080;font:13px/1.45 "Instrument Sans",system-ui,sans-serif';
    mustChangeBox.innerHTML = '<span><strong>Contraseña inicial detectada.</strong> Cámbiala para poder guardar cambios en el CRM.</span><button type="button" style="border:0;border-radius:999px;background:#FF5A36;color:#fff;padding:8px 14px;font:700 12px/1 \'Instrument Sans\',system-ui,sans-serif;cursor:pointer">Cambiar contraseña</button>';
    var button = mustChangeBox.querySelector('button');
    button.addEventListener('click', function () {
      var open = document.getElementById('open-profile');
      if (open) open.click();
      else window.location.assign('/app/settings/');
    });
    document.body.appendChild(mustChangeBox);
  }

  function hideMustChange() {
    if (mustChangeBox) {
      mustChangeBox.remove();
      mustChangeBox = null;
    }
    var existing = document.getElementById('crm-password-warning');
    if (existing) existing.remove();
  }

  function showWarning() {
    if (warning || warningBox) return;
    warning = true;
    warningBox = document.createElement('div');
    warningBox.setAttribute('role', 'dialog');
    warningBox.setAttribute('aria-modal', 'true');
    warningBox.setAttribute('aria-labelledby', 'crm-session-title');
    warningBox.style.cssText = 'position:fixed;inset:0;z-index:200;display:flex;align-items:center;justify-content:center;padding:16px;background:#1E223066;backdrop-filter:blur(3px)';
    warningBox.innerHTML = '<div style="width:min(94vw,430px);border:1px solid #E9E4DA;border-radius:22px;background:#FBF9F5;color:#1E2230;padding:22px;box-shadow:0 24px 60px -24px #1E223099;font:14px/1.5 \'Instrument Sans\',system-ui,sans-serif">'
      + '<p style="margin:0;font-size:10px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:#C7381A">Seguridad de la sesión</p>'
      + '<h2 id="crm-session-title" style="margin:5px 0 0;font-family:\'Bricolage Grotesque\',system-ui,sans-serif;font-size:23px;line-height:1.15">Tu sesión va a caducar</h2>'
      + '<p style="margin:8px 0 0;color:#5E6472">Por inactividad, cerraremos la sesión automáticamente en <strong id="crm-session-countdown" style="color:#C7381A">60</strong> segundos.</p>'
      + '<div style="display:flex;flex-wrap:wrap;justify-content:flex-end;gap:8px;margin-top:18px">'
      + '<button type="button" id="crm-session-exit" style="border:1px solid #E9E4DA;border-radius:999px;background:#fff;color:#1E2230;padding:9px 15px;font:700 12px/1.2 inherit;cursor:pointer">Cerrar sesión</button>'
      + '<button type="button" id="crm-session-continue" style="border:0;border-radius:999px;background:#FF5A36;color:#fff;padding:9px 15px;font:700 12px/1.2 inherit;cursor:pointer">Seguir conectado</button>'
      + '</div></div>';
    document.body.appendChild(warningBox);
    countdown = warningBox.querySelector('#crm-session-countdown');
    warningBox.querySelector('#crm-session-continue').addEventListener('click', continueSession);
    warningBox.querySelector('#crm-session-exit').addEventListener('click', expire);
    warningBox.querySelector('#crm-session-continue').focus();
  }

  function continueSession() {
    api('/auth/me').then(function (payload) {
      if (payload.data && payload.data.csrf) csrf = payload.data.csrf;
      warning = false;
      lastActivity = Date.now();
      if (warningBox) warningBox.remove();
      warningBox = null;
      if (payload.data && payload.data.user && Number(payload.data.user.must_change_password) === 1) showMustChange();
      if (payload.data && payload.data.user && Number(payload.data.user.must_change_password) !== 1) hideMustChange();
    }).catch(function (error) {
      if (error.status === 401) goToLogin();
    });
  }

  function tick() {
    if (loggingOut) return;
    var remaining = IDLE_MS - (Date.now() - lastActivity);
    if (remaining <= 0) {
      expire();
      return;
    }
    if (!warning && remaining <= WARN_BEFORE_MS) showWarning();
    if (warning && countdown) countdown.textContent = String(Math.max(0, Math.ceil(remaining / 1000)));
  }

  function activity() {
    if (warning || loggingOut) return;
    lastActivity = Date.now();
  }

  function heartbeat() {
    if (warning || loggingOut || document.visibilityState !== 'visible') return;
    if (Date.now() - lastActivity > HEARTBEAT_MS) return;
    api('/auth/me').catch(function (error) {
      if (error.status === 401) goToLogin();
    });
  }

  ['mousemove', 'mousedown', 'keydown', 'scroll', 'touchstart', 'pointerdown'].forEach(function (eventName) {
    document.addEventListener(eventName, activity, { passive: true });
  });
  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') tick();
  });
  window.addEventListener('storage', function (event) {
    if (event.key === 'crm:logout') goToLogin();
  });
  window.addEventListener('crm:password-changed', hideMustChange);

  api('/auth/me').then(function (payload) {
    if (payload.data && payload.data.csrf) csrf = payload.data.csrf;
    if (payload.data && payload.data.user && Number(payload.data.user.must_change_password) === 1) showMustChange();
    lastActivity = Date.now();
  }).catch(function (error) {
    if (error.status === 401) goToLogin();
  });

  window.setInterval(tick, 1000);
  window.setInterval(heartbeat, HEARTBEAT_MS);
})();
