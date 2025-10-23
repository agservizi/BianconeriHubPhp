document.addEventListener('DOMContentLoaded', () => {
    const navItems = document.querySelectorAll('[data-nav-target]');
    const currentPage = document.body.dataset.currentPage;

    navItems.forEach((item) => {
        const target = item.getAttribute('data-nav-target');
        if (target === currentPage) {
            item.classList.add('text-white', 'scale-105');
            item.classList.remove('text-gray-400');
        }

        item.addEventListener('click', () => {
            navItems.forEach((link) => link.classList.remove('scale-105'));
            item.classList.add('scale-105');
        });
    });
});
