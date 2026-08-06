import '../infra/jquery-init.js';
import 'datatables.net-bs5';
import $ from 'jquery';

(function () {
    'use strict';

    const tableEl = document.getElementById('vtual-registry-pending-table');
    if (!tableEl || typeof $.fn?.DataTable !== 'function') {
        return;
    }

    const configEl = document.querySelector('[data-vtual-registry-dt]');
    const labels = configEl ? JSON.parse(configEl.textContent || '{}') : {};

    $('#vtual-registry-pending-table').DataTable({
        order: [[4, 'asc']],
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        columnDefs: [
            { orderable: false, targets: 5 },
        ],
        language: {
            emptyTable: labels.emptyTable || '',
            zeroRecords: labels.zeroRecords || '',
            search: labels.search || '',
            searchPlaceholder: labels.searchPlaceholder || '',
        },
    });
})();
