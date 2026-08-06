/**
 * Delegated AJAX form submit handler.
 *
 * Listens for `submit` on any <form data-ajax-form>, sends via fetch, and
 * routes the response through window.MonolithApi.handleResponse(). Handles
 * field-error display (data-form-field / data-field-error), submit spinner
 * toggling (data-form-submit-spinner), and hCaptcha execution.
 *
 * Contract: docs/web/blade-admin.md — AJAX error table.
 */
(function () {
    'use strict';

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';

    function findControl(form, field) {
        const wrapper = form.querySelector('[data-form-field="' + CSS.escape(field) + '"]');
        if (!wrapper) {
            return null;
        }

        return wrapper.querySelector('input, select, textarea');
    }

    function findErrorNode(form, field) {
        return form.querySelector('[data-field-error="' + CSS.escape(field) + '"]');
    }

    function clearFormFeedback(form) {
        form.querySelectorAll('[data-form-field]').forEach(function (wrapper) {
            if (wrapper.dataset.fieldInvalidClass) {
                wrapper.classList.remove(wrapper.dataset.fieldInvalidClass);
            }

            wrapper.querySelectorAll('.form-control, .form-select').forEach(function (control) {
                control.classList.remove('is-invalid');
            });
            wrapper.querySelectorAll('.form-check-input').forEach(function (control) {
                control.classList.remove('is-invalid');
            });
        });

        form.querySelectorAll('[data-field-error]').forEach(function (node) {
            node.textContent = '';
            node.style.display = '';
        });

        const alert = form.querySelector('[data-form-alert]');
        if (alert) {
            alert.className = 'alert d-none mb-3';
            alert.textContent = '';
        }
    }

    function applyFieldErrors(form, errors) {
        let firstInvalid = null;

        Object.keys(errors).forEach(function (field) {
            const fieldMessages = errors[field];
            const message = Array.isArray(fieldMessages) ? fieldMessages[0] : fieldMessages;
            const wrapper = form.querySelector('[data-form-field="' + CSS.escape(field) + '"]');
            const control = findControl(form, field);
            const errorNode = findErrorNode(form, field);

            if (control) {
                control.classList.add('is-invalid');
                if (!firstInvalid) {
                    firstInvalid = control;
                }
            } else if (wrapper?.dataset.fieldInvalidClass) {
                wrapper.classList.add(wrapper.dataset.fieldInvalidClass);
                if (!firstInvalid) {
                    firstInvalid = wrapper;
                }
            }

            if (errorNode) {
                errorNode.textContent = message;
                errorNode.style.display = 'block';
            }
        });

        if (firstInvalid) {
            firstInvalid.focus({ preventScroll: true });
            firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    function setSubmitting(form, submitting) {
        form.querySelectorAll('button').forEach(function (button) {
            button.disabled = submitting;
            if (submitting) {
                button.setAttribute('aria-busy', 'true');
            } else {
                button.removeAttribute('aria-busy');
            }
        });

        const submitButton = form.querySelector('[type="submit"]');
        if (!submitButton) {
            return;
        }

        const spinner = submitButton.querySelector('[data-form-submit-spinner]');
        if (spinner) {
            spinner.classList.toggle('d-none', !submitting);
        }
    }

    async function handleAjaxSubmit(event) {
        const form = event.target.closest('[data-ajax-form]');
        if (!form) {
            return;
        }

        const hcaptchaWidget = form.querySelector('[data-hcaptcha-widget]');
        const captchaToken = form.querySelector('[name="h-captcha-response"]')?.value ?? '';

        if (hcaptchaWidget && !captchaToken && window.MonolithHcaptcha?.execute) {
            event.preventDefault();

            setSubmitting(form, true);

            try {
                await window.MonolithHcaptcha.execute(form);
                form.requestSubmit();
            } catch (error) {
                setSubmitting(form, false);
            }

            return;
        }

        event.preventDefault();
        clearFormFeedback(form);
        setSubmitting(form, true);

        const formData = new FormData(form);
        const alertEl = form.querySelector('[data-form-alert]');

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                credentials: 'same-origin',
            });

            let data = {};
            try {
                data = await response.json();
            } catch (parseError) {
                data = {};
            }

            window.MonolithApi.handleResponse(response, data, {
                alertEl: alertEl,
                form: form,
                applyFieldErrors: applyFieldErrors,
            });
        } catch (error) {
            window.MonolithApi.handleNetworkError(alertEl);
            console.error('AJAX form submit failed', error);
        } finally {
            setSubmitting(form, false);
            document.dispatchEvent(new CustomEvent('ajax-form:complete', {
                detail: { form: form },
            }));
        }
    }

    document.addEventListener('submit', handleAjaxSubmit);
})();
