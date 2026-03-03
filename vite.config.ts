import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { defineConfig, loadEnv } from 'vite';


export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), [''])

    // Skip Wayfinder during Docker builds where PHP artisan isn't available
    const skipWayfinder = env.SKIP_WAYFINDER === 'true';

    const { DOCKER_USE_VITE_CONFIG } = env

    const serverConfig = !(/^true$/i.test(DOCKER_USE_VITE_CONFIG)) ? ({}) : ({
        server: {
            origin: '.',
            allowedHosts: ['*'],
            host: true,
            port: 5173,
            hmr: {
                origin: '.',
                protocol: "wss",
                path: '/vite-ws'
            },
        }
    })

    console.table({ DOCKER_USE_VITE_CONFIG, serverConfig })

    return ({
        ...(serverConfig),
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.tsx'],
                ssr: 'resources/js/ssr.tsx',
                refresh: true,
            }),
            react({
                babel: {
                    plugins: ['babel-plugin-react-compiler'],
                },
            }),
            // Only include Wayfinder plugin when not in Docker build
            ...(!skipWayfinder
                ? [
                    wayfinder({
                        formVariants: true,
                    }),
                ]
                : []),
        ],
        esbuild: {
            jsx: 'automatic',
        },
    })
}

);
