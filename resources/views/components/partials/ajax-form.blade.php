<script>
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

        function showFormAlert(form, message, type) {
            const alert = form.querySelector('[data-form-alert]');
            if (!alert) {
                return;
            }

            alert.className = 'alert alert-' + type + ' mb-3';
            alert.textContent = message;
            alert.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function applyFieldErrors(form, errors) {
            let firstInvalid = null;

            Object.keys(errors).forEach(function (field) {
                const messages = errors[field];
                const message = Array.isArray(messages) ? messages[0] : messages;
                const control = findControl(form, field);
                const errorNode = findErrorNode(form, field);

                if (control) {
                    control.classList.add('is-invalid');
                    if (!firstInvalid) {
                        firstInvalid = control;
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
            const button = form.querySelector('[type="submit"]');
            if (!button) {
                return;
            }

            button.disabled = submitting;
            button.setAttribute('aria-busy', submitting ? 'true' : 'false');

            const spinner = button.querySelector('[data-form-submit-spinner]');
            if (spinner) {
                spinner.classList.toggle('d-none', !submitting);
            }
        }

        async function handleAjaxSubmit(event) {
            const form = event.target.closest('[data-ajax-form]');
            if (!form) {
                return;
            }

            event.preventDefault();
            clearFormFeedback(form);
            setSubmitting(form, true);

            const formData = new FormData(form);

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

                if (response.ok) {
                    if (data.message) {
                        showFormAlert(form, data.message, 'success');
                    }

                    if (data.redirect) {
                        window.location.assign(data.redirect);
                        return;
                    }

                    return;
                }

                if (response.status === 422 && data.errors) {
                    applyFieldErrors(form, data.errors);
                    if (data.message) {
                        showFormAlert(form, data.message, 'danger');
                    }
                    return;
                }

                if (data.redirect) {
                    window.location.assign(data.redirect);
                    return;
                }

                showFormAlert(form, data.message || 'Permintaan gagal diproses.', 'danger');
            } catch (error) {
                showFormAlert(form, 'Tidak dapat menghubungi server. Periksa koneksi Anda.', 'danger');
                console.error('AJAX form submit failed', error);
            } finally {
                setSubmitting(form, false);
            }
        }

        document.addEventListener('submit', handleAjaxSubmit);
    })();
</script>
