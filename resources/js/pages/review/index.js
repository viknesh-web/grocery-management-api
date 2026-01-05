document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('orderModal');
  const form = modal.querySelector('form');
  const phoneInput = document.getElementById('phoneInput');
  const addressInput = document.getElementById('addressInput');
  const suggestionBox = document.getElementById('addressSuggestions');
  
  const submitUrl = modal.dataset.submitUrl;
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
  
  let debounceTimer = null;
  const addressCache = {};

  // Modal controls
  window.openOrderModal = () => modal.style.display = 'flex';
  window.closeOrderModal = () => modal.style.display = 'none';

  // Phone input formatting
  if (phoneInput) {
    phoneInput.addEventListener('input', (e) => {
      const cursorAtEnd = phoneInput.selectionStart === phoneInput.value.length;
      phoneInput.value = formatUAEPhone(phoneInput.value);
      if (cursorAtEnd) phoneInput.setSelectionRange(phoneInput.value.length, phoneInput.value.length);
    });

    phoneInput.addEventListener('focus', () => {
      if (!phoneInput.value.startsWith('+971')) phoneInput.value = '+971 ';
    });
  }

  // Address autocomplete
  addressInput.addEventListener('input', function() {
    const query = this.value.trim();
    suggestionBox.innerHTML = '';
    
    if (query.length < 2) return;
    
    clearTimeout(debounceTimer);
    debounceTimer = setTimeout(() => {
      if (addressCache[query]) {
        renderSuggestions(addressCache[query]);
      } else {
        fetchAddresses(query);
      }
    }, 200);
  });

  function fetchAddresses(query) {
    suggestionBox.innerHTML = '<li>Searching…</li>';
    
    fetch(`/geoapify/address?query=${encodeURIComponent(query)}`)
      .then(res => res.json())
      .then(res => {
        addressCache[query] = res.data;
        renderSuggestions(res.data);
      });
  }

  function renderSuggestions(items) {
    suggestionBox.innerHTML = '';
    
    if (!items?.length) {
      suggestionBox.innerHTML = '<li>No results found</li>';
      return;
    }

    items.forEach(item => {
      const li = document.createElement('li');
      li.textContent = item.full_address || item.area;
      li.addEventListener('click', () => {
        addressInput.value = item.full_address || item.area;
        suggestionBox.innerHTML = '';
      });
      suggestionBox.appendChild(li);
    });
  }

  // Close suggestions on outside click
  document.addEventListener('click', (e) => {
    if (!e.target.closest('.form-group')) suggestionBox.innerHTML = '';
  });

  // Form submission
  window.submitOrderAjax = function() {
    clearErrors(form);
    
    if (!validateForm(form)) return;

    const formData = new FormData(form);
    const headers = { 'Accept': 'application/json' };
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken;

    fetch(submitUrl, { method: 'POST', headers, body: formData })
      .then(res => res.ok ? res.json() : res.json().then(err => Promise.reject(err)))
      .then(data => {
        if (data.redirect_url) {
          window.location.href = data.redirect_url;
        } else if (!data.status) {
          showErrors(data.errors);
        } else {
          form.submit();
        }
      })
      .catch(err => err.errors && showErrors(err.errors));
  };

  function validateForm(form) {
    const fields = ['name', 'address', 'email', 'phone'];
    let isValid = true;

    fields.forEach(field => {
      const input = form.querySelector(`[name="${field}"]`);
      if (!input) return;

      const value = input.value.trim();
      input.classList.remove('input-error');

      if (!value || 
          (field === 'address' && value.length < 3) ||
          (field === 'email' && !isValidEmail(value)) ||
          (field === 'phone' && !isValidUAEPhone(value))) {
        input.classList.add('input-error');
        isValid = false;
      }
    });

    return isValid;
  }

  function clearErrors(form) {
    form.querySelectorAll('input, textarea, select').forEach(el => {
      el.classList.remove('input-error', 'input-success');
    });
    form.querySelectorAll('.field-error').forEach(el => el.textContent = '');
    document.getElementById('formErrors').innerHTML = '';
  }

  function showErrors(errors) {
    const errorList = Object.values(errors).map(err => `<li>${err[0]}</li>`).join('');
    document.getElementById('formErrors').innerHTML = `<ul>${errorList}</ul>`;
  }

  function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
  }

  function isValidUAEPhone(phone) {
    const digits = phone.replace(/\D/g, '');
    return /^9715\d{8}$/.test(digits);
  }

  function formatUAEPhone(value = '') {
    let digits = value.replace(/\D/g, '');
    if (digits.startsWith('971')) digits = digits.slice(3);
    if (digits.startsWith('0')) digits = digits.slice(1);
    digits = digits.slice(0, 9);

    let formatted = '+971';
    if (digits.length > 0) formatted += ' ' + digits.slice(0, 2);
    if (digits.length > 2) formatted += ' ' + digits.slice(2, 5);
    if (digits.length > 5) formatted += ' ' + digits.slice(5, 9);
    
    return formatted;
  }
});