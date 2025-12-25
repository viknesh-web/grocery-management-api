document.addEventListener("DOMContentLoaded", function () {
 
    const leftTable = document.getElementById('leftTable');
    const rightTable = document.getElementById('rightTable');
    const allRows = document.querySelectorAll('#allProducts tr');
    const filter = document.getElementById('categoryFilter');
    const grandTotalEl = document.getElementById('grandTotal');
    const reviewBtn = document.getElementById('reviewBtn');
 
    /* =========================
       DISTRIBUTE PRODUCTS
    ========================== */
    // function distributeProducts() {
    //     leftTable.innerHTML = '';
    //     rightTable.innerHTML = '';
 
    //     const visibleRows = Array.from(allRows).filter(
    //         row => row.style.display !== 'none'
    //     );
 
    //     // ✅ Mobile / Tablet → Single table
    //     if (window.innerWidth <= 1320) {
    //         visibleRows.forEach(row => {
    //             leftTable.appendChild(row.cloneNode(true));
    //         });
    //         return;
    //     }
 
    //     // ✅ Desktop → Split into 2 tables
    //     const half = Math.ceil(visibleRows.length / 2);
 
    //     visibleRows.forEach((row, index) => {
    //         const clone = row.cloneNode(true);
    //         (index < half ? leftTable : rightTable).appendChild(clone);
    //     });
    // }
 
    //     function distributeProducts() {
    //     leftTable.innerHTML = '';
    //     rightTable.innerHTML = '';
 
    //     const visibleRows = Array.from(allRows).filter(
    //         row => row.style.display !== 'none'
    //     );
 
    //     const noProductsMsg = document.getElementById('noProductsMsg');
 
    //     // 🚨 NO PRODUCTS FOUND
    //     if (visibleRows.length === 0) {
    //         noProductsMsg.style.display = 'block';
    //         return;
    //     } else {
    //         noProductsMsg.style.display = 'none';
    //     }
 
    //     // ✅ Mobile / Tablet → Single table
    //     if (window.innerWidth <= 1320) {
    //         visibleRows.forEach(row => {
    //             leftTable.appendChild(row.cloneNode(true));
    //         });
    //         return;
    //     }
 
    //     // ✅ Desktop → Split into 2 tables
    //     const half = Math.ceil(visibleRows.length / 2);
 
    //     visibleRows.forEach((row, index) => {
    //         const clone = row.cloneNode(true);
    //         (index < half ? leftTable : rightTable).appendChild(clone);
    //     });
    // }
 
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
 
        /* =========================
           0 PRODUCTS
        ========================== */
        if (visibleRows.length === 0) {
            noProductsMsg.style.display = 'block';
 
            // 🔥 hide entire tables section (headers + body)
            tablesWrapper.style.display = 'none';
 
            // hide footer actions
            document.querySelector('.grand-total').style.display = 'none';
            reviewBtn.style.display = 'none';
 
            return;
        }
 
 
        noProductsMsg.style.display = 'none';
 
        /* =========================
           1 PRODUCT → FULL WIDTH
        ========================== */
        if (visibleRows.length === 1) {
            leftBox.classList.add('full-width');
            rightBox.style.display = 'none';
            leftTable.appendChild(visibleRows[0].cloneNode(true));
            return;
        }
 
        /* =========================
           MOBILE / TABLET
        ========================== */
        if (window.innerWidth <= 1320) {
            leftBox.classList.add('full-width');
            rightBox.style.display = 'none';
 
            visibleRows.forEach(row => {
                leftTable.appendChild(row.cloneNode(true));
            });
            return;
        }
 
        /* =========================
           DESKTOP → SPLIT TABLES
        ========================== */
        const half = Math.ceil(visibleRows.length / 2);
 
        visibleRows.forEach((row, index) => {
            const clone = row.cloneNode(true);
            (index < half ? leftTable : rightTable).appendChild(clone);
        });
    }
 
 
 
    /* =========================
       TOTAL CALCULATION
    ========================== */
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
 
 
    /* =========================
       EVENTS
    ========================== */
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
 
    /* =========================
       INITIAL LOAD
    ========================== */
    distributeProducts();
    calculateTotals();
 
    /* ===== REFRESH HANDLER ===== */
    if (performance.getEntriesByType("navigation")[0].type === "reload") {
        window.location.href = window.location.pathname;
    }
 
});