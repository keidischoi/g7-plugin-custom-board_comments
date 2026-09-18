import { resolve } from 'node:path';
import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        lib: {
            entry: resolve(import.meta.dirname, 'resources/js/index.ts'),
            name: 'G7PluginCustomBoardComments',
            formats: ['iife'],
            cssFileName: 'plugin',
        },
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            output: {
                entryFileNames: 'js/plugin.iife.js',
                assetFileNames: (asset) => (
                    asset.name?.endsWith('.css') ? 'css/plugin.css' : 'assets/[name][extname]'
                ),
            },
        },
        sourcemap: !['0', 'false'].includes(process.env.G7_BUILD_SOURCEMAP ?? ''),
        target: 'es2020',
    },
});
