(function () {
    'use strict';

    const modalEl = document.getElementById('vtualConfirmModal');
    if (!modalEl) {
        return;
    }

    const titleEl = modalEl.querySelector('[data-confirm-title]');
    const bodyEl = modalEl.querySelector('[data-confirm-body]');
    const submitBtn = modalEl.querySelector('[data-confirm-submit]');
    const submitLabel = modalEl.querySelector('[data-confirm-submit-label]');

    let activeForm = null;

    modalEl.addEventListener('show.bs.modal', function (event) {
        const trigger = event.relatedTarget;
        if (!trigger) {
            return;
        }

        const title = trigger.dataset.confirmTitle || '';
        const body = trigger.dataset.confirmBody || '';
        const label = trigger.dataset.confirmSubmitLabel || '';
        const submitClass = trigger.dataset.confirmSubmitClass || 'btn-danger';

        activeForm = trigger.closest('form[data-ajax-form]');

        if (title && titleEl) {
            titleEl.textContent = title;
        }
        if (body && bodyEl) {
            bodyEl.textContent = body;
        }
        if (label && submitLabel) {
            submitLabel.textContent = label;
        }

        submitBtn.className = 'btn ' + submitClass;
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        activeForm = null;
    });

    submitBtn.addEventListener('click', function () {
        if (!activeForm) {
            return;
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        activeForm.requestSubmit();
    });
})();
