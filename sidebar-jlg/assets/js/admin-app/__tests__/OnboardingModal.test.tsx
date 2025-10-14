import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import OnboardingModal from '../components/OnboardingModal';
import { bootstrapStore, useOptionsStore } from '../store/optionsStore';
import type { SidebarJLGAppBootstrap } from '../types';

jest.mock('@wordpress/api-fetch', () => {
  const mock: any = jest.fn(() => Promise.resolve({}));
  mock.createNonceMiddleware = () => () => {};
  return mock;
});

const bootstrap: SidebarJLGAppBootstrap = {
  options: {
    app_name: 'Sidebar JLG',
  },
  defaults: {
    app_name: 'Sidebar JLG',
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
    onboardingTitle: 'Assistant de démarrage',
    onboardingDescription: 'Suivez ces étapes.',
    onboardingSteps: ['Étape 1', 'Étape 2', 'Étape 3', 'Étape 4', 'Étape 5'],
    onboardingCtaLabels: ['CTA1', 'CTA2', 'CTA3', 'CTA4', 'CTA5'],
    onboardingSkip: 'Ignorer',
    onboardingFinish: 'Terminer',
  },
};

describe('OnboardingModal', () => {
  beforeEach(() => {
    bootstrapStore(bootstrap);
  });

  test('renders the current step and advances on CTA click', async () => {
    render(<OnboardingModal />);

    expect(screen.getByText('Assistant de démarrage')).toBeInTheDocument();
    expect(screen.getByText('Étape 1')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'CTA1' }));

    await waitFor(() => {
      expect(useOptionsStore.getState().onboarding.currentStep).toBe(1);
    });
  });

  test('allows skipping the onboarding', async () => {
    render(<OnboardingModal />);

    fireEvent.click(screen.getByRole('button', { name: 'Ignorer' }));

    await waitFor(() => {
      const state = useOptionsStore.getState().onboarding;
      expect(state.dismissed).toBe(true);
      expect(state.completed).toBe(false);
    });
  });
});
