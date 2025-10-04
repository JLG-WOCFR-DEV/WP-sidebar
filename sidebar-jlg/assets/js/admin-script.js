function getSidebarGlobalData() {
    if (typeof window !== 'undefined' && window.sidebarJLG && typeof window.sidebarJLG === 'object') {
        return window.sidebarJLG;
    }

    if (typeof sidebarJLG !== 'undefined' && typeof sidebarJLG === 'object') {
        return sidebarJLG;
    }

    return null;
}

function getI18nString(key, fallback = '') {
    const globalData = getSidebarGlobalData();
    if (globalData && globalData.i18n && typeof globalData.i18n[key] === 'string') {
        return globalData.i18n[key];
    }

    return fallback;
}

function getSvgUrlRestrictions(overrides) {
    if (overrides && typeof overrides === 'object') {
        return overrides;
    }

    const globalData = getSidebarGlobalData();

    if (globalData && typeof globalData.svg_url_restrictions === 'object' && globalData.svg_url_restrictions !== null) {
        return globalData.svg_url_restrictions;
    }

    return {};
}

function normalizePath(path) {
    if (typeof path !== 'string') {
        return '';
    }

    let normalized = path.replace(/\\/g, '/').trim();
    if (normalized === '') {
        return '';
    }

    normalized = normalized.replace(/\/{2,}/g, '/');
    normalized = normalized.replace(/^\/+/g, '');

    return '/' + normalized;
}

function normalizeAllowedPath(path) {
    const normalized = normalizePath(path);
    if (!normalized) {
        return '';
    }

    return normalized.endsWith('/') ? normalized : normalized + '/';
}

function normalizeUrlPath(path) {
    const normalized = normalizePath(path);
    if (!normalized) {
        return '';
    }

    return normalized;
}

function getRestrictionDescription(restrictions) {
    if (!restrictions || typeof restrictions !== 'object') {
        return '';
    }

    const allowedPath = typeof restrictions.allowed_path === 'string' ? restrictions.allowed_path : '';
    const host = typeof restrictions.host === 'string' ? restrictions.host : '';

    if (host) {
        return host + (allowedPath || '');
    }

    return allowedPath || '';
}

function buildOutOfScopeMessage(restrictions) {
    const description = getRestrictionDescription(restrictions);

    if (description) {
        const template = getI18nString(
            'svgUrlOutOfScopeWithDescription',
            'Cette URL ne sera pas enregistrée. Utilisez une adresse dans %s.'
        );

        return template.replace('%s', description);
    }

    return getI18nString(
        'svgUrlOutOfScope',
        'Cette URL ne sera pas enregistrée car elle est en dehors de la zone autorisée.'
    );
}

function joinMessages(...messages) {
    return messages.filter(Boolean).join(' ');
}

function normalizeNumericString(value) {
    if (typeof value === 'number') {
        if (!Number.isFinite(value)) {
            return '';
        }

        if (Math.abs(value - Math.round(value)) < 0.0001) {
            return String(Math.round(value));
        }

        return String(parseFloat(value.toFixed(4)));
    }

    if (typeof value !== 'string') {
        return '';
    }

    const trimmed = value.trim();
    if (trimmed === '') {
        return '';
    }

    const numeric = Number(trimmed);
    if (!Number.isFinite(numeric)) {
        return '';
    }

    if (Math.abs(numeric - Math.round(numeric)) < 0.0001) {
        return String(Math.round(numeric));
    }

    return String(parseFloat(numeric.toFixed(4)));
}

function parseDimensionValue(value, fallbackUnit = 'px', allowedUnits = null) {
    const fallback = typeof fallbackUnit === 'string' && fallbackUnit.trim() !== ''
        ? fallbackUnit.trim()
        : 'px';

    const normalizedUnits = Array.isArray(allowedUnits) && allowedUnits.length
        ? allowedUnits.map((unit) => (typeof unit === 'string' ? unit.trim() : '')).filter(Boolean)
        : null;

    const normalizeUnit = (unitCandidate) => {
        const unitString = typeof unitCandidate === 'string' ? unitCandidate.trim() : '';
        if (normalizedUnits && normalizedUnits.length) {
            if (normalizedUnits.includes(unitString)) {
                return unitString;
            }

            return normalizedUnits[0];
        }

        return unitString || fallback;
    };

    const finalize = (numericValue, unitCandidate) => {
        const normalizedValue = normalizeNumericString(numericValue);
        return {
            value: normalizedValue,
            unit: normalizeUnit(unitCandidate),
        };
    };

    if (value && typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, 'value')) {
        return finalize(value.value, value.unit);
    }

    if (typeof value === 'number') {
        return finalize(value, fallback);
    }

    if (typeof value === 'string') {
        const trimmed = value.trim();
        if (trimmed === '') {
            return { value: '', unit: fallback };
        }

        const match = trimmed.match(/^(-?(?:\d+|\d*\.\d+))\s*([a-z%]*)$/i);
        if (match) {
            return finalize(match[1], match[2] || fallback);
        }
    }

    return { value: '', unit: normalizedUnits && normalizedUnits.length ? normalizedUnits[0] : fallback };
}

function dimensionToCssString(dimension, fallback = '') {
    if (dimension === null || dimension === undefined) {
        return fallback;
    }

    if (typeof dimension === 'string') {
        return dimension.trim();
    }

    if (typeof dimension === 'number') {
        if (!Number.isFinite(dimension)) {
            return fallback;
        }
        return `${dimension}px`;
    }

    if (typeof dimension === 'object' && Object.prototype.hasOwnProperty.call(dimension, 'value')) {
        const numeric = normalizeNumericString(dimension.value);
        if (numeric === '') {
            return fallback;
        }

        const unit = typeof dimension.unit === 'string' ? dimension.unit : '';
        return `${numeric}${unit}`;
    }

    return fallback;
}

function triggerFieldUpdate(element) {
    if (!element) {
        return;
    }

    element.dispatchEvent(new Event('input', { bubbles: true }));
    element.dispatchEvent(new Event('change', { bubbles: true }));
}

function isUrlWithinAllowedArea(urlObject, originalValue, restrictions) {
    const allowedPath = normalizeAllowedPath(restrictions.allowed_path);
    if (!allowedPath) {
        return false;
    }

    const urlPath = normalizeUrlPath(urlObject.pathname || '');
    if (!urlPath) {
        return false;
    }

    if (urlPath.indexOf(allowedPath) !== 0) {
        return false;
    }

    const hasExplicitHost = /^https?:\/\//i.test(originalValue) || originalValue.startsWith('//');
    const allowedHost = typeof restrictions.host === 'string' && restrictions.host !== ''
        ? restrictions.host.toLowerCase()
        : null;

    if (hasExplicitHost) {
        if (!urlObject.hostname || !allowedHost) {
            return false;
        }

        return urlObject.hostname.toLowerCase() === allowedHost;
    }

    return true;
}

function renderSvgUrlPreview(iconValue, $preview, restrictionsOverride) {
    if (!$preview || typeof $preview.empty !== 'function' || typeof $preview.append !== 'function') {
        return false;
    }

    const restrictions = getSvgUrlRestrictions(restrictionsOverride);
    const outOfScopeMessage = buildOutOfScopeMessage(restrictions);
    const $status = typeof $preview.siblings === 'function' ? $preview.siblings('.icon-preview-status') : null;
    const $input = typeof $preview.siblings === 'function' ? $preview.siblings('.icon-input') : null;

    const setStatus = (message, isError) => {
        if (!$status || !$status.length) {
            return;
        }

        const text = message || '';
        $status.text(text);
        if (isError) {
            $status.addClass('is-error');
        } else {
            $status.removeClass('is-error');
        }
    };

    const setInputValidity = (isValid) => {
        if (!$input || !$input.length) {
            return;
        }

        if (isValid) {
            $input.removeClass('icon-input-invalid');
            $input.removeAttr('aria-invalid');
        } else {
            $input.addClass('icon-input-invalid');
            $input.attr('aria-invalid', 'true');
        }
    };

    const clearPreview = () => {
        $preview.empty();
    };

    if (!iconValue) {
        clearPreview();
        setStatus('', false);
        setInputValidity(true);
        return false;
    }

    let url;
    try {
        url = new URL(iconValue, window.location.origin);
    } catch (error) {
        clearPreview();
        setStatus(joinMessages(getI18nString('invalidUrl', 'URL invalide.'), outOfScopeMessage), true);
        setInputValidity(false);
        return false;
    }

    if (url.protocol !== 'https:' && url.protocol !== 'http:') {
        clearPreview();
        setStatus(joinMessages(getI18nString('httpOnly', 'Seuls les liens HTTP(S) sont autorisés.'), outOfScopeMessage), true);
        setInputValidity(false);
        return false;
    }

    if (!isUrlWithinAllowedArea(url, iconValue, restrictions)) {
        clearPreview();
        setStatus(outOfScopeMessage, true);
        setInputValidity(false);
        return false;
    }

    const img = document.createElement('img');
    img.src = url.href;
    img.alt = getI18nString('iconPreviewAlt', 'preview');

    clearPreview();
    setStatus('', false);
    setInputValidity(true);
    $preview.append(img);

    return true;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports.renderSvgUrlPreview = renderSvgUrlPreview;
}


let cachedNavMenus = null;
let navMenusPromise = null;

function normalizeNavMenus(menus) {
    if (!Array.isArray(menus)) {
        return [];
    }

    return menus.reduce((accumulator, menu) => {
        if (!menu) {
            return accumulator;
        }

        let id = '';
        if (typeof menu.id === 'number' || typeof menu.id === 'string') {
            id = String(menu.id);
        } else if (typeof menu.term_id === 'number' || typeof menu.term_id === 'string') {
            id = String(menu.term_id);
        }

        if (id === '') {
            return accumulator;
        }

        const name = typeof menu.name === 'string' && menu.name.trim() !== ''
            ? menu.name
            : (typeof menu.slug === 'string' && menu.slug !== '' ? menu.slug : id);

        accumulator.push({
            id,
            name,
            slug: typeof menu.slug === 'string' ? menu.slug : '',
        });

        return accumulator;
    }, []);
}

function fetchNavMenusFromDataStore() {
    if (!window.wp || !wp.data || typeof wp.data.select !== 'function') {
        return null;
    }

    const store = wp.data.select('core');
    if (!store || typeof store.getMenus !== 'function') {
        return null;
    }

    const menus = store.getMenus();
    if (Array.isArray(menus)) {
        return Promise.resolve(normalizeNavMenus(menus));
    }

    if (!wp.data.dispatch || typeof wp.data.dispatch !== 'function') {
        return null;
    }

    const dispatcher = wp.data.dispatch('core');
    if (!dispatcher || typeof dispatcher.fetchMenus !== 'function') {
        return null;
    }

    return new Promise((resolve) => {
        const unsubscribe = wp.data.subscribe(() => {
            const loadedMenus = store.getMenus();
            if (Array.isArray(loadedMenus)) {
                unsubscribe();
                resolve(normalizeNavMenus(loadedMenus));
            }
        });

        try {
            dispatcher.fetchMenus();
        } catch (error) {
            unsubscribe();
            resolve([]);
        }
    });
}

function fetchNavMenusViaApi() {
    if (!window.wp || !wp.apiFetch || typeof wp.apiFetch !== 'function') {
        return Promise.resolve([]);
    }

    return wp.apiFetch({ path: '/wp/v2/menus?per_page=100' })
        .then((menus) => normalizeNavMenus(menus))
        .catch(() => []);
}

function fetchNavMenus() {
    if (Array.isArray(cachedNavMenus)) {
        return Promise.resolve(cachedNavMenus);
    }

    if (navMenusPromise) {
        return navMenusPromise;
    }

    const storePromise = fetchNavMenusFromDataStore();
    if (storePromise) {
        navMenusPromise = storePromise
            .then((menus) => {
                cachedNavMenus = menus;
                return menus;
            })
            .catch(() => fetchNavMenusViaApi().then((menus) => {
                cachedNavMenus = menus;
                return menus;
            }));

        return navMenusPromise;
    }

    navMenusPromise = fetchNavMenusViaApi().then((menus) => {
        cachedNavMenus = menus;
        return menus;
    });

    return navMenusPromise;
}


class SidebarPreviewModule {
    constructor(args = {}) {
        this.container = args.container || null;
        this.viewport = this.container ? this.container.querySelector('.sidebar-jlg-preview__viewport') : null;
        this.statusElement = this.container ? this.container.querySelector('.sidebar-jlg-preview__status') : null;
        this.form = args.form || null;
        this.ajaxUrl = args.ajaxUrl || '';
        this.nonce = args.nonce || '';
        this.action = args.action || 'jlg_render_preview';
        this.messages = Object.assign({
            loading: '',
            error: '',
            emptyMenu: '',
            refresh: ''
        }, args.messages || {});

        this.initialOptions = SidebarPreviewModule.cloneObject(args.options || {});
        this.currentOptions = SidebarPreviewModule.cloneObject(this.initialOptions);
        this.fontStacks = SidebarPreviewModule.cloneObject(args.fontStacks || {});
        this.defaultFontStack = typeof args.defaultFontStack === 'string' && args.defaultFontStack !== ''
            ? args.defaultFontStack
            : 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';

        this.dimensionKeys = [
            'content_margin',
            'floating_vertical_margin',
            'border_radius',
            'hamburger_top_position',
            'hamburger_horizontal_offset',
            'hamburger_size',
            'header_padding_top',
            'horizontal_bar_height',
            'letter_spacing',
        ];

        this.dimensionKeys.forEach((key) => {
            const initial = parseDimensionValue(this.initialOptions[key], 'px');
            this.initialOptions[key] = initial;
            this.currentOptions[key] = parseDimensionValue(this.currentOptions[key], initial.unit);
        });

        this.isComparing = false;
        this.afterOptionsSnapshot = SidebarPreviewModule.cloneObject(this.currentOptions);

        this.$ = typeof window !== 'undefined' ? window.jQuery : null;

        const initialPreviewSize = this.container ? this.container.getAttribute('data-preview-size') : '';
        this.previewSize = SidebarPreviewModule.normalizePreviewSize(initialPreviewSize);
        this.toolbar = this.container ? this.container.querySelector('.sidebar-jlg-preview__toolbar') : null;
        this.previewButtons = this.toolbar ? Array.from(this.toolbar.querySelectorAll('[data-preview-size]')) : [];
        this.toolbarInitialized = false;

        this.aside = null;
        this.nav = null;
        this.menuList = null;
        this.menuSocialItem = null;
        this.footerSocial = null;
        this.hamburger = null;
        this.overlay = null;

        this.defaultNavAriaLabel = '';
        this.defaultToggleExpandLabel = '';
        this.defaultToggleCollapseLabel = '';

        this.menuContainer = null;
        this.socialContainer = null;
        this.menuObserver = null;
        this.socialObserver = null;
        this.isInitializing = false;

        this.updatePreviewSizeClasses();

        this.updateMenuFromDomBound = this.updateMenuFromDom.bind(this);
        this.updateSocialFromDomBound = this.updateSocialFromDom.bind(this);
    }

    static normalizePreviewSize(value) {
        const normalized = typeof value === 'string' ? value.toLowerCase() : '';
        if (normalized === 'mobile' || normalized === 'tablet') {
            return normalized;
        }

        return 'desktop';
    }

