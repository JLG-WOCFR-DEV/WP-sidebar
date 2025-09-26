document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('pro-sidebar');
    const hamburgerBtn = document.getElementById('hamburger-btn');
    const closeBtn = sidebar ? sidebar.querySelector('.close-sidebar-btn') : null;
    const overlay = document.getElementById('sidebar-overlay');

    if (!sidebar || !hamburgerBtn) {
        if (typeof sidebarSettings !== 'undefined' && sidebarSettings.debug_mode == '1') {
            console.error('Sidebar JLG: Sidebar or hamburger button not found.');
        }
        return;
    }

    // Appliquer la classe d'animation
    const animationType = (typeof sidebarSettings !== 'undefined' && sidebarSettings.animation_type) ? sidebarSettings.animation_type : 'slide-left';
    sidebar.classList.add(`animation-${animationType}`);

    const focusableElements = 'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])';
    const focusableContent = sidebar.querySelectorAll(focusableElements);
    const firstFocusableElement = focusableContent[0];
    const lastFocusableElement = focusableContent[focusableContent.length - 1];

    function openSidebar() {
        document.body.classList.add('sidebar-open');
        hamburgerBtn.classList.add('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        if (overlay) overlay.classList.add('is-visible');
        setTimeout(() => {
            if (firstFocusableElement) firstFocusableElement.focus();
        }, 100);
        document.addEventListener('keydown', trapFocus);
    }

    function closeSidebar() {
        const isSidebarOpen = document.body.classList.contains('sidebar-open');
        if (!isSidebarOpen) {
            return;
        }
        document.body.classList.remove('sidebar-open');
        hamburgerBtn.classList.remove('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        if (overlay) overlay.classList.remove('is-visible');
        hamburgerBtn.focus();
        document.removeEventListener('keydown', trapFocus);
    }

    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function trapFocus(e) {
        let isTabPressed = e.key === 'Tab' || e.keyCode === 9;
        if (!isTabPressed) return;

        if (e.shiftKey) { // shift + tab
            if (document.activeElement === firstFocusableElement) {
                if (lastFocusableElement) lastFocusableElement.focus();
                e.preventDefault();
            }
        } else { // tab
            if (document.activeElement === lastFocusableElement) {
                if (firstFocusableElement) firstFocusableElement.focus();
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
        const isDesktop = window.innerWidth >= 993;
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

    applyHoverEffect();
    window.addEventListener('resize', applyHoverEffect);
});
