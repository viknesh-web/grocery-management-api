document.addEventListener("DOMContentLoaded", function () {
    const leftTable  = document.getElementById('leftTable');
    const rightTable = document.getElementById('rightTable');
    const allRows    = document.querySelectorAll('#allProducts tr');
    const filter     = document.getElementById('categoryFilter');
    const grandTotalEl = document.getElementById('grandTotal');
    const reviewBtn = document.getElementById('reviewBtn');
    function distributeProducts() {
        leftTable.innerHTML = '';
        rightTable.innerHTML = '';

        const visibleRows = Array.from(allRows).filter(row => row.style.display !== 'none');
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
            const qty   = parseInt(row.querySelector('.qty')?.value || 0);
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
    }

    document.addEventListener('input', e => {
        if (e.target.classList.contains('qty')) calculateTotals();
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

    distributeProducts();
    calculateTotals();

    /* ===== REFRESH HANDLER ===== */
    if (performance.getEntriesByType("navigation")[0].type === "reload") {
        window.location.href = window.location.pathname;
    }

});
