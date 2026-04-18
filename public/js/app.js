'use strict';

(function () {
    function setActiveNavItem() {
        const params = new URLSearchParams(window.location.search);
        const page = params.get('page') || 'dashboard';

        document.querySelectorAll('.nav-item').forEach(function (item) {
            const href = item.getAttribute('href') || '';
            const itemParams = new URLSearchParams(href.split('?')[1] || '');
            const itemPage = itemParams.get('page') || 'dashboard';

            if (itemPage === page) {
                item.classList.add('nav-item--active');
            } else {
                item.classList.remove('nav-item--active');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        setActiveNavItem();
    });
})();
