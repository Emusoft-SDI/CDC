(function () {
  const tabs = Array.from(document.querySelectorAll('[data-fo-tab]'));
  const panels = Array.from(document.querySelectorAll('[data-fo-panel]'));
  function activate(key) {
    const chosen = panels.some((panel) => panel.dataset.foPanel === key) ? key : 'overview';
    tabs.forEach((tab) => tab.setAttribute('aria-selected', tab.dataset.foTab === chosen ? 'true' : 'false'));
    panels.forEach((panel) => { panel.hidden = panel.dataset.foPanel !== chosen; });
    try { localStorage.setItem('natcodev_farm_operations_tab', chosen); } catch (error) {}
  }
  tabs.forEach((tab) => tab.addEventListener('click', () => activate(tab.dataset.foTab || 'overview')));
  let initial = 'overview';
  try { initial = localStorage.getItem('natcodev_farm_operations_tab') || initial; } catch (error) {}
  if (window.location.hash) initial = window.location.hash.replace('#', '');
  activate(initial);
})();
