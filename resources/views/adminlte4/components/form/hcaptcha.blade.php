@if (config('hcaptcha.enabled') && config('hcaptcha.sitekey'))
    <div
        class="hcaptcha-field mb-3{{ $errors->has('h-captcha-response') ? ' hcaptcha-field--invalid' : '' }}"
        data-form-field="h-captcha-response"
        data-field-invalid-class="hcaptcha-field--invalid"
    >
        <div class="hcaptcha-field__widget" data-hcaptcha-fit>
            <div
                class="h-captcha"
                data-sitekey="{{ config('hcaptcha.sitekey') }}"
                data-size="normal"
            ></div>
        </div>
        <x-adminlte4::form.error
            name="h-captcha-response"
            class="d-block text-center hcaptcha-field__error"
        />
    </div>

    @once
        <script src="https://js.hcaptcha.com/1/api.js?onload=onHcaptchaApiLoad" async defer></script>
        <style>
            .hcaptcha-field__widget {
                width: 100%;
                overflow: hidden;
                border: 1px solid var(--bs-border-color);
                border-radius: var(--bs-border-radius, 0.375rem);
                background-color: var(--bs-body-bg);
                box-sizing: border-box;
            }

            .hcaptcha-field__widget .h-captcha {
                display: block;
                margin: 0;
                line-height: 0;
                transform-origin: top left;
                will-change: transform;
            }

            .hcaptcha-field__error:empty {
                display: none !important;
            }

            .hcaptcha-field .hcaptcha-field__error {
                margin-top: 0.375rem;
            }

            .hcaptcha-field--invalid .hcaptcha-field__widget {
                border-color: var(--bs-form-invalid-border-color, #dc3545);
                box-shadow: 0 0 0 0.25rem rgba(var(--bs-danger-rgb), 0.25);
            }
        </style>
        <script>
            (function () {
                'use strict';

                let resizeTimer = null;

                function fitHcaptchaField(container) {
                    const captcha = container.querySelector('.h-captcha');
                    const iframe = captcha?.querySelector('iframe');

                    if (!captcha || !iframe) {
                        return false;
                    }

                    captcha.style.transform = '';
                    captcha.style.width = '';
                    captcha.style.height = '';
                    container.style.height = '';

                    const naturalWidth = iframe.offsetWidth;
                    const naturalHeight = iframe.offsetHeight;

                    if (naturalWidth <= 0 || naturalHeight <= 0) {
                        return false;
                    }

                    const targetWidth = container.clientWidth;
                    const scale = targetWidth / naturalWidth;

                    captcha.style.width = naturalWidth + 'px';
                    captcha.style.height = naturalHeight + 'px';
                    captcha.style.transform = 'scale(' + scale + ')';
                    container.style.height = Math.ceil(naturalHeight * scale) + 'px';

                    return true;
                }

                function fitAllHcaptchaFields() {
                    let fitted = true;

                    document.querySelectorAll('[data-hcaptcha-fit]').forEach(function (container) {
                        if (!fitHcaptchaField(container)) {
                            fitted = false;
                        }
                    });

                    return fitted;
                }

                function scheduleHcaptchaFit(retries) {
                    if (fitAllHcaptchaFields() || retries <= 0) {
                        return;
                    }

                    window.setTimeout(function () {
                        scheduleHcaptchaFit(retries - 1);
                    }, 100);
                }

                function resetHcaptchaWidgets() {
                    if (typeof window.hcaptcha === 'undefined') {
                        return;
                    }

                    window.hcaptcha.reset();

                    if (typeof window.fitAllHcaptchaFields === 'function') {
                        window.setTimeout(window.fitAllHcaptchaFields, 150);
                    }
                }

                window.fitAllHcaptchaFields = fitAllHcaptchaFields;

                window.onHcaptchaApiLoad = function () {
                    scheduleHcaptchaFit(40);
                };

                document.addEventListener('ajax-form:invalid', resetHcaptchaWidgets);

                window.addEventListener('resize', function () {
                    window.clearTimeout(resizeTimer);
                    resizeTimer = window.setTimeout(fitAllHcaptchaFields, 150);
                });

                if (document.readyState === 'complete') {
                    scheduleHcaptchaFit(40);
                } else {
                    window.addEventListener('load', function () {
                        scheduleHcaptchaFit(40);
                    });
                }
            })();
        </script>
    @endonce
@endif
