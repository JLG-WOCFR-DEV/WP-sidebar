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

jQuery(document).ready(function($) {
    const options = sidebarJLG.options || {};
    const ajaxUrl = sidebarJLG.ajax_url || '';
    const ajaxNonce = sidebarJLG.nonce || '';
    const iconFetchAction = typeof sidebarJLG.icon_fetch_action === 'string' ? sidebarJLG.icon_fetch_action : 'jlg_get_icon_svg';
    const iconManifest = Array.isArray(sidebarJLG.icons_manifest) ? sidebarJLG.icons_manifest : [];
    const debugMode = options.debug_mode == '1';
    const ajaxCache = { posts: {}, categories: {} };
    const searchDebounceDelay = 300;
    const iconCache = {};
    const pendingIconRequests = {};
    const iconEntryByExactKey = {};
    const iconKeyLookup = {};

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

    function logDebug(message, data = '') {
        if (debugMode) {
            console.log(`[Sidebar JLG Debug] ${message}`, data);
        }
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
    $('.nav-tab-wrapper a').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab-wrapper a').removeClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $(this).addClass('nav-tab-active');
        $($(this).attr('href')).addClass('active');
    });

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

    // --- Préréglages de style ---
    $('#style-preset-select').on('change', function() {
        const preset = $(this).val();
        if (preset === 'custom') return;

        const presets = {
            moderne_dark: {
                bg_color: '#1a1d24',
                accent_color: '#0d6efd',
                font_color: '#e0e0e0',
                font_hover_color: '#ffffff'
            }
        };

        if (presets[preset]) {
            const p = presets[preset];
            $('input[name="sidebar_jlg_settings[bg_color]"]').val(p.bg_color).trigger('change');
            $('input[name="sidebar_jlg_settings[accent_color]"]').val(p.accent_color).trigger('change');
            $('input[name="sidebar_jlg_settings[font_color]"]').val(p.font_color).trigger('change');
            $('input[name="sidebar_jlg_settings[font_hover_color]"]').val(p.font_hover_color).trigger('change');
        }
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
            icon: '' 
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
        updateValueField($itemBox, { value: '' });
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