import { useCallback, useEffect, useRef } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';

const FOCUSABLE_SELECTOR =
  'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

type TrapEvent = KeyboardEvent | ReactKeyboardEvent<HTMLElement>;

interface InertSnapshot {
  element: HTMLElement;
  ariaHidden: string | null;
  inert: boolean;
}

interface UseFocusTrapOptions {
  isActive: boolean;
  surfaceRef: React.RefObject<HTMLElement | null>;
  ownerRef?: React.RefObject<HTMLElement | null>;
  onEscape?: () => void;
}

const useFocusTrap = ({
  isActive,
  surfaceRef,
  ownerRef,
  onEscape,
}: UseFocusTrapOptions): { handleKeyDown: (event: TrapEvent) => void } => {
  const restoreFocusRef = useRef<HTMLElement | null>(null);
  const inertSnapshotRef = useRef<InertSnapshot[]>([]);

  const getFocusableElements = useCallback((): HTMLElement[] => {
    const surface = surfaceRef.current;
    if (!surface) {
      return [];
    }

    const nodes = Array.from(surface.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));

    return nodes.filter((element) => {
      if (element.hasAttribute('disabled') || element.getAttribute('aria-hidden') === 'true') {
        return false;
      }

      if (element.tabIndex < 0) {
        return false;
      }

      if (element.hasAttribute('hidden')) {
        return false;
      }

      if (element.closest('[hidden]')) {
        return false;
      }

      return true;
    });
  }, [surfaceRef]);

  const getOwnerElement = useCallback((): HTMLElement | null => {
    return ownerRef?.current ?? surfaceRef.current ?? null;
  }, [ownerRef, surfaceRef]);

  const getInertRoot = useCallback((): HTMLElement | null => {
    const ownerElement = getOwnerElement();
    if (!ownerElement) {
      return null;
    }

    const scopedRoot = ownerElement.closest('.sidebar-jlg-admin-app__root') as HTMLElement | null;
    if (scopedRoot) {
      return scopedRoot;
    }

    const parent = ownerElement.parentElement;
    if (
      parent instanceof HTMLElement &&
      parent !== document.body &&
      parent !== document.documentElement &&
      parent.id !== 'wpwrap'
    ) {
      return parent;
    }

    return null;
  }, [getOwnerElement]);

  const focusFirstElement = useCallback(() => {
    const focusable = getFocusableElements();
    const target = focusable[0] ?? surfaceRef.current;

    target?.focus({ preventScroll: true });
  }, [getFocusableElements, surfaceRef]);

  const handleKeyDown = useCallback(
    (event: TrapEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onEscape?.();
        return;
      }

      if (event.key !== 'Tab') {
        return;
      }

      const focusable = getFocusableElements();

      if (!focusable.length) {
        event.preventDefault();
        surfaceRef.current?.focus({ preventScroll: true });
        return;
      }

      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      const activeElement = document.activeElement as HTMLElement | null;

      if (event.shiftKey) {
        if (!activeElement || activeElement === first || !surfaceRef.current?.contains(activeElement)) {
          event.preventDefault();
          last.focus();
        }
        return;
      }

      if (activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    },
    [getFocusableElements, onEscape, surfaceRef]
  );

  useEffect(() => {
    if (!isActive) {
      return undefined;
    }

    const ownerElement = getOwnerElement();
    restoreFocusRef.current = (document.activeElement as HTMLElement | null) ?? null;

    const previouslyFocused = restoreFocusRef.current;
    if (previouslyFocused && ownerElement && !ownerElement.contains(previouslyFocused)) {
      if (typeof previouslyFocused.blur === 'function') {
        previouslyFocused.blur();
      }
    }

    const inertRoot = getInertRoot();
    let trapContainer: HTMLElement | null = ownerElement;

    if (inertRoot && trapContainer) {
      while (trapContainer && trapContainer.parentElement !== inertRoot) {
        trapContainer = trapContainer.parentElement as HTMLElement | null;
      }
    }

    if (inertRoot && trapContainer && trapContainer.parentElement === inertRoot) {
      const siblings = Array.from(inertRoot.children).filter(
        (node): node is HTMLElement => node instanceof HTMLElement && node !== trapContainer
      );

      inertSnapshotRef.current = siblings.map((element) => {
        const snapshot: InertSnapshot = {
          element,
          ariaHidden: element.getAttribute('aria-hidden'),
          inert: element.hasAttribute('inert'),
        };

        element.setAttribute('aria-hidden', 'true');
        element.setAttribute('inert', '');

        return snapshot;
      });
    } else {
      inertSnapshotRef.current = [];
    }

    const ensureFocusWithinTrap = () => {
      const activeElement = document.activeElement as HTMLElement | null;
      if (!ownerElement || (activeElement && ownerElement.contains(activeElement))) {
        return;
      }

      focusFirstElement();
    };

    ensureFocusWithinTrap();
    const focusTimer = window.setTimeout(() => {
      ensureFocusWithinTrap();
    }, 0);

    return () => {
      if (typeof focusTimer === 'number') {
        window.clearTimeout(focusTimer);
      }

      inertSnapshotRef.current.forEach(({ element, ariaHidden, inert }) => {
        if (ariaHidden === null) {
          element.removeAttribute('aria-hidden');
        } else {
          element.setAttribute('aria-hidden', ariaHidden);
        }

        if (inert) {
          element.setAttribute('inert', '');
        } else {
          element.removeAttribute('inert');
        }
      });

      inertSnapshotRef.current = [];

      restoreFocusRef.current?.focus({ preventScroll: true });
      restoreFocusRef.current = null;
    };
  }, [focusFirstElement, getInertRoot, getOwnerElement, isActive]);

  return { handleKeyDown };
};

export default useFocusTrap;
