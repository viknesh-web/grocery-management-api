/**
 * Order Review Page JavaScript
 * Handles modal interactions and order confirmation AJAX submission
 */

// Get CSRF token and submit URL from DOM
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
const modal = document.getElementById('orderModal');
const submitUrl = modal?.getAttribute('data-submit-url');

/**
 * Open order confirmation modal
 */
window.openOrderModal = function() {
    if (modal) {
        modal.style.display = 'flex';
        // Clear any previous errors
        const errorDiv = document.getElementById('formErrors');
        if (errorDiv) {
            errorDiv.style.display = 'none';
            errorDiv.innerHTML = '';
        }
    }
};

/**
 * Close order confirmation modal
 */
window.closeOrderModal = function() {
    if (modal) {
        modal.style.display = 'none';
    }
};

/**
 * Submit order via AJAX
 */
window.submitOrderAjax = function() {
    const form = document.querySelector('#orderModal form');
    const errorDiv = document.getElementById('formErrors');
    const submitBtn = document.querySelector('#orderModal button[onclick="submitOrderAjax()"]');
    
    if (!form || !submitUrl) {
        console.error('Form or submit URL not found');
        return;
    }

    if (!validateOrderForm(form)) {
        return;
    }
    
    // Disable submit button
    if (submitBtn) {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Submitting...';
    }
    
    // Clear previous errors
    if (errorDiv) {
        errorDiv.style.display = 'none';
        errorDiv.innerHTML = '';
    }
    
    const formData = new FormData(form);
    
    // Make AJAX request
    fetch(submitUrl, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        },
        body: formData
    })
    .then(response => {
        // Handle non-OK responses
        if (!response.ok) {
            return response.json().then(err => {
                throw err;
            });
        }
        return response.json();
    })
    .then(data => {
        if (data.status && data.redirect_url) {
            // Success - redirect to confirmation page
            window.location.href = data.redirect_url;
        } else {
            // Show error message
            showErrors({ message: data.message || 'Failed to submit order' });
            // Re-enable submit button
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.textContent = 'Submit Order';
            }
        }
    })
    .catch(error => {
        console.error('Order submission error:', error);
        
        // Show validation errors or generic error
        if (error.errors) {
            showErrors(error.errors);
        } else if (error.message) {
            showErrors({ message: error.message });
        } else {
            showErrors({ message: 'Failed to submit order. Please try again.' });
        }
        
        // Re-enable submit button
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Submit Order';
        }
    });
};

function validateOrderForm(form) {
    let isValid = true;

    // Clear old errors
    form.querySelectorAll('.field-error').forEach(e => e.remove());
    form.querySelectorAll('input').forEach(i => i.classList.remove('error'));

    const showFieldError = (input, message) => {
        isValid = false;
        input.classList.add('error');

        const error = document.createElement('div');
        error.className = 'field-error';
        error.textContent = message;
        input.closest('.form-group').appendChild(error);
    };

    const name = form.customer_name.value.trim();
    const whatsapp = form.whatsapp.value.trim();
    const address = form.address.value.trim();
    const email = form.email.value.trim();

    // Name validation
    if (!name) {
        showFieldError(form.customer_name, 'Full name is required');
    } else if (name.length < 3) {
        showFieldError(form.customer_name, 'Name must be at least 3 characters');
    }

    // WhatsApp validation (UAE)
    const cleanedWhatsapp = whatsapp.replace(/\D/g, '').replace(/^971/, '');
    if (!whatsapp) {
        showFieldError(form.whatsapp, 'WhatsApp number is required');
    } else if (
        !(
            (cleanedWhatsapp.length === 9 && cleanedWhatsapp.startsWith('5')) ||
            (cleanedWhatsapp.length === 12 && cleanedWhatsapp.startsWith('9715'))
        )
    ) {
        showFieldError(form.whatsapp, 'Enter a valid UAE WhatsApp number');
    }

   if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
        showFieldError(form.email, 'Enter a valid email address');
    }

    // Address validation
    if (!address) {
        showFieldError(form.address, 'Delivery address is required');
    }

    return isValid;
}

function initUAEWhatsappInput(inputId) {
    const whatsappInput = document.getElementById(inputId);
    if (!whatsappInput) return;

    const PREFIX = '+971 ';

    // Set prefix on focus
    whatsappInput.addEventListener('focus', () => {
        if (!whatsappInput.value) {
            whatsappInput.value = PREFIX;
        }
    });

    // Prevent deleting +971
    whatsappInput.addEventListener('keydown', (e) => {
        if (
            whatsappInput.selectionStart <= PREFIX.length &&
            (e.key === 'Backspace' || e.key === 'Delete')
        ) {
            e.preventDefault();
        }
    });

    // Auto-format while typing
    whatsappInput.addEventListener('input', () => {
        let value = whatsappInput.value.replace(/\D/g, '');

        // Remove country code if typed again
        if (value.startsWith('971')) {
            value = value.slice(3);
        }

        // Enforce UAE mobile starting with 5
        if (!value.startsWith('5')) {
            value = value.replace(/^[^5]+/, '');
        }

        value = value.slice(0, 9); // Max 9 digits

        let formatted = PREFIX;
        if (value.length > 0) formatted += value.slice(0, 2);
        if (value.length > 2) formatted += ' ' + value.slice(2, 5);
        if (value.length > 5) formatted += ' ' + value.slice(5, 9);

        whatsappInput.value = formatted;
    });

    // Handle paste
    whatsappInput.addEventListener('paste', (e) => {
        e.preventDefault();

        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const digits = pasted.replace(/\D/g, '');

        let number = digits.startsWith('971') ? digits.slice(3) : digits;
        number = number.startsWith('5') ? number.slice(0, 9) : '';

        let formatted = PREFIX;
        if (number.length > 0) formatted += number.slice(0, 2);
        if (number.length > 2) formatted += ' ' + number.slice(2, 5);
        if (number.length > 5) formatted += ' ' + number.slice(5, 9);

        whatsappInput.value = formatted;
    });
}


