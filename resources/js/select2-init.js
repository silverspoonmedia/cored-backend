/**
 * Select2 initializer for Blade forms (Bootstrap 5 theme).
 *
 * Owns the Select2 factory attach so init never races app.js body code.
 * Loaded via app.js after jQuery is on window. Watches the DOM for selects
 * marked [data-monolith-select2="1"] and (re)initialises them.
 * Re-exported on window.MonolithSelect2 for Blade <script> tags that call
 * initAll() after dynamic content injection.
 *
 * AJAX lookups use Select2's default jQuery.ajax transport (url + data +
 * processResults). Do NOT add a custom fetch transport or return an abortable
 * controller — Select2 aborts prior jqXHR via status 0 (silent); a custom
 * abortable return can cancel before the network and surface errorLoading.
 */
import $ from 'jquery';
import select2 from 'select2';
import { processLookupResults } from './select2-lookup.js';

select2($);

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

function destroyIfNeeded($element) {
    if ($element.hasClass('select2-hidden-accessible')) {
        $element.off('.select2');
        $element.off('.select2ScrollLock');
        $element.select2('destroy');
    }
}

function initElement(element) {
    if (element.dataset.monolithSelect2 !== '1') {
        return;
    }

    const $element = $(element);
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
            url: ajaxUrl,
            dataType: 'json',
            delay: 300,
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrfToken(),
            },
            data(params) {
                const query = {
                    ...staticParams,
                    q: params.term || '',
                    page: params.page || 1,
                    // Intentionally fixed at 10 — parity with SPA LOOKUP_PER_PAGE and config/lookup.php default.
                    per_page: 10,
                };

                return Object.fromEntries(
                    Object.entries(query).filter(([, value]) => value !== null && value !== undefined && value !== ''),
                );
            },
            processResults(data) {
                return processLookupResults(data);
            },
        };
    }

    $element.select2(config);

    // Select2 focuses selection/search without preventScroll — browser scrolls
    // the clipped original <select> into view and the page jumps. Lock Y around open/close.
    let lockedScrollY = 0;
    $element
        .on('select2:opening.select2ScrollLock select2:closing.select2ScrollLock', () => {
            lockedScrollY = window.scrollY;
        })
        .on('select2:open.select2ScrollLock select2:close.select2ScrollLock', () => {
            window.scrollTo(window.scrollX, lockedScrollY);
            document
                .querySelector('.select2-container--open .select2-search__field')
                ?.focus({ preventScroll: true });
        });

    if (element.dataset.autosubmit === '1' && element.form) {
        $element.on('change.select2Autosubmit', () => {
            element.form.requestSubmit();
        });
    }
}

function initAll(root = document) {
    root.querySelectorAll('select[data-monolith-select2="1"]').forEach((element) => initElement(element));
}

// Defer init so the Select2 factory attach above always wins, even when this
// module evaluates after DOMContentLoaded (deferred ES modules at end of body).
queueMicrotask(() => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => initAll());
    } else {
        initAll();
    }
});

window.MonolithSelect2 = {
    initAll,
    initElement,
};
