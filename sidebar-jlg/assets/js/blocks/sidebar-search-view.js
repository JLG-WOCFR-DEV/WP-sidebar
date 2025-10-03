(() => {
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
                mutation.addedNodes.forEach((node) => {
                    if (!(node instanceof HTMLElement)) {
                        return;
                    }

                    if (node.matches('[data-sidebar-search-align]')) {
                        applyAlignmentProperty(node);
                    }

                    scan(node);
                });
            });
        });

        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
})();
