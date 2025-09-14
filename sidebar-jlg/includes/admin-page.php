<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap sidebar-jlg-admin-wrap">
    <h1><?php _e( 'Réglages de la Sidebar JLG', 'sidebar-jlg' ); ?></h1>

    <?php
    if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] ) {
        add_settings_error( 'sidebar_jlg_messages', 'sidebar_jlg_message', __( 'Réglages sauvegardés.', 'sidebar-jlg' ), 'updated' );
    }
    settings_errors( 'sidebar_jlg_messages' );
    ?>

    <p><?php _e( 'Personnalisez l\'apparence et le comportement de votre sidebar.', 'sidebar-jlg' ); ?></p>
    <p><b><?php _e( 'Nouveau :', 'sidebar-jlg' ); ?></b> <?php _e( 'Ajoutez vos propres icônes SVG dans le dossier <code>/wp-content/plugins/sidebar-jlg/assets/icons/</code>. Elles apparaîtront dans les listes de sélection !', 'sidebar-jlg' ); ?></p>

    <div class="nav-tab-wrapper">
        <a href="#tab-general" class="nav-tab nav-tab-active"><?php _e( 'Général & Comportement', 'sidebar-jlg' ); ?></a>
        <a href="#tab-presets" class="nav-tab"><?php _e( 'Style & Préréglages', 'sidebar-jlg' ); ?></a>
        <a href="#tab-menu" class="nav-tab"><?php _e( 'Contenu du Menu', 'sidebar-jlg' ); ?></a>
        <a href="#tab-social" class="nav-tab"><?php _e( 'Réseaux Sociaux', 'sidebar-jlg' ); ?></a>
        <a href="#tab-effects" class="nav-tab"><?php _e( 'Effets & Animations', 'sidebar-jlg' ); ?></a>
        <a href="#tab-tools" class="nav-tab"><?php _e( 'Outils', 'sidebar-jlg' ); ?></a>
    </div>

    <form action="options.php" method="post" id="sidebar-jlg-form">
        <?php
        settings_fields( 'sidebar_jlg_options_group' );
        $defaults = Sidebar_JLG::get_instance()->get_default_settings();
        $options_from_db = get_option( 'sidebar_jlg_settings' );
        $options = wp_parse_args( $options_from_db, $defaults );
        ?>

        <!-- Onglet Général -->
        <div id="tab-general" class="tab-content active">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Activation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label class="jlg-switch">
                            <input type="checkbox" name="sidebar_jlg_settings[enable_sidebar]" value="1" <?php checked( $options['enable_sidebar'], 1 ); ?> />
                            <span class="jlg-slider"></span>
                        </label>
                        <p class="description"><?php _e( 'Active ou désactive complètement la sidebar sur votre site.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Style d\'affichage (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><input type="radio" name="sidebar_jlg_settings[layout_style]" value="full" <?php checked($options['layout_style'], 'full'); ?>> <?php _e('Pleine hauteur', 'sidebar-jlg'); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[layout_style]" value="floating" <?php checked($options['layout_style'], 'floating'); ?>> <?php _e('Flottant', 'sidebar-jlg'); ?></label>
                        </p>
                        <div class="floating-options-field" style="<?php echo $options['layout_style'] === 'floating' ? '' : 'display:none;'; ?>">
                            <p><label><?php _e( 'Marge verticale', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[floating_vertical_margin]" value="<?php echo esc_attr( $options['floating_vertical_margin'] ); ?>" class="small-text"/> <em class="description"><?php _e( 'Ex: 4rem, 15px', 'sidebar-jlg' ); ?></em></p>
                            <p><label><?php _e( 'Arrondi des coins', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[border_radius]" value="<?php echo esc_attr( $options['border_radius'] ); ?>" class="small-text"/> <em class="description"><?php _e( 'Ex: 12px, 1rem', 'sidebar-jlg' ); ?></em></p>
                            <p><label><?php _e( 'Épaisseur de la bordure', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[border_width]" value="<?php echo esc_attr( $options['border_width'] ); ?>" class="small-text"/> px</p>
                            <p><label><?php _e( 'Couleur de la bordure', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[border_color]" value="<?php echo esc_attr( $options['border_color'] ); ?>" class="color-picker-rgba"/></p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Comportement sur Desktop', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[desktop_behavior]" class="desktop-behavior-select">
                            <option value="push" <?php selected($options['desktop_behavior'], 'push'); ?>><?php _e('Pousser le contenu (Push)', 'sidebar-jlg'); ?></option>
                            <option value="overlay" <?php selected($options['desktop_behavior'], 'overlay'); ?>><?php _e('Superposer au contenu (Overlay)', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php _e('Choisissez si la sidebar pousse le contenu de votre site ou passe par-dessus.', 'sidebar-jlg'); ?></p>
                        <p class="push-option-field" style="<?php echo $options['desktop_behavior'] === 'push' ? '' : 'display:none;'; ?>">
                            <label><?php _e( 'Marge de sécurité du contenu', 'sidebar-jlg' ); ?></label>
                            <input type="text" name="sidebar_jlg_settings[content_margin]" value="<?php echo esc_attr( $options['content_margin'] ); ?>" class="small-text"/>
                            <em class="description"><?php _e( 'Espace entre la sidebar et le contenu (ex: 2rem, 30px).', 'sidebar-jlg' ); ?></em>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Dimensions', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php _e( 'Largeur (Desktop)', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[width_desktop]" value="<?php echo esc_attr( $options['width_desktop'] ); ?>" class="small-text"/> px</p>
                        <p><label><?php _e( 'Largeur (Tablette)', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[width_tablet]" value="<?php echo esc_attr( $options['width_tablet'] ); ?>" class="small-text"/> px <em class="description"><?php _e( 'Appliquée entre 768px et 992px.', 'sidebar-jlg' ); ?></em></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Bouton Hamburger (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php _e( 'Position verticale', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[hamburger_top_position]" value="<?php echo esc_attr( $options['hamburger_top_position'] ); ?>" class="small-text"/> <em class="description"><?php _e( 'Unités CSS (ex: 4rem, 15px).', 'sidebar-jlg' ); ?></em></p>
                        <p><label><input type="checkbox" name="sidebar_jlg_settings[show_close_button]" value="1" <?php checked( $options['show_close_button'], 1 ); ?> /> <?php _e( 'Afficher le bouton de fermeture (X) dans la sidebar.', 'sidebar-jlg' ); ?></label></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Barre de recherche', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="sidebar_jlg_settings[enable_search]" value="1" <?php checked( $options['enable_search'], 1 ); ?> /> <?php _e( 'Activer la barre de recherche.', 'sidebar-jlg' ); ?></label>
                        <div class="search-options-wrapper" style="<?php echo $options['enable_search'] ? '' : 'display:none;'; ?>">
                            <p>
                                <label><?php _e( 'Méthode d\'intégration :', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[search_method]" class="search-method-select">
                                    <option value="default" <?php selected($options['search_method'], 'default'); ?>><?php _e('Recherche WordPress par défaut', 'sidebar-jlg'); ?></option>
                                    <option value="shortcode" <?php selected($options['search_method'], 'shortcode'); ?>><?php _e('Shortcode personnalisé', 'sidebar-jlg'); ?></option>
                                    <option value="hook" <?php selected($options['search_method'], 'hook'); ?>><?php _e('Hook PHP (avancé)', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                            <p class="search-method-field search-shortcode-field" style="display:none;">
                                <label><?php _e( 'Shortcode :', 'sidebar-jlg' ); ?></label>
                                <input type="text" name="sidebar_jlg_settings[search_shortcode]" value="<?php echo esc_attr( $options['search_shortcode'] ); ?>" class="regular-text" placeholder="[mon_shortcode_recherche]"/>
                            </p>
                             <p class="search-method-field search-hook-field" style="display:none;">
                                <span class="description"><?php _e( 'Pour les moteurs de recherche complexes, ajoutez ce code à votre fichier `functions.php` :', 'sidebar-jlg' ); ?></span><br>
                                <code>add_action('jlg_sidebar_search_area', function() { /* Votre code PHP ici */ });</code>
                            </p>
                            <p>
                                <label><?php _e( 'Alignement de la recherche', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[search_alignment]">
                                    <option value="flex-start" <?php selected($options['search_alignment'], 'flex-start'); ?>><?php _e('Gauche', 'sidebar-jlg'); ?></option>
                                    <option value="center" <?php selected($options['search_alignment'], 'center'); ?>><?php _e('Centré', 'sidebar-jlg'); ?></option>
                                    <option value="flex-end" <?php selected($options['search_alignment'], 'flex-end'); ?>><?php _e('Droite', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Onglet Style & Préréglages -->
        <div id="tab-presets" class="tab-content">
             <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Préréglage de style', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[style_preset]" id="style-preset-select">
                            <option value="custom" <?php selected($options['style_preset'], 'custom'); ?>><?php _e('Personnalisé', 'sidebar-jlg'); ?></option>
                            <option value="moderne_dark" <?php selected($options['style_preset'], 'moderne_dark'); ?>><?php _e('Critique Moderne (Dark)', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php _e('Choisir un préréglage mettra à jour automatiquement les options de couleur ci-dessous.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'En-tête (Logo/Titre)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><input type="radio" name="sidebar_jlg_settings[header_logo_type]" value="text" <?php checked($options['header_logo_type'], 'text'); ?>> <?php _e('Afficher un titre textuel', 'sidebar-jlg'); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[header_logo_type]" value="image" <?php checked($options['header_logo_type'], 'image'); ?>> <?php _e('Afficher une image (logo)', 'sidebar-jlg'); ?></label>
                        </p>
                        <div class="header-text-options" style="<?php echo $options['header_logo_type'] === 'text' ? '' : 'display:none;'; ?>">
                            <p><label><?php _e( 'Texte du titre', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[app_name]" value="<?php echo esc_attr( $options['app_name'] ); ?>" class="regular-text"/></p>
                        </div>
                        <div class="header-image-options" style="<?php echo $options['header_logo_type'] === 'image' ? '' : 'display:none;'; ?>">
                            <p>
                                <input type="hidden" name="sidebar_jlg_settings[header_logo_image]" class="header-logo-image-url" value="<?php echo esc_attr($options['header_logo_image']); ?>">
                                <button type="button" class="button upload-logo-button"><?php _e('Choisir un logo', 'sidebar-jlg'); ?></button>
                                <span class="logo-preview"><img src="<?php echo esc_attr($options['header_logo_image']); ?>" style="<?php echo empty($options['header_logo_image']) ? 'display:none;' : ''; ?>"></span>
                            </p>
                            <p><label><?php _e( 'Largeur du logo', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[header_logo_size]" value="<?php echo esc_attr( $options['header_logo_size'] ); ?>" class="small-text"/> px</p>
                        </div>
                        <p>
                            <label><?php _e( 'Alignement sur Desktop', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[header_alignment_desktop]">
                                <option value="flex-start" <?php selected($options['header_alignment_desktop'], 'flex-start'); ?>><?php _e('Gauche', 'sidebar-jlg'); ?></option>
                                <option value="center" <?php selected($options['header_alignment_desktop'], 'center'); ?>><?php _e('Centré', 'sidebar-jlg'); ?></option>
                                <option value="flex-end" <?php selected($options['header_alignment_desktop'], 'flex-end'); ?>><?php _e('Droite', 'sidebar-jlg'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php _e( 'Alignement sur Mobile', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[header_alignment_mobile]">
                                <option value="flex-start" <?php selected($options['header_alignment_mobile'], 'flex-start'); ?>><?php _e('Gauche', 'sidebar-jlg'); ?></option>
                                <option value="center" <?php selected($options['header_alignment_mobile'], 'center'); ?>><?php _e('Centré', 'sidebar-jlg'); ?></option>
                                <option value="flex-end" <?php selected($options['header_alignment_mobile'], 'flex-end'); ?>><?php _e('Droite', 'sidebar-jlg'); ?></option>
                            </select>
                        </p>
                        <p><label><?php _e( 'Marge supérieure du header', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[header_padding_top]" value="<?php echo esc_attr( $options['header_padding_top'] ); ?>" class="small-text"/> <em class="description"><?php _e( 'Unités CSS (ex: 2.5rem, 30px).', 'sidebar-jlg' ); ?></em></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Couleur de fond (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td><?php jlg_color_picker('bg_color', $options); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Couleur d\'accentuation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <?php jlg_color_picker('accent_color', $options); ?>
                        <p class="description"><?php _e('Utilisée pour les liens actifs et certains effets.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Apparence sur Mobile', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php _e( 'Couleur de fond', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[mobile_bg_color]" value="<?php echo esc_attr( $options['mobile_bg_color'] ); ?>" class="color-picker-rgba"/></p>
                        <p>
                            <label><?php _e( 'Opacité du fond', 'sidebar-jlg' ); ?></label>
                            <input type="range" name="sidebar_jlg_settings[mobile_bg_opacity]" min="0" max="1" step="0.05" value="<?php echo esc_attr($options['mobile_bg_opacity']); ?>">
                            <span class="range-value"><?php echo esc_html($options['mobile_bg_opacity']); ?></span>
                        </p>
                        <p><label><?php _e( 'Intensité du flou', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[mobile_blur]" value="<?php echo esc_attr($options['mobile_blur']); ?>" class="small-text" /> px</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Typographie du menu', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php _e( 'Taille de police', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[font_size]" value="<?php echo esc_attr($options['font_size']); ?>" class="small-text" /> px</p>
                        <p><label><?php _e( 'Couleur du texte', 'sidebar-jlg' ); ?></label> <?php jlg_color_picker('font_color', $options); ?></p>
                        <p><label><?php _e( 'Couleur du texte (survol)', 'sidebar-jlg' ); ?></label> <?php jlg_color_picker('font_hover_color', $options); ?></p>
                    </td>
                </tr>
             </table>
        </div>

        <!-- Onglet Contenu du Menu -->
        <div id="tab-menu" class="tab-content">
            <h2><?php _e('Construire le menu', 'sidebar-jlg'); ?></h2>
            <p class="description"><?php _e('Ajoutez, organisez et supprimez les éléments de votre menu. Glissez-déposez pour réorganiser.', 'sidebar-jlg'); ?></p>
            <div id="menu-items-container"></div>
            <button type="button" class="button button-primary" id="add-menu-item"><?php _e('Ajouter un élément', 'sidebar-jlg'); ?></button>
            
            <hr style="margin: 20px 0;">

            <h2><?php _e('Alignement du Menu', 'sidebar-jlg'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Alignement sur Desktop', 'sidebar-jlg'); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[menu_alignment_desktop]">
                            <option value="flex-start" <?php selected($options['menu_alignment_desktop'], 'flex-start'); ?>><?php _e('Gauche', 'sidebar-jlg'); ?></option>
                            <option value="center" <?php selected($options['menu_alignment_desktop'], 'center'); ?>><?php _e('Centré', 'sidebar-jlg'); ?></option>
                            <option value="flex-end" <?php selected($options['menu_alignment_desktop'], 'flex-end'); ?>><?php _e('Droite', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php _e('Alignement horizontal des éléments du menu sur les écrans larges.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Alignement sur Mobile', 'sidebar-jlg'); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[menu_alignment_mobile]">
                            <option value="flex-start" <?php selected($options['menu_alignment_mobile'], 'flex-start'); ?>><?php _e('Gauche', 'sidebar-jlg'); ?></option>
                            <option value="center" <?php selected($options['menu_alignment_mobile'], 'center'); ?>><?php _e('Centré', 'sidebar-jlg'); ?></option>
                            <option value="flex-end" <?php selected($options['menu_alignment_mobile'], 'flex-end'); ?>><?php _e('Droite', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php _e('Alignement horizontal des éléments du menu sur les écrans mobiles et tablettes.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Onglet Réseaux Sociaux -->
        <div id="tab-social" class="tab-content">
            <h2><?php _e('Icônes des réseaux sociaux', 'sidebar-jlg'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Position', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[social_position]">
                            <option value="footer" <?php selected($options['social_position'], 'footer'); ?>><?php _e('En bas de la sidebar (Footer)', 'sidebar-jlg'); ?></option>
                            <option value="in-menu" <?php selected($options['social_position'], 'in-menu'); ?>><?php _e('À la suite du menu', 'sidebar-jlg'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Orientation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[social_orientation]">
                            <option value="horizontal" <?php selected($options['social_orientation'], 'horizontal'); ?>><?php _e('Horizontale', 'sidebar-jlg'); ?></option>
                            <option value="vertical" <?php selected($options['social_orientation'], 'vertical'); ?>><?php _e('Verticale', 'sidebar-jlg'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Taille des icônes', 'sidebar-jlg' ); ?></th>
                    <td>
                        <input type="number" name="sidebar_jlg_settings[social_icon_size]" value="<?php echo esc_attr( $options['social_icon_size'] ); ?>" class="small-text"/> %
                        <p class="description"><?php _e( 'Ajustez la taille des icônes des réseaux sociaux. 100% est la taille par défaut.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                 <tr>
                    <th scope="row"><?php _e( 'Icônes', 'sidebar-jlg' ); ?></th>
                    <td>
                        <div id="social-icons-container"></div>
                        <button type="button" class="button button-primary" id="add-social-icon"><?php _e('Ajouter une icône', 'sidebar-jlg'); ?></button>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Onglet Effets -->
        <div id="tab-effects" class="tab-content">
             <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Animation (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><?php _e( 'Vitesse d\'animation', 'sidebar-jlg' ); ?></label>
                            <input type="number" name="sidebar_jlg_settings[animation_speed]" value="<?php echo esc_attr($options['animation_speed']); ?>" class="small-text" /> ms
                        </p>
                        <p>
                            <label><?php _e( 'Type d\'animation', 'sidebar-jlg' ); ?></label>
                             <select name="sidebar_jlg_settings[animation_type]">
                                <option value="slide-left" <?php selected( $options['animation_type'], 'slide-left' ); ?>><?php _e( 'Glissement (Slide)', 'sidebar-jlg' ); ?></option>
                                <option value="fade" <?php selected( $options['animation_type'], 'fade' ); ?>><?php _e( 'Fondu (Fade)', 'sidebar-jlg' ); ?></option>
                                <option value="scale" <?php selected( $options['animation_type'], 'scale' ); ?>><?php _e( 'Zoom (Scale)', 'sidebar-jlg' ); ?></option>
                            </select>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Effet de survol (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[hover_effect_desktop]">
                            <option value="none" <?php selected( $options['hover_effect_desktop'], 'none' ); ?>><?php _e( 'Aucun', 'sidebar-jlg' ); ?></option>
                            <option value="tile-slide" <?php selected( $options['hover_effect_desktop'], 'tile-slide' ); ?>><?php _e( 'Tuile glissante', 'sidebar-jlg' ); ?></option>
                            <option value="underline-center" <?php selected( $options['hover_effect_desktop'], 'underline-center' ); ?>><?php _e( 'Soulignement centré', 'sidebar-jlg' ); ?></option>
                             <option value="pill-center" <?php selected( $options['hover_effect_desktop'], 'pill-center' ); ?>><?php _e( 'Pilule centrée', 'sidebar-jlg' ); ?></option>
                            <option value="spotlight" <?php selected( $options['hover_effect_desktop'], 'spotlight' ); ?>><?php _e( 'Spotlight (Projecteur)', 'sidebar-jlg' ); ?></option>
                            <option value="glossy-tilt" <?php selected( $options['hover_effect_desktop'], 'glossy-tilt' ); ?>><?php _e( 'Inclinaison 3D', 'sidebar-jlg' ); ?></option>
                            <option value="neon" <?php selected( $options['hover_effect_desktop'], 'neon' ); ?>><?php _e( 'Néon', 'sidebar-jlg' ); ?></option>
                            <option value="glow" <?php selected( $options['hover_effect_desktop'], 'glow' ); ?>><?php _e( 'Lueur (Glow)', 'sidebar-jlg' ); ?></option>
                            <option value="pulse" <?php selected( $options['hover_effect_desktop'], 'pulse' ); ?>><?php _e( 'Pulsation', 'sidebar-jlg' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Effet de survol (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[hover_effect_mobile]">
                            <option value="none" <?php selected( $options['hover_effect_mobile'], 'none' ); ?>><?php _e( 'Aucun', 'sidebar-jlg' ); ?></option>
                            <option value="tile-slide" <?php selected( $options['hover_effect_mobile'], 'tile-slide' ); ?>><?php _e( 'Tuile glissante', 'sidebar-jlg' ); ?></option>
                            <option value="underline-center" <?php selected( $options['hover_effect_mobile'], 'underline-center' ); ?>><?php _e( 'Soulignement centré', 'sidebar-jlg' ); ?></option>
                            <option value="pill-center" <?php selected( $options['hover_effect_mobile'], 'pill-center' ); ?>><?php _e( 'Pilule centrée', 'sidebar-jlg' ); ?></option>
                            <option value="spotlight" <?php selected( $options['hover_effect_mobile'], 'spotlight' ); ?>><?php _e( 'Spotlight (Projecteur)', 'sidebar-jlg' ); ?></option>
                            <option value="glossy-tilt" <?php selected( $options['hover_effect_mobile'], 'glossy-tilt' ); ?>><?php _e( 'Inclinaison 3D', 'sidebar-jlg' ); ?></option>
                            <option value="neon" <?php selected( $options['hover_effect_mobile'], 'neon' ); ?>><?php _e( 'Néon', 'sidebar-jlg' ); ?></option>
                            <option value="glow" <?php selected( $options['hover_effect_mobile'], 'glow' ); ?>><?php _e( 'Lueur (Glow)', 'sidebar-jlg' ); ?></option>
                            <option value="pulse" <?php selected( $options['hover_effect_mobile'], 'pulse' ); ?>><?php _e( 'Pulsation', 'sidebar-jlg' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top" class="neon-options-row" style="<?php echo ($options['hover_effect_desktop'] !== 'neon' && $options['hover_effect_mobile'] !== 'neon') ? 'display:none;' : ''; ?>">
                    <th scope="row"><?php _e( 'Options Néon', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label><?php _e( 'Flou:', 'sidebar-jlg' ); ?> <span class="neon-blur-value"><?php echo esc_html($options['neon_blur']); ?>px</span></label>
                        <input type="range" name="sidebar_jlg_settings[neon_blur]" min="5" max="50" value="<?php echo esc_attr( $options['neon_blur'] ); ?>">
                        <br>
                        <label><?php _e( 'Diffusion:', 'sidebar-jlg' ); ?> <span class="neon-spread-value"><?php echo esc_html($options['neon_spread']); ?>px</span></label>
                        <input type="range" name="sidebar_jlg_settings[neon_spread]" min="1" max="15" value="<?php echo esc_attr( $options['neon_spread'] ); ?>">
                    </td>
                </tr>
             </table>
        </div>
        
        <!-- Onglet Outils & Débogage -->
        <div id="tab-tools" class="tab-content">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e( 'Mode de débogage', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sidebar_jlg_settings[debug_mode]" value="1" <?php checked( $options['debug_mode'], 1 ); ?> />
                            <?php _e( 'Activer le mode de débogage.', 'sidebar-jlg' ); ?>
                        </label>
                        <p class="description"><?php _e( 'Affiche des informations utiles dans la console du navigateur (F12) pour résoudre les problèmes.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e( 'Réinitialiser les réglages', 'sidebar-jlg' ); ?></th>
                    <td>
                        <button type="button" id="reset-jlg-settings" class="button button-danger"><?php _e( 'Réinitialiser tous les réglages', 'sidebar-jlg' ); ?></button>
                        <p class="description" style="color: #d63638;"><?php _e( 'Attention : Ceci réinitialisera tous les réglages de la sidebar à leurs valeurs par défaut. Cette action est irréversible.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<!-- Templates JS -->
<script type="text/html" id="tmpl-menu-item">
    <div class="menu-item-box">
        <div class="menu-item-header">
            <span class="menu-item-handle">::</span>
            <span class="menu-item-title item-title">{{ data.label || 'Nouvel élément' }}</span>
            <button type="button" class="button-link delete-menu-item">Supprimer</button>
        </div>
        <div class="menu-item-content">
            <p><label>Label</label><input type="text" class="widefat item-label" name="sidebar_jlg_settings[menu_items][{{ data.index }}][label]" value="{{ data.label }}"></p>
            <p><label>Type de lien</label>
                <select class="widefat menu-item-type" name="sidebar_jlg_settings[menu_items][{{ data.index }}][type]">
                    <option value="custom" <# if (data.type === 'custom') { #>selected<# } #>>Lien personnalisé</option>
                    <option value="post" <# if (data.type === 'post') { #>selected<# } #>>Article</option>
                    <option value="category" <# if (data.type === 'category') { #>selected<# } #>>Catégorie</option>
                </select>
            </p>
            <div class="menu-item-value-wrapper"></div>
            <p><label>Icône</label>
                <select class="widefat menu-item-icon-type" name="sidebar_jlg_settings[menu_items][{{ data.index }}][icon_type]">
                    <option value="svg_inline" <# if (data.icon_type === 'svg_inline') { #>selected<# } #>>Icône de la bibliothèque</option>
                    <option value="svg_url" <# if (data.icon_type === 'svg_url') { #>selected<# } #>>SVG personnalisé (URL)</option>
                </select>
            </p>
            <div class="menu-item-icon-wrapper"></div>
        </div>
    </div>
</script>
<script type="text/html" id="tmpl-social-icon">
    <div class="menu-item-box">
        <div class="menu-item-header">
            <span class="menu-item-handle">::</span>
            <span class="menu-item-title item-title">{{ data.icon || 'Nouvelle icône' }}</span>
            <button type="button" class="button-link delete-social-icon">Supprimer</button>
        </div>
        <div class="menu-item-content">
            <p><label>URL</label><input type="text" class="widefat social-url" name="sidebar_jlg_settings[social_icons][{{ data.index }}][url]" value="{{ data.url }}" placeholder="https://..."></p>
            <p><label>Icône</label>
                <select class="widefat social-icon-select" name="sidebar_jlg_settings[social_icons][{{ data.index }}][icon]"></select>
                 <span class="icon-preview"></span>
            </p>
        </div>
    </div>
</script>
<?php
function jlg_color_picker($name, $options) {
    $type = $options[$name.'_type'] ?? 'solid';
    $solid_color = $options[$name] ?? '#ffffff';
    $start_color = $options[$name.'_start'] ?? '#000000';
    $end_color = $options[$name.'_end'] ?? '#ffffff';
    ?>
    <div class="color-picker-wrapper" data-color-name="<?php echo esc_attr($name); ?>">
        <p>
            <label><input type="radio" name="sidebar_jlg_settings[<?php echo $name; ?>_type]" value="solid" <?php checked($type, 'solid'); ?>> Solide</label>
            <label><input type="radio" name="sidebar_jlg_settings[<?php echo $name; ?>_type]" value="gradient" <?php checked($type, 'gradient'); ?>> Dégradé</label>
        </p>
        <div class="color-solid-field" style="<?php echo $type === 'solid' ? '' : 'display:none;'; ?>">
            <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>]" value="<?php echo esc_attr($solid_color); ?>" class="color-picker-rgba"/>
        </div>
        <div class="color-gradient-field" style="<?php echo $type === 'gradient' ? '' : 'display:none;'; ?>">
            <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>_start]" value="<?php echo esc_attr($start_color); ?>" class="color-picker-rgba"/>
            <input type="text" name="sidebar_jlg_settings[<?php echo $name; ?>_end]" value="<?php echo esc_attr($end_color); ?>" class="color-picker-rgba"/>
        </div>
    </div>
    <?php
}
?>
