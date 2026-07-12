import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/payments/channels-admin.css',
                'resources/css/payments/order-invoice-show.css',
                'resources/js/app.js',
                'resources/js/notifications/bell.js',
                'resources/js/orders/direct-upload.js',
                'resources/js/payments/checkout.js',
                'resources/js/payments/channels-admin.js',
                'resources/js/select2-init.js',
            ],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
