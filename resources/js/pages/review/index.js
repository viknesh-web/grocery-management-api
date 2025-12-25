document.addEventListener('DOMContentLoaded', () => {
    window.openOrderModal = function () {
        const modal = document.getElementById('orderModal');
        if (modal) {
            modal.style.display = 'flex';
        }
    };
    window.closeOrderModal = function () {
        const modal = document.getElementById('orderModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    const submitUrl = document.getElementById('orderModal').dataset.submitUrl;
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : null;
    window.submitOrderAjax = function () {
        const form = document.querySelector('#orderModal form');
        const formData = new FormData(form);
        fetch(submitUrl, {
            method: "POST",
            headers: Object.assign({ 'Accept': 'application/json' }, csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            body: formData
        })
            .then(res => {
                if (!res.ok) return res.json().then(err => Promise.reject(err));
                return res.json();
            })
            .then(data => {
                if (!data.status) {
                    showErrors(data.errors);
                } else if (data.redirect_url) {
                    window.location.href = data.redirect_url;
                } else {
                    form.submit();
                }
            })
            .catch(err => {
                if (err.errors) {
                    showErrors(err.errors);
                }
            });
    }
    function showErrors(errors) {
        let html = '<ul>';
        Object.values(errors).forEach(err => {
            html += `<li>${err[0]}</li>`;
        });
        html += '</ul>';
        document.getElementById('formErrors').innerHTML = html;
    }

    const addressInput = document.getElementById('addressInput');
    const suggestionBox = document.getElementById('addressSuggestions');
    let debounceTimer = null;
    const addressCache = {};
    addressInput.addEventListener('input', function () {
        const query = this.value.trim();
        if (query.length < 2) {
            suggestionBox.innerHTML = '';
            return;
        }
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            if (addressCache[query]) {
                renderSuggestions(addressCache[query]);
            } else {
                fetchDubaiAddresses(query);
            }
        }, 200);
    });
    function fetchDubaiAddresses(query) {
        suggestionBox.innerHTML = '<li>Searching…</li>';
        fetch(`/geoapify/address?q=${encodeURIComponent(query)}`)
            .then(res => res.json())
            .then(data => {
                addressCache[query] = data;
                renderSuggestions(data);
            });
    }
    function renderSuggestions(features) {
        suggestionBox.innerHTML = '';
        if (!features.length) {
            suggestionBox.innerHTML = '<li>No results found</li>';
            return;
        }
        features.forEach(item => {
            const li = document.createElement('li');
            li.textContent = item.properties.formatted;
            li.addEventListener('click', () => {
                addressInput.value = item.properties.formatted;
                suggestionBox.innerHTML = '';
            });
            suggestionBox.appendChild(li);
        });
    }
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.form-group')) {
            suggestionBox.innerHTML = '';
        }
    });
});
