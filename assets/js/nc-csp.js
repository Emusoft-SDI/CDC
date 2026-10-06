/**
 * NATCODEV admin CSP runtime (Phase 9).
 *
 * Implements the delegated behaviour for the data attributes produced by
 * lib/admin-csp.php (which converts legacy inline on* handlers). No eval, no
 * inline script — safe under a nonce-based CSP.
 */
(function () {
  'use strict';

  function closest(el, selector) {
    while (el && el.nodeType === 1) {
      if (el.matches && el.matches(selector)) return el;
      el = el.parentElement;
    }
    return null;
  }

  function all(selector) {
    return Array.prototype.slice.call(document.querySelectorAll(selector));
  }

  // Clicks: confirm, select-all, set-value(+submit), generic fn call.
  document.addEventListener('click', function (event) {
    var target = event.target;

    var mapEl = closest(target, '[data-nc-confirm-map]');
    if (mapEl) {
      var mapForm = mapEl.form || closest(mapEl, 'form');
      var mapFieldName = mapEl.getAttribute('data-nc-confirm-field');
      var mapField = mapForm && mapForm.elements ? mapForm.elements[mapFieldName] : null;
      var mapValue = mapField ? mapField.value : '';
      var map = {};
      try { map = JSON.parse(mapEl.getAttribute('data-nc-confirm-map') || '{}'); } catch (err) { map = {}; }
      if (map[mapValue] && !window.confirm(map[mapValue])) {
        event.preventDefault();
        return;
      }
    }

    var confirmEl = closest(target, '[data-nc-confirm]');
    if (confirmEl) {
      var needsConfirm = true;
      var whenField = confirmEl.getAttribute('data-nc-confirm-when-field');
      if (whenField) {
        var confirmForm = confirmEl.form || closest(confirmEl, 'form');
        var field = confirmForm && confirmForm.elements ? confirmForm.elements[whenField] : null;
        needsConfirm = !!(field && field.value === confirmEl.getAttribute('data-nc-confirm-when-value'));
      }
      if (needsConfirm && !window.confirm(confirmEl.getAttribute('data-nc-confirm'))) {
        event.preventDefault();
        return;
      }
    }

    var checkAll = closest(target, '[data-nc-check-all]');
    if (checkAll) {
      var sel = checkAll.getAttribute('data-nc-check-all');
      if (sel) all(sel).forEach(function (cb) { cb.checked = checkAll.checked; });
    }

    var setVal = closest(target, '[data-nc-set-value]');
    if (setVal) {
      var field = document.getElementById(setVal.getAttribute('data-nc-set-value'));
      if (field) field.value = setVal.getAttribute('data-nc-set-to') || '';
      if (setVal.hasAttribute('data-nc-submit')) {
        var form = setVal.form || closest(setVal, 'form');
        if (form) { event.preventDefault(); form.submit(); return; }
      }
    }

    var callEl = closest(target, '[data-nc-call]');
    if (callEl) {
      var fn = window[callEl.getAttribute('data-nc-call')];
      if (typeof fn === 'function') {
        var args = [];
        if (callEl.getAttribute('data-nc-args-from') === 'value') {
          args = [callEl.value];
        } else if (callEl.hasAttribute('data-nc-args')) {
          try { args = JSON.parse(callEl.getAttribute('data-nc-args')); } catch (err) { args = []; }
        }
        fn.apply(callEl, args);
      }
    }
  }, true);

  // Changes: auto-submit (optionally resetting page to 1).
  document.addEventListener('change', function (event) {
    var el = closest(event.target, '[data-nc-autosubmit]');
    if (!el) return;
    var form = el.form || closest(el, 'form');
    if (!form) return;
    if (el.hasAttribute('data-nc-reset-page') && form.page) form.page.value = '1';
    form.submit();
  }, true);

  // Submits: confirm gate.
  document.addEventListener('submit', function (event) {
    var el = closest(event.target, '[data-nc-confirm-submit]');
    if (el && !window.confirm(el.getAttribute('data-nc-confirm-submit'))) {
      event.preventDefault();
    }
  }, true);
})();
