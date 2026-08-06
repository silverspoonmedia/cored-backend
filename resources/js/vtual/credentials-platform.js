import '../infra/jquery-init.js';
import 'datatables.net-bs5';
import $ from 'jquery';

(function () {
    'use strict';

    const tableEl = document.getElementById('vtual-credentials-table');
    if (!tableEl || typeof $.fn?.DataTable !== 'function') {
        return;
    }

    const wrapperEl = document.getElementById('vtual-credentials-table-wrapper');
    const configEl = document.querySelector('[data-vtual-credentials-dt]');
    const labels = configEl ? JSON.parse(configEl.textContent || '{}') : {};

    let statusLabels = {};
    try {
        statusLabels = JSON.parse(wrapperEl?.dataset.statusLabels || '{}');
    } catch (e) {
        statusLabels = {};
    }

    const dataTable = $('#vtual-credentials-table').DataTable({
        order: [[3, 'asc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50],
        columnDefs: [
            { orderable: false, targets: 7 },
        ],
        language: {
            emptyTable: labels.emptyTable || '',
            zeroRecords: labels.zeroRecords || '',
            search: labels.search || '',
            searchPlaceholder: labels.searchPlaceholder || '',
        },
    });

    function badgeClass(status) {
        if (status === 'active') {
            return 'text-bg-success';
        }
        if (status === 'pending') {
            return 'text-bg-warning';
        }
        if (status === 'revoked') {
            return 'text-bg-dark';
        }
        if (status === 'archived') {
            return 'text-bg-secondary';
        }

        return 'text-bg-light border';
    }

    function statusLabel(status) {
        return statusLabels[status] || status;
    }

    function escapeSelector(id) {
        if (typeof CSS !== 'undefined' && typeof CSS.escape === 'function') {
            return CSS.escape(String(id));
        }

        return String(id).replace(/(["\\])/g, '\\$1');
    }

    function extractCredential(detail) {
        const envelope = detail?.data;
        if (!envelope || typeof envelope !== 'object') {
            return null;
        }

        const inner = envelope.data;
        if (!inner || typeof inner !== 'object') {
            return null;
        }

        if (inner.credential && inner.credential.id) {
            return inner.credential;
        }

        if (inner.id && inner.status) {
            return inner;
        }

        return null;
    }

    function formatVerifiedAt(credential) {
        return credential.verified_at_formatted
            || credential.verified_at
            || '—';
    }

    function formatRuntimeExpires(credential) {
        if (credential.has_runtime && (credential.runtime_expires_at_formatted || credential.runtime_expires_at)) {
            return credential.runtime_expires_at_formatted || credential.runtime_expires_at;
        }

        return '—';
    }

    function updateStatusCell(cell, status) {
        if (!cell) {
            return;
        }

        const badge = document.createElement('span');
        badge.className = 'badge ' + badgeClass(status);
        badge.textContent = statusLabel(status);
        cell.replaceChildren(badge);
    }

    function updateActionForms(row, status) {
        const testWrap = row.querySelector('[data-credential-test-form]');
        if (testWrap) {
            const showTest = status === 'pending' || status === 'active';
            testWrap.classList.toggle('d-none', !showTest);
        }

        const revokeForm = row.querySelector('[data-credential-revoke-form]');
        if (revokeForm) {
            revokeForm.classList.toggle('d-none', status !== 'active');
        }

        const deleteForm = row.querySelector('[data-credential-delete-form]');
        if (deleteForm) {
            deleteForm.classList.toggle('d-none', status === 'active');
        }
    }

    function removeCredentialRow(credentialId) {
        const rowEl = tableEl.querySelector(
            'tr[data-credential-id="' + escapeSelector(credentialId) + '"]',
        );
        if (!rowEl) {
            return;
        }

        const dtRow = dataTable.row(rowEl);
        if (dtRow.length) {
            dtRow.remove().draw(false);
        } else {
            rowEl.remove();
        }
    }

    function updateCredentialRow(credential) {
        if (!credential?.id) {
            return;
        }

        const rowEl = tableEl.querySelector(
            'tr[data-credential-id="' + escapeSelector(credential.id) + '"]',
        );
        if (!rowEl) {
            return;
        }

        const status = credential.status || '';
        updateStatusCell(rowEl.querySelector('[data-credential-field="status"]'), status);

        const verifiedCell = rowEl.querySelector('[data-credential-field="verified_at"]');
        if (verifiedCell) {
            verifiedCell.textContent = formatVerifiedAt(credential);
        }

        const runtimeCell = rowEl.querySelector('[data-credential-field="runtime_expires"]');
        if (runtimeCell) {
            runtimeCell.textContent = formatRuntimeExpires(credential);
        }

        updateActionForms(rowEl, status);

        const dtRow = dataTable.row(rowEl);
        if (dtRow.length) {
            dtRow.invalidate().draw(false);
        }
    }

    function isCredentialRowForm(form) {
        return form?.hasAttribute('data-credential-delete-form')
            || form?.hasAttribute('data-credential-revoke-form')
            || (form?.action?.includes('/test') ?? false);
    }

    function credentialIdFromForm(form) {
        const row = form.closest('tr[data-credential-id]');
        if (row?.dataset.credentialId) {
            return row.dataset.credentialId;
        }

        const match = form.action.match(/credentials\/([^/]+)\/(?:test|revoke)/);

        return match ? match[1] : null;
    }

    document.addEventListener('ajax-form:success', (event) => {
        const form = event.detail?.form;
        if (!form || !isCredentialRowForm(form)) {
            return;
        }

        if (form.hasAttribute('data-credential-delete-form')) {
            const credentialId = credentialIdFromForm(form);
            if (credentialId) {
                removeCredentialRow(credentialId);
            }

            return;
        }

        const credential = extractCredential(event.detail);
        if (credential) {
            updateCredentialRow(credential);
        }
    });

    document.addEventListener('ajax-form:invalid', (event) => {
        const form = event.detail?.form;
        if (!form?.action?.includes('/test')) {
            return;
        }

        const credential = extractCredential(event.detail);
        if (credential) {
            updateCredentialRow(credential);
        }
    });
})();
