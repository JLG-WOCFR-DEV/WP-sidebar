(() => {
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

    const scan = (root = document) => {
        root.querySelectorAll('[data-sidebar-search-align]').forEach((node) => {
            applyAlignmentProperty(node);
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
