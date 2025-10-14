import { render } from '@wordpress/element';
import { StrictMode } from 'react';
import apiFetch from '@wordpress/api-fetch';

import App from './admin-app/App';
import { bootstrapStore } from './admin-app/store/optionsStore';
import type { SidebarJLGAppBootstrap } from './admin-app/types';

declare global {
  interface Window {
    sidebarJLGApp?: SidebarJLGAppBootstrap;
  }
}

const root = document.getElementById('sidebar-jlg-admin-app-root');
const bootstrap = window.sidebarJLGApp;

if (bootstrap?.restNonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(bootstrap.restNonce));
}

if (root && bootstrap) {
  bootstrapStore(bootstrap);
  root.classList.add('is-mounted');
  render(
    <StrictMode>
      <App />
    </StrictMode>,
    root
  );
} else if (root) {
  root.classList.add('is-missing-bootstrap');
}
