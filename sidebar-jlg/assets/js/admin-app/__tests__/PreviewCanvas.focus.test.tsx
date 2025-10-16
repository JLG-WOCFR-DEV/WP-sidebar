import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import PreviewCanvas from '../components/PreviewCanvas';
import { bootstrapStore, useOptionsStore } from '../store/optionsStore';
import type { SidebarJLGAppBootstrap } from '../types';

const originalFetch = global.fetch;

const bootstrap: SidebarJLGAppBootstrap = {
  options: {},
  defaults: {},
  profiles: [],
  activeProfile: 'default',
  preview: {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    action: 'jlg_render_preview',
    nonce: 'nonce',
  },
  onboarding: {
    currentStep: 0,
    completed: true,
  },
  strings: {
    generalTab: 'Général',
    stylesTab: 'Styles',
    profilesTab: 'Profils',
    openCanvas: 'Ouvrir le canvas',
    undo: 'Annuler',
    redo: 'Rétablir',
    closeCanvas: 'Fermer',
    previewError: 'Erreur',
    previewCanvasTitle: 'Aperçu',
    previewCanvasDescription: 'Description',
  },
};

const mockPreviewResponse = () =>
  Promise.resolve({
    json: () =>
      Promise.resolve({
        success: true,
        data: {
          html: '<button type="button">CTA</button>',
        },
      }),
  });

describe('PreviewCanvas focus management', () => {
  beforeEach(() => {
    bootstrapStore(bootstrap);
    useOptionsStore.setState({ isCanvasOpen: true });

    Object.defineProperty(global, 'fetch', {
      configurable: true,
      writable: true,
      value: jest.fn().mockImplementation(mockPreviewResponse),
    });
  });

  afterEach(() => {
    jest.clearAllMocks();

    if (originalFetch) {
      Object.defineProperty(global, 'fetch', {
        configurable: true,
        writable: true,
        value: originalFetch,
      });
    } else {
      Reflect.deleteProperty(global, 'fetch');
    }
  });

  test('focuses the first available control when opened', async () => {
    const trigger = document.createElement('button');
    trigger.textContent = 'Ouvrir';
    document.body.appendChild(trigger);
    trigger.focus();

    render(<PreviewCanvas />);

    await waitFor(() => {
      expect(screen.getByRole('button', { name: 'Fermer' })).toHaveFocus();
    });

    trigger.remove();
  });

  test('loops focus with Tab and Shift+Tab', async () => {
    render(<PreviewCanvas />);

    const closeButton = await screen.findByRole('button', { name: 'Fermer' });
    const ctaButton = await screen.findByRole('button', { name: 'CTA' });

    expect(closeButton).toHaveFocus();

    ctaButton.focus();
    fireEvent.keyDown(ctaButton, { key: 'Tab' });
    expect(closeButton).toHaveFocus();

    fireEvent.keyDown(closeButton, { key: 'Tab', shiftKey: true });
    expect(ctaButton).toHaveFocus();
  });

  test('closes on Escape and restores focus to the trigger', async () => {
    const trigger = document.createElement('button');
    trigger.textContent = 'Ouvrir';
    document.body.appendChild(trigger);
    trigger.focus();

    render(<PreviewCanvas />);

    const surface = await screen.findByRole('dialog');

    fireEvent.keyDown(surface, { key: 'Escape' });

    await waitFor(() => {
      expect(useOptionsStore.getState().isCanvasOpen).toBe(false);
    });

    await waitFor(() => {
      expect(trigger).toHaveFocus();
    });

    trigger.remove();
  });
});
