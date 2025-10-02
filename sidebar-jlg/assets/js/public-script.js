document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('pro-sidebar');
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = sidebar ? sidebar.querySelector('.close-sidebar-btn') : null;
    const overlay = document.getElementById('sidebar-overlay');
    const openLabel = hamburgerBtn ? (hamburgerBtn.getAttribute('data-open-label') || hamburgerBtn.getAttribute('aria-label') || '') : '';
    const closeLabel = hamburgerBtn ? (hamburgerBtn.getAttribute('data-close-label') || openLabel) : '';

    function getMissingElementsMessage() {
        if (
            typeof sidebarSettings !== 'undefined' &&
            sidebarSettings.messages &&
            sidebarSettings.messages.missingElements
        ) {
            return sidebarSettings.messages.missingElements;
        }

        if (
            typeof wp !== 'undefined' &&
            wp.i18n &&
            typeof wp.i18n.__ === 'function'
        ) {
            return wp.i18n.__('Sidebar JLG : menu introuvable.', 'sidebar-jlg');
        }

        return 'Sidebar JLG : menu introuvable.';
    }

    if (!sidebar || !hamburgerBtn) {
        if (typeof sidebarSettings !== 'undefined' && sidebarSettings.debug_mode == '1') {
            console.error(getMissingElementsMessage());
        }
        return;
    }

    const sidebarPositionAttr = sidebar.getAttribute('data-position');
    const sidebarPosition = sidebarPositionAttr === 'right' ? 'right' : 'left';
    document.body.classList.remove('jlg-sidebar-position-left', 'jlg-sidebar-position-right');
    document.body.classList.add(`jlg-sidebar-position-${sidebarPosition}`);
    document.body.setAttribute('data-sidebar-position', sidebarPosition);
    hamburgerBtn.classList.remove('orientation-left', 'orientation-right');
    hamburgerBtn.classList.add(`orientation-${sidebarPosition}`);

    const prefersReducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    let isReducedMotion = prefersReducedMotionQuery ? prefersReducedMotionQuery.matches : false;

    // Appliquer la classe d'animation
    const animationType = (typeof sidebarSettings !== 'undefined' && sidebarSettings.animation_type) ? sidebarSettings.animation_type : 'slide-left';
    const animationClass = `animation-${animationType}`;
    const REDUCED_MOTION_CLASS = 'jlg-prefers-reduced-motion';
    const INTERACTIVE_HOVER_EFFECTS = new Set(['spotlight', 'glossy-tilt']);
    const MAX_TILT_DEGREES = 10;
    let cleanupHoverEffectListeners = null;

    function applyAnimationPreference() {
        document.documentElement.classList.toggle(REDUCED_MOTION_CLASS, isReducedMotion);

        if (isReducedMotion) {
            sidebar.classList.remove(animationClass);
        } else if (!sidebar.classList.contains(animationClass)) {
            sidebar.classList.add(animationClass);
        }
    }

    function handleReducedMotionChange(event) {
        isReducedMotion = event.matches;
        applyAnimationPreference();
        applyHoverEffect();
    }

    applyAnimationPreference();

    if (prefersReducedMotionQuery) {
        if (typeof prefersReducedMotionQuery.addEventListener === 'function') {
            prefersReducedMotionQuery.addEventListener('change', handleReducedMotionChange);
        } else if (typeof prefersReducedMotionQuery.addListener === 'function') {
            prefersReducedMotionQuery.addListener(handleReducedMotionChange);
        }
    }

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
    const SCROLL_LOCK_CLASS = 'sidebar-scroll-lock';

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

    function applyScrollLock() {
        if (window.innerWidth < DESKTOP_BREAKPOINT) {
            document.body.classList.add(SCROLL_LOCK_CLASS);
        } else {
            document.body.classList.remove(SCROLL_LOCK_CLASS);
        }
    }

    function releaseScrollLock() {
        document.body.classList.remove(SCROLL_LOCK_CLASS);
    }

    function openSidebar() {
        applyScrollLockCompensation();
        applyScrollLock();
        document.body.classList.add('sidebar-open');
        hamburgerBtn.classList.add('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        if (closeLabel) {
            hamburgerBtn.setAttribute('aria-label', closeLabel);
        }
        if (overlay) overlay.classList.add('is-visible');
        if (isReducedMotion) {
            focusFirstAvailableElement();
        } else {
            setTimeout(() => {
                focusFirstAvailableElement();
            }, 100);
        }
        document.addEventListener('keydown', trapFocus);
    }

    function closeSidebar(options = {}) {
        const { returnFocus = true } = options;
        const isSidebarOpen = document.body.classList.contains('sidebar-open');
        if (!isSidebarOpen) {
            return;
        }
        document.body.classList.remove('sidebar-open');
        releaseScrollLock();
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
                    if (isReducedMotion) {
                        closeSidebar();
                    } else {
                        setTimeout(closeSidebar, 50);
                    }
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
    function teardownHoverEffectListeners() {
        if (typeof cleanupHoverEffectListeners === 'function') {
            cleanupHoverEffectListeners();
            cleanupHoverEffectListeners = null;
        }
    }

    function resetInteractiveHoverState(element) {
        if (!element) {
            return;
        }

        element.style.removeProperty('--mouse-x');
        element.style.removeProperty('--mouse-y');
        element.style.removeProperty('--rotate-x');
        element.style.removeProperty('--rotate-y');
    }

    function applyHoverEffect() {
        teardownHoverEffectListeners();

        const isDesktop = window.innerWidth >= DESKTOP_BREAKPOINT;
        const hoverEffectDesktop = sidebar.getAttribute('data-hover-desktop');
        const hoverEffectMobile = sidebar.getAttribute('data-hover-mobile');

        // Nettoyer les anciennes classes d'effet
        sidebar.className = sidebar.className.replace(/\bhover-effect-\S+/g, '');

        if (isReducedMotion) {
            return;
        }

        const activeHoverEffect = isDesktop ? hoverEffectDesktop : hoverEffectMobile;

        if (isDesktop && hoverEffectDesktop && hoverEffectDesktop !== 'none') {
            sidebar.classList.add(`hover-effect-${hoverEffectDesktop}`);
        } else if (!isDesktop && hoverEffectMobile && hoverEffectMobile !== 'none') {
            sidebar.classList.add(`hover-effect-${hoverEffectMobile}`);
        }

        if (!activeHoverEffect || !INTERACTIVE_HOVER_EFFECTS.has(activeHoverEffect)) {
            return;
        }

        const sidebarLinks = Array.from(sidebar.querySelectorAll('.sidebar-menu a'));

        if (!sidebarLinks.length) {
            return;
        }

        const handlePointerMove = (event) => {
            const target = event.currentTarget;
            if (!target || typeof target.getBoundingClientRect !== 'function') {
                return;
            }

            const rect = target.getBoundingClientRect();
            if (!rect || !rect.width || !rect.height) {
                return;
            }

            const clientX = typeof event.clientX === 'number' ? event.clientX : 0;
            const clientY = typeof event.clientY === 'number' ? event.clientY : 0;
            const relativeX = (clientX - rect.left) / rect.width;
            const relativeY = (clientY - rect.top) / rect.height;

            const clampedX = Math.min(Math.max(relativeX, 0), 1);
            const clampedY = Math.min(Math.max(relativeY, 0), 1);

            const percentX = (clampedX * 100).toFixed(2) + '%';
            const percentY = (clampedY * 100).toFixed(2) + '%';
            target.style.setProperty('--mouse-x', percentX);
            target.style.setProperty('--mouse-y', percentY);

            const tiltX = ((0.5 - clampedY) * MAX_TILT_DEGREES * 2).toFixed(2) + 'deg';
            const tiltY = ((clampedX - 0.5) * MAX_TILT_DEGREES * 2).toFixed(2) + 'deg';
            target.style.setProperty('--rotate-x', tiltX);
            target.style.setProperty('--rotate-y', tiltY);
        };

        const handlePointerExit = (event) => {
            resetInteractiveHoverState(event.currentTarget);
        };

        const handlePointerUp = (event) => {
            if (event.pointerType && event.pointerType !== 'touch') {
                return;
            }
            resetInteractiveHoverState(event.currentTarget);
        };

        const handleTouchEnd = (event) => {
            resetInteractiveHoverState(event.currentTarget);
        };

        sidebarLinks.forEach((link) => {
            link.addEventListener('pointermove', handlePointerMove);
            link.addEventListener('pointerleave', handlePointerExit);
            link.addEventListener('pointercancel', handlePointerExit);
            link.addEventListener('pointerup', handlePointerUp);
            link.addEventListener('touchend', handleTouchEnd);
            link.addEventListener('touchcancel', handleTouchEnd);
        });

        cleanupHoverEffectListeners = () => {
            sidebarLinks.forEach((link) => {
                link.removeEventListener('pointermove', handlePointerMove);
                link.removeEventListener('pointerleave', handlePointerExit);
                link.removeEventListener('pointercancel', handlePointerExit);
                link.removeEventListener('pointerup', handlePointerUp);
                link.removeEventListener('touchend', handleTouchEnd);
                link.removeEventListener('touchcancel', handleTouchEnd);
                resetInteractiveHoverState(link);
            });
        };
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
            applyScrollLock();
        }
    }

    applyHoverEffect();
    window.addEventListener('resize', handleResize);
});
