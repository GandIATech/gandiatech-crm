/* Mejoras progresivas para campos de contraseña: visibilidad y fortaleza. */
(function () {
  'use strict';
  if (window.__crmPasswordTools) return;
  window.__crmPasswordTools = true;

  var MIN_LENGTH = 12;
  var MAX_LENGTH = 72;
  var eye = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></svg>';

  function isNewPassword(input) {
    return input.name === 'new_password'
      || input.name === 'admin_password'
      || input.id === 'new-password'
      || input.id === 'admin-password'
      || input.id === 'user-password';
  }

  function strength(value) {
    var score = 0;
    if (value.length >= MIN_LENGTH) score++;
    if (value.length >= 16) score++;
    if (/[a-záéíóúüñ]/i.test(value) && /[A-ZÁÉÍÓÚÜÑ]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9áéíóúüñÁÉÍÓÚÜÑ]/.test(value)) score++;
    if (/^(123456|password|qwerty|admin|gandiatech|crm)/i.test(value)) score = Math.min(score, 1);
    if (score <= 2) return { level: 'Débil', color: '#C7381A', width: 28 };
    if (score <= 3) return { level: 'Media', color: '#B45309', width: 58 };
    if (score === 4) return { level: 'Fuerte', color: '#1C6B45', width: 82 };
    return { level: 'Muy fuerte', color: '#1C6B45', width: 100 };
  }

  function addMeter(input, wrapper) {
    var meter = document.createElement('div');
    meter.setAttribute('aria-live', 'polite');
    meter.style.marginTop = '6px';
    meter.innerHTML = '<div style="height:4px;border-radius:999px;background:#E9E4DA;overflow:hidden"><span style="display:block;height:100%;width:0;border-radius:999px;transition:width .2s ease,background .2s ease"></span></div>'
      + '<p style="margin:4px 0 0;font-size:11px;line-height:1.4;color:#5E6472">Mínimo 12 caracteres, con letras y al menos un número.</p>';
    var bar = meter.querySelector('span');
    var hint = meter.querySelector('p');
    var update = function () {
      var value = input.value || '';
      if (!value) {
        bar.style.width = '0';
        hint.textContent = 'Mínimo 12 caracteres, con letras y al menos un número.';
        hint.style.color = '#5E6472';
        return;
      }
      var result = strength(value);
      bar.style.width = result.width + '%';
      bar.style.background = result.color;
      hint.textContent = 'Fortaleza: ' + result.level + '. Mínimo 12 caracteres, con letras y al menos un número.';
      hint.style.color = result.color;
    };
    input.addEventListener('input', update);
    update();
    if (wrapper && wrapper.parentNode) {
      wrapper.parentNode.insertBefore(meter, wrapper.nextSibling);
    } else if (input.parentNode) {
      input.parentNode.insertBefore(meter, input.nextSibling);
    }
  }

  function enhance(input) {
    if (!input || input.type !== 'password' || input.dataset.pwTools === '1') return;
    input.dataset.pwTools = '1';
    var serviceSecret = input.id === 'mail-password' || input.id === 'mysql-password';
    if (!input.getAttribute('autocomplete')) input.setAttribute('autocomplete', isNewPassword(input) ? 'new-password' : 'current-password');
    if (!serviceSecret) input.maxLength = MAX_LENGTH;
    if (isNewPassword(input) && (input.minLength < MIN_LENGTH || input.minLength < 0)) {
      input.minLength = MIN_LENGTH;
    }

    var parent = input.parentElement;
    var existingToggle = parent && parent.querySelector('button[aria-label*="contraseña" i], #toggle-password');
    var wrapper = null;

    if (!existingToggle) {
      wrapper = document.createElement('div');
      wrapper.style.position = 'relative';
      wrapper.style.width = '100%';
      input.parentNode.insertBefore(wrapper, input);
      wrapper.appendChild(input);

      var button = document.createElement('button');
      button.type = 'button';
      button.setAttribute('aria-label', 'Mostrar contraseña');
      button.setAttribute('data-password-toggle', '');
      button.innerHTML = eye;
      button.style.cssText = 'position:absolute;right:8px;top:50%;transform:translateY(-50%);display:flex;align-items:center;justify-content:center;width:32px;height:32px;border:0;border-radius:9px;background:transparent;color:#5E6472;cursor:pointer;padding:0';
      input.style.paddingRight = '46px';
      button.addEventListener('click', function () {
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.setAttribute('aria-label', show ? 'Ocultar contraseña' : 'Mostrar contraseña');
        button.style.color = show ? '#1E2230' : '#5E6472';
      });
      wrapper.appendChild(button);
    }

    if (isNewPassword(input)) addMeter(input, wrapper);
  }

  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    root.querySelectorAll('input[type="password"]').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { scan(document); });
  } else {
    scan(document);
  }

  new MutationObserver(function (mutations) {
    mutations.forEach(function (mutation) {
      mutation.addedNodes.forEach(function (node) {
        if (node.nodeType !== 1) return;
        if (node.matches && node.matches('input[type="password"]')) enhance(node);
        scan(node);
      });
    });
  }).observe(document.documentElement, { childList: true, subtree: true });
})();
