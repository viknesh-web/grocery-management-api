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
        li.textContent = feature.properties.formatted || 'Unknown address';
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
            addressInput.value = feature.properties.formatted;
            suggestionsList.style.display = 'none';
        });
        
        suggestionsList.appendChild(li);
    });
    
    suggestionsList.style.display = 'block';
}