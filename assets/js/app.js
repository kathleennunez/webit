const themeToggle = document.querySelector('[data-theme-toggle]');
const root = document.documentElement;

function setTheme(theme) {
  root.setAttribute('data-theme', theme);
  document.body.setAttribute('data-theme', theme);
  localStorage.setItem('webit-theme', theme);
}

if (themeToggle) {
  themeToggle.addEventListener('click', () => {
    const current = root.getAttribute('data-theme') || 'light';
    setTheme(current === 'light' ? 'dark' : 'light');
  });
}

const savedTheme = localStorage.getItem('webit-theme');
if (savedTheme) {
  setTheme(savedTheme);
} else {
  const prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  setTheme(prefersDark ? 'dark' : 'light');
}

const sidebarToggle = document.querySelector('[data-sidebar-toggle]');
const sidebarState = localStorage.getItem('webit-sidebar');

if (sidebarState === 'collapsed') {
  document.body.classList.add('sidebar-collapsed');
}

if (sidebarToggle) {
  sidebarToggle.addEventListener('click', () => {
    document.body.classList.toggle('sidebar-collapsed');
    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('webit-sidebar', isCollapsed ? 'collapsed' : 'expanded');
  });
}

const searchInput = document.querySelector('[data-search]');
const filterSelect = document.querySelector('[data-filter]');
const searchForm = document.querySelector('[data-search-form]');
const webinarCards = document.querySelectorAll('[data-webinar-card]');

function filterWebinars() {
  const query = (searchInput?.value || '').toLowerCase();
  const category = filterSelect?.value || 'all';

  webinarCards.forEach((card) => {
    const title = card.getAttribute('data-title');
    const speaker = card.getAttribute('data-speaker');
    const tags = card.getAttribute('data-category');

    const matchesQuery = !query || [title, speaker, tags].some((val) => val?.includes(query));
    const matchesCategory = category === 'all' || tags === category;

    card.style.display = matchesQuery && matchesCategory ? '' : 'none';
  });
}

if (searchInput) {
  searchInput.addEventListener('input', filterWebinars);
}

if (filterSelect) {
  filterSelect.addEventListener('change', filterWebinars);
}

if (searchInput || filterSelect) {
  filterWebinars();
}

if (searchForm) {
  searchForm.addEventListener('submit', (event) => {
    event.preventDefault();
    filterWebinars();
  });
}

const registerButtons = document.querySelectorAll('[data-register-btn]');
registerButtons.forEach((btn) => {
  btn.addEventListener('click', () => {
    const spinner = btn.querySelector('.spinner-border');
    if (spinner) {
      spinner.classList.remove('d-none');
      setTimeout(() => spinner.classList.add('d-none'), 1200);
    }
  });
});

const imageInput = document.querySelector('[data-image-input]');
const imagePreview = document.querySelector('[data-image-preview]');

if (imageInput && imagePreview) {
  imageInput.addEventListener('change', (event) => {
    const file = event.target.files[0];
    if (file) {
      const reader = new FileReader();
      reader.onload = (e) => {
        imagePreview.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }
  });
}

const likeForms = document.querySelectorAll('[data-like-form]');
likeForms.forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const button = form.querySelector('[data-like-button]');
    const countEl = form.querySelector('[data-like-count]');
    const formData = new FormData(form);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      if (response.ok) {
        const current = parseInt(countEl.textContent, 10) || 0;
        countEl.textContent = String(current + 1);
      }
    } catch (error) {
      if (button) {
        button.classList.add('btn-danger');
      }
    }
  });
});

const commentForms = document.querySelectorAll('[data-comment-form]');
commentForms.forEach((form) => {
  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    const container = form.closest('.collapse');
    const list = container?.querySelector('[data-comment-list]');
    const emptyState = container?.querySelector('[data-empty-comments]');
    const countEl = container?.closest('.glass-panel')?.querySelector('[data-comment-count]');
    const input = form.querySelector('input[name=\"content\"]');
    const formData = new FormData(form);
    try {
      const response = await fetch(form.action, {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      if (response.ok) {
        const payload = await response.json();
        if (payload.comment && list) {
          const wrapper = document.createElement('div');
          wrapper.className = 'mb-3';
          const userId = form.dataset.userId || '';
          const userName = form.dataset.userName || payload.comment.author;
          const userAvatar = form.dataset.userAvatar || '/assets/images/avatar-default.svg';
          wrapper.innerHTML = `<div class=\"d-flex align-items-center gap-2 small\"><a href=\"/app/profile.php?user_id=${userId}\"><img src=\"${userAvatar}\" class=\"avatar-sm\" alt=\"Comment author\"></a><a class=\"text-decoration-none text-dark fw-semibold\" href=\"/app/profile.php?user_id=${userId}\">${userName}</a><span class=\"text-muted\">• ${payload.comment.created_at}</span></div><div class=\"text-muted small\">${payload.comment.content}</div>`;
          list.appendChild(wrapper);
          if (emptyState) {
            emptyState.remove();
          }
          if (countEl) {
            const current = parseInt(countEl.textContent, 10) || 0;
            countEl.textContent = String(current + 1);
          }
          if (input) {
            input.value = '';
          }
        }
      }
    } catch (error) {
      if (input) {
        input.classList.add('is-invalid');
      }
    }
  });
});

