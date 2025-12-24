import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/pages/review/index.js',
                'resources/js/pages/orderform/index.js',
                'resources/css/pages/orderform/index.css',
                'resources/css/pages/review/index.css',
                'resources/css/pages/confirmation/index.css',
            ],
            refresh: true,
        }),
    ],
    
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        rollupOptions: {          
            output: {                
                assetFileNames: assetInfo => {
                    if (assetInfo.name && assetInfo.name.match(/\.(woff|woff2|eot|ttf|svg)$/)) {
                        return 'assets/fonts/[name][extname]';
                    }
                    if (assetInfo.name && assetInfo.name.match(/\.(png|jpe?g|gif|svg)$/)) {
                        return 'assets/images/[name][extname]';
                    }
                    if (assetInfo.name && assetInfo.name.endsWith('.css')) {
                        return 'assets/css/app.min.css';
                    }
                    return 'assets/[name].min[extname]';
                },
                chunkFileNames: 'assets/js/chunk-[name].min.js',
                manualChunks: undefined,
            },
        },
    },
});
