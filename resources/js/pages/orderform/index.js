document.addEventListener("DOMContentLoaded", function () {
 
    const leftTable = document.getElementById('leftTable');
    const rightTable = document.getElementById('rightTable');
    const allRows = document.querySelectorAll('#allProducts tr');
    const filter = document.getElementById('categoryFilter');
    const grandTotalEl = document.getElementById('grandTotal');
    const reviewBtn = document.getElementById('reviewBtn');
 
    function distributeProducts() {
        leftTable.innerHTML = '';
        rightTable.innerHTML = '';
 
        const visibleRows = Array.from(allRows).filter(
            row => row.style.display !== 'none'
        );
 
        const noProductsMsg = document.getElementById('noProductsMsg');
        const leftBox = leftTable.closest('.table-box');
        const rightBox = rightTable.closest('.table-box');
 
        // RESET layout every time
        leftBox.classList.remove('full-width');
        leftBox.style.display = '';
        rightBox.style.display = '';
 

        if (visibleRows.length === 0) {
            noProductsMsg.style.display = 'block';
 
            tablesWrapper.style.display = 'none';
 
            // hide footer actions
            document.querySelector('.grand-total').style.display = 'none';
            reviewBtn.style.display = 'none';
 
            return;
        }
 
 
        noProductsMsg.style.display = 'none';
 
    
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
 
 
 
    function calculateTotals() {
        let grandTotal = 0;
        let hasQty = false;
 
        document.querySelectorAll('.productTable tr').forEach(row => {
            const price = parseFloat(row.querySelector('.price-value')?.value || 0);
            const qty = parseInt(row.querySelector('.qty')?.value || 0);
            const total = price * qty;
 
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
 

    document.addEventListener('input', e => {
        if (e.target.classList.contains('qty')) {
            calculateTotals();
        }
    });
 
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
 
    window.addEventListener('resize', () => {
        distributeProducts();
        calculateTotals();
    });
 

    distributeProducts();
    calculateTotals();
 
    /* ===== REFRESH HANDLER ===== */
    if (performance.getEntriesByType("navigation")[0].type === "reload") {
        window.location.href = window.location.pathname;
    }
 
});
