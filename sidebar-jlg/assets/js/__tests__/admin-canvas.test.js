describe('admin-canvas', () => {
    let initCanvasExperience;
    let createHistory;

    beforeEach(() => {
        jest.resetModules();
        document.body.innerHTML = `
            <div data-sidebar-experience data-sidebar-experience-mode="simple" data-sidebar-form-mode="simple">
                <div data-sidebar-mode-toggle>
                    <button data-mode-value="simple"></button>
                    <button data-mode-value="expert"></button>
                </div>
                <div data-sidebar-mode-summary></div>
                <div data-sidebar-experience-view-toggle>
                    <button data-experience-view="form"></button>
                    <button data-experience-view="canvas"></button>
                </div>
                <section data-sidebar-canvas hidden aria-hidden="true">
                    <header class="sidebar-jlg-canvas__toolbar" data-sidebar-canvas-toolbar>
                        <button data-canvas-command="undo" disabled></button>
                        <button data-canvas-command="redo" disabled></button>
                        <button data-canvas-command="refresh"></button>
                    </header>
                    <div data-sidebar-canvas-workspace>
                        <div data-sidebar-canvas-preview></div>
                        <div data-sidebar-canvas-items role="list"></div>
                        <p data-sidebar-canvas-empty hidden></p>
                    </div>
                    <aside data-sidebar-canvas-inspector hidden>
                        <form data-sidebar-canvas-form>
                            <input data-canvas-field="label" />
                            <input data-canvas-field="icon" />
                            <input data-canvas-field="color" />
                            <button type="button" data-canvas-command="apply"></button>
                            <button type="button" data-canvas-command="cancel"></button>
                        </form>
                    </aside>
                </section>
                <form id="sidebar-jlg-form"></form>
                <div class="sidebar-jlg-preview"></div>
            </div>
        `;

        global.sidebarJLG = {
            ajax_url: '/wp-admin/admin-ajax.php',
            canvas: {
                nonce: 'nonce-jlg_canvas_nonce',
                update_action: 'jlg_canvas_update_item',
                reorder_action: 'jlg_canvas_reorder_items',
            },
            options: {
                menu_items: [
                    {
                        type: 'custom',
                        label: 'Accueil',
                        value: 1,
                        icon: 'home_white',
                        icon_type: 'svg_inline',
                    },
                    {
                        type: 'cta',
                        label: 'CTA',
                        cta_title: 'Titre CTA',
                        cta_button_label: 'Agir',
                        cta_button_url: 'https://example.com',
                        icon: 'star',
                        icon_type: 'svg_inline',
                        cta_button_color: 'rgba(0,0,0,1)',
                    },
                ],
            },
            i18n: {
                canvasDuplicateSuffix: '(copie)',
                canvasUpdateError: 'Erreur',
                menuItemDefaultTitle: 'Nouvel élément',
            },
        };

        global.SidebarJLGExperience = {
            getView: () => 'form',
            setView: jest.fn(),
            getMode: () => 'simple',
            setMode: jest.fn(),
        };

        global.fetch = jest.fn().mockResolvedValue({
            json: () => Promise.resolve({ success: true, data: { items: global.sidebarJLG.options.menu_items } }),
        });

        ({ initCanvasExperience, createHistory } = require('../admin-canvas.js'));
    });

    test('history manager supports undo and redo', () => {
        const history = createHistory([{ label: 'A' }]);
        history.commit([{ label: 'B' }]);
        expect(history.canUndo()).toBe(true);
        const previous = history.undo();
        expect(previous[0].label).toBe('A');
        expect(history.canRedo()).toBe(true);
        const next = history.redo();
        expect(next[0].label).toBe('B');
    });

    test('initialization renders items and toggles view', () => {
        const controller = initCanvasExperience(document);
        expect(controller.getItems().length).toBe(2);
        controller.setView('canvas');
        expect(global.SidebarJLGExperience.setView).toHaveBeenCalledWith('canvas');
        const canvasSection = document.querySelector('[data-sidebar-canvas]');
        expect(canvasSection.hidden).toBe(false);
    });

    test('duplicate item updates state before persistence', async () => {
        const controller = initCanvasExperience(document);
        controller.setView('canvas');
        const duplicateButton = document.querySelector('[data-canvas-action="duplicate"]');
        expect(duplicateButton).not.toBeNull();
        duplicateButton.click();
        expect(controller.getItems().length).toBe(3);
        await Promise.resolve();
        expect(global.fetch).toHaveBeenCalled();
    });
});
