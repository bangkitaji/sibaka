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
    // Paksa light mode — hapus class dark dari awal
    document.documentElement.classList.remove('dark');
    localStorage.setItem('dark-mode-preference', 'light');
    localStorage.setItem('sibaka-theme', 'light');

    createRoot(el).render(<App {...props} />);
  },
  progress: {
    color: '#4B5563',
  },
});
