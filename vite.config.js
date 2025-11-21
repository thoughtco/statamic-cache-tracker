import laravel from 'laravel-vite-plugin'
import { defineConfig } from 'vite'
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    plugins: [
        statamic(),
        laravel({
            input: [
                'resources/js/cp.js'
            ],
            refresh: true,
            publicDirectory: 'dist',
            hotFile: 'dist/hot',
        }),
    ],
});
