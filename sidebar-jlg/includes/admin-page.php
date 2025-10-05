<?php

use JLG\Sidebar\Settings\TypographyOptions;
use JLG\Sidebar\Settings\ValueNormalizer;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! function_exists( 'sidebar_jlg_prepare_dimension_option' ) ) {
    /**
     * @param array<string, mixed> $options
     * @param array<string, mixed> $defaults
     * @param string               $key
     * @param string[]|null        $allowedUnits
     *
     * @return array{value:string,unit:string}
     */
    function sidebar_jlg_prepare_dimension_option( array $options, array $defaults, string $key, ?array $allowedUnits = null ): array {
        $default = $defaults[ $key ] ?? [];
        $current = $options[ $key ] ?? $default;

        return ValueNormalizer::normalizeDimensionStructure( $current, $current, $default, $allowedUnits );
    }
}

$fontFamilies = TypographyOptions::getFontFamilies();
$safeFontFamilies = array_filter(
    $fontFamilies,
    static fn( $font ) => isset( $font['type'] ) && $font['type'] === 'system'
);
$googleFontFamilies = array_filter(
    $fontFamilies,
    static fn( $font ) => isset( $font['type'] ) && $font['type'] === 'google'
);
$availableFontWeights = TypographyOptions::getFontWeights();
$availableTextTransforms = TypographyOptions::getTextTransformChoices();
$textTransformLabels = [
    'none'       => __( 'Aucune', 'sidebar-jlg' ),
    'uppercase'  => __( 'Majuscules', 'sidebar-jlg' ),
    'lowercase'  => __( 'Minuscules', 'sidebar-jlg' ),
    'capitalize' => __( 'Capitaliser', 'sidebar-jlg' ),
];
?>
<div class="wrap sidebar-jlg-admin-wrap">
    <h1><?php esc_html_e( 'Réglages de la Sidebar JLG', 'sidebar-jlg' ); ?></h1>

    <?php
    if ( filter_input( INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN ) ) {
        add_settings_error( 'sidebar_jlg_messages', 'sidebar_jlg_message', __( 'Réglages sauvegardés.', 'sidebar-jlg' ), 'updated' );
    }
    settings_errors( 'sidebar_jlg_messages' );
    ?>

    <div id="sidebar-jlg-js-notices"></div>

    <p><?php esc_html_e( 'Personnalisez l\'apparence et le comportement de votre sidebar.', 'sidebar-jlg' ); ?></p>
    <p><b><?php esc_html_e( 'Nouveau :', 'sidebar-jlg' ); ?></b> <?php printf( esc_html__( 'Ajoutez vos propres icônes SVG dans le dossier %1$s. Elles apparaîtront dans les listes de sélection !', 'sidebar-jlg' ), '<code>/wp-content/uploads/sidebar-jlg/icons/</code>' ); ?></p>

    <div class="nav-tab-wrapper" role="tablist">
        <a href="#tab-general" class="nav-tab nav-tab-active" id="tab-general-tab" role="tab" aria-controls="tab-general" aria-selected="true" tabindex="0"><?php esc_html_e( 'Général & Comportement', 'sidebar-jlg' ); ?></a>
        <a href="#tab-profiles" class="nav-tab" id="tab-profiles-tab" role="tab" aria-controls="tab-profiles" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Profils', 'sidebar-jlg' ); ?></a>
        <a href="#tab-presets" class="nav-tab" id="tab-presets-tab" role="tab" aria-controls="tab-presets" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Style & Préréglages', 'sidebar-jlg' ); ?></a>
        <a href="#tab-menu" class="nav-tab" id="tab-menu-tab" role="tab" aria-controls="tab-menu" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Contenu du Menu', 'sidebar-jlg' ); ?></a>
        <a href="#tab-social" class="nav-tab" id="tab-social-tab" role="tab" aria-controls="tab-social" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Réseaux Sociaux', 'sidebar-jlg' ); ?></a>
        <a href="#tab-effects" class="nav-tab" id="tab-effects-tab" role="tab" aria-controls="tab-effects" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Effets & Animations', 'sidebar-jlg' ); ?></a>
        <a href="#tab-tools" class="nav-tab" id="tab-tools-tab" role="tab" aria-controls="tab-tools" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Outils', 'sidebar-jlg' ); ?></a>
    </div>

    <div id="sidebar-jlg-preview" class="sidebar-jlg-preview" data-state="idle">
        <div class="sidebar-jlg-preview__header">
            <h2><?php esc_html_e( 'Aperçu en direct', 'sidebar-jlg' ); ?></h2>
            <p class="description"><?php esc_html_e( 'L’aperçu se met à jour automatiquement lorsque vous modifiez les réglages.', 'sidebar-jlg' ); ?></p>
        </div>
        <div class="sidebar-jlg-preview__toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Choisir la taille de prévisualisation', 'sidebar-jlg' ); ?>">
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" data-preview-size="mobile" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode mobile', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Mobile', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" data-preview-size="tablet" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode tablette', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Tablette', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button is-active" data-preview-size="desktop" aria-pressed="true">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode bureau', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Desktop', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" id="sidebar-jlg-preview-compare" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Basculer entre l’aperçu initial et l’aperçu actuel', 'sidebar-jlg' ); ?></span>
                <span class="sidebar-jlg-preview__toolbar-button-label" aria-hidden="true"><?php esc_html_e( 'Comparer', 'sidebar-jlg' ); ?></span>
            </button>
        </div>
        <div class="sidebar-jlg-preview__status" role="status" aria-live="polite"></div>
        <div class="sidebar-jlg-preview__viewport" aria-label="<?php esc_attr_e( 'Aperçu de la sidebar', 'sidebar-jlg' ); ?>"></div>
    </div>

    <form action="options.php" method="post" id="sidebar-jlg-form">
        <?php
        settings_fields( 'sidebar_jlg_options_group' );
        $defaults = $defaults ?? [];
        $options = wp_parse_args( $options ?? [], $defaults );

        $dimensionUnits = [
            'floating_vertical_margin'  => ['px', 'rem', 'em', '%', 'vh', 'vw'],
            'border_radius'            => ['px', 'rem', 'em', '%'],
            'horizontal_bar_height'    => ['px', 'rem', 'em', 'vh', 'vw'],
            'content_margin'           => ['px', 'rem', 'em', '%'],
            'hamburger_top_position'   => ['px', 'rem', 'em', 'vh', 'vw'],
            'hamburger_horizontal_offset' => ['px', 'rem', 'em', '%', 'vh', 'vw'],
            'hamburger_size'           => ['px', 'rem', 'em', '%', 'vh', 'vw'],
            'header_padding_top'       => ['px', 'rem', 'em', '%'],
            'letter_spacing'           => ['px', 'rem', 'em'],
        ];

        $dimensionValues = [];
        foreach ( $dimensionUnits as $dimensionKey => $units ) {
            $dimensionValues[ $dimensionKey ] = sidebar_jlg_prepare_dimension_option( $options, $defaults, $dimensionKey, $units );
        }

        $profilesOption = get_option( 'sidebar_jlg_profiles', [] );
        $profilesData = is_array( $profilesOption ) ? $profilesOption : [];
        $activeProfileId = get_option( 'sidebar_jlg_active_profile', '' );
        ?>

        <!-- Onglet Général -->
        <div id="tab-general" class="tab-content active" role="tabpanel" aria-labelledby="tab-general-tab" aria-hidden="false">
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Activation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label class="jlg-switch">
                            <input type="checkbox" name="sidebar_jlg_settings[enable_sidebar]" value="1" <?php checked( $options['enable_sidebar'], 1 ); ?> />
                            <span class="jlg-slider"></span>
                        </label>
                        <p class="description"><?php esc_html_e( 'Active ou désactive complètement la sidebar sur votre site.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Style d\'affichage (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><input type="radio" name="sidebar_jlg_settings[layout_style]" value="full" <?php checked($options['layout_style'], 'full'); ?>> <?php esc_html_e('Pleine hauteur', 'sidebar-jlg'); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[layout_style]" value="floating" <?php checked($options['layout_style'], 'floating'); ?>> <?php esc_html_e('Flottant', 'sidebar-jlg'); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[layout_style]" value="horizontal-bar" <?php checked($options['layout_style'], 'horizontal-bar'); ?>> <?php esc_html_e('Barre horizontale', 'sidebar-jlg'); ?></label>
                        </p>
                        <div class="floating-options-field" style="<?php echo $options['layout_style'] === 'floating' ? '' : 'display:none;'; ?>">
                            <?php $floatingMargin = $dimensionValues['floating_vertical_margin']; ?>
                            <p>
                                <label><?php esc_html_e( 'Marge verticale', 'sidebar-jlg' ); ?></label>
                                <div
                                    class="sidebar-jlg-unit-control"
                                    data-sidebar-unit-control
                                    data-setting-name="sidebar_jlg_settings[floating_vertical_margin]"
                                    data-label="<?php esc_attr_e( 'Marge verticale', 'sidebar-jlg' ); ?>"
                                    data-help="<?php esc_attr_e( 'Définit la distance entre la sidebar flottante et le bord de l’écran.', 'sidebar-jlg' ); ?>"
                                    data-error-message="<?php esc_attr_e( 'La marge verticale ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                    data-default-value="<?php echo esc_attr( $defaults['floating_vertical_margin']['value'] ?? '4' ); ?>"
                                    data-default-unit="<?php echo esc_attr( $defaults['floating_vertical_margin']['unit'] ?? 'rem' ); ?>"
                                    data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['floating_vertical_margin'] ) ); ?>"
                                >
                                    <input type="hidden" data-dimension-value name="sidebar_jlg_settings[floating_vertical_margin][value]" value="<?php echo esc_attr( $floatingMargin['value'] ); ?>" />
                                    <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[floating_vertical_margin][unit]" value="<?php echo esc_attr( $floatingMargin['unit'] ); ?>" />
                                </div>
                                <em class="description"><?php esc_html_e( 'Ex: 4rem, 15px', 'sidebar-jlg' ); ?></em>
                            </p>
                            <?php $borderRadius = $dimensionValues['border_radius']; ?>
                            <p>
                                <label><?php esc_html_e( 'Arrondi des coins', 'sidebar-jlg' ); ?></label>
                                <div
                                    class="sidebar-jlg-unit-control"
                                    data-sidebar-unit-control
                                    data-setting-name="sidebar_jlg_settings[border_radius]"
                                    data-label="<?php esc_attr_e( 'Arrondi des coins', 'sidebar-jlg' ); ?>"
                                    data-help="<?php esc_attr_e( 'Contrôle la courbure des angles de la sidebar.', 'sidebar-jlg' ); ?>"
                                    data-error-message="<?php esc_attr_e( 'L’arrondi ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                    data-default-value="<?php echo esc_attr( $defaults['border_radius']['value'] ?? '12' ); ?>"
                                    data-default-unit="<?php echo esc_attr( $defaults['border_radius']['unit'] ?? 'px' ); ?>"
                                    data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['border_radius'] ) ); ?>"
                                >
                                    <input type="hidden" data-dimension-value name="sidebar_jlg_settings[border_radius][value]" value="<?php echo esc_attr( $borderRadius['value'] ); ?>" />
                                    <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[border_radius][unit]" value="<?php echo esc_attr( $borderRadius['unit'] ); ?>" />
                                </div>
                                <em class="description"><?php esc_html_e( 'Ex: 12px, 1rem', 'sidebar-jlg' ); ?></em>
                            </p>
                            <p><label><?php esc_html_e( 'Épaisseur de la bordure', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[border_width]" value="<?php echo esc_attr( $options['border_width'] ); ?>" class="small-text"/> px</p>
                            <p><label><?php esc_html_e( 'Couleur de la bordure', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[border_color]" value="<?php echo esc_attr( $options['border_color'] ); ?>" class="color-picker-rgba"/></p>
                        </div>
                        <div class="horizontal-options-field" style="<?php echo $options['layout_style'] === 'horizontal-bar' ? '' : 'display:none;'; ?>">
                            <?php $horizontalHeight = $dimensionValues['horizontal_bar_height']; ?>
                            <p>
                                <label><?php esc_html_e( 'Hauteur de la barre', 'sidebar-jlg' ); ?></label>
                                <div
                                    class="sidebar-jlg-unit-control"
                                    data-sidebar-unit-control
                                    data-setting-name="sidebar_jlg_settings[horizontal_bar_height]"
                                    data-label="<?php esc_attr_e( 'Hauteur de la barre', 'sidebar-jlg' ); ?>"
                                    data-help="<?php esc_attr_e( 'Détermine la hauteur de la barre horizontale.', 'sidebar-jlg' ); ?>"
                                    data-error-message="<?php esc_attr_e( 'La hauteur de barre ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                    data-default-value="<?php echo esc_attr( $defaults['horizontal_bar_height']['value'] ?? '4' ); ?>"
                                    data-default-unit="<?php echo esc_attr( $defaults['horizontal_bar_height']['unit'] ?? 'rem' ); ?>"
                                    data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['horizontal_bar_height'] ) ); ?>"
                                >
                                    <input type="hidden" data-dimension-value name="sidebar_jlg_settings[horizontal_bar_height][value]" value="<?php echo esc_attr( $horizontalHeight['value'] ); ?>" />
                                    <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[horizontal_bar_height][unit]" value="<?php echo esc_attr( $horizontalHeight['unit'] ); ?>" />
                                </div>
                                <em class="description"><?php esc_html_e( 'Utilisez des unités CSS (ex : 4rem, 72px).', 'sidebar-jlg' ); ?></em>
                            </p>
                            <p>
                                <label><?php esc_html_e( 'Position sur l\'écran', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[horizontal_bar_position]">
                                    <option value="top" <?php selected($options['horizontal_bar_position'], 'top'); ?>><?php esc_html_e('En haut (Top)', 'sidebar-jlg'); ?></option>
                                    <option value="bottom" <?php selected($options['horizontal_bar_position'], 'bottom'); ?>><?php esc_html_e('En bas (Bottom)', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                            <p>
                                <label><?php esc_html_e( 'Alignement du contenu', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[horizontal_bar_alignment]">
                                    <option value="flex-start" <?php selected($options['horizontal_bar_alignment'], 'flex-start'); ?>><?php esc_html_e('Aligné à gauche', 'sidebar-jlg'); ?></option>
                                    <option value="center" <?php selected($options['horizontal_bar_alignment'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                                    <option value="flex-end" <?php selected($options['horizontal_bar_alignment'], 'flex-end'); ?>><?php esc_html_e('Aligné à droite', 'sidebar-jlg'); ?></option>
                                    <option value="space-between" <?php selected($options['horizontal_bar_alignment'], 'space-between'); ?>><?php esc_html_e('Espacé (Space-between)', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                            <p>
                                <label><input type="checkbox" name="sidebar_jlg_settings[horizontal_bar_sticky]" value="1" <?php checked( ! empty( $options['horizontal_bar_sticky'] ) ); ?>> <?php esc_html_e( 'Rendre la barre collante (reste visible en scrollant)', 'sidebar-jlg' ); ?></label>
                            </p>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Orientation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><input type="radio" name="sidebar_jlg_settings[sidebar_position]" value="left" <?php checked($options['sidebar_position'], 'left'); ?>> <?php esc_html_e( 'Alignée à gauche', 'sidebar-jlg' ); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[sidebar_position]" value="right" <?php checked($options['sidebar_position'], 'right'); ?>> <?php esc_html_e( 'Alignée à droite', 'sidebar-jlg' ); ?></label>
                        </p>
                        <p class="description"><?php esc_html_e( 'Choisissez le côté d\'affichage de la sidebar et du bouton hamburger.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Comportement sur Desktop', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[desktop_behavior]" class="desktop-behavior-select">
                            <option value="push" <?php selected($options['desktop_behavior'], 'push'); ?>><?php esc_html_e('Pousser le contenu (Push)', 'sidebar-jlg'); ?></option>
                            <option value="overlay" <?php selected($options['desktop_behavior'], 'overlay'); ?>><?php esc_html_e('Superposer au contenu (Overlay)', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Choisissez si la sidebar pousse le contenu de votre site ou passe par-dessus.', 'sidebar-jlg'); ?></p>
                        <?php $contentMargin = $dimensionValues['content_margin']; ?>
                        <div class="push-option-field" style="<?php echo $options['desktop_behavior'] === 'push' ? '' : 'display:none;'; ?>">
                            <label><?php esc_html_e( 'Marge de sécurité du contenu', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[content_margin]"
                                data-label="<?php esc_attr_e( 'Marge de sécurité du contenu', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Évite que la sidebar ne chevauche votre mise en page.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'La marge de contenu ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['content_margin']['value'] ?? '2' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['content_margin']['unit'] ?? 'rem' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['content_margin'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[content_margin][value]" value="<?php echo esc_attr( $contentMargin['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[content_margin][unit]" value="<?php echo esc_attr( $contentMargin['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Espace entre la sidebar et le contenu (ex: 2rem, 30px).', 'sidebar-jlg' ); ?></em>
                        </div>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Fond de superposition', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php esc_html_e( 'Couleur', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[overlay_color]" value="<?php echo esc_attr( $options['overlay_color'] ); ?>" class="color-picker-rgba"/></p>
                        <div class="sidebar-jlg-range-field">
                            <label><?php esc_html_e( 'Opacité', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-range-control"
                                data-sidebar-range-control
                                data-setting-name="sidebar_jlg_settings[overlay_opacity]"
                                data-label="<?php esc_attr_e( 'Opacité de la superposition', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( '0 = totalement transparent, 1 = totalement opaque.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'L’opacité doit rester comprise entre 0 et 1.', 'sidebar-jlg' ); ?>"
                                data-min="0"
                                data-max="1"
                                data-step="0.05"
                            >
                                <input type="hidden" data-range-value name="sidebar_jlg_settings[overlay_opacity]" value="<?php echo esc_attr( $options['overlay_opacity'] ); ?>" />
                            </div>
                        </div>
                        <p class="description"><?php esc_html_e( 'Ajuste le fond affiché derrière la sidebar en mode overlay.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Dimensions', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php esc_html_e( 'Largeur (Desktop)', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[width_desktop]" value="<?php echo esc_attr( $options['width_desktop'] ); ?>" class="small-text"/> px</p>
                        <p><label><?php esc_html_e( 'Largeur (Tablette)', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[width_tablet]" value="<?php echo esc_attr( $options['width_tablet'] ); ?>" class="small-text"/> px <em class="description"><?php esc_html_e( 'Appliquée entre 768px et 992px.', 'sidebar-jlg' ); ?></em></p>
                        <p>
                            <label><?php esc_html_e( 'Largeur (Mobile)', 'sidebar-jlg' ); ?></label>
                            <input type="text" name="sidebar_jlg_settings[width_mobile]" value="<?php echo esc_attr( $options['width_mobile'] ); ?>" class="small-text" />
                            <em class="description"><?php esc_html_e( '100 % par défaut pour couvrir l’écran. Accepte toute valeur CSS (320px, 85%, calc(100% - 2rem)…) appliquée sous 768px.', 'sidebar-jlg' ); ?></em>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Bouton Hamburger (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <?php $hamburgerOffset = $dimensionValues['hamburger_top_position']; ?>
                        <p>
                            <label><?php esc_html_e( 'Position verticale', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[hamburger_top_position]"
                                data-label="<?php esc_attr_e( 'Position verticale', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Contrôle l’emplacement du bouton hamburger sur l’axe vertical.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'La position verticale ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['hamburger_top_position']['value'] ?? '4' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['hamburger_top_position']['unit'] ?? 'rem' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['hamburger_top_position'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[hamburger_top_position][value]" value="<?php echo esc_attr( $hamburgerOffset['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[hamburger_top_position][unit]" value="<?php echo esc_attr( $hamburgerOffset['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Unités CSS (ex: 4rem, 15px).', 'sidebar-jlg' ); ?></em>
                        </p>
                        <?php $hamburgerInlineOffset = $dimensionValues['hamburger_horizontal_offset']; ?>
                        <p>
                            <label><?php esc_html_e( 'Décalage horizontal', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[hamburger_horizontal_offset]"
                                data-label="<?php esc_attr_e( 'Décalage horizontal', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Définit la distance entre le bord de l’écran et le bouton hamburger.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'Le décalage horizontal ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['hamburger_horizontal_offset']['value'] ?? '15' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['hamburger_horizontal_offset']['unit'] ?? 'px' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['hamburger_horizontal_offset'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[hamburger_horizontal_offset][value]" value="<?php echo esc_attr( $hamburgerInlineOffset['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[hamburger_horizontal_offset][unit]" value="<?php echo esc_attr( $hamburgerInlineOffset['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Unités CSS (ex: 15px, 2rem).', 'sidebar-jlg' ); ?></em>
                        </p>
                        <?php $hamburgerSize = $dimensionValues['hamburger_size']; ?>
                        <p>
                            <label><?php esc_html_e( 'Taille du bouton', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[hamburger_size]"
                                data-label="<?php esc_attr_e( 'Taille du bouton', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Contrôle la largeur et la hauteur du bouton hamburger.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'La taille du bouton ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['hamburger_size']['value'] ?? '50' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['hamburger_size']['unit'] ?? 'px' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['hamburger_size'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[hamburger_size][value]" value="<?php echo esc_attr( $hamburgerSize['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[hamburger_size][unit]" value="<?php echo esc_attr( $hamburgerSize['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Unités CSS (ex: 50px, 3rem).', 'sidebar-jlg' ); ?></em>
                        </p>
                        <p><label><?php esc_html_e( 'Couleur des barres', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[hamburger_color]" value="<?php echo esc_attr( $options['hamburger_color'] ); ?>" class="color-picker-rgba"/> <em class="description"><?php esc_html_e( 'Utilisez une couleur contrastée pour les barres du bouton.', 'sidebar-jlg' ); ?></em></p>
                        <p><label><input type="checkbox" name="sidebar_jlg_settings[show_close_button]" value="1" <?php checked( $options['show_close_button'], 1 ); ?> /> <?php esc_html_e( 'Afficher le bouton de fermeture (X) dans la sidebar.', 'sidebar-jlg' ); ?></label></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Fermeture automatique', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="sidebar_jlg_settings[close_on_link_click]" value="1" <?php checked( $options['close_on_link_click'], 1 ); ?> /> <?php esc_html_e( 'Fermer automatiquement la sidebar après un clic sur un lien ou une icône sociale.', 'sidebar-jlg' ); ?></label>
                        <p class="description"><?php esc_html_e( 'Recommandé sur mobile pour éviter qu\'elle reste ouverte après la navigation.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Libellé ARIA de la navigation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <input type="text" class="regular-text" name="sidebar_jlg_settings[nav_aria_label]" value="<?php echo esc_attr( $options['nav_aria_label'] ?? '' ); ?>" />
                        <p class="description"><?php esc_html_e( 'Définit le texte de l’attribut aria-label du bloc de navigation pour les lecteurs d’écran.', 'sidebar-jlg' ); ?></p>
                        <p class="description"><?php esc_html_e( 'Laissez vide pour utiliser automatiquement la traduction fournie par le plugin.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Libellés du bouton de sous-menu', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label for="sidebar-jlg-toggle-open-label"><?php esc_html_e( 'Texte lorsque le sous-menu est fermé', 'sidebar-jlg' ); ?></label>
                            <input type="text" id="sidebar-jlg-toggle-open-label" name="sidebar_jlg_settings[toggle_open_label]" value="<?php echo esc_attr( $options['toggle_open_label'] ?? '' ); ?>" class="regular-text" />
                        </p>
                        <p>
                            <label for="sidebar-jlg-toggle-close-label"><?php esc_html_e( 'Texte lorsque le sous-menu est ouvert', 'sidebar-jlg' ); ?></label>
                            <input type="text" id="sidebar-jlg-toggle-close-label" name="sidebar_jlg_settings[toggle_close_label]" value="<?php echo esc_attr( $options['toggle_close_label'] ?? '' ); ?>" class="regular-text" />
                        </p>
                        <p class="description"><?php esc_html_e( 'Ces textes alimentent les attributs aria-label et la mention pour les lecteurs d’écran. Laissez vide pour conserver les libellés traduits par défaut.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Barre de recherche', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label><input type="checkbox" name="sidebar_jlg_settings[enable_search]" value="1" <?php checked( $options['enable_search'], 1 ); ?> /> <?php esc_html_e( 'Activer la barre de recherche.', 'sidebar-jlg' ); ?></label>
                        <div class="search-options-wrapper" style="<?php echo $options['enable_search'] ? '' : 'display:none;'; ?>">
                            <p>
                                <label><?php esc_html_e( 'Méthode d\'intégration :', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[search_method]" class="search-method-select">
                                    <option value="default" <?php selected($options['search_method'], 'default'); ?>><?php esc_html_e('Recherche WordPress par défaut', 'sidebar-jlg'); ?></option>
                                    <option value="shortcode" <?php selected($options['search_method'], 'shortcode'); ?>><?php esc_html_e('Shortcode personnalisé', 'sidebar-jlg'); ?></option>
                                    <option value="hook" <?php selected($options['search_method'], 'hook'); ?>><?php esc_html_e('Hook PHP (avancé)', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                            <p class="search-method-field search-shortcode-field" style="display:none;">
                                <label><?php esc_html_e( 'Shortcode :', 'sidebar-jlg' ); ?></label>
                                <input type="text" name="sidebar_jlg_settings[search_shortcode]" value="<?php echo esc_attr( $options['search_shortcode'] ); ?>" class="regular-text" placeholder="[mon_shortcode_recherche]"/>
                            </p>
                             <p class="search-method-field search-hook-field" style="display:none;">
                                <span class="description"><?php esc_html_e( 'Pour les moteurs de recherche complexes, ajoutez ce code à votre fichier `functions.php` :', 'sidebar-jlg' ); ?></span><br>
                                <code>add_action('jlg_sidebar_search_area', function() { /* Votre code PHP ici */ });</code>
                            </p>
                            <p>
                                <label><?php esc_html_e( 'Alignement de la recherche', 'sidebar-jlg' ); ?></label>
                                <select name="sidebar_jlg_settings[search_alignment]">
                                    <option value="flex-start" <?php selected($options['search_alignment'], 'flex-start'); ?>><?php esc_html_e('Gauche', 'sidebar-jlg'); ?></option>
                                    <option value="center" <?php selected($options['search_alignment'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                                    <option value="flex-end" <?php selected($options['search_alignment'], 'flex-end'); ?>><?php esc_html_e('Droite', 'sidebar-jlg'); ?></option>
                                </select>
                            </p>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        
        <!-- Onglet Style & Préréglages -->
        <div id="tab-presets" class="tab-content" role="tabpanel" aria-labelledby="tab-presets-tab" aria-hidden="true" hidden>
             <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Préréglage de style', 'sidebar-jlg' ); ?></th>
                    <td>
                        <input type="hidden" name="sidebar_jlg_settings[style_preset]" id="sidebar-jlg-style-preset" value="<?php echo esc_attr( $options['style_preset'] ?? 'custom' ); ?>" disabled>
                        <div id="sidebar-jlg-style-presets" class="sidebar-jlg-style-presets" data-selected-preset="<?php echo esc_attr( $options['style_preset'] ?? 'custom' ); ?>">
                            <p class="description"><?php esc_html_e( 'Choisissez un préréglage pour remplir automatiquement les couleurs, la typographie et les effets.', 'sidebar-jlg' ); ?></p>
                            <div class="sidebar-jlg-style-presets__grid" data-style-preset-grid>
                                <p class="sidebar-jlg-style-presets__placeholder"><?php esc_html_e( 'Chargement des préréglages…', 'sidebar-jlg' ); ?></p>
                            </div>
                            <p class="sidebar-jlg-style-presets__empty" data-style-preset-empty hidden><?php esc_html_e( 'Aucun préréglage n’est disponible pour le moment.', 'sidebar-jlg' ); ?></p>
                            <noscript>
                                <p><?php esc_html_e( 'JavaScript est nécessaire pour parcourir les préréglages interactifs. Utilisez la liste déroulante ci-dessous.', 'sidebar-jlg' ); ?></p>
                                <select name="sidebar_jlg_settings[style_preset]" id="style-preset-select-fallback">
                                    <option value="custom" <?php selected($options['style_preset'], 'custom'); ?>><?php esc_html_e('Personnalisé', 'sidebar-jlg'); ?></option>
                                    <?php if ( ! empty( $stylePresets ) && is_array( $stylePresets ) ) : ?>
                                        <?php foreach ( $stylePresets as $presetKey => $presetData ) : ?>
                                            <?php
                                            $presetLabel = '';
                                            if ( is_array( $presetData ) && isset( $presetData['label'] ) && is_string( $presetData['label'] ) ) {
                                                $presetLabel = __($presetData['label'], 'sidebar-jlg');
                                            }
                                            ?>
                                            <option value="<?php echo esc_attr( $presetKey ); ?>" <?php selected( $options['style_preset'], $presetKey ); ?>><?php echo esc_html( $presetLabel !== '' ? $presetLabel : $presetKey ); ?></option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </noscript>
                        </div>
                        <p class="description"><?php esc_html_e( 'Les réglages appliqués peuvent ensuite être ajustés individuellement ci-dessous.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'En-tête (Logo/Titre)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><input type="radio" name="sidebar_jlg_settings[header_logo_type]" value="text" <?php checked($options['header_logo_type'], 'text'); ?>> <?php esc_html_e('Afficher un titre textuel', 'sidebar-jlg'); ?></label>
                            <br>
                            <label><input type="radio" name="sidebar_jlg_settings[header_logo_type]" value="image" <?php checked($options['header_logo_type'], 'image'); ?>> <?php esc_html_e('Afficher une image (logo)', 'sidebar-jlg'); ?></label>
                        </p>
                        <div class="header-text-options" style="<?php echo $options['header_logo_type'] === 'text' ? '' : 'display:none;'; ?>">
                            <p><label><?php esc_html_e( 'Texte du titre', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[app_name]" value="<?php echo esc_attr( $options['app_name'] ); ?>" class="regular-text"/></p>
                        </div>
                        <div class="header-image-options" style="<?php echo $options['header_logo_type'] === 'image' ? '' : 'display:none;'; ?>">
                            <p>
                                <input type="hidden" name="sidebar_jlg_settings[header_logo_image]" class="header-logo-image-url" value="<?php echo esc_url($options['header_logo_image']); ?>">
                                <button type="button" class="button upload-logo-button"><?php esc_html_e('Choisir un logo', 'sidebar-jlg'); ?></button>
                                <span class="logo-preview"><img src="<?php echo esc_url($options['header_logo_image']); ?>" style="<?php echo empty($options['header_logo_image']) ? 'display:none;' : ''; ?>"></span>
                            </p>
                            <p><label><?php esc_html_e( 'Largeur du logo', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[header_logo_size]" value="<?php echo esc_attr( $options['header_logo_size'] ); ?>" class="small-text"/> px</p>
                        </div>
                        <p>
                            <label><?php esc_html_e( 'Alignement sur Desktop', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[header_alignment_desktop]">
                                <option value="flex-start" <?php selected($options['header_alignment_desktop'], 'flex-start'); ?>><?php esc_html_e('Gauche', 'sidebar-jlg'); ?></option>
                                <option value="center" <?php selected($options['header_alignment_desktop'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                                <option value="flex-end" <?php selected($options['header_alignment_desktop'], 'flex-end'); ?>><?php esc_html_e('Droite', 'sidebar-jlg'); ?></option>
                            </select>
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Alignement sur Mobile', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[header_alignment_mobile]">
                                <option value="flex-start" <?php selected($options['header_alignment_mobile'], 'flex-start'); ?>><?php esc_html_e('Gauche', 'sidebar-jlg'); ?></option>
                                <option value="center" <?php selected($options['header_alignment_mobile'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                                <option value="flex-end" <?php selected($options['header_alignment_mobile'], 'flex-end'); ?>><?php esc_html_e('Droite', 'sidebar-jlg'); ?></option>
                            </select>
                        </p>
                        <?php $headerPadding = $dimensionValues['header_padding_top']; ?>
                        <p>
                            <label><?php esc_html_e( 'Marge supérieure du header', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[header_padding_top]"
                                data-label="<?php esc_attr_e( 'Marge supérieure du header', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Ajuste l’espace au-dessus du bloc d’en-tête.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'La marge supérieure ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['header_padding_top']['value'] ?? '2.5' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['header_padding_top']['unit'] ?? 'rem' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['header_padding_top'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[header_padding_top][value]" value="<?php echo esc_attr( $headerPadding['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[header_padding_top][unit]" value="<?php echo esc_attr( $headerPadding['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Unités CSS (ex: 2.5rem, 30px).', 'sidebar-jlg' ); ?></em>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Couleur de fond (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td><?php $colorPicker->render('bg_color', $options); ?></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Couleur d\'accentuation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <?php $colorPicker->render('accent_color', $options); ?>
                        <p class="description"><?php esc_html_e('Utilisée pour les liens actifs et certains effets.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Apparence sur Mobile', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php esc_html_e( 'Couleur de fond', 'sidebar-jlg' ); ?></label> <input type="text" name="sidebar_jlg_settings[mobile_bg_color]" value="<?php echo esc_attr( $options['mobile_bg_color'] ); ?>" class="color-picker-rgba"/></p>
                        <div class="sidebar-jlg-range-field">
                            <label><?php esc_html_e( 'Opacité du fond', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-range-control"
                                data-sidebar-range-control
                                data-setting-name="sidebar_jlg_settings[mobile_bg_opacity]"
                                data-label="<?php esc_attr_e( 'Opacité du fond mobile', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( '0 = totalement transparent, 1 = totalement opaque.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'L’opacité doit rester comprise entre 0 et 1.', 'sidebar-jlg' ); ?>"
                                data-min="0"
                                data-max="1"
                                data-step="0.05"
                            >
                                <input type="hidden" data-range-value name="sidebar_jlg_settings[mobile_bg_opacity]" value="<?php echo esc_attr( $options['mobile_bg_opacity'] ); ?>" />
                            </div>
                        </div>
                        <p><label><?php esc_html_e( 'Intensité du flou', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[mobile_blur]" value="<?php echo esc_attr($options['mobile_blur']); ?>" class="small-text" /> px</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Typographie du menu', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p><label><?php esc_html_e( 'Taille de police', 'sidebar-jlg' ); ?></label> <input type="number" name="sidebar_jlg_settings[font_size]" value="<?php echo esc_attr($options['font_size']); ?>" class="small-text" /> px</p>
                        <p>
                            <label><?php esc_html_e( 'Famille de police', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[font_family]">
                                <?php if ( ! empty( $safeFontFamilies ) ) : ?>
                                    <optgroup label="<?php esc_attr_e( 'Polices système', 'sidebar-jlg' ); ?>">
                                        <?php foreach ( $safeFontFamilies as $fontKey => $fontData ) : ?>
                                            <option value="<?php echo esc_attr( $fontKey ); ?>" data-font-stack="<?php echo esc_attr( $fontData['stack'] ?? '' ); ?>" <?php selected( $options['font_family'], $fontKey ); ?>><?php echo esc_html( $fontData['label'] ?? $fontKey ); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                                <?php if ( ! empty( $googleFontFamilies ) ) : ?>
                                    <optgroup label="<?php esc_attr_e( 'Google Fonts', 'sidebar-jlg' ); ?>">
                                        <?php foreach ( $googleFontFamilies as $fontKey => $fontData ) : ?>
                                            <option value="<?php echo esc_attr( $fontKey ); ?>" data-font-stack="<?php echo esc_attr( $fontData['stack'] ?? '' ); ?>" <?php selected( $options['font_family'], $fontKey ); ?>><?php echo esc_html( $fontData['label'] ?? $fontKey ); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Graisse', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[font_weight]" class="small-text">
                                <?php foreach ( $availableFontWeights as $weight ) : ?>
                                    <option value="<?php echo esc_attr( $weight ); ?>" <?php selected( $options['font_weight'], $weight ); ?>><?php echo esc_html( $weight ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <?php $letterSpacing = $dimensionValues['letter_spacing']; ?>
                        <p>
                            <label><?php esc_html_e( 'Espacement des lettres', 'sidebar-jlg' ); ?></label>
                            <div
                                class="sidebar-jlg-unit-control"
                                data-sidebar-unit-control
                                data-setting-name="sidebar_jlg_settings[letter_spacing]"
                                data-label="<?php esc_attr_e( 'Espacement des lettres', 'sidebar-jlg' ); ?>"
                                data-help="<?php esc_attr_e( 'Contrôle la distance entre chaque lettre du menu.', 'sidebar-jlg' ); ?>"
                                data-error-message="<?php esc_attr_e( 'L’espacement des lettres ne peut pas être vide.', 'sidebar-jlg' ); ?>"
                                data-default-value="<?php echo esc_attr( $defaults['letter_spacing']['value'] ?? '0' ); ?>"
                                data-default-unit="<?php echo esc_attr( $defaults['letter_spacing']['unit'] ?? 'em' ); ?>"
                                data-allowed-units="<?php echo esc_attr( wp_json_encode( $dimensionUnits['letter_spacing'] ) ); ?>"
                            >
                                <input type="hidden" data-dimension-value name="sidebar_jlg_settings[letter_spacing][value]" value="<?php echo esc_attr( $letterSpacing['value'] ); ?>" />
                                <input type="hidden" data-dimension-unit name="sidebar_jlg_settings[letter_spacing][unit]" value="<?php echo esc_attr( $letterSpacing['unit'] ); ?>" />
                            </div>
                            <em class="description"><?php esc_html_e( 'Sélectionnez une valeur numérique et l’unité adaptée (`px`, `em` ou `rem`).', 'sidebar-jlg' ); ?></em>
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Casse du texte', 'sidebar-jlg' ); ?></label>
                            <select name="sidebar_jlg_settings[text_transform]">
                                <?php foreach ( $availableTextTransforms as $transform ) : ?>
                                    <option value="<?php echo esc_attr( $transform ); ?>" <?php selected( $options['text_transform'], $transform ); ?>><?php echo esc_html( $textTransformLabels[ $transform ] ?? $transform ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </p>
                        <p><label><?php esc_html_e( 'Couleur du texte', 'sidebar-jlg' ); ?></label> <?php $colorPicker->render('font_color', $options); ?></p>
                        <p><label><?php esc_html_e( 'Couleur du texte (survol)', 'sidebar-jlg' ); ?></label> <?php $colorPicker->render('font_hover_color', $options); ?></p>
                    </td>
                </tr>
             </table>
        </div>

        <!-- Onglet Contenu du Menu -->
        <div id="tab-menu" class="tab-content" role="tabpanel" aria-labelledby="tab-menu-tab" aria-hidden="true" hidden>
            <h2><?php esc_html_e('Construire le menu', 'sidebar-jlg'); ?></h2>
            <p class="description"><?php esc_html_e('Ajoutez, organisez et supprimez les éléments de votre menu. Glissez-déposez pour réorganiser.', 'sidebar-jlg'); ?></p>
            <div id="menu-items-container"></div>
            <button type="button" class="button button-primary" id="add-menu-item"><?php esc_html_e('Ajouter un élément', 'sidebar-jlg'); ?></button>

            <div class="sidebar-jlg-custom-icon-upload">
                <button type="button" class="button sidebar-jlg-upload-svg" data-context="menu">
                    <?php esc_html_e('Téléverser un SVG', 'sidebar-jlg'); ?>
                </button>
                <span class="sidebar-jlg-upload-feedback" role="status" aria-live="polite"></span>
            </div>

            <hr style="margin: 20px 0;">

            <h2><?php esc_html_e('Alignement du Menu', 'sidebar-jlg'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Alignement sur Desktop', 'sidebar-jlg'); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[menu_alignment_desktop]">
                            <option value="flex-start" <?php selected($options['menu_alignment_desktop'], 'flex-start'); ?>><?php esc_html_e('Gauche', 'sidebar-jlg'); ?></option>
                            <option value="center" <?php selected($options['menu_alignment_desktop'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                            <option value="flex-end" <?php selected($options['menu_alignment_desktop'], 'flex-end'); ?>><?php esc_html_e('Droite', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Alignement horizontal des éléments du menu sur les écrans larges.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Alignement sur Mobile', 'sidebar-jlg'); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[menu_alignment_mobile]">
                            <option value="flex-start" <?php selected($options['menu_alignment_mobile'], 'flex-start'); ?>><?php esc_html_e('Gauche', 'sidebar-jlg'); ?></option>
                            <option value="center" <?php selected($options['menu_alignment_mobile'], 'center'); ?>><?php esc_html_e('Centré', 'sidebar-jlg'); ?></option>
                            <option value="flex-end" <?php selected($options['menu_alignment_mobile'], 'flex-end'); ?>><?php esc_html_e('Droite', 'sidebar-jlg'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Alignement horizontal des éléments du menu sur les écrans mobiles et tablettes.', 'sidebar-jlg'); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Onglet Réseaux Sociaux -->
        <div id="tab-social" class="tab-content" role="tabpanel" aria-labelledby="tab-social-tab" aria-hidden="true" hidden>
            <h2><?php esc_html_e('Icônes des réseaux sociaux', 'sidebar-jlg'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Position', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[social_position]">
                            <option value="footer" <?php selected($options['social_position'], 'footer'); ?>><?php esc_html_e('En bas de la sidebar (Footer)', 'sidebar-jlg'); ?></option>
                            <option value="in-menu" <?php selected($options['social_position'], 'in-menu'); ?>><?php esc_html_e('À la suite du menu', 'sidebar-jlg'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Orientation', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[social_orientation]">
                            <option value="horizontal" <?php selected($options['social_orientation'], 'horizontal'); ?>><?php esc_html_e('Horizontale', 'sidebar-jlg'); ?></option>
                            <option value="vertical" <?php selected($options['social_orientation'], 'vertical'); ?>><?php esc_html_e('Verticale', 'sidebar-jlg'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Taille des icônes', 'sidebar-jlg' ); ?></th>
                    <td>
                        <input type="number" name="sidebar_jlg_settings[social_icon_size]" value="<?php echo esc_attr( $options['social_icon_size'] ); ?>" class="small-text"/> %
                        <p class="description"><?php esc_html_e( 'Ajustez la taille des icônes des réseaux sociaux. 100% est la taille par défaut.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                 <tr>
                    <th scope="row"><?php esc_html_e( 'Icônes', 'sidebar-jlg' ); ?></th>
                 <td>
                        <div id="social-icons-container"></div>
                        <button type="button" class="button button-primary" id="add-social-icon"><?php esc_html_e('Ajouter une icône', 'sidebar-jlg'); ?></button>
                        <div class="sidebar-jlg-custom-icon-upload">
                            <button type="button" class="button sidebar-jlg-upload-svg" data-context="social">
                                <?php esc_html_e('Téléverser un SVG', 'sidebar-jlg'); ?>
                            </button>
                            <span class="sidebar-jlg-upload-feedback" role="status" aria-live="polite"></span>
                        </div>
                 </td>
                </tr>
            </table>
        </div>

        <!-- Onglet Effets -->
        <div id="tab-effects" class="tab-content" role="tabpanel" aria-labelledby="tab-effects-tab" aria-hidden="true" hidden>
             <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Animation (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <p>
                            <label><?php esc_html_e( 'Vitesse d\'animation', 'sidebar-jlg' ); ?></label>
                            <input type="number" name="sidebar_jlg_settings[animation_speed]" value="<?php echo esc_attr($options['animation_speed']); ?>" class="small-text" /> ms
                        </p>
                        <p>
                            <label><?php esc_html_e( 'Type d\'animation', 'sidebar-jlg' ); ?></label>
                             <select name="sidebar_jlg_settings[animation_type]">
                                <option value="slide-left" <?php selected( $options['animation_type'], 'slide-left' ); ?>><?php esc_html_e( 'Glissement (Slide)', 'sidebar-jlg' ); ?></option>
                                <option value="fade" <?php selected( $options['animation_type'], 'fade' ); ?>><?php esc_html_e( 'Fondu (Fade)', 'sidebar-jlg' ); ?></option>
                                <option value="scale" <?php selected( $options['animation_type'], 'scale' ); ?>><?php esc_html_e( 'Zoom (Scale)', 'sidebar-jlg' ); ?></option>
                            </select>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Effet de survol (Desktop)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[hover_effect_desktop]">
                            <option value="none" <?php selected( $options['hover_effect_desktop'], 'none' ); ?>><?php esc_html_e( 'Aucun', 'sidebar-jlg' ); ?></option>
                            <option value="tile-slide" <?php selected( $options['hover_effect_desktop'], 'tile-slide' ); ?>><?php esc_html_e( 'Tuile glissante', 'sidebar-jlg' ); ?></option>
                            <option value="underline-center" <?php selected( $options['hover_effect_desktop'], 'underline-center' ); ?>><?php esc_html_e( 'Soulignement centré', 'sidebar-jlg' ); ?></option>
                             <option value="pill-center" <?php selected( $options['hover_effect_desktop'], 'pill-center' ); ?>><?php esc_html_e( 'Pilule centrée', 'sidebar-jlg' ); ?></option>
                            <option value="spotlight" <?php selected( $options['hover_effect_desktop'], 'spotlight' ); ?>><?php esc_html_e( 'Spotlight (Projecteur)', 'sidebar-jlg' ); ?></option>
                            <option value="glossy-tilt" <?php selected( $options['hover_effect_desktop'], 'glossy-tilt' ); ?>><?php esc_html_e( 'Inclinaison 3D', 'sidebar-jlg' ); ?></option>
                            <option value="neon" <?php selected( $options['hover_effect_desktop'], 'neon' ); ?>><?php esc_html_e( 'Néon', 'sidebar-jlg' ); ?></option>
                            <option value="glow" <?php selected( $options['hover_effect_desktop'], 'glow' ); ?>><?php esc_html_e( 'Lueur (Glow)', 'sidebar-jlg' ); ?></option>
                            <option value="pulse" <?php selected( $options['hover_effect_desktop'], 'pulse' ); ?>><?php esc_html_e( 'Pulsation', 'sidebar-jlg' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Effet de survol (Mobile)', 'sidebar-jlg' ); ?></th>
                    <td>
                        <select name="sidebar_jlg_settings[hover_effect_mobile]">
                            <option value="none" <?php selected( $options['hover_effect_mobile'], 'none' ); ?>><?php esc_html_e( 'Aucun', 'sidebar-jlg' ); ?></option>
                            <option value="tile-slide" <?php selected( $options['hover_effect_mobile'], 'tile-slide' ); ?>><?php esc_html_e( 'Tuile glissante', 'sidebar-jlg' ); ?></option>
                            <option value="underline-center" <?php selected( $options['hover_effect_mobile'], 'underline-center' ); ?>><?php esc_html_e( 'Soulignement centré', 'sidebar-jlg' ); ?></option>
                            <option value="pill-center" <?php selected( $options['hover_effect_mobile'], 'pill-center' ); ?>><?php esc_html_e( 'Pilule centrée', 'sidebar-jlg' ); ?></option>
                            <option value="spotlight" <?php selected( $options['hover_effect_mobile'], 'spotlight' ); ?>><?php esc_html_e( 'Spotlight (Projecteur)', 'sidebar-jlg' ); ?></option>
                            <option value="glossy-tilt" <?php selected( $options['hover_effect_mobile'], 'glossy-tilt' ); ?>><?php esc_html_e( 'Inclinaison 3D', 'sidebar-jlg' ); ?></option>
                            <option value="neon" <?php selected( $options['hover_effect_mobile'], 'neon' ); ?>><?php esc_html_e( 'Néon', 'sidebar-jlg' ); ?></option>
                            <option value="glow" <?php selected( $options['hover_effect_mobile'], 'glow' ); ?>><?php esc_html_e( 'Lueur (Glow)', 'sidebar-jlg' ); ?></option>
                            <option value="pulse" <?php selected( $options['hover_effect_mobile'], 'pulse' ); ?>><?php esc_html_e( 'Pulsation', 'sidebar-jlg' ); ?></option>
                        </select>
                    </td>
                </tr>
                <tr valign="top" class="neon-options-row" style="<?php echo ($options['hover_effect_desktop'] !== 'neon' && $options['hover_effect_mobile'] !== 'neon') ? 'display:none;' : ''; ?>">
                    <th scope="row"><?php esc_html_e( 'Options Néon', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label><?php esc_html_e( 'Flou:', 'sidebar-jlg' ); ?> <span class="neon-blur-value"><?php echo esc_html($options['neon_blur']); ?>px</span></label>
                        <input type="range" name="sidebar_jlg_settings[neon_blur]" min="5" max="50" value="<?php echo esc_attr( $options['neon_blur'] ); ?>">
                        <br>
                        <label><?php esc_html_e( 'Diffusion:', 'sidebar-jlg' ); ?> <span class="neon-spread-value"><?php echo esc_html($options['neon_spread']); ?>px</span></label>
                        <input type="range" name="sidebar_jlg_settings[neon_spread]" min="1" max="15" value="<?php echo esc_attr( $options['neon_spread'] ); ?>">
                    </td>
                </tr>
             </table>
        </div>
        
        <!-- Onglet Profils -->
        <div id="tab-profiles" class="tab-content" role="tabpanel" aria-labelledby="tab-profiles-tab" aria-hidden="true" hidden>
            <div
                id="sidebar-jlg-profiles-app"
                class="sidebar-jlg-profiles"
                data-profiles="<?php echo esc_attr( wp_json_encode( $profilesData ) ?: '[]' ); ?>"
                data-active-profile="<?php echo esc_attr( is_string( $activeProfileId ) ? $activeProfileId : '' ); ?>"
            >
                <div class="sidebar-jlg-profiles__columns">
                    <div class="sidebar-jlg-profiles__list-panel">
                        <h2><?php esc_html_e( 'Profils enregistrés', 'sidebar-jlg' ); ?></h2>
                        <p class="description"><?php esc_html_e( 'Créez plusieurs variantes de la sidebar et définissez leurs conditions d’affichage.', 'sidebar-jlg' ); ?></p>
                        <ul id="sidebar-jlg-profiles-list" class="sidebar-jlg-profiles__list" aria-live="polite"></ul>
                        <p class="description sidebar-jlg-profiles__hint"><?php esc_html_e( 'Faites glisser les profils pour modifier leur ordre de priorité.', 'sidebar-jlg' ); ?></p>
                        <button type="button" class="button button-secondary" id="sidebar-jlg-profiles-add"><?php esc_html_e( 'Ajouter un profil', 'sidebar-jlg' ); ?></button>
                        <button type="button" class="button-link sidebar-jlg-profiles-clear-active" id="sidebar-jlg-profiles-clear-active"><?php esc_html_e( 'Ne sélectionner aucun profil actif', 'sidebar-jlg' ); ?></button>
                    </div>
                    <div class="sidebar-jlg-profiles__editor-panel">
                        <h2><?php esc_html_e( 'Édition du profil', 'sidebar-jlg' ); ?></h2>
                        <div
                            id="sidebar-jlg-profile-editor"
                            class="sidebar-jlg-profile-editor"
                            data-empty-text="<?php esc_attr_e( 'Sélectionnez un profil pour afficher ses réglages.', 'sidebar-jlg' ); ?>"
                        >
                            <div class="sidebar-jlg-profile-editor__fieldset">
                                <p>
                                    <label for="sidebar-jlg-profile-title"><?php esc_html_e( 'Nom du profil', 'sidebar-jlg' ); ?></label>
                                    <input type="text" id="sidebar-jlg-profile-title" class="regular-text" autocomplete="off" />
                                </p>
                                <p>
                                    <label for="sidebar-jlg-profile-slug"><?php esc_html_e( 'Identifiant (slug)', 'sidebar-jlg' ); ?></label>
                                    <input type="text" id="sidebar-jlg-profile-slug" class="regular-text" autocomplete="off" />
                                    <span class="description"><?php esc_html_e( 'Utilisé pour référencer ce profil dans le code ou les exports.', 'sidebar-jlg' ); ?></span>
                                </p>
                                <p>
                                    <label for="sidebar-jlg-profile-priority"><?php esc_html_e( 'Priorité', 'sidebar-jlg' ); ?></label>
                                    <input type="number" id="sidebar-jlg-profile-priority" class="small-text" />
                                    <span class="description"><?php esc_html_e( 'Plus la valeur est élevée, plus le profil sera évalué tôt.', 'sidebar-jlg' ); ?></span>
                                </p>
                                <p>
                                    <label>
                                        <input type="checkbox" id="sidebar-jlg-profile-enabled" />
                                        <?php esc_html_e( 'Activer ce profil', 'sidebar-jlg' ); ?>
                                    </label>
                                </p>
                            </div>
                            <fieldset class="sidebar-jlg-profile-editor__fieldset">
                                <legend><?php esc_html_e( 'Conditions d’affichage', 'sidebar-jlg' ); ?></legend>
                                <p class="description"><?php esc_html_e( 'Choisissez les contextes dans lesquels ce profil doit s’appliquer.', 'sidebar-jlg' ); ?></p>
                                <p>
                                    <label for="sidebar-jlg-profile-post-types"><?php esc_html_e( 'Types de contenu', 'sidebar-jlg' ); ?></label>
                                    <select id="sidebar-jlg-profile-post-types" multiple></select>
                                </p>
                                <div class="sidebar-jlg-profile-taxonomies">
                                    <label><?php esc_html_e( 'Taxonomies & termes', 'sidebar-jlg' ); ?></label>
                                    <div id="sidebar-jlg-profile-taxonomies"></div>
                                    <button type="button" class="button button-small" id="sidebar-jlg-profile-add-taxonomy"><?php esc_html_e( 'Ajouter une condition de taxonomie', 'sidebar-jlg' ); ?></button>
                                </div>
                                <p>
                                    <label for="sidebar-jlg-profile-roles"><?php esc_html_e( 'Rôles utilisateurs', 'sidebar-jlg' ); ?></label>
                                    <select id="sidebar-jlg-profile-roles" multiple></select>
                                </p>
                                <p>
                                    <label for="sidebar-jlg-profile-languages"><?php esc_html_e( 'Langues', 'sidebar-jlg' ); ?></label>
                                    <select id="sidebar-jlg-profile-languages" multiple></select>
                                </p>
                                <p>
                                    <label for="sidebar-jlg-profile-devices"><?php esc_html_e( 'Appareils ciblés', 'sidebar-jlg' ); ?></label>
                                    <select id="sidebar-jlg-profile-devices" multiple></select>
                                    <span class="description"><?php esc_html_e( 'Laisser vide pour tous les appareils.', 'sidebar-jlg' ); ?></span>
                                </p>
                                <p>
                                    <label for="sidebar-jlg-profile-login-state"><?php esc_html_e( 'Statut de connexion', 'sidebar-jlg' ); ?></label>
                                    <select id="sidebar-jlg-profile-login-state"></select>
                                </p>
                                <fieldset class="sidebar-jlg-profile-schedule">
                                    <legend><?php esc_html_e( 'Créneau horaire (heure locale)', 'sidebar-jlg' ); ?></legend>
                                    <p class="description"><?php esc_html_e( 'Limitez l’affichage du profil à certaines heures ou journées.', 'sidebar-jlg' ); ?></p>
                                    <div class="sidebar-jlg-profile-schedule__row">
                                        <label for="sidebar-jlg-profile-schedule-start"><?php esc_html_e( 'Début', 'sidebar-jlg' ); ?></label>
                                        <input type="time" id="sidebar-jlg-profile-schedule-start" />
                                    </div>
                                    <div class="sidebar-jlg-profile-schedule__row">
                                        <label for="sidebar-jlg-profile-schedule-end"><?php esc_html_e( 'Fin', 'sidebar-jlg' ); ?></label>
                                        <input type="time" id="sidebar-jlg-profile-schedule-end" />
                                    </div>
                                    <p>
                                        <label for="sidebar-jlg-profile-schedule-days"><?php esc_html_e( 'Jours concernés', 'sidebar-jlg' ); ?></label>
                                        <select id="sidebar-jlg-profile-schedule-days" multiple></select>
                                    </p>
                                </fieldset>
                            </fieldset>
                            <div class="sidebar-jlg-profile-editor__fieldset">
                                <h3><?php esc_html_e( 'Réglages associés', 'sidebar-jlg' ); ?></h3>
                                <p id="sidebar-jlg-profile-settings-summary" class="description"></p>
                                <div class="sidebar-jlg-profile-editor__actions">
                                    <button type="button" class="button button-primary" id="sidebar-jlg-profile-clone-settings"><?php esc_html_e( 'Utiliser les réglages actuels', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" id="sidebar-jlg-profile-clear-settings"><?php esc_html_e( 'Réinitialiser les réglages du profil', 'sidebar-jlg' ); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php wp_nonce_field( 'sidebar_jlg_profiles', 'sidebar_jlg_profiles_nonce' ); ?>
            </div>
            <input type="hidden" id="sidebar-jlg-active-profile-field" name="sidebar_jlg_active_profile" value="<?php echo esc_attr( is_string( $activeProfileId ) ? $activeProfileId : '' ); ?>" />
            <div id="sidebar-jlg-profiles-hidden" class="sidebar-jlg-hidden-inputs" aria-hidden="true"></div>
            <template id="sidebar-jlg-profile-taxonomy-template">
                <div class="sidebar-jlg-profile-taxonomy-row">
                    <select class="sidebar-jlg-profile-taxonomy-name"></select>
                    <input type="text" class="sidebar-jlg-profile-taxonomy-terms" placeholder="<?php esc_attr_e( 'Slugs ou IDs séparés par des virgules', 'sidebar-jlg' ); ?>" />
                    <button type="button" class="button-link sidebar-jlg-profile-remove-taxonomy"><?php esc_html_e( 'Supprimer', 'sidebar-jlg' ); ?></button>
                </div>
            </template>
        </div>

        <!-- Onglet Outils & Débogage -->
        <div id="tab-tools" class="tab-content" role="tabpanel" aria-labelledby="tab-tools-tab" aria-hidden="true" hidden>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Mode de débogage', 'sidebar-jlg' ); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sidebar_jlg_settings[debug_mode]" value="1" <?php checked( $options['debug_mode'], 1 ); ?> />
                            <?php esc_html_e( 'Activer le mode de débogage.', 'sidebar-jlg' ); ?>
                        </label>
                        <p class="description"><?php esc_html_e( 'Affiche des informations utiles dans la console du navigateur (F12) pour résoudre les problèmes.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Exporter les réglages', 'sidebar-jlg' ); ?></th>
                    <td>
                        <button type="button" id="export-jlg-settings" class="button button-primary"><?php esc_html_e( 'Exporter les réglages', 'sidebar-jlg' ); ?></button>
                        <p class="description"><?php esc_html_e( 'Générez un fichier JSON contenant la configuration actuelle pour la déployer sur un autre environnement.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Importer des réglages', 'sidebar-jlg' ); ?></th>
                    <td class="sidebar-jlg-import-tools">
                        <div class="sidebar-jlg-import-controls">
                            <input type="file" id="import-jlg-settings-file" accept="application/json,.json" />
                            <button type="button" id="import-jlg-settings" class="button button-secondary"><?php esc_html_e( 'Importer les réglages', 'sidebar-jlg' ); ?></button>
                        </div>
                        <p class="description"><?php esc_html_e( 'Sélectionnez un export JSON valide. Les réglages actuels seront remplacés après confirmation.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Réinitialiser les réglages', 'sidebar-jlg' ); ?></th>
                    <td>
                        <button type="button" id="reset-jlg-settings" class="button button-danger"><?php esc_html_e( 'Réinitialiser tous les réglages', 'sidebar-jlg' ); ?></button>
                        <p class="description" style="color: #d63638;"><?php esc_html_e( 'Attention : Ceci réinitialisera tous les réglages de la sidebar à leurs valeurs par défaut. Cette action est irréversible.', 'sidebar-jlg' ); ?></p>
                    </td>
                </tr>
            </table>
        </div>

        <?php submit_button(); ?>
    </form>
</div>

<div id="icon-library-modal" style="display:none;">
    <div class="modal-backdrop"></div>
    <div class="modal-content">
        <button type="button" class="modal-close" aria-label="<?php esc_attr_e( 'Fermer la modale', 'sidebar-jlg' ); ?>">&times;</button>
        <h2><?php esc_html_e( 'Bibliothèque d\'icônes', 'sidebar-jlg' ); ?></h2>
        <input type="search" id="icon-search" placeholder="<?php esc_attr_e( 'Rechercher une icône…', 'sidebar-jlg' ); ?>" />
        <div id="icon-grid"></div>
    </div>
</div>

<!-- Templates JS -->
<script type="text/html" id="tmpl-menu-item">
    <div class="menu-item-box">
        <div class="menu-item-header">
            <span class="menu-item-handle">::</span>
            <span class="menu-item-title item-title">{{ data.label || ( window.sidebarJLG && window.sidebarJLG.i18n && window.sidebarJLG.i18n.menuItemDefaultTitle ? window.sidebarJLG.i18n.menuItemDefaultTitle : '<?php echo esc_js( __( 'Nouvel élément', 'sidebar-jlg' ) ); ?>' ) }}</span>
            <button type="button" class="button-link delete-menu-item"><?php echo esc_html__( 'Supprimer', 'sidebar-jlg' ); ?></button>
        </div>
        <div class="menu-item-content">
            <p><label><?php echo esc_html__( 'Label', 'sidebar-jlg' ); ?></label><input type="text" class="widefat item-label" name="sidebar_jlg_settings[menu_items][{{ data.index }}][label]" value="{{ data.label }}"></p>
            <p><label><?php echo esc_html__( 'Type de lien', 'sidebar-jlg' ); ?></label>
                <select class="widefat menu-item-type" name="sidebar_jlg_settings[menu_items][{{ data.index }}][type]">
                    <option value="custom" <# if (data.type === 'custom') { #>selected<# } #>><?php echo esc_html__( 'Lien personnalisé', 'sidebar-jlg' ); ?></option>
                    <option value="post" <# if (data.type === 'post') { #>selected<# } #>><?php echo esc_html__( 'Article', 'sidebar-jlg' ); ?></option>
                    <option value="page" <# if (data.type === 'page') { #>selected<# } #>><?php echo esc_html__( 'Page', 'sidebar-jlg' ); ?></option>
                    <option value="category" <# if (data.type === 'category') { #>selected<# } #>><?php echo esc_html__( 'Catégorie', 'sidebar-jlg' ); ?></option>
                    <option value="nav_menu" <# if (data.type === 'nav_menu') { #>selected<# } #>><?php echo esc_html__( 'Menu WordPress', 'sidebar-jlg' ); ?></option>
                    <option value="cta" <# if (data.type === 'cta') { #>selected<# } #>><?php echo esc_html__( 'Bloc CTA', 'sidebar-jlg' ); ?></option>
                </select>
            </p>
            <div class="menu-item-value-wrapper">
                <div class="menu-item-field-container"></div>
                <div class="menu-item-search-container" style="display:none;">
                    <input type="search" class="menu-item-search-input" placeholder="<?php esc_attr_e( 'Rechercher…', 'sidebar-jlg' ); ?>" aria-label="<?php esc_attr_e( 'Rechercher un élément', 'sidebar-jlg' ); ?>" />
                    <div class="menu-item-search-status" aria-live="polite"></div>
                </div>
            </div>
            <p><label><?php echo esc_html__( 'Icône', 'sidebar-jlg' ); ?></label>
                <select class="widefat menu-item-icon-type" name="sidebar_jlg_settings[menu_items][{{ data.index }}][icon_type]">
                    <option value="svg_inline" <# if (data.icon_type === 'svg_inline') { #>selected<# } #>><?php echo esc_html__( 'Icône de la bibliothèque', 'sidebar-jlg' ); ?></option>
                    <option value="svg_url" <# if (data.icon_type === 'svg_url') { #>selected<# } #>><?php echo esc_html__( 'SVG personnalisé (URL)', 'sidebar-jlg' ); ?></option>
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
            <span class="menu-item-title item-title">{{ data.label || data.icon || ( window.sidebarJLG && window.sidebarJLG.i18n && window.sidebarJLG.i18n.socialIconDefaultTitle ? window.sidebarJLG.i18n.socialIconDefaultTitle : '<?php echo esc_js( __( 'Nouvelle icône', 'sidebar-jlg' ) ); ?>' ) }}</span>
            <button type="button" class="button-link delete-social-icon"><?php echo esc_html__( 'Supprimer', 'sidebar-jlg' ); ?></button>
        </div>
        <div class="menu-item-content">
            <p><label><?php echo esc_html__( 'Label', 'sidebar-jlg' ); ?></label><input type="text" class="widefat item-label" name="sidebar_jlg_settings[social_icons][{{ data.index }}][label]" value="{{ data.label || '' }}"></p>
            <p><label><?php echo esc_html__( 'URL', 'sidebar-jlg' ); ?></label><input type="text" class="widefat social-url" name="sidebar_jlg_settings[social_icons][{{ data.index }}][url]" value="{{ data.url }}" placeholder="<?php echo esc_attr__( 'https://…', 'sidebar-jlg' ); ?>"></p>
            <p><label><?php echo esc_html__( 'Icône', 'sidebar-jlg' ); ?></label>
                <select class="widefat social-icon-select" name="sidebar_jlg_settings[social_icons][{{ data.index }}][icon]"></select>
             <span class="icon-preview"></span>
            </p>
        </div>
    </div>
</script>
