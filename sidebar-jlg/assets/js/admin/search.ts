/* eslint-disable */
// @ts-nocheck

export function initSearchSection($, getI18nString) {
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

    return {
        activateTab,
        applySearchFilter,
    };
}
