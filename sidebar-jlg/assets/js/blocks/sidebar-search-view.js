(() => {
    const isBrowserEnvironment = typeof window !== 'undefined' && typeof document !== 'undefined';

    const parseRgbColor = (color) => {
        if (typeof color !== 'string') {
            return null;
        }

        if (color === '' || color.toLowerCase() === 'transparent') {
            return null;
        }

        const match = color.match(/^rgba?\(([^)]+)\)$/i);
        if (!match) {
            return null;
        }

        const parts = match[1]
            .split(',')
            .map((value) => parseFloat(value.trim()))
            .filter((value) => !Number.isNaN(value));

        if (parts.length < 3) {
            return null;
        }

        const [r, g, b, a = 1] = parts;

        return {
            r: Math.min(Math.max(r, 0), 255),
            g: Math.min(Math.max(g, 0), 255),
            b: Math.min(Math.max(b, 0), 255),
            a: Math.min(Math.max(a, 0), 1),
        };
    };

    const relativeLuminance = (color) => {
        const channel = (value) => {
            const normalized = value / 255;

            if (normalized <= 0.03928) {
                return normalized / 12.92;
            }

            return Math.pow((normalized + 0.055) / 1.055, 2.4);
        };

        return (
            0.2126 * channel(color.r) +
            0.7152 * channel(color.g) +
            0.0722 * channel(color.b)
        );
    };

    const isTransparentColor = (color) => !color || color.a <= 0.05;

    const resolveAutoScheme = (element) => {
        if (!isBrowserEnvironment || !element) {
            return 'dark';
        }

        let current = element;

        while (current) {
            if (current instanceof Element) {
                const style = window.getComputedStyle(current);
                const parsed = parseRgbColor(style.backgroundColor);

                if (!isTransparentColor(parsed)) {
                    return relativeLuminance(parsed) < 0.5 ? 'dark' : 'light';
                }

                const parentNode = current.parentNode;
                if (parentNode instanceof ShadowRoot) {
                    current = parentNode;
                } else if (current.parentElement) {
                    current = current.parentElement;
                } else if (parentNode instanceof Document) {
                    current = parentNode.documentElement;
                } else {
                    current = parentNode;
                }

                continue;
            }

            if (current instanceof ShadowRoot) {
                current = current.host;
                continue;
            }

            if (current instanceof Document) {
                current = current.documentElement;
                continue;
            }

            break;
        }

        if (isBrowserEnvironment && window.matchMedia) {
            try {
                if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
                    return 'dark';
                }
            } catch (error) {
                // Ignore matchMedia errors.
            }
        }

        return 'light';
    };

    const applyColorScheme = (element) => {
        if (!element) {
            return;
        }

        const requested = element.getAttribute('data-sidebar-search-scheme') || 'auto';
        let resolved = requested;

        if (requested === 'auto') {
            resolved = resolveAutoScheme(element);
        }

        if (resolved !== 'light' && resolved !== 'dark') {
            resolved = 'dark';
        }

        if (element.getAttribute('data-sidebar-search-applied-scheme') !== resolved) {
            element.setAttribute('data-sidebar-search-applied-scheme', resolved);
        }

        ['light', 'dark'].forEach((scheme) => {
            const className = `sidebar-search--scheme-${scheme}`;
            if (scheme === resolved) {
                element.classList.add(className);
            } else {
                element.classList.remove(className);
            }
        });
    };

    const getEnvironmentClass = () => {
        if (typeof document === 'undefined') {
            return '';
        }

        const body = document.body;
        if (!body) {
            return '';
        }

        const editorClassCandidates = [
            'block-editor-page',
            'block-editor-iframe__body',
            'edit-site-visual-editor__body',
            'site-editor-iframe__body'
        ];

        const isEditor = editorClassCandidates.some((className) => body.classList.contains(className));

        if (isEditor) {
            return 'sidebar-search--editor';
        }

        return 'sidebar-search--frontend';
    };

    const environmentClass = getEnvironmentClass();

    const applyAlignmentProperty = (element) => {
        if (!element) {
            return;
        }

        const alignment = element.getAttribute('data-sidebar-search-align');
        if (!alignment) {
            return;
        }

        element.style.setProperty('--sidebar-search-alignment', alignment);
    };

    const applyEnvironmentClass = (element) => {
        if (!element || !environmentClass) {
            return;
        }

        element.classList.add(environmentClass);
    };

    const scan = (root = document) => {
        root.querySelectorAll('[data-sidebar-search-align]').forEach((node) => {
            applyAlignmentProperty(node);
            applyEnvironmentClass(node);
            applyColorScheme(node);
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan());
    } else {
        scan();
    }

    if ('MutationObserver' in window) {
        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.target instanceof HTMLElement) {
                    if (mutation.attributeName === 'data-sidebar-search-scheme') {
                        applyColorScheme(mutation.target);
                    }
                }

                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }

                    if (node.matches('[data-sidebar-search-align]')) {
                        applyAlignmentProperty(node);
                    }

                    if (node.matches('[data-sidebar-search-scheme]')) {
                        applyColorScheme(node);
                    }

                    scan(node);
                });
            });
        });

        observer.observe(document.documentElement, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-sidebar-search-scheme'] });
    }
})();
