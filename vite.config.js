import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/monolith-overrides.css',
                'resources/css/payments/channels-admin.css',
                'resources/css/payments/order-invoice-show.css',
                'resources/css/file-hosting/file-hosting.css',
                'resources/js/notifications/bell.js',
                'resources/js/file-hosting/direct-upload.js',
                'resources/js/file-hosting/file-manager.js',
                'resources/js/payments/checkout.js',
                'resources/js/payments/channels-admin.js',
                'resources/js/select2-init.js',
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
});
