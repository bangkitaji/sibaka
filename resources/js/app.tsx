import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { ReactElement } from 'react';

createInertiaApp({
  title: (title) => (title ? `${title} - SIBAKA` : 'SIBAKA'),
  resolve: (name) =>
    resolvePageComponent(
      `./Pages/${name}.tsx`,
      import.meta.glob<{ default: React.ComponentType<Record<string, unknown>> & { layout?: (page: ReactElement) => ReactElement } }>(
        './Pages/**/*.tsx'
      )
    ),
  setup({ el, App, props }) {
    // Detect system dark mode preference on initial load
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const storedTheme = localStorage.getItem('sibaka-theme');

    if (storedTheme === 'dark' || (!storedTheme && prefersDark)) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }

    createRoot(el).render(<App {...props} />);
  },
  progress: {
    color: '#4B5563',
  },
});
