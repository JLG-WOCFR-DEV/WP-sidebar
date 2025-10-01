document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('pro-sidebar');
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = sidebar ? sidebar.querySelector('.close-sidebar-btn') : null;
    const overlay = document.getElementById('sidebar-overlay');
    const openLabel = hamburgerBtn ? (hamburgerBtn.getAttribute('data-open-label') || hamburgerBtn.getAttribute('aria-label') || '') : '';
    const closeLabel = hamburgerBtn ? (hamburgerBtn.getAttribute('data-close-label') || openLabel) : '';

    if (!sidebar || !hamburgerBtn) {
        if (typeof sidebarSettings !== 'undefined' && sidebarSettings.debug_mode == '1') {
            console.error('Sidebar JLG: Sidebar or hamburger button not found.');
        }
        return;
    }

    // Appliquer la classe d'animation
    const animationType = (typeof sidebarSettings !== 'undefined' && sidebarSettings.animation_type) ? sidebarSettings.animation_type : 'slide-left';
    sidebar.classList.add(`animation-${animationType}`);

    const focusableSelector = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    const originalSidebarTabIndex = sidebar.hasAttribute('tabindex') ? sidebar.getAttribute('tabindex') : null;
    let cachedFocusableElements = [];

    function isAriaHidden(element) {
        const ariaHidden = element.getAttribute('aria-hidden');
        return ariaHidden !== null && ariaHidden !== 'false';
    }

    function isElementHiddenByStyles(element, computedStyle) {
        return computedStyle.display === 'none' || computedStyle.visibility === 'hidden';
    }

    function isElementVisible(element) {
        if (!element) return false;
        if (element.disabled || element.hidden || isAriaHidden(element)) {
            return false;
        }

        const computedStyle = window.getComputedStyle(element);
        if (isElementHiddenByStyles(element, computedStyle)) {
            return false;
        }

        if (typeof element.offsetParent !== 'undefined' && element.offsetParent === null) {
            const hiddenAncestor = element.closest('[hidden], [aria-hidden="true"]');
            if (hiddenAncestor && hiddenAncestor !== document.body) {
                return false;
            }
        }

        let parent = element.parentElement;
        while (parent && parent !== document.body) {
            if (parent.hidden || isAriaHidden(parent)) {
                return false;
            }
            const parentStyle = window.getComputedStyle(parent);
            if (isElementHiddenByStyles(parent, parentStyle)) {
                return false;
            }
            parent = parent.parentElement;
        }

        return true;
    }

    function getVisibleFocusableElements() {
        const allFocusable = Array.from(sidebar.querySelectorAll(focusableSelector));
        return allFocusable.filter(isElementVisible);
    }

    function refreshFocusableElements() {
        cachedFocusableElements = getVisibleFocusableElements();
        return cachedFocusableElements;
    }

    function focusFirstAvailableElement() {
        const focusableElements = refreshFocusableElements();
        if (focusableElements.length > 0) {
            focusableElements[0].focus();
            return;
        }

        if (!sidebar.hasAttribute('tabindex')) {
            sidebar.setAttribute('tabindex', '-1');
        }
        sidebar.focus({ preventScroll: true });
    }
    const DESKTOP_BREAKPOINT = 993;

    function getScrollbarWidth() {
        const scrollProbe = document.createElement('div');
        scrollProbe.style.position = 'absolute';
        scrollProbe.style.top = '-9999px';
        scrollProbe.style.width = '100px';
        scrollProbe.style.height = '100px';
        scrollProbe.style.overflow = 'scroll';
        document.body.appendChild(scrollProbe);
        const width = scrollProbe.offsetWidth - scrollProbe.clientWidth;
        document.body.removeChild(scrollProbe);
        return width;
    }

    function applyScrollLockCompensation() {
        const isDesktop = window.innerWidth >= DESKTOP_BREAKPOINT;
        const compensation = isDesktop ? getScrollbarWidth() : 0;
        document.body.style.setProperty('--sidebar-scrollbar-compensation', `${compensation}px`);
    }

    function clearScrollLockCompensation() {
        document.body.style.removeProperty('--sidebar-scrollbar-compensation');
    }

    function openSidebar() {
        applyScrollLockCompensation();
        document.body.classList.add('sidebar-open');
        hamburgerBtn.classList.add('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        if (closeLabel) {
            hamburgerBtn.setAttribute('aria-label', closeLabel);
        }
        if (overlay) overlay.classList.add('is-visible');
        setTimeout(() => {
            focusFirstAvailableElement();
        }, 100);
        document.addEventListener('keydown', trapFocus);
    }

    function closeSidebar(options = {}) {
        const { returnFocus = true } = options;
        const isSidebarOpen = document.body.classList.contains('sidebar-open');
        if (!isSidebarOpen) {
            return;
        }
        document.body.classList.remove('sidebar-open');
        hamburgerBtn.classList.remove('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        if (openLabel) {
            hamburgerBtn.setAttribute('aria-label', openLabel);
        }
        if (overlay) overlay.classList.remove('is-visible');
        clearScrollLockCompensation();
        if (returnFocus) {
            hamburgerBtn.focus();
        }
        document.removeEventListener('keydown', trapFocus);

        if (originalSidebarTabIndex === null) {
            sidebar.removeAttribute('tabindex');
        } else {
            sidebar.setAttribute('tabindex', originalSidebarTabIndex);
        }
    }

    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function trapFocus(e) {
        const isTabPressed = e.key === 'Tab' || e.keyCode === 9;
        if (!isTabPressed) return;

        const focusableElements = refreshFocusableElements();
        if (focusableElements.length === 0) {
            if (!sidebar.hasAttribute('tabindex')) {
                sidebar.setAttribute('tabindex', '-1');
            }
            sidebar.focus({ preventScroll: true });
            e.preventDefault();
            return;
        }

        const firstFocusableElement = focusableElements[0];
        const lastFocusableElement = focusableElements[focusableElements.length - 1];
        const activeElement = document.activeElement;

        if (e.shiftKey) {
            if (!sidebar.contains(activeElement) || activeElement === firstFocusableElement) {
                lastFocusableElement.focus();
                e.preventDefault();
            }
        } else {
            if (!sidebar.contains(activeElement) || activeElement === lastFocusableElement) {
                firstFocusableElement.focus();
                e.preventDefault();
            }
        }
    }

    hamburgerBtn.addEventListener('click', toggleSidebar);
    if (closeBtn) closeBtn.addEventListener('click', closeSidebar);
    if (overlay) overlay.addEventListener('click', closeSidebar);

    const closeOnClickTruthyValues = new Set([true, 1, '1', 'true']);
    const shouldCloseOnLinkClick = typeof sidebarSettings !== 'undefined'
        && closeOnClickTruthyValues.has(sidebarSettings.close_on_link_click);

    if (shouldCloseOnLinkClick) {
        const selectors = ['.sidebar-menu a', '.social-icons a'];
        selectors.forEach((selector) => {
            sidebar.querySelectorAll(selector).forEach((element) => {
                element.addEventListener('click', () => {
                    setTimeout(closeSidebar, 50);
                });
            });
        });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') {
            return;
        }

        if (!document.body.classList.contains('sidebar-open')) {
            return;
        }

        closeSidebar();
    });

    // Appliquer la classe d'effet de survol en fonction de la taille de l'écran
    function applyHoverEffect() {
        const isDesktop = window.innerWidth >= DESKTOP_BREAKPOINT;
        const hoverEffectDesktop = sidebar.getAttribute('data-hover-desktop');
        const hoverEffectMobile = sidebar.getAttribute('data-hover-mobile');
        
        // Nettoyer les anciennes classes d'effet
        sidebar.className = sidebar.className.replace(/\bhover-effect-\S+/g, '');

        if (isDesktop && hoverEffectDesktop && hoverEffectDesktop !== 'none') {
            sidebar.classList.add(`hover-effect-${hoverEffectDesktop}`);
        } else if (!isDesktop && hoverEffectMobile && hoverEffectMobile !== 'none') {
            sidebar.classList.add(`hover-effect-${hoverEffectMobile}`);
        }
    }

    function handleResize() {
        applyHoverEffect();

        if (!document.body.classList.contains('sidebar-open')) {
            return;
        }

        if (window.innerWidth >= DESKTOP_BREAKPOINT) {
            closeSidebar({ returnFocus: false });
        } else {
            applyScrollLockCompensation();
        }
    }

    applyHoverEffect();
    window.addEventListener('resize', handleResize);
});
