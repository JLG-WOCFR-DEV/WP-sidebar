/* eslint-disable */
// @ts-nocheck

export function initProfilesSection($, previewModule, renderNotice, getI18nString) {
    const profileChoices = normalizeProfileChoices(sidebarJLG.profile_choices);
    const profilesState = {
        profiles: [],
        selectedId: '',
        activeId: '',
    };
    const previewActiveTemplate = (sidebarJLG.preview_messages && typeof sidebarJLG.preview_messages.activeProfile === 'string')
        ? sidebarJLG.preview_messages.activeProfile
        : 'Profil actif : %s';
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

    return {
        profilesState,
        syncProfilesToInputs,
        updateActiveProfileStatusMessage,
    };
}