    static getPreviewLabel(value) {
        const normalized = SidebarPreviewModule.normalizePreviewSize(value);
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    static cloneObject(object) {
        try {
            return JSON.parse(JSON.stringify(object));
        } catch (error) {
            return Object.assign({}, object);
        }
    }

    captureFontStacksFromDom() {
        if (!this.form) {
            return;
        }

        const select = this.form.querySelector('select[name="sidebar_jlg_settings[font_family]"]');
        if (!select) {
            return;
        }

        const options = Array.from(select.options || []);
        if (!options.length) {
            return;
        }

        options.forEach((option) => {
            if (!option || typeof option.value !== 'string') {
                return;
            }

            const key = option.value;
            const stack = option.getAttribute('data-font-stack') || '';
            if (key !== '') {
                this.fontStacks[key] = stack;
            }

            if (!this.defaultFontStack && stack) {
                this.defaultFontStack = stack;
            }
        });
    }

    lookupFontStack(value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return this.defaultFontStack;
        }

        if (Object.prototype.hasOwnProperty.call(this.fontStacks, value)) {
            const stack = this.fontStacks[value];
            if (typeof stack === 'string' && stack.trim() !== '') {
                return stack;
            }
        }

        return this.defaultFontStack;
    }

    resolveFontFamily() {
        if (this.currentOptions && typeof this.currentOptions.font_stack === 'string' && this.currentOptions.font_stack !== '') {
            return this.currentOptions.font_stack;
        }

        const key = this.currentOptions ? this.currentOptions.font_family : '';
        return this.lookupFontStack(key);
    }

    captureDefaultFontStackFromMarkup() {
        if (this.defaultFontStack && this.defaultFontStack !== '') {
            return;
        }

        if (typeof window === 'undefined' || !window.getComputedStyle) {
            return;
        }

        if (!this.aside) {
            return;
        }

        const computed = window.getComputedStyle(this.aside);
        if (!computed) {
            return;
        }

        const family = computed.getPropertyValue('font-family');
        if (typeof family === 'string' && family.trim() !== '') {
            this.defaultFontStack = family.trim();
        }
    }

    resolveAccentBaseColor() {
        const type = (this.currentOptions.accent_color_type || 'solid').toLowerCase();
        if (type === 'gradient') {
            return this.currentOptions.accent_color_start || this.currentOptions.accent_color || '';
        }

        return this.currentOptions.accent_color || '';
    }

    applyAccentGradient() {
        if (!this.container) {
            return;
        }

        const type = (this.currentOptions.accent_color_type || 'solid').toLowerCase();
        const start = this.currentOptions.accent_color_start || '';
        const end = this.currentOptions.accent_color_end || '';

        if (type === 'gradient' && start && end) {
            this.container.style.setProperty('--sidebar-accent-gradient', `linear-gradient(135deg, ${start} 0%, ${end} 100%)`);
            return;
        }

        this.container.style.setProperty('--sidebar-accent-gradient', 'none');
    }

    init() {
        if (!this.container || !this.viewport || !this.form || !this.$) {
            return;
        }

        this.setState('loading');
        this.setStatus(this.messages.loading || '', false);

        this.loadPreview()
            .then(() => {
                this.setState('ready');
                this.clearStatus();
                this.setupToolbar();
                this.setupBindings();
                this.applyOptions();
            })
            .catch(() => {
                // Failure is handled by loadPreview via renderFallback.
            });
    }

    setState(state) {
        if (this.container) {
            this.container.setAttribute('data-state', state);
        }
    }

    setStatus(message, isError) {
        if (!this.statusElement) {
            return;
        }

        this.statusElement.textContent = message || '';

        if (isError) {
            this.statusElement.classList.add('is-error');
        } else {
            this.statusElement.classList.remove('is-error');
        }
    }

    clearStatus() {
        this.setStatus('', false);
    }

    renderFallback(message) {
        if (!this.viewport) {
            return;
        }

        this.viewport.innerHTML = '';
        this.setState('error');
        this.setStatus(message || this.messages.error || '', true);

        const wrapper = document.createElement('div');
        wrapper.className = 'sidebar-jlg-preview__fallback';

        const text = document.createElement('p');
        text.textContent = message || this.messages.error || '';
        wrapper.appendChild(text);

        if (this.messages.refresh) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'button';
            button.textContent = this.messages.refresh;
            button.addEventListener('click', () => {
                this.setState('loading');
                this.setStatus(this.messages.loading || '', false);
                this.loadPreview()
                    .then(() => {
                        this.setState('ready');
                        this.clearStatus();
                        this.setupBindings();
                        this.applyOptions();
                    })
                    .catch(() => {
                        // Nothing else to do, fallback already visible.
                    });
            });
            wrapper.appendChild(button);
        }

