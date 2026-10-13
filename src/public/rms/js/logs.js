'use strict';
// Read-only filter convenience; actual date validation/query are handled by Laravel.
(() => {
  const preset = document.getElementById('rms-logs-preset');
  const dateField = document.getElementById('rms-logs-date-field');
  const range = document.getElementById('rms-logs-custom-range');
  const from = document.getElementById('rms-logs-from');
  const to = document.getElementById('rms-logs-to');
  if (!preset || !range || !from || !to) return;
  function sync() {
    const custom = preset.value === 'custom';
    range.hidden = !custom;
    from.disabled = !custom;
    to.disabled = !custom;
    from.required = custom;
    to.required = custom;
    if (dateField) dateField.hidden = custom;
  }
  preset.addEventListener('change', sync);
  sync();
})();

// Multi-select robot picker. Server performs all filtering; JS updates only the UI.
(() => {
  const picker = document.getElementById('rms-logs-robot-select');
  const summary = document.getElementById('rms-logs-robot-summary');
  const chips = document.getElementById('rms-logs-selected-robots');
  const options = Array.from(document.querySelectorAll('#rms-logs-robot-select input[name="robot_ids[]"]'));
  const all = document.getElementById('rms-logs-all');
  const selectAll = document.getElementById('rms-logs-select-all');
  const done = document.getElementById('rms-logs-robot-done');
  const search = document.getElementById('rms-logs-robot-search');

  if (!picker || !summary || !chips) return;

  function renderSelection() {
    const checked = options.filter(option => option.checked);
    summary.textContent = checked.length === 0
      ? 'Semua robot'
      : checked.length === 1
        ? checked[0].dataset.robotCode
        : `${checked.length} robot dipilih`;

    chips.replaceChildren();
    chips.hidden = checked.length === 0;
    for (const option of checked) {
      const chip = document.createElement('span');
      chip.className = 'rms-logs-robot-chip';
      chip.textContent = option.dataset.robotCode ?? option.value;
      chips.appendChild(chip);
    }
  }

  function setAll(checked) {
    for (const option of options) option.checked = checked;
    renderSelection();
  }

  options.forEach(option => option.addEventListener('change', renderSelection));
  all?.addEventListener('click', () => setAll(false));
  selectAll?.addEventListener('click', () => setAll(true));
  done?.addEventListener('click', () => { picker.open = false; });
  search?.addEventListener('input', () => {
    const term = search.value.trim().toLocaleLowerCase('id-ID');
    picker.querySelectorAll('.rms-logs-robot-option').forEach(row => {
      row.hidden = !(row.dataset.search ?? '').toLocaleLowerCase('id-ID').includes(term);
    });
  });
  document.addEventListener('click', event => {
    if (picker.open && !picker.contains(event.target)) picker.open = false;
  });
  renderSelection();
})();
