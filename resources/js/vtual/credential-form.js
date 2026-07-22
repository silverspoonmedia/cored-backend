(function () {
    'use strict';

    const typeSelect = document.getElementById('credential-type');
    if (!typeSelect) {
        return;
    }

    const toggle = () => {
        const type = typeSelect.value;
        document.querySelectorAll('[data-secret-fields]').forEach((el) => {
            const match = el.getAttribute('data-secret-fields') === type;
            el.classList.toggle('d-none', !match);
            el.querySelectorAll('input').forEach((input) => {
                input.disabled = !match;
            });
        });
    };

    typeSelect.addEventListener('change', toggle);
    toggle();
})();
