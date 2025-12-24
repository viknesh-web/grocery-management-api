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
});
