jQuery(document).ready(function($) {
    const options = sidebarJLG.options;
    const availableIcons = sidebarJLG.all_icons || {};
    const debugMode = options.debug_mode == '1';
    const ajaxCache = { posts: {}, categories: {} };

    function logDebug(message, data = '') {
        if (debugMode) {
            console.log(`[Sidebar JLG Debug] ${message}`, data);
        }
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

    $('input[name="sidebar_jlg_settings[layout_style]"]').on('change', function() {
        $('.floating-options-field').toggle($(this).val() === 'floating');
    }).trigger('change');

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

    function populateIconGrid() {
        const grid = $('#icon-grid');
        grid.empty();

        Object.keys(availableIcons).forEach(iconName => {
            const svgMarkup = availableIcons[iconName];

            if (typeof svgMarkup !== 'string' || svgMarkup.trim() === '') {
                return;
            }

            const $button = $('<button>', {
                type: 'button',
                'data-icon-name': iconName,
                title: iconName
            });

            $button.append(svgMarkup);
            $button.append($('<span></span>').text(iconName));
            grid.append($button);
        });
    }

    function updateIconPreview(input, $preview) {
        const iconValue = $(input).val();
        if (!iconValue) {
            $preview.empty();
            return;
        }

        const iconType = $(input).closest('.menu-item-box').find('.menu-item-icon-type').val();
        if (iconType === 'svg_url') {
            $preview.html(iconValue.startsWith('http') ? `<img src="${iconValue}" alt="preview">` : '');
            return;
        }

        const sanitizedKey = sanitizeIconKey(iconValue);
        const iconMarkup = availableIcons[iconValue] || availableIcons[sanitizedKey];

        if (iconMarkup) {
            $preview.html(iconMarkup);
        } else {
            $preview.empty();
        }
    }

    $('body').on('click', '.choose-icon', function() { 
        openIconLibrary($(this).siblings('.icon-input'), $(this).siblings('.icon-preview')); 
    });
    $('body').on('click', '.choose-svg', function() { 
        openMediaLibrary($(this).siblings('.icon-input'), $(this).siblings('.icon-preview')); 
    });
    
    modal.on('click', '.modal-close, .modal-backdrop', () => modal.hide());
    $('#icon-grid').on('click', 'button', function() {
        const iconName = $(this).data('icon-name');
        currentIconInput.val(iconName).trigger('change');
        modal.hide();
    });
    $('#icon-search').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('#icon-grid button').each(function() {
            $(this).toggle($(this).data('icon-name').includes(searchTerm));
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
            const label = $(this).val();
            $itemBox.find('.item-title').text(label || config.newTitle);
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
        
        iconWrapper.empty();
        
        if (type === 'svg_url') {
            iconWrapper.html(`
                <input type="text" class="widefat icon-input" 
                       name="sidebar_jlg_settings[${dataKey}][${index}][icon]" 
                       placeholder="https://example.com/icon.svg">
                <button type="button" class="button choose-svg">Choisir depuis la médiathèque</button>
                <span class="icon-preview"></span>
            `);
        } else {
            iconWrapper.html(`
                <input type="text" class="widefat icon-input" 
                       name="sidebar_jlg_settings[${dataKey}][${index}][icon]" 
                       placeholder="Nom de l'icône">
                <button type="button" class="button choose-icon">Parcourir les icônes</button>
                <span class="icon-preview"></span>
            `);
        }
    }

    // Fonction pour mettre à jour le champ valeur selon le type
    function getCacheBucket(action) {
        return action === 'jlg_get_posts' ? ajaxCache.posts : ajaxCache.categories;
    }

    function buildCacheKey(action, include, page, perPage, postType) {
        const hasInclude = include !== undefined && include !== null && include !== '';
        const includeKey = hasInclude ? (Array.isArray(include) ? include.join(',') : String(include)) : '';
        const normalizedPostType = postType ? String(postType) : '';
        return [action, includeKey, page, perPage, normalizedPostType].join('|');
    }

    function requestAjaxData(action, requestData) {
        const bucket = getCacheBucket(action);
        const key = buildCacheKey(
            action,
            requestData.include,
            requestData.page,
            requestData.posts_per_page,
            requestData.post_type
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

    function populateSelectOptions($selectElement, type, response, normalizedValue, createCurrentOption, action) {
        if (!$selectElement.closest('body').length) {
            return;
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

            if (!$selectElement.children().length) {
                const emptyOption = document.createElement('option');
                emptyOption.value = '';
                emptyOption.textContent = 'Aucun résultat';
                $selectElement.append(emptyOption);
            }
        } else {
            logDebug(`Failed to fetch data for ${action}.`);
            $selectElement.empty();

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
    }

    function handleAjaxFailure($selectElement, createCurrentOption, action) {
        logDebug(`AJAX request failed for ${action}.`);

        if (!$selectElement.closest('body').length) {
            return;
        }

        $selectElement.empty();

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

    function updateValueField($itemBox, itemData) {
        const type = $itemBox.find('.menu-item-type').val();
        const valueWrapper = $itemBox.find('.menu-item-value-wrapper');
        const index = $itemBox.index();
        const value = itemData.value || '';
        
        valueWrapper.empty();
        
        if (type === 'custom') {
            valueWrapper.html(`
                <p><label>URL</label>
                <input type="text" class="widefat"
                       name="sidebar_jlg_settings[menu_items][${index}][value]"
                       value="${value}" placeholder="https://..."></p>
            `);
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
            if (!initialCurrentOption) {
                loadingOption.selected = true;
            }
            $selectElement.append(loadingOption);

            const $paragraph = $('<p>');
            $paragraph.append($label);
            $paragraph.append($selectElement);
            valueWrapper.append($paragraph);

            const page = 1;
            const postsPerPage = 20;

            const requestData = {
                action: action,
                nonce: sidebarJLG.nonce,
                page: page,
                posts_per_page: postsPerPage
            };

            if (normalizedValue) {
                requestData.include = normalizedValue;
            }

            if (type === 'page' || type === 'post') {
                requestData.post_type = type;
            }

            requestAjaxData(action, requestData)
                .done(function(response) {
                    populateSelectOptions($selectElement, type, response, normalizedValue, createCurrentOption, action);
                })
                .fail(function() {
                    handleAjaxFailure($selectElement, createCurrentOption, action);
                });
        }
    }

    // --- Builder pour les éléments de menu ---
    createBuilder({
        containerId: 'menu-items-container', 
        templateId: 'menu-item',
        dataKey: 'menu_items',
        addButtonId: 'add-menu-item', 
        deleteButtonClass: 'delete-menu-item',
        newTitle: 'Nouvel élément',
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
    const standardIcons = availableIcons;
    
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
            if (standardIcons[key]) {
                const name = key.replace('_', ' (').replace('white', 'Blanc').replace('black', 'Noir') + ')';
                const capitalizedName = name.charAt(0).toUpperCase() + name.slice(1);
                $select.append($('<option>', {
                    value: key,
                    text: capitalizedName,
                    selected: key === selectedValue
                }));
            }
        });
        
        // Ajouter les icônes personnalisées
        Object.keys(standardIcons).forEach(key => {
            if (key.startsWith('custom_')) {
                const name = key.replace('custom_', '').replace(/_/g, ' ');
                const capitalizedName = 'Personnalisé: ' + name.charAt(0).toUpperCase() + name.slice(1);
                $select.append($('<option>', {
                    value: key,
                    text: capitalizedName,
                    selected: key === selectedValue
                }));
            }
        });
    }

    createBuilder({
        containerId: 'social-icons-container', 
        templateId: 'social-icon',
        dataKey: 'social_icons',
        addButtonId: 'add-social-icon',
        deleteButtonClass: 'delete-social-icon',
        newTitle: 'Nouvelle icône',
        newItem: (index) => ({ 
            index, 
            url: '', 
            icon: 'facebook_white' 
        }),
        onAppend: ($itemBox, itemData) => {
            const $select = $itemBox.find('.social-icon-select');
            populateStandardIconsDropdown($select, itemData.icon);
            
            const $preview = $itemBox.find('.icon-preview');
            $preview.html(standardIcons[itemData.icon] || '');
            
            $itemBox.find('.item-title').text(itemData.icon.split('_')[0]);

            $select.on('change', function() {
                const selectedIconKey = $(this).val();
                $preview.html(standardIcons[selectedIconKey] || '');
                $itemBox.find('.item-title').text(selectedIconKey.split('_')[0]);
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