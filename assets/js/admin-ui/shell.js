/*
  NATCODEV Admin UI (v2) — shell behaviours.
  Only runs when <html> has [data-admin-ui]; legacy pages are untouched.
*/
(function () {
  'use strict';
  var root = document.documentElement;
  if (!root.hasAttribute('data-admin-ui')) { return; }

  function onReady(fn) {
    if (document.readyState !== 'loading') { fn(); }
    else { document.addEventListener('DOMContentLoaded', fn); }
  }

  onReady(function () {
    var toggle = document.querySelector('.a-menu-toggle');
    var scrim = document.querySelector('.a-scrim');

    function isOverlay() { return window.matchMedia('(max-width: 63.99rem)').matches; }
    function closeDrawer() {
      root.classList.remove('a-drawer-open');
      if (toggle) { toggle.setAttribute('aria-expanded', 'false'); }
    }
    if (toggle) {
      toggle.setAttribute('aria-expanded', root.classList.contains('a-drawer-open') ? 'true' : 'false');
      toggle.addEventListener('click', function () {
        var open = root.classList.toggle('a-drawer-open');
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
      });
    }
    if (scrim) { scrim.addEventListener('click', closeDrawer); }
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { closeDrawer(); } });
    document.querySelectorAll('.a-sidebar a').forEach(function (a) {
      a.addEventListener('click', function () { if (isOverlay()) { closeDrawer(); } });
    });

    // Password show/hide (same contract as the legacy admin layout).
    document.querySelectorAll('.password-toggle').forEach(function (button) {
      button.addEventListener('click', function () {
        var input = document.getElementById(button.dataset.target || '');
        if (!input) { return; }
        var show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        button.textContent = show ? 'Hide' : 'Show';
        button.setAttribute('aria-pressed', show ? 'true' : 'false');
      });
    });

    // Double-submit guard + busy state (mirrors the legacy admin layout).
    document.querySelectorAll('form').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        if (event.defaultPrevented) { return; }
        if (form.dataset.submitting === '1') { event.preventDefault(); return; }
        form.dataset.submitting = '1';
        var submitter = event.submitter || form.querySelector('button[type="submit"], button:not([type]), input[type="submit"]');
        if (submitter && submitter.name) {
          var hidden = document.createElement('input');
          hidden.type = 'hidden';
          hidden.name = submitter.name;
          hidden.value = submitter.value || '';
          form.appendChild(hidden);
        }
        if (submitter && submitter.tagName === 'BUTTON') {
          submitter.classList.add('is-busy');
          submitter.disabled = true;
          submitter.textContent = submitter.dataset.busyText || 'Processing...';
        }
        form.querySelectorAll('button[type="submit"], button:not([type]), input[type="submit"]').forEach(function (b) {
          if (b !== submitter) { b.disabled = true; }
        });
      });
    });
  });
})();
