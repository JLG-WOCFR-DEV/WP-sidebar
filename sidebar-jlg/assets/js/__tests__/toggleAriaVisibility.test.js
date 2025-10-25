const $ = require('jquery');

describe('toggleAriaVisibility', () => {
    let toggleAriaVisibility;

    beforeAll(() => {
        global.jQuery = $;
        global.$ = $;
        if (typeof window !== 'undefined') {
            window.jQuery = $;
            window.$ = $;
        }

        const noop = () => {};

        const wpDataStore = {
            getMenus: () => [],
            getMenuItems: () => [],
        };

        const wpDataDispatch = () => ({
            saveMenu: () => Promise.resolve(),
        });

        const wpData = {
            select: () => wpDataStore,
            dispatch: () => wpDataDispatch,
            subscribe: () => noop,
        };

        const wpElement = {
            createElement: () => null,
            render: noop,
            useMemo: (factory) => (typeof factory === 'function' ? factory() : undefined),
            useState: (initial) => [initial, noop],
        };

        const wpComponents = {
            UnitControl: () => null,
            RangeControl: () => null,
        };

        const wpMediaResponse = {
            on: noop,
            open: noop,
            state: () => ({
                get: () => ({
                    first: () => ({
                        toJSON: () => ({ url: '' }),
                    }),
                }),
            }),
        };

        const wpStub = {
            data: wpData,
            apiFetch: () => Promise.resolve([]),
            element: wpElement,
            components: wpComponents,
            media: () => wpMediaResponse,
            a11y: { speak: noop },
            template: () => noop,
        };

        global.wp = wpStub;
        if (typeof window !== 'undefined') {
            window.wp = wpStub;
        }

        const sidebarStub = {
            options: {},
            ajax_url: '',
            nonce: '',
            icon_fetch_action: '',
            tools_nonce: '',
            icons_manifest: [],
            icon_upload_action: '',
            icon_upload_max_size: 0,
            widget_schemas: {},
        };

        global.sidebarJLG = sidebarStub;
        if (typeof window !== 'undefined') {
            window.sidebarJLG = sidebarStub;
        }

        toggleAriaVisibility = require('../admin-script.js').toggleAriaVisibility;
    });

    afterAll(() => {
        delete require.cache[require.resolve('../admin-script.js')];
        delete global.sidebarJLG;
        delete global.wp;
        delete global.jQuery;
        delete global.$;
        if (typeof window !== 'undefined') {
            delete window.sidebarJLG;
            delete window.wp;
            delete window.jQuery;
            delete window.$;
        }
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    test('hides element with accessibility attributes applied', () => {
        const element = document.createElement('div');
        document.body.appendChild(element);

        toggleAriaVisibility(element, false);

        expect(element.hidden).toBe(true);
        expect(element.getAttribute('aria-hidden')).toBe('true');
        expect(element.getAttribute('aria-disabled')).toBe('true');
        expect(element.getAttribute('tabindex')).toBe('-1');
        expect(element.style.display).toBe('none');
    });

    test('restores visibility and prior tab index when showing an element again', () => {
        const element = document.createElement('button');
        element.setAttribute('tabindex', '2');
        document.body.appendChild(element);

        toggleAriaVisibility(element, false);
        toggleAriaVisibility(element, true);

        expect(element.hidden).toBe(false);
        expect(element.getAttribute('aria-hidden')).toBe('false');
        expect(element.getAttribute('aria-disabled')).toBe('false');
        expect(element.getAttribute('tabindex')).toBe('2');
        expect(element.style.display).toBe('');
    });

    test('works with jQuery collections and removes temporary tabindex', () => {
        const element = document.createElement('div');
        document.body.appendChild(element);

        const collection = $(element);

        toggleAriaVisibility(collection, false);
        expect(element.getAttribute('tabindex')).toBe('-1');

        toggleAriaVisibility(collection, true);
        expect(element.hasAttribute('tabindex')).toBe(false);
    });
});
