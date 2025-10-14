import { Button, Modal, Notice, ProgressBar } from '@wordpress/components';
import { useEffect, useMemo, useState } from 'react';

import { useOptionsStore } from '../store/optionsStore';

interface StepDescriptor {
  description: string;
  ctaLabel: string;
  action?: () => void;
}

const OnboardingModal = (): JSX.Element | null => {
  const onboarding = useOptionsStore((state) => state.onboarding);
  const setOnboardingState = useOptionsStore((state) => state.setOnboardingState);
  const setActiveTab = useOptionsStore((state) => state.setActiveTab);
  const openCanvas = useOptionsStore((state) => state.openCanvas);
  const strings = useOptionsStore((state) => state.strings);

  const [isOpen, setIsOpen] = useState(() => !onboarding.completed && onboarding.dismissed !== true);

  useEffect(() => {
    setIsOpen(!onboarding.completed && onboarding.dismissed !== true);
  }, [onboarding]);

  const steps = useMemo<StepDescriptor[]>(() => {
    if (!strings) {
      return [];
    }

    const descriptions = strings.onboardingSteps ?? [];
    const ctas = strings.onboardingCtaLabels ?? [];

    return [
      {
        description: descriptions[0] ?? 'Activez la sidebar et personnalisez les libellés essentiels.',
        ctaLabel: ctas[0] ?? 'Configurer le général',
        action: () => setActiveTab('general'),
      },
      {
        description: descriptions[1] ?? 'Choisissez les couleurs principales adaptées à votre marque.',
        ctaLabel: ctas[1] ?? 'Adapter les styles',
        action: () => setActiveTab('styles'),
      },
      {
        description: descriptions[2] ?? 'Ajustez votre premier CTA directement dans le canvas interactif.',
        ctaLabel: ctas[2] ?? 'Ouvrir le canvas',
        action: () => openCanvas(),
      },
      {
        description: descriptions[3] ?? 'Organisez vos profils de diffusion et vérifiez les contextes cibles.',
        ctaLabel: ctas[3] ?? 'Parcourir les profils',
        action: () => setActiveTab('profiles'),
      },
      {
        description: descriptions[4] ?? 'Publiez et partagez votre nouvelle expérience Sidebar JLG !',
        ctaLabel: ctas[4] ?? 'Terminer',
      },
    ];
  }, [openCanvas, setActiveTab, strings]);

  if (!strings || !isOpen || !steps.length) {
    return null;
  }

  const totalSteps = steps.length;
  const currentStep = Math.min(onboarding.currentStep ?? 0, totalSteps - 1);
  const step = steps[currentStep];
  const isLast = currentStep >= totalSteps - 1;
  const progress = ((currentStep + 1) / totalSteps) * 100;

  const persistState = async (next: typeof onboarding) => {
    await setOnboardingState(next, true);
  };

  const goToNext = async () => {
    const nextStep = Math.min(currentStep + 1, totalSteps - 1);
    const completed = isLast || nextStep === totalSteps - 1;
    await persistState({ ...onboarding, currentStep: nextStep, completed });
    if (completed) {
      setIsOpen(false);
    }
  };

  const skip = async () => {
    await persistState({ ...onboarding, dismissed: true, completed: onboarding.completed });
    setIsOpen(false);
  };

  const handleCta = async () => {
    step.action?.();
    if (isLast) {
      await persistState({ ...onboarding, completed: true, currentStep });
      setIsOpen(false);
      return;
    }

    await goToNext();
  };

  return (
    <Modal title={strings.onboardingTitle} onRequestClose={skip} className="sidebar-jlg-onboarding-modal">
      <div className="sidebar-jlg-onboarding-modal__intro">
        <p>{strings.onboardingDescription}</p>
        <ProgressBar value={progress} label={`Étape ${currentStep + 1} / ${totalSteps}`} />
      </div>
      <Notice status="info" isDismissible={false}>
        {step.description}
      </Notice>
      <div className="sidebar-jlg-onboarding-modal__actions">
        <Button variant="link" onClick={skip}>
          {strings.onboardingSkip}
        </Button>
        <Button variant="primary" onClick={handleCta}>
          {isLast ? strings.onboardingFinish : step.ctaLabel}
        </Button>
      </div>
    </Modal>
  );
};

export default OnboardingModal;