/**
 * Display validation errors
 * @param {Object} errors - Error object containing field errors or message
 */
function showErrors(errors) {
    const errorDiv = document.getElementById('formErrors');
    if (!errorDiv) return;
    
    let errorHtml = '';
    
    if (errors.message) {
        errorHtml = `<p>${errors.message}</p>`;
    } else if (typeof errors === 'object') {
        errorHtml = '<ul style="margin:0;padding-left:20px;">';
        for (const [field, messages] of Object.entries(errors)) {
            if (Array.isArray(messages)) {
                messages.forEach(msg => {
                    errorHtml += `<li>${msg}</li>`;
                });
            } else {
                errorHtml += `<li>${messages}</li>`;
            }
        }
        errorHtml += '</ul>';
    }
    
    errorDiv.innerHTML = errorHtml;
    errorDiv.style.display = 'block';
    errorDiv.style.backgroundColor = '#fee2e2';
    errorDiv.style.border = '1px solid #dc2626';
    errorDiv.style.color = '#dc2626';
    errorDiv.style.padding = '12px';
    errorDiv.style.borderRadius = '6px';
    errorDiv.style.marginTop = '10px';
}

/**
 * Address autocomplete functionality
 */
document.addEventListener('DOMContentLoaded', function() {
    const addressInput = document.getElementById('addressInput');
    const suggestionsList = document.getElementById('addressSuggestions');
    let debounceTimer;
    
    if (!addressInput || !suggestionsList) return;
    
    // Handle input with debounce
    addressInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        clearTimeout(debounceTimer);
        
        if (query.length < 3) {
            suggestionsList.innerHTML = '';
            suggestionsList.style.display = 'none';
            return;
        }
        
        debounceTimer = setTimeout(() => {
            fetchAddressSuggestions(query);
        }, 300);
    });
    
    // Handle click outside to close suggestions
    document.addEventListener('click', function(e) {
        if (!addressInput.contains(e.target) && !suggestionsList.contains(e.target)) {
            suggestionsList.style.display = 'none';
        }
    });
    
    // Close modal when clicking outside
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeOrderModal();
            }
        });
    }
    initUAEWhatsappInput('whatsapp');
});

/**
 * Fetch address suggestions from API
 * @param {string} query - Search query
 */
function fetchAddressSuggestions(query) {
    const suggestionsList = document.getElementById('addressSuggestions');
    if (!suggestionsList) return;
    
    // Show loading state
    suggestionsList.innerHTML = '<li style="padding:10px;color:#6b7280;">Searching...</li>';
    suggestionsList.style.display = 'block';
    
    // Fetch suggestions
    fetch(`/api/address/search?q=${encodeURIComponent(query)}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': csrfToken || '',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.features && data.features.length > 0) {
            displayAddressSuggestions(data.features);
        } else {
            suggestionsList.innerHTML = '<li style="padding:10px;color:#6b7280;">No addresses found</li>';
        }
    })
    .catch(error => {
        console.error('Address search error:', error);
        suggestionsList.innerHTML = '<li style="padding:10px;color:#dc2626;">Failed to search addresses</li>';
    });
}

/**
 * Display address suggestions in dropdown
 * @param {Array} features - Array of address features
 */
function displayAddressSuggestions(features) {
    const suggestionsList = document.getElementById('addressSuggestions');
    const addressInput = document.getElementById('addressInput');
    
    if (!suggestionsList || !addressInput) return;
    
    suggestionsList.innerHTML = '';
    
    features.forEach(feature => {
        const li = document.createElement('li');
        li.textContent = feature.properties.full_address || 'Unknown address';
        li.style.cursor = 'pointer';
        li.style.padding = '10px';
        li.style.borderBottom = '1px solid #e5e7eb';
        
        li.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f3f4f6';
        });
        
        li.addEventListener('mouseleave', function() {
            this.style.backgroundColor = 'white';
        });
        
        li.addEventListener('click', function() {
            addressInput.value = feature.properties.full_address;
            suggestionsList.style.display = 'none';
        });
        
        suggestionsList.appendChild(li);
    });
    
    suggestionsList.style.display = 'block';
}
