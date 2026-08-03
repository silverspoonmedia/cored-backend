/**
 * Shared DataTables factory.
 *
 * Wraps the boilerplate (processing, serverSide, ajax error handler, language)
 * so each index view only declares its columns + ajax URL. Returns the
 * DataTables API instance for external reload (e.g. realtime live-reload).
 *
 * jQuery + DataTables core binding lives in infra/jquery-init.js — it must run
 * before this module body so $.fn.DataTable is attached.
 */

import './infra/jquery-init.js';
import 'datatables.net-bs5';
import $ from 'jquery';

/**
 * Initialise a server-side DataTable after DOM ready.
 *
 * @param {object} options
 * @param {string} options.tableId    - <table> element id (without #).
 * @param {string} options.ajaxUrl    - server-side data URL.
 * @param {Array}  options.columns    - DataTables column definitions.
 * @param {Array}  [options.order]    - default sort order, e.g. [[0, 'asc']].
 * @param {object} [options.language] - { emptyTable, zeroRecords } translations.
 * @param {boolean} [options.exposeForReload] - store instance in window.dataTables[tableId].
 * @param {function} [options.onReady] - callback(table) after init, for page-specific logic.
 * @returns {object|null} DataTables API instance, or null if DataTables missing.
 */
export function initDataTable(options) {
    const boot = () => {
        if (typeof $.fn?.DataTable !== 'function') {
            console.error('initDataTable: DataTables not loaded', { tableId: options.tableId });

            return null;
        }

        const table = $('#' + options.tableId).DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: options.ajaxUrl,
                error: function (xhr) {
                    if (window.MonolithApi?.datatableAjaxError) {
                        window.MonolithApi.datatableAjaxError(xhr);
                    }
                },
            },
            order: options.order ?? [[0, 'asc']],
            language: {
                emptyTable: options.language?.emptyTable ?? '',
                zeroRecords: options.language?.zeroRecords ?? '',
            },
            columns: options.columns,
        });

        if (options.exposeForReload) {
            window.dataTables = window.dataTables || {};
            window.dataTables[options.tableId] = table;
        }

        if (typeof options.onReady === 'function') {
            options.onReady(table);
        }

        return table;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        return boot();
    }

    return null;
}

// Expose for Blade inline scripts that load this via @vite (not ESM import).
window.initDataTable = initDataTable;
