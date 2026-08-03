/**
 * jQuery + DataTables window binding — must execute BEFORE DataTables
 * consumer code.
 *
 * DataTables 3 does NOT auto-detect window.jQuery for jQuerySetup().
 * We must explicitly call DataTable.use(jQuery) to attach .fn.DataTable.
 *
 * ES module imports are hoisted, so this is split into its own module
 * to guarantee execution order before datatables.net body runs.
 */
import jQuery from 'jquery';
import DataTable from 'datatables.net';

window.jQuery = jQuery;
window.$ = jQuery;
DataTable.use(jQuery);
