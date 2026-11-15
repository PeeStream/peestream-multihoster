(function () {
  function init() {
    var list = document.getElementById('psmh-source-list');
    var add = document.getElementById('psmh-add-source');
    if (!list || !add) return;
    var template = list.querySelector('[data-template]');
    add.addEventListener('click', function () {
      var row = template.cloneNode(true);
      row.removeAttribute('data-template'); row.style.display = '';
      var key = 'new_' + Date.now() + '_' + Math.random().toString(36).slice(2, 8);
      row.innerHTML = row.innerHTML.replaceAll('__INDEX__', key);
      list.appendChild(row);
    });
    list.addEventListener('click', function (event) {
      if (event.target.classList.contains('psmh-remove')) event.target.closest('.psmh-row').remove();
    });
    list.addEventListener('click', function (event) {
      if (!event.target.classList.contains('psmh-up') && !event.target.classList.contains('psmh-down')) return;
      var row = event.target.closest('.psmh-row');
      if (event.target.classList.contains('psmh-up') && row.previousElementSibling && !row.previousElementSibling.hasAttribute('data-template')) list.insertBefore(row, row.previousElementSibling);
      if (event.target.classList.contains('psmh-down') && row.nextElementSibling && !row.nextElementSibling.hasAttribute('data-template')) list.insertBefore(row.nextElementSibling, row);
    });
    var form = list.closest('form');
    if (form) form.addEventListener('submit', function () {
      Array.prototype.forEach.call(list.querySelectorAll('.psmh-row:not([data-template])'), function (row, index) {
        var order = row.querySelector('[data-order]'); if (order) order.value = String(index);
      });
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
