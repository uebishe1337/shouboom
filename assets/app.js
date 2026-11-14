/* ШоуБум — общие интерактивные элементы */
(function () {
  'use strict';

  /* ---------- Мобильное меню ---------- */
  const burger = document.getElementById('burgerBtn');
  const mobileNav = document.getElementById('mobileNav');
  if (burger && mobileNav) {
    burger.addEventListener('click', function () {
      burger.classList.toggle('open');
      mobileNav.classList.toggle('open');
    });
  }

  /* ---------- Подтверждение удаления ---------- */
  document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('click', function (e) {
      if (!window.confirm(el.getAttribute('data-confirm') || 'Вы уверены?')) {
        e.preventDefault();
      }
    });
  });

  /* ---------- Автоскрытие уведомлений ---------- */
  document.querySelectorAll('.alert').forEach(function (el) {
    setTimeout(function () {
      el.style.transition = 'opacity .4s';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 450);
    }, 6500);
  });

  /* ---------- Маска телефона ---------- */
  function formatPhone(raw) {
    let digits = String(raw || '').replace(/\D/g, '');
    if (!digits) return '';
    if (digits[0] === '8') digits = '7' + digits.slice(1);
    if (digits[0] !== '7') digits = '7' + digits;
    digits = digits.slice(0, 11);
    let out = '+7';
    if (digits.length > 1) out += ' (' + digits.slice(1, 4);
    if (digits.length >= 4) out += ') ' + digits.slice(4, 7);
    if (digits.length >= 7) out += '-' + digits.slice(7, 9);
    if (digits.length >= 9) out += '-' + digits.slice(9, 11);
    return out;
  }

  document.querySelectorAll('input[type="tel"], [data-phone-mask]').forEach(function (input) {
    input.addEventListener('input', function () {
      const pos = input.selectionStart;
      input.value = formatPhone(input.value);
      // Курсор оставляем в конце — маска простая и предсказуемая
      if (pos !== null) input.setSelectionRange(input.value.length, input.value.length);
    });
    input.addEventListener('blur', function () {
      if (input.value) input.value = formatPhone(input.value);
    });
  });

  /* ---------- Минимальная дата ---------- */
  document.querySelectorAll('input[type="date"][data-min-today]').forEach(function (input) {
    const now = new Date();
    const local = new Date(now.getTime() - now.getTimezoneOffset() * 60000);
    input.min = local.toISOString().slice(0, 10);
    if (!input.value) input.value = input.min;
  });

  /* ---------- DaData: подсказки и проверка ---------- */
  const API = 'api/dadata.php';
  const cache = {};

  function statusEl(input) {
    const selector = input.getAttribute('data-status');
    if (selector) return document.querySelector(selector);
    return input.parentElement ? input.parentElement.querySelector('.dadata-status') : null;
  }

  function setStatus(input, text, ok) {
    const el = statusEl(input);
    if (!el) return;
    el.textContent = text || '';
    el.className = 'dadata-status' + (ok === true ? ' ok' : ok === false ? ' err' : '');
  }

  function closeDropdown(input) {
    const group = input.closest('.form-group') || input.parentElement;
    if (!group) return;
    group.querySelectorAll('.dd-list').forEach(function (list) { list.remove(); });
  }

  function showDropdown(input, items, onSelect) {
    closeDropdown(input);
    if (!items.length) return;
    const group = input.closest('.form-group') || input.parentElement;
    group.style.position = 'relative';
    const list = document.createElement('ul');
    list.className = 'dd-list';
    items.forEach(function (item) {
      const li = document.createElement('li');
      li.className = 'dd-item';
      li.textContent = item.value || item;
      li.addEventListener('mousedown', function (e) {
        e.preventDefault();
        input.value = item.value || item;
        closeDropdown(input);
        if (typeof onSelect === 'function') onSelect(item);
        input.dispatchEvent(new Event('change'));
      });
      list.appendChild(li);
    });
    group.appendChild(list);
  }

  async function suggest(type, query) {
    const key = type + '|' + query;
    if (cache[key]) return cache[key];
    const res = await fetch(API + '?action=suggest&type=' + encodeURIComponent(type) + '&query=' + encodeURIComponent(query), {
      headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) return { ok: false, suggestions: [] };
    const data = await res.json();
    cache[key] = data;
    return data;
  }

  async function clean(type, value) {
    const res = await fetch(API + '?action=clean&type=' + encodeURIComponent(type) + '&value=' + encodeURIComponent(value), {
      headers: { 'Accept': 'application/json' }
    });
    if (!res.ok) return { ok: false };
    return res.json();
  }

  document.querySelectorAll('[data-dadata]').forEach(function (input) {
    const type = input.getAttribute('data-dadata');
    if (!type) return;
    let timer = null;

    input.setAttribute('autocomplete', 'off');
    input.addEventListener('input', function () {
      clearTimeout(timer);
      const query = input.value.trim();
      if (type === 'address' ? query.length < 3 : query.length < 2) {
        closeDropdown(input);
        return;
      }
      timer = setTimeout(async function () {
        const data = await suggest(type, query);
        if (!data || !data.ok) {
          setStatus(input, 'DaData временно недоступна — заполните вручную', false);
          return;
        }
        if (data.suggestions && data.suggestions.length) {
          showDropdown(input, data.suggestions, function () {
            setStatus(input, '✓ выбрано из подсказок DaData', true);
          });
          if (data.fallback) {
            setStatus(input, 'Подсказки из локального справочника (тариф DaData не включает suggestions)', null);
          }
        } else {
          closeDropdown(input);
        }
      }, 350);
    });

    input.addEventListener('blur', function () {
      closeDropdown(input);
      const value = input.value.trim();
      if (!value || !input.hasAttribute('data-clean')) return;
      setTimeout(async function () {
        const data = await clean(type, value);
        if (data && data.ok && data.value) {
          if (data.value !== value) input.value = data.value;
          setStatus(input, '✓ проверено DaData', true);
        }
      }, 120);
    });
  });

  /* ---------- Калькулятор заказа ---------- */
  const orderForm = document.getElementById('orderForm');
  if (orderForm) {
    const animator = orderForm.querySelector('[name="animator_id"]');
    const program = orderForm.querySelector('[name="program_id"]');
    const hours = orderForm.querySelector('[name="duration_hours"]');
    const preview = document.getElementById('pricePreview');

    function money(n) {
      return new Intl.NumberFormat('ru-RU').format(Math.round(n)) + ' ₽';
    }

    function recalc() {
      if (!preview) return;
      const opt = animator && animator.options[animator.selectedIndex];
      const rate = opt ? parseFloat(opt.getAttribute('data-rate') || '0') : 0;
      const progOpt = program && program.options[program.selectedIndex];
      const programPrice = progOpt ? parseFloat(progOpt.getAttribute('data-price') || '0') : 0;
      const h = hours ? Math.max(0.5, parseFloat(hours.value || '1')) : 1;
      const base = programPrice > 0 ? programPrice : rate;
      const total = base + Math.max(0, h - 1) * rate;
      preview.textContent = money(total);
    }

    [animator, program, hours].forEach(function (el) {
      if (el) el.addEventListener('change', recalc);
    });
    recalc();
  }

  /* ---------- Автотабы (hash) ---------- */
  document.querySelectorAll('[data-tab-link]').forEach(function (link) {
    link.addEventListener('click', function () {
      document.querySelectorAll('[data-tab-link]').forEach(function (l) { l.classList.remove('active'); });
      link.classList.add('active');
    });
  });
})();
