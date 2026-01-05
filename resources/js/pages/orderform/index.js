document.addEventListener("DOMContentLoaded", function () {
    // Get unit conversions from blade template (passed from controller)
    const UNIT_CONVERSIONS = window.UNIT_CONVERSIONS || {};
    
    const leftTable = document.getElementById('leftTable');
    const rightTable = document.getElementById('rightTable');
    const allRows = document.querySelectorAll('#allProducts tr');
    const filter = document.getElementById('categoryFilter');
    const grandTotalEl = document.getElementById('grandTotal');
    const reviewBtn = document.getElementById('reviewBtn');
    const tablesWrapper = document.querySelector('.tables-wrapper');
    const noProductsMsg = document.getElementById('noProductsMsg');
 
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
 
        // RESET layout every time
        leftBox.classList.remove('full-width');
        leftBox.style.display = '';
        rightBox.style.display = '';
 
        if (visibleRows.length === 0) {
            noProductsMsg.style.display = 'block';
            tablesWrapper.style.display = 'none';
            
            // Hide footer actions
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
            return;
        }
 
        if (window.innerWidth <= 1320) {
            leftBox.classList.add('full-width');
            rightBox.style.display = 'none';
 
            visibleRows.forEach(row => {
                leftTable.appendChild(row.cloneNode(true));
            });
            return;
        }
 
        const half = Math.ceil(visibleRows.length / 2);
 
        visibleRows.forEach((row, index) => {
            const clone = row.cloneNode(true);
            (index < half ? leftTable : rightTable).appendChild(clone);
        });
    }
 
    /**
     * Calculate unit conversion factor
     * 
     * @param {string} baseUnit - Product's stock unit (e.g., 'kg')
     * @param {string} targetUnit - Customer's selected unit (e.g., 'g')
     * @returns {number|null} - Conversion factor or null if not possible
     */
    function getUnitConversionFactor(baseUnit, targetUnit) {
        baseUnit = baseUnit.toLowerCase().trim();
        targetUnit = targetUnit.toLowerCase().trim();
        
        // Same unit, no conversion needed
        if (baseUnit === targetUnit) {
            return 1;
        }
        
        // Find conversion factor
        for (const [family, data] of Object.entries(UNIT_CONVERSIONS)) {
            if (data.conversions && 
                data.conversions[baseUnit] && 
                data.conversions[targetUnit]) {
                
                const baseValue = data.conversions[baseUnit];
                const targetValue = data.conversions[targetUnit];
                
                return targetValue / baseValue;
            }
        }
        
        // No conversion found
        console.warn(`Cannot convert from ${targetUnit} to ${baseUnit}`);
        return null;
    }
    
    /**
     * Calculate price with unit conversion
     * 
     * @param {number} qty - Quantity
     * @param {string} unit - Selected unit
     * @param {string} baseUnit - Product's base unit
     * @param {number} basePrice - Price per base unit
     * @returns {number} - Total price
     */
    function calculateWithConversion(qty, unit, baseUnit, basePrice) {
        const conversionFactor = getUnitConversionFactor(baseUnit, unit);
        
        if (conversionFactor === null) {
            // Fallback to base calculation if conversion fails
            return basePrice * qty;
        }
        
        // Convert quantity to base unit
        // Example: 500g to kg → 500 / 1000 = 0.5 kg
        const qtyInBaseUnit = qty / conversionFactor;
        
        return basePrice * qtyInBaseUnit;
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
                // Unit conversion enabled
                const unit = unitSelect.value;
                const baseUnit = unitSelect.getAttribute('data-base-unit');
                
                if (unit === baseUnit) {
                    total = basePrice * qty;
                } else {
                    total = calculateWithConversion(qty, unit, baseUnit, basePrice);
                }
            } else {
                // No unit conversion (backward compatibility)
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
     * Handle review button click
     */
    document.getElementById('reviewBtn').addEventListener('click', function (e) {
        const hasQty = calculateTotals();
        const errorBox = document.getElementById('qtyError');
 
        if (!hasQty) {
            e.preventDefault();
            errorBox.style.display = 'block';
            errorBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            errorBox.style.display = 'none';
        }
    });
 
    /**
     * Listen for quantity changes
     */
    document.addEventListener('input', e => {
        if (e.target.classList.contains('qty')) {
            calculateTotals();
        }
    });
    
    /**
     * Listen for unit changes
     */
    document.addEventListener('change', e => {
        if (e.target.classList.contains('unit-select')) {
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
        window.location.href = window.location.pathname;
    }
});
