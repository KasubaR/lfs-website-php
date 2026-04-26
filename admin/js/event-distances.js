/**
 * LFS admin — add/remove per-distance rows on the event form.
 */
(function () {
  const list = document.getElementById('lfsDistanceRows');
  const addBtn = document.getElementById('lfsAddDistance');
  if (!list || !addBtn) return;

  function clearRow(li) {
    li.querySelectorAll('input[name="dist_label[]"]').forEach((el) => { el.value = ''; });
    li.querySelectorAll('input[name="dist_route_file[]"]').forEach((el) => { el.value = ''; });
    const h = li.querySelector('input[name="dist_route_existing[]"]');
    if (h) h.value = '';
    const pr = li.querySelector('.event-form__distance-preview');
    if (pr) pr.remove();
  }

  addBtn.addEventListener('click', function () {
    const first = list.querySelector('li[data-distance-row]');
    if (!first) return;
    const clone = first.cloneNode(true);
    clearRow(clone);
    list.appendChild(clone);
  });

  list.addEventListener('click', function (e) {
    const btn = e.target.closest('.lfsRemoveDistance');
    if (!btn) return;
    const li = btn.closest('li[data-distance-row]');
    if (!li) return;
    const items = list.querySelectorAll('li[data-distance-row]');
    if (items.length <= 1) {
      clearRow(li);
      return;
    }
    li.remove();
  });
}());
