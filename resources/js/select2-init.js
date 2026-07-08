/**
 * Select2 initializer for Blade forms (Bootstrap 5 theme).
 */
(function () {
    'use strict';

    const generations = new WeakMap();

    function generationFor(element) {
        if (!generations.has(element)) {
            generations.set(element, 0);
        }

        return generations.get(element);
    }

    function bumpGeneration(element) {
        generations.set(element, generationFor(element) + 1);

        return generations.get(element);
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
    }

    function destroyIfNeeded($element) {
        if ($element.hasClass('select2-hidden-accessible')) {
            $element.off('.select2');
            $element.select2('destroy');
        }
    }

    function initElement(element) {
        // Never use data-select2 — jQuery .data('select2') collides with Select2's instance key.
        if (element.dataset.monolithSelect2 !== '1' || typeof window.jQuery === 'undefined' || !window.jQuery.fn.select2) {
            return;
        }

        const $element = window.jQuery(element);
        destroyIfNeeded($element);

        const config = {
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: !element.multiple,
            placeholder: element.dataset.placeholder || undefined,
            minimumInputLength: Number.parseInt(element.dataset.minimumInputLength || '0', 10),
        };

        const ajaxUrl = element.dataset.ajaxUrl;
        if (ajaxUrl) {
            let staticParams = {};

            try {
                staticParams = JSON.parse(element.dataset.ajaxParams || '{}');
            } catch {
                staticParams = {};
            }

            config.ajax = {
                delay: 300,
                transport(params, success, failure) {
                    const generation = bumpGeneration(element);
                    const requestData = params.data || {};
                    const query = new URLSearchParams();

                    Object.entries(staticParams).forEach(([key, value]) => {
                        if (value !== null && value !== undefined && value !== '') {
                            query.append(key, String(value));
                        }
                    });

                    if (requestData.q) {
                        query.append('q', String(requestData.q));
                    }

                    query.append('page', String(requestData.page || 1));
                    query.append('per_page', '10');

                    fetch(`${ajaxUrl}?${query.toString()}`, {
                        headers: {
                            Accept: 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrfToken(),
                        },
                        credentials: 'same-origin',
                    })
                        .then((response) => response.json())
                        .then((body) => {
                            if (generationFor(element) !== generation) {
                                return;
                            }

                            success(body.data ?? body);
                        })
                        .catch(() => {
                            if (generationFor(element) !== generation) {
                                return;
                            }

                            failure();
                        });
                },
                data(params) {
                    return {
                        q: params.term || '',
                        page: params.page || 1,
                    };
                },
                processResults(data) {
                    return {
                        results: data.results || [],
                        pagination: data.pagination || { more: false },
                    };
                },
            };
        }

        $element.select2(config);

        if (element.dataset.autosubmit === '1' && element.form) {
            $element.on('change.select2Autosubmit', () => {
                element.form.requestSubmit();
            });
        }
    }

    function initAll(root = document) {
        root.querySelectorAll('select[data-monolith-select2="1"]').forEach((element) => initElement(element));
    }

    function boot(attempt = 0) {
        if (typeof window.jQuery === 'undefined' || !window.jQuery.fn.select2) {
            if (attempt < 40) {
                window.setTimeout(() => boot(attempt + 1), 50);
            }

            return;
        }

        initAll();
    }

    // Classic script (not type="module"): run after preceding jQuery/Select2 CDN tags.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => boot());
    } else {
        boot();
    }

    window.MonolithSelect2 = {
        initAll,
        initElement,
    };
})();
