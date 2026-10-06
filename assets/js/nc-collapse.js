/**
 * NATCODEV wallet collapse (progressive enhancement).
 *
 * Turns the wallet's <div class="card"><div class="card-header">…</div>…</div>
 * blocks into the master design-system `.collapse-card` <details> component.
 * Defaults to open, so nothing is hidden; with JS disabled the cards render as-is.
 */
(function () {
  'use strict';

  function enhance() {
    var cards = document.querySelectorAll('.fin-main .card, .review-main .card');
    Array.prototype.forEach.call(cards, function (card) {
      if (card.dataset.ncCollapse === '1') return;
      if (card.closest('details.collapse-card')) return;

      var header = null;
      for (var i = 0; i < card.children.length; i++) {
        if (card.children[i].classList.contains('card-header')) { header = card.children[i]; break; }
      }
      if (!header) return;

      var details = document.createElement('details');
      details.className = 'collapse-card';
      details.open = true;

      var summary = document.createElement('summary');
      var icon = document.createElement('span');
      icon.className = 'cc-icon';
      icon.innerHTML = '<i class="fas fa-table-columns"></i>';
      var title = document.createElement('span');
      title.className = 'collapse-title';
      title.innerHTML = header.innerHTML;
      var caret = document.createElement('span');
      caret.className = 'caret';
      caret.innerHTML = '<i class="fas fa-chevron-down"></i>';
      summary.appendChild(icon);
      summary.appendChild(title);
      summary.appendChild(caret);

      var body = document.createElement('div');
      body.className = 'collapse-body';
      Array.prototype.slice.call(card.children).forEach(function (child) {
        if (child !== header) { body.appendChild(child); }
      });

      card.innerHTML = '';
      details.appendChild(summary);
      details.appendChild(body);
      card.appendChild(details);

      card.dataset.ncCollapse = '1';
      card.style.border = '0';
      card.style.boxShadow = 'none';
      card.style.background = 'transparent';
      card.style.padding = '0';
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', enhance);
  } else {
    enhance();
  }
})();
