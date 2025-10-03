(() => {
    const SCHEME_LIGHT_CLASS = 'sidebar-search--scheme-light';
    const SCHEME_DARK_CLASS = 'sidebar-search--scheme-dark';
    const SCHEME_DATA_ATTRIBUTE = 'data-sidebar-search-scheme';
    const SCHEME_AUTO_VALUE = 'auto';

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
            'site-editor-iframe__body',
        ];

        const isEditor = editorClassCandidates.some((className) => body.classList.contains(className));

        if (isEditor) {
            return 'sidebar-search--editor';
        }

        return 'sidebar-search--frontend';
    };

    const parseRgbColor = (value) => {
        if (typeof value !== 'string') {
            return null;
        }

        const match = value.match(/rgba?\(\s*([0-9.]+)\s*,\s*([0-9.]+)\s*,\s*([0-9.]+)/i);
        if (!match) {
            return null;
        }

        return {
            r: Number.parseFloat(match[1]),
            g: Number.parseFloat(match[2]),
            b: Number.parseFloat(match[3]),
        };
    };

    const getRelativeLuminance = ({ r, g, b }) => {
        const normalize = (channel) => {
            const value = channel / 255;

            if (value <= 0.03928) {
                return value / 12.92;
            }

            return ((value + 0.055) / 1.055) ** 2.4;
        };

        const red = normalize(r);
        const green = normalize(g);
        const blue = normalize(b);

        return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
    };

    const determineScheme = (element) => {
        if (!element || typeof window === 'undefined' || !window.getComputedStyle) {
            return null;
        }

        const declaredScheme = element.getAttribute(SCHEME_DATA_ATTRIBUTE);
        if (declaredScheme === 'light' || declaredScheme === 'dark') {
            return declaredScheme;
        }

        if (declaredScheme && declaredScheme !== SCHEME_AUTO_VALUE) {
            return null;
        }

        const computed = window.getComputedStyle(element);
        const parsed = parseRgbColor(computed?.color ?? '');

        if (!parsed) {
            return null;
        }

        const luminance = getRelativeLuminance(parsed);

        return luminance > 0.55 ? 'dark' : 'light';
    };

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

    const environmentClass = getEnvironmentClass();

    const applyEnvironmentClass = (element) => {
        if (!element || !environmentClass) {
            return;
        }

        element.classList.add(environmentClass);
    };

    const applySchemeClass = (element) => {
        if (!element) {
            return;
        }

        const scheme = determineScheme(element);
        if (!scheme) {
            return;
        }

        element.classList.remove(SCHEME_LIGHT_CLASS, SCHEME_DARK_CLASS);
        element.classList.add(scheme === 'dark' ? SCHEME_DARK_CLASS : SCHEME_LIGHT_CLASS);
    };

    const enhanceNode = (element) => {
        applyAlignmentProperty(element);
        applyEnvironmentClass(element);
        applySchemeClass(element);
    };

    const scan = (root = document) => {
        if (typeof document === 'undefined' || !root) {
            return;
        }

        const nodes = root.querySelectorAll?.('[data-sidebar-search-align]') ?? [];
        nodes.forEach((node) => {
            enhanceNode(node);
        });
    };

    const api = {
        applyAlignmentProperty,
        applyEnvironmentClass,
        applySchemeClass,
        determineScheme,
        scan,
    };

    if (typeof window !== 'undefined') {
        window.SidebarJlgSearchView = api;
    }

    if (typeof document === 'undefined') {
        return;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => scan());
    } else {
        scan();
    }

    if (typeof MutationObserver === 'undefined') {
        return;
    }

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) {
                    return;
                }

                if (node.matches('[data-sidebar-search-align]')) {
                    enhanceNode(node);
                    return;
                }

                scan(node);
            });
        });
    });

    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
