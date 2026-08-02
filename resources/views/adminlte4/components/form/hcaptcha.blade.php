@if (setting_config('hcaptcha.enabled') && config('hcaptcha.sitekey'))
    <div
        class="hcaptcha-field{{ $errors->has('h-captcha-response') ? ' hcaptcha-field--invalid mb-3' : '' }}"
        data-form-field="h-captcha-response"
        data-field-invalid-class="hcaptcha-field--invalid"
    >
        <div
            class="visually-hidden"
            data-hcaptcha-widget
            data-sitekey="{{ config('hcaptcha.sitekey') }}"
        ></div>
        <x-adminlte4::form.error
            name="h-captcha-response"
            class="d-block text-center hcaptcha-field__error mb-3"
        />
    </div>

    @once
        <script src="https://js.hcaptcha.com/1/api.js?onload=onHcaptchaApiLoad&amp;render=explicit" async defer></script>
        <style>
            .hcaptcha-field__error:empty {
                display: none !important;
            }

            .hcaptcha-field--invalid .hcaptcha-field__error:not(:empty) {
                margin-top: 0.375rem;
            }
        </style>
        <script>
            (function () {
                'use strict';

                const widgets = new WeakMap();
                const pending = new WeakMap();

                function findForm(container) {
                    return container.closest('[data-ajax-form]');
                }

                function findTokenInput(form) {
                    return form?.querySelector('[name="h-captcha-response"]') ?? null;
                }

                function renderWidgets() {
                    if (typeof window.hcaptcha === 'undefined') {
                        return;
                    }

                    document.querySelectorAll('[data-hcaptcha-widget]').forEach(function (container) {
                        if (container.dataset.hcaptchaRendered === '1') {
                            return;
                        }

                        const form = findForm(container);
                        const widgetId = window.hcaptcha.render(container, {
                            sitekey: container.dataset.sitekey,
                            size: 'invisible',
                            callback: function () {
                                const formRef = findForm(container);
                                const pendingExec = formRef ? pending.get(formRef) : null;

                                if (pendingExec) {
                                    pendingExec.resolve();
                                    pending.delete(formRef);
                                }
                            },
                            'error-callback': function () {
                                const formRef = findForm(container);
                                const pendingExec = formRef ? pending.get(formRef) : null;

                                if (pendingExec) {
                                    pendingExec.reject(new Error('hCaptcha challenge failed'));
                                    pending.delete(formRef);
                                }
                            },
                        });

                        container.dataset.hcaptchaRendered = '1';

                        if (form) {
                            widgets.set(form, widgetId);
                        }
                    });
                }

                function resetForm(form) {
                    const widgetId = widgets.get(form);

                    if (widgetId !== undefined && typeof window.hcaptcha !== 'undefined') {
                        window.hcaptcha.reset(widgetId);
                    }

                    const tokenInput = findTokenInput(form);

                    if (tokenInput) {
                        tokenInput.value = '';
                    }
                }

                window.MonolithHcaptcha = {
                    execute: function (form) {
                        return new Promise(function (resolve, reject) {
                            const widgetId = widgets.get(form);
                            const tokenInput = findTokenInput(form);

                            if (tokenInput?.value) {
                                resolve(tokenInput.value);

                                return;
                            }

                            if (widgetId === undefined || typeof window.hcaptcha === 'undefined') {
                                reject(new Error('hCaptcha not ready'));

                                return;
                            }

                            pending.set(form, { resolve: resolve, reject: reject });
                            window.hcaptcha.execute(widgetId);
                        });
                    },
                    resetForm: resetForm,
                    resetAll: function () {
                        document.querySelectorAll('[data-ajax-form]').forEach(function (form) {
                            if (form.querySelector('[data-hcaptcha-widget]')) {
                                resetForm(form);
                            }
                        });
                    },
                };

                window.onHcaptchaApiLoad = function () {
                    renderWidgets();
                };

                document.addEventListener('ajax-form:invalid', function () {
                    window.MonolithHcaptcha.resetAll();
                });
            })();
        </script>
    @endonce
@endif
