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
    let activeTrigger = null;
    let lockedForm = null;
    let lockedTrigger = null;

    function releaseConfirmLock() {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.removeAttribute('aria-busy');
        }
        if (lockedTrigger) {
            lockedTrigger.disabled = false;
            lockedTrigger.removeAttribute('aria-busy');
        }
        lockedForm = null;
        lockedTrigger = null;
    }

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
        activeTrigger = trigger;

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
        activeTrigger = null;
    });

    submitBtn.addEventListener('click', function () {
        if (!activeForm) {
            return;
        }

        lockedForm = activeForm;
        lockedTrigger = activeTrigger;

        submitBtn.disabled = true;
        submitBtn.setAttribute('aria-busy', 'true');
        if (lockedTrigger) {
            lockedTrigger.disabled = true;
            lockedTrigger.setAttribute('aria-busy', 'true');
        }

        window.bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        lockedForm.requestSubmit();
    });

    function maybeReleaseLock(event) {
        if (event.detail?.form === lockedForm) {
            releaseConfirmLock();
        }
    }

    document.addEventListener('ajax-form:success', maybeReleaseLock);
    document.addEventListener('ajax-form:invalid', maybeReleaseLock);
    document.addEventListener('ajax-form:complete', maybeReleaseLock);
})();
