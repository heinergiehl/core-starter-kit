import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/templates/default.css',
                'resources/css/templates/void.css',
                'resources/css/templates/aurora.css',
                'resources/css/templates/prism.css',
                'resources/css/templates/velvet.css',
                'resources/css/templates/frost.css',
                'resources/css/templates/ember.css',
                'resources/css/templates/ocean.css',
                'resources/js/app.js',
            ],
            refresh: true,
        }),
    ],
});
