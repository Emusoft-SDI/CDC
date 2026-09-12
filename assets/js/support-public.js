/**
 * Support Desk Client Enhancements
 * - Smooth member dropdown toggle
 * - Category pill interaction
 * - Clean hash scrolling
 */
(function () {
  'use strict';

  // Member dropdown toggle for mobile/click
  const dropdownBtn = document.getElementById('memberDropdownBtn');
  const dropdownMenu = document.getElementById('memberDropdownMenu');
  const dropdownWrap = document.getElementById('memberDropdown');

  if (dropdownBtn && dropdownMenu && dropdownWrap) {
    dropdownBtn.addEventListener('click', function (e) {
      e.stopPropagation();
      const isOpen = dropdownMenu.classList.contains('show');
      dropdownMenu.classList.toggle('show', !isOpen);
      dropdownBtn.setAttribute('aria-expanded', !isOpen);
    });

    document.addEventListener('click', function (e) {
      if (!dropdownWrap.contains(e.target)) {
        dropdownMenu.classList.remove('show');
        dropdownBtn.setAttribute('aria-expanded', 'false');
      }
    });
  }

  // Double-submit prevention for forms
  const forms = document.querySelectorAll('form');
  forms.forEach(function (form) {
    form.addEventListener('submit', function () {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn && !submitBtn.disabled) {
        setTimeout(function () {
          submitBtn.disabled = true;
          submitBtn.style.opacity = '0.7';
          submitBtn.style.cursor = 'not-allowed';
        }, 10);
      }
    });
  });
})();
