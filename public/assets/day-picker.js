(() => {
  const parseDays = (value) => String(value || '')
    .split(/[\s,;]+/)
    .map((item) => Number.parseInt(item, 10))
    .filter((day) => Number.isFinite(day) && day > 0);

  const normalizeDays = (days) => Array.from(new Set(days))
    .sort((a, b) => b - a);

  document.querySelectorAll('[data-day-picker]').forEach((picker) => {
    const hidden = picker.querySelector('[data-day-values]');
    const chips = picker.querySelector('[data-day-chips]');
    const input = picker.querySelector('[data-day-input]');
    const addButton = picker.querySelector('[data-day-add]');
    const optionButtons = Array.from(picker.querySelectorAll('[data-day-option]'));
    if (!hidden || !chips || !input || !addButton) {
      return;
    }

    let days = normalizeDays(parseDays(hidden.value));

    const render = () => {
      hidden.value = days.join(',');
      chips.innerHTML = '';

      if (days.length === 0) {
        const empty = document.createElement('span');
        empty.className = 'day-empty muted';
        empty.textContent = 'Bildirim gunu yok';
        chips.appendChild(empty);
      }

      days.forEach((day) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'day-chip';
        chip.textContent = `${day} gun`;
        chip.setAttribute('aria-label', `${day} gun bildirimini kaldir`);
        chip.addEventListener('click', () => {
          days = days.filter((item) => item !== day);
          render();
        });
        chips.appendChild(chip);
      });

      optionButtons.forEach((button) => {
        const day = Number.parseInt(button.dataset.dayOption || '', 10);
        const selected = days.includes(day);
        button.classList.toggle('active', selected);
        button.setAttribute('aria-pressed', selected ? 'true' : 'false');
      });
    };

    const addDay = (value) => {
      const day = Number.parseInt(String(value || ''), 10);
      if (!Number.isFinite(day) || day <= 0) {
        input.focus();
        return;
      }

      days = normalizeDays([...days, day]);
      input.value = '';
      render();
    };

    addButton.addEventListener('click', () => addDay(input.value));
    input.addEventListener('keydown', (event) => {
      if (event.key === 'Enter') {
        event.preventDefault();
        addDay(input.value);
      }
    });

    optionButtons.forEach((button) => {
      button.addEventListener('click', () => addDay(button.dataset.dayOption || ''));
    });

    render();
  });
})();
