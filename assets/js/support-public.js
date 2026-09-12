(function () {
  function openHashPanel() {
    if (!location.hash) return;
    const target = document.querySelector(location.hash);
    if (!target) return;
    const panel = target.closest('details.support-panel');
    if (panel) panel.open = true;
  }
  window.addEventListener('hashchange', openHashPanel);
  document.addEventListener('DOMContentLoaded', openHashPanel);
})();