        this.viewport.appendChild(wrapper);
    }

    loadPreview(overrideOptions = null) {
        if (!this.ajaxUrl || !this.nonce || !this.action) {
            this.renderFallback(this.messages.error || '');
            return Promise.reject(new Error('Missing preview configuration.'));
        }

        const payloadOptions = overrideOptions !== null
            ? SidebarPreviewModule.cloneObject(overrideOptions)
            : SidebarPreviewModule.cloneObject(this.currentOptions || {});

        return new Promise((resolve, reject) => {
            this.$.ajax({
                url: this.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: this.action,
                    nonce: this.nonce,
                    options: JSON.stringify(payloadOptions || {})
                }
            })
                .done((response) => {
                    if (response && response.success && response.data && typeof response.data.html === 'string') {
                        this.viewport.innerHTML = response.data.html;
                        this.afterMarkupRendered();
                        resolve(response.data);
                        return;
                    }

                    const fallbackMessage = response && response.data && response.data.message
                        ? response.data.message
                        : this.messages.error;

                    this.renderFallback(fallbackMessage || this.messages.error || '');
                    reject(new Error('Invalid preview response.'));
                })
                .fail(() => {
                    this.renderFallback(this.messages.error || '');
                    reject(new Error('Preview request failed.'));
                });
        });
    }

    afterMarkupRendered() {
        this.aside = this.viewport ? this.viewport.querySelector('.pro-sidebar') : null;
        this.nav = this.viewport ? this.viewport.querySelector('.sidebar-navigation') : null;
        this.menuList = this.viewport ? this.viewport.querySelector('.sidebar-menu') : null;
        this.menuSocialItem = this.viewport ? this.viewport.querySelector('.social-icons-wrapper') : null;
        this.footerSocial = this.viewport ? this.viewport.querySelector('.sidebar-footer') : null;
        this.hamburger = this.viewport ? this.viewport.querySelector('#hamburger-btn') : null;

        this.overlay = this.viewport ? this.viewport.querySelector('.sidebar-overlay') : null;

        if (this.nav) {
            this.defaultNavAriaLabel = this.nav.getAttribute('data-default-aria-label') || this.nav.getAttribute('aria-label') || '';
            this.defaultToggleExpandLabel = this.nav.getAttribute('data-default-toggle-expand') || '';
            this.defaultToggleCollapseLabel = this.nav.getAttribute('data-default-toggle-collapse') || '';
        }

        this.syncOverlayVisibility();

        if (this.hamburger) {
            this.hamburger.setAttribute('aria-hidden', 'true');
            this.hamburger.setAttribute('tabindex', '-1');
        }

        if (this.menuList && this.menuSocialItem && !this.menuList.contains(this.menuSocialItem)) {
            this.menuList.appendChild(this.menuSocialItem);
        }

        this.captureDefaultFontStackFromMarkup();
        this.updatePreviewSizeClasses();
        this.updateAccessibilityLabels();
    }

    setupToolbar() {
        if (!this.container) {
            return;
        }

        if (!this.toolbar || !this.previewButtons.length) {
            this.updatePreviewSizeClasses();
            return;
        }

        if (!this.toolbarInitialized) {
            this.previewButtons.forEach((button) => {
                button.addEventListener('click', (event) => {
                    event.preventDefault();
                    const size = SidebarPreviewModule.normalizePreviewSize(button.getAttribute('data-preview-size'));
                    this.setPreviewSize(size, { triggerButton: button });
                });
            });
            this.toolbarInitialized = true;
        }

        this.setPreviewSize(this.previewSize, { skipApply: true });
    }

    setPreviewSize(size, options = {}) {
        const normalized = SidebarPreviewModule.normalizePreviewSize(size);
        const { triggerButton = null, skipApply = false } = options || {};

        this.previewSize = normalized;
        this.updatePreviewSizeClasses();

        if (this.previewButtons && this.previewButtons.length) {
            this.previewButtons.forEach((button) => {
                const buttonSize = SidebarPreviewModule.normalizePreviewSize(button.getAttribute('data-preview-size'));
                const isActive = triggerButton ? button === triggerButton : buttonSize === normalized;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        if (!this.isInitializing && !skipApply) {
            this.applyOptions();
        }
    }

    updatePreviewSizeClasses() {
        if (this.container) {
            this.container.setAttribute('data-preview-size', this.previewSize);
        }

        if (this.viewport) {
            this.viewport.setAttribute('data-preview-size', this.previewSize);
            const baseLabel = this.getPreviewButtonLabel(this.previewSize) || SidebarPreviewModule.getPreviewLabel(this.previewSize);
            const composedLabel = this.isComparing ? `${baseLabel} · Avant` : baseLabel;
            this.viewport.setAttribute('data-preview-label', composedLabel);
            this.replaceClass(this.viewport, 'preview-', this.previewSize);
        }

        if (this.aside) {
            this.aside.dataset.previewSize = this.previewSize;
            this.replaceClass(this.aside, 'preview-', this.previewSize);
        }

        this.syncOverlayVisibility();
        this.updateComparisonClass();
    }

    updateComparisonClass() {
        if (this.container) {
            this.container.classList.toggle('is-comparing', this.isComparing);
        }

        if (this.viewport) {
            if (this.isComparing) {
                this.viewport.setAttribute('data-compare-mode', 'before');
            } else {
                this.viewport.removeAttribute('data-compare-mode');
            }
        }
    }

    getPreviewButtonLabel(size) {
        if (!this.previewButtons || !this.previewButtons.length) {
            return '';
        }

        const target = this.previewButtons.find((button) => {
            const buttonSize = SidebarPreviewModule.normalizePreviewSize(button.getAttribute('data-preview-size'));
            return buttonSize === size;
        });

        if (!target) {
            return '';
        }

        const hiddenLabel = target.querySelector('[aria-hidden="true"]');
        if (hiddenLabel && hiddenLabel.textContent) {
            return hiddenLabel.textContent.trim();
        }

        return target.textContent ? target.textContent.trim() : '';
    }

    syncOverlayVisibility() {
        if (!this.overlay) {
            return;
        }

        if (this.previewSize === 'mobile') {
            this.overlay.style.display = '';
        } else {
            this.overlay.style.display = 'none';
        }
    }

    detachObservers() {
        if (this.menuObserver) {
            this.menuObserver.disconnect();
            this.menuObserver = null;
        }

        if (this.socialObserver) {
            this.socialObserver.disconnect();
            this.socialObserver = null;
        }

        if (this.menuContainer) {
            this.menuContainer.removeEventListener('input', this.updateMenuFromDomBound);
            this.menuContainer.removeEventListener('change', this.updateMenuFromDomBound);
        }

        if (this.socialContainer) {
            this.socialContainer.removeEventListener('input', this.updateSocialFromDomBound);
            this.socialContainer.removeEventListener('change', this.updateSocialFromDomBound);
        }
    }

    setupBindings() {
        this.detachObservers();
        if (!this.form) {
            return;
        }

        this.isInitializing = true;

        this.captureFontStacksFromDom();

        this.bindField('sidebar_jlg_settings[layout_style]', (value) => {
            this.currentOptions.layout_style = value || 'full';
        });

        this.bindField('sidebar_jlg_settings[sidebar_position]', (value) => {
            this.currentOptions.sidebar_position = value === 'right' ? 'right' : 'left';
        });

        this.bindField('sidebar_jlg_settings[horizontal_bar_position]', (value) => {
            this.currentOptions.horizontal_bar_position = value === 'bottom' ? 'bottom' : 'top';
        });

        this.bindField('sidebar_jlg_settings[horizontal_bar_alignment]', (value) => {
            this.currentOptions.horizontal_bar_alignment = value || 'space-between';
        });

        this.bindField('sidebar_jlg_settings[horizontal_bar_sticky]', (value) => {
            this.currentOptions.horizontal_bar_sticky = value === true || value === '1' || value === 'on';
        });

        this.bindField('sidebar_jlg_settings[bg_color]', (value) => {
            this.currentOptions.bg_color = value;
        });

        this.bindField('sidebar_jlg_settings[bg_color_type]', (value) => {
            this.currentOptions.bg_color_type = value;
        });

        this.bindField('sidebar_jlg_settings[bg_color_start]', (value) => {
            this.currentOptions.bg_color_start = value;
        });

        this.bindField('sidebar_jlg_settings[bg_color_end]', (value) => {
            this.currentOptions.bg_color_end = value;
        });

        this.bindField('sidebar_jlg_settings[font_color]', (value) => {
            this.currentOptions.font_color = value;
        });

        this.bindField('sidebar_jlg_settings[font_hover_color]', (value) => {
            this.currentOptions.font_hover_color = value;
        });

        this.bindField('sidebar_jlg_settings[font_size]', (value) => {
            this.currentOptions.font_size = value;
        });

        this.bindField('sidebar_jlg_settings[font_family]', (value, elements) => {
            this.currentOptions.font_family = value;
            if (Array.isArray(elements) && elements.length) {
                const stack = this.lookupFontStack(value);
                this.currentOptions.font_stack = stack;
            } else {
                this.currentOptions.font_stack = this.lookupFontStack(value);
            }
        });

        this.bindField('sidebar_jlg_settings[font_weight]', (value) => {
            this.currentOptions.font_weight = value;
        });

        this.bindField('sidebar_jlg_settings[text_transform]', (value) => {
            this.currentOptions.text_transform = value || 'none';
        });

        this.bindField('sidebar_jlg_settings[accent_color]', (value) => {
            this.currentOptions.accent_color = value;
        });

        this.bindField('sidebar_jlg_settings[accent_color_type]', (value) => {
            this.currentOptions.accent_color_type = value;
        });

        this.bindField('sidebar_jlg_settings[accent_color_start]', (value) => {
            this.currentOptions.accent_color_start = value;
        });

        this.bindField('sidebar_jlg_settings[accent_color_end]', (value) => {
            this.currentOptions.accent_color_end = value;
        });

        this.bindField('sidebar_jlg_settings[mobile_bg_color]', (value) => {
            this.currentOptions.mobile_bg_color = value;
        });

        this.bindField('sidebar_jlg_settings[mobile_bg_opacity]', (value) => {
            this.currentOptions.mobile_bg_opacity = value;
        });

        this.bindField('sidebar_jlg_settings[mobile_blur]', (value) => {
            this.currentOptions.mobile_blur = value;
        });

        this.bindField('sidebar_jlg_settings[width_desktop]', (value) => {
            this.currentOptions.width_desktop = value;
        });

        this.bindField('sidebar_jlg_settings[width_tablet]', (value) => {
            this.currentOptions.width_tablet = value;
        });

        this.bindField('sidebar_jlg_settings[width_mobile]', (value) => {
            this.currentOptions.width_mobile = value;
        });

        this.bindDimensionField('sidebar_jlg_settings[horizontal_bar_height]', 'horizontal_bar_height');

        this.bindField('sidebar_jlg_settings[overlay_color]', (value) => {
            this.currentOptions.overlay_color = value;
        });

        this.bindField('sidebar_jlg_settings[overlay_opacity]', (value) => {
            this.currentOptions.overlay_opacity = value;
        });

        this.bindField('sidebar_jlg_settings[hamburger_color]', (value) => {
            this.currentOptions.hamburger_color = value;
        });

        this.bindDimensionField('sidebar_jlg_settings[hamburger_top_position]', 'hamburger_top_position');
        this.bindDimensionField('sidebar_jlg_settings[hamburger_horizontal_offset]', 'hamburger_horizontal_offset');
        this.bindDimensionField('sidebar_jlg_settings[hamburger_size]', 'hamburger_size');

        this.bindDimensionField('sidebar_jlg_settings[content_margin]', 'content_margin');

        this.bindDimensionField('sidebar_jlg_settings[floating_vertical_margin]', 'floating_vertical_margin');

        this.bindDimensionField('sidebar_jlg_settings[border_radius]', 'border_radius');
        this.bindDimensionField('sidebar_jlg_settings[letter_spacing]', 'letter_spacing');

        this.bindField('sidebar_jlg_settings[border_width]', (value) => {
            this.currentOptions.border_width = value;
        });

        this.bindField('sidebar_jlg_settings[border_color]', (value) => {
            this.currentOptions.border_color = value;
        });

        this.bindField('sidebar_jlg_settings[menu_alignment_desktop]', (value) => {
            this.currentOptions.menu_alignment_desktop = value || 'flex-start';
        });

        this.bindField('sidebar_jlg_settings[menu_alignment_mobile]', (value) => {
            this.currentOptions.menu_alignment_mobile = value || 'flex-start';
        });

        this.bindField('sidebar_jlg_settings[app_name]', (value) => {
            this.currentOptions.app_name = value;
        });

        this.bindField('sidebar_jlg_settings[nav_aria_label]', (value) => {
            this.currentOptions.nav_aria_label = value;
        });

        this.bindField('sidebar_jlg_settings[toggle_open_label]', (value) => {
            this.currentOptions.toggle_open_label = value;
        });

        this.bindField('sidebar_jlg_settings[toggle_close_label]', (value) => {
            this.currentOptions.toggle_close_label = value;
        });

        this.bindField('sidebar_jlg_settings[header_logo_type]', (value) => {
            this.currentOptions.header_logo_type = value;
        });

        this.bindField('sidebar_jlg_settings[header_logo_image]', (value) => {
            this.currentOptions.header_logo_image = value;
        });

        this.bindField('sidebar_jlg_settings[header_logo_size]', (value) => {
            this.currentOptions.header_logo_size = value;
        });

        this.bindField('sidebar_jlg_settings[header_alignment_desktop]', (value) => {
            this.currentOptions.header_alignment_desktop = value || 'flex-start';
        });

        this.bindField('sidebar_jlg_settings[header_alignment_mobile]', (value) => {
            this.currentOptions.header_alignment_mobile = value || 'center';
        });

        this.bindDimensionField('sidebar_jlg_settings[header_padding_top]', 'header_padding_top');

        this.bindField('sidebar_jlg_settings[social_position]', (value) => {
            this.currentOptions.social_position = value || 'footer';
        });

        this.bindField('sidebar_jlg_settings[social_orientation]', (value) => {
            this.currentOptions.social_orientation = value || 'horizontal';
        });

        this.bindField('sidebar_jlg_settings[social_icon_size]', (value) => {
            this.currentOptions.social_icon_size = value;
        });

        this.isInitializing = false;

        this.menuContainer = document.getElementById('menu-items-container');
        if (this.menuContainer) {
            this.menuContainer.addEventListener('input', this.updateMenuFromDomBound);
            this.menuContainer.addEventListener('change', this.updateMenuFromDomBound);
            this.menuObserver = new MutationObserver(() => this.updateMenuFromDom());
            this.menuObserver.observe(this.menuContainer, { childList: true, subtree: true });
        }

        this.socialContainer = document.getElementById('social-icons-container');
        if (this.socialContainer) {
            this.socialContainer.addEventListener('input', this.updateSocialFromDomBound);
            this.socialContainer.addEventListener('change', this.updateSocialFromDomBound);
            this.socialObserver = new MutationObserver(() => this.updateSocialFromDom());
            this.socialObserver.observe(this.socialContainer, { childList: true, subtree: true });
        }

        this.updateMenuFromDom();
        this.updateSocialFromDom();
    }

    bindField(name, handler) {
        if (!this.form) {
            return;
        }

        const selector = `[name="${name.replace(/"/g, '\"')}"]`;
        const elements = Array.from(this.form.querySelectorAll(selector));

        if (!elements.length) {
            return;
        }

        const update = () => {
            if (this.isComparing) {
                this.disableComparison();
            }
            const value = this.getFieldValue(elements);
            handler(value, elements);
            if (!this.isInitializing) {
                this.applyOptions();
            }
        };

        elements.forEach((element) => {
            const eventName = element.type === 'radio' || element.type === 'checkbox' ? 'change' : 'input';
            element.addEventListener(eventName, update);
        });

        update();
    }

    bindDimensionField(name, optionKey) {
        if (!this.form) {
            return;
        }

        const baseName = name.replace(/"/g, '\"');
        const valueSelector = `[name="${baseName}[value]"]`;
        const unitSelector = `[name="${baseName}[unit]"]`;
        const valueElement = this.form.querySelector(valueSelector);
        const unitElement = this.form.querySelector(unitSelector);

        if (!valueElement || !unitElement) {
            return;
        }

        const update = () => {
            if (this.isComparing) {
                this.disableComparison();
            }
            const fallbackUnit = this.initialOptions[optionKey] && typeof this.initialOptions[optionKey].unit === 'string'
                ? this.initialOptions[optionKey].unit
                : 'px';
            const dimension = parseDimensionValue({
                value: valueElement.value,
                unit: unitElement.value,
            }, fallbackUnit);

            this.currentOptions[optionKey] = dimension;

            if (!this.isInitializing) {
                this.applyOptions();
            }
        };

        valueElement.addEventListener('input', update);
        valueElement.addEventListener('change', update);
        unitElement.addEventListener('input', update);
        unitElement.addEventListener('change', update);

        update();
    }

    getFieldValue(elements) {
        if (!elements.length) {
            return null;
        }

        if (elements.length > 1 && elements[0].type === 'radio') {
            const checked = elements.find((element) => element.checked);
            return checked ? checked.value : null;
        }

        const element = elements[0];

        if (element.type === 'checkbox') {
            return element.checked ? element.value || true : false;
        }

        return element.value;
    }

    applyOptions() {
        this.applyCssVariables();
        this.updateLayout();
        this.updateHeader();
        this.renderMenu();
        this.renderSocial();
        this.updateAccessibilityLabels();
        if (!this.isComparing) {
            this.afterOptionsSnapshot = SidebarPreviewModule.cloneObject(this.currentOptions);
        }
        this.updatePreviewSizeClasses();
    }

    enableComparison() {
        if (this.isComparing) {
            return this.isComparing;
        }

        this.afterOptionsSnapshot = SidebarPreviewModule.cloneObject(this.currentOptions);
        this.currentOptions = SidebarPreviewModule.cloneObject(this.initialOptions);
        this.isComparing = true;
        this.applyOptions();

        return this.isComparing;
    }

    disableComparison() {
        if (!this.isComparing) {
            return this.isComparing;
        }

        const snapshot = this.afterOptionsSnapshot && typeof this.afterOptionsSnapshot === 'object'
            ? SidebarPreviewModule.cloneObject(this.afterOptionsSnapshot)
            : SidebarPreviewModule.cloneObject(this.initialOptions);

        this.isComparing = false;
        this.currentOptions = snapshot;
        this.applyOptions();

        return this.isComparing;
    }

    toggleComparison(forceState) {
        const shouldEnable = typeof forceState === 'boolean' ? forceState : !this.isComparing;
        if (shouldEnable) {
            this.enableComparison();
        } else {
            this.disableComparison();
        }

        return this.isComparing;
    }

    isComparisonActive() {
        return this.isComparing;
    }

    refreshPreview() {
        if (!this.$) {
            return Promise.resolve();
        }

        const wasComparing = this.isComparing;
        if (wasComparing) {
            this.disableComparison();
        }

        this.setState('loading');
        this.setStatus(this.messages.loading || '', false);

        const requestOptions = SidebarPreviewModule.cloneObject(this.currentOptions);

        return this.loadPreview(requestOptions)
            .then((data) => {
                this.setState('ready');
                this.clearStatus();
                this.setupToolbar();
                this.setupBindings();
                this.applyOptions();
                if (wasComparing) {
                    this.enableComparison();
                }
                return data;
            })
            .catch((error) => {
                return Promise.reject(error);
            });
    }

    applyCssVariables() {
        if (!this.container) {
            return;
        }

        const assignments = [
            ['--sidebar-bg-color', this.currentOptions.bg_color],
            ['--sidebar-text-color', this.currentOptions.font_color],
            ['--sidebar-hover-color', this.currentOptions.font_hover_color],
            ['--sidebar-width-desktop', this.formatDimension(this.currentOptions.width_desktop)],
            ['--sidebar-width-tablet', this.formatDimension(this.currentOptions.width_tablet)],
            ['--sidebar-width-mobile', this.formatDimension(this.currentOptions.width_mobile)],
            ['--sidebar-overlay-color', this.currentOptions.overlay_color],
            ['--sidebar-overlay-opacity', this.formatOpacity(this.currentOptions.overlay_opacity)],
            ['--sidebar-hamburger-color', this.currentOptions.hamburger_color],
            ['--sidebar-hamburger-top', this.formatDimension(this.currentOptions.hamburger_top_position)],
            ['--sidebar-hamburger-inline', this.formatDimension(this.currentOptions.hamburger_horizontal_offset)],
            ['--sidebar-hamburger-size', this.formatDimension(this.currentOptions.hamburger_size)],
            ['--sidebar-content-margin', this.formatDimension(this.currentOptions.content_margin)],
            ['--sidebar-floating-margin', this.formatDimension(this.currentOptions.floating_vertical_margin)],
            ['--sidebar-border-radius', this.formatDimension(this.currentOptions.border_radius)],
            ['--sidebar-border-width', this.formatDimension(this.currentOptions.border_width)],
            ['--sidebar-border-color', this.currentOptions.border_color],
            ['--sidebar-menu-align-desktop', this.currentOptions.menu_alignment_desktop],
            ['--sidebar-menu-align-mobile', this.currentOptions.menu_alignment_mobile],
            ['--sidebar-social-size', this.formatPercent(this.currentOptions.social_icon_size)],
            ['--sidebar-font-size', this.formatDimension(this.currentOptions.font_size)],
            ['--sidebar-font-family', this.resolveFontFamily()],
            ['--sidebar-font-weight', this.currentOptions.font_weight],
            ['--sidebar-text-transform', this.currentOptions.text_transform],
            ['--sidebar-letter-spacing', this.formatDimension(this.currentOptions.letter_spacing)],
            ['--mobile-bg-color', this.currentOptions.mobile_bg_color],
            ['--mobile-bg-opacity', this.formatOpacity(this.currentOptions.mobile_bg_opacity)],
            ['--mobile-blur', this.formatDimension(this.currentOptions.mobile_blur)],
            ['--sidebar-accent-color', this.resolveAccentBaseColor()],
            ['--sidebar-logo-size', this.formatDimension(this.currentOptions.header_logo_size)],
            ['--sidebar-header-align-desktop', this.currentOptions.header_alignment_desktop],
            ['--sidebar-header-align-mobile', this.currentOptions.header_alignment_mobile],
            ['--sidebar-header-padding-top', this.formatDimension(this.currentOptions.header_padding_top)],
            ['--sidebar-horizontal-alignment', this.currentOptions.horizontal_bar_alignment],
            ['--sidebar-horizontal-bar-height', this.formatDimension(this.currentOptions.horizontal_bar_height)]
        ];

        assignments.forEach(([variable, value]) => {
            if (value === undefined || value === null || value === '') {
                this.container.style.removeProperty(variable);
            } else {
                this.container.style.setProperty(variable, value);
            }
        });

        const bgType = (this.currentOptions.bg_color_type || 'solid').toLowerCase();
        const start = this.currentOptions.bg_color_start || '';
        const end = this.currentOptions.bg_color_end || '';

        if (bgType === 'gradient' && start && end) {
            this.container.style.setProperty('--sidebar-bg-image', `linear-gradient(180deg, ${start} 0%, ${end} 100%)`);
            this.container.style.setProperty('--sidebar-bg-color', start);
        } else {
            this.container.style.setProperty('--sidebar-bg-image', 'none');
            if (this.currentOptions.bg_color) {
                this.container.style.setProperty('--sidebar-bg-color', this.currentOptions.bg_color);
            }
        }

        this.applyAccentGradient();
    }

    formatDimension(value) {
        if (value === null || value === undefined) {
            return '';
        }

        if (typeof value === 'object' && Object.prototype.hasOwnProperty.call(value, 'value')) {
            return dimensionToCssString(value, '');
        }

        if (value === '') {
            return '';
        }

        if (typeof value === 'number') {
            if (!Number.isFinite(value)) {
                return '';
            }
            return `${value}px`;
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        if (/^-?\d+(?:\.\d+)?$/.test(stringValue)) {
            return `${stringValue}px`;
        }

        return stringValue;
    }

    formatOpacity(value) {
        const numeric = parseFloat(value);
        if (!Number.isFinite(numeric)) {
            return '0.5';
        }
        return Math.min(1, Math.max(0, numeric)).toString();
    }

    formatPercent(value) {
        if (value === null || value === undefined || value === '') {
            return '100%';
        }

        if (typeof value === 'number' || /^-?\d+(?:\.\d+)?$/.test(String(value))) {
            return `${value}%`;
        }

        return String(value);
    }

    updateLayout() {
        if (!this.aside) {
            return;
        }

        const layout = this.currentOptions.layout_style || 'full';
        const position = this.currentOptions.sidebar_position === 'right' ? 'right' : 'left';
        const horizontalPosition = this.currentOptions.horizontal_bar_position === 'bottom' ? 'bottom' : 'top';
        const horizontalAlignment = this.currentOptions.horizontal_bar_alignment || 'space-between';
        const isSticky = Boolean(this.currentOptions.horizontal_bar_sticky);

        this.replaceClass(this.aside, 'layout-', layout);
        this.replaceClass(this.aside, 'orientation-', position);
        this.replaceClass(this.aside, 'position-', layout === 'horizontal-bar' ? horizontalPosition : '');

        if (layout === 'horizontal-bar' && isSticky) {
            this.aside.classList.add('is-sticky');
        } else {
            this.aside.classList.remove('is-sticky');
        }

        if (this.menuList) {
            if (layout === 'horizontal-bar') {
                this.menuList.classList.add('is-horizontal');
            } else {
                this.menuList.classList.remove('is-horizontal');
            }
        }

        if (this.nav) {
            if (layout === 'horizontal-bar') {
                this.nav.classList.add('is-horizontal');
            } else {
                this.nav.classList.remove('is-horizontal');
            }
        }

        if (this.hamburger) {
            this.hamburger.dataset.position = position;
        }

        this.aside.dataset.position = position;
        this.aside.dataset.horizontalAlignment = horizontalAlignment;
        this.aside.dataset.layout = layout;
    }

    replaceClass(element, prefix, suffix) {
        if (!element) {
            return;
        }

        const target = suffix ? `${prefix}${String(suffix).toLowerCase()}` : '';
        const toRemove = [];

        element.classList.forEach((className) => {
            if (className.indexOf(prefix) === 0) {
                toRemove.push(className);
            }
        });

        toRemove.forEach((className) => element.classList.remove(className));

        if (target) {
            element.classList.add(target);
        }
    }

    updateHeader() {
        if (!this.viewport) {
            return;
        }

        const header = this.viewport.querySelector('.sidebar-header');
        if (!header) {
            return;
        }

        const logoType = this.currentOptions.header_logo_type;
        const logoUrl = this.currentOptions.header_logo_image;
        const appName = this.currentOptions.app_name || '';

        let logoImage = header.querySelector('.sidebar-logo-image');
        let logoText = header.querySelector('.logo-text');

        if (logoType === 'image' && logoUrl) {
            if (!logoImage) {
                logoImage = document.createElement('img');
                logoImage.className = 'sidebar-logo-image';
                header.insertBefore(logoImage, header.firstChild);
            }

            logoImage.src = logoUrl;
            logoImage.alt = appName;

            if (logoText) {
                logoText.remove();
            }
        } else {
            if (!logoText) {
                logoText = document.createElement('span');
                logoText.className = 'logo-text';
                header.insertBefore(logoText, header.firstChild);
            }

            logoText.textContent = appName;

            if (logoImage) {
                logoImage.remove();
            }
        }
    }

    updateMenuFromDom() {
        const items = [];

        if (this.menuContainer) {
            const boxes = this.menuContainer.querySelectorAll('.menu-item-box');

            boxes.forEach((box) => {
                const labelField = box.querySelector('.item-label');
                const label = labelField && typeof labelField.value === 'string' ? labelField.value.trim() : '';
                let url = '#';

                const customInput = box.querySelector('input[name*="[value]"]');
                const selectInput = box.querySelector('select[name*="[value]"]');

                if (customInput && typeof customInput.value === 'string' && customInput.value.trim() !== '') {
                    url = customInput.value.trim();
                } else if (selectInput && typeof selectInput.value === 'string' && selectInput.value.trim() !== '') {
                    url = selectInput.value.trim();
                }

                const iconPreview = box.querySelector('.menu-item-icon-wrapper .icon-preview');
                const iconHtml = iconPreview ? iconPreview.innerHTML : '';

                items.push({ label, url, iconHtml });
            });
        }

        this.currentOptions.menu_items = items;
        this.renderMenu();
    }

    escapeHtml(value) {
        return String(value || '').replace(/[&<>"']/g, (match) => {
            switch (match) {
                case '&':
                    return '&amp;';
                case '<':
                    return '&lt;';
                case '>':
                    return '&gt;';
                case '"':
                    return '&quot;';
                case "'":
                    return '&#039;';
                default:
                    return match;
            }
        });
    }

    buildMenuItem(item) {
        const li = document.createElement('li');
        const link = document.createElement('a');
        link.href = '#';
        link.dataset.previewUrl = item.url || '#';
        link.addEventListener('click', (event) => event.preventDefault());

        if (item.iconHtml) {
            const iconSpan = document.createElement('span');
            iconSpan.className = 'menu-icon';
            iconSpan.innerHTML = item.iconHtml;
            link.appendChild(iconSpan);
        }

        const labelSpan = document.createElement('span');
        labelSpan.textContent = item.label || item.url || '#';
        link.appendChild(labelSpan);

        li.appendChild(link);
        return li;
    }

    renderMenu() {
        if (!this.menuList) {
            return;
        }

        const items = Array.isArray(this.currentOptions.menu_items) ? this.currentOptions.menu_items : [];
        const socialItem = this.menuSocialItem && this.menuSocialItem.parentElement === this.menuList
            ? this.menuSocialItem
            : null;

        if (socialItem) {
            this.menuList.removeChild(socialItem);
        }

        this.menuList.innerHTML = '';

        if (!items.length) {
            const li = document.createElement('li');
            li.className = 'menu-empty-message';
            li.textContent = this.messages.emptyMenu || '';
            this.menuList.appendChild(li);
        } else {
            const fragment = document.createDocumentFragment();
            items.forEach((item) => {
                fragment.appendChild(this.buildMenuItem(item));
            });
            this.menuList.appendChild(fragment);
        }

        if (socialItem) {
            this.menuList.appendChild(socialItem);
        }
    }

    updateSocialFromDom() {
        const icons = [];

        if (this.socialContainer) {
            const boxes = this.socialContainer.querySelectorAll('.menu-item-box');

            boxes.forEach((box) => {
                const urlField = box.querySelector('.social-url');
                const iconPreview = box.querySelector('.icon-preview');
                const labelField = box.querySelector('.item-label');

                const url = urlField && typeof urlField.value === 'string' ? urlField.value.trim() : '';

                if (!url) {
                    return;
                }

                icons.push({
                    url,
                    iconHtml: iconPreview ? iconPreview.innerHTML : '',
                    label: labelField && typeof labelField.value === 'string' ? labelField.value.trim() : ''
                });
            });
        }

        this.currentOptions.social_icons = icons;
        this.renderSocial();
    }

    buildSocialMarkup(icons, orientation) {
        if (!Array.isArray(icons) || !icons.length) {
            return '';
        }

        const orientationClass = orientation === 'vertical' ? 'vertical' : 'horizontal';

        const items = icons
            .filter((icon) => icon && icon.url)
            .map((icon) => {
                const label = this.escapeHtml(icon.label || icon.url);
                const content = icon.iconHtml && icon.iconHtml.trim() !== ''
                    ? icon.iconHtml
                    : `<span class="no-icon-label">${label}</span>`;

                return `<a href="#" data-preview-url="${this.escapeHtml(icon.url)}" aria-label="${label}">${content}</a>`;
            })
            .join('');

        if (!items) {
            return '';
        }

        return `<div class="social-icons ${orientationClass}">${items}</div>`;
    }

    renderSocial() {
        const icons = Array.isArray(this.currentOptions.social_icons) ? this.currentOptions.social_icons : [];
        const position = this.currentOptions.social_position === 'in-menu' ? 'in-menu' : 'footer';
        const orientation = this.currentOptions.social_orientation || 'horizontal';
        const markup = this.buildSocialMarkup(icons, orientation);

        if (position === 'in-menu') {
            if (!this.menuList) {
                return;
            }

            if (!this.menuSocialItem) {
                this.menuSocialItem = document.createElement('li');
                this.menuSocialItem.className = 'social-icons-wrapper';
            }

            this.menuSocialItem.innerHTML = markup;
            this.menuSocialItem.style.display = markup ? '' : 'none';

            if (!this.menuList.contains(this.menuSocialItem)) {
                this.menuList.appendChild(this.menuSocialItem);
            }

            if (this.footerSocial) {
                this.footerSocial.style.display = 'none';
            }
        } else {
            if (!this.footerSocial && this.aside) {
                const inner = this.aside.querySelector('.sidebar-inner');
                this.footerSocial = document.createElement('div');
                this.footerSocial.className = 'sidebar-footer';
                if (inner) {
                    inner.appendChild(this.footerSocial);
                } else {
                    this.aside.appendChild(this.footerSocial);
                }
            }

            if (this.footerSocial) {
                this.footerSocial.innerHTML = markup;
                this.footerSocial.style.display = markup ? '' : 'none';
            }

            if (this.menuSocialItem && this.menuSocialItem.parentElement) {
                this.menuSocialItem.parentElement.removeChild(this.menuSocialItem);
            }
        }
    }

    updateAccessibilityLabels() {
        const navLabelValue = typeof this.currentOptions.nav_aria_label === 'string'
            ? this.currentOptions.nav_aria_label.trim()
            : '';
        const navLabel = navLabelValue !== ''
            ? navLabelValue
            : (this.defaultNavAriaLabel || '');

        if (this.nav) {
            if (navLabel) {
                this.nav.setAttribute('aria-label', navLabel);
            } else {
                this.nav.removeAttribute('aria-label');
            }
        }

        const expandValue = typeof this.currentOptions.toggle_open_label === 'string'
            ? this.currentOptions.toggle_open_label.trim()
            : '';
        const collapseValue = typeof this.currentOptions.toggle_close_label === 'string'
            ? this.currentOptions.toggle_close_label.trim()
            : '';

        const expandLabel = expandValue !== '' ? expandValue : (this.defaultToggleExpandLabel || '');
        const collapseLabel = collapseValue !== '' ? collapseValue : (this.defaultToggleCollapseLabel || '');

        if (!this.menuList) {
            return;
        }

        const toggles = this.menuList.querySelectorAll('.submenu-toggle');
        toggles.forEach((button) => {
            if (expandLabel) {
                button.setAttribute('data-label-expand', expandLabel);
            } else {
                button.removeAttribute('data-label-expand');
            }

            if (collapseLabel) {
                button.setAttribute('data-label-collapse', collapseLabel);
            } else {
                button.removeAttribute('data-label-collapse');
            }

            const isExpanded = button.getAttribute('aria-expanded') === 'true';
            const labelForState = isExpanded ? collapseLabel : expandLabel;

            if (labelForState) {
                button.setAttribute('aria-label', labelForState);
            } else {
                button.removeAttribute('aria-label');
            }

            const srText = button.querySelector('.screen-reader-text');
            if (srText) {
                srText.textContent = labelForState || '';
            }
        });
    }
}

jQuery(document).ready(function($) {
    const options = sidebarJLG.options || {};
    const ajaxUrl = sidebarJLG.ajax_url || '';
    const ajaxNonce = sidebarJLG.nonce || '';
    const iconFetchAction = typeof sidebarJLG.icon_fetch_action === 'string' ? sidebarJLG.icon_fetch_action : 'jlg_get_icon_svg';
    const toolsNonce = typeof sidebarJLG.tools_nonce === 'string' ? sidebarJLG.tools_nonce : '';
    let iconManifest = Array.isArray(sidebarJLG.icons_manifest) ? sidebarJLG.icons_manifest : [];
    const iconUploadAction = typeof sidebarJLG.icon_upload_action === 'string' ? sidebarJLG.icon_upload_action : '';
    const parsedUploadMax = parseInt(sidebarJLG.icon_upload_max_size, 10);
    const iconUploadMaxSize = !isNaN(parsedUploadMax) && parsedUploadMax > 0 ? parsedUploadMax : 0;
    const debugMode = options.debug_mode == '1';
    const ajaxCache = { posts: {}, categories: {} };
    const searchDebounceDelay = 300;
    const iconCache = {};
    const pendingIconRequests = {};
    const iconEntryByExactKey = {};
    const iconKeyLookup = {};

    const previewModule = new SidebarPreviewModule({
        container: document.getElementById('sidebar-jlg-preview'),
        form: document.getElementById('sidebar-jlg-form'),
        ajaxUrl,
        nonce: sidebarJLG.preview_nonce || '',
        action: sidebarJLG.preview_action || 'jlg_render_preview',
        options,
        messages: sidebarJLG.preview_messages || {}
    });

    previewModule.init();

    initializeUnitControls();
    initializeRangeControls();

    if (typeof window !== 'undefined') {
        window.SidebarJLGPreview = previewModule;
    }

    const stylePresetsData = (typeof sidebarJLG.style_presets === 'object' && sidebarJLG.style_presets !== null)
        ? sidebarJLG.style_presets
        : {};
    initializeStylePresetCards(stylePresetsData);
    initializePreviewToolbar(previewModule);

    function rebuildIconLookups(manifest) {
        iconManifest = Array.isArray(manifest) ? manifest : [];

        Object.keys(iconEntryByExactKey).forEach(key => {
            delete iconEntryByExactKey[key];
        });

        Object.keys(iconKeyLookup).forEach(key => {
            delete iconKeyLookup[key];
        });

        iconManifest.forEach(icon => {
            if (!icon || typeof icon.key !== 'string') {
                return;
            }

            const key = icon.key;
            iconEntryByExactKey[key] = icon;

            if (key === '') {
                return;
            }

            if (!iconKeyLookup[key]) {
                iconKeyLookup[key] = key;
            }

            const sanitized = sanitizeIconKey(key);
            if (sanitized && !iconKeyLookup[sanitized]) {
                iconKeyLookup[sanitized] = key;
            }
        });
    }

    rebuildIconLookups(iconManifest);

    function logDebug(message, data = '') {
        if (debugMode) {
            console.log(`[Sidebar JLG Debug] ${message}`, data);
        }
    }

    function applyPresetValues(values) {
        if (!values || typeof values !== 'object') {
            return;
        }

        const unitControlContainers = new Set();

        Object.entries(values).forEach(([optionKey, optionValue]) => {
            if (optionValue && typeof optionValue === 'object' && Object.prototype.hasOwnProperty.call(optionValue, 'value') && Object.prototype.hasOwnProperty.call(optionValue, 'unit')) {
                const valueInput = document.querySelector(`[name="sidebar_jlg_settings[${optionKey}][value]"]`);
                const unitInput = document.querySelector(`[name="sidebar_jlg_settings[${optionKey}][unit]"]`);

                if (valueInput) {
                    valueInput.value = optionValue.value;
                    triggerFieldUpdate(valueInput);
                    const container = valueInput.closest('[data-sidebar-unit-control]');
                    if (container) {
                        unitControlContainers.add(container);
                    }
                }

                if (unitInput) {
                    unitInput.value = optionValue.unit;
                    triggerFieldUpdate(unitInput);
                }

                return;
            }

            const selector = `[name="sidebar_jlg_settings[${optionKey}]"]`;
            const elements = Array.from(document.querySelectorAll(selector));
            if (!elements.length) {
                return;
            }

            const type = elements[0].type || elements[0].getAttribute('type') || '';

            if (type === 'radio') {
                elements.forEach((element) => {
                    element.checked = element.value === optionValue;
                    if (element.checked) {
                        triggerFieldUpdate(element);
                    }
                });
                return;
            }

            if (type === 'checkbox') {
                const isChecked = optionValue === true || optionValue === '1' || optionValue === 1 || optionValue === 'on';
                elements.forEach((element) => {
                    element.checked = isChecked;
                    triggerFieldUpdate(element);
                });
                return;
            }

            elements.forEach((element) => {
                const $element = $(element);
                if ($element.hasClass('color-picker') || $element.hasClass('color-picker-rgba')) {
                    if (typeof $element.wpColorPicker === 'function') {
                        $element.wpColorPicker('color', optionValue);
                    } else {
                        element.value = optionValue;
                        triggerFieldUpdate(element);
                    }
                    return;
                }

                element.value = optionValue;
                triggerFieldUpdate(element);
            });
        });

        if (unitControlContainers.size) {
            initializeUnitControls();
        }
    }

    function initializeStylePresetCards(presets) {
        const container = document.querySelector('.sidebar-jlg-style-presets');
        if (!container) {
            return;
        }

        const cards = Array.from(container.querySelectorAll('.sidebar-jlg-style-preset-card'));
        if (!cards.length) {
            return;
        }

        const radios = cards
            .map((card) => card.querySelector('input[name="sidebar_jlg_settings[style_preset]"]'))
            .filter(Boolean);

        const setActiveCard = (key) => {
            cards.forEach((card) => {
                const cardKey = card.getAttribute('data-preset-key');
                card.classList.toggle('is-active', cardKey === key);
            });
        };

        const applyPresetForKey = (key) => {
            if (!presets || typeof presets !== 'object') {
                return;
            }

            const preset = presets[key];
            if (!preset || typeof preset !== 'object') {
                return;
            }

            const values = preset.values || {};
            applyPresetValues(values);
        };

        radios.forEach((radio) => {
            radio.addEventListener('change', () => {
                if (!radio.checked) {
                    return;
                }

                const key = radio.value;
                setActiveCard(key);

                if (key === 'custom') {
                    return;
                }

                if (window.SidebarJLGPreview && typeof window.SidebarJLGPreview.disableComparison === 'function') {
                    window.SidebarJLGPreview.disableComparison();
                }

                applyPresetForKey(key);

                if (window.SidebarJLGPreview && typeof window.SidebarJLGPreview.refreshPreview === 'function') {
                    window.SidebarJLGPreview.refreshPreview().catch(() => {});
                }
            });
        });

        const activeRadio = radios.find((radio) => radio.checked);
        if (activeRadio) {
            setActiveCard(activeRadio.value);
        }
    }

    function initializePreviewToolbar(previewInstance) {
        if (!previewInstance) {
            return;
        }

        const refreshButton = document.getElementById('sidebar-jlg-preview-refresh');
        if (refreshButton && typeof previewInstance.refreshPreview === 'function') {
            const visibleLabel = refreshButton.querySelector('[aria-hidden="true"]');
            const originalText = visibleLabel ? visibleLabel.textContent : '';
            const loadingText = getI18nString('previewRefreshLoading', 'Actualisation…');
            const defaultText = getI18nString('previewRefreshLabel', originalText || 'Actualiser');

            refreshButton.addEventListener('click', () => {
                if (refreshButton.disabled) {
                    return;
                }

                refreshButton.disabled = true;
                if (visibleLabel) {
                    visibleLabel.textContent = loadingText;
                }

                previewInstance.refreshPreview()
                    .catch(() => {})
                    .finally(() => {
                        refreshButton.disabled = false;
                        if (visibleLabel) {
                            visibleLabel.textContent = defaultText;
                        }
                    });
            });
        }

        const compareButton = document.getElementById('sidebar-jlg-preview-compare');
        if (compareButton && typeof previewInstance.toggleComparison === 'function') {
            const visibleLabel = compareButton.querySelector('[aria-hidden="true"]');
            const srLabel = compareButton.querySelector('.screen-reader-text');
            const labelOff = getI18nString('previewCompareToggleOff', 'Comparer avant/après');
            const labelOn = getI18nString('previewCompareToggleOn', 'Afficher l’après');
            const srText = getI18nString('previewCompareSr', 'Basculer entre l’aperçu avant et après vos modifications');

            const updateButtonState = (isActive) => {
                compareButton.classList.toggle('is-active', !!isActive);
                compareButton.setAttribute('aria-pressed', isActive ? 'true' : 'false');
                if (visibleLabel) {
                    visibleLabel.textContent = isActive ? labelOn : labelOff;
                }
                if (srLabel) {
                    srLabel.textContent = srText;
                }
            };

            compareButton.addEventListener('click', () => {
                const nextState = previewInstance.toggleComparison();
                updateButtonState(nextState);
            });

            if (typeof previewInstance.isComparisonActive === 'function') {
                updateButtonState(previewInstance.isComparisonActive());
            } else {
                updateButtonState(false);
            }
        }
    }

    function parseAllowedUnitsAttribute(raw) {
        if (typeof raw !== 'string' || raw.trim() === '') {
            return [];
        }

        try {
            const parsed = JSON.parse(raw);
            if (!Array.isArray(parsed)) {
                return [];
            }

            return parsed
                .filter((unit) => typeof unit === 'string' && unit.trim() !== '')
                .map((unit) => unit.trim());
        } catch (error) {
            return [];
        }
    }

    function initializeUnitControls() {
        if (!window.wp || !wp.element || !wp.components || !wp.components.UnitControl) {
            return;
        }

        const { createElement, useMemo, useState } = wp.element;
        const { UnitControl } = wp.components;

        document.querySelectorAll('[data-sidebar-unit-control]').forEach((container) => {
            const valueInput = container.querySelector('input[data-dimension-value]');
            const unitInput = container.querySelector('input[data-dimension-unit]');

            if (!valueInput || !unitInput) {
                return;
            }

            const label = container.dataset.label || '';
            const helpText = container.dataset.help || '';
            const errorMessage = container.dataset.errorMessage || '';
            const defaultValue = container.dataset.defaultValue || '';
            const defaultUnit = container.dataset.defaultUnit || 'px';
            const allowedUnits = parseAllowedUnitsAttribute(container.dataset.allowedUnits || '');

            const initialDimension = parseDimensionValue(
                { value: valueInput.value, unit: unitInput.value },
                defaultUnit,
                allowedUnits
            );

            const defaultDimension = parseDimensionValue(
                { value: defaultValue, unit: defaultUnit },
                defaultUnit,
                allowedUnits
            );

            const UnitField = () => {
                const [currentValue, setCurrentValue] = useState(initialDimension.value);
                const [currentUnit, setCurrentUnit] = useState(initialDimension.unit);
                const [feedback, setFeedback] = useState('');

                const units = useMemo(() => {
                    const list = allowedUnits && allowedUnits.length
                        ? allowedUnits
                        : [initialDimension.unit || defaultDimension.unit || defaultUnit];
                    return list.map((unit) => ({ value: unit, label: unit }));
                }, [allowedUnits, initialDimension.unit, defaultDimension.unit]);

                const updateValue = (nextValue) => {
                    const normalized = normalizeNumericString(nextValue);
                    if (normalized === '') {
                        setCurrentValue('');
                        valueInput.value = '';
                        setFeedback(errorMessage || helpText);
                    } else {
                        setCurrentValue(normalized);
                        valueInput.value = normalized;
                        setFeedback('');
                    }

                    triggerFieldUpdate(valueInput);
                };

                const updateUnit = (nextUnit) => {
                    const normalized = parseDimensionValue(
                        { value: currentValue || defaultDimension.value, unit: nextUnit },
                        defaultUnit,
                        allowedUnits
                    ).unit;
                    setCurrentUnit(normalized);
                    unitInput.value = normalized;
                    triggerFieldUpdate(unitInput);
                };

                const handleBlur = () => {
                    if (currentValue === '') {
                        const fallbackValue = defaultDimension.value !== '' ? defaultDimension.value : '0';
                        const fallbackUnit = defaultDimension.unit || currentUnit || defaultUnit;
                        setCurrentValue(fallbackValue);
                        valueInput.value = fallbackValue;
                        const normalizedUnit = parseDimensionValue(
                            { value: fallbackValue, unit: fallbackUnit },
                            defaultUnit,
                            allowedUnits
                        ).unit;
                        setCurrentUnit(normalizedUnit);
                        unitInput.value = normalizedUnit;
                        setFeedback('');
                        triggerFieldUpdate(valueInput);
                        triggerFieldUpdate(unitInput);
                    }
                };

                return createElement(UnitControl, {
                    label,
                    value: currentValue === '' ? '' : parseFloat(currentValue),
                    unit: currentUnit,
                    units,
                    onChange: updateValue,
                    onUnitChange: updateUnit,
                    onBlur: handleBlur,
                    isInvalid: currentValue === '',
                    help: feedback || helpText,
                });
            };

            wp.element.render(createElement(UnitField), container);
        });
    }

    function initializeRangeControls() {
        if (!window.wp || !wp.element || !wp.components || !wp.components.RangeControl) {
            return;
        }

        const { createElement, useState } = wp.element;
        const { RangeControl } = wp.components;

        document.querySelectorAll('[data-sidebar-range-control]').forEach((container) => {
            const hiddenInput = container.querySelector('input[data-range-value]');
            if (!hiddenInput) {
                return;
            }

            const label = container.dataset.label || '';
            const helpText = container.dataset.help || '';
            const errorMessage = container.dataset.errorMessage || '';
            const min = Number.parseFloat(container.dataset.min);
            const max = Number.parseFloat(container.dataset.max);
            const step = Number.parseFloat(container.dataset.step);

            const safeMin = Number.isFinite(min) ? min : 0;
            const safeMax = Number.isFinite(max) ? max : 1;
            const safeStep = Number.isFinite(step) && step > 0 ? step : 0.01;

            const initialValue = Number.parseFloat(hiddenInput.value);
            const normalizedInitial = Number.isFinite(initialValue) ? initialValue : safeMin;
            hiddenInput.value = normalizeNumericString(normalizedInitial);

            const RangeField = () => {
                const [currentValue, setCurrentValue] = useState(normalizedInitial);
                const [feedback, setFeedback] = useState('');

                const updateValue = (nextValue) => {
                    const parsed = typeof nextValue === 'number' ? nextValue : Number.parseFloat(nextValue);
                    if (!Number.isFinite(parsed)) {
                        setFeedback(errorMessage || helpText);
                        return;
                    }

                    const clamped = Math.min(safeMax, Math.max(safeMin, parsed));
                    const normalized = parseFloat(clamped.toFixed(3));
                    setCurrentValue(normalized);
                    hiddenInput.value = normalizeNumericString(normalized);
                    setFeedback('');
                    triggerFieldUpdate(hiddenInput);
                };

                return createElement(RangeControl, {
                    label,
                    value: currentValue,
                    onChange: updateValue,
                    min: safeMin,
                    max: safeMax,
                    step: safeStep,
                    help: feedback || helpText,
                });
            };

            wp.element.render(createElement(RangeField), container);
        });
    }

    function renderNotice(type, message) {
        const container = $('#sidebar-jlg-js-notices');
        const $target = container.length ? container : $('.sidebar-jlg-admin-wrap');

        if (!$target || !$target.length) {
            return;
        }

        const typeClasses = {
            success: 'notice-success',
            error: 'notice-error',
            warning: 'notice-warning',
            info: 'notice-info'
        };

        const noticeClass = typeClasses[type] || typeClasses.info;
        const dismissText = getI18nString('dismissNotice', 'Ignorer cette notification.');

        const $notice = $('<div/>', {
            class: `notice ${noticeClass} is-dismissible sidebar-jlg-notice`
        });

        const $message = $('<p/>');
        $message.text(message || '');
        $notice.append($message);

        const $dismissButton = $('<button type="button" class="notice-dismiss"></button>');
        const $dismissScreenReader = $('<span class="screen-reader-text"></span>');
        $dismissScreenReader.text(dismissText);
        $dismissButton.append($dismissScreenReader);
        $notice.append($dismissButton);

        $target.find('.sidebar-jlg-notice').remove();
        $target.prepend($notice);

        $notice.on('click', '.notice-dismiss', function() {
            $notice.remove();
        });
    }

    function getResponseMessage(response, fallback) {
        if (response && typeof response === 'object') {
            if (response.data && typeof response.data.message === 'string') {
                return response.data.message;
            }

            if (typeof response.message === 'string') {
                return response.message;
            }
        }

        return fallback;
    }

    function debounce(fn, delay) {
        let timeoutId;

        return function(...args) {
            const context = this;
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => fn.apply(context, args), delay);
        };
    }

    logDebug('Admin script loaded.', options);

    // --- Initialisation ---
    $('.color-picker-rgba').wpColorPicker({
        change: function(event, ui) { $(event.target).val(ui.color.toString()).trigger('change'); },
        clear: function() { $(this).val('').trigger('change'); }
    });
    
    // --- Gestion des onglets ---
    const tabWrapper = $('.nav-tab-wrapper');
    const tabPanels = $('.tab-content');

    function getTabs() {
        return tabWrapper.find('.nav-tab');
    }

    function setPanelState($panel, isActive) {
        if (!$panel || !$panel.length) {
            return;
        }

        $panel.toggleClass('active', isActive);
        $panel.attr('aria-hidden', isActive ? 'false' : 'true');

        if (isActive) {
            $panel.removeAttr('hidden');
        } else {
            $panel.attr('hidden', 'hidden');
        }
    }

    function activateTab($tab, shouldFocus = false) {
        if (!$tab || !$tab.length) {
            return;
        }

        const tabs = getTabs();
        const panelId = $tab.attr('aria-controls');
        const panelElement = panelId ? document.getElementById(panelId) : null;
        const $panel = panelElement ? $(panelElement) : $();

        tabs.each(function() {
            const $currentTab = $(this);
            const isActive = $currentTab.is($tab);

            $currentTab.toggleClass('nav-tab-active', isActive);
            $currentTab.attr('aria-selected', isActive ? 'true' : 'false');
            $currentTab.attr('tabindex', isActive ? '0' : '-1');
        });

        tabPanels.each(function() {
            const $currentPanel = $(this);
            const isActive = $currentPanel.is($panel);
            setPanelState($currentPanel, isActive);
        });

        if (panelElement && !$panel.hasClass('active')) {
            setPanelState($panel, true);
        }

        if (shouldFocus) {
            $tab.trigger('focus');
        }
    }

    function focusTabAtIndex(index) {
        const tabs = getTabs();
        if (!tabs.length) {
            return;
        }

        const normalizedIndex = (index + tabs.length) % tabs.length;
        const $targetTab = tabs.eq(normalizedIndex);
        activateTab($targetTab, true);
    }

    tabWrapper.on('click', '.nav-tab', function(e) {
        e.preventDefault();
        activateTab($(this), true);
    });

    tabWrapper.on('keydown', '.nav-tab', function(e) {
        const key = e.key;
        const tabs = getTabs();
        const currentIndex = tabs.index(this);

        if (key === 'ArrowLeft') {
            e.preventDefault();
            focusTabAtIndex(currentIndex - 1);
        } else if (key === 'ArrowRight') {
            e.preventDefault();
            focusTabAtIndex(currentIndex + 1);
        } else if (key === 'Home') {
            e.preventDefault();
            focusTabAtIndex(0);
        } else if (key === 'End') {
            e.preventDefault();
            focusTabAtIndex(tabs.length - 1);
        } else if (key === ' ' || key === 'Spacebar' || key === 'Enter') {
            e.preventDefault();
            activateTab($(this), true);
        }
    });

    const initialTab = getTabs().filter('.nav-tab-active').first();
    if (initialTab.length) {
        activateTab(initialTab, false);
    } else {
        activateTab(getTabs().first(), false);
    }

    // --- Options de Comportement ---
    const behaviorSelect = $('.desktop-behavior-select');
    behaviorSelect.on('change', function() {
        $('.push-option-field').toggle($(this).val() === 'push');
    }).trigger('change');

    const layoutStyleRadios = $('input[name="sidebar_jlg_settings[layout_style]"]');
    function updateLayoutStyleFields() {
        const selectedLayout = layoutStyleRadios.filter(':checked').val();
        $('.floating-options-field').toggle(selectedLayout === 'floating');
        $('.horizontal-options-field').toggle(selectedLayout === 'horizontal-bar');
    }
    layoutStyleRadios.on('change', updateLayoutStyleFields);
    updateLayoutStyleFields();

    // --- Options de recherche ---
    const enableSearchCheckbox = $('input[name="sidebar_jlg_settings[enable_search]"]');
    const searchOptionsWrapper = $('.search-options-wrapper');
    const searchMethodSelect = $('.search-method-select');

    enableSearchCheckbox.on('change', function() {
        searchOptionsWrapper.toggle($(this).is(':checked'));
    }).trigger('change');

    searchMethodSelect.on('change', function() {
        const method = $(this).val();
        searchOptionsWrapper.find('.search-method-field').hide();
        searchOptionsWrapper.find(`.search-${method}-field`).show();
    }).trigger('change');
    
    // --- Options de l'en-tête ---
    const logoTypeRadios = $('input[name="sidebar_jlg_settings[header_logo_type]"]');
    logoTypeRadios.on('change', function() {
        if ($(this).val() === 'image') {
            $('.header-image-options').show();
            $('.header-text-options').hide();
        } else {
            $('.header-image-options').hide();
            $('.header-text-options').show();
        }
    }).trigger('change');

    // --- Sliders en temps réel ---
    $('input[type="range"]').each(function() {
        const $input = $(this);
        const $valueDisplay = $input.siblings('span');
        
        if ($valueDisplay.length) {
            $input.on('input', function() {
                $valueDisplay.text($(this).val() + ($valueDisplay.text().includes('px') ? 'px' : 
                                                     $valueDisplay.text().includes('%') ? '%' : 
                                                     $valueDisplay.text().includes('ms') ? 'ms' : ''));
            });
        }
    });

    // --- Upload Media ---
    let mediaFrame;
    $('.upload-logo-button').on('click', function(e) {
        e.preventDefault();
        if (mediaFrame) { mediaFrame.open(); return; }
        mediaFrame = wp.media({
            title: 'Choisir un logo',
            button: { text: 'Utiliser ce logo' },
            multiple: false
        });
        mediaFrame.on('select', function() {
            const attachment = mediaFrame.state().get('selection').first().toJSON();
            $('.header-logo-image-url').val(attachment.url);
            $('.logo-preview img').attr('src', attachment.url).show();
        });
        mediaFrame.open();
    });

    // --- Modale Icônes ---
    const modal = $('#icon-library-modal');
    let currentIconInput = null;

    function openIconLibrary(inputElement, previewElement) {
        currentIconInput = inputElement;
        populateIconGrid();
        modal.show();
    }

    function openMediaLibrary(inputElement, previewElement) {
        const frame = wp.media({
            title: 'Sélectionner une icône SVG',
            button: { text: 'Utiliser cette icône' },
            library: { type: 'image/svg+xml' },
            multiple: false
        });
        frame.on('select', function() {
            const attachment = frame.state().get('selection').first().toJSON();
            inputElement.val(attachment.url).trigger('change');
        });
        frame.open();
    }

    const hiddenSvgUploadInput = $('<input>', {
        type: 'file',
        accept: '.svg,image/svg+xml',
        style: 'display:none;',
        'aria-hidden': 'true'
    });

    $('body').append(hiddenSvgUploadInput);

    hiddenSvgUploadInput.on('change', function() {
        const $originButton = hiddenSvgUploadInput.data('originButton');
        const files = this.files;
        const file = files && files.length ? files[0] : null;

        hiddenSvgUploadInput.val('');
        hiddenSvgUploadInput.removeData('originButton');

        if (!$originButton || !$originButton.length) {
            return;
        }

        if (!file) {
            showUploadMessage($originButton, '', false);
            return;
        }

        uploadSvgBlob($originButton, file, file.name);
    });

    let customIconUploadFrame = null;

    function setUploadButtonState($button, isLoading) {
        if (!$button || !$button.length) {
            return;
        }

        const busy = !!isLoading;
        $button.prop('disabled', busy);

        if (busy) {
            $button.addClass('is-busy');
            $button.attr('aria-busy', 'true');
        } else {
            $button.removeClass('is-busy');
            $button.removeAttr('aria-busy');
        }
    }

    function getUploadFeedbackElement($button) {
        if (!$button || !$button.length) {
            return $();
        }

        const $container = $button.closest('.sidebar-jlg-custom-icon-upload');
        if ($container.length) {
            return $container.find('.sidebar-jlg-upload-feedback');
        }

        return $();
    }

    function showUploadMessage($button, message, isError = false) {
        const $feedback = getUploadFeedbackElement($button);

        if (!$feedback.length) {
            if (message) {
                logDebug('SVG upload message', message);
            }
            return;
        }

        const text = message || '';
        $feedback.text(text);
        $feedback.toggleClass('is-error', !!isError);
    }

    function speakUploadMessage(message) {
        if (!message) {
            return;
        }

        if (window.wp && wp.a11y && typeof wp.a11y.speak === 'function') {
            wp.a11y.speak(message);
        }
    }

    function handleUploadFailure($button, message) {
        const fallback = message || getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.');
        showUploadMessage($button, fallback, true);
        speakUploadMessage(fallback);
    }

    function parseAjaxError(jqXHR) {
        if (jqXHR && jqXHR.responseJSON) {
            const response = jqXHR.responseJSON;
            if (response.data && typeof response.data === 'string') {
                return response.data;
            }
            if (response.message && typeof response.message === 'string') {
                return response.message;
            }
        }

        if (jqXHR && typeof jqXHR.responseText === 'string' && jqXHR.responseText !== '') {
            return jqXHR.responseText;
        }

        return getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.');
    }

    function isSvgFileType(fileName, mimeType) {
        const normalizedMime = typeof mimeType === 'string' ? mimeType.toLowerCase() : '';
        if (normalizedMime === 'image/svg+xml') {
            return true;
        }

        if (typeof fileName === 'string') {
            return /\.svg(?:\?.*)?$/i.test(fileName.trim());
        }

        return false;
    }

    function uploadSvgBlob($button, blob, suggestedName) {
        if (!blob) {
            handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
            return false;
        }

        if (!iconUploadAction || !ajaxUrl) {
            handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
            return false;
        }

        const fileName = typeof suggestedName === 'string' && suggestedName.trim() !== '' ? suggestedName.trim() : 'icon.svg';

        if (!isSvgFileType(fileName, blob.type)) {
            handleUploadFailure($button, getI18nString('iconUploadErrorMime', 'Seuls les fichiers SVG sont acceptés.'));
            return false;
        }

        if (iconUploadMaxSize > 0 && blob.size > iconUploadMaxSize) {
            const sizeMessage = getI18nString('iconUploadErrorSize', 'Le fichier dépasse la taille maximale autorisée de %d Ko.')
                .replace('%d', Math.ceil(iconUploadMaxSize / 1024));
            handleUploadFailure($button, sizeMessage);
            return false;
        }

        const formData = new FormData();
        formData.append('action', iconUploadAction);
        formData.append('nonce', ajaxNonce);
        formData.append('icon_file', blob, fileName);

        setUploadButtonState($button, true);
        showUploadMessage($button, getI18nString('iconUploadInProgress', 'Téléversement du SVG en cours…'), false);

        const jqxhr = $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
        });

        jqxhr.done(response => {
            if (!response || typeof response !== 'object') {
                handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
                return;
            }

            if (!response.success) {
                const serverMessage = response.data && typeof response.data === 'string'
                    ? response.data
                    : getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.');
                handleUploadFailure($button, serverMessage);
                return;
            }

            const data = response.data || {};

            if (Array.isArray(data.icon_manifest)) {
                sidebarJLG.icons_manifest = data.icon_manifest;
                rebuildIconLookups(sidebarJLG.icons_manifest);
                if (modal.is(':visible')) {
                    populateIconGrid();
                }
            }

            if (typeof data.icon_key === 'string' && typeof data.icon_markup === 'string') {
                iconCache[data.icon_key] = data.icon_markup;
            }

            const successMessage = typeof data.message === 'string' && data.message !== ''
                ? data.message
                : getI18nString('iconUploadSuccess', 'Icône SVG ajoutée.');

            showUploadMessage($button, successMessage, false);
            speakUploadMessage(successMessage);
            logDebug('Custom SVG uploaded.', data);
        });

        jqxhr.fail(jqXHR => {
            const message = parseAjaxError(jqXHR);
            handleUploadFailure($button, message);
        });

        jqxhr.always(() => {
            setUploadButtonState($button, false);
        });

        return jqxhr;
    }

    function triggerHiddenUpload($button) {
        hiddenSvgUploadInput.data('originButton', $button);
        hiddenSvgUploadInput.trigger('click');
    }

    function openUploadMediaFrame($button) {
        if (!window.wp || !wp.media || typeof wp.media !== 'function') {
            triggerHiddenUpload($button);
            return;
        }

        if (!customIconUploadFrame) {
            customIconUploadFrame = wp.media({
                title: getI18nString('iconUploadMediaTitle', 'Sélectionner un fichier SVG'),
                button: { text: getI18nString('iconUploadMediaButton', 'Utiliser ce SVG') },
                library: { type: 'image/svg+xml' },
                multiple: false
            });
        }

        customIconUploadFrame.off('select');
        customIconUploadFrame.on('select', function() {
            const selection = customIconUploadFrame.state().get('selection');
            const attachment = selection ? selection.first() : null;

            if (!attachment || typeof attachment.toJSON !== 'function') {
                handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
                return;
            }

            const details = attachment.toJSON();
            if (!details || !details.url) {
                handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
                return;
            }

            if (typeof window.fetch !== 'function') {
                triggerHiddenUpload($button);
                return;
            }

            showUploadMessage($button, getI18nString('iconUploadPreparing', 'Préparation du fichier…'), false);
            setUploadButtonState($button, true);

            fetch(details.url, { credentials: 'same-origin' })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('network_error');
                    }
                    return response.blob();
                })
                .then(blob => {
                    setUploadButtonState($button, false);
                    uploadSvgBlob($button, blob, details.filename || 'icon.svg');
                })
                .catch(() => {
                    setUploadButtonState($button, false);
                    handleUploadFailure($button, getI18nString('iconUploadErrorFetch', 'Impossible de récupérer le fichier depuis la médiathèque.'));
                });
        });

        customIconUploadFrame.open();
    }

    function startSvgUploadFlow($button) {
        if (!$button || !$button.length) {
            return;
        }

        if (!iconUploadAction || !ajaxUrl) {
            handleUploadFailure($button, getI18nString('iconUploadErrorGeneric', 'Le téléversement du SVG a échoué.'));
            return;
        }

        openUploadMediaFrame($button);
    }

    function sanitizeIconKey(iconName) {
        if (typeof iconName !== 'string') {
            return '';
        }

        return iconName.toLowerCase().replace(/[^a-z0-9_\-]/g, '');
    }

    function resolveIconKey(rawKey) {
        if (typeof rawKey !== 'string') {
            return '';
        }

        const trimmed = rawKey.trim();

        if (trimmed === '') {
            return '';
        }

        return iconKeyLookup[trimmed] || iconKeyLookup[sanitizeIconKey(trimmed)] || '';
    }

    function getIconEntry(rawKey) {
        const resolved = resolveIconKey(rawKey);

        if (!resolved) {
            return null;
        }

        return iconEntryByExactKey[resolved] || null;
    }

    function ensureIconsFetched(rawKeys = []) {
        const normalizedKeys = Array.from(new Set(rawKeys.map(resolveIconKey).filter(key => key && !iconCache[key])));

        if (normalizedKeys.length === 0 || !ajaxUrl || !iconFetchAction) {
            return Promise.resolve(iconCache);
        }

        const requestId = normalizedKeys.slice().sort().join(',');

        if (pendingIconRequests[requestId]) {
            return pendingIconRequests[requestId];
        }

        const payload = {
            action: iconFetchAction,
            nonce: ajaxNonce,
            icons: normalizedKeys
        };

        const request = $.post(ajaxUrl, payload)
            .then(response => {
                if (!response || !response.success || typeof response.data !== 'object') {
                    throw new Error('Invalid icon response');
                }

                Object.keys(response.data).forEach(iconKey => {
                    const markup = response.data[iconKey];
                    if (typeof markup === 'string') {
                        iconCache[iconKey] = markup;
                    }
                });

                return response.data;
            })
            .catch(error => {
                logDebug('Icon fetch failed.', { keys: normalizedKeys, error });
                return {};
            })
            .finally(() => {
                delete pendingIconRequests[requestId];
            });

        pendingIconRequests[requestId] = request;
        return request;
    }

    let iconPreviewRequestCounter = 0;

    function populateIconGrid() {
        const grid = $('#icon-grid');
        grid.empty();

        const keysToFetch = [];

        iconManifest.forEach(icon => {
            if (!icon || typeof icon.key !== 'string' || icon.key === '') {
                return;
            }

            const iconName = icon.key;
            const labelSource = typeof icon.label === 'string' && icon.label.trim() !== '' ? icon.label : iconName;
            const labelText = labelSource;

            const $button = $('<button>', {
                type: 'button',
                title: iconName
            });

            $button.attr('data-icon-name', iconName);
            $button.attr('data-icon-search', (iconName + ' ' + labelText).toLowerCase());

            const $preview = $('<span>', {
                class: 'icon-preview',
                'aria-hidden': 'true'
            });
            const $label = $('<span>', {
                class: 'icon-label'
            }).text(labelText);

            $button.append($preview);
            $button.append($label);
            grid.append($button);

            if (iconCache[iconName]) {
                $preview.html(iconCache[iconName]);
            } else {
                keysToFetch.push(iconName);
            }
        });

        if (keysToFetch.length > 0) {
            ensureIconsFetched(keysToFetch).then(() => {
                grid.find('button').each(function() {
                    const iconName = $(this).attr('data-icon-name');
                    const markup = iconCache[iconName];
                    if (markup) {
                        $(this).find('.icon-preview').html(markup);
                    }
                });
            });
        }
    }

    function updateIconPreview(input, $preview) {
        const iconValue = $(input).val();
        if (!iconValue) {
            $preview.empty();
            return;
        }

        const iconType = $(input).closest('.menu-item-box').find('.menu-item-icon-type').val();
        if (iconType === 'svg_url') {
            renderSvgUrlPreview(iconValue, $preview);
            return;
        }

        const entry = getIconEntry(iconValue);

        if (!entry) {
            $preview.empty();
            return;
        }

        const resolvedKey = entry.key;
        const requestId = ++iconPreviewRequestCounter;
        $preview.data('iconRequestId', requestId);

        if (iconCache[resolvedKey]) {
            $preview.html(iconCache[resolvedKey]);
            return;
        }

        ensureIconsFetched([resolvedKey]).then(() => {
            if ($preview.data('iconRequestId') !== requestId) {
                return;
            }

            const markup = iconCache[resolvedKey];
            if (markup) {
                $preview.html(markup);
            } else {
                $preview.empty();
            }
        });
    }

    $('body').on('click', '.choose-icon', function() {
        openIconLibrary($(this).siblings('.icon-input'), $(this).siblings('.icon-preview'));
    });
    $('body').on('click', '.choose-svg', function() {
        openMediaLibrary($(this).siblings('.icon-input'), $(this).siblings('.icon-preview'));
    });

    $('.sidebar-jlg-upload-svg').on('click', function(e) {
        e.preventDefault();
        startSvgUploadFlow($(this));
    });

    modal.on('click', '.modal-close, .modal-backdrop', () => modal.hide());
    $('#icon-grid').on('click', 'button', function() {
        const iconName = $(this).attr('data-icon-name');
        currentIconInput.val(iconName).trigger('change');
        modal.hide();
    });
    $('#icon-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('#icon-grid button').each(function() {
            const haystack = ($(this).attr('data-icon-search') || '').toLowerCase();
            $(this).toggle(haystack.includes(searchTerm));
        });
    });

    // ===========================================
    // CORRECTIF POUR LA SAUVEGARDE DES DONNÉES
    // ===========================================
    
    // Fonction améliorée de création de builder
    function createBuilder(config) {
        const container = $(`#${config.containerId}`);
        const template = wp.template(config.templateId);
        const items = options[config.dataKey] || [];

        function refreshItemTitle($itemBox) {
            if (!$itemBox || !$itemBox.length) {
                return;
            }

            let fallbackTitle = $itemBox.data('fallbackTitle');
            if (typeof fallbackTitle !== 'string') {
                fallbackTitle = '';
            } else {
                fallbackTitle = fallbackTitle.trim();
            }

            if (fallbackTitle === '') {
                fallbackTitle = (config.newTitle || '').trim();
                $itemBox.data('fallbackTitle', fallbackTitle);
            }

            const $labelField = $itemBox.find('.item-label');
            let labelValue = '';

            if ($labelField.length) {
                const rawLabel = $labelField.val();
                if (typeof rawLabel === 'string') {
                    labelValue = rawLabel.trim();
                } else if (Array.isArray(rawLabel)) {
                    labelValue = rawLabel.join(' ').trim();
                }
            }

            $itemBox.find('.item-title').text(labelValue || fallbackTitle);
        }

        // Fonction critique : réindexation correcte des champs
        function reindexFields() {
            logDebug(`Réindexation de ${config.dataKey}`);

            container.children('.menu-item-box').each(function(newIndex) {
                $(this).find('input, select, textarea').each(function() {
                    const $field = $(this);
                    const oldName = $field.attr('name');
                    
                    if (oldName) {
                        // Pattern plus robuste pour la réindexation
                        let newName = oldName;
                        
                        // Pour menu_items
                        if (config.dataKey === 'menu_items') {
                            newName = oldName.replace(
                                /\[menu_items\]\[\d+\]/g,
                                `[menu_items][${newIndex}]`
                            );
                        }
                        // Pour social_icons
                        else if (config.dataKey === 'social_icons') {
                            newName = oldName.replace(
                                /\[social_icons\]\[\d+\]/g,
                                `[social_icons][${newIndex}]`
                            );
                        }
                        
                        if (oldName !== newName) {
                            $field.attr('name', newName);
                            logDebug(`Champ réindexé : ${oldName} -> ${newName}`);
                        }
                    }
                });
            });
            
            logDebug(`Réindexation terminée : ${container.children('.menu-item-box').length} éléments`);
        }

        // Initialisation du sortable avec réindexation après tri
        container.sortable({ 
            handle: '.menu-item-handle', 
            placeholder: 'menu-item-placeholder',
            update: function(event, ui) {
                logDebug('Ordre modifié par drag & drop');
                reindexFields();
            }
        });

        // Charger les éléments existants
        items.forEach((item, index) => {
            item.index = index;
            const $newItem = $(template(item));
            container.append($newItem);
            if (config.onAppend) {
                config.onAppend($newItem, item);
            }
            refreshItemTitle($newItem);
        });

        // Gestionnaire d'ajout avec réindexation
        $(`#${config.addButtonId}`).on('click', function() {
            const newIndex = container.children('.menu-item-box').length;
            const newItem = config.newItem(newIndex);
            newItem.index = newIndex;

            const $newElement = $(template(newItem));
            container.append($newElement);

            if (config.onAppend) {
                config.onAppend($newElement, newItem);
            }

            refreshItemTitle($newElement);

            // Réindexation après ajout
            setTimeout(function() {
                reindexFields();
                logDebug(`Nouvel élément ajouté à ${config.dataKey}`);
            }, 100);
        });

        // Gestionnaire de suppression avec réindexation
        container.on('click', `.${config.deleteButtonClass}`, function() {
            const $box = $(this).closest('.menu-item-box');
            const label = $box.find('.item-label').val() || $box.find('.item-title').text();

            if (confirm(`Supprimer "${label}" ?`)) {
                $box.fadeOut(200, function() {
                    $(this).remove();
                    reindexFields();
                    logDebug(`Élément supprimé de ${config.dataKey}`);
                });
            }
        });

        // Mise à jour du titre en temps réel
        container.on('input', '.item-label', function() {
            const $itemBox = $(this).closest('.menu-item-box');
            refreshItemTitle($itemBox);
        });
        
        // Gestion des changements d'icônes
        container.on('change', '.icon-input', function() {
            updateIconPreview(this, $(this).siblings('.icon-preview'));
        });

        container.on('change', '.menu-item-icon-type', function() {
            updateIconField($(this).closest('.menu-item-box'));
        });
        
        // Réindexation initiale
        reindexFields();
    }

    // Fonction pour mettre à jour le champ icône
    function updateIconField($itemBox) {
        const type = $itemBox.find('.menu-item-icon-type').val();
        const iconWrapper = $itemBox.find('.menu-item-icon-wrapper');
        const index = $itemBox.index();
        const dataKey = $itemBox.parent().attr('id') === 'menu-items-container' ? 'menu_items' : 'social_icons';

        const existingInput = iconWrapper.find('.icon-input');
        const previousValueRaw = existingInput.length ? existingInput.val() : '';
        const previousValue = typeof previousValueRaw === 'string' ? previousValueRaw : '';

        iconWrapper.empty();

        if (type === 'svg_url') {
            iconWrapper.html(`
                <input type="text" class="widefat icon-input"
                       name="sidebar_jlg_settings[${dataKey}][${index}][icon]"
                       placeholder="https://example.com/icon.svg">
                <button type="button" class="button choose-svg">Choisir depuis la médiathèque</button>
                <span class="icon-preview"></span>
                <span class="icon-preview-status" role="status" aria-live="polite"></span>
                <p class="description icon-url-hint"></p>
            `);

            const restrictions = getSvgUrlRestrictions();
            const description = getRestrictionDescription(restrictions);
            const hintElement = iconWrapper.find('.icon-url-hint');
            if (hintElement.length) {
                if (description) {
                    hintElement.text(`Les SVG personnalisés doivent provenir de : ${description}`);
                } else {
                    hintElement.text('Les SVG personnalisés doivent provenir du dossier de téléversement autorisé.');
                }
            }
        } else {
            iconWrapper.html(`
                <input type="text" class="widefat icon-input"
                       name="sidebar_jlg_settings[${dataKey}][${index}][icon]"
                       placeholder="Nom de l'icône">
                <button type="button" class="button choose-icon">Parcourir les icônes</button>
                <span class="icon-preview"></span>
            `);
        }

        const newInput = iconWrapper.find('.icon-input');
        if (newInput.length) {
            if (previousValue !== '') {
                newInput.val(previousValue).trigger('change');
            } else if (type === 'svg_url') {
                renderSvgUrlPreview('', iconWrapper.find('.icon-preview'));
            } else {
                newInput.removeClass('icon-input-invalid');
                newInput.removeAttr('aria-invalid');
            }
        }
    }

    // Fonction pour mettre à jour le champ valeur selon le type
    function getCacheBucket(action) {
        return action === 'jlg_get_posts' ? ajaxCache.posts : ajaxCache.categories;
    }

    function clearCacheBucket(action) {
        const bucket = getCacheBucket(action);
        Object.keys(bucket).forEach(key => delete bucket[key]);
    }

    function buildCacheKey(action, include, page, perPage, postType, search) {
        const hasInclude = include !== undefined && include !== null && include !== '';
        const includeKey = hasInclude ? (Array.isArray(include) ? include.join(',') : String(include)) : '';
        const normalizedPostType = postType ? String(postType) : '';
        const normalizedSearch = typeof search === 'string' ? search : '';
        return [action, includeKey, page, perPage, normalizedPostType, normalizedSearch].join('|');
    }

    function requestAjaxData(action, requestData) {
        const bucket = getCacheBucket(action);
        requestData.search = typeof requestData.search === 'string' ? requestData.search : '';
        const key = buildCacheKey(
            action,
            requestData.include,
            requestData.page,
            requestData.posts_per_page,
            requestData.post_type,
            requestData.search
        );

        if (bucket[key]) {
            return bucket[key];
        }

        const jqxhr = $.post(sidebarJLG.ajax_url, requestData);

        jqxhr.fail(function() {
            delete bucket[key];
        });

        bucket[key] = jqxhr;
        return jqxhr;
    }

    function populateSelectOptions(
        $selectElement,
        type,
        response,
        normalizedValue,
        createCurrentOption,
        action,
        statusElement,
        previousOptions = [],
        previousValue = ''
    ) {
        if (!$selectElement.closest('body').length) {
            return;
        }

        if (statusElement) {
            statusElement.empty();
        }

        if (response.success) {
            const idKey = 'id';
            const titleKey = type === 'category' ? 'name' : 'title';
            const optionsArray = Array.isArray(response.data) ? response.data : [];
            const hasCurrentInResponse = normalizedValue && optionsArray.some(opt => String(opt[idKey]) === normalizedValue);

            $selectElement.empty();

            if (!hasCurrentInResponse) {
                const currentOption = createCurrentOption();
                if (currentOption) {
                    $selectElement.append(currentOption);
                }
            }

            optionsArray.forEach(opt => {
                const optionElement = document.createElement('option');
                optionElement.value = opt[idKey];
                optionElement.textContent = opt[titleKey] || '';
                if (normalizedValue && String(opt[idKey]) === normalizedValue) {
                    optionElement.selected = true;
                }
                $selectElement.append(optionElement);
            });

            if (statusElement && optionsArray.length === 0) {
                statusElement.text('Aucun résultat');
            }

            if (!$selectElement.children().length) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Aucun résultat';
                $selectElement.append(emptyOption);
            }
        } else {
            logDebug(`Failed to fetch data for ${action}.`);
            const hasPreviousOptions = Array.isArray(previousOptions) && previousOptions.length > 0;

            $selectElement.empty();

            if (hasPreviousOptions) {
                previousOptions.forEach(optionClone => {
                    if (optionClone && optionClone.length) {
                        $selectElement.append(optionClone);
                    }
                });

                if (previousValue !== undefined && previousValue !== null && previousValue !== '') {
                    $selectElement.val(previousValue);
                }
            } else {
                const currentOption = createCurrentOption();
                if (currentOption) {
                    $selectElement.append(currentOption);
                }

                const errorOption = document.createElement('option');
                errorOption.value = '';
                errorOption.textContent = 'Erreur de chargement';
                errorOption.disabled = true;
                $selectElement.append(errorOption);
            }

            if (statusElement) {
                statusElement.text('Erreur de chargement. Vérifiez votre connexion puis réessayez.');
            }
        }

        $selectElement.prop('disabled', false);
    }

    function handleAjaxFailure(
        $selectElement,
        createCurrentOption,
        action,
        statusElement,
        previousOptions = [],
        previousValue = ''
    ) {
        logDebug(`AJAX request failed for ${action}.`);

        if (!$selectElement.closest('body').length) {
            return;
        }

        const hasPreviousOptions = Array.isArray(previousOptions) && previousOptions.length > 0;

        $selectElement.empty();

        if (hasPreviousOptions) {
            previousOptions.forEach(optionClone => {
                if (optionClone && optionClone.length) {
                    $selectElement.append(optionClone);
                }
            });

            if (previousValue !== undefined && previousValue !== null && previousValue !== '') {
                $selectElement.val(previousValue);
            }
        } else {
            const currentOption = createCurrentOption();
            if (currentOption) {
                $selectElement.append(currentOption);
            }

            const errorOption = document.createElement('option');
            errorOption.value = '';
            errorOption.textContent = 'Erreur de chargement';
            errorOption.disabled = true;
            $selectElement.append(errorOption);
        }

        if (statusElement) {
            statusElement.text('Erreur de chargement. Vérifiez votre connexion puis réessayez.');
        }

        $selectElement.prop('disabled', false);
    }

    function updateValueField($itemBox, itemData) {
        const type = $itemBox.find('.menu-item-type').val();
        const valueWrapper = $itemBox.find('.menu-item-value-wrapper');
        const index = $itemBox.index();
        const value = itemData.value || '';

        let fieldContainer = valueWrapper.find('.menu-item-field-container');
        if (!fieldContainer.length) {
            fieldContainer = $('<div>', { class: 'menu-item-field-container' });
            const existingChildren = valueWrapper.children().not('.menu-item-search-container');
            if (existingChildren.length) {
                fieldContainer.append(existingChildren);
            }
            valueWrapper.prepend(fieldContainer);
        }

        let searchContainer = valueWrapper.find('.menu-item-search-container');
        if (!searchContainer.length) {
            searchContainer = $('<div>', { class: 'menu-item-search-container' });
            const fallbackInput = $('<input>', {
                type: 'search',
                class: 'menu-item-search-input',
                placeholder: 'Rechercher…',
                'aria-label': 'Rechercher un élément'
            });
            const fallbackStatus = $('<div>', {
                class: 'menu-item-search-status',
                'aria-live': 'polite'
            });
            searchContainer.append(fallbackInput, fallbackStatus).css('display', 'none');
            valueWrapper.append(searchContainer);
        }

        const searchInput = searchContainer.find('.menu-item-search-input');
        const statusElement = searchContainer.find('.menu-item-search-status');
        searchInput.off('.sidebarSearch');

        fieldContainer.empty();

        if (type === 'custom') {
            fieldContainer.html(`
                <p><label>URL</label>
                <input type="text" class="widefat"
                       name="sidebar_jlg_settings[menu_items][${index}][value]"
                       value="${value}" placeholder="https://..."></p>
            `);
            searchInput.val('');
            statusElement.empty();
            searchContainer.css('display', 'none');
        } else if (type === 'post' || type === 'page' || type === 'category') {
            const isContentType = type === 'post' || type === 'page';
            const action = isContentType ? 'jlg_get_posts' : 'jlg_get_categories';
            const name = `sidebar_jlg_settings[menu_items][${index}][value]`;
            const labelMap = {
                post: 'Article',
                page: 'Page',
                category: 'Catégorie'
            };
            const labelText = labelMap[type] || 'Élément';
            const $label = $('<label>').text(labelText);
            const $selectElement = $('<select>', {
                class: 'widefat',
                name: name
            });

            const normalizedValue = value !== null && value !== undefined ? String(value) : '';
            const currentLabel = itemData.current_label || itemData.value_label || itemData.label || '';
            const createCurrentOption = () => {
                if (!normalizedValue) {
                    return null;
                }

                const option = document.createElement('option');
                option.value = normalizedValue;
                option.textContent = currentLabel || `Élément actuel (ID: ${normalizedValue})`;
                option.selected = true;
                option.dataset.currentOption = '1';
                return option;
            };

            const initialCurrentOption = createCurrentOption();

            if (initialCurrentOption) {
                $selectElement.append(initialCurrentOption);
            }

            const loadingOption = document.createElement('option');
            loadingOption.value = '';
            loadingOption.textContent = 'Chargement...';
            loadingOption.disabled = true;
            loadingOption.dataset.loadingOption = '1';
            if (!initialCurrentOption) {
                loadingOption.selected = true;
            }
            $selectElement.append(loadingOption);

            const $paragraph = $('<p>');
            $paragraph.append($label);
            $paragraph.append($selectElement);
            fieldContainer.append($paragraph);

            searchContainer.css('display', 'flex');
            statusElement.empty();

            const postsPerPage = 20;

            const buildRequestData = (searchTerm, page) => {
                const normalizedSearchTerm = (searchTerm || '').trim();
                const requestData = {
                    action: action,
                    nonce: sidebarJLG.nonce,
                    page: page,
                    posts_per_page: postsPerPage,
                    search: normalizedSearchTerm
                };

                if (normalizedValue) {
                    requestData.include = normalizedValue;
                }

                if (type === 'page' || type === 'post') {
                    requestData.post_type = type;
                }

                return requestData;
            };

            const fetchOptions = (searchTerm, page = 1) => {
                const requestData = buildRequestData(searchTerm, page);
                statusElement.text('Chargement...');
                $selectElement.prop('disabled', true);
                $selectElement.data('current-search', requestData.search);
                searchInput.data('current-page', page);

                const previousOptions = $selectElement.children().not('[data-loading-option="1"]').map(function() {
                    return $(this).clone();
                }).get();
                const previousValue = $selectElement.val();

                requestAjaxData(action, requestData)
                    .done(function(response) {
                        populateSelectOptions(
                            $selectElement,
                            type,
                            response,
                            normalizedValue,
                            createCurrentOption,
                            action,
                            statusElement,
                            previousOptions,
                            previousValue
                        );
                    })
                    .fail(function() {
                        handleAjaxFailure(
                            $selectElement,
                            createCurrentOption,
                            action,
                            statusElement,
                            previousOptions,
                            previousValue
                        );
                    })
                    .always(function() {
                        if (statusElement.text() === 'Chargement...') {
                            statusElement.empty();
                        }
                    });
            };

            const triggerSearch = () => {
                const term = (searchInput.val() || '').trim();
                const lastSearch = searchInput.data('last-search') || '';
                if (term === lastSearch) {
                    return;
                }
                searchInput.data('last-search', term);
                clearCacheBucket(action);
                fetchOptions(term, 1);
            };

            const initialSearchTerm = (searchInput.val() || '').trim();
            searchInput.data('last-search', initialSearchTerm);
            fetchOptions(initialSearchTerm, 1);

            const debouncedSearchHandler = debounce(triggerSearch, searchDebounceDelay);
            searchInput.on('input.sidebarSearch search.sidebarSearch', debouncedSearchHandler);
        } else if (type === 'nav_menu') {
            const menuSelectLabel = getI18nString('navMenuFieldLabel', 'Menu WordPress');
            const menuPlaceholder = getI18nString('navMenuSelectPlaceholder', 'Sélectionnez un menu…');
            const depthLabel = getI18nString('navMenuDepthLabel', 'Profondeur maximale');
            const depthHelp = getI18nString('navMenuDepthHelp', '0 = illimité');
            const filterLabel = getI18nString('navMenuFilterLabel', 'Filtrage');
            const filterAllLabel = getI18nString('navMenuFilterAll', 'Tous les éléments');
            const filterTopLevelLabel = getI18nString('navMenuFilterTopLevel', 'Uniquement le niveau 1');
            const filterBranchLabel = getI18nString('navMenuFilterBranch', 'Branche de la page courante');

            const allowedFilters = ['all', 'top-level', 'current-branch'];
            const normalizedMenuValue = value !== null && value !== undefined ? String(value) : '';
            let depthValue = parseInt(itemData.nav_menu_max_depth, 10);
            if (!Number.isFinite(depthValue) || depthValue < 0) {
                depthValue = 0;
            }
            let filterValue = typeof itemData.nav_menu_filter === 'string' ? itemData.nav_menu_filter : '';
            if (!allowedFilters.includes(filterValue)) {
                filterValue = 'all';
            }

            fieldContainer.html(`
                <p>
                    <label>${menuSelectLabel}</label>
                    <select class="widefat menu-item-nav-select" name="sidebar_jlg_settings[menu_items][${index}][value]">
                        <option value="">${menuPlaceholder}</option>
                    </select>
                </p>
                <p>
                    <label>${depthLabel}</label>
                    <input type="number" class="small-text menu-item-nav-depth" min="0" step="1"
                        name="sidebar_jlg_settings[menu_items][${index}][nav_menu_max_depth]"
                        value="${depthValue}">
                    <span class="description">${depthHelp}</span>
                </p>
                <p>
                    <label>${filterLabel}</label>
                    <select class="widefat menu-item-nav-filter" name="sidebar_jlg_settings[menu_items][${index}][nav_menu_filter]">
                        <option value="all"${filterValue === 'all' ? ' selected' : ''}>${filterAllLabel}</option>
                        <option value="top-level"${filterValue === 'top-level' ? ' selected' : ''}>${filterTopLevelLabel}</option>
                        <option value="current-branch"${filterValue === 'current-branch' ? ' selected' : ''}>${filterBranchLabel}</option>
                    </select>
                </p>
            `);

            searchInput.val('');
            statusElement.empty();
            searchContainer.css('display', 'none');

            const $menuSelect = fieldContainer.find('.menu-item-nav-select');
            const $depthInput = fieldContainer.find('.menu-item-nav-depth');

            const updateTitle = (menuName) => {
                const fallback = menuName || getI18nString('menuItemDefaultTitle', 'Nouvel élément');
                $itemBox.data('fallbackTitle', fallback);
                const $labelField = $itemBox.find('.item-label');
                const labelValue = $labelField.length && typeof $labelField.val() === 'string'
                    ? $labelField.val().trim()
                    : '';
                $itemBox.find('.item-title').text(labelValue || fallback);
            };

            updateTitle('');

            fetchNavMenus()
                .then((menus) => {
                    const menuItems = Array.isArray(menus) ? menus : [];
                    $menuSelect.empty();
                    const placeholderOption = document.createElement('option');
                    placeholderOption.value = '';
                    placeholderOption.textContent = menuPlaceholder;
                    $menuSelect.append(placeholderOption);

                    let selectedName = '';
                    menuItems.forEach((menu) => {
                        if (!menu || typeof menu.id === 'undefined') {
                            return;
                        }

                        const option = document.createElement('option');
                        option.value = String(menu.id);
                        option.textContent = menu.name || menu.slug || option.value;

                        if (normalizedMenuValue !== '' && option.value === normalizedMenuValue) {
                            option.selected = true;
                            selectedName = option.textContent;
                        }

                        $menuSelect.append(option);
                    });

                    updateTitle(selectedName);
                })
                .catch(() => {
                    updateTitle('');
                });

            $menuSelect.on('change', function() {
                const option = this.options[this.selectedIndex];
                const menuName = option && option.value ? option.textContent : '';
                updateTitle(menuName);
            });

            $depthInput.on('input', function() {
                const parsed = parseInt(this.value, 10);
                if (!Number.isFinite(parsed) || parsed < 0) {
                    this.value = '0';
                }
            });
        } else {
            fieldContainer.empty();
            searchInput.val('');
            statusElement.empty();
            searchContainer.css('display', 'none');
        }
    }

    // --- Builder pour les éléments de menu ---
    createBuilder({
        containerId: 'menu-items-container', 
        templateId: 'menu-item',
        dataKey: 'menu_items',
        addButtonId: 'add-menu-item', 
        deleteButtonClass: 'delete-menu-item',
        newTitle: getI18nString('menuItemDefaultTitle', 'Nouvel élément'),
        newItem: (index) => ({
            index,
            label: '',
            type: 'custom',
            value: '',
            icon_type: 'svg_inline',
            icon: '',
            nav_menu_max_depth: 0,
            nav_menu_filter: 'all'
        }),
        onAppend: ($itemBox, itemData) => {
            updateValueField($itemBox, itemData);
            updateIconField($itemBox);
            $itemBox.find('.icon-input').val(itemData.icon).trigger('change');
        }
    });
    
    // Gestion du changement de type de menu
    $('#menu-items-container').on('change', '.menu-item-type', function() {
        const $itemBox = $(this).closest('.menu-item-box');
        const selectedType = $(this).val();
        if (selectedType === 'nav_menu') {
            updateValueField($itemBox, { value: '', nav_menu_max_depth: 0, nav_menu_filter: 'all' });
        } else {
            updateValueField($itemBox, { value: '' });
        }
    });

    // --- Builder pour les icônes sociales ---
    function populateStandardIconsDropdown($select, selectedValue) {
        $select.empty();

        // Ajouter les icônes standard
        const socialIcons = [
            'facebook_white', 'facebook_black',
            'x_white', 'x_black',
            'instagram_white', 'instagram_black',
            'youtube_white', 'youtube_black',
            'linkedin_white', 'linkedin_black',
            'github_white', 'github_black',
            'tiktok_white', 'tiktok_black',
            'pinterest_white', 'pinterest_black',
            'whatsapp_white', 'whatsapp_black',
            'telegram_white', 'telegram_black'
        ];

        socialIcons.forEach(key => {
            const entry = getIconEntry(key);
            if (entry) {
                const labelSource = entry.label || key;
                const capitalizedName = labelSource.charAt(0).toUpperCase() + labelSource.slice(1);
                $select.append($('<option>', {
                    value: entry.key,
                    text: capitalizedName,
                    selected: entry.key === selectedValue
                }));
            }
        });

        // Ajouter les icônes personnalisées
        iconManifest.forEach(icon => {
            if (!icon || !icon.is_custom || typeof icon.key !== 'string') {
                return;
            }

            const customLabel = icon.label || icon.key.replace('custom_', '').replace(/_/g, ' ');
            const formatted = customLabel.charAt(0).toUpperCase() + customLabel.slice(1);
            $select.append($('<option>', {
                value: icon.key,
                text: 'Personnalisé: ' + formatted,
                selected: icon.key === selectedValue
            }));
        });
    }

    createBuilder({
        containerId: 'social-icons-container', 
        templateId: 'social-icon',
        dataKey: 'social_icons',
        addButtonId: 'add-social-icon',
        deleteButtonClass: 'delete-social-icon',
        newTitle: getI18nString('socialIconDefaultTitle', 'Nouvelle icône'),
        newItem: (index) => ({
            index,
            label: '',
            url: '',
            icon: 'facebook_white'
        }),
        onAppend: ($itemBox, itemData) => {
            const $select = $itemBox.find('.social-icon-select');
            populateStandardIconsDropdown($select, itemData.icon);

            const $preview = $itemBox.find('.icon-preview');
            $preview.html(standardIcons[itemData.icon] || '');

            const defaultTitle = getI18nString('socialIconDefaultTitle', 'Nouvelle icône');
            const iconKey = typeof itemData.icon === 'string' ? itemData.icon : '';
            const fallbackTitle = iconKey ? iconKey.split('_')[0] : defaultTitle;
            $itemBox.data('fallbackTitle', (fallbackTitle || defaultTitle).trim());

            $select.on('change', function() {
                const selectedIconKey = $(this).val();
                $preview.html(standardIcons[selectedIconKey] || '');
                const updatedFallback = typeof selectedIconKey === 'string' && selectedIconKey !== ''
                    ? selectedIconKey.split('_')[0]
                    : defaultTitle;
                const normalizedFallback = (updatedFallback || defaultTitle).trim();
                $itemBox.data('fallbackTitle', normalizedFallback);

                const $labelField = $itemBox.find('.item-label');
                const currentLabelValue = $labelField.length && typeof $labelField.val() === 'string'
                    ? $labelField.val().trim()
                    : '';

                $itemBox.find('.item-title').text(currentLabelValue || normalizedFallback);
            });
        }
    });

    // --- Vérification avant soumission du formulaire ---
    $('#sidebar-jlg-form').on('submit', function(e) {
        logDebug('Soumission du formulaire...');
        
        // Compter les éléments
        const menuCount = $('#menu-items-container .menu-item-box').length;
        const socialCount = $('#social-icons-container .menu-item-box').length;
        
        logDebug(`Éléments à sauvegarder : ${menuCount} menus, ${socialCount} réseaux sociaux`);
        
        // Vérifier que les indices sont corrects
        let hasError = false;
        
        $('#menu-items-container input, #menu-items-container select').each(function() {
            const name = $(this).attr('name');
            if (name && !name.match(/\[menu_items\]\[\d+\]/)) {
                console.error('Erreur d\'indexation détectée:', name);
                hasError = true;
            }
        });
        
        if (hasError) {
            if (!confirm('Des erreurs d\'indexation ont été détectées. Voulez-vous continuer ?')) {
                e.preventDefault();
                return false;
            }
        }
    });

    // --- Réinitialisation des réglages ---
    $('#reset-jlg-settings').on('click', function() {
        if (!confirm("Êtes-vous sûr de vouloir réinitialiser tous les réglages ? Cette action est irréversible.")) {
            return;
        }

        logDebug('Reset button clicked.');
        const $this = $(this);
        $this.prop('disabled', true).text('Réinitialisation...');

        $.post(sidebarJLG.ajax_url, {
            action: 'jlg_reset_settings',
            nonce: sidebarJLG.reset_nonce
        })
        .done(function(response) {
            if (response.success) {
                logDebug('Settings reset successfully. Reloading page.');
                alert('Les réglages ont été réinitialisés. La page va maintenant se recharger.');
                location.reload();
            } else {
                logDebug('Failed to reset settings.', response);
                alert('Erreur lors de la réinitialisation.');
                $this.prop('disabled', false).text('Réinitialiser tous les réglages');
            }
        })
        .fail(function() {
            logDebug('AJAX request failed.');
            alert('La requête de réinitialisation a échoué.');
            $this.prop('disabled', false).text('Réinitialiser tous les réglages');
        });
    });

    const $exportButton = $('#export-jlg-settings');
    const $importButton = $('#import-jlg-settings');
    const $importFileInput = $('#import-jlg-settings-file');

    if ($exportButton.length) {
        const exportDefaultText = $exportButton.text();

        $exportButton.on('click', function() {
            if (!ajaxUrl) {
                renderNotice('error', getI18nString('exportError', 'Impossible de générer l’export.'));
                return;
            }

            if (!toolsNonce) {
                renderNotice('error', getI18nString('exportError', 'Impossible de générer l’export.'));
                return;
            }

            const confirmMessage = getI18nString('exportConfirm', 'Voulez-vous exporter les réglages actuels ?');
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            const inProgressText = getI18nString('exportInProgress', 'Export en cours…');
            $exportButton.prop('disabled', true).text(inProgressText);

            $.post(ajaxUrl, {
                action: 'jlg_export_settings',
                nonce: toolsNonce
            })
            .done(function(response) {
                if (response && response.success && response.data) {
                    const data = response.data;
                    const payload = data.payload || {};
                    let jsonString = '';

                    try {
                        jsonString = JSON.stringify(payload, null, 2);
                    } catch (error) {
                        logDebug('Failed to stringify export payload.', error);
                    }

                    if (jsonString) {
                        if (typeof URL === 'undefined' || typeof URL.createObjectURL !== 'function') {
                            const fallbackMessage = getI18nString('exportError', 'Impossible de générer l’export.');
                            renderNotice('error', fallbackMessage);
                            return;
                        }

                        const blob = new Blob([jsonString], { type: 'application/json' });
                        const url = URL.createObjectURL(blob);
                        const link = document.createElement('a');
                        link.href = url;
                        link.download = (typeof data.file_name === 'string' && data.file_name !== '')
                            ? data.file_name
                            : 'sidebar-jlg-settings.json';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                        URL.revokeObjectURL(url);

                        const message = getResponseMessage(response, getI18nString('exportSuccess', 'Export terminé. Le téléchargement va démarrer.'));
                        renderNotice('success', message);
                        return;
                    }
                }

                const errorMessage = getResponseMessage(response, getI18nString('exportError', 'Impossible de générer l’export.'));
                renderNotice('error', errorMessage);
            })
            .fail(function(xhr) {
                const errorMessage = getResponseMessage(xhr ? xhr.responseJSON : null, getI18nString('exportError', 'Impossible de générer l’export.'));
                renderNotice('error', errorMessage);
            })
            .always(function() {
                $exportButton.prop('disabled', false).text(exportDefaultText);
            });
        });
    }

    if ($importButton.length && $importFileInput.length) {
        const importDefaultText = $importButton.text();

        $importButton.on('click', function() {
            if (!ajaxUrl) {
                renderNotice('error', getI18nString('importError', 'L’import des réglages a échoué.'));
                return;
            }

            if (!toolsNonce) {
                renderNotice('error', getI18nString('importError', 'L’import des réglages a échoué.'));
                return;
            }

            const inputElement = $importFileInput.get(0);
            if (!inputElement || !inputElement.files || !inputElement.files.length) {
                renderNotice('error', getI18nString('importMissingFile', 'Veuillez sélectionner un fichier JSON avant de lancer l’import.'));
                return;
            }

            const confirmMessage = getI18nString('importConfirm', 'Importer ces réglages écrasera la configuration actuelle. Continuer ?');
            if (confirmMessage && !confirm(confirmMessage)) {
                return;
            }

            const file = inputElement.files[0];
            if (typeof FormData === 'undefined') {
                renderNotice('error', getI18nString('importError', 'L’import des réglages a échoué.'));
                return;
            }
            const formData = new FormData();
            formData.append('action', 'jlg_import_settings');
            formData.append('nonce', toolsNonce);
            formData.append('settings_file', file);

            $importButton.prop('disabled', true).text(getI18nString('importInProgress', 'Import en cours…'));
            $importFileInput.prop('disabled', true);

            $.ajax({
                url: ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false
            })
            .done(function(response) {
                if (response && response.success) {
                    const message = getResponseMessage(response, getI18nString('importSuccess', 'Réglages importés avec succès. Rechargement de la page…'));
                    renderNotice('success', message);
                    setTimeout(function() {
                        location.reload();
                    }, 1200);
                    return;
                }

                const errorMessage = getResponseMessage(response, getI18nString('importError', 'L’import des réglages a échoué.'));
                renderNotice('error', errorMessage);
            })
            .fail(function(xhr) {
                const errorMessage = getResponseMessage(xhr ? xhr.responseJSON : null, getI18nString('importError', 'L’import des réglages a échoué.'));
                renderNotice('error', errorMessage);
            })
            .always(function() {
                $importButton.prop('disabled', false).text(importDefaultText);
                $importFileInput.prop('disabled', false).val('');
            });
        });
    }

    // --- Bouton de debug (si mode debug activé) ---
    if (typeof window !== 'undefined') {
        window.SidebarJLGAdmin = window.SidebarJLGAdmin || {};
        window.SidebarJLGAdmin.updateIconPreview = updateIconPreview;
        window.SidebarJLGAdmin.renderSvgUrlPreview = renderSvgUrlPreview;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports.updateIconPreview = updateIconPreview;
    }

    if (debugMode) {
        const debugButton = $('<button type="button" class="button" style="margin-left: 20px;">🐛 Debug Info</button>');
        debugButton.on('click', function(e) {
            e.preventDefault();
            
            console.group('📊 Debug Sidebar JLG');
            console.log('Menu items:', $('#menu-items-container .menu-item-box').length);
            console.log('Social icons:', $('#social-icons-container .menu-item-box').length);
            
            $('#menu-items-container .menu-item-box').each(function(i) {
                console.log(`Menu ${i}:`, {
                    label: $(this).find('.item-label').val(),
                    type: $(this).find('.menu-item-type').val(),
                    icon: $(this).find('.icon-input').val()
                });
            });
            
            $('#social-icons-container .menu-item-box').each(function(i) {
                console.log(`Social ${i}:`, {
                    label: $(this).find('.item-label').val(),
                    url: $(this).find('.social-url').val(),
                    icon: $(this).find('.social-icon-select').val()
                });
            });
            
            console.groupEnd();
        });
        
        $('.nav-tab-wrapper').append(debugButton);
    }

    logDebug('Script initialization complete');
});
