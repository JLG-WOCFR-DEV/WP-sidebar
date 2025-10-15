import {
  Button,
  ColorPicker,
  Notice,
  Panel,
  PanelBody,
  Spinner,
} from '@wordpress/components';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import type { KeyboardEvent as ReactKeyboardEvent } from 'react';

import { useOptionsStore } from '../store/optionsStore';
import type { SidebarOptions } from '../types';

const useDebouncedEffect = (callback: () => void, delay: number, dependencies: unknown[]): void => {
  useEffect(() => {
    const handler = window.setTimeout(() => {
      callback();
    }, delay);

    return () => {
      window.clearTimeout(handler);
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, dependencies);
};

const findFirstCtaIndex = (options: SidebarOptions): number => {
  const items = Array.isArray(options.menu_items) ? (options.menu_items as unknown[]) : [];

  return items.findIndex((item) => {
    if (!item || typeof item !== 'object') {
      return false;
    }

    const record = item as Record<string, unknown>;
    return record.type === 'cta';
  });
};

const FOCUSABLE_SELECTOR =
  'a[href], area[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

interface InertSnapshot {
  element: HTMLElement;
  ariaHidden: string | null;
  inert: boolean;
}

const PreviewCanvas = (): JSX.Element | null => {
  const isOpen = useOptionsStore((state) => state.isCanvasOpen);
  const closeCanvas = useOptionsStore((state) => state.closeCanvas);
  const options = useOptionsStore((state) => state.options);
  const preview = useOptionsStore((state) => state.preview);
  const applyServerOptions = useOptionsStore((state) => state.applyServerOptions);
  const setOption = useOptionsStore((state) => state.setOption);
  const undo = useOptionsStore((state) => state.undo);
  const redo = useOptionsStore((state) => state.redo);
  const canUndo = useOptionsStore((state) => state.canUndo());
  const canRedo = useOptionsStore((state) => state.canRedo());
  const strings = useOptionsStore((state) => state.strings);

  const [html, setHtml] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const dialogRef = useRef<HTMLDivElement | null>(null);
  const surfaceRef = useRef<HTMLDivElement | null>(null);
  const previewRef = useRef<HTMLDivElement | null>(null);
  const restoreFocusRef = useRef<HTMLElement | null>(null);
  const inertSnapshotRef = useRef<InertSnapshot[]>([]);

  const dialogTitleId = useMemo(
    () => `sidebar-jlg-preview-title-${Math.random().toString(36).slice(2)}`,
    []
  );
  const dialogDescriptionId = useMemo(
    () => `sidebar-jlg-preview-description-${Math.random().toString(36).slice(2)}`,
    []
  );

  const getFocusableElements = useCallback((): HTMLElement[] => {
    if (!surfaceRef.current) {
      return [];
    }

    const nodes = Array.from(
      surfaceRef.current.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR)
    );

    return nodes.filter((element) => {
      if (element.hasAttribute('disabled') || element.getAttribute('aria-hidden') === 'true') {
        return false;
      }

      if (element.tabIndex < 0) {
        return false;
      }

      return element.offsetParent !== null;
    });
  }, []);

  const handleKeyDown = useCallback(
    (event: ReactKeyboardEvent<HTMLDivElement>) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        closeCanvas();
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
      const active = document.activeElement as HTMLElement | null;

      if (event.shiftKey) {
        if (!active || active === first || !surfaceRef.current?.contains(active)) {
          event.preventDefault();
          last.focus();
        }
        return;
      }

      if (active === last) {
        event.preventDefault();
        first.focus();
      }
    },
    [closeCanvas, getFocusableElements]
  );

  const ctaIndex = useMemo(() => findFirstCtaIndex(options), [options]);
  const ctaColor = useMemo(() => {
    if (ctaIndex < 0) {
      return '#3b82f6';
    }

    const items = Array.isArray(options.menu_items) ? (options.menu_items as Record<string, unknown>[]) : [];
    const item = items[ctaIndex] ?? {};

    return (item.cta_button_color as string) ?? '#3b82f6';
  }, [options, ctaIndex]);

  const fetchPreview = useMemo(() => {
    if (!preview) {
      return () => {};
    }

    return async () => {
      setLoading(true);
      setError(null);

      try {
        const formData = new window.FormData();
        formData.append('action', preview.action);
        formData.append('nonce', preview.nonce);
        formData.append('options', JSON.stringify(options));

        const response = await window.fetch(preview.ajaxUrl, {
          method: 'POST',
          credentials: 'same-origin',
          body: formData,
        });

        const payload = await response.json();

        if (!payload?.success) {
          setError(strings?.previewError ?? 'Impossible de charger l’aperçu.');
          return;
        }

        const data = payload.data ?? {};
        if (typeof data.html === 'string') {
          setHtml(data.html);
        }

        if (data.options) {
          applyServerOptions(data.options as SidebarOptions);
        }
      } catch (err) {
        setError(strings?.previewError ?? 'Impossible de charger l’aperçu.');
      } finally {
        setLoading(false);
      }
    };
  }, [applyServerOptions, options, preview, strings]);

  useDebouncedEffect(() => {
    if (!isOpen || !preview) {
      return;
    }

    void fetchPreview();
  }, 300, [options, isOpen, preview, fetchPreview]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    restoreFocusRef.current = (document.activeElement as HTMLElement | null) ?? null;

    const dialog = dialogRef.current;
    if (dialog?.parentElement) {
      const siblings = Array.from(dialog.parentElement.children).filter(
        (node): node is HTMLElement => node instanceof HTMLElement && node !== dialog
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
    }

    const surface = surfaceRef.current;
    const focusTimer = surface
      ? window.setTimeout(() => {
          const focusable = getFocusableElements();
          const target = focusable[0] ?? surface;
          target.focus({ preventScroll: true });
        }, 0)
      : undefined;

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
  }, [getFocusableElements, isOpen]);

  useEffect(() => {
    if (!isOpen) {
      return;
    }

    const container = previewRef.current;
    if (!container || ctaIndex < 0) {
      return;
    }

    const unregister: Array<() => void> = [];

    const makeEditable = (selector: string, optionKey: string) => {
      const element = container.querySelector<HTMLElement>(selector);
      if (!element) {
        return;
      }

      element.setAttribute('contenteditable', 'true');
      element.dataset.sidebarEditable = optionKey;

      const handleInput = () => {
        const path = `menu_items[${ctaIndex}].${optionKey}`;
        setOption(path, element.innerText.trim());
      };

      element.addEventListener('input', handleInput);
      unregister.push(() => {
        element.removeEventListener('input', handleInput);
        element.removeAttribute('contenteditable');
        delete element.dataset.sidebarEditable;
      });
    };

    makeEditable('.menu-cta__title', 'cta_title');
    makeEditable('.menu-cta__description', 'cta_description');
    makeEditable('.menu-cta__button span', 'cta_button_label');

    return () => {
      unregister.forEach((cleanup) => cleanup());
    };
  }, [ctaIndex, html, isOpen, setOption]);

  if (!isOpen) {
    return null;
  }

  return (
    <div className="sidebar-jlg-preview-canvas" ref={dialogRef} role="presentation">
      <div className="sidebar-jlg-preview-canvas__backdrop" aria-hidden="true" onClick={closeCanvas} />
      <div
        className="sidebar-jlg-preview-canvas__surface"
        ref={surfaceRef}
        role="dialog"
        aria-modal="true"
        aria-labelledby={dialogTitleId}
        aria-describedby={dialogDescriptionId}
        tabIndex={-1}
        onKeyDown={handleKeyDown}
      >
        <header className="sidebar-jlg-preview-canvas__toolbar">
          <h2 id={dialogTitleId} className="screen-reader-text">
            {strings?.previewCanvasTitle ?? 'Aperçu de la sidebar JLG'}
          </h2>
          <div className="sidebar-jlg-preview-canvas__toolbar-group">
            <Button variant="secondary" onClick={undo} disabled={!canUndo}>
              {strings?.undo ?? 'Annuler'}
            </Button>
            <Button variant="secondary" onClick={redo} disabled={!canRedo}>
              {strings?.redo ?? 'Rétablir'}
            </Button>
          </div>
          <Button variant="primary" onClick={closeCanvas}>
            {strings?.closeCanvas ?? 'Fermer'}
          </Button>
        </header>
        <p id={dialogDescriptionId} className="screen-reader-text">
          {strings?.previewCanvasDescription ??
            'Aperçu interactif de la sidebar. Les touches Tab et Maj+Tab parcourent les contrôles, Échap ferme la fenêtre.'}
        </p>
        {error && (
          <div className="sidebar-jlg-preview-canvas__notice">
            <Notice status="error" onRemove={() => setError(null)}>
              {error}
            </Notice>
          </div>
        )}
        <div className="sidebar-jlg-preview-canvas__content">
          <div className="sidebar-jlg-preview-canvas__preview" ref={previewRef} dangerouslySetInnerHTML={{ __html: html }} />
          {ctaIndex >= 0 && (
            <aside className="sidebar-jlg-preview-canvas__inspector">
              <Panel>
                <PanelBody title="CTA" initialOpen>
                  <ColorPicker
                    color={ctaColor}
                    onChangeComplete={(value: string | { hex?: string }) => {
                      const colorValue = typeof value === 'string' ? value : value.hex ?? ctaColor;
                      const path = `menu_items[${ctaIndex}].cta_button_color`;
                      setOption(path, colorValue);
                    }}
                  />
                </PanelBody>
              </Panel>
            </aside>
          )}
        </div>
        {loading && (
          <div className="sidebar-jlg-preview-canvas__loading" role="status" aria-live="polite">
            <Spinner />
          </div>
        )}
      </div>
    </div>
  );
};

export default PreviewCanvas;
