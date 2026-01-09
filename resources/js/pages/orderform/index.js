document.addEventListener("DOMContentLoaded", function () {
    // Get unit conversions from blade template (passed from controller)
    const UNIT_CONVERSIONS = window.UNIT_CONVERSIONS || {};
    const PRODUCT_MIN_QTY = window.PRODUCT_MIN_QTY || {};
    
    const leftTable = document.getElementById('leftTable');
    const rightTable = document.getElementById('rightTable');
    const allRows = document.querySelectorAll('#allProducts tr');
    const filter = document.getElementById('categoryFilter');
    const grandTotalEl = document.getElementById('grandTotal');
    const reviewBtn = document.getElementById('reviewBtn');
    const tablesWrapper = document.querySelector('.tables-wrapper');
    const noProductsMsg = document.getElementById('noProductsMsg');
    const minQtyErrorSummary = document.getElementById('minQtyErrorSummary');
    const minQtyErrorList = document.getElementById('minQtyErrorList');
 
    /**
     * Distribute products between left and right tables
     */
    function distributeProducts() {
        leftTable.innerHTML = '';
        rightTable.innerHTML = '';
 
        const visibleRows = Array.from(allRows).filter(
            row => row.style.display !== 'none'
        );
 
        const leftBox = leftTable.closest('.table-box');
        const rightBox = rightTable.closest('.table-box');
 
        leftBox.classList.remove('full-width');
        leftBox.style.display = '';
        rightBox.style.display = '';
 
        if (visibleRows.length === 0) {
            noProductsMsg.style.display = 'block';
            tablesWrapper.style.display = 'none';
            
            document.querySelector('.grand-total').style.display = 'none';
            reviewBtn.style.display = 'none';
            
            return;
        }
 
        noProductsMsg.style.display = 'none';
        tablesWrapper.style.display = '';
        document.querySelector('.grand-total').style.display = '';
        reviewBtn.style.display = '';
    
        if (visibleRows.length === 1) {
            leftBox.classList.add('full-width');
            rightBox.style.display = 'none';
            leftTable.appendChild(visibleRows[0].cloneNode(true));
            attachEventListeners();
            return;
        }
 
        if (window.innerWidth <= 1320) {
            leftBox.classList.add('full-width');
            rightBox.style.display = 'none';
 
            visibleRows.forEach(row => {
                leftTable.appendChild(row.cloneNode(true));
            });
            attachEventListeners();
            return;
        }
 
        const half = Math.ceil(visibleRows.length / 2);
 
        visibleRows.forEach((row, index) => {
            const clone = row.cloneNode(true);
            (index < half ? leftTable : rightTable).appendChild(clone);
        });
        
        attachEventListeners();
    }
 
    /**
     * Attach event listeners to cloned rows
     */
    function attachEventListeners() {
        document.querySelectorAll('.qty').forEach(input => {
            input.addEventListener('input', function() {
                validateMinimumQuantity(this);
                calculateTotals();
            });
        });
        
        document.querySelectorAll('.unit-select').forEach(select => {
            select.addEventListener('change', function() {
                const productId = this.getAttribute('data-product-id');
                const qtyInput = document.querySelector(`.qty[data-product-id="${productId}"]`);
                if (qtyInput) {
                    validateMinimumQuantity(qtyInput);
                }
                calculateTotals();
            });
        });
    }
 
    /**
     * Calculate unit conversion factor
     */
    function getUnitConversionFactor(baseUnit, targetUnit) {
        baseUnit = baseUnit.toLowerCase().trim();
        targetUnit = targetUnit.toLowerCase().trim();
        
        if (baseUnit === targetUnit) {
            return 1;
        }
        
        for (const [family, data] of Object.entries(UNIT_CONVERSIONS)) {
            if (data.conversions && 
                data.conversions[baseUnit] && 
                data.conversions[targetUnit]) {
                
                const baseValue = data.conversions[baseUnit];
                const targetValue = data.conversions[targetUnit];
                
                return targetValue / baseValue;
            }
        }
        
        console.warn(`Cannot convert from ${targetUnit} to ${baseUnit}`);
        return null;
    }
    
    /**
     * Calculate price with unit conversion
     */
    function calculateWithConversion(qty, unit, baseUnit, basePrice) {
        const conversionFactor = getUnitConversionFactor(baseUnit, unit);
        
        if (conversionFactor === null) {
            return basePrice * qty;
        }
        
        const qtyInBaseUnit = qty / conversionFactor;
        
        return basePrice * qtyInBaseUnit;
    }
    
    /**
     * Validate minimum quantity for a product
     */
    function validateMinimumQuantity(qtyInput) {
        const productId = qtyInput.getAttribute('data-product-id');
        const row = qtyInput.closest('tr');
        const minQty = parseFloat(row.getAttribute('data-min-qty'));
        const stockUnit = row.getAttribute('data-stock-unit');
        const qty = parseFloat(qtyInput.value || 0);
        
        // Clear previous error state
        qtyInput.classList.remove('qty-input-error');
        const warning = document.getElementById(`minQtyWarning${productId}`);
        if (warning) {
            warning.classList.remove('show');
        }
        
        // If no minimum or quantity is 0, no validation needed
        if (!minQty || minQty <= 0 || qty <= 0) {
            return true;
        }
        
        // Get selected unit
        const unitSelect = row.querySelector('.unit-select');
        const selectedUnit = unitSelect ? unitSelect.value : stockUnit;
        
        // Check if minimum quantity is met
        let isValid = false;
        
        if (selectedUnit.toLowerCase() === stockUnit.toLowerCase()) {
            // Same unit, direct comparison
            isValid = qty >= minQty;
        } else {
            // Different unit, convert to base unit
            const conversionFactor = getUnitConversionFactor(stockUnit, selectedUnit);
            if (conversionFactor !== null) {
                const qtyInBaseUnit = qty / conversionFactor;
                isValid = qtyInBaseUnit >= minQty;
            } else {
                // Cannot convert, assume valid
                isValid = true;
            }
        }
        
        // Show error if not valid
        if (!isValid) {
            qtyInput.classList.add('qty-input-error');
            if (warning) {
                warning.classList.add('show');
            }
            return false;
        }
        
        return true;
    }
    
    /**
     * Validate all minimum quantities and show summary
     */
    /**
     * Validate minimum quantity for a product
     */
    function validateMinimumQuantity(qtyInput) {
        const productId = qtyInput.getAttribute('data-product-id');
        const row = qtyInput.closest('tr');
        const minQty = parseFloat(row.getAttribute('data-min-qty'));
        const stockUnit = row.getAttribute('data-stock-unit');
        const qty = parseFloat(qtyInput.value || 0);
        
        // Clear previous error state
        qtyInput.classList.remove('qty-input-error');
        const warning = document.getElementById(`minQtyWarning${productId}`);
        if (warning) {
            warning.classList.remove('show');
        }
        
        // If no minimum or quantity is 0, no validation needed
        if (!minQty || minQty <= 0 || qty <= 0) {
            return true;
        }
        
        // Get selected unit
        const unitSelect = row.querySelector('.unit-select');
        const selectedUnit = unitSelect ? unitSelect.value : stockUnit;
        
        // Check if minimum quantity is met
        let isValid = false;
        
        if (selectedUnit.toLowerCase() === stockUnit.toLowerCase()) {
            // Same unit, direct comparison
            isValid = qty >= minQty;
        } else {
            // Different unit, convert to base unit
            const conversionFactor = getUnitConversionFactor(stockUnit, selectedUnit);
            if (conversionFactor !== null) {
                const qtyInBaseUnit = qty / conversionFactor;
                isValid = qtyInBaseUnit >= minQty;
            } else {
                // Cannot convert, assume valid
                isValid = true;
            }
        }
        
        // Show error if not valid
        if (!isValid) {
            qtyInput.classList.add('qty-input-error');
            if (warning) {
                warning.classList.add('show');
            }
            return false;
        }
        
        return true;
    }

    /**
     * Validate all minimum quantities and show summary
     */
    function validateAllMinimumQuantities() {
        const errors = [];
        
        document.querySelectorAll('.productTable tr').forEach(row => {
            const qtyInput = row.querySelector('.qty');
            if (!qtyInput) return;
            
            const qty = parseFloat(qtyInput.value || 0);
            if (qty <= 0) return; // Skip products with no quantity
            
            const productId = qtyInput.getAttribute('data-product-id');
            const minQty = parseFloat(row.getAttribute('data-min-qty'));
            const stockUnit = row.getAttribute('data-stock-unit');
            const productName = row.getAttribute('data-product-name') || 'Product';
            
            if (!minQty || minQty <= 0) return;
            
            const unitSelect = row.querySelector('.unit-select');
            const selectedUnit = unitSelect ? unitSelect.value : stockUnit;
            
            // Check if minimum is met
            let isValid = false;
            
            if (selectedUnit.toLowerCase() === stockUnit.toLowerCase()) {
                isValid = qty >= minQty;
            } else {
                const conversionFactor = getUnitConversionFactor(stockUnit, selectedUnit);
                if (conversionFactor !== null) {
                    const qtyInBaseUnit = qty / conversionFactor;
                    isValid = qtyInBaseUnit >= minQty;
                } else {
                    isValid = true;
                }
            }
            
            if (!isValid) {
                const minQtyDisplay = minQty + ' ' + stockUnit;
                errors.push({
                    productId: productId,
                    productName: productName,
                    message: `${productName}: Minimum order is ${minQtyDisplay}`
                });
            }
        });
        
        // Show or hide error summary
        if (errors.length > 0 && minQtyErrorSummary) {
            minQtyErrorList.innerHTML = errors.map(err => 
                `<li>${err.message}</li>`
            ).join('');
            minQtyErrorSummary.classList.add('show');
            return false;
        } else if (minQtyErrorSummary) {
            minQtyErrorSummary.classList.remove('show');
            return true;
        }
        
        return true;
    }
 
    /**
     * Calculate totals for all products
     */
    function calculateTotals() {
        let grandTotal = 0;
        let hasQty = false;
 
        document.querySelectorAll('.productTable tr').forEach(row => {
            const priceInput = row.querySelector('.price-value');
            const qtyInput = row.querySelector('.qty');
            const unitSelect = row.querySelector('.unit-select');
            
            if (!priceInput || !qtyInput) return;
            
            const basePrice = parseFloat(priceInput.value || 0);
            const qty = parseFloat(qtyInput.value || 0);
            
            let total = 0;
            
            if (unitSelect) {
                const unit = unitSelect.value;
                const baseUnit = unitSelect.getAttribute('data-base-unit');
                
                if (unit === baseUnit) {
                    total = basePrice * qty;
                } else {
                    total = calculateWithConversion(qty, unit, baseUnit, basePrice);
                }
            } else {
                total = basePrice * qty;
            }
 
            const rowTotalEl = row.querySelector('.row-total');
            if (rowTotalEl) rowTotalEl.textContent = total.toFixed(2);
 
            if (qty > 0) {
                hasQty = true;
                grandTotal += total;
            }
        });
 
        grandTotalEl.textContent = grandTotal.toFixed(2);
        reviewBtn.disabled = !hasQty;
        return hasQty;
    }
 
    /**
     * Handle review button click with minimum quantity validation
     */
    document.getElementById('reviewBtn').addEventListener('click', function (e) {
        const hasQty = calculateTotals();
        const errorBox = document.getElementById('qtyError');
 
        if (!hasQty) {
            e.preventDefault();
            errorBox.style.display = 'block';
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        
        // Validate minimum quantities
        const minQtyValid = validateAllMinimumQuantities();
        
        if (!minQtyValid) {
            e.preventDefault();
            minQtyErrorSummary.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }
        
        errorBox.style.display = 'none';
    });
 
    /**
     * Listen for quantity changes
     */
    document.addEventListener('input', e => {
        if (e.target.classList.contains('qty')) {
            validateMinimumQuantity(e.target);
            calculateTotals();
        }
    });
    
    /**
     * Listen for unit changes
     */
    document.addEventListener('change', e => {
        if (e.target.classList.contains('unit-select')) {
            const productId = e.target.getAttribute('data-product-id');
            const qtyInput = document.querySelector(`.qty[data-product-id="${productId}"]`);
            if (qtyInput) {
                validateMinimumQuantity(qtyInput);
            }
            calculateTotals();
        }
    });
 
    /**
     * Category filter
     */
    if (filter) {
        filter.addEventListener('change', function () {
            const val = this.value;
 
            allRows.forEach(row => {
                row.style.display = (!val || row.dataset.category === val) ? '' : 'none';
            });
 
            distributeProducts();
            calculateTotals();
            validateAllMinimumQuantities();
        });
    }
 
    /**
     * Window resize handler
     */
    window.addEventListener('resize', () => {
        distributeProducts();
        calculateTotals();
    });
 
    /**
     * Initial setup
     */
    distributeProducts();
    calculateTotals();
 
    /**
     * Refresh handler - redirect to base URL
     */
   if (performance.getEntriesByType("navigation")[0].type === "reload") {
        // Check if this is an admin order (has required query params)
        const urlParams = new URLSearchParams(window.location.search);
        const isAdmin = urlParams.get('is_admin');
        const hasSignature = urlParams.get('signature');
        
        // Only redirect to clean URL if NOT an admin order
        if (!isAdmin || !hasSignature) {
            window.location.href = window.location.pathname;
        }
        // For admin orders, keep the URL parameters intact
    }
});