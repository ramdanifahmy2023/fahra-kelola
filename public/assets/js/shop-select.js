window.enhanceShopSelect = select => {
  const picker = document.createElement('div');
  picker.className = 'shop-picker dropdown dropdown-bottom dropdown-end';
  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.id = select.id + '-trigger';
  trigger.className = 'btn w-full justify-start gap-3 font-normal shadow-sm';
  const menu = document.createElement('ul');
  menu.className = 'shop-picker-list dropdown-content menu rounded-box mt-2 bg-base-100 p-2 shadow-lg';
  menu.setAttribute('aria-label', 'Daftar toko');
  menu.id = select.id + '-options';
  menu.hidden = true;
  trigger.setAttribute('aria-controls', menu.id);
  trigger.setAttribute('aria-expanded', 'false');
  select.before(picker);
  picker.append(trigger, menu);
  select.hidden = true;
  const label = document.querySelector('label[for="' + select.id + '"]');
  if (label) label.htmlFor = trigger.id;
  function setOpen(open) {
    menu.hidden = !open;
    trigger.setAttribute('aria-expanded', String(open));
  }
  trigger.addEventListener('click', () => setOpen(menu.hidden));
  picker.addEventListener('focusout', event => {
    if (!picker.contains(event.relatedTarget)) setOpen(false);
  });
  document.addEventListener('click', event => {
    if (!picker.contains(event.target)) setOpen(false);
  });

  function content(option, target) {
    const logo = document.createElement('span');
    logo.className = 'shop-picker-logo';
    window.renderShopLogo(logo, option.value);
    const name = document.createElement('span');
    name.className = 'min-w-0 flex-1 text-left';
    name.textContent = option.text;
    target.replaceChildren(logo, name);
  }

  function sync() {
    trigger.disabled = select.disabled;
    if (select.disabled) setOpen(false);
    const selected = select.selectedOptions[0] || select.options[0];
    if (!selected) return;
    content(selected, trigger);
    trigger.children[1].classList.add('truncate');
    const arrow = document.createElement('span');
    arrow.className = 'material-symbols-outlined';
    arrow.setAttribute('aria-hidden', 'true');
    arrow.textContent = 'expand_more';
    trigger.append(arrow);
    trigger.setAttribute('aria-label', (label?.textContent || 'Toko') + ': ' + selected.text);
    menu.replaceChildren();
    Array.from(select.options).forEach(option => {
      const row = document.createElement('li');
      const button = document.createElement('button');
      button.type = 'button';
      button.dataset.shopValue = option.value;
      button.disabled = option.disabled;
      content(option, button);
      if (option.selected) {
        button.classList.add('bg-base-200');
        button.setAttribute('aria-current', 'true');
      }
      button.addEventListener('click', () => {
        select.value = option.value;
        setOpen(false);
        trigger.focus();
        sync();
        select.dispatchEvent(new Event('change', {bubbles: true}));
      });
      row.append(button);
      menu.append(row);
    });
  }
  picker.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      event.preventDefault();
      setOpen(false);
      trigger.focus();
    }
    if (event.key === 'ArrowDown' && event.target === trigger) {
      event.preventDefault();
      setOpen(true);
      menu.querySelector('button:not(:disabled)')?.focus();
    }
  });
  select.addEventListener('change', sync);
  sync();
  return sync;
};
