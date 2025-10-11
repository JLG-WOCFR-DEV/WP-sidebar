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

function deepClone(value) {
    if (value === null || typeof value !== 'object') {
        return value;
    }

    try {
        return JSON.parse(JSON.stringify(value));
    } catch (error) {
        if (Array.isArray(value)) {
            return value.map((item) => deepClone(item));
        }

        const clone = {};
        Object.keys(value).forEach((key) => {
            clone[key] = deepClone(value[key]);
        });

        return clone;
    }
}

function sanitizeWidgetType(type) {
    if (typeof type !== 'string') {
        return '';
    }

    return type.replace(/[^a-z0-9_-]/gi, '').toLowerCase();
}

function generateWidgetId(type) {
    const sanitized = sanitizeWidgetType(type) || 'widget';
    const random = Math.floor(Math.random() * 99999).toString(36);

    return `${sanitized}-${Date.now().toString(36)}-${random}`;
}

function buildSectionDefaults(schema) {
    const defaults = {};

    if (!schema || typeof schema !== 'object') {
        return defaults;
    }

    Object.keys(schema).forEach((key) => {
        const definition = schema[key];
        if (!definition || typeof definition !== 'object') {
            return;
        }

        const type = typeof definition.type === 'string' ? definition.type : 'text';
        const fallback = definition.default;

        switch (type) {
            case 'boolean':
                defaults[key] = !!fallback;
                break;
            case 'number':
                defaults[key] = Number.isFinite(Number(fallback)) ? Number(fallback) : 0;
                break;
            case 'dimension': {
                const baseUnit = Array.isArray(definition.units) && definition.units.length
                    ? definition.units[0]
                    : 'px';
                const dimension = (fallback && typeof fallback === 'object') ? fallback : {};
                const value = typeof dimension.value === 'string' || typeof dimension.value === 'number'
                    ? String(dimension.value)
                    : '';
                const unit = typeof dimension.unit === 'string' && dimension.unit !== ''
                    ? dimension.unit
                    : baseUnit;

                defaults[key] = { value, unit };
                break;
            }
            case 'repeater':
                defaults[key] = Array.isArray(fallback) ? deepClone(fallback) : [];
                break;
            default:
                defaults[key] = typeof fallback === 'string' ? fallback : '';
        }
    });

    return defaults;
}

function createWidgetFromSchema(type, schema) {
    const sanitizedType = sanitizeWidgetType(type) || 'widget';
    const label = schema && typeof schema.label === 'string' ? schema.label : sanitizedType;
    const contentDefaults = buildSectionDefaults(schema && schema.content ? schema.content : {});
    const styleDefaults = buildSectionDefaults(schema && schema.style ? schema.style : {});
    const trackingDefaults = buildSectionDefaults(
        schema && schema.tracking && schema.tracking.fields ? schema.tracking.fields : {}
    );

    const events = {};
    if (schema && schema.tracking && schema.tracking.events && typeof schema.tracking.events === 'object') {
        Object.keys(schema.tracking.events).forEach((eventKey) => {
            const eventName = schema.tracking.events[eventKey];
            if (typeof eventName === 'string' && eventName.trim() !== '') {
                const normalized = eventName.trim().replace(/[^a-z0-9_]+/gi, '_').toLowerCase();
                if (normalized !== '') {
                    events[eventKey] = normalized;
                }
            }
        });
    }

    return {
        id: generateWidgetId(sanitizedType),
        type: sanitizedType,
        title: label,
        is_enabled: true,
        content: contentDefaults,
        style: styleDefaults,
        tracking: {
            events,
            ...trackingDefaults,
        },
    };
}

function mergeSectionDefaults(defaultSection, overrideSection, schemaSection) {
    const merged = deepClone(defaultSection || {});

    if (!overrideSection || typeof overrideSection !== 'object') {
        return merged;
    }

    Object.keys(overrideSection).forEach((key) => {
        const value = overrideSection[key];
        const definition = schemaSection && schemaSection[key] ? schemaSection[key] : null;
        const type = definition && typeof definition.type === 'string' ? definition.type : 'text';

        switch (type) {
            case 'boolean':
                merged[key] = !!value;
                break;
            case 'number':
                if (Number.isFinite(Number(value))) {
                    merged[key] = Number(value);
                }
                break;
            case 'dimension':
                if (value && typeof value === 'object') {
                    const defaultDimension = merged[key] || {};
                    const units = Array.isArray(definition && definition.units) && definition.units.length
                        ? definition.units
                        : ['px'];
                    const unitFallback = typeof defaultDimension.unit === 'string' && defaultDimension.unit !== ''
                        ? defaultDimension.unit
                        : units[0];
                    const nextValue = value.value !== undefined ? value.value : defaultDimension.value;
                    const nextUnit = value.unit !== undefined ? value.unit : unitFallback;

                    merged[key] = {
                        value: typeof nextValue === 'string' ? nextValue : String(nextValue || ''),
                        unit: typeof nextUnit === 'string' && nextUnit !== '' ? nextUnit : unitFallback,
                    };
                }
                break;
            case 'repeater':
                if (Array.isArray(value)) {
                    merged[key] = deepClone(value);
                }
                break;
            default:
                if (typeof value === 'string') {
                    merged[key] = value;
                }
        }
    });

    return merged;
}

function normalizeWidgetCollection(rawWidgets, schemas) {
    if (!Array.isArray(rawWidgets)) {
        return [];
    }

    const normalized = [];

    rawWidgets.forEach((widget) => {
        if (!widget || typeof widget !== 'object') {
            return;
        }

        const sanitizedType = sanitizeWidgetType(widget.type);
        if (sanitizedType === '' || !schemas[sanitizedType]) {
            return;
        }

        const schema = schemas[sanitizedType];
        const base = createWidgetFromSchema(sanitizedType, schema);

        const merged = {
            ...base,
            id: typeof widget.id === 'string' && widget.id !== '' ? widget.id : base.id,
            title: typeof widget.title === 'string' && widget.title !== '' ? widget.title : base.title,
            is_enabled: widget.is_enabled !== undefined ? !!widget.is_enabled : base.is_enabled,
        };

        merged.content = mergeSectionDefaults(base.content, widget.content, schema && schema.content ? schema.content : {});
        merged.style = mergeSectionDefaults(base.style, widget.style, schema && schema.style ? schema.style : {});

        const trackingSchema = schema && schema.tracking && schema.tracking.fields ? schema.tracking.fields : {};
        const baseTracking = { events: { ...base.tracking.events } };

        Object.keys(base.tracking).forEach((key) => {
            if (key !== 'events') {
                baseTracking[key] = base.tracking[key];
            }
        });

        const normalizedTracking = mergeSectionDefaults(baseTracking, widget.tracking, trackingSchema);

        if (widget.tracking && typeof widget.tracking === 'object' && widget.tracking.events && typeof widget.tracking.events === 'object') {
            Object.keys(widget.tracking.events).forEach((eventKey) => {
                const eventName = widget.tracking.events[eventKey];
                if (typeof eventName === 'string' && eventName.trim() !== '') {
                    const normalizedEvent = eventName.trim().replace(/[^a-z0-9_]+/gi, '_').toLowerCase();
                    if (normalizedEvent !== '') {
                        normalizedTracking.events[eventKey] = normalizedEvent;
                    }
                }
            });
        }

        merged.tracking = normalizedTracking;

        normalized.push(merged);
    });

    return normalized;
}

function reorderWidgets(list, fromIndex, toIndex) {
    if (!Array.isArray(list)) {
        return [];
    }

    const copy = list.slice();
    const length = copy.length;

    if (
        typeof fromIndex !== 'number' ||
        typeof toIndex !== 'number' ||
        fromIndex < 0 ||
        fromIndex >= length ||
        toIndex < 0 ||
        toIndex >= length
    ) {
        return copy;
    }

    const [item] = copy.splice(fromIndex, 1);
    copy.splice(toIndex, 0, item);

    return copy;
}

function serializeWidgetsForInput(widgets) {
    try {
        return JSON.stringify(Array.isArray(widgets) ? widgets : []);
    } catch (error) {
        return '[]';
    }
}

