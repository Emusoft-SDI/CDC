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
    form.addEventListener('submit', function (e) {
      if (form.checkValidity && !form.checkValidity()) {
        return;
      }
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

  window.addEventListener('pageshow', function () {
    forms.forEach(function (form) {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        submitBtn.disabled = false;
        submitBtn.style.opacity = '1';
        submitBtn.style.cursor = 'pointer';
      }
    });
  });

  function escapeHtml(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  window.selectCategory = function (key, btn) {
    const select = document.getElementById('category_select');
    if (select) {
      select.value = key;
    }
    document.querySelectorAll('.category-pill').forEach(function (el) {
      el.classList.remove('active');
    });
    if (btn) {
      btn.classList.add('active');
    }
  };

  window.updateFileList = function (input, listId) {
    const container = document.getElementById(listId);
    if (!container) return;
    container.innerHTML = '';
    if (!input.files || input.files.length === 0) {
      return;
    }
    const list = document.createElement('div');
    list.className = 'selected-file-chips';
    Array.from(input.files).forEach(function (file) {
      const chip = document.createElement('div');
      chip.className = 'file-chip';
      const size = file.size < 1048576
        ? (file.size / 1024).toFixed(1) + ' KB'
        : (file.size / 1048576).toFixed(2) + ' MB';
      chip.innerHTML = '<i class="fas fa-paperclip"></i> <span class="file-chip-name">' + escapeHtml(file.name) + '</span> <span class="file-chip-size">(' + size + ')</span>';
      list.appendChild(chip);
    });
    container.appendChild(list);
  };
})();

