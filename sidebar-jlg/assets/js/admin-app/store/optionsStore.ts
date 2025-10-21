import apiFetch from '@wordpress/api-fetch';
import { create } from 'zustand';

import type {
  SidebarAppStrings,
  SidebarJLGAppBootstrap,
  SidebarOnboardingState,
  SidebarOptions,
} from '../types';

type OptionsStore = {
  options: SidebarOptions;
  defaults: SidebarOptions;
  profiles: unknown[];
  activeProfile: string;
  activeTab: 'general' | 'styles' | 'profiles';
  isCanvasOpen: boolean;
  history: SidebarOptions[];
  future: SidebarOptions[];
  onboarding: SidebarOnboardingState;
  strings: SidebarAppStrings | null;
  preview: SidebarJLGAppBootstrap['preview'] | null;
  setActiveTab: (tab: 'general' | 'styles' | 'profiles') => void;
  openCanvas: () => void;
  closeCanvas: () => void;
  setOption: (path: string, value: unknown) => void;
  applyServerOptions: (options: SidebarOptions) => void;
  undo: () => void;
  redo: () => void;
  canUndo: () => boolean;
  canRedo: () => boolean;
  setOnboardingState: (state: SidebarOnboardingState, persist?: boolean) => Promise<void>;
  setStrings: (strings: SidebarAppStrings) => void;
};

const defaultOnboardingState: SidebarOnboardingState = {
  currentStep: 0,
  completed: true,
};

const deepClone = <T,>(value: T): T => {
  if (typeof structuredClone === 'function') {
    return structuredClone(value);
  }

  return JSON.parse(JSON.stringify(value));
};

const isObjectLike = (value: unknown): value is Record<string, unknown> | unknown[] =>
  typeof value === 'object' && value !== null;

const deepEqual = (a: unknown, b: unknown): boolean => {
  if (a === b) {
    return true;
  }

  if (!isObjectLike(a) || !isObjectLike(b)) {
    return false;
  }

  if (Array.isArray(a) || Array.isArray(b)) {
    if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) {
      return false;
    }

    return a.every((value, index) => deepEqual(value, b[index]));
  }

  const aEntries = Object.entries(a);
  const bEntries = Object.entries(b);

  if (aEntries.length !== bEntries.length) {
    return false;
  }

  return aEntries.every(([key, value]) =>
    Object.prototype.hasOwnProperty.call(b, key) && deepEqual(value, (b as Record<string, unknown>)[key])
  );
};

const normalizePath = (path: string): string[] =>
  path
    .replace(/\[(\d+)\]/g, '.$1')
    .split('.')
    .map((segment) => segment.trim())
    .filter(Boolean);

const setByPath = (input: SidebarOptions, path: string, value: unknown): SidebarOptions => {
  const segments = normalizePath(path);
  const next = deepClone(input);

  if (!segments.length) {
    return next;
  }

  let cursor: Record<string, unknown> | unknown[] = next as Record<string, unknown>;

  segments.forEach((segment, index) => {
    const isLast = index === segments.length - 1;
    const numericIndex = Number(segment);
    const key = Number.isInteger(numericIndex) && segment !== '' ? numericIndex : segment;

    if (isLast) {
      if (Array.isArray(cursor) && typeof key === 'number') {
        cursor[key] = value as never;
      } else if (!Array.isArray(cursor) && typeof cursor === 'object' && cursor !== null) {
        (cursor as Record<string, unknown>)[key as string] = value;
      }
      return;
    }

    if (Array.isArray(cursor)) {
      if (typeof key !== 'number') {
        return;
      }

      if (cursor[key] === undefined) {
        const nextKey = Number.isInteger(Number(segments[index + 1])) ? [] : {};
        cursor[key] = nextKey as never;
      }

      cursor = cursor[key] as Record<string, unknown> | unknown[];
    } else if (cursor && typeof cursor === 'object') {
      const record = cursor as Record<string, unknown>;
      if (!(key as string in record) || record[key as string] === undefined) {
        const nextKey = Number.isInteger(Number(segments[index + 1])) ? [] : {};
        record[key as string] = nextKey;
      }

      const nextCursor = record[key as string];
      if (nextCursor && (typeof nextCursor === 'object' || Array.isArray(nextCursor))) {
        cursor = nextCursor as Record<string, unknown> | unknown[];
      }
    }
  });

  return next;
};

export const useOptionsStore = create<OptionsStore>((set, get) => ({
  options: {},
  defaults: {},
  profiles: [],
  activeProfile: 'default',
  activeTab: 'general',
  isCanvasOpen: false,
  history: [],
  future: [],
  onboarding: defaultOnboardingState,
  strings: null,
  preview: null,
  setActiveTab: (tab) => set({ activeTab: tab }),
  openCanvas: () => set({ isCanvasOpen: true }),
  closeCanvas: () => set({ isCanvasOpen: false }),
  setOption: (path, value) =>
    set((state) => {
      const nextOptions = setByPath(state.options, path, value);
      const history = [...state.history, deepClone(state.options)];
      return {
        options: nextOptions,
        history,
        future: [],
      };
    }),
  applyServerOptions: (options) =>
    set((state) => {
      if (deepEqual(state.options, options)) {
        return state;
      }

      return {
        options: deepClone(options),
      };
    }),
  undo: () =>
    set((state) => {
      if (!state.history.length) {
        return state;
      }

      const previous = state.history[state.history.length - 1];
      const history = state.history.slice(0, -1);
      const future = [deepClone(state.options), ...state.future];

      return {
        options: deepClone(previous),
        history,
        future,
      };
    }),
  redo: () =>
    set((state) => {
      if (!state.future.length) {
        return state;
      }

      const [next, ...remaining] = state.future;
      const history = [...state.history, deepClone(state.options)];

      return {
        options: deepClone(next),
        history,
        future: remaining,
      };
    }),
  canUndo: () => get().history.length > 0,
  canRedo: () => get().future.length > 0,
  setOnboardingState: async (state, persist = false) => {
    set({ onboarding: state });

    if (persist) {
      try {
        await apiFetch({
          path: '/wp/v2/settings',
          method: 'POST',
          data: {
            sidebar_jlg_onboarding_state: state,
          },
        });
      } catch (error) {
        // eslint-disable-next-line no-console
        console.error('Failed to persist onboarding state', error);
      }
    }
  },
  setStrings: (strings) => set({ strings }),
}));

export const bootstrapStore = (bootstrap: SidebarJLGAppBootstrap): void => {
  useOptionsStore.setState({
    options: deepClone(bootstrap.options),
    defaults: deepClone(bootstrap.defaults),
    profiles: bootstrap.profiles,
    activeProfile: bootstrap.activeProfile || 'default',
    onboarding: bootstrap.onboarding ?? defaultOnboardingState,
    strings: bootstrap.strings,
    preview: bootstrap.preview,
    history: [],
    future: [],
    activeTab: 'general',
    isCanvasOpen: false,
  });
};