function escapeHtmlString(value) {
    return String(value == null ? '' : value).replace(/[&<>"']/g, (match) => {
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

function initializeWidgetBuilderInterface({ container, hiddenField, schemas: providedSchemas }) {
    if (!container || typeof container !== 'object') {
        return null;
    }

    const datasetSchemas = container.getAttribute('data-widget-schemas');
    const datasetWidgets = container.getAttribute('data-widget-options');

    let parsedSchemas = {};
    let parsedWidgets = [];

    if (providedSchemas && typeof providedSchemas === 'object') {
        parsedSchemas = { ...providedSchemas };
    }

    if (datasetSchemas) {
        try {
            const decodedSchemas = JSON.parse(datasetSchemas);
            if (decodedSchemas && typeof decodedSchemas === 'object') {
                parsedSchemas = { ...decodedSchemas };
            }
        } catch (error) {
            // Ignore JSON parsing errors and keep fallback schemas.
        }
    }

    if (datasetWidgets) {
        try {
            const decodedWidgets = JSON.parse(datasetWidgets);
            if (Array.isArray(decodedWidgets)) {
                parsedWidgets = decodedWidgets;
            }
        } catch (error) {
            parsedWidgets = [];
        }
    }

    const normalizedSchemas = {};
    Object.keys(parsedSchemas).forEach((key) => {
        const normalizedKey = sanitizeWidgetType(key);
        if (normalizedKey === '') {
            return;
        }

        const schema = parsedSchemas[key];
        if (schema && typeof schema === 'object') {
            normalizedSchemas[normalizedKey] = schema;
        }
    });

    const listElement = container.querySelector('[data-widget-list]');
    const editorElement = container.querySelector('[data-widget-editor]');
    const editorPlaceholder = editorElement ? editorElement.querySelector('[data-widget-editor-empty]') : null;
    const previewContainer = container.querySelector('[data-widget-preview-frame]');
    const typeSelect = container.querySelector('[data-widget-type]');
    const addButton = container.querySelector('[data-widget-add]');

    let widgets = normalizeWidgetCollection(parsedWidgets, normalizedSchemas);
    let selectedId = widgets.length > 0 ? widgets[0].id : '';

    function syncHiddenField() {
        if (hiddenField) {
            hiddenField.value = serializeWidgetsForInput(widgets);
        }
    }

    function ensureSelectionIntegrity() {
        if (!selectedId || !widgets.some((widget) => widget.id === selectedId)) {
            selectedId = widgets.length > 0 ? widgets[0].id : '';
        }
    }

    function renderList() {
        if (!listElement) {
            return;
        }

        ensureSelectionIntegrity();
        const previousScroll = listElement.scrollTop;
        const previouslySelected = selectedId;

        listElement.innerHTML = '';

        widgets.forEach((widget, index) => {
            const schema = normalizedSchemas[widget.type] || {};
            const item = document.createElement('div');
            item.className = 'sidebar-jlg-widget-card';
            item.dataset.index = String(index);
            item.dataset.widgetId = widget.id;
            item.setAttribute('role', 'listitem');

            if (widget.id === selectedId) {
                item.classList.add('is-active');
            }

            const header = document.createElement('div');
            header.className = 'sidebar-jlg-widget-card__header';

            const handle = document.createElement('span');
            handle.className = 'sidebar-jlg-widget-card__handle';
            handle.setAttribute('aria-hidden', 'true');
            handle.textContent = '⋮⋮';

            const selectButton = document.createElement('button');
            selectButton.type = 'button';
            selectButton.className = 'sidebar-jlg-widget-card__select';
            const titleSource = widget.title && widget.title.trim() !== ''
                ? widget.title
                : (schema.label || widget.type);
            selectButton.textContent = titleSource;
            selectButton.addEventListener('click', () => {
                selectedId = widget.id;
                renderAll();
            });

            const toggleLabel = document.createElement('label');
            toggleLabel.className = 'sidebar-jlg-widget-card__toggle';
            const toggleInput = document.createElement('input');
            toggleInput.type = 'checkbox';
            toggleInput.checked = !!widget.is_enabled;
            toggleInput.setAttribute('aria-label', getI18nString('widgetsToggleLabel', 'Activer le widget'));
            toggleInput.addEventListener('change', () => {
                widget.is_enabled = toggleInput.checked;
                syncHiddenField();
                renderPreview();
            });
            toggleLabel.appendChild(toggleInput);
            const toggleText = document.createElement('span');
            toggleText.className = 'sidebar-jlg-widget-card__toggle-text';
            toggleText.textContent = getI18nString('widgetsToggleText', 'Actif');
            toggleLabel.appendChild(toggleText);

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'button-link sidebar-jlg-widget-card__delete';
            removeButton.textContent = getI18nString('widgetsDelete', 'Supprimer');
            removeButton.addEventListener('click', () => {
                const confirmMessage = getI18nString('widgetsDeleteConfirm', 'Supprimer ce widget ?');
                if (window.confirm(confirmMessage)) {
                    widgets = widgets.filter((candidate) => candidate.id !== widget.id);
                    if (selectedId === widget.id) {
                        selectedId = previouslySelected;
                    }
                    syncHiddenField();
                    renderAll();
                }
            });

            header.appendChild(handle);
            header.appendChild(selectButton);
            header.appendChild(toggleLabel);
            header.appendChild(removeButton);

            item.appendChild(header);

            listElement.appendChild(item);
        });

        listElement.scrollTop = previousScroll;
        initSortable();
    }

    function renderPreview() {
        if (!previewContainer) {
            return;
        }

        ensureSelectionIntegrity();
        const activeWidget = widgets.find((widget) => widget.id === selectedId);

        if (!activeWidget) {
            previewContainer.innerHTML = '<p class="description">' + escapeHtmlString(getI18nString('widgetsPreviewEmpty', 'Ajoutez un widget pour afficher un aperçu.')) + '</p>';
            return;
        }

        const schema = normalizedSchemas[activeWidget.type] || {};
        const markup = buildWidgetPreviewMarkup(activeWidget, schema);
        previewContainer.innerHTML = markup;
    }

    function buildWidgetPreviewMarkup(widget, schema) {
        const safeTitle = escapeHtmlString(widget.content && widget.content.title ? widget.content.title : widget.title || schema.label || widget.type);
        const accentColor = widget.style && widget.style.accent_color ? widget.style.accent_color : '#2563eb';
        const background = widget.style && widget.style.background_color ? widget.style.background_color : 'rgba(15,23,42,0.92)';
        const textColor = widget.style && widget.style.text_color ? widget.style.text_color : '#f8fafc';

        switch (widget.type) {
            case 'cta': {
                const description = widget.content && widget.content.message ? widget.content.message : '';
                const buttonLabel = widget.content && widget.content.button_label ? widget.content.button_label : getI18nString('widgetsCtaButton', 'En savoir plus');
                return `
                    <div class="sidebar-jlg-widget-preview sidebar-jlg-widget-preview--cta" style="background:${background};color:${textColor}">
                        <h4>${safeTitle}</h4>
                        ${description ? `<div class="sidebar-jlg-widget-preview__content">${description}</div>` : ''}
                        <div class="sidebar-jlg-widget-preview__actions">
                            <span class="sidebar-jlg-widget-preview__button" style="background:${accentColor}">${escapeHtmlString(buttonLabel)}</span>
                        </div>
                    </div>
                `;
            }
            case 'form': {
                const intro = widget.content && widget.content.intro ? widget.content.intro : '';
                const shortcode = widget.content && widget.content.shortcode ? widget.content.shortcode : '';
                return `
                    <div class="sidebar-jlg-widget-preview sidebar-jlg-widget-preview--form" style="background:${background};color:${textColor}">
                        <h4>${safeTitle}</h4>
                        ${intro ? `<p>${escapeHtmlString(intro)}</p>` : ''}
                        <div class="sidebar-jlg-widget-preview__form">
                            <label><span>${escapeHtmlString(getI18nString('widgetsFormLabel', 'Email'))}</span><input type="email" placeholder="${escapeHtmlString(getI18nString('widgetsFormPlaceholder', 'vous@example.com'))}" /></label>
                            <button type="button" class="button" style="background:${accentColor}">${escapeHtmlString(getI18nString('widgetsFormSubmit', 'Envoyer'))}</button>
                        </div>
                        ${shortcode ? `<code class="sidebar-jlg-widget-preview__shortcode">${escapeHtmlString(shortcode)}</code>` : ''}
                    </div>
                `;
            }
            case 'slider': {
                const items = Array.isArray(widget.content && widget.content.items ? widget.content.items : [])
                    ? widget.content.items.slice(0, 3)
                    : [];
                const slides = items.map((item) => {
                    const heading = escapeHtmlString(item && item.heading ? item.heading : getI18nString('widgetsSliderHeading', 'Slide'));
                    const text = escapeHtmlString(item && item.text ? item.text : '');
                    return `<li><strong>${heading}</strong>${text ? `<p>${text}</p>` : ''}</li>`;
                }).join('');

                return `
                    <div class="sidebar-jlg-widget-preview sidebar-jlg-widget-preview--slider" style="background:${background};color:${textColor}">
                        <h4>${safeTitle}</h4>
                        <ul>${slides}</ul>
                        <p class="sidebar-jlg-widget-preview__hint">${escapeHtmlString(getI18nString('widgetsSliderHint', 'Lecture automatique activée'))}</p>
                    </div>
                `;
            }
            case 'woocommerce': {
                const mode = widget.content && widget.content.mode ? widget.content.mode : 'product';
                const ids = widget.content && widget.content.ids ? widget.content.ids : '';
                return `
                    <div class="sidebar-jlg-widget-preview sidebar-jlg-widget-preview--woocommerce" style="background:${background};color:${textColor}">
                        <h4>${safeTitle}</h4>
                        <p>${escapeHtmlString(getI18nString('widgetsWooMode', 'Mode'))}: <strong>${escapeHtmlString(mode)}</strong></p>
                        ${ids ? `<p>${escapeHtmlString(getI18nString('widgetsWooIds', 'Cibles'))}: ${escapeHtmlString(ids)}</p>` : ''}
                        <div class="sidebar-jlg-widget-preview__products">
                            <span class="sidebar-jlg-widget-preview__pill">${escapeHtmlString(getI18nString('widgetsWooSample', 'Produit en avant'))}</span>
                            <span class="sidebar-jlg-widget-preview__pill">${escapeHtmlString(getI18nString('widgetsWooSample', 'Produit en avant'))}</span>
                        </div>
                    </div>
                `;
            }
            default:
                return `<div class="sidebar-jlg-widget-preview"><h4>${safeTitle}</h4></div>`;
        }
    }

    function createFieldWrapper(labelText, description) {
        const wrapper = document.createElement('div');
        wrapper.className = 'sidebar-jlg-widget-field';

        if (labelText) {
            const label = document.createElement('label');
            label.textContent = labelText;
            wrapper.appendChild(label);
        }

        if (description) {
            const hint = document.createElement('p');
            hint.className = 'description';
            hint.textContent = description;
            wrapper.appendChild(hint);
        }

        return wrapper;
    }

    function updateWidgetValue(widgetId, path, value) {
        const widgetIndex = widgets.findIndex((widget) => widget.id === widgetId);
        if (widgetIndex === -1) {
            return;
        }

        let target = widgets[widgetIndex];
        for (let i = 0; i < path.length - 1; i += 1) {
            const key = path[i];
            if (!target[key] || typeof target[key] !== 'object') {
                target[key] = {};
            }
            target = target[key];
        }

        target[path[path.length - 1]] = value;

        syncHiddenField();
        renderList();
        renderPreview();
    }

    function createWidgetField(widget, sectionKey, fieldKey, definition, value) {
        if (!definition || typeof definition !== 'object') {
            return null;
        }

        const fieldWrapper = createFieldWrapper(definition.label || fieldKey, definition.description || '');
        const type = definition.type || 'text';
        const path = [sectionKey, fieldKey];

        switch (type) {
            case 'textarea':
            case 'richtext': {
                const textarea = document.createElement('textarea');
                textarea.value = typeof value === 'string' ? value : '';
                textarea.addEventListener('input', () => {
                    updateWidgetValue(widget.id, path, textarea.value);
                });
                fieldWrapper.appendChild(textarea);
                break;
            }
            case 'url':
            case 'text': {
                const input = document.createElement('input');
                input.type = type === 'url' ? 'url' : 'text';
                input.value = typeof value === 'string' ? value : '';
                if (definition.placeholder) {
                    input.placeholder = definition.placeholder;
                }
                input.addEventListener('input', () => {
                    updateWidgetValue(widget.id, path, input.value);
                });
                fieldWrapper.appendChild(input);
                break;
            }
            case 'select': {
                const select = document.createElement('select');
                const choices = Array.isArray(definition.choices) ? definition.choices : [];
                choices.forEach((choice) => {
                    const option = document.createElement('option');
                    option.value = choice;
                    option.textContent = choice;
                    select.appendChild(option);
                });
                select.value = typeof value === 'string' && value !== '' ? value : (choices[0] || '');
                select.addEventListener('change', () => {
                    updateWidgetValue(widget.id, path, select.value);
                });
                fieldWrapper.appendChild(select);
                break;
            }
            case 'boolean': {
                const checkbox = document.createElement('input');
                checkbox.type = 'checkbox';
                checkbox.checked = !!value;
                checkbox.addEventListener('change', () => {
                    updateWidgetValue(widget.id, path, checkbox.checked);
                });
                fieldWrapper.appendChild(checkbox);
                break;
            }
            case 'number': {
                const input = document.createElement('input');
                input.type = 'number';
                input.value = Number.isFinite(Number(value)) ? Number(value) : (definition.default || 0);
                input.addEventListener('input', () => {
                    const numeric = Number(input.value);
                    updateWidgetValue(widget.id, path, Number.isFinite(numeric) ? numeric : 0);
                });
                fieldWrapper.appendChild(input);
                break;
            }
            case 'dimension': {
                const dimension = (value && typeof value === 'object') ? value : { value: '', unit: '' };
                const units = Array.isArray(definition.units) && definition.units.length ? definition.units : ['px'];

                const input = document.createElement('input');
                input.type = 'text';
                input.value = typeof dimension.value === 'string' ? dimension.value : String(dimension.value || '');
                input.style.marginRight = '8px';

                const select = document.createElement('select');
                units.forEach((unit) => {
                    const option = document.createElement('option');
                    option.value = unit;
                    option.textContent = unit;
                    select.appendChild(option);
                });
                select.value = typeof dimension.unit === 'string' && dimension.unit !== '' ? dimension.unit : units[0];

                const commit = () => {
                    updateWidgetValue(widget.id, path, {
                        value: input.value,
                        unit: select.value,
                    });
                };

                input.addEventListener('input', commit);
                select.addEventListener('change', commit);

                fieldWrapper.appendChild(input);
                fieldWrapper.appendChild(select);
                break;
            }
            case 'repeater': {
                const items = Array.isArray(value) ? value : [];
                const list = document.createElement('div');
                list.className = 'sidebar-jlg-widget-field__repeater';

                const renderItems = () => {
                    list.innerHTML = '';
                    items.forEach((item, index) => {
                        const card = document.createElement('div');
                        card.className = 'sidebar-jlg-widget-field__repeater-item';
                        const headingInput = document.createElement('input');
                        headingInput.type = 'text';
                        headingInput.placeholder = getI18nString('widgetsRepeaterHeading', 'Titre');
                        headingInput.value = typeof item.heading === 'string' ? item.heading : '';
                        headingInput.addEventListener('input', () => {
                            item.heading = headingInput.value;
                            updateWidgetValue(widget.id, path, items);
                        });

                        const textArea = document.createElement('textarea');
                        textArea.placeholder = getI18nString('widgetsRepeaterContent', 'Contenu');
                        textArea.value = typeof item.text === 'string' ? item.text : '';
                        textArea.addEventListener('input', () => {
                            item.text = textArea.value;
                            updateWidgetValue(widget.id, path, items);
                        });

                        const removeButton = document.createElement('button');
                        removeButton.type = 'button';
                        removeButton.className = 'button-link';
                        removeButton.textContent = getI18nString('widgetsRepeaterRemove', 'Supprimer');
                        removeButton.addEventListener('click', () => {
                            items.splice(index, 1);
                            updateWidgetValue(widget.id, path, items);
                            renderItems();
                        });

                        card.appendChild(headingInput);
                        card.appendChild(textArea);
                        card.appendChild(removeButton);

                        list.appendChild(card);
                    });
                };

                renderItems();

                const addItemButton = document.createElement('button');
                addItemButton.type = 'button';
                addItemButton.className = 'button';
                addItemButton.textContent = getI18nString('widgetsRepeaterAdd', 'Ajouter un élément');
                addItemButton.addEventListener('click', () => {
                    items.push({ heading: '', text: '' });
                    updateWidgetValue(widget.id, path, items);
                    renderItems();
                });

                fieldWrapper.appendChild(list);
                fieldWrapper.appendChild(addItemButton);
                break;
            }
            default:
                break;
        }

        return fieldWrapper;
    }

    function renderEditor() {
        if (!editorElement) {
            return;
        }

        ensureSelectionIntegrity();
        const activeWidget = widgets.find((widget) => widget.id === selectedId);

        editorElement.innerHTML = '';

        if (!activeWidget) {
            if (editorPlaceholder) {
                editorPlaceholder.style.display = '';
            }
            return;
        }

        if (editorPlaceholder) {
            editorPlaceholder.style.display = 'none';
        }

        const schema = normalizedSchemas[activeWidget.type] || {};
        const sections = [
            { key: 'content', schema: schema.content || {}, label: getI18nString('widgetsSectionContent', 'Contenu') },
            { key: 'style', schema: schema.style || {}, label: getI18nString('widgetsSectionStyle', 'Style') },
            { key: 'tracking', schema: schema.tracking && schema.tracking.fields ? schema.tracking.fields : {}, label: getI18nString('widgetsSectionTracking', 'Tracking') },
        ];

        sections.forEach((section) => {
            if (!section.schema || Object.keys(section.schema).length === 0) {
                return;
            }

            const fieldset = document.createElement('fieldset');
            fieldset.className = 'sidebar-jlg-widgets-fieldset';
            const legend = document.createElement('legend');
            legend.textContent = section.label;
            fieldset.appendChild(legend);

            Object.keys(section.schema).forEach((fieldKey) => {
                const field = createWidgetField(
                    activeWidget,
                    section.key,
                    fieldKey,
                    section.schema[fieldKey],
                    activeWidget[section.key] ? activeWidget[section.key][fieldKey] : undefined
                );
                if (field) {
                    fieldset.appendChild(field);
                }
            });

            editorElement.appendChild(fieldset);
        });
    }

    function initSortable() {
        if (!listElement || !window.jQuery) {
            return;
        }

        const $list = window.jQuery(listElement);
        if (typeof $list.sortable !== 'function') {
            return;
        }

        if ($list.data('ui-sortable')) {
            $list.sortable('destroy');
        }

        $list.sortable({
            handle: '.sidebar-jlg-widget-card__handle',
            placeholder: 'sidebar-jlg-widget-card__placeholder',
            update: () => {
                const orderedIds = [];
                $list.children().each(function collect() {
                    const id = this.getAttribute('data-widget-id');
                    if (id) {
                        orderedIds.push(id);
                    }
                });

                if (orderedIds.length !== widgets.length) {
                    return;
                }

                const reordered = [];
                orderedIds.forEach((id) => {
                    const match = widgets.find((widget) => widget.id === id);
                    if (match) {
                        reordered.push(match);
                    }
                });

                if (reordered.length === widgets.length) {
                    widgets = reordered;
                    syncHiddenField();
                    renderAll();
                }
            },
        });
    }

    function renderAll() {
        renderList();
        renderEditor();
        renderPreview();
        syncHiddenField();
    }

    if (addButton) {
        addButton.addEventListener('click', () => {
            if (!typeSelect) {
                return;
            }

            const selectedType = sanitizeWidgetType(typeSelect.value);
            if (!selectedType || !normalizedSchemas[selectedType]) {
                typeSelect.focus();
                return;
            }

            const newWidget = createWidgetFromSchema(selectedType, normalizedSchemas[selectedType]);
            widgets.push(newWidget);
            selectedId = newWidget.id;
            renderAll();

            if (typeSelect.options.length > 0) {
                typeSelect.selectedIndex = 0;
            }
        });
    }

    renderAll();

    return {
        getWidgets: () => widgets.slice(),
        setSelectedId: (id) => {
            selectedId = id;
            renderAll();
        },
        refresh: renderAll,
    };
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
        this.viewportsContainer = this.container
            ? this.container.querySelector('[data-sidebar-preview-viewports]')
            : null;
        this.viewport = this.viewportsContainer
            ? this.viewportsContainer.querySelector('[data-preview-role="primary"]')
            : (this.container ? this.container.querySelector('.sidebar-jlg-preview__viewport') : null);
        this.splitViewClones = this.viewportsContainer
            ? Array.from(this.viewportsContainer.querySelectorAll('[data-preview-role="split"]'))
            : [];
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

        this.$ = typeof window !== 'undefined' ? window.jQuery : null;

        const initialPreviewSize = this.container ? this.container.getAttribute('data-preview-size') : '';
        this.previewSize = SidebarPreviewModule.normalizePreviewSize(initialPreviewSize);
        this.toolbar = this.container ? this.container.querySelector('.sidebar-jlg-preview__toolbar') : null;
        this.previewButtons = this.toolbar ? Array.from(this.toolbar.querySelectorAll('[data-preview-size]')) : [];
        this.toolbarInitialized = false;
        this.splitToggle = this.toolbar ? this.toolbar.querySelector('[data-preview-split-toggle]') : null;
        this.touchOverlay = this.container ? this.container.querySelector('[data-touch-overlay]') : null;
        this.breakpointsForm = this.container ? this.container.querySelector('[data-preview-breakpoints]') : null;
        this.breakpointInputs = this.breakpointsForm
            ? Array.from(this.breakpointsForm.querySelectorAll('[data-breakpoint-input]'))
            : [];
        this.breakpointsInitialized = false;
        this.splitViewEnabled = false;

        this.breakpoints = SidebarPreviewModule.getDefaultBreakpoints(this.container);
        this.applyBreakpointVariables();
        this.populateBreakpointInputs();

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
        this.updateTouchOverlay();

        this.updateMenuFromDomBound = this.updateMenuFromDom.bind(this);
        this.updateSocialFromDomBound = this.updateSocialFromDom.bind(this);
        this.handleResizeBound = this.updateTouchOverlay.bind(this);

        if (typeof window !== 'undefined' && typeof window.addEventListener === 'function') {
            window.addEventListener('resize', this.handleResizeBound, { passive: true });
        }
    }

    static normalizePreviewSize(value) {
        const normalized = typeof value === 'string' ? value.toLowerCase() : '';
        if (normalized === 'mobile' || normalized === 'tablet' || normalized === 'desktop') {
            return normalized;
        }

        return 'mobile';
    }

    static getPreviewLabel(value) {
        const normalized = SidebarPreviewModule.normalizePreviewSize(value);
        return normalized.charAt(0).toUpperCase() + normalized.slice(1);
    }

    static getDefaultBreakpoints(container) {
        const defaults = {
            mobile: 360,
            tablet: 820,
            desktop: 1160
        };

        if (!container) {
            return defaults;
        }

        return {
            mobile: SidebarPreviewModule.parseBreakpoint(container.getAttribute('data-breakpoint-mobile'), defaults.mobile),
            tablet: SidebarPreviewModule.parseBreakpoint(container.getAttribute('data-breakpoint-tablet'), defaults.tablet),
            desktop: SidebarPreviewModule.parseBreakpoint(container.getAttribute('data-breakpoint-desktop'), defaults.desktop)
        };
    }

    static normalizeBreakpointKey(value) {
        const normalized = typeof value === 'string' ? value.toLowerCase() : '';
        if (normalized === 'mobile' || normalized === 'tablet' || normalized === 'desktop') {
            return normalized;
        }

        return '';
    }

    static parseBreakpoint(value, fallback) {
        const fallbackValue = Number.isFinite(fallback) ? fallback : 0;
        if (typeof value === 'number' && Number.isFinite(value)) {
            return Math.max(0, Math.round(value));
        }

        if (typeof value !== 'string') {
            return fallbackValue;
        }

        const trimmed = value.trim();
        if (trimmed === '') {
            return fallbackValue;
        }

        const parsed = Number.parseInt(trimmed, 10);
        if (!Number.isFinite(parsed)) {
            return fallbackValue;
        }

        return Math.max(0, parsed);
    }

    static getPreviewOrder(size) {
        const normalized = SidebarPreviewModule.normalizePreviewSize(size);
        if (normalized === 'mobile') {
            return 1;
        }

        if (normalized === 'tablet') {
            return 2;
        }

        return 3;
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
                this.setupBreakpointControls();
                this.setupBindings();
                this.applyOptions();
                this.updateTouchOverlay();
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

    loadPreview() {
        if (!this.ajaxUrl || !this.nonce || !this.action) {
            this.renderFallback(this.messages.error || '');
            return Promise.reject(new Error('Missing preview configuration.'));
        }

        return new Promise((resolve, reject) => {
            this.$.ajax({
                url: this.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: this.action,
                    nonce: this.nonce,
                    options: JSON.stringify(this.currentOptions || {})
                }
            })
                .done((response) => {
                    if (response && response.success && response.data && typeof response.data.html === 'string') {
                        this.viewport.innerHTML = response.data.html;
                        this.afterMarkupRendered();
                        this.refreshSplitView();
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
        this.updateTouchOverlay();
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
            if (this.splitToggle) {
                this.splitToggle.addEventListener('click', (event) => {
                    event.preventDefault();
                    this.setSplitView(!this.splitViewEnabled);
                });
            }
            this.toolbarInitialized = true;
        }

        this.setPreviewSize(this.previewSize, { skipApply: true });
        this.updateSplitToggleState();
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

        this.refreshSplitView();
        this.updateTouchOverlay();
    }

    updatePreviewSizeClasses() {
        if (this.container) {
            this.container.setAttribute('data-preview-size', this.previewSize);
        }

        if (this.viewport) {
            this.viewport.setAttribute('data-preview-size', this.previewSize);
            const label = this.getPreviewButtonLabel(this.previewSize) || SidebarPreviewModule.getPreviewLabel(this.previewSize);
            this.viewport.setAttribute('data-preview-label', label);
            this.replaceClass(this.viewport, 'preview-', this.previewSize);
        }

        if (this.aside) {
            this.aside.dataset.previewSize = this.previewSize;
            this.replaceClass(this.aside, 'preview-', this.previewSize);
        }

        this.syncOverlayVisibility();
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

    updateTouchOverlay() {
        if (!this.touchOverlay) {
            return;
        }

        const shouldDisplay = this.previewSize === 'mobile' || this.splitViewEnabled;
        if (shouldDisplay) {
            this.touchOverlay.removeAttribute('hidden');
            const target = this.resolveMobilePreviewTarget();
            if (target) {
                const raf = (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function')
                    ? window.requestAnimationFrame.bind(window)
                    : (callback) => callback();

                raf(() => {
                    this.positionTouchOverlay(target);
                });
            }
        } else {
            this.touchOverlay.setAttribute('hidden', '');
        }
    }

    resolveMobilePreviewTarget() {
        if (!this.viewportsContainer) {
            return this.previewSize === 'mobile' ? this.viewport : null;
        }

        if (!this.splitViewEnabled) {
            return this.previewSize === 'mobile' ? this.viewport : null;
        }

        const candidates = Array.from(this.viewportsContainer.querySelectorAll('.sidebar-jlg-preview__viewport'));
        const mobileCandidate = candidates.find((element) => {
            if (!element || element.hasAttribute('hidden')) {
                return false;
            }

            const size = SidebarPreviewModule.normalizePreviewSize(element.getAttribute('data-preview-size'));
            return size === 'mobile';
        });

        if (mobileCandidate) {
            return mobileCandidate;
        }

        return this.previewSize === 'mobile' ? this.viewport : null;
    }

    positionTouchOverlay(target) {
        if (!this.touchOverlay || !target || !this.container) {
            return;
        }

        const containerRect = this.container.getBoundingClientRect();
        const targetRect = target.getBoundingClientRect();
        const left = Math.max(0, targetRect.left - containerRect.left);
        const width = Math.max(0, targetRect.width);

        this.touchOverlay.style.setProperty('--sidebar-touch-left', `${left}px`);
        this.touchOverlay.style.setProperty('--sidebar-touch-width', `${width}px`);
    }

    updateSplitToggleState() {
        if (!this.splitToggle) {
            return;
        }

        const isActive = Boolean(this.splitViewEnabled);
        this.splitToggle.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        this.splitToggle.classList.toggle('is-active', isActive);
    }

    setSplitView(enable) {
        const nextState = Boolean(enable);
        if (nextState === this.splitViewEnabled) {
            return;
        }

        this.splitViewEnabled = nextState;

        if (this.container) {
            this.container.setAttribute('data-split-view', this.splitViewEnabled ? 'true' : 'false');
        }

        this.refreshSplitView();
        this.updateSplitToggleState();
        this.updateTouchOverlay();

        if (!this.splitViewEnabled && this.splitViewClones && this.splitViewClones.length) {
            this.splitViewClones.forEach((clone) => {
                if (clone) {
                    clone.setAttribute('hidden', '');
                    clone.setAttribute('aria-hidden', 'true');
                }
            });
        }
    }

    refreshSplitView() {
        if (!this.splitViewEnabled || !this.splitViewClones || !this.splitViewClones.length || !this.viewport) {
            return;
        }

        const markup = this.viewport.innerHTML;
        const primaryOrder = SidebarPreviewModule.getPreviewOrder(this.previewSize);

        if (this.viewport && typeof this.viewport.style !== 'undefined') {
            this.viewport.style.order = primaryOrder;
        }

        this.splitViewClones.forEach((clone) => {
            if (!clone) {
                return;
            }

            clone.innerHTML = markup;
            const size = SidebarPreviewModule.normalizePreviewSize(clone.getAttribute('data-preview-size'));
            const label = this.getPreviewButtonLabel(size) || SidebarPreviewModule.getPreviewLabel(size);
            clone.setAttribute('data-preview-size', size);
            clone.setAttribute('data-preview-label', label);
            this.replaceClass(clone, 'preview-', size);

            if (typeof clone.setAttribute === 'function') {
                clone.setAttribute('aria-hidden', 'true');
                clone.setAttribute('inert', '');
            }

            if (size === this.previewSize) {
                clone.setAttribute('hidden', '');
            } else {
                clone.removeAttribute('hidden');
            }

            if (typeof clone.style !== 'undefined') {
                clone.style.order = SidebarPreviewModule.getPreviewOrder(size);
            }

            const focusable = clone.querySelectorAll('a, button, input, select, textarea, [tabindex]');
            focusable.forEach((element) => {
                element.setAttribute('tabindex', '-1');
                element.setAttribute('aria-hidden', 'true');
            });
        });
    }

    populateBreakpointInputs() {
        if (!this.breakpointInputs || !this.breakpointInputs.length) {
            return;
        }

        this.breakpointInputs.forEach((input) => {
            if (!input) {
                return;
            }

            const key = SidebarPreviewModule.normalizeBreakpointKey(input.getAttribute('data-breakpoint-input'));
            if (!key || !Object.prototype.hasOwnProperty.call(this.breakpoints, key)) {
                return;
            }

            input.value = this.breakpoints[key];
        });
    }

    applyBreakpointVariables() {
        if (!this.container) {
            return;
        }

        const map = {
            mobile: '--sidebar-preview-width-mobile',
            tablet: '--sidebar-preview-width-tablet',
            desktop: '--sidebar-preview-width-desktop'
        };

        Object.keys(map).forEach((key) => {
            const cssVar = map[key];
            const value = this.breakpoints[key];
            if (Number.isFinite(value) && value > 0) {
                this.container.style.setProperty(cssVar, `${value}px`);
                this.container.setAttribute(`data-breakpoint-${key}`, String(value));
            }
        });
    }

    setupBreakpointControls() {
        if (this.breakpointsInitialized || !this.breakpointInputs || !this.breakpointInputs.length) {
            return;
        }

        this.breakpointInputs.forEach((input) => {
            if (!input) {
                return;
            }

            input.addEventListener('input', () => {
                this.handleBreakpointInput(input, { previewOnly: true });
            });

            input.addEventListener('change', () => {
                this.handleBreakpointInput(input);
            });
        });

        this.breakpointsInitialized = true;
    }

    handleBreakpointInput(input, options = {}) {
        if (!input) {
            return;
        }

        const key = SidebarPreviewModule.normalizeBreakpointKey(input.getAttribute('data-breakpoint-input'));
        if (!key) {
            return;
        }

        const previous = this.breakpoints[key];
        const nextValue = SidebarPreviewModule.parseBreakpoint(input.value, previous);

        if (!Number.isFinite(nextValue) || nextValue <= 0) {
            input.value = previous;
            return;
        }

        this.breakpoints[key] = nextValue;
        this.applyBreakpointVariables();

        if (!options || !options.previewOnly) {
            this.refreshSplitView();
        }

        this.updateTouchOverlay();
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

        this.bindField('sidebar_jlg_settings[style_preset]', (value) => {
            this.currentOptions.style_preset = value || 'custom';
        });

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

    initializeWidgetBuilderInterface({
        container: document.querySelector('[data-widget-builder]'),
        hiddenField: document.getElementById('sidebar-jlg-widgets-field'),
        schemas: sidebarJLG.widget_schemas || {},
    });

    const EXPERIENCE_STORAGE_KEY = 'sidebarJlgExperienceMode';
    const experienceDom = {
        root: document.querySelector('[data-sidebar-experience]'),
        toggle: document.querySelector('[data-sidebar-mode-toggle]'),
        summary: document.querySelector('[data-sidebar-mode-summary]'),
    };
    const experienceButtons = experienceDom.toggle
        ? Array.from(experienceDom.toggle.querySelectorAll('[data-mode-value]'))
        : [];
    const experienceState = {
        mode: 'simple',
        view: 'form',
    };

    function normalizeExperienceMode(value) {
        return value === 'expert' ? 'expert' : 'simple';
    }

    function normalizeExperienceView(value) {
        return value === 'canvas' ? 'canvas' : 'form';
    }

    if (experienceDom.root) {
        const initialView = experienceDom.root.getAttribute('data-sidebar-experience-mode');
        if (initialView === 'canvas') {
            experienceState.view = 'canvas';
        }

        const storedFormMode = experienceDom.root.getAttribute('data-sidebar-form-mode');
        if (typeof storedFormMode === 'string' && storedFormMode !== '') {
            experienceState.mode = normalizeExperienceMode(storedFormMode);
        }
    }

    function readStoredExperienceMode() {
        try {
            if (window.localStorage) {
                const stored = window.localStorage.getItem(EXPERIENCE_STORAGE_KEY);
                if (typeof stored === 'string' && stored !== '') {
                    if (stored === 'canvas') {
                        experienceState.view = 'canvas';

                        return 'simple';
                    }

                    return normalizeExperienceMode(stored);
                }
            }
        } catch (error) {
            // Ignore storage access errors (private mode, quota, etc.).
        }

        if (experienceDom.root && typeof experienceDom.root.getAttribute === 'function') {
            const initial = experienceDom.root.getAttribute('data-sidebar-experience-mode');
            if (typeof initial === 'string' && initial !== '') {
                if (initial === 'canvas') {
                    experienceState.view = 'canvas';

                    return 'simple';
                }

                return normalizeExperienceMode(initial);
            }
        }

        return 'simple';
    }

    function persistExperienceMode(mode) {
        if (mode === 'canvas') {
            return;
        }

        try {
            if (window.localStorage) {
                window.localStorage.setItem(EXPERIENCE_STORAGE_KEY, mode);
            }
        } catch (error) {
            // Silently ignore storage persistence issues.
        }
    }

    function updateExperienceButtons() {
        if (!experienceButtons.length) {
            return;
        }

        experienceButtons.forEach((button) => {
            const value = normalizeExperienceMode(button.getAttribute('data-mode-value'));
            const isActive = experienceState.view === 'form' && value === experienceState.mode;
            button.classList.toggle('is-active', isActive);
            button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
        });
    }

    function getAdvancedElements() {
        if (!experienceDom.root) {
            return [];
        }

        return Array.from(experienceDom.root.querySelectorAll('[data-sidebar-mode="expert"]'));
    }

    function updateExperienceSummary() {
        if (!experienceDom.summary) {
            return;
        }

        if (experienceState.view === 'canvas') {
            const canvasSummary = getI18nString(
                'canvasModeSummary',
                'Mode canvas : pilotez la sidebar en glisser-déposer.'
            );
            experienceDom.summary.textContent = canvasSummary;

            return;
        }

        if (experienceState.mode === 'expert') {
            const expertSummary = getI18nString(
                'experienceModeExpertSummary',
                'Mode expert : toutes les options sont visibles.'
            );
            experienceDom.summary.textContent = expertSummary;
            return;
        }

        const advancedCount = getAdvancedElements().length;
        const template = getI18nString(
            'experienceModeSimpleSummary',
            'Mode simple : %d option(s) avancée(s) masquée(s).'
        );
        experienceDom.summary.textContent = template.replace('%d', advancedCount);
    }

    function syncExperienceModeVisibility() {
        if (!experienceDom.root) {
            return;
        }

        const attributeValue = experienceState.view === 'canvas' ? 'canvas' : experienceState.mode;
        experienceDom.root.setAttribute('data-sidebar-experience-mode', attributeValue);
        experienceDom.root.setAttribute('data-sidebar-experience-view', experienceState.view);

        if (experienceState.view !== 'canvas') {
            experienceDom.root.setAttribute('data-sidebar-form-mode', experienceState.mode);
        }

        if (experienceState.view === 'form') {
            getAdvancedElements().forEach((element) => {
                if (experienceState.mode === 'simple') {
                    element.setAttribute('aria-hidden', 'true');
                    element.classList.add('is-experience-hidden');
                } else {
                    element.removeAttribute('aria-hidden');
                    element.classList.remove('is-experience-hidden');
                }
            });
        }

        updateExperienceButtons();
        updateExperienceSummary();
    }

    function setExperienceMode(nextMode, { persist = false } = {}) {
        if (nextMode === 'canvas') {
            setExperienceView('canvas');

            return;
        }

        const normalized = normalizeExperienceMode(nextMode);

        if (experienceState.mode !== normalized) {
            experienceState.mode = normalized;
            if (persist) {
                persistExperienceMode(normalized);
            }
        }

        if (experienceState.view !== 'form') {
            setExperienceView('form');

            return;
        }

        syncExperienceModeVisibility();
    }

    function setExperienceView(nextView) {
        const normalized = normalizeExperienceView(nextView);
        if (experienceState.view !== normalized) {
            experienceState.view = normalized;
            syncExperienceModeVisibility();
        }

        return experienceState.view;
    }

    function bindExperienceModeToggle() {
        if (!experienceButtons.length) {
            setExperienceMode(readStoredExperienceMode());
            return;
        }

        experienceButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const value = button.getAttribute('data-mode-value');
                setExperienceMode(value, { persist: true });
            });
        });

        setExperienceMode(readStoredExperienceMode());
    }

    bindExperienceModeToggle();

    const profileChoices = normalizeProfileChoices(sidebarJLG.profile_choices);
    const profilesState = {
        profiles: [],
        selectedId: '',
        activeId: '',
    };
    const previewActiveTemplate = (sidebarJLG.preview_messages && typeof sidebarJLG.preview_messages.activeProfile === 'string')
        ? sidebarJLG.preview_messages.activeProfile
        : 'Profil actif : %s';

    const previewModule = new SidebarPreviewModule({
        container: document.getElementById('sidebar-jlg-preview'),
        form: document.getElementById('sidebar-jlg-form'),
        ajaxUrl,
        nonce: sidebarJLG.preview_nonce || '',
        action: sidebarJLG.preview_action || 'jlg_render_preview',
        options,
        messages: sidebarJLG.preview_messages || {}
    });

    const originalLoadPreview = previewModule.loadPreview.bind(previewModule);
    previewModule.loadPreview = function(...args) {
        return originalLoadPreview(...args).then((response) => {
            updateActiveProfileStatusMessage();
            return response;
        });
    };

    previewModule.init();

    initializeUnitControls();
    initializeRangeControls();
    initializeAccessibilityChecklist();
    initializeAccessibilityAudit();

    const compareControls = setupPreviewCompare(previewModule);
    initializeStylePresets(compareControls);
    initializeSectionNavigation();

    if (typeof window !== 'undefined') {
        window.SidebarJLGPreview = previewModule;
    }

    const experienceApi = {
        getMode: () => experienceState.mode,
        setMode: (mode, options = {}) => setExperienceMode(mode, options),
        getView: () => experienceState.view,
        setView: (view) => setExperienceView(view),
    };

    if (typeof window !== 'undefined') {
        window.SidebarJLGExperience = experienceApi;
    }

    if (typeof module !== 'undefined' && module.exports) {
        module.exports.SidebarJLGExperience = experienceApi;
    }

    function setupPreviewCompare(previewModuleInstance) {
        const button = document.getElementById('sidebar-jlg-preview-compare');
        const formElement = document.getElementById('sidebar-jlg-form');

        if (!button || !previewModuleInstance) {
            return {
                exit: () => Promise.resolve(),
                isActive: () => false,
            };
        }

        const labelSpan = button.querySelector('.sidebar-jlg-preview__toolbar-button-label');
        const defaultLabelFallback = labelSpan && labelSpan.textContent
            ? labelSpan.textContent.trim()
            : (button.textContent || '').trim();
        const defaultLabel = getI18nString('stylePresetCompareButton', defaultLabelFallback || 'Comparer avant/après');
        const exitLabel = getI18nString('stylePresetCompareExit', 'Revenir à l’après');
        const beforeMessage = getI18nString('stylePresetCompareBefore', '');
        const afterMessage = getI18nString('stylePresetCompareAfter', '');

        if (labelSpan) {
            labelSpan.textContent = defaultLabel;
        } else {
            button.textContent = defaultLabel;
        }

        button.setAttribute('aria-label', defaultLabel);
        button.setAttribute('aria-pressed', 'false');

        const state = {
            active: false,
            disabledFields: [],
            afterOptions: null,
            busy: false,
        };

        const setButtonState = (active) => {
            const label = active ? exitLabel : defaultLabel;
            if (labelSpan) {
                labelSpan.textContent = label;
            } else {
                button.textContent = label;
            }
            button.classList.toggle('is-active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
            button.setAttribute('aria-label', label);
        };

        const toggleButtonBusy = (busy) => {
            state.busy = busy;
            button.disabled = busy;
            button.classList.toggle('is-busy', busy);
        };

        const disableFormControls = (disabled) => {
            if (!formElement) {
                return;
            }

            if (disabled) {
                state.disabledFields = [];
                const elements = Array.from(formElement.querySelectorAll('input, select, textarea, button'));
                elements.forEach((element) => {
                    if (element === button) {
                        return;
                    }
                    if (element.disabled) {
                        return;
                    }
                    element.disabled = true;
                    state.disabledFields.push(element);
                });
                formElement.setAttribute('aria-disabled', 'true');
            } else {
                state.disabledFields.forEach((element) => {
                    element.disabled = false;
                });
                state.disabledFields = [];
                formElement.removeAttribute('aria-disabled');
            }
        };

        const enterCompare = () => {
            if (state.active || state.busy) {
                return Promise.resolve();
            }

            state.afterOptions = SidebarPreviewModule.cloneObject(previewModuleInstance.currentOptions || {});
            setButtonState(true);
            disableFormControls(true);
            toggleButtonBusy(true);
            state.active = true;

            previewModuleInstance.currentOptions = SidebarPreviewModule.cloneObject(previewModuleInstance.initialOptions || {});

            return previewModuleInstance.loadPreview()
                .then(() => {
                    previewModuleInstance.applyOptions();
                    if (beforeMessage) {
                        previewModuleInstance.setStatus(beforeMessage, false);
                    }
                })
                .catch(() => {
                    state.active = false;
                    disableFormControls(false);
                    setButtonState(false);
                })
                .finally(() => {
                    toggleButtonBusy(false);
                });
        };

        const exitCompare = () => {
            if (!state.active || state.busy) {
                return Promise.resolve();
            }

            const afterOptions = SidebarPreviewModule.cloneObject(state.afterOptions || previewModuleInstance.currentOptions || {});
            setButtonState(false);
            disableFormControls(false);
            toggleButtonBusy(true);
            state.active = false;
            previewModuleInstance.currentOptions = afterOptions;

            return previewModuleInstance.loadPreview()
                .then(() => {
                    previewModuleInstance.applyOptions();
                    if (afterMessage) {
                        previewModuleInstance.setStatus(afterMessage, false);
                    } else {
                        previewModuleInstance.clearStatus();
                    }
                })
                .catch(() => {
                    previewModuleInstance.clearStatus();
                })
                .finally(() => {
                    state.afterOptions = null;
                    toggleButtonBusy(false);
                });
        };

        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (state.active) {
                exitCompare();
            } else {
                enterCompare();
            }
        });

        return {
            exit: exitCompare,
            isActive: () => state.active,
        };
    }

    function initializeStylePresets(compareControls) {
        const hiddenInput = document.getElementById('sidebar-jlg-style-preset');
        const container = document.getElementById('sidebar-jlg-style-presets');
        if (!hiddenInput || !container) {
            return;
        }

        const grid = container.querySelector('[data-style-preset-grid]');
        if (!grid) {
            hiddenInput.disabled = false;
            return;
        }

        hiddenInput.disabled = false;

        const emptyMessage = container.querySelector('[data-style-preset-empty]');
        const applyLabel = getI18nString('stylePresetApplyLabel', 'Utiliser ce préréglage');
        const customLabel = getI18nString('stylePresetCustomLabel', 'Personnalisé');
        const customDescription = getI18nString('stylePresetCustomDescription', 'Utilisez vos propres combinaisons de couleurs, typographies et effets.');
        const emptyText = getI18nString('stylePresetEmpty', 'Aucun préréglage n’est disponible pour le moment.');

        if (emptyMessage) {
            emptyMessage.textContent = emptyText;
        }

        const rawPresets = (typeof sidebarJLG.style_presets === 'object' && sidebarJLG.style_presets !== null)
            ? sidebarJLG.style_presets
            : {};

        const normalizedPresets = Object.keys(rawPresets).map((key) => {
            const raw = rawPresets[key] || {};
            const previewData = raw.preview && typeof raw.preview === 'object' ? raw.preview : {};
            return {
                key,
                label: typeof raw.label === 'string' && raw.label.trim() !== '' ? raw.label : key,
                description: typeof raw.description === 'string' ? raw.description : '',
                settings: raw.settings && typeof raw.settings === 'object' ? raw.settings : null,
                preview: {
                    background: typeof previewData.background === 'string' ? previewData.background : '',
                    accent: typeof previewData.accent === 'string' ? previewData.accent : '',
                    text: typeof previewData.text === 'string' ? previewData.text : '',
                },
            };
        });

        grid.innerHTML = '';

        const ensureCompareInactive = () => {
            if (!compareControls || typeof compareControls.isActive !== 'function') {
                return Promise.resolve();
            }

            try {
                if (compareControls.isActive() && typeof compareControls.exit === 'function') {
                    return Promise.resolve(compareControls.exit());
                }
            } catch (error) {
                return Promise.resolve();
            }

            return Promise.resolve();
        };

        let isApplyingPreset = false;
        let customCardElement = null;

        const markActiveCard = (key) => {
            const cards = grid.querySelectorAll('.sidebar-jlg-style-preset-card');
            cards.forEach((card) => {
                const isActive = card.dataset.presetKey === key;
                card.classList.toggle('is-active', isActive);
                card.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
            container.setAttribute('data-selected-preset', key);
        };

        const updateHiddenInput = (key) => {
            hiddenInput.value = key;
            triggerFieldUpdate(hiddenInput);
        };

        const computeCurrentPreview = () => {
            const source = (previewModule && previewModule.currentOptions) ? previewModule.currentOptions : options;
            const result = {
                background: '#1f2937',
                accent: '#0ea5e9',
                text: '#f8fafc',
            };

            const bgType = typeof source.bg_color_type === 'string' ? source.bg_color_type.toLowerCase() : 'solid';
            if (bgType === 'gradient') {
                const start = source.bg_color_start || source.bg_color || result.background;
                const end = source.bg_color_end || start;
                result.background = `linear-gradient(180deg, ${start} 0%, ${end} 100%)`;
            } else if (source.bg_color) {
                result.background = source.bg_color;
            }

            const accentType = typeof source.accent_color_type === 'string' ? source.accent_color_type.toLowerCase() : 'solid';
            if (accentType === 'gradient') {
                const start = source.accent_color_start || source.accent_color || result.accent;
                const end = source.accent_color_end || start;
                result.accent = `linear-gradient(135deg, ${start} 0%, ${end} 100%)`;
            } else if (source.accent_color) {
                result.accent = source.accent_color;
            }

            if (source.font_color) {
                result.text = source.font_color;
            }

            return result;
        };

        const updateCustomCardPreview = () => {
            if (!customCardElement) {
                return;
            }

            const previewElement = customCardElement.querySelector('.sidebar-jlg-style-preset-card__preview');
            const accentElement = customCardElement.querySelector('.sidebar-jlg-style-preset-card__accent');
            const sampleElement = customCardElement.querySelector('.sidebar-jlg-style-preset-card__sample');

            if (!previewElement || !accentElement || !sampleElement) {
                return;
            }

            const currentPreview = computeCurrentPreview();
            previewElement.style.background = currentPreview.background;
            accentElement.style.background = currentPreview.accent;
            sampleElement.style.color = currentPreview.text;
        };

        const applyPresetSettings = (settings) => {
            if (!settings || typeof settings !== 'object') {
                return;
            }

            Object.keys(settings).forEach((optionKey) => {
                const optionValue = settings[optionKey];

                if (optionValue && typeof optionValue === 'object' && Object.prototype.hasOwnProperty.call(optionValue, 'value') && Object.prototype.hasOwnProperty.call(optionValue, 'unit')) {
                    const valueInput = document.querySelector(`[name="sidebar_jlg_settings[${optionKey}][value]"]`);
                    const unitInput = document.querySelector(`[name="sidebar_jlg_settings[${optionKey}][unit]"]`);

                    if (valueInput) {
                        valueInput.value = optionValue.value;
                        triggerFieldUpdate(valueInput);
                    }
                    if (unitInput) {
                        unitInput.value = optionValue.unit;
                        triggerFieldUpdate(unitInput);
                    }

                    return;
                }

                const radioElements = document.querySelectorAll(`input[type="radio"][name="sidebar_jlg_settings[${optionKey}]"]`);
                if (radioElements.length) {
                    let matched = null;
                    radioElements.forEach((radio) => {
                        const isMatch = radio.value === String(optionValue);
                        radio.checked = isMatch;
                        if (isMatch) {
                            matched = radio;
                        }
                    });
                    if (matched) {
                        triggerFieldUpdate(matched);
                    } else if (radioElements[0]) {
                        triggerFieldUpdate(radioElements[0]);
                    }
                    return;
                }

                const checkbox = document.querySelector(`input[type="checkbox"][name="sidebar_jlg_settings[${optionKey}]"]`);
                if (checkbox) {
                    const isChecked = optionValue === true || optionValue === '1' || optionValue === 1 || optionValue === 'on';
                    checkbox.checked = isChecked;
                    triggerFieldUpdate(checkbox);
                    return;
                }

                const field = document.querySelector(`[name="sidebar_jlg_settings[${optionKey}]"]`);
                if (!field) {
                    return;
                }

                field.value = optionValue;
                triggerFieldUpdate(field);

                if (window.jQuery) {
                    const $field = window.jQuery(field);
                    if (typeof $field.wpColorPicker === 'function' && $field.data('wpColorPicker')) {
                        $field.wpColorPicker('color', optionValue);
                    } else if ($field.hasClass('color-picker-rgba')) {
                        $field.trigger('change');
                    }
                }
            });
        };

        const refreshPreview = () => {
            if (!previewModule || typeof previewModule.loadPreview !== 'function') {
                updateCustomCardPreview();
                return Promise.resolve();
            }

            return previewModule.loadPreview()
                .then(() => {
                    previewModule.applyOptions();
                    updateCustomCardPreview();
                })
                .catch(() => {
                    updateCustomCardPreview();
                });
        };

        const handlePresetSelection = (preset) => {
            isApplyingPreset = true;

            ensureCompareInactive()
                .then(() => {
                    if (preset.key !== 'custom' && preset.settings) {
                        applyPresetSettings(preset.settings);
                    }

                    updateHiddenInput(preset.key);
                    markActiveCard(preset.key);

                    return refreshPreview();
                })
                .catch(() => {
                    // Silently ignore compare exit failures.
                })
                .finally(() => {
                    isApplyingPreset = false;
                });
        };

        const createCardElement = (preset) => {
            const card = document.createElement('button');
            card.type = 'button';
            card.className = 'sidebar-jlg-style-preset-card';
            card.dataset.presetKey = preset.key;
            card.setAttribute('aria-pressed', 'false');

            const previewElement = document.createElement('span');
            previewElement.className = 'sidebar-jlg-style-preset-card__preview';
            if (preset.preview.background) {
                previewElement.style.background = preset.preview.background;
            }

            const accentElement = document.createElement('span');
            accentElement.className = 'sidebar-jlg-style-preset-card__accent';
            if (preset.preview.accent) {
                accentElement.style.background = preset.preview.accent;
            }
            previewElement.appendChild(accentElement);

            const sampleElement = document.createElement('span');
            sampleElement.className = 'sidebar-jlg-style-preset-card__sample';
            sampleElement.textContent = 'Aa';
            if (preset.preview.text) {
                sampleElement.style.color = preset.preview.text;
            }
            previewElement.appendChild(sampleElement);

            const titleElement = document.createElement('h4');
            titleElement.className = 'sidebar-jlg-style-preset-card__title';
            titleElement.textContent = preset.label;

            card.appendChild(previewElement);
            card.appendChild(titleElement);

            if (preset.description) {
                const descriptionElement = document.createElement('p');
                descriptionElement.className = 'sidebar-jlg-style-preset-card__description';
                descriptionElement.textContent = preset.description;
                card.appendChild(descriptionElement);
            }

            const ctaElement = document.createElement('span');
            ctaElement.className = 'sidebar-jlg-style-preset-card__cta';
            ctaElement.textContent = applyLabel;
            card.appendChild(ctaElement);

            card.addEventListener('click', () => {
                handlePresetSelection(preset);
            });

            return card;
        };

        const presetsToRender = [
            {
                key: 'custom',
                label: customLabel,
                description: customDescription,
                settings: null,
                preview: computeCurrentPreview(),
            },
            ...normalizedPresets,
        ];

        const presetByKey = {};

        presetsToRender.forEach((preset) => {
            const card = createCardElement(preset);
            if (preset.key === 'custom') {
                customCardElement = card;
            }
            grid.appendChild(card);
            presetByKey[preset.key] = preset;
        });

        const initialKey = hiddenInput.value && hiddenInput.value !== '' ? hiddenInput.value : 'custom';
        markActiveCard(initialKey);
        updateCustomCardPreview();

        const quickContainer = container.querySelector('[data-style-preset-quick]');
        if (quickContainer) {
            quickContainer.querySelectorAll('[data-style-preset-trigger]').forEach((button) => {
                button.addEventListener('click', () => {
                    const key = (button.getAttribute('data-style-preset-trigger') || '').trim();
                    const preset = key && presetByKey[key] ? presetByKey[key] : presetByKey.custom;
                    if (preset) {
                        handlePresetSelection(preset);
                    }
                });
            });
        }

        const styleTab = document.getElementById('tab-presets');
        if (styleTab) {
            const manualChangeHandler = (event) => {
                if (isApplyingPreset) {
                    return;
                }

                const target = event.target;
                if (!(target instanceof HTMLElement)) {
                    return;
                }

                if (!container.contains(target)) {
                    return;
                }

                if (hiddenInput.value !== 'custom') {
                    updateHiddenInput('custom');
                    markActiveCard('custom');
                }

                window.requestAnimationFrame(() => {
                    updateCustomCardPreview();
                });
            };

            styleTab.addEventListener('input', manualChangeHandler);
            styleTab.addEventListener('change', manualChangeHandler);
        }
    }

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

    function createFallbackUnitControl(container, config) {
        const {
            valueInput,
            unitInput,
            label,
            helpText,
            errorMessage,
            allowedUnits,
            initialDimension,
            defaultDimension,
            defaultUnit,
        } = config;

        const wrapper = document.createElement('div');
        wrapper.className = 'sidebar-jlg-unit-control__fallback';

        const baseName = (container.dataset.settingName || valueInput.name || 'sidebar-unit')
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-');
        const uniqueSuffix = Math.random().toString(36).slice(2, 8);
        const fieldId = `${baseName || 'sidebar-unit'}-${uniqueSuffix}`;
        const valueId = `${fieldId}-value`;
        const unitId = `${fieldId}-unit`;
        const helpId = `${fieldId}-help`;
        const feedbackId = `${fieldId}-feedback`;

        const labelElement = document.createElement('label');
        labelElement.className = 'sidebar-jlg-unit-control__fallback-label';
        labelElement.setAttribute('for', valueId);
        labelElement.textContent = label || '';
        wrapper.appendChild(labelElement);

        const controls = document.createElement('div');
        controls.className = 'sidebar-jlg-unit-control__fallback-controls';

        const visibleInput = document.createElement('input');
        visibleInput.type = 'number';
        visibleInput.className = 'sidebar-jlg-unit-control__fallback-input';
        visibleInput.id = valueId;
        visibleInput.step = '0.1';

        const units = (allowedUnits && allowedUnits.length)
            ? allowedUnits
            : [initialDimension.unit || defaultDimension.unit || defaultUnit || 'px'];

        const select = document.createElement('select');
        select.className = 'sidebar-jlg-unit-control__fallback-select';
        select.id = unitId;

        units.forEach((unit) => {
            const option = document.createElement('option');
            option.value = unit;
            option.textContent = unit;
            select.appendChild(option);
        });

        const initialValue = initialDimension.value !== ''
            ? initialDimension.value
            : (defaultDimension.value !== '' ? defaultDimension.value : '0');

        visibleInput.value = initialValue;
        valueInput.value = normalizeNumericString(initialValue);
        const resolvedUnit = initialDimension.unit || defaultDimension.unit || defaultUnit || units[0];
        select.value = resolvedUnit;
        unitInput.value = resolvedUnit;

        const describedBy = [];

        const helpElement = document.createElement('p');
        helpElement.className = 'description';
        helpElement.id = helpId;
        helpElement.textContent = helpText || '';
        if (helpElement.textContent !== '') {
            describedBy.push(helpId);
        }

        const feedbackElement = document.createElement('p');
        feedbackElement.className = 'sidebar-jlg-unit-control__fallback-feedback';
        feedbackElement.id = feedbackId;
        feedbackElement.setAttribute('aria-live', 'polite');
        feedbackElement.textContent = '';
        if (errorMessage) {
            describedBy.push(feedbackId);
        }

        if (describedBy.length) {
            const attr = describedBy.join(' ');
            visibleInput.setAttribute('aria-describedby', attr);
            select.setAttribute('aria-describedby', attr);
        }

        const updateFeedback = (message) => {
            feedbackElement.textContent = message || '';
        };

        visibleInput.addEventListener('input', () => {
            const normalized = normalizeNumericString(visibleInput.value);
            if (normalized === '') {
                valueInput.value = '';
                updateFeedback(errorMessage || helpText || '');
            } else {
                valueInput.value = normalized;
                updateFeedback('');
            }
            triggerFieldUpdate(valueInput);
        });

        visibleInput.addEventListener('blur', () => {
            if (valueInput.value === '') {
                const fallbackValue = defaultDimension.value !== '' ? defaultDimension.value : '0';
                valueInput.value = fallbackValue;
                visibleInput.value = fallbackValue;
                updateFeedback('');
                triggerFieldUpdate(valueInput);
            }
        });

        select.addEventListener('change', () => {
            const nextUnit = select.value;
            unitInput.value = nextUnit;
            triggerFieldUpdate(unitInput);
        });

        controls.appendChild(visibleInput);
        controls.appendChild(select);
        wrapper.appendChild(controls);

        if (helpElement.textContent !== '') {
            wrapper.appendChild(helpElement);
        }

        if (errorMessage) {
            wrapper.appendChild(feedbackElement);
        }

        container.appendChild(wrapper);
    }

    function initializeSectionNavigation() {
        const nav = document.querySelector('[data-sidebar-section-nav]');
        if (!nav) {
            return;
        }

        nav.querySelectorAll('[data-sidebar-section-target]').forEach((button) => {
            button.addEventListener('click', () => {
                const targetId = button.getAttribute('data-sidebar-section-target');
                if (!targetId) {
                    return;
                }

                const section = document.getElementById(targetId);
                if (!section) {
                    return;
                }

                if (section.tagName && section.tagName.toLowerCase() === 'details' && !section.open) {
                    section.open = true;
                }

                try {
                    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
                } catch (error) {
                    section.scrollIntoView();
                }

                const summary = section.querySelector('summary');
                if (summary && typeof summary.focus === 'function') {
                    try {
                        summary.focus({ preventScroll: true });
                    } catch (error) {
                        summary.focus();
                    }
                }
            });
        });
    }

    function initializeUnitControls() {
        const hasUnitControl = !!(window.wp && wp.element && wp.components && wp.components.UnitControl);
        let createElement;
        let useMemo;
        let useState;
        let UnitControl;

        if (hasUnitControl) {
            ({ createElement, useMemo, useState } = wp.element);
            ({ UnitControl } = wp.components);
        }

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

            if (!hasUnitControl) {
                createFallbackUnitControl(container, {
                    valueInput,
                    unitInput,
                    label,
                    helpText,
                    errorMessage,
                    allowedUnits,
                    initialDimension,
                    defaultDimension,
                    defaultUnit,
                });
                return;
            }

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
                    __next40pxDefaultSize: true,
                    __nextHasNoMarginBottom: true,
                });
            };

            wp.element.render(createElement(RangeField), container);
        });
    }

    function renderNotice(type, message, extraOptions) {
        const container = $('#sidebar-jlg-js-notices');
        const $target = container.length ? container : $('.sidebar-jlg-admin-wrap');

        if (!$target || !$target.length) {
            return;
        }

        const baseOptions = {
            autoDismiss: true,
            dismissDelay: 8000,
            maxVisible: 3,
            actions: [],
            message: '',
        };

        let resolvedMessage = typeof message === 'string' ? message : '';
        let options = $.extend(true, {}, baseOptions);

        if (message && typeof message === 'object' && !Array.isArray(message)) {
            options = $.extend(true, options, message);
            if (typeof message.message === 'string') {
                resolvedMessage = message.message;
            }
        }

        if (extraOptions && typeof extraOptions === 'object') {
            options = $.extend(true, options, extraOptions);
        }

        if (typeof options.message === 'string' && options.message) {
            resolvedMessage = options.message;
        }

        options.message = resolvedMessage;

        const hasExplicitAutoDismiss = (
            message && typeof message === 'object' && !Array.isArray(message) && Object.prototype.hasOwnProperty.call(message, 'autoDismiss')
        ) || (
            extraOptions && typeof extraOptions === 'object' && Object.prototype.hasOwnProperty.call(extraOptions, 'autoDismiss')
        );

        const tone = typeof type === 'string' ? type.toLowerCase() : 'info';
        const toneClassMap = {
            success: 'sidebar-jlg-toast--success',
            error: 'sidebar-jlg-toast--error',
            warning: 'sidebar-jlg-toast--warning',
            info: 'sidebar-jlg-toast--info'
        };

        const iconMap = {
            success: '✓',
            error: '!',
            warning: '!',
            info: 'i'
        };

        const toneLabels = {
            success: getI18nString('noticeToneSuccess', 'Succès'),
            error: getI18nString('noticeToneError', 'Erreur'),
            warning: getI18nString('noticeToneWarning', 'Avertissement'),
            info: getI18nString('noticeToneInfo', 'Information'),
        };

        const toneClass = toneClassMap[tone] || toneClassMap.info;
        const iconLabel = iconMap[tone] || iconMap.info;
        const toneLabel = toneLabels[tone] || toneLabels.info;
        const dismissText = getI18nString('dismissNotice', 'Ignorer cette notification.');

        if (tone === 'error' && !hasExplicitAutoDismiss) {
            options.autoDismiss = false;
        }

        const noticeId = `sidebar-jlg-toast-${Date.now()}-${Math.floor(Math.random() * 1000)}`;
        const $notice = $('<section/>', {
            class: `sidebar-jlg-toast ${toneClass}`,
            id: noticeId,
            role: 'status',
            'aria-live': 'polite',
            'data-sidebar-toast': 'true',
            'data-autodismiss': options.autoDismiss ? 'true' : 'false',
        });

        const $icon = $('<span/>', {
            class: 'sidebar-jlg-toast__icon',
            'aria-hidden': 'true',
        }).text(iconLabel);

        const $body = $('<div/>', { class: 'sidebar-jlg-toast__body' });
        const $message = $('<p/>', { class: 'sidebar-jlg-toast__message' });
        $message.text(resolvedMessage || '');

        const $meta = $('<div/>', { class: 'sidebar-jlg-toast__meta' });
        const $toneSr = $('<span/>', { class: 'screen-reader-text sidebar-jlg-toast__tone' });
        $toneSr.text(toneLabel);

        const actions = Array.isArray(options.actions) ? options.actions : [];
        if (actions.length) {
            const $actions = $('<div/>', { class: 'sidebar-jlg-toast__actions' });
            actions.forEach((action) => {
                if (!action || typeof action !== 'object' || typeof action.label !== 'string') {
                    return;
                }

                const isLink = typeof action.href === 'string' && action.href;
                const elementTag = isLink ? '<a/>' : '<button/>';
                const baseAttributes = {
                    class: 'sidebar-jlg-toast__action',
                };

                if (isLink) {
                    baseAttributes.href = action.href;
                    if (action.target) {
                        baseAttributes.target = action.target;
                    }
                    if (action.rel) {
                        baseAttributes.rel = action.rel;
                    }
                } else {
                    baseAttributes.type = 'button';
                }

                const $action = $(elementTag, baseAttributes);
                $action.text(action.label);

                if (!isLink && typeof action.onClick === 'function') {
                    $action.on('click', (event) => {
                        event.preventDefault();
                        action.onClick.call(event.currentTarget, event, { id: noticeId, remove: () => removeNotice($notice, $target) });
                    });
                }

                if (typeof action.description === 'string' && action.description) {
                    $action.attr('aria-label', `${action.label}. ${action.description}`);
                }

                $actions.append($action);
            });

            if ($actions.children().length) {
                $meta.append($actions);
            }
        }

        $body.append($toneSr).append($message);
        if ($meta.children().length) {
            $body.append($meta);
        }

        const $close = $('<button/>', {
            type: 'button',
            class: 'sidebar-jlg-toast__close',
            'aria-label': dismissText,
        });

        const $closeSr = $('<span/>', { class: 'screen-reader-text' });
        $closeSr.text(dismissText);
        $close.append($closeSr);

        const $progress = $('<div/>', { class: 'sidebar-jlg-toast__progress' });
        const $progressBar = $('<div/>', { class: 'sidebar-jlg-toast__progress-bar' });
        $progress.append($progressBar);

        $notice.append($icon, $body, $close, $progress);

        const removeNotice = (currentNotice, host = $target) => {
            if (!currentNotice || !currentNotice.length || currentNotice.hasClass('is-leaving')) {
                return;
            }

            currentNotice.addClass('is-leaving');

            setTimeout(() => {
                currentNotice.remove();
                updateNoticeCounter(host);
            }, 220);
        };

        const updateNoticeCounter = (host = $target) => {
            const count = host.find('[data-sidebar-toast]').length;

            if (host.is('.sidebar-jlg-aria-notices')) {
                host.attr('data-sidebar-notices', String(count));

                if (count <= 0) {
                    host.attr('aria-hidden', 'true');
                } else {
                    host.removeAttr('aria-hidden');
                }
            }
        };

        const maxVisible = Number.isFinite(options.maxVisible) && options.maxVisible > 0
            ? Math.floor(options.maxVisible)
            : baseOptions.maxVisible;

        $target.prepend($notice);
        updateNoticeCounter($target);

        const existing = $target.find('[data-sidebar-toast]');
        if (existing.length > maxVisible) {
            existing.slice(maxVisible).each(function() {
                removeNotice($(this), $target);
            });
        }

        const closeNotice = () => removeNotice($notice, $target);

        $close.on('click', (event) => {
            event.preventDefault();
            closeNotice();
        });

        let remaining = Math.max(1500, parseInt(options.dismissDelay, 10) || baseOptions.dismissDelay);
        let timerId = null;
        let timerStart = null;

        const startTimer = () => {
            if (!options.autoDismiss || timerId !== null) {
                return;
            }

            timerStart = Date.now();
            timerId = window.setTimeout(() => {
                timerId = null;
                closeNotice();
            }, remaining);

            if ($progressBar.length) {
                $progressBar.css('animation-duration', `${remaining}ms`);
                $progressBar.css('animation-play-state', 'running');
            }
        };

        const pauseTimer = () => {
            if (!options.autoDismiss || timerId === null) {
                return;
            }

            clearTimeout(timerId);
            timerId = null;

            if (timerStart !== null) {
                remaining -= Date.now() - timerStart;
            }

            if ($progressBar.length) {
                $progressBar.css('animation-play-state', 'paused');
            }
        };

        const resumeTimer = () => {
            if (!options.autoDismiss) {
                return;
            }

            if ($notice.is(':hover') || $notice.is(':focus-within')) {
                return;
            }

            if (remaining <= 0) {
                closeNotice();
                return;
            }

            startTimer();
        };

        if (options.autoDismiss) {
            $progressBar.css('animation-duration', `${remaining}ms`);
            $progressBar.css('animation-play-state', 'paused');

            if (typeof window !== 'undefined' && typeof window.requestAnimationFrame === 'function') {
                window.requestAnimationFrame(startTimer);
            } else {
                startTimer();
            }

            $notice.on('mouseenter focusin', pauseTimer);
            $notice.on('mouseleave focusout', () => {
                setTimeout(resumeTimer, 10);
            });
        } else {
            $progressBar.css('animation', 'none');
        }
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
    
    function initializeAccessibilityAudit() {
        const globalData = getSidebarGlobalData();
        if (!globalData || !globalData.accessibility_audit) {
            return;
        }

        const config = globalData.accessibility_audit;
        const ajaxUrl = globalData.ajax_url || '';
        const $container = $('#sidebar-jlg-accessibility-audit');

        if (!$container.length) {
            return;
        }

        const $urlInput = $container.find('.sidebar-jlg-accessibility-audit__url');
        const $launchButton = $container.find('.sidebar-jlg-accessibility-audit__launch');
        const $status = $container.find('.sidebar-jlg-accessibility-audit__status');
        const $result = $('#sidebar-jlg-audit-result');
        const defaultUrl = typeof config.default_url === 'string' ? config.default_url : '';
        const buttonDefaultText = $launchButton.length ? $launchButton.text() : '';

        if ($urlInput.length && defaultUrl && typeof $urlInput.val() === 'string' && !$urlInput.val()) {
            $urlInput.val(defaultUrl);
        }

        const typeLabels = {
            error: getI18nString('auditTypeError', 'Erreur'),
            warning: getI18nString('auditTypeWarning', 'Avertissement'),
            notice: getI18nString('auditTypeNotice', 'Notice'),
        };

        const setStatusMessage = (message, tone = '') => {
            if (!$status.length) {
                return;
            }

            const safeMessage = typeof message === 'string' ? message : '';
            $status.text(safeMessage);
            $status.removeClass('is-error is-success is-info');
            if (tone) {
                $status.addClass(`is-${tone}`);
            }
        };

        const renderLog = (logText) => {
            if (!$result.length || !logText) {
                return;
            }

            const summaryLabel = getI18nString('auditLogTitle', 'Sortie technique');
            const expandLabel = getI18nString('auditSeeDetails', 'Afficher les détails');
            const collapseLabel = getI18nString('auditHideDetails', 'Masquer les détails');

            const $details = $('<details/>', { class: 'sidebar-jlg-accessibility-audit__log' });
            const $summary = $('<summary/>');
            const $summaryLabel = $('<span/>', { class: 'sidebar-jlg-accessibility-audit__log-title' });
            $summaryLabel.text(summaryLabel);
            const $toggleLabel = $('<span/>', { class: 'sidebar-jlg-accessibility-audit__log-toggle' });
            $toggleLabel.text(expandLabel);
            $summary.append($summaryLabel).append($toggleLabel);
            $details.append($summary);

            const $pre = $('<pre/>', { class: 'sidebar-jlg-accessibility-audit__log-output' });
            $pre.text(logText);
            $details.append($pre);

            $details.on('toggle', function() {
                const isOpen = $details.prop('open');
                $toggleLabel.text(isOpen ? collapseLabel : expandLabel);
            });

            $result.append($details);
        };

        const renderIssues = (issues) => {
            const $list = $('<ol/>', { class: 'sidebar-jlg-accessibility-audit__issues' });

            issues.forEach((rawIssue) => {
                if (!rawIssue || typeof rawIssue !== 'object') {
                    return;
                }

                const type = typeof rawIssue.type === 'string' ? rawIssue.type : 'notice';
                const message = typeof rawIssue.message === 'string' ? rawIssue.message : '';
                const selector = typeof rawIssue.selector === 'string' ? rawIssue.selector : '';
                const code = typeof rawIssue.code === 'string' ? rawIssue.code : '';
                const context = typeof rawIssue.context === 'string' ? rawIssue.context : '';

                const $item = $('<li/>', { class: `sidebar-jlg-accessibility-audit__issue sidebar-jlg-accessibility-audit__issue--${type}` });
                const $header = $('<div/>', { class: 'sidebar-jlg-accessibility-audit__issue-header' });
                const $type = $('<span/>', { class: 'sidebar-jlg-accessibility-audit__issue-type' });
                $type.text(typeLabels[type] || typeLabels.notice);
                $header.append($type);

                if (message) {
                    const $message = $('<span/>', { class: 'sidebar-jlg-accessibility-audit__issue-message' });
                    $message.text(message);
                    $header.append($message);
                }

                $item.append($header);

                if (code) {
                    const codeLabel = getI18nString('auditIssueCode', 'Code : %s').replace('%s', code);
                    const $code = $('<p/>', { class: 'sidebar-jlg-accessibility-audit__issue-code' });
                    $code.text(codeLabel);
                    $item.append($code);
                }

                if (selector) {
                    const selectorLabel = getI18nString('auditIssueSelector', 'Sélecteur : %s').replace('%s', selector);
                    const $selector = $('<p/>', { class: 'sidebar-jlg-accessibility-audit__issue-selector' });
                    $selector.text(selectorLabel);
                    $item.append($selector);
                }

                if (context) {
                    const contextLabel = getI18nString('auditIssueContext', 'Extrait :');
                    const $contextLabel = $('<p/>', { class: 'sidebar-jlg-accessibility-audit__issue-context-label' });
                    $contextLabel.text(contextLabel);
                    const $context = $('<pre/>', { class: 'sidebar-jlg-accessibility-audit__issue-context' });
                    const $codeBlock = $('<code/>');
                    $codeBlock.text(context);
                    $context.append($codeBlock);
                    $item.append($contextLabel).append($context);
                }

                $list.append($item);
            });

            return $list;
        };

        const renderSuccess = (data) => {
            if (!$result.length) {
                return;
            }

            const summary = data && typeof data.summary === 'object' ? data.summary : {};
            const meta = data && typeof data.meta === 'object' ? data.meta : {};
            const issues = Array.isArray(data && data.issues) ? data.issues : [];
            const errorCount = parseInt(summary.error, 10) || 0;
            const warningCount = parseInt(summary.warning, 10) || 0;
            const noticeCount = parseInt(summary.notice, 10) || 0;
            const summaryTemplate = getI18nString('auditSummaryTemplate', '%1$d erreur(s), %2$d avertissement(s), %3$d notice(s).');
            const summaryText = summaryTemplate
                .replace('%1$d', errorCount)
                .replace('%2$d', warningCount)
                .replace('%3$d', noticeCount);

            $result.removeAttr('hidden').removeClass('has-error').addClass('has-success').empty();

            $('<p/>', { class: 'sidebar-jlg-accessibility-audit__summary' }).text(summaryText).appendTo($result);

            if (meta && typeof meta.page_url === 'string' && meta.page_url) {
                const $linkWrapper = $('<p/>', { class: 'sidebar-jlg-accessibility-audit__meta' });
                const $link = $('<a/>', {
                    href: meta.page_url,
                    target: '_blank',
                    rel: 'noreferrer noopener',
                    class: 'sidebar-jlg-accessibility-audit__link'
                });
                $link.text(meta.page_url);
                $linkWrapper.append($link);
                $result.append($linkWrapper);
            }

            if (meta && typeof meta.document_title === 'string' && meta.document_title) {
                const titleLabel = getI18nString('auditDocumentTitle', 'Titre de page : %s').replace('%s', meta.document_title);
                $('<p/>', { class: 'sidebar-jlg-accessibility-audit__meta' }).text(titleLabel).appendTo($result);
            }

            if (meta && typeof meta.execution_time_ms !== 'undefined') {
                const duration = Number(meta.execution_time_ms);
                if (Number.isFinite(duration) && duration >= 0) {
                    const durationLabel = getI18nString('auditExecutionTime', 'Durée : %s ms').replace('%s', String(Math.round(duration)));
                    $('<p/>', { class: 'sidebar-jlg-accessibility-audit__meta' }).text(durationLabel).appendTo($result);
                }
            }

            if (!issues.length) {
                const successText = getI18nString('auditNoIssues', 'Pa11y n’a détecté aucun problème critique.');
                $('<p/>', { class: 'sidebar-jlg-accessibility-audit__success-message' }).text(successText).appendTo($result);
                if (data && typeof data.log === 'string' && data.log) {
                    renderLog(data.log);
                }
                return;
            }

            const $issuesList = renderIssues(issues);
            if ($issuesList && $issuesList.children().length) {
                $result.append($issuesList);
            }

            if (data && typeof data.log === 'string' && data.log) {
                renderLog(data.log);
            }
        };

        const renderError = (payload, message) => {
            if (!$result.length) {
                return;
            }

            const errorMessage = typeof message === 'string'
                ? message
                : getI18nString('auditGenericError', 'L’audit d’accessibilité a échoué.');

            $result.removeAttr('hidden').removeClass('has-success').addClass('has-error').empty();

            $('<p/>', { class: 'sidebar-jlg-accessibility-audit__error' }).text(errorMessage).appendTo($result);

            if (payload && typeof payload.log === 'string' && payload.log) {
                renderLog(payload.log);
            }
        };

        if (!config.is_available) {
            const unavailableMessage = getI18nString('auditUnavailable', 'Pa11y n’est pas disponible sur ce serveur.');
            setStatusMessage(unavailableMessage, 'error');
            if ($launchButton.length) {
                $launchButton.prop('disabled', true).attr('aria-disabled', 'true');
            }
        }

        if ($launchButton.length) {
            $launchButton.on('click', function() {
                if (!ajaxUrl) {
                    renderNotice('error', getI18nString('auditGenericError', 'L’audit d’accessibilité a échoué.'));
                    return;
                }

                if (!config.nonce) {
                    renderNotice('error', getI18nString('auditGenericError', 'L’audit d’accessibilité a échoué.'));
                    return;
                }

                const rawValue = $urlInput.length && typeof $urlInput.val() === 'string'
                    ? $urlInput.val().trim()
                    : '';

                if (!rawValue) {
                    const missingMessage = getI18nString('auditMissingUrl', 'Veuillez saisir une URL à analyser.');
                    renderNotice('error', missingMessage);
                    setStatusMessage(missingMessage, 'error');
                    return;
                }

                setStatusMessage(getI18nString('auditRunning', 'Audit en cours…'), 'info');
                $launchButton.prop('disabled', true).text(getI18nString('auditRunning', 'Audit en cours…'));
                $container.addClass('is-running');

                if ($result.length) {
                    $result.attr('hidden', 'hidden').removeClass('has-success has-error').empty();
                }

                $.post(ajaxUrl, {
                    action: config.action,
                    nonce: config.nonce,
                    target_url: rawValue
                })
                .done((response) => {
                    if (response && response.success && response.data) {
                        renderSuccess(response.data);
                        setStatusMessage(getI18nString('auditCompleted', 'Audit terminé.'), 'success');
                    } else {
                        const message = getResponseMessage(response, getI18nString('auditGenericError', 'L’audit d’accessibilité a échoué.'));
                        renderError(response ? response.data : null, message);
                        setStatusMessage(message, 'error');
                    }
                })
                .fail((xhr) => {
                    const message = getResponseMessage(xhr ? xhr.responseJSON : null, getI18nString('auditGenericError', 'L’audit d’accessibilité a échoué.'));
                    renderError(xhr ? xhr.responseJSON : null, message);
                    setStatusMessage(message, 'error');
                })
                .always(() => {
                    $container.removeClass('is-running');
                    $launchButton.prop('disabled', !config.is_available).text(buttonDefaultText);
                });
            });
        }
    }

    function initializeAccessibilityChecklist() {
        const containers = $('.sidebar-jlg-accessibility');
        if (!containers.length) {
            return;
        }

        const locale = document.documentElement ? document.documentElement.lang || undefined : undefined;
        const numberFormatter = typeof Intl !== 'undefined' && Intl.NumberFormat
            ? new Intl.NumberFormat(locale, { maximumFractionDigits: 0 })
            : null;

        const formatNumber = (value) => {
            if (typeof value !== 'number' || !Number.isFinite(value)) {
                return '0';
            }

            if (numberFormatter) {
                try {
                    return numberFormatter.format(value);
                } catch (error) {
                    return String(Math.round(value));
                }
            }

            return String(Math.round(value));
        };

        containers.each(function() {
            const $container = $(this);
            const $checkboxes = $container.find('input[type="checkbox"]');
            const totalItemsData = parseInt($container.data('totalItems'), 10);
            const totalItems = Number.isFinite(totalItemsData) && totalItemsData > 0
                ? totalItemsData
                : $checkboxes.length;
            const $status = $container.find('.sidebar-jlg-accessibility__progress-status');
            const $progress = $container.find('.sidebar-jlg-accessibility__progress-meter');
            const template = String($container.data('progressTemplate') || '');
            const ariaTemplate = String($container.data('progressAriaTemplate') || '');

            const formatProgressText = (completed, total, percent) => {
                if (!template) {
                    return `${formatNumber(completed)} / ${formatNumber(total)} (${formatNumber(percent)}%)`;
                }

                return template
                    .replace('%1$s', formatNumber(completed))
                    .replace('%2$s', formatNumber(total))
                    .replace('%3$s', formatNumber(percent));
            };

            const formatAriaLabel = (percent) => {
                if (!ariaTemplate) {
                    return `${formatNumber(percent)}%`;
                }

                return ariaTemplate.replace('%1$s', formatNumber(percent));
            };

            const updateProgress = () => {
                let completed = 0;
                $checkboxes.each(function() {
                    if ($(this).prop('checked')) {
                        completed += 1;
                    }
                });

                const percent = totalItems > 0 ? Math.round((completed / totalItems) * 100) : 0;

                if ($status.length) {
                    $status.text(formatProgressText(completed, totalItems, percent));
                }

                if ($progress.length) {
                    $progress.attr('value', completed);
                    $progress.attr('max', totalItems);
                    $progress.attr('aria-label', formatAriaLabel(percent));
                }
            };

            $checkboxes.on('change', updateProgress);
            updateProgress();
        });
    }

    // --- Gestion des onglets ---
    const tabWrapper = $('.nav-tab-wrapper');
    const tabPanels = $('.tab-content');
    const $searchInput = $('[data-sidebar-settings-search]');
    const $searchStatus = $('#sidebar-jlg-settings-search-status');
    const $searchClear = $('[data-sidebar-search-clear]');
    const guidedUi = {
        root: $('[data-sidebar-guided]'),
        toggle: $('[data-sidebar-guided-toggle]'),
        controls: $('[data-sidebar-guided-controls]'),
        progress: $('[data-sidebar-guided-progress]'),
        title: $('[data-sidebar-guided-title]'),
        prev: $('[data-sidebar-guided-prev]'),
        next: $('[data-sidebar-guided-next]'),
        exit: $('[data-sidebar-guided-exit]'),
    };
    let currentSearchQuery = '';
    const guidedState = {
        isActive: false,
        index: 0,
    };

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

        handleTabActivation(panelId, $panel);

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

    function getActivePanel() {
        return tabPanels.filter('.active').first();
    }

    function getFilterableElements($panel) {
        if (!$panel || !$panel.length) {
            return $();
        }

        return $panel.find('[data-sidebar-filterable]');
    }

    function updateSearchControls() {
        const hasQuery = currentSearchQuery.length > 0;

        if ($searchClear.length) {
            $searchClear.prop('disabled', !hasQuery || guidedState.isActive);
        }

        if ($searchInput.length) {
            $searchInput.prop('disabled', guidedState.isActive);
            const $searchContainer = $searchInput.closest('.sidebar-jlg-command-bar__search');
            $searchContainer.toggleClass('is-disabled', guidedState.isActive);
        }
    }

    function resetFilterStateForAllPanels() {
        tabPanels.each(function() {
            const $panel = $(this);
            $panel.removeClass('is-searching');
            $panel.find('[data-sidebar-filterable]').each(function() {
                const $element = $(this);
                $element.removeClass('is-filter-hidden');
                $element.attr('aria-hidden', 'false');
            });
        });

        syncExperienceModeVisibility();
    }

    function applySearchFilter(rawQuery) {
        const query = typeof rawQuery === 'string' ? rawQuery.trim().toLowerCase() : '';
        currentSearchQuery = query;

        if (!query) {
            resetFilterStateForAllPanels();
            if ($searchStatus.length) {
                $searchStatus.text('');
            }
            updateSearchControls();
            syncExperienceModeVisibility();
            return;
        }

        const $panel = getActivePanel();
        const $elements = getFilterableElements($panel);

        if (!$panel.length || !$elements.length) {
            if ($searchStatus.length) {
                $searchStatus.text(getI18nString('searchNoTarget', 'Aucun réglage filtrable sur cet onglet.'));
            }
            updateSearchControls();
            return;
        }

        $panel.addClass('is-searching');
        let matchCount = 0;

        $elements.each(function() {
            const $element = $(this);
            if (!$element.data('sidebarFilterText')) {
                const label = typeof $element.attr('data-sidebar-filter-label') === 'string'
                    ? $element.attr('data-sidebar-filter-label')
                    : '';
                const keywords = typeof $element.attr('data-sidebar-filter-keywords') === 'string'
                    ? $element.attr('data-sidebar-filter-keywords')
                    : '';
                const textContent = ($element.text() || '').replace(/\s+/g, ' ');
                $element.data('sidebarFilterText', `${label} ${keywords} ${textContent}`.toLowerCase());
            }

            const haystack = $element.data('sidebarFilterText');
            const isMatch = typeof haystack === 'string' && haystack.includes(query);
            $element.toggleClass('is-filter-hidden', !isMatch);
            $element.attr('aria-hidden', isMatch ? 'false' : 'true');

            if ($element.is('details')) {
                if (isMatch) {
                    $element.attr('open', 'open');
                } else {
                    $element.removeAttr('open');
                }
            }

            if (isMatch) {
                matchCount += 1;
            }
        });

        if ($searchStatus.length) {
            if (matchCount > 0) {
                const template = getI18nString('searchResultsCount', '%d sections affichées.');
                $searchStatus.text(template.replace('%d', matchCount));
            } else {
                $searchStatus.text(getI18nString('searchNoResults', 'Aucun résultat ne correspond aux mots-clés saisis.'));
            }
        }

        updateSearchControls();
        syncExperienceModeVisibility();
    }

    function clearSearch() {
        if ($searchInput.length) {
            $searchInput.val('');
        }
        applySearchFilter('');
    }

    function scrollSectionIntoView($section) {
        if (!$section || !$section.length || typeof $section[0].scrollIntoView !== 'function') {
            return;
        }

        const prefersReducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        try {
            $section[0].scrollIntoView({
                behavior: prefersReducedMotion ? 'auto' : 'smooth',
                block: 'start',
            });
        } catch (error) {
            $section[0].scrollIntoView(prefersReducedMotion ? false : true);
        }
    }

    function getGuidedPanel() {
        return $('#tab-general');
    }

    function getGuidedSections() {
        const $panel = getGuidedPanel();
        if (!$panel.length) {
            return $();
        }

        return $panel.find('[data-sidebar-guided-step]');
    }

    function setGuidedToggleLabel() {
        if (!guidedUi.toggle.length) {
            return;
        }

        const enableLabel = guidedUi.toggle.attr('data-label-enable') || guidedUi.toggle.text();
        const disableLabel = guidedUi.toggle.attr('data-label-disable') || enableLabel;
        guidedUi.toggle.text(guidedState.isActive ? disableLabel : enableLabel);
        guidedUi.toggle.attr('aria-pressed', guidedState.isActive ? 'true' : 'false');
    }

    function updateGuidedButtons(totalSteps) {
        const steps = typeof totalSteps === 'number' ? totalSteps : getGuidedSections().length;

        if (guidedUi.prev.length) {
            guidedUi.prev.prop('disabled', !guidedState.isActive || guidedState.index <= 0);
        }

        if (guidedUi.next.length) {
            const isLast = guidedState.index >= steps - 1;
            const nextLabel = isLast
                ? getI18nString('guidedModeFinish', 'Terminer')
                : getI18nString('guidedModeNext', 'Suivant');
            guidedUi.next.text(nextLabel);
        }
    }

    function syncGuidedUi() {
        const $panel = getGuidedPanel();
        const $sections = getGuidedSections();

        if (!guidedState.isActive) {
            if ($panel.length) {
                $panel.removeClass('is-guided-mode');
            }

            $sections.each(function() {
                const $section = $(this);
                $section.removeClass('is-guided-active is-filter-hidden');
                $section.attr('aria-hidden', 'false');
                if ($section.is('details')) {
                    $section.removeAttr('open');
                }
            });

            if (guidedUi.controls.length) {
                guidedUi.controls.attr('hidden', 'hidden');
            }

            if (guidedUi.progress.length) {
                guidedUi.progress.text('');
            }

            if (guidedUi.title.length) {
                guidedUi.title.text('');
            }

            guidedUi.root.attr('data-guided-active', 'false');
            setGuidedToggleLabel();
            updateGuidedButtons($sections.length);
            updateSearchControls();
            return;
        }

        if (!$sections.length) {
            exitGuidedMode();
            return;
        }

        if ($panel.length) {
            $panel.addClass('is-guided-mode');
        }

        const total = $sections.length;
        if (guidedState.index >= total) {
            guidedState.index = total - 1;
        }
        if (guidedState.index < 0) {
            guidedState.index = 0;
        }

        let $current = $();
        $sections.each(function(stepIndex) {
            const $section = $(this);
            const isActive = stepIndex === guidedState.index;
            $section.toggleClass('is-guided-active', isActive);
            $section.removeClass('is-filter-hidden');
            $section.attr('aria-hidden', isActive ? 'false' : 'true');

            if ($section.is('details')) {
                if (isActive) {
                    $section.attr('open', 'open');
                } else {
                    $section.removeAttr('open');
                }
            }

            if (isActive) {
                $current = $section;
            }
        });

        const title = $current.attr('data-sidebar-guided-title')
            || $current.find('.sidebar-jlg-section__title').text()
            || '';
        const progressTemplate = getI18nString('guidedModeStepLabel', 'Étape %1$s sur %2$s');

        if (guidedUi.progress.length) {
            guidedUi.progress.text(
                progressTemplate.replace('%1$s', guidedState.index + 1).replace('%2$s', total)
            );
        }

        if (guidedUi.title.length) {
            guidedUi.title.text(title);
        }

        if (guidedUi.controls.length) {
            guidedUi.controls.removeAttr('hidden');
        }

        guidedUi.root.attr('data-guided-active', 'true');
        setGuidedToggleLabel();
        updateGuidedButtons(total);
        updateSearchControls();

        if ($current.length) {
            window.setTimeout(() => {
                scrollSectionIntoView($current);
            }, 100);
        }
    }

    function refreshGuidedAvailability() {
        const hasGuided = getGuidedSections().length > 0;
        if (guidedUi.toggle.length) {
            guidedUi.toggle.prop('disabled', !hasGuided);
        }
        guidedUi.root.attr('data-guided-available', hasGuided ? 'true' : 'false');

        if (!hasGuided && guidedState.isActive) {
            guidedState.isActive = false;
            syncGuidedUi();
        }
    }

    function enterGuidedMode() {
        const $generalTab = $('#tab-general-tab');
        if ($generalTab.length) {
            activateTab($generalTab, false);
        }

        guidedState.isActive = true;
        guidedState.index = 0;
        clearSearch();
        syncGuidedUi();
    }

    function exitGuidedMode() {
        if (!guidedState.isActive) {
            return;
        }

        guidedState.isActive = false;
        syncGuidedUi();
    }

    function handleTabActivation(panelId, $panel) {
        if (guidedState.isActive && panelId !== 'tab-general') {
            exitGuidedMode();
        } else if (guidedState.isActive) {
            syncGuidedUi();
        }

        applySearchFilter(currentSearchQuery);

        if ($panel && $panel.length && $panel.is('#tab-general')) {
            refreshGuidedAvailability();
        }
    }

    if ($searchInput.length) {
        $searchInput.on('input', function() {
            if (guidedState.isActive) {
                exitGuidedMode();
            }

            const value = $(this).val();
            applySearchFilter(typeof value === 'string' ? value : '');
        });

        $searchInput.on('keydown', function(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                clearSearch();
            }
        });
    }

    if ($searchClear.length) {
        $searchClear.on('click', function() {
            clearSearch();
            if ($searchInput.length) {
                $searchInput.trigger('focus');
            }
        });
    }

    if (guidedUi.toggle.length) {
        guidedUi.toggle.on('click', function() {
            if (guidedState.isActive) {
                exitGuidedMode();
            } else {
                enterGuidedMode();
            }
        });
    }

    if (guidedUi.prev.length) {
        guidedUi.prev.on('click', function() {
            if (!guidedState.isActive) {
                return;
            }

            if (guidedState.index > 0) {
                guidedState.index -= 1;
                syncGuidedUi();
            }
        });
    }

    if (guidedUi.next.length) {
        guidedUi.next.on('click', function() {
            if (!guidedState.isActive) {
                enterGuidedMode();
                return;
            }

            const total = getGuidedSections().length;
            if (guidedState.index >= total - 1) {
                exitGuidedMode();
            } else {
                guidedState.index += 1;
                syncGuidedUi();
            }
        });
    }

    if (guidedUi.exit.length) {
        guidedUi.exit.on('click', function() {
            exitGuidedMode();
            if ($searchInput.length) {
                $searchInput.trigger('focus');
            }
        });
    }

    refreshGuidedAvailability();
    setGuidedToggleLabel();
    applySearchFilter('');

    const initialTab = getTabs().filter('.nav-tab-active').first();
    if (initialTab.length) {
        activateTab(initialTab, false);
    } else {
        activateTab(getTabs().first(), false);
    }


    // --- Gestion des profils ---
    const $profilesApp = $('#sidebar-jlg-profiles-app');
    const $profilesList = $('#sidebar-jlg-profiles-list');
    const $profilesHidden = $('#sidebar-jlg-profiles-hidden');
    const $profileEditor = $('#sidebar-jlg-profile-editor');
    const $profileTitle = $('#sidebar-jlg-profile-title');
    const $profileSlug = $('#sidebar-jlg-profile-slug');
    const $profilePriority = $('#sidebar-jlg-profile-priority');
    const $profileEnabled = $('#sidebar-jlg-profile-enabled');
    const $profilePostTypes = $('#sidebar-jlg-profile-post-types');
    const $profileRoles = $('#sidebar-jlg-profile-roles');
    const $profileLanguages = $('#sidebar-jlg-profile-languages');
    const $profileDevices = $('#sidebar-jlg-profile-devices');
    const $profileLoginState = $('#sidebar-jlg-profile-login-state');
    const $profileScheduleStart = $('#sidebar-jlg-profile-schedule-start');
    const $profileScheduleEnd = $('#sidebar-jlg-profile-schedule-end');
    const $profileScheduleDays = $('#sidebar-jlg-profile-schedule-days');
    const $profileTaxonomiesContainer = $('#sidebar-jlg-profile-taxonomies');
    const $profileAddTaxonomy = $('#sidebar-jlg-profile-add-taxonomy');
    const $profileSettingsSummary = $('#sidebar-jlg-profile-settings-summary');
    const $profileCloneButton = $('#sidebar-jlg-profile-clone-settings');
    const $profileClearSettings = $('#sidebar-jlg-profile-clear-settings');
    const $clearActiveButton = $('#sidebar-jlg-profiles-clear-active');
    const $activeProfileField = $('#sidebar-jlg-active-profile-field');
    const taxonomyTemplate = document.getElementById('sidebar-jlg-profile-taxonomy-template');

    if ($profilesApp.length) {
        initializeProfiles();
    }

    function initializeProfiles() {
        const rawProfiles = Array.isArray(sidebarJLG.profiles) ? sidebarJLG.profiles : [];
        profilesState.profiles = buildInitialProfiles(rawProfiles);

        const activeCandidate = typeof sidebarJLG.active_profile === 'string' ? sidebarJLG.active_profile : '';
        profilesState.activeId = profilesState.profiles.some((profile) => profile.id === activeCandidate)
            ? activeCandidate
            : '';

        profilesState.selectedId = profilesState.profiles.length ? profilesState.profiles[0].id : '';

        renderProfilesList();
        renderProfileEditor();
        setupProfilesSortable();
        bindProfileEvents();
        updateActiveProfileField();
        updateActiveProfileStatusMessage();
        syncProfilesToInputs();
    }

    function buildInitialProfiles(rawProfiles) {
        const reserved = new Set();
        const normalizedProfiles = [];

        rawProfiles.forEach((rawProfile, index) => {
            const normalized = normalizeExistingProfile(rawProfile, reserved, index);
            normalizedProfiles.push(normalized);
            reserved.add(normalized.id);
        });

        return normalizedProfiles;
    }

    function normalizeExistingProfile(rawProfile, reservedIds, index) {
        const source = rawProfile && typeof rawProfile === 'object' ? rawProfile : {};
        const baseIdentifier = sanitizeProfileSlug(
            source.id || source.slug || source.key || ''
        );
        const fallbackBase = baseIdentifier !== '' ? baseIdentifier : `profil-${index + 1}`;
        const id = generateUniqueProfileId(fallbackBase, reservedIds);

        const title = sanitizeText(source.title || source.name || '');
        const enabled = normalizeBoolean(
            source.enabled ?? source.is_enabled ?? source.active ?? source.is_active ?? true,
            true
        );

        let priority = 0;
        if (typeof source.priority === 'number' || typeof source.priority === 'string') {
            const parsedPriority = parseInt(source.priority, 10);
            if (Number.isFinite(parsedPriority)) {
                priority = parsedPriority;
            }
        } else if (typeof source.order === 'number' || typeof source.order === 'string') {
            const parsedOrder = parseInt(source.order, 10);
            if (Number.isFinite(parsedOrder)) {
                priority = parsedOrder;
            }
        }

        return {
            id,
            title,
            enabled,
            priority,
            conditions: normalizeProfileConditions(source.conditions),
            settings: normalizeProfileSettings(source.settings),
        };
    }

    function normalizeProfileConditions(rawConditions) {
        const conditions = rawConditions && typeof rawConditions === 'object' ? rawConditions : {};

        const postTypes = Array.isArray(conditions.post_types)
            ? conditions.post_types
            : (Array.isArray(conditions.content_types) ? conditions.content_types : []);

        return {
            post_types: normalizeStringArray(postTypes),
            roles: normalizeStringArray(conditions.roles),
            languages: normalizeLanguageArray(conditions.languages),
            taxonomies: normalizeTaxonomyCollection(conditions.taxonomies),
            devices: normalizeDeviceArray(conditions.devices),
            logged_in: normalizeLoginState(conditions.logged_in),
            schedule: normalizeScheduleObject(conditions.schedule),
        };
    }

    function normalizeProfileSettings(rawSettings) {
        if (!rawSettings || typeof rawSettings !== 'object') {
            return {};
        }

        const cloned = SidebarPreviewModule.cloneObject(rawSettings) || {};
        if (cloned && typeof cloned === 'object' && Object.prototype.hasOwnProperty.call(cloned, 'profiles')) {
            delete cloned.profiles;
        }

        return cloned;
    }

    function normalizeProfileChoices(choices) {
        const fallback = {
            post_types: [],
            taxonomies: [],
            roles: [],
            languages: [],
            devices: [],
            login_states: [],
            schedule_days: [],
        };
        if (!choices || typeof choices !== 'object') {
            return fallback;
        }

        const normalizeGroup = (items) => {
            if (!Array.isArray(items)) {
                return [];
            }

            return items.reduce((accumulator, item) => {
                if (!item || typeof item !== 'object') {
                    return accumulator;
                }

                const value = typeof item.value === 'string' ? item.value : '';
                if (value === '') {
                    return accumulator;
                }

                const label = typeof item.label === 'string' && item.label !== ''
                    ? item.label
                    : value;

                accumulator.push({ value, label });
                return accumulator;
            }, []);
        };

        return {
            post_types: normalizeGroup(choices.post_types),
            taxonomies: normalizeGroup(choices.taxonomies),
            roles: normalizeGroup(choices.roles),
            languages: normalizeGroup(choices.languages),
            devices: normalizeGroup(choices.devices),
            login_states: normalizeGroup(choices.login_states),
            schedule_days: normalizeGroup(choices.schedule_days),
        };
    }

    function normalizeStringArray(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        const normalized = [];
        values.forEach((value) => {
            if (typeof value !== 'string' && typeof value !== 'number') {
                return;
            }
            const slug = sanitizeProfileSlug(String(value));
            if (slug && !normalized.includes(slug)) {
                normalized.push(slug);
            }
        });

        return normalized;
    }

    function normalizeLanguageArray(values) {
        if (!Array.isArray(values)) {
            return [];
        }

        const normalized = [];
        values.forEach((value) => {
            if (typeof value !== 'string' && typeof value !== 'number') {
                return;
            }

            const stringValue = String(value).trim();
            if (stringValue !== '' && !normalized.includes(stringValue)) {
                normalized.push(stringValue);
            }
        });

        return normalized;
    }

    function normalizeDeviceArray(values) {
        const allowed = ['desktop', 'mobile'];
        const list = Array.isArray(values) ? values : [];
        const normalized = [];

        list.forEach((value) => {
            if (typeof value !== 'string' && typeof value !== 'number') {
                return;
            }

            const slug = sanitizeProfileSlug(String(value));
            if (slug && allowed.includes(slug) && !normalized.includes(slug)) {
                normalized.push(slug);
            }
        });

        return normalized;
    }

    function normalizeLoginState(value) {
        if (Array.isArray(value)) {
            return normalizeLoginState(value[0]);
        }

        if (value === null || value === undefined) {
            return 'any';
        }

        if (typeof value === 'boolean') {
            return value ? 'logged-in' : 'logged-out';
        }

        if (typeof value === 'number') {
            return value !== 0 ? 'logged-in' : 'logged-out';
        }

        if (typeof value !== 'string') {
            return 'any';
        }

        const normalized = value.trim().toLowerCase();
        if (normalized === '' || normalized === 'any') {
            return 'any';
        }

        if (['1', 'true', 'yes', 'on', 'logged-in', 'logged_in'].includes(normalized)) {
            return 'logged-in';
        }

        if (['0', 'false', 'no', 'off', 'logged-out', 'logged_out'].includes(normalized)) {
            return 'logged-out';
        }

        return 'any';
    }

    function normalizeScheduleObject(rawValue) {
        const value = rawValue && typeof rawValue === 'object' ? rawValue : {};

        return {
            start: normalizeScheduleTime(value.start ?? value.from),
            end: normalizeScheduleTime(value.end ?? value.to),
            days: normalizeScheduleDays(value.days),
        };
    }

    function normalizeScheduleTime(value) {
        if (value === null || value === undefined || value === '') {
            return '';
        }

        const stringValue = String(value).trim();
        if (stringValue === '') {
            return '';
        }

        const match = stringValue.match(/^(\d{1,2}):(\d{2})$/);
        if (!match) {
            return '';
        }

        const hour = parseInt(match[1], 10);
        const minute = parseInt(match[2], 10);

        if (!Number.isFinite(hour) || !Number.isFinite(minute) || hour < 0 || hour > 23 || minute < 0 || minute > 59) {
            return '';
        }

        return `${hour.toString().padStart(2, '0')}:${minute.toString().padStart(2, '0')}`;
    }

    function normalizeScheduleDays(value) {
        const allowed = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        let values = [];

        if (Array.isArray(value)) {
            values = value;
        } else if (typeof value === 'string' || typeof value === 'number') {
            values = String(value).split(/[\s,]+/);
        }

        const normalized = [];

        values.forEach((entry) => {
            if (typeof entry !== 'string' && typeof entry !== 'number') {
                return;
            }

            let candidate = String(entry).trim().toLowerCase();
            if (candidate === '') {
                return;
            }

            switch (candidate) {
                case '1':
                case '01':
                case 'monday':
                case 'mon':
                    candidate = 'mon';
                    break;
                case '2':
                case '02':
                case 'tuesday':
                case 'tue':
                    candidate = 'tue';
                    break;
                case '3':
                case '03':
                case 'wednesday':
                case 'wed':
                    candidate = 'wed';
                    break;
                case '4':
                case '04':
                case 'thursday':
                case 'thu':
                    candidate = 'thu';
                    break;
                case '5':
                case '05':
                case 'friday':
                case 'fri':
                    candidate = 'fri';
                    break;
                case '6':
                case '06':
                case 'saturday':
                case 'sat':
                    candidate = 'sat';
                    break;
                case '0':
                case '00':
                case '7':
                case '07':
                case 'sunday':
                case 'sun':
                    candidate = 'sun';
                    break;
            }

            if (!allowed.includes(candidate) || normalized.includes(candidate)) {
                return;
            }

            normalized.push(candidate);
        });

        return normalized;
    }

    function normalizeTaxonomyCollection(rawTaxonomies) {
        if (!Array.isArray(rawTaxonomies)) {
            return [];
        }

        const normalized = [];
        rawTaxonomies.forEach((entry) => {
            if (!entry) {
                return;
            }

            if (typeof entry === 'string' || typeof entry === 'number') {
                const taxonomy = sanitizeProfileSlug(String(entry));
                if (!taxonomy) {
                    return;
                }

                normalized.push({ taxonomy, terms: [] });
                return;
            }

            if (typeof entry === 'object') {
                const taxonomy = sanitizeProfileSlug(entry.taxonomy || entry.name || '');
                if (!taxonomy) {
                    return;
                }

                normalized.push({
                    taxonomy,
                    terms: normalizeTaxonomyTerms(entry.terms),
                });
            }
        });

        return normalized;
    }

    function normalizeTaxonomyTerms(rawTerms) {
        const normalized = [];

        if (Array.isArray(rawTerms)) {
            rawTerms.forEach((term) => {
                const normalizedValue = normalizeTermValue(term);
                if (normalizedValue !== null && !normalized.includes(normalizedValue)) {
                    normalized.push(normalizedValue);
                }
            });
        } else if (typeof rawTerms === 'string') {
            rawTerms.split(',').forEach((segment) => {
                const normalizedValue = normalizeTermValue(segment);
                if (normalizedValue !== null && !normalized.includes(normalizedValue)) {
                    normalized.push(normalizedValue);
                }
            });
        } else if (typeof rawTerms === 'number') {
            const normalizedValue = normalizeTermValue(rawTerms);
            if (normalizedValue !== null && !normalized.includes(normalizedValue)) {
                normalized.push(normalizedValue);
            }
        }

        return normalized;
    }

    function normalizeTermValue(term) {
        if (typeof term === 'number') {
            const numeric = Math.abs(parseInt(term, 10));
            return Number.isFinite(numeric) && numeric > 0 ? String(numeric) : null;
        }

        if (typeof term === 'string') {
            const trimmed = term.trim();
            if (trimmed === '') {
                return null;
            }

            if (/^-?\d+$/.test(trimmed)) {
                const numeric = Math.abs(parseInt(trimmed, 10));
                return Number.isFinite(numeric) && numeric > 0 ? String(numeric) : null;
            }

            const slug = sanitizeProfileSlug(trimmed);
            return slug || null;
        }

        return null;
    }

    function sanitizeProfileSlug(value) {
        if (typeof value !== 'string') {
            if (value === null || value === undefined) {
                return '';
            }

            value = String(value);
        }

        const normalized = value
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9-_]/g, '-')
            .replace(/-{2,}/g, '-')
            .replace(/^-+|-+$/g, '');

        return normalized;
    }

    function sanitizeText(value) {
        if (typeof value !== 'string') {
            if (value === null || value === undefined) {
                return '';
            }

            value = String(value);
        }

        return value.trim();
    }

    function normalizeBoolean(value, fallback) {
        if (typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            return value !== 0;
        }

        if (typeof value === 'string') {
            const normalized = value.trim().toLowerCase();
            if (normalized === '') {
                return fallback;
            }

            return !['0', 'false', 'no', 'off', 'inactive', 'disabled'].includes(normalized);
        }

        if (value === null || value === undefined) {
            return fallback;
        }

        return Boolean(value);
    }

    function generateUniqueProfileId(base, reservedIds = null) {
        const sanitizedBase = sanitizeProfileSlug(base) || 'profil';
        const taken = reservedIds instanceof Set
            ? new Set(Array.from(reservedIds))
            : new Set(profilesState.profiles.map((profile) => profile.id));

        if (!taken.has(sanitizedBase)) {
            return sanitizedBase;
        }

        let counter = 2;
        let candidate = `${sanitizedBase}-${counter}`;
        while (taken.has(candidate)) {
            counter += 1;
            candidate = `${sanitizedBase}-${counter}`;
        }

        return candidate;
    }

    function getProfileById(id) {
        if (!id) {
            return null;
        }

        return profilesState.profiles.find((profile) => profile.id === id) || null;
    }

    function getSelectedProfile() {
        return getProfileById(profilesState.selectedId);
    }

    function getProfileLabel(profile) {
        const label = sanitizeText(profile.title);
        return label !== '' ? label : profile.id;
    }

    function renderProfilesList() {
        if (!$profilesList.length) {
            return;
        }

        if (!getProfileById(profilesState.selectedId) && profilesState.profiles.length) {
            profilesState.selectedId = profilesState.profiles[0].id;
        }

        $profilesList.empty();

        if (profilesState.profiles.length === 0) {
            const emptyMessage = getI18nString('profilesListEmpty', 'Aucun profil n’a encore été créé.');
            const $emptyItem = $('<li>', {
                class: 'sidebar-jlg-profiles__empty',
                text: emptyMessage,
            });
            $profilesList.append($emptyItem);
            return;
        }

        profilesState.profiles.forEach((profile) => {
            const isSelected = profile.id === profilesState.selectedId;
            const isActive = profile.id === profilesState.activeId;

            const $item = $('<li>', {
                class: `sidebar-jlg-profiles__item${isSelected ? ' is-active' : ''}${profile.enabled ? '' : ' is-disabled'}`,
                'data-profile-id': profile.id,
            });

            const $handle = $('<span>', {
                class: 'sidebar-jlg-profiles__handle',
                text: '⋮⋮',
                'aria-hidden': 'true',
            });

            const $select = $('<button type="button" class="sidebar-jlg-profiles__select"></button>');
            $select.text(getProfileLabel(profile));
            $select.attr('data-profile-id', profile.id);
            $select.attr('aria-pressed', isSelected ? 'true' : 'false');

            const $status = $('<span class="sidebar-jlg-profiles__status"></span>');
            if (!profile.enabled) {
                $status.text(getI18nString('profilesInactiveBadge', 'Profil désactivé'));
            }

            const $activeWrapper = $('<label class="sidebar-jlg-profiles__active"></label>');
            const $radio = $('<input type="radio" class="sidebar-jlg-profiles__active-input" name="sidebar-jlg-profile-active">');
            $radio.val(profile.id);
            $radio.prop('checked', isActive);
            $activeWrapper.append($radio);
            $activeWrapper.append($('<span></span>').text(getI18nString('profilesActiveLabel', 'Profil actif')));

            const $delete = $('<button type="button" class="button-link sidebar-jlg-profiles__delete"></button>');
            $delete.text(getI18nString('profilesDeleteLabel', 'Supprimer'));

            $item.append($handle, $select);
            if ($status.text()) {
                $item.append($status);
            }
            $item.append($activeWrapper, $delete);

            $profilesList.append($item);
        });
    }

    function renderProfileEditor() {
        if (!$profileEditor.length) {
            return;
        }

        const profile = getSelectedProfile();

        if (!profile) {
            $profileEditor.addClass('is-empty');
            $profileEditor.attr('aria-hidden', 'true');
            $profileEditor.find('input, select, button').prop('disabled', true);
            if ($profileSettingsSummary.length) {
                $profileSettingsSummary.text('');
            }
            return;
        }

        $profileEditor.removeClass('is-empty');
        $profileEditor.attr('aria-hidden', 'false');
        $profileEditor.find('input, select, button').prop('disabled', false);

        $profileTitle.val(profile.title);
        $profileSlug.val(profile.id);
        $profilePriority.val(profile.priority);
        $profileEnabled.prop('checked', profile.enabled);

        renderSelectOptions($profilePostTypes, profileChoices.post_types, profile.conditions.post_types);
        renderSelectOptions($profileRoles, profileChoices.roles, profile.conditions.roles);
        renderSelectOptions($profileLanguages, profileChoices.languages, profile.conditions.languages);
        renderSelectOptions($profileDevices, profileChoices.devices, profile.conditions.devices);
        renderSelectOptions(
            $profileLoginState,
            profileChoices.login_states,
            profile.conditions.logged_in && profile.conditions.logged_in !== 'any'
                ? [profile.conditions.logged_in]
                : ['any']
        );
        renderSelectOptions($profileScheduleDays, profileChoices.schedule_days, profile.conditions.schedule.days);

        if ($profileScheduleStart.length) {
            $profileScheduleStart.val(profile.conditions.schedule.start || '');
        }

        if ($profileScheduleEnd.length) {
            $profileScheduleEnd.val(profile.conditions.schedule.end || '');
        }

        renderTaxonomyRows(profile);
        updateSettingsSummary(profile);
    }

    function renderSelectOptions($select, choices, selectedValues) {
        if (!$select || !$select.length) {
            return;
        }

        const values = Array.isArray(selectedValues) ? selectedValues.map((value) => String(value)) : [];
        $select.empty();

        choices.forEach((choice) => {
            const value = typeof choice.value === 'string' ? choice.value : '';
            if (value === '') {
                return;
            }

            const label = typeof choice.label === 'string' && choice.label !== '' ? choice.label : value;
            const $option = $('<option></option>').attr('value', value).text(label);
            if (values.includes(value)) {
                $option.prop('selected', true);
            }
            $select.append($option);
        });
    }

    function renderTaxonomyRows(profile) {
        if (!$profileTaxonomiesContainer.length) {
            return;
        }

        const taxonomies = Array.isArray(profile.conditions.taxonomies) ? profile.conditions.taxonomies : [];
        $profileTaxonomiesContainer.empty();

        if (!taxonomies.length) {
            const placeholder = $('<p>', {
                class: 'description sidebar-jlg-profiles__taxonomy-placeholder',
                text: getI18nString('profilesConditionsDescription', 'Définissez les règles qui activent ce profil.'),
            });
            $profileTaxonomiesContainer.append(placeholder);
            return;
        }

        taxonomies.forEach((taxonomy, index) => {
            const $row = createTaxonomyRow(taxonomy, index);
            $profileTaxonomiesContainer.append($row);
        });
    }

    function createTaxonomyRow(taxonomy, index) {
        let $row;
        if (taxonomyTemplate && typeof taxonomyTemplate.innerHTML === 'string') {
            $row = $(taxonomyTemplate.innerHTML.trim());
        } else {
            $row = $('<div class="sidebar-jlg-profile-taxonomy-row"></div>');
            const $select = $('<select class="sidebar-jlg-profile-taxonomy-name"></select>');
            const $terms = $('<input type="text" class="sidebar-jlg-profile-taxonomy-terms">');
            $terms.attr('placeholder', getI18nString('profilesTaxonomyTermsPlaceholder', 'Slugs ou IDs séparés par des virgules'));
            const $remove = $('<button type="button" class="button-link sidebar-jlg-profile-remove-taxonomy"></button>');
            $remove.text(getI18nString('profilesDeleteLabel', 'Supprimer'));
            $row.append($select, $terms, $remove);
        }

        const $selectElement = $row.find('.sidebar-jlg-profile-taxonomy-name');
        renderSelectOptions($selectElement, profileChoices.taxonomies, [taxonomy.taxonomy]);
        $selectElement.attr('data-index', index);
        $selectElement.data('taxonomyIndex', index);

        const $termsElement = $row.find('.sidebar-jlg-profile-taxonomy-terms');
        $termsElement.val(taxonomy.terms.join(', '));
        $termsElement.attr('data-index', index);
        $termsElement.data('taxonomyIndex', index);

        const $removeButton = $row.find('.sidebar-jlg-profile-remove-taxonomy');
        $removeButton.attr('data-index', index);
        $removeButton.data('taxonomyIndex', index);

        return $row;
    }

    function setupProfilesSortable() {
        if (!$profilesList.length || typeof $profilesList.sortable !== 'function') {
            return;
        }

        $profilesList.sortable({
            handle: '.sidebar-jlg-profiles__handle',
            axis: 'y',
            placeholder: 'sidebar-jlg-profiles__placeholder',
            update() {
                const orderedIds = [];
                $profilesList.children().each(function() {
                    const id = $(this).data('profileId');
                    if (typeof id === 'string') {
                        orderedIds.push(id);
                    }
                });

                if (!orderedIds.length) {
                    return;
                }

                profilesState.profiles.sort((a, b) => orderedIds.indexOf(a.id) - orderedIds.indexOf(b.id));
                syncProfilesToInputs();
            },
        });
    }

    function bindProfileEvents() {
        $('#sidebar-jlg-profiles-add').on('click', function(e) {
            e.preventDefault();
            createProfile();
        });

        $profilesList.on('click', '.sidebar-jlg-profiles__select', function() {
            const profileId = $(this).attr('data-profile-id');
            if (typeof profileId === 'string') {
                setSelectedProfile(profileId);
            }
        });

        $profilesList.on('click', '.sidebar-jlg-profiles__delete', function(e) {
            e.preventDefault();
            const $item = $(this).closest('.sidebar-jlg-profiles__item');
            const profileId = $item.data('profileId');
            if (!profileId) {
                return;
            }

            const confirmMessage = getI18nString('profilesDeleteConfirm', 'Supprimer ce profil ?');
            if (!confirmMessage || window.confirm(confirmMessage)) {
                handleProfileDelete(String(profileId));
            }
        });

        $profilesList.on('change', '.sidebar-jlg-profiles__active-input', function() {
            const value = $(this).val();
            setActiveProfile(typeof value === 'string' ? value : '');
        });

        $profileTitle.on('input', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            profile.title = sanitizeText($(this).val());
            renderProfilesList();
        });

        $profileSlug.on('blur', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const previousId = profile.id;
            let desired = sanitizeProfileSlug($(this).val());

            if (desired === '') {
                desired = sanitizeProfileSlug(profile.title) || previousId;
            }

            const nextId = desired === previousId ? previousId : generateUniqueProfileId(desired);

            profile.id = nextId;
            profilesState.selectedId = nextId;

            if (profilesState.activeId === previousId) {
                profilesState.activeId = nextId;
            }

            $profileSlug.val(nextId);
            renderProfilesList();
            updateActiveProfileField();
            syncProfilesToInputs();
            updateActiveProfileStatusMessage();
        });

        $profilePriority.on('input change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const numeric = parseInt($(this).val(), 10);
            profile.priority = Number.isFinite(numeric) ? numeric : 0;
        });

        $profileEnabled.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            profile.enabled = $(this).is(':checked');
            renderProfilesList();
        });

        $profilePostTypes.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const values = Array.isArray($(this).val()) ? $(this).val() : [];
            profile.conditions.post_types = normalizeStringArray(values);
            syncProfilesToInputs();
        });

        $profileRoles.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const values = Array.isArray($(this).val()) ? $(this).val() : [];
            profile.conditions.roles = normalizeStringArray(values);
            syncProfilesToInputs();
        });

        $profileLanguages.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const values = Array.isArray($(this).val()) ? $(this).val() : [];
            profile.conditions.languages = normalizeLanguageArray(values);
            syncProfilesToInputs();
        });

        $profileDevices.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const values = Array.isArray($(this).val()) ? $(this).val() : [];
            profile.conditions.devices = normalizeDeviceArray(values);
            syncProfilesToInputs();
        });

        $profileLoginState.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const value = $(this).val();
            profile.conditions.logged_in = normalizeLoginState(value);
            renderSelectOptions(
                $profileLoginState,
                profileChoices.login_states,
                profile.conditions.logged_in && profile.conditions.logged_in !== 'any'
                    ? [profile.conditions.logged_in]
                    : ['any']
            );
            syncProfilesToInputs();
        });

        $profileScheduleDays.on('change', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const values = Array.isArray($(this).val()) ? $(this).val() : [];
            profile.conditions.schedule.days = normalizeScheduleDays(values);
            syncProfilesToInputs();
        });

        $profileScheduleStart.on('change input', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const normalized = normalizeScheduleTime($(this).val());
            profile.conditions.schedule.start = normalized;
            $(this).val(normalized);
            syncProfilesToInputs();
        });

        $profileScheduleEnd.on('change input', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const normalized = normalizeScheduleTime($(this).val());
            profile.conditions.schedule.end = normalized;
            $(this).val(normalized);
            syncProfilesToInputs();
        });

        $profileAddTaxonomy.on('click', function(e) {
            e.preventDefault();
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const defaultTaxonomy = profileChoices.taxonomies.length ? profileChoices.taxonomies[0].value : '';
            profile.conditions.taxonomies.push({
                taxonomy: sanitizeProfileSlug(defaultTaxonomy),
                terms: [],
            });

            renderTaxonomyRows(profile);
            syncProfilesToInputs();
        });

        $profileTaxonomiesContainer.on('change', '.sidebar-jlg-profile-taxonomy-name', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const index = parseInt($(this).attr('data-index'), 10);
            if (!Number.isFinite(index) || !profile.conditions.taxonomies[index]) {
                return;
            }

            profile.conditions.taxonomies[index].taxonomy = sanitizeProfileSlug($(this).val());
            renderProfilesList();
            syncProfilesToInputs();
        });

        $profileTaxonomiesContainer.on('input change', '.sidebar-jlg-profile-taxonomy-terms', function() {
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const index = parseInt($(this).attr('data-index'), 10);
            if (!Number.isFinite(index) || !profile.conditions.taxonomies[index]) {
                return;
            }

            profile.conditions.taxonomies[index].terms = normalizeTaxonomyTerms($(this).val());
            syncProfilesToInputs();
        });

        $profileTaxonomiesContainer.on('click', '.sidebar-jlg-profile-remove-taxonomy', function(e) {
            e.preventDefault();
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const index = parseInt($(this).attr('data-index'), 10);
            if (!Number.isFinite(index) || !profile.conditions.taxonomies[index]) {
                return;
            }

            profile.conditions.taxonomies.splice(index, 1);
            renderTaxonomyRows(profile);
            syncProfilesToInputs();
        });

        $profileCloneButton.on('click', function(e) {
            e.preventDefault();
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            const cloned = cloneCurrentSettings();
            if (!cloned) {
                renderNotice('error', getI18nString('profilesCloneError', 'Impossible de copier les réglages actuels.'));
                return;
            }

            profile.settings = cloned;
            updateSettingsSummary(profile);
            syncProfilesToInputs();
            renderNotice('success', getI18nString('profilesCloneSuccess', 'Les réglages actuels ont été associés au profil.'));
        });

        $profileClearSettings.on('click', function(e) {
            e.preventDefault();
            const profile = getSelectedProfile();
            if (!profile) {
                return;
            }

            profile.settings = {};
            updateSettingsSummary(profile);
            syncProfilesToInputs();
        });

        if ($clearActiveButton.length) {
            $clearActiveButton.on('click', function(e) {
                e.preventDefault();
                setActiveProfile('');
                $profilesList.find('.sidebar-jlg-profiles__active-input').prop('checked', false);
            });
        }

        $('#sidebar-jlg-form').on('submit.sidebarProfiles', function() {
            updateActiveProfileField();
            syncProfilesToInputs();
        });
    }

    function setSelectedProfile(id) {
        const profile = getProfileById(id);
        if (!profile) {
            return;
        }

        profilesState.selectedId = profile.id;
        renderProfilesList();
        renderProfileEditor();
    }

    function setActiveProfile(id) {
        if (id && !getProfileById(id)) {
            return;
        }

        profilesState.activeId = id;
        renderProfilesList();
        updateActiveProfileField();
        updateActiveProfileStatusMessage();
    }

    function createProfile() {
        const defaultTitle = getI18nString('profilesDefaultTitle', 'Nouveau profil');
        const newProfile = {
            id: generateUniqueProfileId(`profil-${profilesState.profiles.length + 1}`),
            title: defaultTitle,
            enabled: true,
            priority: 0,
            conditions: {
                post_types: [],
                roles: [],
                languages: [],
                taxonomies: [],
                devices: [],
                logged_in: 'any',
                schedule: {
                    start: '',
                    end: '',
                    days: [],
                },
            },
            settings: {},
        };

        profilesState.profiles.push(newProfile);
        profilesState.selectedId = newProfile.id;

        renderProfilesList();
        renderProfileEditor();
        syncProfilesToInputs();
    }

    function handleProfileDelete(id) {
        const index = profilesState.profiles.findIndex((profile) => profile.id === id);
        if (index === -1) {
            return;
        }

        profilesState.profiles.splice(index, 1);

        if (profilesState.selectedId === id) {
            profilesState.selectedId = profilesState.profiles.length ? profilesState.profiles[0].id : '';
        }

        if (profilesState.activeId === id) {
            profilesState.activeId = '';
        }

        renderProfilesList();
        renderProfileEditor();
        updateActiveProfileField();
        syncProfilesToInputs();
        updateActiveProfileStatusMessage();
    }

    function updateActiveProfileField() {
        if (!$activeProfileField.length) {
            return;
        }

        $activeProfileField.val(profilesState.activeId || '');
    }

    function updateSettingsSummary(profile) {
        if (!$profileSettingsSummary.length) {
            return;
        }

        const count = countSettingsKeys(profile.settings);
        if (count === 0) {
            $profileSettingsSummary.text(getI18nString('profilesSettingsEmpty', 'Aucun réglage personnalisé n’est défini pour ce profil.'));
            return;
        }

        const template = getI18nString('profilesSettingsSummary', 'Réglages personnalisés : %d champ(s).');
        $profileSettingsSummary.text(template.replace('%d', count));
    }

    function countSettingsKeys(value) {
        if (value === null || value === undefined) {
            return 0;
        }

        if (Array.isArray(value)) {
            return value.reduce((total, item) => total + countSettingsKeys(item), 0);
        }

        if (typeof value === 'object') {
            return Object.keys(value).reduce((total, key) => total + countSettingsKeys(value[key]), 0);
        }

        return 1;
    }

    function cloneCurrentSettings() {
        if (!previewModule || !previewModule.currentOptions) {
            return null;
        }

        const cloned = SidebarPreviewModule.cloneObject(previewModule.currentOptions);
        if (cloned && typeof cloned === 'object' && Object.prototype.hasOwnProperty.call(cloned, 'profiles')) {
            delete cloned.profiles;
        }

        return cloned || {};
    }

    function syncProfilesToInputs() {
        if (!$profilesHidden.length) {
            return;
        }

        $profilesHidden.empty();

        profilesState.profiles.forEach((profile, index) => {
            const base = `sidebar_jlg_profiles[${index}]`;
            appendHiddenField(`${base}[id]`, profile.id);
            appendHiddenField(`${base}[title]`, profile.title || profile.id);
            appendHiddenField(`${base}[priority]`, String(profile.priority));
            appendHiddenField(`${base}[enabled]`, profile.enabled ? '1' : '0');

            appendArrayFields(`${base}[conditions][post_types]`, profile.conditions.post_types);
            appendArrayFields(`${base}[conditions][content_types]`, profile.conditions.post_types);
            appendTaxonomyFields(`${base}[conditions][taxonomies]`, profile.conditions.taxonomies);
            appendArrayFields(`${base}[conditions][roles]`, profile.conditions.roles);
            appendArrayFields(`${base}[conditions][languages]`, profile.conditions.languages);
            appendArrayFields(`${base}[conditions][devices]`, profile.conditions.devices);

            const loginValue = profile.conditions.logged_in === 'logged-in'
                ? 'logged-in'
                : profile.conditions.logged_in === 'logged-out'
                    ? 'logged-out'
                    : '';
            appendHiddenField(`${base}[conditions][logged_in]`, loginValue);

            appendScheduleFields(`${base}[conditions][schedule]`, profile.conditions.schedule);

            appendNestedValue(`${base}[settings]`, profile.settings);
        });
    }

    function appendScheduleFields(baseName, schedule) {
        const normalized = schedule && typeof schedule === 'object'
            ? schedule
            : { start: '', end: '', days: [] };

        appendHiddenField(`${baseName}[start]`, normalized.start || '');
        appendHiddenField(`${baseName}[end]`, normalized.end || '');
        appendArrayFields(`${baseName}[days]`, Array.isArray(normalized.days) ? normalized.days : []);
    }

    function appendHiddenField(name, value) {
        if (!$profilesHidden.length) {
            return;
        }

        const input = $('<input>', { type: 'hidden', name, value });
        $profilesHidden.append(input);
    }

    function appendArrayFields(baseName, values) {
        if (!Array.isArray(values) || values.length === 0) {
            appendHiddenField(`${baseName}[]`, '');
            return;
        }

        values.forEach((value) => {
            appendHiddenField(`${baseName}[]`, value);
        });
    }

    function appendTaxonomyFields(baseName, taxonomies) {
        if (!Array.isArray(taxonomies) || taxonomies.length === 0) {
            appendHiddenField(baseName, '');
            return;
        }

        taxonomies.forEach((taxonomy, index) => {
            appendHiddenField(`${baseName}[${index}][taxonomy]`, taxonomy.taxonomy);
            if (Array.isArray(taxonomy.terms) && taxonomy.terms.length) {
                taxonomy.terms.forEach((term) => {
                    appendHiddenField(`${baseName}[${index}][terms][]`, term);
                });
            } else {
                appendHiddenField(`${baseName}[${index}][terms][]`, '');
            }
        });
    }

    function appendNestedValue(prefix, value) {
        if (value === null || value === undefined) {
            appendHiddenField(prefix, '');
            return;
        }

        if (Array.isArray(value)) {
            if (!value.length) {
                appendHiddenField(`${prefix}[]`, '');
                return;
            }

            value.forEach((item, index) => {
                appendNestedValue(`${prefix}[${index}]`, item);
            });
            return;
        }

        if (typeof value === 'object') {
            const keys = Object.keys(value);
            if (!keys.length) {
                appendHiddenField(prefix, '');
                return;
            }

            keys.forEach((key) => {
                appendNestedValue(`${prefix}[${key}]`, value[key]);
            });
            return;
        }

        appendHiddenField(prefix, typeof value === 'boolean' ? (value ? '1' : '0') : String(value));
    }

    function updateActiveProfileStatusMessage() {
        if (!previewModule || !previewModule.container) {
            return;
        }

        if (previewModule.container.getAttribute('data-state') !== 'ready') {
            return;
        }

        const message = previewActiveTemplate.replace('%s', getActiveProfileLabel());
        previewModule.setStatus(message, false);
    }

    function getActiveProfileLabel() {
        if (!profilesState.activeId) {
            return getI18nString('profilesDefaultActiveLabel', 'Réglages globaux');
        }

        const profile = getProfileById(profilesState.activeId);
        if (!profile) {
            return getI18nString('profilesDefaultActiveLabel', 'Réglages globaux');
        }

        return getProfileLabel(profile);
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
        } else if (type === 'cta') {
            const titleLabel = getI18nString('ctaTitleLabel', 'Titre du bloc');
            const descriptionLabel = getI18nString('ctaDescriptionLabel', 'Description');
            const buttonLabelLabel = getI18nString('ctaButtonLabel', 'Texte du bouton');
            const buttonUrlLabel = getI18nString('ctaButtonUrlLabel', 'Lien du bouton');
            const shortcodeLabel = getI18nString('ctaShortcodeLabel', 'Shortcode (optionnel)');
            const buttonUrlPlaceholder = getI18nString('ctaButtonUrlPlaceholder', 'https://...');

            const ctaTitle = typeof itemData.cta_title === 'string' ? itemData.cta_title : '';
            const ctaDescription = typeof itemData.cta_description === 'string' ? itemData.cta_description : '';
            const ctaButtonLabel = typeof itemData.cta_button_label === 'string' ? itemData.cta_button_label : '';
            const ctaButtonUrl = typeof itemData.cta_button_url === 'string' && itemData.cta_button_url.trim() !== ''
                ? itemData.cta_button_url
                : value;
            const ctaShortcode = typeof itemData.cta_shortcode === 'string' ? itemData.cta_shortcode : '';

            const $fields = $('<div>', {
                class: 'menu-item-cta-fields',
                'data-menu-item-cta-fields': '1'
            });

            const $titleWrapper = $('<p>');
            $titleWrapper.append($('<label>').text(titleLabel));
            const $titleInput = $('<input>', {
                type: 'text',
                class: 'widefat menu-item-cta-title',
                name: `sidebar_jlg_settings[menu_items][${index}][cta_title]`
            }).val(ctaTitle);
            $titleWrapper.append($titleInput);

            const $descriptionWrapper = $('<p>');
            $descriptionWrapper.append($('<label>').text(descriptionLabel));
            const $descriptionTextarea = $('<textarea>', {
                class: 'widefat menu-item-cta-description',
                rows: 3,
                name: `sidebar_jlg_settings[menu_items][${index}][cta_description]`
            }).val(ctaDescription);
            $descriptionWrapper.append($descriptionTextarea);

            const $buttonLabelWrapper = $('<p>');
            $buttonLabelWrapper.append($('<label>').text(buttonLabelLabel));
            const $buttonLabelInput = $('<input>', {
                type: 'text',
                class: 'widefat menu-item-cta-button-label',
                name: `sidebar_jlg_settings[menu_items][${index}][cta_button_label]`
            }).val(ctaButtonLabel);
            $buttonLabelWrapper.append($buttonLabelInput);

            const $buttonUrlWrapper = $('<p>');
            $buttonUrlWrapper.append($('<label>').text(buttonUrlLabel));
            const $buttonUrlInput = $('<input>', {
                type: 'url',
                class: 'widefat menu-item-cta-button-url',
                placeholder: buttonUrlPlaceholder,
                name: `sidebar_jlg_settings[menu_items][${index}][cta_button_url]`
            }).val(ctaButtonUrl || '');
            $buttonUrlWrapper.append($buttonUrlInput);

            const $hiddenValueInput = $('<input>', {
                type: 'hidden',
                name: `sidebar_jlg_settings[menu_items][${index}][value]`
            }).val(ctaButtonUrl || '');
            $buttonUrlWrapper.append($hiddenValueInput);

            const $shortcodeWrapper = $('<p>');
            $shortcodeWrapper.append($('<label>').text(shortcodeLabel));
            const $shortcodeTextarea = $('<textarea>', {
                class: 'widefat menu-item-cta-shortcode',
                rows: 2,
                name: `sidebar_jlg_settings[menu_items][${index}][cta_shortcode]`
            }).val(ctaShortcode);
            $shortcodeWrapper.append($shortcodeTextarea);

            $fields
                .append($titleWrapper)
                .append($descriptionWrapper)
                .append($buttonLabelWrapper)
                .append($buttonUrlWrapper)
                .append($shortcodeWrapper);

            fieldContainer.append($fields);

            const defaultFallback = getI18nString('menuItemDefaultTitle', 'Nouvel élément');
            const updateFallbackTitle = (nextTitle) => {
                const trimmed = typeof nextTitle === 'string' ? nextTitle.trim() : '';
                const fallback = trimmed || defaultFallback;
                $itemBox.data('fallbackTitle', fallback);
                const $labelField = $itemBox.find('.item-label');
                const labelValue = $labelField.length && typeof $labelField.val() === 'string'
                    ? $labelField.val().trim()
                    : '';
                $itemBox.find('.item-title').text(labelValue || fallback);
            };

            updateFallbackTitle(ctaTitle);
            $titleInput.on('input', function() {
                updateFallbackTitle(this.value || '');
            });

            $buttonUrlInput.on('input', function() {
                const currentValue = typeof this.value === 'string' ? this.value.trim() : '';
                $hiddenValueInput.val(currentValue);
                triggerFieldUpdate($hiddenValueInput[0]);
            });

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
            nav_menu_filter: 'all',
            cta_title: '',
            cta_description: '',
            cta_button_label: '',
            cta_button_url: '',
            cta_shortcode: ''
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
    const socialIconStandardKeys = [
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

    ensureIconsFetched(socialIconStandardKeys);

    function populateStandardIconsDropdown($select, selectedValue) {
        $select.empty();

        // Ajouter les icônes standard
        socialIconStandardKeys.forEach(key => {
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

    let socialIconPreviewRequestCounter = 0;

    function updateSocialIconPreviewElement($preview, iconKey) {
        if (!$preview || typeof $preview.html !== 'function') {
            return;
        }

        const entry = getIconEntry(iconKey);

        if (!entry) {
            $preview.empty();
            return;
        }

        const resolvedKey = entry.key;
        const requestId = ++socialIconPreviewRequestCounter;
        $preview.data('socialIconRequestId', requestId);

        if (iconCache[resolvedKey]) {
            $preview.html(iconCache[resolvedKey]);
            return;
        }

        ensureIconsFetched([resolvedKey]).then(() => {
            if ($preview.data('socialIconRequestId') !== requestId) {
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
            updateSocialIconPreviewElement($preview, itemData.icon);

            const defaultTitle = getI18nString('socialIconDefaultTitle', 'Nouvelle icône');
            const iconKey = typeof itemData.icon === 'string' ? itemData.icon : '';
            const fallbackTitle = iconKey ? iconKey.split('_')[0] : defaultTitle;
            $itemBox.data('fallbackTitle', (fallbackTitle || defaultTitle).trim());

            $select.on('change', function() {
                const selectedIconKey = $(this).val();
                updateSocialIconPreviewElement($preview, selectedIconKey);
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
