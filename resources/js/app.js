/**
 * Global application entry — loaded on every page via @vite in assets-scripts.
 *
 * Boots the shared vendor JS stack (jQuery, DataTables, Select2, Bootstrap 5,
 * AdminLTE 4, OverlayScrollbars) once, then initialises infra modules (API
 * response handler, AJAX form delegation, Select2 auto-init). Per-page modules
 * are loaded separately via @vite('resources/js/...') in the views that need them.
 *
 * Vendor CSS is loaded separately via resources/css/vendor.css in <head> to
 * avoid FOUC.
 *
 * Script order contract (docs/web/blade-admin.md):
 *   app.js (vendor + MonolithApi + ajax-form + Select2) → page scripts
 */

// jQuery + DataTables core binding (sets window.jQuery, calls DataTable.use).
import './infra/jquery-init.js';

// DataTables Bootstrap 5 styling integration.
import 'datatables.net-bs5';

// Select2 + Bootstrap 5 theme.
import 'select2';

// Bootstrap 5 (JS bundle includes Popper).
import 'bootstrap';

// AdminLTE 4.
import 'admin-lte';

// OverlayScrollbars (sidebar).
import { OverlayScrollbars } from 'overlayscrollbars';
window.OverlayScrollbars = OverlayScrollbars;

/**
 * Form confirm delegation — replaces inline onsubmit="return confirm(...)".
 * Forms marked with [data-swal-confirm] show a SweetAlert2 confirmation
 * before submit. The attribute value is the confirmation message.
 */
document.addEventListener('submit', async (event) => {
    const form = event.target;
    const message = form.dataset.swalConfirm;

    if (!message) {
        return;
    }

    event.preventDefault();

    const confirmed = window.MonolithSwal
        ? await window.MonolithSwal.confirm(message, '', { dangerMode: true })
        : window.confirm(message);

    if (confirmed) {
        form.removeAttribute('data-swal-confirm');
        form.submit();
    }
});

// Shared infrastructure.
import './infra/api-response-handler.js';
import './infra/ajax-form.js';
import './infra/sweetalert.js';
import './infra/theme-scroll.js';
import './select2-init.js';
