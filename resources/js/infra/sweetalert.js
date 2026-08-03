/**
 * SweetAlert2 wrapper — centralised toast + confirm for the Monolith admin.
 *
 * All window.alert / window.confirm calls route through here so the UI stays
 * consistent (themed, accessible, async). Imported by app.js so Swal + the
 * global helpers are available on every page.
 */
import Swal from 'sweetalert2';

// Read i18n labels from the Blade-injected JSON blob.
const labelBlob = document.querySelector('script[data-monolith-swal-labels]');
const labelData = (() => {
    try {
        return JSON.parse(labelBlob?.textContent || '{}');
    } catch {
        return {};
    }
})();
window.MonolithSwal = { labels: labelData };

const toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    didOpen: (el) => {
        el.addEventListener('mouseenter', Swal.stopTimer);
        el.addEventListener('mouseleave', Swal.resumeTimer);
    },
});

/**
 * Show a success/info/error/warning toast.
 * @param {string} message
 * @param {'success'|'error'|'info'|'warning'} [type='info']
 */
function monolithToast(message, type = 'info') {
    toast.fire({ icon: type, title: message });
}

/**
 * Show an alert (replaces window.alert). Uses a themed modal with a single OK.
 * @param {string} message
 * @param {string} [title]
 * @returns {Promise<void>}
 */
function monolithAlert(message, title = '') {
    return Swal.fire({
        title: title || undefined,
        text: message,
        icon: 'info',
        confirmButtonText: window.MonolithSwal?.labels?.ok || 'OK',
    });
}

/**
 * Show a confirmation dialog (replaces window.confirm).
 * @param {string} message
 * @param {string} [title]
 * @param {object} [opts] — { confirmButtonText, cancelButtonText, dangerMode }
 * @returns {Promise<boolean>} true if confirmed
 */
function monolithConfirm(message, title = '', opts = {}) {
    const labels = window.MonolithSwal?.labels || {};
    return Swal.fire({
        title: title || undefined,
        text: message,
        icon: opts.dangerMode ? 'warning' : 'question',
        showCancelButton: true,
        confirmButtonText: opts.confirmButtonText || labels.confirm || 'Confirm',
        cancelButtonText: labels.cancel || 'Cancel',
        reverseButtons: true,
    }).then((result) => result.isConfirmed);
}

window.MonolithSwal = {
    Swal,
    toast,
    alert: monolithAlert,
    confirm: monolithConfirm,
    toastMessage: monolithToast,
};
