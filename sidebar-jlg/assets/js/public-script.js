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

    if (overlay) {
        const overlayIsVisible = overlay.classList.contains('is-visible');
        overlay.setAttribute('aria-hidden', overlayIsVisible ? 'false' : 'true');
    }

    const analyticsSettings = typeof sidebarSettings !== 'undefined' ? sidebarSettings.analytics : null;
    const analyticsConfig = analyticsSettings
        && analyticsSettings.enabled
        && analyticsSettings.endpoint
        && analyticsSettings.nonce
        && analyticsSettings.action
        ? {
            endpoint: analyticsSettings.endpoint,
            nonce: analyticsSettings.nonce,
            action: analyticsSettings.action,
            profileId: analyticsSettings.profile_id || analyticsSettings.profileId || 'default',
            isFallback: analyticsSettings.profile_is_fallback ? '1' : (analyticsSettings.is_fallback_profile ? '1' : '0'),
        }
        : null;

    const analyticsFactory = typeof window.sidebarJLGAnalyticsFactory === 'function'
        ? window.sidebarJLGAnalyticsFactory
        : null;

    const analyticsAdapter = analyticsConfig && analyticsFactory
        ? analyticsFactory(analyticsConfig)
        : null;

    const analyticsEnabled = !!(analyticsAdapter && analyticsAdapter.enabled);

    const supportsHighResolutionTime = typeof window !== 'undefined'
        && typeof window.performance !== 'undefined'
        && typeof window.performance.now === 'function';

    function getSessionTimestamp() {
        if (supportsHighResolutionTime) {
            return window.performance.now();
        }

        return Date.now();
    }

    let sessionStartTimestamp = null;
    let sessionOpenTarget = 'toggle_button';
    let sessionInteractionTotals = null;

    function getEmptySessionInteractions() {
        return {
            menu_link_click: 0,
            social_link_click: 0,
            cta_click: 0,
        };
    }

    function resetSessionTracking() {
        sessionStartTimestamp = null;
        sessionOpenTarget = 'toggle_button';
        sessionInteractionTotals = null;
    }

    function beginSession(target) {
        if (!analyticsEnabled) {
            return;
        }

        if (sessionStartTimestamp !== null) {
            finalizeSession('restart');
        }

        sessionStartTimestamp = getSessionTimestamp();
        sessionOpenTarget = typeof target === 'string' && target !== '' ? target : 'toggle_button';
        sessionInteractionTotals = getEmptySessionInteractions();
    }

    function recordSessionInteraction(key) {
        if (!analyticsEnabled || !sessionInteractionTotals || typeof key !== 'string' || key === '') {
            return;
        }

        if (!Object.prototype.hasOwnProperty.call(sessionInteractionTotals, key)) {
            sessionInteractionTotals[key] = 0;
        }

        sessionInteractionTotals[key] += 1;
    }

    function finalizeSession(reason) {
        if (!analyticsEnabled) {
            resetSessionTracking();
            return;
        }

        if (sessionStartTimestamp === null) {
            return;
        }

        const elapsed = Math.max(0, Math.round(getSessionTimestamp() - sessionStartTimestamp));
        const normalizedReason = typeof reason === 'string' && reason !== '' ? reason : 'user';
        let interactionsPayload;
        if (sessionInteractionTotals) {
            interactionsPayload = {};
            Object.keys(sessionInteractionTotals).forEach((key) => {
                const value = sessionInteractionTotals[key];
                if (typeof value === 'number' && value > 0) {
                    interactionsPayload[key] = value;
                }
            });
            if (Object.keys(interactionsPayload).length === 0) {
                interactionsPayload = undefined;
            }
        }

        const payload = {
            target: sessionOpenTarget || 'toggle_button',
            close_reason: normalizedReason,
            duration_ms: elapsed,
        };

        if (interactionsPayload) {
            payload.interactions = interactionsPayload;
        }

        dispatchAnalytics('sidebar_session', payload);

        resetSessionTracking();
    }

    const seenCtaElements = analyticsEnabled
        ? (typeof WeakSet === 'function' ? new WeakSet() : new Set())
        : null;
    const seenWidgetElements = analyticsEnabled
        ? (typeof WeakSet === 'function' ? new WeakSet() : new Set())
        : null;
    const interactedWidgetElements = analyticsEnabled
        ? (typeof WeakSet === 'function' ? new WeakSet() : new Set())
        : null;

    const sidebarInner = sidebar ? sidebar.querySelector('.sidebar-inner') : null;
    const rememberTruthyValues = new Set([true, 'true', 1, '1']);
    const shouldRememberState = typeof sidebarSettings !== 'undefined'
        && rememberTruthyValues.has(sidebarSettings.remember_last_state);
    const activeProfileId = typeof sidebarSettings !== 'undefined'
        && typeof sidebarSettings.active_profile_id === 'string'
        && sidebarSettings.active_profile_id !== ''
        ? sidebarSettings.active_profile_id
        : 'default';
    const storageKey = typeof sidebarSettings !== 'undefined'
        && typeof sidebarSettings.state_storage_key === 'string'
        && sidebarSettings.state_storage_key !== ''
        ? sidebarSettings.state_storage_key
        : `sidebar-jlg-state:${activeProfileId}`;
    const CTA_CLICKED_CLASS = 'menu-cta--clicked';
    const defaultPersistedState = {
        isOpen: false,
        scrollTop: 0,
        openSubmenus: [],
        clickedCtas: [],
        lastOpenSubmenu: null,
    };
    const persistentStorage = shouldRememberState ? resolvePersistentStorage() : null;
    let persistedState = persistentStorage ? loadPersistedState() : { ...defaultPersistedState };
    const rawGestureSettings = typeof sidebarSettings !== 'undefined'
        && sidebarSettings.touch_gestures
        && typeof sidebarSettings.touch_gestures === 'object'
            ? sidebarSettings.touch_gestures
            : {};
    const gestureTruthyValues = new Set([true, 'true', 1, '1']);
    const gestureSettings = {
        edgeSwipeEnabled: gestureTruthyValues.has(rawGestureSettings.edge_swipe_enabled),
        closeSwipeEnabled: gestureTruthyValues.has(rawGestureSettings.close_swipe_enabled),
        edgeSize: Math.max(0, Math.min(200, parseInt(rawGestureSettings.edge_size, 10) || 0)),
        minDistance: Math.max(30, Math.min(600, parseInt(rawGestureSettings.min_distance, 10) || 96)),
    };
    const isSidebarOnRight = typeof sidebarSettings !== 'undefined'
        && sidebarSettings.sidebar_position === 'right';
    let isRestoringState = false;
    let scrollPersistTimeout = null;
    const rawBehaviorTriggers = typeof sidebarSettings !== 'undefined'
        && sidebarSettings.behavior_triggers
        && typeof sidebarSettings.behavior_triggers === 'object'
        ? sidebarSettings.behavior_triggers
        : {};
    const autoOpenTimeDelay = Math.max(0, parseInt(rawBehaviorTriggers.time_delay, 10) || 0);
    const autoOpenScrollDepth = Math.max(
        0,
        Math.min(100, parseInt(rawBehaviorTriggers.scroll_depth, 10) || 0)
    );
    const autoOpenExitIntentEnabled = rawBehaviorTriggers.exit_intent === true
        || rawBehaviorTriggers.exit_intent === '1'
        || rawBehaviorTriggers.exit_intent === 1
        || rawBehaviorTriggers.exit_intent === 'true';
    const autoOpenInactivityDelay = Math.max(
        0,
        Math.min(1800, parseInt(rawBehaviorTriggers.inactivity_delay, 10) || 0)
    );
    let manualDismissed = false;
    let autoOpenTriggered = false;
    let autoOpenTimer = null;
    let autoOpenScrollListener = null;
    let autoOpenExitIntentListener = null;
    let autoOpenInactivityListener = null;
    let autoOpenInactivityTimer = null;
    const autoOpenInactivityEvents = ['mousemove', 'keydown', 'scroll', 'touchstart', 'pointerdown'];

    if (!shouldRememberState) {
        const fallbackStorage = resolvePersistentStorage();
        if (fallbackStorage) {
            try {
                fallbackStorage.removeItem(storageKey);
            } catch (error) {
                // Ignore storage cleanup errors.
            }
        }
    }

    function resolvePersistentStorage() {
        if (typeof window === 'undefined' || typeof window.localStorage === 'undefined') {
            return null;
        }

        try {
            const probeKey = `${storageKey}__probe`;
            window.localStorage.setItem(probeKey, '1');
            window.localStorage.removeItem(probeKey);
            return window.localStorage;
        } catch (error) {
            return null;
        }
    }

    function normalizePersistedState(rawState) {
        const normalized = { ...defaultPersistedState };
        if (!rawState || typeof rawState !== 'object') {
            return normalized;
        }

        if (rawState.isOpen === true) {
            normalized.isOpen = true;
        }

        if (typeof rawState.scrollTop === 'number' && !Number.isNaN(rawState.scrollTop)) {
            normalized.scrollTop = rawState.scrollTop;
        }

        if (Array.isArray(rawState.openSubmenus)) {
            normalized.openSubmenus = Array.from(new Set(rawState.openSubmenus.filter((value) => typeof value === 'string' && value !== '')));
        }

        if (Array.isArray(rawState.clickedCtas)) {
            normalized.clickedCtas = Array.from(new Set(rawState.clickedCtas.filter((value) => typeof value === 'string' && value !== '')));
        }

        if (typeof rawState.lastOpenSubmenu === 'string' && rawState.lastOpenSubmenu !== '') {
            normalized.lastOpenSubmenu = rawState.lastOpenSubmenu;
        }

        return normalized;
    }

    function loadPersistedState() {
        if (!persistentStorage) {
            return { ...defaultPersistedState };
        }

        try {
            const raw = persistentStorage.getItem(storageKey);
            if (typeof raw !== 'string' || raw === '') {
                return { ...defaultPersistedState };
            }

            const parsed = JSON.parse(raw);
            return normalizePersistedState(parsed);
        } catch (error) {
            return { ...defaultPersistedState };
        }
    }

    function persistState(partialState) {
        if (!partialState || typeof partialState !== 'object') {
            return;
        }

        const merged = normalizePersistedState({
            ...persistedState,
            ...partialState,
        });

        persistedState = merged;

        if (!shouldRememberState || !persistentStorage) {
            return;
        }

        try {
            persistentStorage.setItem(storageKey, JSON.stringify(merged));
        } catch (error) {
            // Ignore persistence errors (private browsing, quota, etc.).
        }
    }

    function cancelAutoOpenTimer() {
        if (autoOpenTimer !== null) {
            window.clearTimeout(autoOpenTimer);
            autoOpenTimer = null;
        }
    }

    function cancelAutoOpenInactivityTimer() {
        if (autoOpenInactivityTimer !== null) {
            window.clearTimeout(autoOpenInactivityTimer);
            autoOpenInactivityTimer = null;
        }
    }

    function teardownExitIntentListener() {
        if (!autoOpenExitIntentListener) {
            return;
        }

        document.removeEventListener('mouseout', autoOpenExitIntentListener);
        autoOpenExitIntentListener = null;
    }

    function teardownInactivityListeners() {
        if (autoOpenInactivityListener) {
            autoOpenInactivityEvents.forEach((eventName) => {
                document.removeEventListener(eventName, autoOpenInactivityListener);
            });
            autoOpenInactivityListener = null;
        }

        cancelAutoOpenInactivityTimer();
    }

    function teardownAutoOpenTriggers() {
        cancelAutoOpenTimer();

        if (autoOpenScrollListener) {
            window.removeEventListener('scroll', autoOpenScrollListener);
            autoOpenScrollListener = null;
        }

        teardownExitIntentListener();
        teardownInactivityListeners();
    }

    function logAutoOpen(reason) {
        if (typeof sidebarSettings === 'undefined' || sidebarSettings.debug_mode !== '1') {
            return;
        }

        if (typeof console !== 'undefined' && typeof console.info === 'function') {
            console.info(`Sidebar JLG auto-open trigger: ${reason}`);
        }
    }

    function canAutoOpen() {
        if (autoOpenTriggered || manualDismissed) {
            return false;
        }

        if (document.body.classList.contains('sidebar-open')) {
            return false;
        }

        if (persistedState.isOpen) {
            return false;
        }

        return true;
    }

    function triggerAutoOpen(reason) {
        if (!canAutoOpen()) {
            return;
        }

        autoOpenTriggered = true;
        teardownAutoOpenTriggers();
        logAutoOpen(reason);
        const analyticsReason = typeof reason === 'string' && reason !== '' ? `auto_${reason}` : 'auto_trigger';
        openSidebar({ analyticsTarget: analyticsReason });
    }

    function handleAutoOpenScroll() {
        if (!canAutoOpen()) {
            teardownAutoOpenTriggers();
            return;
        }

        const doc = document.documentElement || document.body;
        if (!doc) {
            return;
        }

        const viewportHeight = typeof window.innerHeight === 'number'
            ? window.innerHeight
            : doc.clientHeight || 0;
        const scrollHeight = doc.scrollHeight || 0;
        const scrollableHeight = Math.max(scrollHeight - viewportHeight, 0);
        const scrollTop = typeof window.pageYOffset === 'number'
            ? window.pageYOffset
            : (doc.scrollTop || (document.body ? document.body.scrollTop : 0) || 0);

        const progress = scrollableHeight <= 0
            ? 100
            : (scrollTop / scrollableHeight) * 100;

        if (progress >= autoOpenScrollDepth) {
            triggerAutoOpen('scroll');
        }
    }

    function ensureExitIntentListener() {
        if (!autoOpenExitIntentEnabled || autoOpenExitIntentListener) {
            return;
        }

        autoOpenExitIntentListener = (event) => {
            if (!canAutoOpen()) {
                teardownExitIntentListener();
                return;
            }

            const relatedTarget = event.relatedTarget;
            if (relatedTarget && relatedTarget !== document.documentElement && relatedTarget !== document.body) {
                return;
            }

            const pointerType = typeof event.pointerType === 'string' ? event.pointerType : '';
            if (pointerType && pointerType !== 'mouse') {
                return;
            }

            if (typeof event.clientY === 'number' && event.clientY <= 0) {
                triggerAutoOpen('exit');
            }
        };

        document.addEventListener('mouseout', autoOpenExitIntentListener);
    }

    function scheduleInactivityTimer() {
        cancelAutoOpenInactivityTimer();

        if (autoOpenInactivityDelay <= 0 || !canAutoOpen()) {
            return;
        }

        autoOpenInactivityTimer = window.setTimeout(() => {
            autoOpenInactivityTimer = null;
            triggerAutoOpen('inactivity');
        }, autoOpenInactivityDelay * 1000);
    }

    function ensureInactivityListeners() {
        if (autoOpenInactivityDelay <= 0 || autoOpenInactivityListener) {
            return;
        }

        autoOpenInactivityListener = () => {
            if (!canAutoOpen()) {
                cancelAutoOpenInactivityTimer();
                return;
            }

            scheduleInactivityTimer();
        };

        autoOpenInactivityEvents.forEach((eventName) => {
            document.addEventListener(eventName, autoOpenInactivityListener);
        });

        scheduleInactivityTimer();
    }

    function setupAutoOpenTriggers() {
        if (autoOpenTriggered) {
            return;
        }

        const hasAnyTrigger = autoOpenTimeDelay > 0
            || autoOpenScrollDepth > 0
            || autoOpenExitIntentEnabled
            || autoOpenInactivityDelay > 0;

        if (!hasAnyTrigger) {
            return;
        }

        if (!canAutoOpen()) {
            autoOpenTriggered = document.body.classList.contains('sidebar-open') || persistedState.isOpen;
            return;
        }

        if (autoOpenTimeDelay > 0) {
            cancelAutoOpenTimer();
            autoOpenTimer = window.setTimeout(() => {
                autoOpenTimer = null;
                triggerAutoOpen('timer');
            }, autoOpenTimeDelay * 1000);
        }

        if (autoOpenScrollDepth > 0 && !autoOpenScrollListener) {
            autoOpenScrollListener = () => {
                handleAutoOpenScroll();
            };
            window.addEventListener('scroll', autoOpenScrollListener, { passive: true });
            handleAutoOpenScroll();
        }

        if (autoOpenExitIntentEnabled) {
            ensureExitIntentListener();
        }

        if (autoOpenInactivityDelay > 0) {
            ensureInactivityListeners();
        }
    }

    function dispatchAnalytics(eventType, context = {}) {
        if (!analyticsEnabled || !analyticsAdapter || typeof analyticsAdapter.dispatch !== 'function') {
            return;
        }

        analyticsAdapter.dispatch(eventType, context);
    }

    function getCtaLabel(element, fallbackIndex) {
        if (!element || typeof element.getAttribute !== 'function') {
            return fallbackIndex ? `cta-${fallbackIndex}` : 'cta';
        }

        const explicit = element.getAttribute('data-cta-analytics');
        if (explicit && explicit.trim() !== '') {
            return explicit.trim();
        }

        if (element.id && element.id.trim() !== '') {
            return element.id.trim();
        }

        const indexAttr = element.getAttribute('data-analytics-index') || fallbackIndex;
        return indexAttr ? `cta-${indexAttr}` : 'cta';
    }

    function markCtaView(element, overrideLabel) {
        if (!analyticsEnabled || !seenCtaElements || !element) {
            return;
        }

        if (typeof seenCtaElements.has === 'function' && seenCtaElements.has(element)) {
            return;
        }

        if (typeof seenCtaElements.add === 'function') {
            seenCtaElements.add(element);
        }

        const label = overrideLabel || getCtaLabel(element);
        dispatchAnalytics('cta_view', { target: label });
    }

    function collectOpenSubmenuIds() {
        if (!Array.isArray(menuItemsWithChildren)) {
            return [];
        }

        const openIds = [];
        menuItemsWithChildren.forEach((menuItem) => {
            const submenu = findDirectChild(menuItem, SUBMENU_SELECTOR);
            const toggle = findDirectChild(menuItem, SUBMENU_TOGGLE_SELECTOR);
            if (!submenu || !toggle) {
                return;
            }

            if (toggle.getAttribute('aria-expanded') === 'true' && typeof submenu.id === 'string' && submenu.id !== '') {
                openIds.push(submenu.id);
            }
        });

        return openIds;
    }

    function persistOpenSubmenus(lastOpenedId) {
        if (!shouldRememberState) {
            return;
        }

        const openIds = collectOpenSubmenuIds();
        let nextLast = persistedState.lastOpenSubmenu;

        if (typeof lastOpenedId === 'string' && lastOpenedId !== '') {
            nextLast = lastOpenedId;
        } else if (!openIds.includes(nextLast || '')) {
            nextLast = openIds.length > 0 ? openIds[openIds.length - 1] : null;
        }

        persistState({
            openSubmenus: openIds,
            lastOpenSubmenu: nextLast,
        });
    }

    function scheduleScrollPersistence() {
        if (!shouldRememberState || !sidebarInner) {
            return;
        }

        if (scrollPersistTimeout !== null) {
            clearTimeout(scrollPersistTimeout);
        }

        scrollPersistTimeout = window.setTimeout(() => {
            scrollPersistTimeout = null;
            persistState({ scrollTop: sidebarInner.scrollTop || 0 });
        }, 150);
    }

    function applyClickedCtas() {
        if (!Array.isArray(persistedState.clickedCtas) || persistedState.clickedCtas.length === 0) {
            return;
        }

        persistedState.clickedCtas.forEach((ctaId) => {
            if (typeof ctaId !== 'string' || ctaId === '') {
                return;
            }

            const ctaElement = sidebar.querySelector(`.menu-cta[data-cta-id="${ctaId}"]`);
            if (!ctaElement) {
                return;
            }

            ctaElement.classList.add(CTA_CLICKED_CLASS);
            ctaElement.setAttribute('data-cta-clicked', 'true');
        });
    }

    function markCtaAsClicked(ctaElement) {
        if (!ctaElement) {
            return;
        }

        ctaElement.classList.add(CTA_CLICKED_CLASS);
        ctaElement.setAttribute('data-cta-clicked', 'true');

        if (!shouldRememberState) {
            return;
        }

        const ctaId = ctaElement.getAttribute('data-cta-id');
        if (!ctaId || typeof ctaId !== 'string' || ctaId === '') {
            return;
        }

        const updated = new Set(persistedState.clickedCtas || []);
        updated.add(ctaId);
        persistState({ clickedCtas: Array.from(updated) });
    }

    function getWidgetAnalyticsTarget(element) {
        if (!element) {
            return 'widget';
        }

        const explicitId = element.getAttribute('data-widget-id');
        if (explicitId && explicitId.trim() !== '') {
            return explicitId.trim();
        }

        const type = element.getAttribute('data-widget-type');
        if (type && type.trim() !== '') {
            return type.trim();
        }

        return 'widget';
    }

    function markWidgetView(element) {
        if (!analyticsEnabled || !seenWidgetElements || !element) {
            return;
        }

        if (typeof seenWidgetElements.has === 'function' && seenWidgetElements.has(element)) {
            return;
        }

        if (typeof seenWidgetElements.add === 'function') {
            seenWidgetElements.add(element);
        }

        const eventName = element.getAttribute('data-widget-view-event');
        if (eventName && eventName.trim() !== '') {
            dispatchAnalytics(eventName.trim(), { target: getWidgetAnalyticsTarget(element) });
        }
    }

    function markWidgetInteraction(element) {
        if (!analyticsEnabled || !interactedWidgetElements || !element) {
            return;
        }

        if (typeof interactedWidgetElements.has === 'function' && interactedWidgetElements.has(element)) {
            return;
        }

        if (typeof interactedWidgetElements.add === 'function') {
            interactedWidgetElements.add(element);
        }

        const eventName = element.getAttribute('data-widget-interaction-event');
        if (eventName && eventName.trim() !== '') {
            dispatchAnalytics(eventName.trim(), { target: getWidgetAnalyticsTarget(element) });
            recordSessionInteraction('widget_interaction');
        }
    }

    function dispatchWidgetConversion(element, detail = {}) {
        if (!analyticsEnabled || !element) {
            return;
        }

        const eventName = element.getAttribute('data-widget-conversion-event');
        if (!eventName || eventName.trim() === '') {
            return;
        }

        const payload = { target: getWidgetAnalyticsTarget(element) };
        if (detail && typeof detail === 'object') {
            Object.keys(detail).forEach((key) => {
                if (detail[key] !== undefined && detail[key] !== null) {
                    payload[key] = detail[key];
                }
            });
        }

        dispatchAnalytics(eventName.trim(), payload);
        recordSessionInteraction('widget_conversion');
    }

    function restorePersistedState() {
        if (!shouldRememberState) {
            return;
        }

        if (Array.isArray(persistedState.openSubmenus) && persistedState.openSubmenus.length > 0) {
            isRestoringState = true;
            persistedState.openSubmenus.forEach((submenuId) => {
                if (typeof submenuId !== 'string' || submenuId === '') {
                    return;
                }

                const submenu = document.getElementById(submenuId);
                if (!submenu) {
                    return;
                }

                const menuItem = submenu.closest('.menu-item');
                if (!menuItem) {
                    return;
                }

                const toggle = findDirectChild(menuItem, SUBMENU_TOGGLE_SELECTOR);
                if (!toggle) {
                    return;
                }

                setMenuItemState(menuItem, submenu, toggle, true);
            });
            isRestoringState = false;
        }

        applyClickedCtas();

        if (sidebarInner && typeof persistedState.scrollTop === 'number') {
            sidebarInner.scrollTop = persistedState.scrollTop;
        }

        if (persistedState.isOpen) {
            openSidebar({ skipFocus: true, skipAnalytics: true });
        }

        persistOpenSubmenus(persistedState.lastOpenSubmenu || null);
    }

    const sidebarPositionAttr = sidebar.getAttribute('data-position');
    const sidebarPosition = sidebarPositionAttr === 'right' ? 'right' : 'left';
    document.body.classList.remove('jlg-sidebar-position-left', 'jlg-sidebar-position-right');
    document.body.classList.add(`jlg-sidebar-position-${sidebarPosition}`);
    document.body.dataset.sidebarPosition = sidebarPosition;
    hamburgerBtn.classList.remove('orientation-left', 'orientation-right');
    hamburgerBtn.classList.add(`orientation-${sidebarPosition}`);
    hamburgerBtn.dataset.position = sidebarPosition;

    const prefersReducedMotionQuery = typeof window.matchMedia === 'function'
        ? window.matchMedia('(prefers-reduced-motion: reduce)')
        : null;
    let isReducedMotion = prefersReducedMotionQuery ? prefersReducedMotionQuery.matches : false;
    const sliderControllers = [];

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
        sliderControllers.forEach((controller) => {
            if (controller && typeof controller.handleReducedMotionChange === 'function') {
                controller.handleReducedMotionChange(isReducedMotion);
            }
        });
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
    const DESKTOP_BREAKPOINT = 993;
    const GESTURE_POINTER_TYPES = new Set(['touch', 'pen']);
    const GESTURE_VERTICAL_RATIO_LIMIT = 0.6;
    const supportsPointerEvents = typeof window !== 'undefined' && typeof window.PointerEvent === 'function';
    let activeTouchGesture = null;

    document.body.classList.add('sidebar-js-enhanced');

    let ctaObserver = null;
    let widgetObserver = null;
    if (analyticsEnabled) {
        const ctaBlocks = Array.from(sidebar.querySelectorAll('.menu-cta[data-cta-analytics]'));
        if (ctaBlocks.length) {
            ctaBlocks.forEach((element, index) => {
                if (element && typeof element.setAttribute === 'function') {
                    element.setAttribute('data-analytics-index', String(index + 1));
                }
            });

            if (typeof window !== 'undefined' && typeof window.IntersectionObserver === 'function') {
                ctaObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry || !entry.isIntersecting) {
                            return;
                        }
                        const target = entry.target;
                        markCtaView(target);
                        if (ctaObserver && typeof ctaObserver.unobserve === 'function') {
                            ctaObserver.unobserve(target);
                        }
                    });
                }, { threshold: 0.5 });

                ctaBlocks.forEach((element) => {
                    ctaObserver.observe(element);
                });
            } else {
                ctaBlocks.forEach((element) => {
                    markCtaView(element);
                });
            }
        }

        const widgetBlocks = Array.from(sidebar.querySelectorAll('.sidebar-widget'));
        if (widgetBlocks.length) {
            if (typeof window !== 'undefined' && typeof window.IntersectionObserver === 'function') {
                widgetObserver = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (!entry || !entry.isIntersecting) {
                            return;
                        }

                        const target = entry.target;
                        markWidgetView(target);
                        if (widgetObserver && typeof widgetObserver.unobserve === 'function') {
                            widgetObserver.unobserve(target);
                        }
                    });
                }, { threshold: 0.4 });

                widgetBlocks.forEach((element) => {
                    const existingId = element.getAttribute('data-widget-id');
                    if (!existingId || existingId.trim() === '') {
                        element.setAttribute('data-widget-id', `widget-${Math.random().toString(36).slice(2)}`);
                    }

                    widgetObserver.observe(element);
                });
            } else {
                widgetBlocks.forEach((element) => {
                    const existingId = element.getAttribute('data-widget-id');
                    if (!existingId || existingId.trim() === '') {
                        element.setAttribute('data-widget-id', `widget-${Math.random().toString(36).slice(2)}`);
                    }

                    markWidgetView(element);
                });
            }
        }
    }

    function setupSliderWidgets() {
        const sliderWidgets = Array.from(sidebar.querySelectorAll('.sidebar-widget--slider'));
        if (!sliderWidgets.length) {
            return;
        }

        sliderWidgets.forEach((widget) => {
            if (!widget) {
                return;
            }

            const list = widget.querySelector('.sidebar-widget__slides');
            const slides = list ? Array.from(list.querySelectorAll('.sidebar-widget__slide')) : [];
            const prevButton = widget.querySelector('[data-slider-action="prev"]');
            const nextButton = widget.querySelector('[data-slider-action="next"]');
            const toggleButton = widget.querySelector('[data-slider-action="toggle"]');
            const statusElement = widget.querySelector('[data-slider-status]');
            const totalSlides = slides.length;

            if (!list || totalSlides === 0) {
                if (prevButton) {
                    prevButton.disabled = true;
                }
                if (nextButton) {
                    nextButton.disabled = true;
                }
                if (toggleButton) {
                    toggleButton.disabled = true;
                }
                if (statusElement) {
                    statusElement.textContent = '';
                }
                return;
            }

            let currentIndex = 0;
            let autoplayTimer = null;
            const autoplayConfigured = widget.getAttribute('data-widget-autoplay') === 'true';
            let autoplayDelay = parseInt(widget.getAttribute('data-widget-autoplay-delay'), 10);
            if (!Number.isFinite(autoplayDelay) || autoplayDelay < 1000) {
                autoplayDelay = 4500;
            }

            let isUserPaused = !autoplayConfigured;
            let isFocusPaused = false;
            let isPreferencePaused = isReducedMotion;

            const statusTemplate = widget.getAttribute('data-slider-status-template') || '';
            const liveWhenPlaying = widget.getAttribute('data-slider-live-playing') || 'off';
            const liveWhenPaused = widget.getAttribute('data-slider-live-paused') || 'polite';
            const interactionEventName = widget.getAttribute('data-widget-interaction-event');
            const analyticsTarget = getWidgetAnalyticsTarget(widget);

            const baseToggleLabel = toggleButton ? (toggleButton.textContent || '') : '';
            const toggleLabels = {
                play: toggleButton ? (toggleButton.getAttribute('data-label-play') || baseToggleLabel || 'Lecture') : 'Lecture',
                pause: toggleButton ? (toggleButton.getAttribute('data-label-pause') || baseToggleLabel || 'Pause') : 'Pause',
            };

            function formatStatus(current, total) {
                const template = statusTemplate || 'Diapositive %1$s sur %2$s';
                return template.replace('%1$s', String(current)).replace('%2$s', String(total));
            }

            function updateStatus() {
                if (!statusElement) {
                    return;
                }
                statusElement.textContent = formatStatus(currentIndex + 1, totalSlides);
            }

            function setSlide(index) {
                const normalized = ((index % totalSlides) + totalSlides) % totalSlides;
                currentIndex = normalized;
                slides.forEach((slide, slideIndex) => {
                    const isActive = slideIndex === normalized;
                    slide.classList.toggle('is-active', isActive);
                    slide.setAttribute('aria-hidden', isActive ? 'false' : 'true');
                });
                const activeSlide = slides[normalized];
                if (list && activeSlide && activeSlide.id) {
                    list.setAttribute('aria-activedescendant', activeSlide.id);
                }
                updateStatus();
                widget.setAttribute('data-slider-current-index', String(normalized));
            }

            function updateControlAvailability() {
                const disableNav = totalSlides <= 1;
                if (prevButton) {
                    prevButton.disabled = disableNav;
                }
                if (nextButton) {
                    nextButton.disabled = disableNav;
                }
                if (toggleButton) {
                    toggleButton.disabled = totalSlides <= 1;
                }
            }

            function shouldAutoplay() {
                if (totalSlides <= 1) {
                    return false;
                }
                if (isUserPaused) {
                    return false;
                }
                if (isFocusPaused) {
                    return false;
                }
                if (isPreferencePaused) {
                    return false;
                }
                return true;
            }

            function clearAutoplayTimer() {
                if (autoplayTimer) {
                    window.clearTimeout(autoplayTimer);
                    autoplayTimer = null;
                }
            }

            function updateToggleButton() {
                if (!toggleButton) {
                    return;
                }
                const playing = shouldAutoplay();
                const pauseLabel = toggleLabels.pause || toggleLabels.play;
                const playLabel = toggleLabels.play || toggleLabels.pause;
                toggleButton.textContent = playing ? pauseLabel : playLabel;
                toggleButton.setAttribute('aria-pressed', playing ? 'true' : 'false');
                toggleButton.setAttribute('data-slider-state', playing ? 'playing' : 'paused');
            }

            function syncAutoplayState() {
                clearAutoplayTimer();
                updateControlAvailability();
                updateToggleButton();
                const playing = shouldAutoplay();
                widget.setAttribute('data-slider-state', playing ? 'playing' : 'paused');
                widget.setAttribute('aria-live', playing ? liveWhenPlaying : liveWhenPaused);
                if (playing) {
                    autoplayTimer = window.setTimeout(() => {
                        moveToIndex(currentIndex + 1, { action: 'autoplay', user: false });
                    }, autoplayDelay);
                }
            }

            function notifyAnalytics(action) {
                recordSessionInteraction('widget_slider_control');
                if (!analyticsEnabled || !interactionEventName) {
                    return;
                }
                dispatchAnalytics(interactionEventName, {
                    target: analyticsTarget,
                    action,
                    slide_index: currentIndex + 1,
                    total_slides: totalSlides,
                });
            }

            function moveToIndex(index, options = {}) {
                if (!totalSlides) {
                    return;
                }
                const normalized = ((index % totalSlides) + totalSlides) % totalSlides;
                const { user = false, action } = options;
                setSlide(normalized);
                if (user) {
                    markWidgetInteraction(widget);
                    if (typeof action === 'string' && action !== '') {
                        notifyAnalytics(action);
                    } else {
                        notifyAnalytics('navigate');
                    }
                }
                syncAutoplayState();
            }

            function pauseForInteraction() {
                if (!isFocusPaused) {
                    isFocusPaused = true;
                    syncAutoplayState();
                }
            }

            function resumeFromInteraction() {
                if (isFocusPaused) {
                    isFocusPaused = false;
                    syncAutoplayState();
                }
            }

            if (prevButton) {
                prevButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    moveToIndex(currentIndex - 1, { user: true, action: 'previous' });
                });
            }

            if (nextButton) {
                nextButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    moveToIndex(currentIndex + 1, { user: true, action: 'next' });
                });
            }

            if (toggleButton) {
                toggleButton.addEventListener('click', (event) => {
                    event.preventDefault();
                    const playing = shouldAutoplay();
                    if (playing) {
                        isUserPaused = true;
                        notifyAnalytics('pause');
                    } else {
                        isUserPaused = false;
                        isPreferencePaused = false;
                        notifyAnalytics('play');
                    }
                    markWidgetInteraction(widget);
                    syncAutoplayState();
                });
            }

            if (list) {
                list.addEventListener('keydown', (event) => {
                    if (event.defaultPrevented) {
                        return;
                    }
                    switch (event.key) {
                        case 'ArrowRight':
                        case 'ArrowDown':
                            event.preventDefault();
                            moveToIndex(currentIndex + 1, { user: true, action: 'next' });
                            break;
                        case 'ArrowLeft':
                        case 'ArrowUp':
                            event.preventDefault();
                            moveToIndex(currentIndex - 1, { user: true, action: 'previous' });
                            break;
                        case 'Home':
                            event.preventDefault();
                            moveToIndex(0, { user: true, action: 'first' });
                            break;
                        case 'End':
                            event.preventDefault();
                            moveToIndex(totalSlides - 1, { user: true, action: 'last' });
                            break;
                        case ' ': // Space
                        case 'Spacebar':
                            if (toggleButton) {
                                event.preventDefault();
                                toggleButton.click();
                            }
                            break;
                        default:
                            break;
                    }
                });
            }

            widget.addEventListener('focusin', pauseForInteraction);
            widget.addEventListener('focusout', () => {
                window.setTimeout(() => {
                    if (!widget.contains(document.activeElement)) {
                        resumeFromInteraction();
                    }
                }, 50);
            });
            widget.addEventListener('mouseenter', pauseForInteraction);
            widget.addEventListener('mouseleave', () => {
                resumeFromInteraction();
            });
            widget.addEventListener('touchstart', pauseForInteraction, { passive: true });
            widget.addEventListener('touchend', () => {
                window.setTimeout(resumeFromInteraction, 400);
            });
            widget.addEventListener('touchcancel', () => {
                window.setTimeout(resumeFromInteraction, 400);
            });

            setSlide(currentIndex);
            syncAutoplayState();
            sliderControllers.push({
                handleReducedMotionChange(nextValue) {
                    isPreferencePaused = nextValue;
                    syncAutoplayState();
                },
            });
        });
    }

    setupSliderWidgets();

    const SUBMENU_OPEN_CLASS = 'is-open';
    const SUBMENU_TOGGLE_SELECTOR = 'button.submenu-toggle';
    const SUBMENU_SELECTOR = 'ul.submenu';
    const LABEL_EXPAND_ATTR = 'data-label-expand';
    const LABEL_COLLAPSE_ATTR = 'data-label-collapse';
    const menuItemsWithChildren = Array.from(sidebar.querySelectorAll('.menu-item-has-children, .has-submenu-toggle'));
    const supportsResizeObserver = typeof window.ResizeObserver === 'function';
    const submenuResizeObservers = supportsResizeObserver ? new Map() : null;

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

    function isDesktopViewport() {
        return window.innerWidth >= DESKTOP_BREAKPOINT;
    }

    function findDirectChild(element, selector) {
        if (!element || !element.children) {
            return null;
        }

        for (let i = 0; i < element.children.length; i += 1) {
            const child = element.children[i];
            if (child.matches && child.matches(selector)) {
                return child;
            }
        }

        return null;
    }

    function applyToggleLabel(toggle, isOpen) {
        if (!toggle) {
            return;
        }

        const label = toggle.getAttribute(isOpen ? LABEL_COLLAPSE_ATTR : LABEL_EXPAND_ATTR);
        if (!label) {
            return;
        }

        toggle.setAttribute('aria-label', label);

        const srText = toggle.querySelector('.screen-reader-text');
        if (srText) {
            srText.textContent = label;
        }
    }

    function applySubmenuHeight(submenu) {
        if (!submenu) {
            return;
        }

        const height = submenu.scrollHeight;
        submenu.style.setProperty('--submenu-max-height', `${height}px`);
    }

    function observeSubmenuSize(submenu) {
        if (!supportsResizeObserver || !submenu) {
            return;
        }

        if (submenuResizeObservers.has(submenu)) {
            return;
        }

        const observer = new ResizeObserver(() => {
            if (submenu.classList.contains(SUBMENU_OPEN_CLASS)) {
                applySubmenuHeight(submenu);
            }
        });

        observer.observe(submenu);
        submenuResizeObservers.set(submenu, observer);
    }

    function unobserveSubmenuSize(submenu) {
        if (!supportsResizeObserver || !submenu) {
            return;
        }

        const observer = submenuResizeObservers.get(submenu);
        if (!observer) {
            return;
        }

        observer.disconnect();
        submenuResizeObservers.delete(submenu);
    }

    function handleSubmenuTransitionEnd(event) {
        if (!event || event.propertyName !== 'max-height') {
            return;
        }

        const submenu = event.currentTarget;
        if (!submenu || !(submenu instanceof HTMLElement)) {
            return;
        }

        if (submenu.classList.contains(SUBMENU_OPEN_CLASS)) {
            submenu.style.removeProperty('--submenu-max-height');
        }
    }

    function collapseSiblingSubmenus(menuItem) {
        if (!menuItem || !menuItem.parentElement) {
            return;
        }

        const siblings = Array.from(menuItem.parentElement.children).filter((element) => element !== menuItem);
        siblings.forEach((sibling) => {
            const toggle = findDirectChild(sibling, SUBMENU_TOGGLE_SELECTOR);
            const submenu = findDirectChild(sibling, SUBMENU_SELECTOR);
            if (!toggle || !submenu) {
                return;
            }

            if (toggle.getAttribute('aria-expanded') === 'true') {
                setMenuItemState(sibling, submenu, toggle, false);
            }
        });
    }

    function setMenuItemState(menuItem, submenu, toggle, shouldOpen, options = {}) {
        if (!menuItem || !submenu || !toggle) {
            return;
        }

        const { collapseSiblings = false, focusToggle = false } = options;
        const isCurrentlyOpen = toggle.getAttribute('aria-expanded') === 'true';

        if (shouldOpen === isCurrentlyOpen) {
            applyToggleLabel(toggle, shouldOpen);
            return;
        }

        if (shouldOpen && collapseSiblings) {
            collapseSiblingSubmenus(menuItem);
        }

        applySubmenuHeight(submenu);

        menuItem.classList.toggle(SUBMENU_OPEN_CLASS, shouldOpen);
        submenu.classList.toggle(SUBMENU_OPEN_CLASS, shouldOpen);
        toggle.classList.toggle(SUBMENU_OPEN_CLASS, shouldOpen);

        toggle.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        submenu.setAttribute('aria-hidden', shouldOpen ? 'false' : 'true');
        applyToggleLabel(toggle, shouldOpen);

        if (shouldOpen) {
            observeSubmenuSize(submenu);
        } else {
            unobserveSubmenuSize(submenu);
        }

        if (!shouldOpen) {
            requestAnimationFrame(() => {
                submenu.style.setProperty('--submenu-max-height', '0px');
            });
        }

        if (shouldRememberState && !isRestoringState) {
            const lastInteractedId = shouldOpen && typeof submenu.id === 'string' && submenu.id !== ''
                ? submenu.id
                : null;
            persistOpenSubmenus(lastInteractedId);
        }

        refreshFocusableElements();

        if (!shouldOpen && focusToggle) {
            toggle.focus({ preventScroll: true });
        }
    }

    function getFirstVisibleInteractiveElement(container) {
        if (!container) {
            return null;
        }

        const candidates = Array.from(container.querySelectorAll('a, button, [tabindex]:not([tabindex="-1"])'));
        return candidates.find((element) => isElementVisible(element)) || null;
    }

    function refreshOpenSubmenuHeights() {
        menuItemsWithChildren.forEach((menuItem) => {
            const submenu = findDirectChild(menuItem, SUBMENU_SELECTOR);
            const toggle = findDirectChild(menuItem, SUBMENU_TOGGLE_SELECTOR);
            if (!submenu || !toggle) {
                return;
            }

            if (submenu.classList.contains(SUBMENU_OPEN_CLASS) || toggle.getAttribute('aria-expanded') === 'true') {
                applySubmenuHeight(submenu);
                observeSubmenuSize(submenu);
            }
        });
    }

    menuItemsWithChildren.forEach((menuItem) => {
        const toggle = findDirectChild(menuItem, SUBMENU_TOGGLE_SELECTOR);
        const submenu = findDirectChild(menuItem, SUBMENU_SELECTOR);

        if (!toggle || !submenu) {
            return;
        }

        if (!submenu.dataset.transitionBound) {
            submenu.addEventListener('transitionend', handleSubmenuTransitionEnd);
            submenu.dataset.transitionBound = 'true';
        }

        const isExpanded = toggle.getAttribute('aria-expanded') === 'true';

        menuItem.classList.toggle(SUBMENU_OPEN_CLASS, isExpanded);
        submenu.classList.toggle(SUBMENU_OPEN_CLASS, isExpanded);
        toggle.classList.toggle(SUBMENU_OPEN_CLASS, isExpanded);
        submenu.setAttribute('aria-hidden', isExpanded ? 'false' : 'true');
        applyToggleLabel(toggle, isExpanded);

        if (!isExpanded) {
            submenu.style.setProperty('--submenu-max-height', '0px');
            unobserveSubmenuSize(submenu);
        } else {
            applySubmenuHeight(submenu);
            observeSubmenuSize(submenu);
        }

        toggle.addEventListener('click', (event) => {
            event.preventDefault();
            const shouldOpen = toggle.getAttribute('aria-expanded') !== 'true';
            setMenuItemState(menuItem, submenu, toggle, shouldOpen, { collapseSiblings: !isDesktopViewport() });

            if (shouldOpen) {
                const firstInteractive = getFirstVisibleInteractiveElement(submenu);
                if (firstInteractive) {
                    firstInteractive.focus({ preventScroll: true });
                }
            }
        });

        toggle.addEventListener('keydown', (event) => {
            if (!event) {
                return;
            }

            const key = event.key;

            if (key === 'Escape' || key === 'Esc') {
                if (toggle.getAttribute('aria-expanded') === 'true') {
                    event.preventDefault();
                    setMenuItemState(menuItem, submenu, toggle, false, { focusToggle: true });
                }
                return;
            }

            if (key === 'ArrowDown' || key === 'Down') {
                if (toggle.getAttribute('aria-expanded') !== 'true') {
                    setMenuItemState(menuItem, submenu, toggle, true, { collapseSiblings: !isDesktopViewport() });
                }

                const firstInteractive = getFirstVisibleInteractiveElement(submenu);
                if (firstInteractive) {
                    event.preventDefault();
                    firstInteractive.focus({ preventScroll: true });
                }
            }
        });

        submenu.addEventListener('keydown', (event) => {
            if (!event) {
                return;
            }

            const key = event.key;

            if (key === 'Escape' || key === 'Esc') {
                if (toggle.getAttribute('aria-expanded') === 'true') {
                    event.preventDefault();
                    setMenuItemState(menuItem, submenu, toggle, false, { focusToggle: true });
                }
            }
        });
    });

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

    function openSidebar(rawOptions = {}) {
        const options = (rawOptions && typeof rawOptions === 'object' && !Array.isArray(rawOptions)) ? rawOptions : {};
        const skipFocus = options.skipFocus === true;
        const skipAnalytics = options.skipAnalytics === true;
        const analyticsTarget = typeof options.analyticsTarget === 'string' && options.analyticsTarget !== ''
            ? options.analyticsTarget
            : 'toggle_button';

        autoOpenTriggered = true;
        teardownAutoOpenTriggers();
        applyScrollLockCompensation();
        applyScrollLock();
        document.body.classList.add('sidebar-open');
        if (!skipAnalytics) {
            dispatchAnalytics('sidebar_open', { target: analyticsTarget });
        }
        if (analyticsEnabled) {
            beginSession(analyticsTarget);
        }
        hamburgerBtn.classList.add('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'true');
        if (closeLabel) {
            hamburgerBtn.setAttribute('aria-label', closeLabel);
        }
        if (overlay) {
            overlay.classList.add('is-visible');
            overlay.setAttribute('aria-hidden', 'false');
        }
        if (sidebarInner && shouldRememberState && typeof persistedState.scrollTop === 'number') {
            sidebarInner.scrollTop = persistedState.scrollTop;
        }
        if (!skipFocus) {
            if (isReducedMotion) {
                focusFirstAvailableElement();
            } else {
                setTimeout(() => {
                    focusFirstAvailableElement();
                }, 100);
            }
        }
        document.addEventListener('keydown', trapFocus);
        persistState({
            isOpen: true,
            scrollTop: sidebarInner ? (sidebarInner.scrollTop || 0) : 0,
        });
    }

    function closeSidebar(rawOptions = {}) {
        let options = rawOptions;
        if (options && typeof options === 'object' && typeof options.type === 'string') {
            options = {};
        }
        options = (options && typeof options === 'object' && !Array.isArray(options)) ? options : {};
        const { returnFocus = true } = options;
        const source = typeof options.source === 'string' ? options.source : 'user';
        teardownAutoOpenTriggers();
        const isSidebarOpen = document.body.classList.contains('sidebar-open');
        if (!isSidebarOpen) {
            return;
        }
        if (source !== 'responsive') {
            manualDismissed = true;
        }
        if (analyticsEnabled) {
            finalizeSession(source);
        }

        document.body.classList.remove('sidebar-open');
        releaseScrollLock();
        hamburgerBtn.classList.remove('is-active');
        hamburgerBtn.setAttribute('aria-expanded', 'false');
        if (openLabel) {
            hamburgerBtn.setAttribute('aria-label', openLabel);
        }
        if (overlay) {
            overlay.classList.remove('is-visible');
            overlay.setAttribute('aria-hidden', 'true');
        }
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

        persistState({ isOpen: false });
    }

    function toggleSidebar() {
        if (document.body.classList.contains('sidebar-open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    }

    function isTouchPointer(event) {
        if (!event) {
            return false;
        }

        if (typeof event.pointerType === 'string') {
            return GESTURE_POINTER_TYPES.has(event.pointerType);
        }

        return false;
    }

    function shouldIgnoreEdgeGestureTarget(target) {
        if (!target || typeof target.closest !== 'function') {
            return false;
        }

        if (sidebar.contains(target) || (overlay && overlay.contains(target))) {
            return true;
        }

        if (target === hamburgerBtn || hamburgerBtn.contains(target)) {
            return true;
        }

        if (target.closest('[data-sidebar-gesture="ignore"]')) {
            return true;
        }

        if (target.closest('input, textarea, select, button, a, [role="button"], [contenteditable="true"]')) {
            return true;
        }

        return false;
    }

    function handleGesturePointerDown(event) {
        if (!supportsPointerEvents || activeTouchGesture || !isTouchPointer(event) || event.isPrimary === false) {
            return;
        }

        if (isDesktopViewport()) {
            return;
        }

        const pointerId = typeof event.pointerId === 'number' ? event.pointerId : null;
        const startX = typeof event.clientX === 'number' ? event.clientX : null;
        const startY = typeof event.clientY === 'number' ? event.clientY : null;
        if (pointerId === null || startX === null || startY === null) {
            return;
        }

        const sidebarIsOpen = document.body.classList.contains('sidebar-open');
        const viewportWidth = window.innerWidth || (document.documentElement ? document.documentElement.clientWidth : 0) || 0;

        if (!sidebarIsOpen && gestureSettings.edgeSwipeEnabled && gestureSettings.edgeSize > 0) {
            if (shouldIgnoreEdgeGestureTarget(event.target)) {
                return;
            }

            if (!isSidebarOnRight && startX > gestureSettings.edgeSize) {
                return;
            }

            if (isSidebarOnRight && (viewportWidth - startX) > gestureSettings.edgeSize) {
                return;
            }

            activeTouchGesture = {
                pointerId,
                type: 'edge-open',
                startX,
                startY,
                triggered: false,
            };
            return;
        }

        if (sidebarIsOpen && gestureSettings.closeSwipeEnabled) {
            const target = event.target;
            if (!target || (target !== sidebar && !sidebar.contains(target) && !(overlay && overlay.contains(target)))) {
                return;
            }

            activeTouchGesture = {
                pointerId,
                type: 'close',
                startX,
                startY,
                triggered: false,
            };
        }
    }

    function handleGesturePointerMove(event) {
        if (!activeTouchGesture || !isTouchPointer(event) || event.pointerId !== activeTouchGesture.pointerId) {
            return;
        }

        if (activeTouchGesture.triggered) {
            event.preventDefault();
            return;
        }

        const deltaX = event.clientX - activeTouchGesture.startX;
        const deltaY = event.clientY - activeTouchGesture.startY;
        const horizontalDistance = Math.abs(deltaX);
        const verticalDistance = Math.abs(deltaY);

        if (horizontalDistance < verticalDistance || horizontalDistance < 10) {
            return;
        }

        const verticalRatio = verticalDistance / Math.max(horizontalDistance, 1);
        if (verticalRatio > GESTURE_VERTICAL_RATIO_LIMIT) {
            return;
        }

        if (activeTouchGesture.type === 'edge-open') {
            const meetsThreshold = !isSidebarOnRight
                ? deltaX >= gestureSettings.minDistance
                : (-deltaX) >= gestureSettings.minDistance;
            if (!meetsThreshold) {
                return;
            }

            activeTouchGesture.triggered = true;
            openSidebar({ analyticsTarget: 'gesture_swipe' });
            event.preventDefault();
            return;
        }

        if (activeTouchGesture.type === 'close') {
            const meetsThreshold = !isSidebarOnRight
                ? (-deltaX) >= gestureSettings.minDistance
                : deltaX >= gestureSettings.minDistance;
            if (!meetsThreshold) {
                return;
            }

            activeTouchGesture.triggered = true;
            closeSidebar({ returnFocus: false, source: 'gesture' });
            event.preventDefault();
        }
    }

    function handleGesturePointerEnd(event) {
        if (!activeTouchGesture) {
            return;
        }

        if (typeof event.pointerId === 'number' && event.pointerId !== activeTouchGesture.pointerId) {
            return;
        }

        activeTouchGesture = null;
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
    if (closeBtn) {
        closeBtn.addEventListener('click', () => closeSidebar({ source: 'close_button' }));
    }
    if (overlay) {
        overlay.addEventListener('click', () => closeSidebar({ source: 'overlay' }));
    }

    if (supportsPointerEvents && (gestureSettings.edgeSwipeEnabled || gestureSettings.closeSwipeEnabled)) {
        window.addEventListener('pointerdown', handleGesturePointerDown, { passive: true });
        window.addEventListener('pointermove', handleGesturePointerMove, { passive: false });
        window.addEventListener('pointerup', handleGesturePointerEnd, { passive: true });
        window.addEventListener('pointercancel', handleGesturePointerEnd, { passive: true });
    }

    sidebar.addEventListener('click', (event) => {
        if (!event || !event.target || typeof event.target.closest !== 'function') {
            return;
        }

        const actionTarget = analyticsEnabled && typeof event.target.closest === 'function'
            ? event.target.closest('[data-widget-action]')
            : null;
        if (actionTarget) {
            const widgetElement = actionTarget.closest('.sidebar-widget');
            if (widgetElement) {
                const actionType = actionTarget.getAttribute('data-widget-action') || '';
                if (analyticsEnabled) {
                    markWidgetView(widgetElement);
                    if (actionType === 'cta_button' || actionType === 'product') {
                        dispatchWidgetConversion(widgetElement, { action: actionType });
                    } else {
                        markWidgetInteraction(widgetElement);
                    }
                }
            }
        }

        const link = event.target.closest('a');
        if (!link || !sidebar.contains(link)) {
            return;
        }

        if (link.classList && link.classList.contains('menu-cta__button')) {
            const ctaWrapper = link.closest('.menu-cta');
            if (ctaWrapper) {
                if (analyticsEnabled) {
                    markCtaView(ctaWrapper);
                }
                markCtaAsClicked(ctaWrapper);
            }
            if (analyticsEnabled) {
                dispatchAnalytics('cta_click', { target: 'cta_button' });
            }
            recordSessionInteraction('cta_click');
            return;
        }

        if (analyticsEnabled && link.closest('.social-icons')) {
            dispatchAnalytics('menu_link_click', { target: 'social_link' });
            recordSessionInteraction('social_link_click');
            return;
        }

        if (analyticsEnabled && link.closest('.sidebar-menu')) {
            dispatchAnalytics('menu_link_click', { target: 'menu_link' });
            recordSessionInteraction('menu_link_click');
        }
    }, true);

    sidebar.addEventListener('submit', (event) => {
        if (!event || !event.target || typeof event.target.closest !== 'function') {
            return;
        }

        const form = event.target.closest('form');
        if (!form) {
            return;
        }

        const widgetElement = form.closest('.sidebar-widget--form');
        if (!widgetElement) {
            return;
        }

        if (analyticsEnabled) {
            markWidgetView(widgetElement);
            dispatchWidgetConversion(widgetElement, { action: 'form_submit' });
        }

        recordSessionInteraction('widget_form_submit');

        const successMessage = widgetElement.querySelector('[data-widget-success-message]');
        if (successMessage) {
            successMessage.classList.add('is-visible');

            if (!successMessage.hasAttribute('role')) {
                successMessage.setAttribute('role', 'status');
            }

            if (!successMessage.hasAttribute('aria-live')) {
                successMessage.setAttribute('aria-live', 'polite');
            }

            if (!successMessage.hasAttribute('aria-atomic')) {
                successMessage.setAttribute('aria-atomic', 'true');
            }
        }

        if (form.hasAttribute('data-widget-form')) {
            event.preventDefault();
            form.reset();
        }
    });

    if (analyticsEnabled) {
        sidebar.addEventListener('focusin', (event) => {
            if (!event || !event.target || typeof event.target.closest !== 'function') {
                return;
            }

            const widgetElement = event.target.closest('.sidebar-widget--slider');
            if (widgetElement) {
                markWidgetInteraction(widgetElement);
            }
        });

        sidebar.addEventListener('pointerenter', (event) => {
            if (!event || !event.target || typeof event.target.closest !== 'function') {
                return;
            }

            const widgetElement = event.target.closest('.sidebar-widget--slider');
            if (widgetElement) {
                markWidgetInteraction(widgetElement);
            }
        }, true);
    }

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

    if (shouldRememberState && sidebarInner) {
        sidebarInner.addEventListener('scroll', scheduleScrollPersistence, { passive: true });
    }

    if (shouldRememberState) {
        restorePersistedState();
    } else {
        persistedState = { ...defaultPersistedState };
    }

    setupAutoOpenTriggers();

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
        refreshOpenSubmenuHeights();

        if (!document.body.classList.contains('sidebar-open')) {
            return;
        }

        if (window.innerWidth >= DESKTOP_BREAKPOINT) {
            closeSidebar({ returnFocus: false, source: 'responsive' });
        } else {
            applyScrollLockCompensation();
            applyScrollLock();
        }
    }

    applyHoverEffect();
    window.addEventListener('resize', handleResize);
});
