export type SidebarOptions = Record<string, unknown>;

export interface SidebarPreviewConfig {
  ajaxUrl: string;
  action: string;
  nonce: string;
}

export interface SidebarOnboardingState {
  currentStep: number;
  completed: boolean;
  dismissed?: boolean;
}

export interface SidebarAppStrings {
  generalTab: string;
  stylesTab: string;
  profilesTab: string;
  openCanvas: string;
  undo: string;
  redo: string;
  closeCanvas: string;
  previewError: string;
  onboardingTitle: string;
  onboardingDescription: string;
  onboardingSteps: string[];
  onboardingCtaLabels: string[];
  onboardingSkip: string;
  onboardingFinish: string;
}

export interface SidebarJLGAppBootstrap {
  options: SidebarOptions;
  defaults: SidebarOptions;
  profiles: unknown[];
  activeProfile: string;
  preview: SidebarPreviewConfig;
  onboarding: SidebarOnboardingState;
  strings: SidebarAppStrings;
  restNonce?: string;
}
