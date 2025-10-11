(function (factory) {
    if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        var api = factory();
        if (typeof window !== 'undefined') {
            window.SidebarJLGCanvas = api;
        }
    }
})(function () {
    'use strict';

    function clone(value) {
        var source = typeof value === 'undefined' ? null : value;
        return JSON.parse(JSON.stringify(source));
    }

    function isObject(value) {
        return value !== null && typeof value === 'object';
    }

    function getGlobalData() {
        if (typeof window !== 'undefined' && window.sidebarJLG && typeof window.sidebarJLG === 'object') {
            return window.sidebarJLG;
        }

        if (typeof sidebarJLG !== 'undefined' && typeof sidebarJLG === 'object') {
            return sidebarJLG;
        }

        return null;
    }

    function getI18nString(key, fallback) {
        var globalData = getGlobalData();
        if (globalData && globalData.i18n && typeof globalData.i18n[key] === 'string') {
            return globalData.i18n[key];
        }

        return fallback;
    }

    function normalizeItems(rawItems) {
        if (!Array.isArray(rawItems)) {
            return [];
        }

        return rawItems.map(function (item, index) {
            var normalized = isObject(item) ? clone(item) : {};
            var type = typeof normalized.type === 'string' ? normalized.type : 'custom';
            var label = typeof normalized.label === 'string' ? normalized.label : '';
            var icon = typeof normalized.icon === 'string' ? normalized.icon : '';
            var iconType = typeof normalized.icon_type === 'string' ? normalized.icon_type : 'svg_inline';
            var color = typeof normalized.cta_button_color === 'string' ? normalized.cta_button_color : '';
            var uid = typeof normalized.id === 'string' && normalized.id !== '' ? normalized.id : 'item-' + index;

            return {
                id: uid,
                index: index,
                type: type,
                label: label,
                icon: icon,
                icon_type: iconType,
                color: color,
                data: normalized
            };
        });
    }

    function denormalizeItem(item) {
        var base = isObject(item && item.data) ? clone(item.data) : {};
        base.label = item.label || '';
        base.type = item.type || 'custom';
        base.icon = item.icon || '';
        base.icon_type = item.icon_type || 'svg_inline';
        if (item.color !== undefined) {
            base.cta_button_color = item.color || '';
        }

        return base;
    }

    function denormalizeItems(items) {
        if (!Array.isArray(items)) {
            return [];
        }

        return items.map(denormalizeItem);
    }

    function createHistory(initialState) {
        var current = clone(initialState || []);
        var past = [];
        var future = [];

        function commit(next) {
            past.push(clone(current));
            current = clone(next);
            future = [];
            return clone(current);
        }

        function undo() {
            if (!past.length) {
                return null;
            }

            var previous = past.pop();
            future.unshift(clone(current));
            current = clone(previous);

            return clone(current);
        }

        function redo() {
            if (!future.length) {
                return null;
            }

            var next = future.shift();
            past.push(clone(current));
            current = clone(next);

            return clone(current);
        }

        function reset(next) {
            current = clone(next || []);
            past = [];
            future = [];

            return clone(current);
        }

        function replaceCurrent(next) {
            current = clone(next || []);

            return clone(current);
        }

        function clearFuture() {
            future = [];
        }

        return {
            commit: commit,
            undo: undo,
            redo: redo,
            reset: reset,
            replaceCurrent: replaceCurrent,
            clearFuture: clearFuture,
            canUndo: function () {
                return past.length > 0;
            },
            canRedo: function () {
                return future.length > 0;
            },
            getCurrent: function () {
                return clone(current);
            }
        };
    }

    function buildCanvasConfig() {
        var globalData = getGlobalData() || {};
        var ajaxUrl = '';
        if (typeof window !== 'undefined' && typeof window.ajaxurl === 'string' && window.ajaxurl !== '') {
            ajaxUrl = window.ajaxurl;
        } else if (typeof globalData.ajax_url === 'string') {
            ajaxUrl = globalData.ajax_url;
        }

        var canvasConfig = globalData.canvas || {};
        var events = Object.assign({
            viewChange: 'sidebarJlgCanvasViewChange',
            itemsHydrated: 'sidebarJlgCanvasHydrated',
            itemsUpdated: 'sidebarJlgCanvasItemsUpdated',
            historyUpdate: 'sidebarJlgCanvasHistoryUpdate',
            error: 'sidebarJlgCanvasError'
        }, isObject(canvasConfig.events) ? canvasConfig.events : {});

        return {
            ajaxUrl: ajaxUrl,
            nonce: typeof canvasConfig.nonce === 'string' ? canvasConfig.nonce : '',
            updateAction: typeof canvasConfig.update_action === 'string' ? canvasConfig.update_action : 'jlg_canvas_update_item',
            reorderAction: typeof canvasConfig.reorder_action === 'string' ? canvasConfig.reorder_action : 'jlg_canvas_reorder_items',
            events: events
        };
    }

    function createCanvasController(doc) {
        if (!doc || typeof doc.querySelector !== 'function') {
            return null;
        }

        var root = doc.querySelector('[data-sidebar-experience]');
        var canvas = root ? root.querySelector('[data-sidebar-canvas]') : null;
        if (!root || !canvas) {
            return null;
        }

        var itemsContainer = canvas.querySelector('[data-sidebar-canvas-items]');
        var previewContainer = canvas.querySelector('[data-sidebar-canvas-preview]');
        var emptyState = canvas.querySelector('[data-sidebar-canvas-empty]');
        var inspector = canvas.querySelector('[data-sidebar-canvas-inspector]');
        var inspectorForm = canvas.querySelector('[data-sidebar-canvas-form]');
        var toolbar = canvas.querySelector('[data-sidebar-canvas-toolbar]');
        var viewToggle = root.querySelector('[data-sidebar-experience-view-toggle]');
        var inspectorFields = inspectorForm ? Array.from(inspectorForm.querySelectorAll('[data-canvas-field]')) : [];
        var toolbarButtons = toolbar ? Array.from(toolbar.querySelectorAll('[data-canvas-command]')) : [];
        var experienceApi = (typeof window !== 'undefined' && window.SidebarJLGExperience) ? window.SidebarJLGExperience : null;
        var config = buildCanvasConfig();
        var globalData = getGlobalData() || {};
        var history = createHistory([]);
        var state = {
            view: 'form',
            items: [],
            activeIndex: -1,
            isReady: false
        };

        function dispatchCanvasEvent(name, detail) {
            if (!root || typeof CustomEvent !== 'function') {
                return;
            }

            var eventName = config.events[name] || name;
            var event = new CustomEvent(eventName, {
                detail: detail || {},
                bubbles: true
            });

            root.dispatchEvent(event);
        }

        function updateBodyClass() {
            if (!doc || !doc.body) {
                return;
            }

            if (state.view === 'canvas') {
                doc.body.classList.add('sidebar-jlg-canvas-active');
            } else {
                doc.body.classList.remove('sidebar-jlg-canvas-active');
            }
        }

        function applyViewToExperience() {
            if (!root) {
                return;
            }

            root.classList.add('sidebar-jlg-canvas-ready');

            if (experienceApi && typeof experienceApi.setView === 'function') {
                experienceApi.setView(state.view);
            } else {
                var fallbackMode = root.getAttribute('data-sidebar-form-mode') || root.getAttribute('data-sidebar-experience-mode') || 'simple';
                if (state.view === 'canvas') {
                    root.setAttribute('data-sidebar-experience-mode', 'canvas');
                } else {
                    root.setAttribute('data-sidebar-experience-mode', fallbackMode);
                }
            }

            root.setAttribute('data-sidebar-experience-view', state.view);
        }

        function updateToggleButtons() {
            if (!viewToggle) {
                return;
            }

            Array.from(viewToggle.querySelectorAll('[data-experience-view]')).forEach(function (button) {
                var value = button.getAttribute('data-experience-view');
                var isActive = value === state.view;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', isActive ? 'true' : 'false');
            });
        }

        function updateToolbarState() {
            toolbarButtons.forEach(function (button) {
                var command = button.getAttribute('data-canvas-command');
                if (command === 'undo') {
                    button.disabled = !history.canUndo();
                } else if (command === 'redo') {
                    button.disabled = !history.canRedo();
                }
            });

            dispatchCanvasEvent('historyUpdate', {
                canUndo: history.canUndo(),
                canRedo: history.canRedo()
            });
        }

        function hideInspector() {
            if (!inspector) {
                return;
            }

            inspector.setAttribute('hidden', 'hidden');
            inspector.setAttribute('aria-hidden', 'true');
        }

        function showInspector() {
            if (!inspector) {
                return;
            }

            inspector.removeAttribute('hidden');
            inspector.setAttribute('aria-hidden', 'false');
        }

        function renderItems() {
            if (!itemsContainer) {
                return;
            }

            itemsContainer.innerHTML = '';

            if (!state.items.length) {
                if (emptyState) {
                    emptyState.hidden = false;
                    emptyState.setAttribute('aria-hidden', 'false');
                }

                return;
            }

            if (emptyState) {
                emptyState.hidden = true;
                emptyState.setAttribute('aria-hidden', 'true');
            }

            state.items.forEach(function (item, index) {
                var element = doc.createElement('article');
                element.className = 'sidebar-jlg-canvas__item';
                element.setAttribute('role', 'listitem');
                element.setAttribute('data-canvas-item', '');
                element.setAttribute('data-canvas-index', String(index));

                var header = doc.createElement('div');
                header.className = 'sidebar-jlg-canvas__item-header';

                var title = doc.createElement('h3');
                title.className = 'sidebar-jlg-canvas__item-title';
                title.textContent = item.label || getI18nString('menuItemDefaultTitle', 'Nouvel élément');

                var type = doc.createElement('span');
                type.className = 'sidebar-jlg-canvas__item-type';
                type.textContent = item.type || 'custom';

                header.appendChild(title);
                header.appendChild(type);

                var actions = doc.createElement('div');
                actions.className = 'sidebar-jlg-canvas__item-actions';

                var focusButton = doc.createElement('button');
                focusButton.type = 'button';
                focusButton.className = 'button button-secondary';
                focusButton.setAttribute('data-canvas-action', 'focus');
                focusButton.textContent = getI18nString('canvasInspectorApply', 'Appliquer');

                var duplicateButton = doc.createElement('button');
                duplicateButton.type = 'button';
                duplicateButton.className = 'button';
                duplicateButton.setAttribute('data-canvas-action', 'duplicate');
                duplicateButton.textContent = getI18nString('stylePresetCompareButton', 'Dupliquer');

                var moveUpButton = doc.createElement('button');
                moveUpButton.type = 'button';
                moveUpButton.className = 'button';
                moveUpButton.setAttribute('data-canvas-action', 'move-up');
                moveUpButton.textContent = '↑';

                var moveDownButton = doc.createElement('button');
                moveDownButton.type = 'button';
                moveDownButton.className = 'button';
                moveDownButton.setAttribute('data-canvas-action', 'move-down');
                moveDownButton.textContent = '↓';

                actions.appendChild(focusButton);
                actions.appendChild(duplicateButton);
                actions.appendChild(moveUpButton);
                actions.appendChild(moveDownButton);

                element.appendChild(header);
                element.appendChild(actions);

                if (item.color) {
                    var colorSwatch = doc.createElement('div');
                    colorSwatch.className = 'sidebar-jlg-canvas__item-color';
                    colorSwatch.style.width = '28px';
                    colorSwatch.style.height = '14px';
                    colorSwatch.style.borderRadius = '6px';
                    colorSwatch.style.background = item.color;
                    element.appendChild(colorSwatch);
                }

                itemsContainer.appendChild(element);
            });
        }

        function updateInspector(index) {
            if (!inspectorForm || !state.items[index]) {
                hideInspector();
                return;
            }

            var item = state.items[index];
            inspectorFields.forEach(function (field) {
                var key = field.getAttribute('data-canvas-field');
                if (!key) {
                    return;
                }

                if (key === 'label') {
                    field.value = item.label || '';
                } else if (key === 'icon') {
                    field.value = item.icon || '';
                } else if (key === 'color') {
                    field.value = item.color || '';
                }
            });

            showInspector();
        }

        function setActiveIndex(index) {
            if (index < 0 || index >= state.items.length) {
                state.activeIndex = -1;
                hideInspector();
                return;
            }

            state.activeIndex = index;
            updateInspector(index);
            dispatchCanvasEvent('itemFocused', {
                index: index,
                item: denormalizeItem(state.items[index])
            });
        }

        function syncWithServer(response) {
            if (!response || !Array.isArray(response.items)) {
                return;
            }

            var normalized = normalizeItems(response.items);
            history.replaceCurrent(normalized);
            state.items = history.getCurrent();

            if (state.activeIndex >= state.items.length) {
                state.activeIndex = state.items.length - 1;
            }

            renderItems();

            if (state.activeIndex >= 0) {
                updateInspector(state.activeIndex);
            } else {
                hideInspector();
            }

            updateToolbarState();
            dispatchCanvasEvent('itemsUpdated', {
                items: denormalizeItems(state.items)
            });
        }

        function revertLastChange(error) {
            var previous = history.undo();
            if (previous) {
                history.clearFuture();
                state.items = history.getCurrent();
                renderItems();
                if (state.activeIndex >= 0) {
                    updateInspector(state.activeIndex);
                }
                updateToolbarState();
            }

            var message = error && error.message ? error.message : getI18nString('canvasUpdateError', 'Impossible d’enregistrer la modification.');
            dispatchCanvasEvent('error', { message: message });
        }

        function request(action, payload) {
            if (!config.ajaxUrl) {
                return Promise.reject(new Error('Missing AJAX endpoint.'));
            }

            var formData = new window.FormData();
            formData.append('action', action);
            if (config.nonce) {
                formData.append('nonce', config.nonce);
            }

            Object.keys(payload || {}).forEach(function (key) {
                var value = payload[key];
                if (value === undefined) {
                    return;
                }

                if (typeof value === 'object') {
                    formData.append(key, JSON.stringify(value));
                } else {
                    formData.append(key, String(value));
                }
            });

            return window.fetch(config.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.json();
            }).then(function (result) {
                if (!result || result.success !== true) {
                    var message = result && result.data ? result.data : null;
                    throw new Error(typeof message === 'string' ? message : getI18nString('canvasUpdateError', 'Impossible d’enregistrer la modification.'));
                }

                return result.data;
            });
        }

        function persistUpdate(index, item) {
            return request(config.updateAction, {
                index: index,
                item: item
            }).then(syncWithServer);
        }

        function persistReorder(items) {
            return request(config.reorderAction, {
                items: items
            }).then(syncWithServer);
        }

        function applyItems(nextItems, options) {
            var settings = options || {};
            var committed;
            if (settings.recordHistory === false) {
                history.replaceCurrent(nextItems);
                committed = history.getCurrent();
            } else {
                committed = history.commit(nextItems);
            }

            state.items = committed;
            renderItems();
            if (state.activeIndex >= 0) {
                updateInspector(state.activeIndex);
            }
            updateToolbarState();
            dispatchCanvasEvent('itemsUpdated', {
                items: denormalizeItems(state.items)
            });
        }

        function updateItem(index, updates) {
            if (index < 0 || index >= state.items.length) {
                return;
            }

            var nextItems = clone(state.items);
            var item = nextItems[index];
            var payload = denormalizeItem(item);

            if (Object.prototype.hasOwnProperty.call(updates, 'label')) {
                item.label = updates.label || '';
                payload.label = item.label;
            }

            if (Object.prototype.hasOwnProperty.call(updates, 'icon')) {
                item.icon = updates.icon || '';
                payload.icon = item.icon;
            }

            if (Object.prototype.hasOwnProperty.call(updates, 'color')) {
                item.color = updates.color || '';
                payload.cta_button_color = item.color;
            }

            nextItems[index] = item;
            applyItems(nextItems);

            persistUpdate(index, payload).catch(function (error) {
                revertLastChange(error);
            });
        }

        function buildDuplicateLabel(label) {
            var suffix = getI18nString('canvasDuplicateSuffix', '(copie)');
            var base = label || getI18nString('menuItemDefaultTitle', 'Nouvel élément');
            if (base.indexOf(suffix) !== -1) {
                return base;
            }

            return base + ' ' + suffix;
        }

        function duplicateItem(index) {
            if (index < 0 || index >= state.items.length) {
                return;
            }

            var nextItems = clone(state.items);
            var reference = nextItems[index];
            var duplicate = clone(reference);
            duplicate.label = buildDuplicateLabel(reference.label);
            duplicate.data = denormalizeItem(duplicate);
            nextItems.splice(index + 1, 0, duplicate);
            state.activeIndex = index + 1;

            applyItems(nextItems);

            persistReorder(denormalizeItems(state.items)).catch(function (error) {
                revertLastChange(error);
            });
        }

        function moveItem(index, direction) {
            var target = index + direction;
            if (index < 0 || index >= state.items.length || target < 0 || target >= state.items.length) {
                return;
            }

            var nextItems = clone(state.items);
            var moved = nextItems.splice(index, 1)[0];
            nextItems.splice(target, 0, moved);
            state.activeIndex = target;

            applyItems(nextItems);

            persistReorder(denormalizeItems(state.items)).catch(function (error) {
                revertLastChange(error);
            });
        }

        function executeUndo() {
            var previous = history.undo();
            if (!previous) {
                return;
            }

            state.items = history.getCurrent();
            renderItems();
            if (state.activeIndex >= 0) {
                updateInspector(state.activeIndex);
            } else {
                hideInspector();
            }
            updateToolbarState();

            persistReorder(denormalizeItems(state.items)).catch(function (error) {
                history.redo();
                updateToolbarState();
                dispatchCanvasEvent('error', { message: error.message });
            });
        }

        function executeRedo() {
            var next = history.redo();
            if (!next) {
                return;
            }

            state.items = history.getCurrent();
            renderItems();
            if (state.activeIndex >= 0) {
                updateInspector(state.activeIndex);
            }
            updateToolbarState();

            persistReorder(denormalizeItems(state.items)).catch(function (error) {
                history.undo();
                updateToolbarState();
                dispatchCanvasEvent('error', { message: error.message });
            });
        }

        function hydratePreview() {
            var preview = (typeof window !== 'undefined') ? window.SidebarJLGPreview : null;
            if (!preview || typeof preview.loadPreview !== 'function') {
                return Promise.resolve();
            }

            return preview.loadPreview().then(function () {
                if (previewContainer && preview.viewport && typeof preview.viewport.innerHTML === 'string') {
                    previewContainer.innerHTML = preview.viewport.innerHTML;
                }

                dispatchCanvasEvent('itemsHydrated', {
                    items: denormalizeItems(state.items)
                });
            }).catch(function () {
                dispatchCanvasEvent('error', { message: 'Preview refresh failed.' });
            });
        }

        function setView(nextView) {
            var normalized = nextView === 'canvas' ? 'canvas' : 'form';
            if (state.view === normalized) {
                return state.view;
            }

            state.view = normalized;
            applyViewToExperience();
            updateBodyClass();
            updateToggleButtons();

            if (state.view === 'canvas') {
                canvas.hidden = false;
                canvas.setAttribute('aria-hidden', 'false');
                hydratePreview();
            } else {
                canvas.hidden = true;
                canvas.setAttribute('aria-hidden', 'true');
                hideInspector();
            }

            dispatchCanvasEvent('viewChange', {
                view: state.view
            });

            return state.view;
        }

        function attachEventListeners() {
            if (viewToggle) {
                viewToggle.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!target || typeof target.getAttribute !== 'function') {
                        return;
                    }

                    var button = target.closest('[data-experience-view]');
                    if (!button) {
                        return;
                    }

                    var value = button.getAttribute('data-experience-view');
                    setView(value);
                });
            }

            if (itemsContainer) {
                itemsContainer.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!target || typeof target.closest !== 'function') {
                        return;
                    }

                    var actionButton = target.closest('[data-canvas-action]');
                    if (!actionButton) {
                        return;
                    }

                    var itemElement = actionButton.closest('[data-canvas-item]');
                    if (!itemElement) {
                        return;
                    }

                    var index = parseInt(itemElement.getAttribute('data-canvas-index') || '-1', 10);
                    var action = actionButton.getAttribute('data-canvas-action');

                    if (action === 'focus') {
                        setActiveIndex(index);
                    } else if (action === 'duplicate') {
                        duplicateItem(index);
                    } else if (action === 'move-up') {
                        moveItem(index, -1);
                    } else if (action === 'move-down') {
                        moveItem(index, 1);
                    }
                });
            }

            if (inspectorForm) {
                inspectorForm.addEventListener('submit', function (event) {
                    event.preventDefault();
                });
            }

            if (inspector) {
                inspector.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!target || typeof target.closest !== 'function') {
                        return;
                    }

                    var command = target.closest('[data-canvas-command]');
                    if (!command) {
                        return;
                    }

                    var action = command.getAttribute('data-canvas-command');
                    if (action === 'apply') {
                        if (state.activeIndex >= 0) {
                            var updates = {};
                            inspectorFields.forEach(function (field) {
                                var key = field.getAttribute('data-canvas-field');
                                if (key) {
                                    updates[key] = field.value;
                                }
                            });

                            updateItem(state.activeIndex, updates);
                        }
                    } else if (action === 'cancel') {
                        hideInspector();
                        state.activeIndex = -1;
                    }
                });
            }

            if (toolbar) {
                toolbar.addEventListener('click', function (event) {
                    var target = event.target;
                    if (!target || typeof target.closest !== 'function') {
                        return;
                    }

                    var command = target.closest('[data-canvas-command]');
                    if (!command) {
                        return;
                    }

                    var action = command.getAttribute('data-canvas-command');
                    if (action === 'undo') {
                        executeUndo();
                    } else if (action === 'redo') {
                        executeRedo();
                    } else if (action === 'refresh') {
                        hydratePreview();
                    }
                });
            }
        }

        function initialize() {
            var initialItems = normalizeItems((globalData.options && globalData.options.menu_items) || []);
            history.reset(initialItems);
            state.items = history.getCurrent();
            renderItems();
            updateToolbarState();
            updateToggleButtons();

            if (experienceApi && typeof experienceApi.getView === 'function') {
                state.view = experienceApi.getView();
            } else {
                var attr = root.getAttribute('data-sidebar-experience-mode');
                state.view = attr === 'canvas' ? 'canvas' : 'form';
            }

            applyViewToExperience();
            updateBodyClass();
            if (state.view === 'canvas') {
                canvas.hidden = false;
                canvas.setAttribute('aria-hidden', 'false');
                hydratePreview();
            } else {
                canvas.hidden = true;
                canvas.setAttribute('aria-hidden', 'true');
            }

            state.isReady = true;
            dispatchCanvasEvent('itemsUpdated', {
                items: denormalizeItems(state.items)
            });
        }

        attachEventListeners();
        initialize();

        return {
            getView: function () {
                return state.view;
            },
            setView: setView,
            getItems: function () {
                return denormalizeItems(state.items);
            },
            refreshPreview: hydratePreview,
            focus: setActiveIndex,
            undo: executeUndo,
            redo: executeRedo,
            history: history
        };
    }

    var controller = null;

    function initCanvasExperience(doc) {
        if (controller) {
            return controller;
        }

        controller = createCanvasController(doc || (typeof document !== 'undefined' ? document : null));

        return controller;
    }

    if (typeof document !== 'undefined') {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function () {
                initCanvasExperience(document);
            });
        } else {
            initCanvasExperience(document);
        }
    }

    return {
        initCanvasExperience: initCanvasExperience,
        createHistory: createHistory,
        normalizeItems: normalizeItems,
        denormalizeItems: denormalizeItems
    };
});
