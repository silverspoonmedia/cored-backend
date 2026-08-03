import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/vendor.css',
                'resources/js/app.js',
                'resources/css/monolith-overrides.css',
                'resources/css/payments/channels-admin.css',
                'resources/css/inference/rate-plans-admin.css',
                'resources/css/payments/order-invoice-show.css',
                'resources/css/file-hosting/file-hosting.css',
                'resources/js/datatable-init.js',
                'resources/js/navigation/sidebar-search.js',
                'resources/js/notifications/bell.js',
                'resources/js/file-hosting/direct-upload.js',
                'resources/js/file-hosting/file-manager.js',
                'resources/js/file-hosting/microstock-usage.js',
                'resources/js/file-hosting/public-app.js',
                'resources/js/payments/checkout.js',
                'resources/js/payments/channels-admin.js',
                'resources/js/inference/rate-plans-admin.js',
                'resources/js/inference/usage-events.js',
                'resources/js/payments/income.js',
                'resources/js/services/purchase-quantity.js',
                'resources/js/domain-access/toggle.js',
                'resources/js/vtual/confirm-modal.js',
                'resources/js/vtual/credential-form.js',
            ],
            refresh: true,
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        cssMinify: 'esbuild',
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('jquery')) return 'vendor-jquery';
                        if (id.includes('datatables.net')) return 'vendor-datatables';
                        if (id.includes('select2')) return 'vendor-select2';
                        if (id.includes('bootstrap')) return 'vendor-bootstrap';
                        if (id.includes('admin-lte')) return 'vendor-adminlte';
                        if (id.includes('overlayscrollbars')) return 'vendor-overlayscrollbars';
                    }
                },
            },
        },
    },
});
