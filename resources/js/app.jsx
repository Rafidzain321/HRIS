import React from 'react';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import PageLoader from './Components/PageLoader';

createInertiaApp({
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx')
        ),
    // Bar bawaan Inertia diganti PageLoader (bar + persentase).
    progress: false,
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <PageLoader />
            </>
        );
    },
});