const timezonePickers = document.querySelectorAll('[data-timezone-picker]');
timezonePickers.forEach((picker) => {
  const input = picker.querySelector('[data-timezone-input]');
  const value = picker.querySelector('[data-timezone-value]');
  const menu = picker.querySelector('[data-timezone-menu]');
  const items = Array.from(picker.querySelectorAll('[data-timezone-item]'));

  function filterItems() {
    const query = input.value.toLowerCase();
    items.forEach((item) => {
      const text = item.dataset.timezoneItem.toLowerCase();
      const isMatch = text.includes(query);
      item.style.display = isMatch ? 'block' : 'none';
    });
    menu.classList.toggle('show', items.some((item) => item.style.display === 'block'));
  }

  input.addEventListener('focus', filterItems);
  input.addEventListener('click', filterItems);
  input.addEventListener('input', filterItems);
  input.addEventListener('blur', () => {
    setTimeout(() => menu.classList.remove('show'), 120);
  });

  items.forEach((item) => {
    item.addEventListener('click', () => {
      const tz = item.dataset.timezoneItem;
      input.value = tz;
      value.value = tz;
      menu.classList.remove('show');
    });
  });
});

const phoneInputs = document.querySelectorAll('[data-phone-input]');
if (phoneInputs.length && window.intlTelInput) {
  phoneInputs.forEach((input) => {
    const storedValue = input.dataset.phoneValue || '';
    const instance = window.intlTelInput(input, {
      initialCountry: 'ph',
      separateDialCode: true,
      nationalMode: false,
      autoPlaceholder: 'polite',
      utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@18.2.1/build/js/utils.js',
      hiddenInput: 'phone'
    });

    if (storedValue) {
      instance.setNumber(storedValue);
    }

    input.addEventListener('blur', () => {
      const number = instance.getNumber();
      if (!number) {
        return;
      }
      const form = input.form;
      const hidden = form ? form.querySelector('input[name="phone"]') : null;
      if (hidden) {
        hidden.value = number;
      }
    });
  });
}

const timePickers = document.querySelectorAll('[data-time-picker]');
timePickers.forEach((picker) => {
  const input = picker.querySelector('[data-time-input]');
  const value = picker.querySelector('[data-time-value]');
  const menu = picker.querySelector('[data-time-menu]');
  const items = Array.from(picker.querySelectorAll('[data-time-item]'));

  function filterItems() {
    const query = input.value.toLowerCase();
    let visible = 0;
    items.forEach((item) => {
      const text = item.textContent.toLowerCase();
      const raw = item.dataset.timeItem.toLowerCase();
      const isMatch = text.includes(query) || raw.includes(query);
      item.style.display = isMatch ? 'block' : 'none';
      if (isMatch) {
        visible += 1;
      }
    });
    menu.classList.toggle('show', visible > 0);
  }

  input.addEventListener('focus', filterItems);
  input.addEventListener('input', filterItems);
  input.addEventListener('blur', () => {
    setTimeout(() => menu.classList.remove('show'), 120);
    value.value = input.value.trim();
  });

  items.forEach((item) => {
    item.addEventListener('click', () => {
      const time = item.dataset.timeItem;
      input.value = time;
      value.value = time;
      menu.classList.remove('show');
    });
  });
});

const premiumToggle = document.querySelector('[data-premium-toggle]');
const premiumPrice = document.querySelector('[data-premium-price]');
if (premiumToggle && premiumPrice) {
  const togglePrice = () => {
    premiumPrice.classList.toggle('d-none', !premiumToggle.checked);
  };
  premiumToggle.addEventListener('change', togglePrice);
  togglePrice();
}

const passwordToggles = document.querySelectorAll('[data-password-toggle]');
passwordToggles.forEach((toggle) => {
  toggle.addEventListener('click', () => {
    const input = toggle.closest('.input-group')?.querySelector('[data-password-input]');
    if (!input) {
      return;
    }
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    const icon = toggle.querySelector('i');
    if (icon) {
      icon.classList.toggle('bi-eye', !show);
      icon.classList.toggle('bi-eye-slash', show);
    }
    toggle.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
  });
});

const deleteModal = document.querySelector('#deleteWebinarModal');
if (deleteModal) {
  deleteModal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    const webinarId = button?.getAttribute('data-webinar-id') || '';
    const webinarTitle = button?.getAttribute('data-webinar-title') || '';
    const idInput = deleteModal.querySelector('[data-confirm-id]');
    const titleEl = deleteModal.querySelector('[data-confirm-title]');
    if (idInput) {
      idInput.value = webinarId;
    }
    if (titleEl) {
      titleEl.textContent = webinarTitle;
    }
  });
}

document.querySelectorAll('#deleteFeedbackModal').forEach((modal) => {
  modal.addEventListener('show.bs.modal', (event) => {
    const button = event.relatedTarget;
    const feedbackId = button?.getAttribute('data-feedback-id') || '';
    const idInput = modal.querySelector('[data-feedback-id]');
    if (idInput) {
      idInput.value = feedbackId;
    }
  });
});
