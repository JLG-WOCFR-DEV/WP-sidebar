import { act } from '@testing-library/react';

import { bootstrapStore, useOptionsStore } from '../store/optionsStore';
import type { SidebarJLGAppBootstrap } from '../types';

const bootstrap: SidebarJLGAppBootstrap = {
  options: {
    app_name: 'Sidebar JLG',
    menu_items: [
      {
        type: 'cta',
        cta_title: 'Titre CTA',
        cta_description: 'Description',
        cta_button_label: 'Agir',
        cta_button_color: '#000000',
      },
    ],
  },
  defaults: {
    app_name: 'Sidebar JLG',
    menu_items: [],
  },
  profiles: [],
  activeProfile: 'default',
  preview: {
    ajaxUrl: '/wp-admin/admin-ajax.php',
    action: 'jlg_render_preview',
    nonce: 'nonce',
  },
  onboarding: {
    currentStep: 0,
    completed: false,
  },
  strings: {
    generalTab: 'Général',
    stylesTab: 'Styles',
    profilesTab: 'Profils',
    openCanvas: 'Canvas',
    undo: 'Annuler',
    redo: 'Rétablir',
    closeCanvas: 'Fermer',
    previewError: 'Erreur',
    onboardingTitle: 'Assistant',
    onboardingDescription: 'Description',
    onboardingSteps: ['Étape 1', 'Étape 2', 'Étape 3', 'Étape 4', 'Étape 5'],
    onboardingCtaLabels: ['CTA1', 'CTA2', 'CTA3', 'CTA4', 'CTA5'],
    onboardingSkip: 'Ignorer',
    onboardingFinish: 'Terminer',
  },
};

describe('optionsStore', () => {
  beforeEach(() => {
    bootstrapStore(bootstrap);
  });

  test('setOption updates the state and records history', () => {
    const { setOption, history } = useOptionsStore.getState();

    expect(history).toHaveLength(0);

    act(() => {
      setOption('app_name', 'Nouvelle Sidebar');
    });

    const state = useOptionsStore.getState();
    expect(state.options.app_name).toBe('Nouvelle Sidebar');
    expect(state.history).toHaveLength(1);
  });

  test('undo and redo restore previous values', () => {
    const store = useOptionsStore.getState();

    act(() => {
      store.setOption('app_name', 'Première valeur');
      store.setOption('app_name', 'Seconde valeur');
    });

    expect(useOptionsStore.getState().options.app_name).toBe('Seconde valeur');
    expect(useOptionsStore.getState().canUndo()).toBe(true);

    act(() => {
      useOptionsStore.getState().undo();
    });

    expect(useOptionsStore.getState().options.app_name).toBe('Première valeur');
    expect(useOptionsStore.getState().canRedo()).toBe(true);

    act(() => {
      useOptionsStore.getState().redo();
    });

    expect(useOptionsStore.getState().options.app_name).toBe('Seconde valeur');
  });
});
