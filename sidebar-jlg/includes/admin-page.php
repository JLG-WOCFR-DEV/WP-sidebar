<?php

use JLG\Sidebar\Accessibility\Checklist;
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

if ( ! function_exists( 'sidebar_jlg_format_metric_number' ) ) {
    function sidebar_jlg_format_metric_number( $value, int $decimals = 0 ): string {
        $number = is_numeric( $value ) ? (float) $value : 0.0;

        if ( function_exists( 'number_format_i18n' ) ) {
            return number_format_i18n( $number, $decimals );
        }

        return number_format( $number, $decimals );
    }
}

if ( ! function_exists( 'sidebar_jlg_format_percentage_label' ) ) {
    function sidebar_jlg_format_percentage_label( $value ): string {
        return sidebar_jlg_format_metric_number( $value, 1 ) . '%';
    }
}

if ( ! function_exists( 'sidebar_jlg_format_duration_label' ) ) {
    function sidebar_jlg_format_duration_label( $milliseconds ): string {
        $duration = is_numeric( $milliseconds ) ? max( 0, (int) round( (float) $milliseconds ) ) : 0;

        if ( $duration < 1000 ) {
            $base = max( 1, $duration );

            return sprintf(
                _n( '%s milliseconde', '%s millisecondes', $base, 'sidebar-jlg' ),
                sidebar_jlg_format_metric_number( $base )
            );
        }

        $totalSeconds = (int) round( $duration / 1000 );
        if ( $totalSeconds < 60 ) {
            return sprintf(
                _n( '%s seconde', '%s secondes', $totalSeconds, 'sidebar-jlg' ),
                sidebar_jlg_format_metric_number( $totalSeconds )
            );
        }

        $minutes        = (int) floor( $totalSeconds / 60 );
        $remaining      = $totalSeconds % 60;
        $minutesLabel   = sprintf(
            _n( '%s minute', '%s minutes', $minutes, 'sidebar-jlg' ),
            sidebar_jlg_format_metric_number( $minutes )
        );

        if ( $remaining <= 0 ) {
            return $minutesLabel;
        }

        $secondsLabel = sprintf(
            _n( '%s seconde', '%s secondes', $remaining, 'sidebar-jlg' ),
            sidebar_jlg_format_metric_number( $remaining )
        );

        return sprintf(
            /* translators: 1: minutes label, 2: seconds label */
            __( '%1$s %2$s', 'sidebar-jlg' ),
            $minutesLabel,
            $secondsLabel
        );
    }
}

if ( ! function_exists( 'sidebar_jlg_format_local_date' ) ) {
    function sidebar_jlg_format_local_date( string $date_string ): string {
        $timestamp = strtotime( $date_string );

        if ( ! $timestamp ) {
            return $date_string;
        }

        if ( function_exists( 'wp_date' ) ) {
            $date_format = function_exists( 'get_option' ) ? ( get_option( 'date_format' ) ?: 'Y-m-d' ) : 'Y-m-d';

            return wp_date( $date_format, $timestamp );
        }

        return date( 'Y-m-d', $timestamp );
    }
}

if ( ! function_exists( 'sidebar_jlg_clamp_number_to_step' ) ) {
    function sidebar_jlg_clamp_number_to_step( $value, int $min, int $max, int $step, int $fallback = 0 ): int {
        $numeric = is_numeric( $value ) ? (int) round( (float) $value ) : $fallback;

        if ( $min > $max ) {
            $tmp = $min;
            $min  = $max;
            $max  = $tmp;
        }

        $clamped = max( $min, min( $max, $numeric ) );

        if ( $step <= 0 ) {
            return $clamped;
        }

        $offset  = $clamped - $min;
        $rounded = (int) round( $offset / $step ) * $step;
        $snapped = $min + $rounded;

        if ( $snapped < $min ) {
            return $min;
        }

        if ( $snapped > $max ) {
            return $max;
        }

        return $snapped;
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
<div
    class="wrap sidebar-jlg-admin-wrap"
    data-sidebar-experience
    data-sidebar-experience-mode="simple"
    data-sidebar-form-mode="simple"
>
    <h1><?php esc_html_e( 'Réglages de la Sidebar JLG', 'sidebar-jlg' ); ?></h1>

    <?php
    if ( filter_input( INPUT_GET, 'settings-updated', FILTER_VALIDATE_BOOLEAN ) ) {
        add_settings_error( 'sidebar_jlg_messages', 'sidebar_jlg_message', __( 'Réglages sauvegardés.', 'sidebar-jlg' ), 'updated' );
    }
    settings_errors( 'sidebar_jlg_messages' );
    ?>

    <div
        id="sidebar-jlg-js-notices"
        class="sidebar-jlg-aria-notices"
        role="region"
        aria-live="polite"
        aria-atomic="false"
        aria-relevant="additions"
        aria-label="<?php esc_attr_e( 'Notifications Sidebar JLG', 'sidebar-jlg' ); ?>"
        data-sidebar-notices="0"
        aria-hidden="true"
    ></div>

    <p><?php esc_html_e( 'Personnalisez l\'apparence et le comportement de votre sidebar.', 'sidebar-jlg' ); ?></p>
    <p><b><?php esc_html_e( 'Nouveau :', 'sidebar-jlg' ); ?></b> <?php printf( esc_html__( 'Ajoutez vos propres icônes SVG dans le dossier %1$s. Elles apparaîtront dans les listes de sélection !', 'sidebar-jlg' ), '<code>/wp-content/uploads/sidebar-jlg/icons/</code>' ); ?></p>

    <section class="sidebar-jlg-pro-comparison" aria-labelledby="sidebar-jlg-pro-comparison-title">
        <div class="sidebar-jlg-pro-comparison__intro">
            <h2 id="sidebar-jlg-pro-comparison-title"><?php esc_html_e( 'Comparatif express avec les suites professionnelles', 'sidebar-jlg' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Gardez un œil sur les points forts actuels et les chantiers en cours afin d\'aligner l\'expérience sur les références du marché.', 'sidebar-jlg' ); ?>
            </p>
        </div>
        <div class="sidebar-jlg-pro-comparison__grid" role="list">
            <article class="sidebar-jlg-pro-comparison__item" role="listitem">
                <header class="sidebar-jlg-pro-comparison__item-header">
                    <h3><?php esc_html_e( 'UI & UX', 'sidebar-jlg' ); ?></h3>
                    <span class="sidebar-jlg-pro-comparison__badge is-strength" aria-label="<?php esc_attr_e( 'Atout confirmé', 'sidebar-jlg' ); ?>">
                        <?php esc_html_e( 'Atout', 'sidebar-jlg' ); ?>
                    </span>
                </header>
                <p><?php esc_html_e( 'Onglets thématiques, aperçu multi-breakpoints et presets prêts à l\'emploi rapprochent l\'ergonomie de celle des builders drag & drop premium.', 'sidebar-jlg' ); ?></p>
                <p class="sidebar-jlg-pro-comparison__meta"><?php esc_html_e( 'Prochain jalon : édition frontale canvas + historique Undo/Redo.', 'sidebar-jlg' ); ?></p>
            </article>
            <article class="sidebar-jlg-pro-comparison__item" role="listitem">
                <header class="sidebar-jlg-pro-comparison__item-header">
                    <h3><?php esc_html_e( 'Accessibilité', 'sidebar-jlg' ); ?></h3>
                    <span class="sidebar-jlg-pro-comparison__badge is-progress" aria-label="<?php esc_attr_e( 'Travail en cours', 'sidebar-jlg' ); ?>">
                        <?php esc_html_e( 'En progrès', 'sidebar-jlg' ); ?>
                    </span>
                </header>
                <p><?php esc_html_e( 'Les rôles ARIA, le focus trap et les préférences de mouvement sont en place ; la nouvelle check-list guide les équipes vers la conformité WCAG 2.2.', 'sidebar-jlg' ); ?></p>
                <p class="sidebar-jlg-pro-comparison__meta"><?php esc_html_e( 'Prochain jalon : alertes temps réel sur le contraste et audits Pa11y automatisés.', 'sidebar-jlg' ); ?></p>
            </article>
            <article class="sidebar-jlg-pro-comparison__item" role="listitem">
                <header class="sidebar-jlg-pro-comparison__item-header">
                    <h3><?php esc_html_e( 'Fiabilité', 'sidebar-jlg' ); ?></h3>
                    <span class="sidebar-jlg-pro-comparison__badge is-strength" aria-label="<?php esc_attr_e( 'Atout confirmé', 'sidebar-jlg' ); ?>">
                        <?php esc_html_e( 'Atout', 'sidebar-jlg' ); ?>
                    </span>
                </header>
                <p><?php esc_html_e( 'Cache HTML multi-profils, sanitation poussée des réglages et rollback d\'icônes calquent les standards des solutions professionnelles.', 'sidebar-jlg' ); ?></p>
                <p class="sidebar-jlg-pro-comparison__meta"><?php esc_html_e( 'Prochain jalon : pipeline de tests E2E et reporting automatique des erreurs AJAX.', 'sidebar-jlg' ); ?></p>
            </article>
            <article class="sidebar-jlg-pro-comparison__item" role="listitem">
                <header class="sidebar-jlg-pro-comparison__item-header">
                    <h3><?php esc_html_e( 'Visuel', 'sidebar-jlg' ); ?></h3>
                    <span class="sidebar-jlg-pro-comparison__badge is-progress" aria-label="<?php esc_attr_e( 'Travail en cours', 'sidebar-jlg' ); ?>">
                        <?php esc_html_e( 'En progrès', 'sidebar-jlg' ); ?>
                    </span>
                </header>
                <p><?php esc_html_e( 'Les presets inspirés de Headless UI, Radix ou Shadcn donnent des rendus premium ; la galerie d\'icônes SVG dédiée renforce l\'identité visuelle.', 'sidebar-jlg' ); ?></p>
                <p class="sidebar-jlg-pro-comparison__meta"><?php esc_html_e( 'Prochain jalon : design system administrateur et micro-interactions animées.', 'sidebar-jlg' ); ?></p>
            </article>
        </div>
    </section>

    <div class="nav-tab-wrapper" role="tablist">
        <a href="#tab-general" class="nav-tab nav-tab-active" id="tab-general-tab" role="tab" aria-controls="tab-general" aria-selected="true" tabindex="0"><?php esc_html_e( 'Général & Comportement', 'sidebar-jlg' ); ?></a>
        <a href="#tab-profiles" class="nav-tab" id="tab-profiles-tab" role="tab" aria-controls="tab-profiles" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Profils', 'sidebar-jlg' ); ?></a>
        <a href="#tab-presets" class="nav-tab" id="tab-presets-tab" role="tab" aria-controls="tab-presets" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Style & Préréglages', 'sidebar-jlg' ); ?></a>
        <a href="#tab-menu" class="nav-tab" id="tab-menu-tab" role="tab" aria-controls="tab-menu" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Contenu du Menu', 'sidebar-jlg' ); ?></a>
        <a href="#tab-widgets" class="nav-tab" id="tab-widgets-tab" role="tab" aria-controls="tab-widgets" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Widgets', 'sidebar-jlg' ); ?></a>
        <a href="#tab-social" class="nav-tab" id="tab-social-tab" role="tab" aria-controls="tab-social" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Réseaux Sociaux', 'sidebar-jlg' ); ?></a>
        <a href="#tab-effects" class="nav-tab" id="tab-effects-tab" role="tab" aria-controls="tab-effects" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Effets & Animations', 'sidebar-jlg' ); ?></a>
        <a href="#tab-analytics" class="nav-tab" id="tab-analytics-tab" role="tab" aria-controls="tab-analytics" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Insights & Analytics', 'sidebar-jlg' ); ?></a>
        <a href="#tab-accessibility" class="nav-tab" id="tab-accessibility-tab" role="tab" aria-controls="tab-accessibility" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Accessibilité & WCAG 2.2', 'sidebar-jlg' ); ?></a>
        <a href="#tab-tools" class="nav-tab" id="tab-tools-tab" role="tab" aria-controls="tab-tools" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Outils', 'sidebar-jlg' ); ?></a>
    </div>

    <div
        id="sidebar-jlg-preview"
        class="sidebar-jlg-preview"
        data-state="idle"
        data-preview-size="mobile"
        data-breakpoint-mobile="360"
        data-breakpoint-tablet="820"
        data-breakpoint-desktop="1160"
    >
        <div class="sidebar-jlg-preview__header">
            <h2><?php esc_html_e( 'Aperçu en direct', 'sidebar-jlg' ); ?></h2>
            <p class="description"><?php esc_html_e( 'L’aperçu se met à jour automatiquement lorsque vous modifiez les réglages.', 'sidebar-jlg' ); ?></p>
        </div>
        <div class="sidebar-jlg-preview__toolbar" role="toolbar" aria-label="<?php esc_attr_e( 'Choisir la taille de prévisualisation', 'sidebar-jlg' ); ?>">
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button is-active" data-preview-size="mobile" aria-pressed="true">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode mobile', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Mobile', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" data-preview-size="tablet" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode tablette', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Tablette', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" data-preview-size="desktop" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Prévisualiser en mode bureau', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Desktop', 'sidebar-jlg' ); ?></span>
            </button>
            <button type="button" class="button button-secondary sidebar-jlg-preview__toolbar-button" id="sidebar-jlg-preview-compare" aria-pressed="false">
                <span class="screen-reader-text"><?php esc_html_e( 'Basculer entre l’aperçu initial et l’aperçu actuel', 'sidebar-jlg' ); ?></span>
                <span class="sidebar-jlg-preview__toolbar-button-label" aria-hidden="true"><?php esc_html_e( 'Comparer', 'sidebar-jlg' ); ?></span>
            </button>
            <button
                type="button"
                class="button button-secondary sidebar-jlg-preview__toolbar-button"
                data-preview-split-toggle
                aria-pressed="false"
            >
                <span class="screen-reader-text"><?php esc_html_e( 'Activer ou désactiver la vue comparée', 'sidebar-jlg' ); ?></span>
                <span aria-hidden="true"><?php esc_html_e( 'Split view', 'sidebar-jlg' ); ?></span>
            </button>
        </div>
        <fieldset class="sidebar-jlg-preview__breakpoints" data-preview-breakpoints>
            <legend><?php esc_html_e( 'Breakpoints personnalisés', 'sidebar-jlg' ); ?></legend>
            <p class="description">
                <?php esc_html_e( 'Ajustez les largeurs de prévisualisation pour refléter votre grille responsive.', 'sidebar-jlg' ); ?>
            </p>
            <div class="sidebar-jlg-preview__breakpoints-grid">
                <label class="sidebar-jlg-preview__breakpoint">
                    <span><?php esc_html_e( 'Mobile', 'sidebar-jlg' ); ?></span>
                    <input
                        type="number"
                        min="240"
                        max="640"
                        step="10"
                        value="360"
                        data-breakpoint-input="mobile"
                        aria-label="<?php esc_attr_e( 'Largeur mobile en pixels', 'sidebar-jlg' ); ?>"
                    />
                    <span class="sidebar-jlg-preview__breakpoint-unit" aria-hidden="true">px</span>
                </label>
                <label class="sidebar-jlg-preview__breakpoint">
                    <span><?php esc_html_e( 'Tablette', 'sidebar-jlg' ); ?></span>
                    <input
                        type="number"
                        min="600"
                        max="1024"
                        step="10"
                        value="820"
                        data-breakpoint-input="tablet"
                        aria-label="<?php esc_attr_e( 'Largeur tablette en pixels', 'sidebar-jlg' ); ?>"
                    />
                    <span class="sidebar-jlg-preview__breakpoint-unit" aria-hidden="true">px</span>
                </label>
                <label class="sidebar-jlg-preview__breakpoint">
                    <span><?php esc_html_e( 'Desktop', 'sidebar-jlg' ); ?></span>
                    <input
                        type="number"
                        min="960"
                        max="1600"
                        step="10"
                        value="1160"
                        data-breakpoint-input="desktop"
                        aria-label="<?php esc_attr_e( 'Largeur desktop en pixels', 'sidebar-jlg' ); ?>"
                    />
                    <span class="sidebar-jlg-preview__breakpoint-unit" aria-hidden="true">px</span>
                </label>
            </div>
        </fieldset>
        <div class="sidebar-jlg-preview__status" role="status" aria-live="polite"></div>
        <div class="sidebar-jlg-preview__viewports" data-sidebar-preview-viewports>
            <div
                class="sidebar-jlg-preview__viewport"
                data-preview-role="primary"
                data-preview-size="mobile"
                aria-label="<?php esc_attr_e( 'Aperçu de la sidebar', 'sidebar-jlg' ); ?>"
            ></div>
            <div
                class="sidebar-jlg-preview__viewport"
                data-preview-role="split"
                data-preview-size="mobile"
                aria-hidden="true"
                hidden
            ></div>
            <div
                class="sidebar-jlg-preview__viewport"
                data-preview-role="split"
                data-preview-size="tablet"
                aria-hidden="true"
                hidden
            ></div>
            <div
                class="sidebar-jlg-preview__viewport"
                data-preview-role="split"
                data-preview-size="desktop"
                aria-hidden="true"
                hidden
            ></div>
        </div>
        <div class="sidebar-jlg-preview__touch-overlay" data-touch-overlay aria-hidden="true">
            <span class="sidebar-jlg-preview__touch-point"></span>
        </div>
    </div>

    <div class="sidebar-jlg-command-bar" data-sidebar-command-bar>
        <div class="sidebar-jlg-command-bar__search" role="search">
            <label class="sidebar-jlg-command-bar__search-label" for="sidebar-jlg-settings-search">
                <span class="screen-reader-text"><?php esc_html_e( 'Rechercher un réglage', 'sidebar-jlg' ); ?></span>
                <span class="sidebar-jlg-command-bar__search-icon dashicons dashicons-search" aria-hidden="true"></span>
            </label>
            <input
                type="search"
                id="sidebar-jlg-settings-search"
                class="regular-text"
                placeholder="<?php esc_attr_e( 'Rechercher un réglage ou un mot-clé…', 'sidebar-jlg' ); ?>"
                data-sidebar-settings-search
                aria-describedby="sidebar-jlg-settings-search-status"
            />
            <button type="button" class="button button-secondary" data-sidebar-search-clear>
                <?php esc_html_e( 'Effacer', 'sidebar-jlg' ); ?>
            </button>
        </div>
        <div class="sidebar-jlg-command-bar__mode" data-sidebar-mode-panel>
            <span class="sidebar-jlg-command-bar__mode-label"><?php esc_html_e( 'Mode', 'sidebar-jlg' ); ?></span>
            <div
                class="sidebar-jlg-mode-toggle"
                role="group"
                aria-label="<?php esc_attr_e( 'Choisir le niveau de personnalisation', 'sidebar-jlg' ); ?>"
                data-sidebar-mode-toggle
            >
                <button type="button" class="button button-secondary is-active" data-mode-value="simple" aria-pressed="true">
                    <?php esc_html_e( 'Simple', 'sidebar-jlg' ); ?>
                </button>
                <button type="button" class="button button-secondary" data-mode-value="expert" aria-pressed="false">
                    <?php esc_html_e( 'Expert', 'sidebar-jlg' ); ?>
                </button>
            </div>
            <p class="sidebar-jlg-command-bar__mode-summary" data-sidebar-mode-summary aria-live="polite">
                <?php esc_html_e( 'Mode simple : les options avancées sont masquées.', 'sidebar-jlg' ); ?>
            </p>
        </div>
        <div
            class="sidebar-jlg-command-bar__experience-switch"
            data-sidebar-experience-view-toggle
            role="group"
            aria-label="<?php esc_attr_e( 'Choisir l’interface d’édition', 'sidebar-jlg' ); ?>"
        >
            <button
                type="button"
                class="button button-secondary is-active"
                data-experience-view="form"
                aria-pressed="true"
            >
                <?php esc_html_e( 'Formulaire', 'sidebar-jlg' ); ?>
            </button>
            <button
                type="button"
                class="button button-secondary"
                data-experience-view="canvas"
                aria-pressed="false"
            >
                <?php esc_html_e( 'Canvas', 'sidebar-jlg' ); ?>
            </button>
        </div>
        <p id="sidebar-jlg-settings-search-status" class="sidebar-jlg-command-bar__status" aria-live="polite"></p>
        <div class="sidebar-jlg-command-bar__guided" data-sidebar-guided>
            <button
                type="button"
                class="button button-primary"
                data-sidebar-guided-toggle
                data-label-enable="<?php esc_attr_e( 'Activer le parcours guidé', 'sidebar-jlg' ); ?>"
                data-label-disable="<?php esc_attr_e( 'Terminer le parcours guidé', 'sidebar-jlg' ); ?>"
            >
                <?php esc_html_e( 'Activer le parcours guidé', 'sidebar-jlg' ); ?>
            </button>
            <div class="sidebar-jlg-guided-controls" data-sidebar-guided-controls hidden>
                <span class="sidebar-jlg-guided-controls__progress" data-sidebar-guided-progress>
                    <?php esc_html_e( 'Étape 1 sur 1', 'sidebar-jlg' ); ?>
                </span>
                <span class="sidebar-jlg-guided-controls__title" data-sidebar-guided-title></span>
                <div class="sidebar-jlg-guided-controls__actions">
                    <button type="button" class="button" data-sidebar-guided-prev disabled>
                        <?php esc_html_e( 'Précédent', 'sidebar-jlg' ); ?>
                    </button>
                    <button type="button" class="button button-primary" data-sidebar-guided-next>
                        <?php esc_html_e( 'Suivant', 'sidebar-jlg' ); ?>
                    </button>
                    <button type="button" class="button button-link" data-sidebar-guided-exit>
                        <?php esc_html_e( 'Quitter', 'sidebar-jlg' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <section
        class="sidebar-jlg-canvas"
        data-sidebar-canvas
        aria-hidden="true"
        hidden
    >
        <header
            class="sidebar-jlg-canvas__toolbar"
            role="toolbar"
            aria-label="<?php esc_attr_e( 'Outils de l’éditeur visuel', 'sidebar-jlg' ); ?>"
            data-sidebar-canvas-toolbar
        >
            <div class="sidebar-jlg-canvas__toolbar-group">
                <button type="button" class="button button-secondary" data-canvas-command="undo" disabled>
                    <?php esc_html_e( 'Annuler', 'sidebar-jlg' ); ?>
                </button>
                <button type="button" class="button button-secondary" data-canvas-command="redo" disabled>
                    <?php esc_html_e( 'Rétablir', 'sidebar-jlg' ); ?>
                </button>
            </div>
            <div class="sidebar-jlg-canvas__toolbar-group">
                <button type="button" class="button button-secondary" data-canvas-command="refresh">
                    <?php esc_html_e( 'Rafraîchir l’aperçu', 'sidebar-jlg' ); ?>
                </button>
            </div>
        </header>
        <div class="sidebar-jlg-canvas__body">
            <div class="sidebar-jlg-canvas__workspace" data-sidebar-canvas-workspace>
                <div class="sidebar-jlg-canvas__preview" data-sidebar-canvas-preview aria-live="polite"></div>
                <div class="sidebar-jlg-canvas__items" data-sidebar-canvas-items role="list"></div>
                <p class="sidebar-jlg-canvas__empty" data-sidebar-canvas-empty hidden>
                    <?php esc_html_e( 'Ajoutez un élément de menu ou un bloc CTA pour commencer.', 'sidebar-jlg' ); ?>
                </p>
            </div>
            <aside class="sidebar-jlg-canvas__inspector" data-sidebar-canvas-inspector aria-live="polite" hidden>
                <h2 class="sidebar-jlg-canvas__inspector-title"><?php esc_html_e( 'Édition rapide', 'sidebar-jlg' ); ?></h2>
                <form class="sidebar-jlg-canvas__inspector-form" data-sidebar-canvas-form>
                    <label class="sidebar-jlg-canvas__field">
                        <span class="sidebar-jlg-canvas__field-label"><?php esc_html_e( 'Titre', 'sidebar-jlg' ); ?></span>
                        <input type="text" class="regular-text" data-canvas-field="label" autocomplete="off" />
                    </label>
                    <label class="sidebar-jlg-canvas__field">
                        <span class="sidebar-jlg-canvas__field-label"><?php esc_html_e( 'Icône', 'sidebar-jlg' ); ?></span>
                        <input type="text" class="regular-text" data-canvas-field="icon" autocomplete="off" />
                        <p class="description"><?php esc_html_e( 'Utilisez une clé d’icône disponible ou un slug personnalisé.', 'sidebar-jlg' ); ?></p>
                    </label>
                    <label class="sidebar-jlg-canvas__field">
                        <span class="sidebar-jlg-canvas__field-label"><?php esc_html_e( 'Couleur', 'sidebar-jlg' ); ?></span>
                        <input type="text" class="regular-text" data-canvas-field="color" autocomplete="off" />
                        <p class="description"><?php esc_html_e( 'Valeurs CSS ou RGBA (ex: rgba(255, 255, 255, 1)).', 'sidebar-jlg' ); ?></p>
                    </label>
                    <div class="sidebar-jlg-canvas__inspector-actions">
                        <button type="button" class="button button-primary" data-canvas-command="apply">
                            <?php esc_html_e( 'Appliquer', 'sidebar-jlg' ); ?>
                        </button>
                        <button type="button" class="button" data-canvas-command="cancel">
                            <?php esc_html_e( 'Fermer', 'sidebar-jlg' ); ?>
                        </button>
                    </div>
                </form>
            </aside>
        </div>
    </section>

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
        $analyticsSummary = isset( $analyticsSummary ) && is_array( $analyticsSummary ) ? $analyticsSummary : [];
        $accessibilityItems = Checklist::getItems();
        $rawAccessibilityStatuses = get_option( 'sidebar_jlg_accessibility_checklist', Checklist::getDefaultStatuses() );
        $accessibilityStatuses = is_array( $rawAccessibilityStatuses ) ? $rawAccessibilityStatuses : [];
        $normalizedAccessibilityStatuses = [];
        foreach ( $accessibilityItems as $item ) {
            $itemId = isset( $item['id'] ) && is_string( $item['id'] ) ? $item['id'] : '';
            if ( '' === $itemId ) {
                continue;
            }

            $normalizedAccessibilityStatuses[ $itemId ] = ! empty( $accessibilityStatuses[ $itemId ] );
        }

        $completedAccessibilityItems = array_sum( array_map( static fn( $value ) => $value ? 1 : 0, $normalizedAccessibilityStatuses ) );
        $totalAccessibilityItems = count( $accessibilityItems );
        $accessibilityCompletionRatio = $totalAccessibilityItems > 0 ? round( ( $completedAccessibilityItems / $totalAccessibilityItems ) * 100 ) : 0;
        $accessibilityProgressTemplate = __( '%1$s critères validés sur %2$s (%3$s%%)', 'sidebar-jlg' );
        $accessibilityProgressAriaTemplate = __( 'Avancement : %1$s%% des critères d’accessibilité sont validés.', 'sidebar-jlg' );
        $accessibilityProgressText = sprintf(
            $accessibilityProgressTemplate,
            sidebar_jlg_format_metric_number( $completedAccessibilityItems ),
            sidebar_jlg_format_metric_number( $totalAccessibilityItems ),
            sidebar_jlg_format_metric_number( $accessibilityCompletionRatio )
        );
        $accessibilityProgressAria = sprintf(
            $accessibilityProgressAriaTemplate,
            sidebar_jlg_format_metric_number( $accessibilityCompletionRatio )
        );
        $auditStatus = isset( $auditStatus ) && is_array( $auditStatus ) ? $auditStatus : [];
        $auditChecks = isset( $auditStatus['checks'] ) && is_array( $auditStatus['checks'] ) ? $auditStatus['checks'] : [];
        $auditIsAvailable = ! empty( $auditStatus['can_run'] );
        $auditDefaultUrl = isset( $auditDefaultUrl ) && is_string( $auditDefaultUrl ) && $auditDefaultUrl !== ''
            ? $auditDefaultUrl
            : esc_url( home_url( '/' ) );
        ?>

        <div id="tab-general" class="tab-content active" role="tabpanel" aria-labelledby="tab-general-tab" aria-hidden="false">
            <div class="sidebar-jlg-quickstart" role="complementary" aria-label="<?php esc_attr_e( 'Assistant de configuration', 'sidebar-jlg' ); ?>">
                <h2 class="sidebar-jlg-quickstart__title"><?php esc_html_e( 'Démarrage express', 'sidebar-jlg' ); ?></h2>
                <p class="sidebar-jlg-quickstart__intro"><?php esc_html_e( 'Passez en revue les étapes clés pour aligner votre sidebar avec une expérience produit professionnelle.', 'sidebar-jlg' ); ?></p>
                <ol class="sidebar-jlg-quickstart__list">
                    <li><?php esc_html_e( 'Activez la sidebar et, si besoin, la collecte analytics pour suivre les interactions.', 'sidebar-jlg' ); ?></li>
                    <li><?php esc_html_e( 'Choisissez un mode d’affichage (flottant, pleine hauteur ou barre horizontale) adapté à votre mise en page.', 'sidebar-jlg' ); ?></li>
                    <li><?php esc_html_e( 'Affinez les comportements côté utilisateur : fermeture automatique, mémorisation, gestes tactiles et déclencheurs.', 'sidebar-jlg' ); ?></li>
                    <li><?php esc_html_e( 'Peaufinez l’accessibilité : libellés ARIA, textes des sous-menus et barre de recherche.', 'sidebar-jlg' ); ?></li>
                </ol>
            </div>

            <nav class="sidebar-jlg-section-nav" data-sidebar-section-nav aria-label="<?php esc_attr_e( 'Accès rapide aux sections', 'sidebar-jlg' ); ?>">
                <button type="button" class="button button-secondary" data-sidebar-section-target="sidebar-jlg-section-activation"><?php esc_html_e( 'Activation & Observabilité', 'sidebar-jlg' ); ?></button>
                <button type="button" class="button button-secondary" data-sidebar-section-target="sidebar-jlg-section-layout"><?php esc_html_e( 'Mise en page & dimensions', 'sidebar-jlg' ); ?></button>
                <button type="button" class="button button-secondary" data-sidebar-section-target="sidebar-jlg-section-interactions"><?php esc_html_e( 'Interactions & déclencheurs', 'sidebar-jlg' ); ?></button>
                <button type="button" class="button button-secondary" data-sidebar-section-target="sidebar-jlg-section-accessibility"><?php esc_html_e( 'Accessibilité & recherche', 'sidebar-jlg' ); ?></button>
            </nav>

            <div class="sidebar-jlg-accordion" data-sidebar-section-group>
                <details
                    id="sidebar-jlg-section-activation"
                    class="sidebar-jlg-section"
                    open
                    data-sidebar-filterable
                    data-sidebar-filter-label="<?php esc_attr_e( 'Activation & Observabilité', 'sidebar-jlg' ); ?>"
                    data-sidebar-filter-keywords="<?php echo esc_attr( implode( ' ', [ __( 'activation', 'sidebar-jlg' ), __( 'analytics', 'sidebar-jlg' ), __( 'suivi', 'sidebar-jlg' ) ] ) ); ?>"
                    data-sidebar-guided-step
                    data-sidebar-guided-title="<?php esc_attr_e( 'Activation & Observabilité', 'sidebar-jlg' ); ?>"
                >
                    <summary class="sidebar-jlg-section__summary">
                        <span class="sidebar-jlg-section__title"><?php esc_html_e( 'Activation & Observabilité', 'sidebar-jlg' ); ?></span>
                        <span class="sidebar-jlg-section__description"><?php esc_html_e( 'Activez la sidebar et pilotez la mesure des interactions.', 'sidebar-jlg' ); ?></span>
                    </summary>
                    <div class="sidebar-jlg-section__body">
                        <table class="form-table sidebar-jlg-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Activation', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <?php $activationDescriptionId = 'sidebar-jlg-enable-sidebar-help'; ?>
                                    <label class="jlg-switch">
                                        <input type="checkbox" name="sidebar_jlg_settings[enable_sidebar]" value="1" aria-describedby="<?php echo esc_attr( $activationDescriptionId ); ?>" <?php checked( $options['enable_sidebar'], 1 ); ?> />
                                        <span class="jlg-slider"></span>
                                    </label>
                                    <p id="<?php echo esc_attr( $activationDescriptionId ); ?>" class="description"><?php esc_html_e( 'Active ou désactive complètement la sidebar sur votre site.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                            <tr data-sidebar-mode="expert">
                                <th scope="row"><?php esc_html_e( 'Insights & Analytics', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <?php $analyticsDescriptionId = 'sidebar-jlg-analytics-help'; ?>
                                    <label class="jlg-switch">
                                        <input type="checkbox" name="sidebar_jlg_settings[enable_analytics]" value="1" aria-describedby="<?php echo esc_attr( $analyticsDescriptionId ); ?>" <?php checked( ! empty( $options['enable_analytics'] ) ); ?> />
                                        <span class="jlg-slider"></span>
                                    </label>
                                    <p id="<?php echo esc_attr( $analyticsDescriptionId ); ?>" class="description"><?php esc_html_e( 'Collecte les ouvertures, clics de navigation et interactions CTA pour alimenter le tableau de bord Analytics.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </details>

                <details
                    id="sidebar-jlg-section-layout"
                    class="sidebar-jlg-section"
                    data-sidebar-filterable
                    data-sidebar-filter-label="<?php esc_attr_e( 'Mise en page & dimensions', 'sidebar-jlg' ); ?>"
                    data-sidebar-filter-keywords="<?php echo esc_attr( implode( ' ', [ __( 'mise en page', 'sidebar-jlg' ), __( 'dimensions', 'sidebar-jlg' ), __( 'flottant', 'sidebar-jlg' ) ] ) ); ?>"
                    data-sidebar-guided-step
                    data-sidebar-guided-title="<?php esc_attr_e( 'Mise en page & dimensions', 'sidebar-jlg' ); ?>"
                >
                    <summary class="sidebar-jlg-section__summary">
                        <span class="sidebar-jlg-section__title"><?php esc_html_e( 'Mise en page & dimensions', 'sidebar-jlg' ); ?></span>
                        <span class="sidebar-jlg-section__description"><?php esc_html_e( 'Choisissez le comportement visuel par appareil.', 'sidebar-jlg' ); ?></span>
                    </summary>
                    <div class="sidebar-jlg-section__body">
                        <table class="form-table sidebar-jlg-form-table">
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
                                    <div class="floating-options-field" data-sidebar-mode="expert" style="<?php echo $options['layout_style'] === 'floating' ? '' : 'display:none;'; ?>">
                                        <?php $floatingMargin = $dimensionValues['floating_vertical_margin']; ?>
                                        <?php $borderRadius = $dimensionValues['border_radius']; ?>
                                        <div class="sidebar-jlg-dimension-grid" data-sidebar-dimension-grid>
                                            <div class="sidebar-jlg-dimension-grid__item">
                                                <span class="sidebar-jlg-dimension-grid__label"><?php esc_html_e( 'Marge verticale', 'sidebar-jlg' ); ?></span>
                                                <div class="sidebar-jlg-dimension-grid__control">
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
                                                </div>
                                                <p class="description sidebar-jlg-dimension-grid__description"><?php esc_html_e( 'Ex: 4rem, 15px', 'sidebar-jlg' ); ?></p>
                                            </div>
                                            <div class="sidebar-jlg-dimension-grid__item">
                                                <span class="sidebar-jlg-dimension-grid__label"><?php esc_html_e( 'Arrondi des coins', 'sidebar-jlg' ); ?></span>
                                                <div class="sidebar-jlg-dimension-grid__control">
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
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="horizontal-options-field" data-sidebar-mode="expert" style="<?php echo $options['layout_style'] === 'horizontal-bar' ? '' : 'display:none;'; ?>">
                                        <?php $horizontalHeight = $dimensionValues['horizontal_bar_height']; ?>
                                        <div class="sidebar-jlg-dimension-grid" data-sidebar-dimension-grid>
                                            <div class="sidebar-jlg-dimension-grid__item">
                                                <span class="sidebar-jlg-dimension-grid__label"><?php esc_html_e( 'Hauteur de la barre', 'sidebar-jlg' ); ?></span>
                                                <div class="sidebar-jlg-dimension-grid__control">
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
                                                </div>
                                                <p class="description sidebar-jlg-dimension-grid__description"><?php esc_html_e( 'Utilisez des unités CSS (ex : 4rem, 72px).', 'sidebar-jlg' ); ?></p>
                                            </div>
                                        </div>
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
                                    <div class="push-option-field" data-sidebar-mode="expert" style="<?php echo $options['desktop_behavior'] === 'push' ? '' : 'display:none;'; ?>">
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
                            <tr data-sidebar-mode="expert">
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
                            <tr data-sidebar-mode="expert">
                                <th scope="row"><?php esc_html_e( 'Dimensions', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <p><label for="sidebar-jlg-width-desktop"><?php esc_html_e( 'Largeur (Desktop)', 'sidebar-jlg' ); ?></label> <input type="number" id="sidebar-jlg-width-desktop" name="sidebar_jlg_settings[width_desktop]" value="<?php echo esc_attr( $options['width_desktop'] ); ?>" class="small-text"/> <span class="description">px</span></p>
                                    <p><label for="sidebar-jlg-width-tablet"><?php esc_html_e( 'Largeur (Tablette)', 'sidebar-jlg' ); ?></label> <input type="number" id="sidebar-jlg-width-tablet" name="sidebar_jlg_settings[width_tablet]" value="<?php echo esc_attr( $options['width_tablet'] ); ?>" class="small-text"/> <span class="description">px</span> <em class="description"><?php esc_html_e( 'Appliquée entre 768px et 992px.', 'sidebar-jlg' ); ?></em></p>
                                    <p>
                                        <label for="sidebar-jlg-width-mobile"><?php esc_html_e( 'Largeur (Mobile)', 'sidebar-jlg' ); ?></label>
                                        <input type="text" id="sidebar-jlg-width-mobile" name="sidebar_jlg_settings[width_mobile]" value="<?php echo esc_attr( $options['width_mobile'] ); ?>" class="small-text" />
                                        <em class="description"><?php esc_html_e( '100 % par défaut pour couvrir l’écran. Accepte toute valeur CSS (320px, 85%, calc(100% - 2rem)…).', 'sidebar-jlg' ); ?></em>
                                    </p>
                                </td>
                            </tr>
                            <tr data-sidebar-mode="expert">
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
                        </table>
                    </div>
                </details>

                <details
                    id="sidebar-jlg-section-interactions"
                    class="sidebar-jlg-section"
                    data-sidebar-filterable
                    data-sidebar-filter-label="<?php esc_attr_e( 'Interactions & déclencheurs', 'sidebar-jlg' ); ?>"
                    data-sidebar-filter-keywords="<?php echo esc_attr( implode( ' ', [ __( 'gestes', 'sidebar-jlg' ), __( 'déclencheurs', 'sidebar-jlg' ), __( 'comportement', 'sidebar-jlg' ) ] ) ); ?>"
                    data-sidebar-guided-step
                    data-sidebar-guided-title="<?php esc_attr_e( 'Interactions & déclencheurs', 'sidebar-jlg' ); ?>"
                >
                    <summary class="sidebar-jlg-section__summary">
                        <span class="sidebar-jlg-section__title"><?php esc_html_e( 'Interactions & déclencheurs', 'sidebar-jlg' ); ?></span>
                        <span class="sidebar-jlg-section__description"><?php esc_html_e( 'Contrôlez la fermeture, la mémoire et les ouvertures automatiques.', 'sidebar-jlg' ); ?></span>
                    </summary>
                    <div class="sidebar-jlg-section__body">
                        <table class="form-table sidebar-jlg-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Fermeture automatique', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <?php $closeDescriptionId = 'sidebar-jlg-close-on-click'; ?>
                                    <label><input type="checkbox" name="sidebar_jlg_settings[close_on_link_click]" value="1" aria-describedby="<?php echo esc_attr( $closeDescriptionId ); ?>" <?php checked( $options['close_on_link_click'], 1 ); ?> /> <?php esc_html_e( 'Fermer automatiquement la sidebar après un clic sur un lien ou une icône sociale.', 'sidebar-jlg' ); ?></label>
                                    <p id="<?php echo esc_attr( $closeDescriptionId ); ?>" class="description"><?php esc_html_e( 'Recommandé sur mobile pour éviter qu’elle reste ouverte après la navigation.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Mémoire de session', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <label><input type="checkbox" name="sidebar_jlg_settings[remember_last_state]" value="1" <?php checked( $options['remember_last_state'], 1 ); ?> /> <?php esc_html_e( 'Rouvrir la sidebar comme à la dernière visite (état, sous-menus, position de défilement).', 'sidebar-jlg' ); ?></label>
                                    <p class="description"><?php esc_html_e( 'Utilise le stockage local du navigateur pour restaurer les sous-menus ouverts, la position de scroll et les CTA consultés.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Gestes tactiles', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="sidebar_jlg_settings[touch_gestures_edge_swipe]" value="1" <?php checked( ! empty( $options['touch_gestures_edge_swipe'] ), 1 ); ?> />
                                            <?php esc_html_e( 'Autoriser l’ouverture en glissant depuis le bord (mobile).', 'sidebar-jlg' ); ?>
                                        </label>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="sidebar_jlg_settings[touch_gestures_close_swipe]" value="1" <?php checked( ! empty( $options['touch_gestures_close_swipe'] ), 1 ); ?> />
                                            <?php esc_html_e( 'Permettre la fermeture en glissant la sidebar vers l’extérieur.', 'sidebar-jlg' ); ?>
                                        </label>
                                    </p>
                                    <p>
                                        <label for="sidebar-jlg-gesture-edge-size"><?php esc_html_e( 'Largeur de la zone active', 'sidebar-jlg' ); ?></label>
                                        <input
                                            type="number"
                                            id="sidebar-jlg-gesture-edge-size"
                                            name="sidebar_jlg_settings[touch_gestures_edge_size]"
                                            class="small-text"
                                            min="0"
                                            max="200"
                                            step="1"
                                            value="<?php echo esc_attr( (string) (int) ( $options['touch_gestures_edge_size'] ?? $defaults['touch_gestures_edge_size'] ?? 32 ) ); ?>"
                                        />
                                        <span class="description">px</span>
                                    </p>
                                    <p>
                                        <label for="sidebar-jlg-gesture-min-distance"><?php esc_html_e( 'Distance minimale du geste', 'sidebar-jlg' ); ?></label>
                                        <?php
                                        $gesture_min_distance = sidebar_jlg_clamp_number_to_step(
                                            $options['touch_gestures_min_distance'] ?? ( $defaults['touch_gestures_min_distance'] ?? 95 ),
                                            30,
                                            600,
                                            5,
                                            95
                                        );
                                        ?>
                                        <input
                                            type="number"
                                            id="sidebar-jlg-gesture-min-distance"
                                            name="sidebar_jlg_settings[touch_gestures_min_distance]"
                                            class="small-text"
                                            min="30"
                                            max="600"
                                            step="5"
                                            value="<?php echo esc_attr( (string) $gesture_min_distance ); ?>"
                                        />
                                        <span class="description">px</span>
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Ajoute des gestes type « swipe » comparables aux barres latérales professionnelles. Les paramètres ne s’appliquent que sous 992px de large.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Déclencheurs comportementaux', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <p>
                                        <label for="sidebar-jlg-auto-open-delay"><?php esc_html_e( 'Ouvrir automatiquement après (secondes)', 'sidebar-jlg' ); ?></label>
                                        <input
                                            type="number"
                                            id="sidebar-jlg-auto-open-delay"
                                            name="sidebar_jlg_settings[auto_open_time_delay]"
                                            min="0"
                                            max="600"
                                            step="1"
                                            value="<?php echo esc_attr( (string) (int) ( $options['auto_open_time_delay'] ?? 0 ) ); ?>"
                                            class="small-text"
                                        />
                                    </p>
                                    <p>
                                        <label for="sidebar-jlg-auto-open-scroll"><?php esc_html_e( 'Ouvrir après un pourcentage de scroll', 'sidebar-jlg' ); ?></label>
                                        <input
                                            type="number"
                                            id="sidebar-jlg-auto-open-scroll"
                                            name="sidebar_jlg_settings[auto_open_scroll_depth]"
                                            min="0"
                                            max="100"
                                            step="5"
                                            value="<?php echo esc_attr( (string) (int) ( $options['auto_open_scroll_depth'] ?? 0 ) ); ?>"
                                            class="small-text"
                                        />
                                        <span class="description" style="margin-left: 0.5rem;">%</span>
                                    </p>
                                    <p>
                                        <label>
                                            <input type="checkbox" name="sidebar_jlg_settings[auto_open_exit_intent]" value="1" <?php checked( ! empty( $options['auto_open_exit_intent'] ) ); ?> />
                                            <?php esc_html_e( 'Déclencher lorsque le curseur quitte la fenêtre (exit intent).', 'sidebar-jlg' ); ?>
                                        </label>
                                        <span class="description"><?php esc_html_e( 'Idéal pour relancer les visiteurs desktop hésitants. Ignoré sur les terminaux tactiles.', 'sidebar-jlg' ); ?></span>
                                    </p>
                                    <p>
                                        <label for="sidebar-jlg-auto-open-inactivity"><?php esc_html_e( 'Ouvrir après inactivité (secondes)', 'sidebar-jlg' ); ?></label>
                                        <input
                                            type="number"
                                            id="sidebar-jlg-auto-open-inactivity"
                                            name="sidebar_jlg_settings[auto_open_inactivity_delay]"
                                            min="0"
                                            max="1800"
                                            step="5"
                                            value="<?php echo esc_attr( (string) (int) ( $options['auto_open_inactivity_delay'] ?? 0 ) ); ?>"
                                            class="small-text"
                                        />
                                    </p>
                                    <p class="description"><?php esc_html_e( 'Définissez 0 pour désactiver un déclencheur. La sidebar ne se rouvrira pas automatiquement si l’utilisateur l’a refermée manuellement.', 'sidebar-jlg' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </details>

                <details
                    id="sidebar-jlg-section-accessibility"
                    class="sidebar-jlg-section"
                    data-sidebar-filterable
                    data-sidebar-filter-label="<?php esc_attr_e( 'Accessibilité & recherche', 'sidebar-jlg' ); ?>"
                    data-sidebar-filter-keywords="<?php echo esc_attr( implode( ' ', [ __( 'accessibilité', 'sidebar-jlg' ), __( 'recherche', 'sidebar-jlg' ), __( 'aria', 'sidebar-jlg' ) ] ) ); ?>"
                    data-sidebar-guided-step
                    data-sidebar-guided-title="<?php esc_attr_e( 'Accessibilité & recherche', 'sidebar-jlg' ); ?>"
                >
                    <summary class="sidebar-jlg-section__summary">
                        <span class="sidebar-jlg-section__title"><?php esc_html_e( 'Accessibilité & recherche', 'sidebar-jlg' ); ?></span>
                        <span class="sidebar-jlg-section__description"><?php esc_html_e( 'Libellés inclusifs et options de recherche.', 'sidebar-jlg' ); ?></span>
                    </summary>
                    <div class="sidebar-jlg-section__body">
                        <table class="form-table sidebar-jlg-form-table">
                            <tr>
                                <th scope="row"><?php esc_html_e( 'Libellé ARIA de la navigation', 'sidebar-jlg' ); ?></th>
                                <td>
                                    <?php $ariaHelpId = 'sidebar-jlg-nav-aria-help'; ?>
                                    <input type="text" class="regular-text" name="sidebar_jlg_settings[nav_aria_label]" aria-describedby="<?php echo esc_attr( $ariaHelpId ); ?>" value="<?php echo esc_attr( $options['nav_aria_label'] ?? '' ); ?>" />
                                    <p id="<?php echo esc_attr( $ariaHelpId ); ?>" class="description"><?php esc_html_e( 'Définit le texte de l’attribut aria-label du bloc de navigation pour les lecteurs d’écran.', 'sidebar-jlg' ); ?></p>
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
                                            <label for="sidebar-jlg-search-shortcode"><?php esc_html_e( 'Shortcode :', 'sidebar-jlg' ); ?></label>
                                            <input type="text" id="sidebar-jlg-search-shortcode" name="sidebar_jlg_settings[search_shortcode]" value="<?php echo esc_attr( $options['search_shortcode'] ); ?>" class="regular-text" placeholder="[mon_shortcode_recherche]"/>
                                        </p>
                                        <p class="search-method-field search-hook-field" style="display:none;">
                                            <span class="description"><?php esc_html_e( 'Pour les moteurs de recherche complexes, ajoutez ce code à votre fichier `functions.php` :', 'sidebar-jlg' ); ?></span><br>
                                            <code>add_action('jlg_sidebar_search_area', function() { /* Votre code PHP ici */ });</code>
                                        </p>
                                        <p>
                                            <label for="sidebar-jlg-search-alignment"><?php esc_html_e( 'Alignement de la recherche', 'sidebar-jlg' ); ?></label>
                                            <select id="sidebar-jlg-search-alignment" name="sidebar_jlg_settings[search_alignment]">
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
                </details>
            </div>
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
                            <div class="sidebar-jlg-style-presets__quick" data-style-preset-quick>
                                <p class="sidebar-jlg-style-presets__quick-text"><?php esc_html_e( 'Prêts à l’emploi :', 'sidebar-jlg' ); ?></p>
                                <div class="sidebar-jlg-style-presets__quick-actions">
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="headless_minimal"><?php esc_html_e( 'SaaS clair', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="shadcn_soft"><?php esc_html_e( 'Sombre premium', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="bootstrap_classic"><?php esc_html_e( 'Corporate', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="astra_modern"><?php esc_html_e( 'Compatibilité Astra', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="divi_sleek"><?php esc_html_e( 'Divi dynamique', 'sidebar-jlg' ); ?></button>
                                    <button type="button" class="button button-secondary" data-style-preset-trigger="bricks_neutral"><?php esc_html_e( 'Bricks neutre', 'sidebar-jlg' ); ?></button>
                                </div>
                            </div>
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

        <?php
        $widgetSchemas = isset( $options['widget_schemas'] ) && is_array( $options['widget_schemas'] ) ? $options['widget_schemas'] : [];
        $registeredWidgets = isset( $options['widgets'] ) && is_array( $options['widgets'] ) ? $options['widgets'] : [];
        $widgetSchemasJson = wp_json_encode( $widgetSchemas );
        $widgetsJson = wp_json_encode( $registeredWidgets );
        ?>

        <div id="tab-widgets" class="tab-content" role="tabpanel" aria-labelledby="tab-widgets-tab" aria-hidden="true" hidden>
            <h2><?php esc_html_e( 'Widgets enrichis', 'sidebar-jlg' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Ajoutez des blocs CTA, formulaires, sliders ou intégrations WooCommerce à votre sidebar et personnalisez leur style.', 'sidebar-jlg' ); ?></p>

            <input type="hidden" name="sidebar_jlg_settings[widgets]" id="sidebar-jlg-widgets-field" value="<?php echo esc_attr( $widgetsJson ); ?>" />

            <div
                class="sidebar-jlg-widgets-builder"
                data-widget-builder
                data-widget-schemas="<?php echo esc_attr( $widgetSchemasJson ); ?>"
                data-widget-options="<?php echo esc_attr( $widgetsJson ); ?>"
            >
                <div class="sidebar-jlg-widgets-toolbar">
                    <label for="sidebar-jlg-widget-type" class="screen-reader-text"><?php esc_html_e( 'Type de widget', 'sidebar-jlg' ); ?></label>
                    <select id="sidebar-jlg-widget-type" data-widget-type>
                        <option value="" disabled selected><?php esc_html_e( 'Sélectionnez un type de widget…', 'sidebar-jlg' ); ?></option>
                        <?php foreach ( $widgetSchemas as $key => $schema ) :
                            if ( ! is_string( $key ) ) {
                                continue;
                            }

                            $label = isset( $schema['label'] ) && is_string( $schema['label'] ) ? $schema['label'] : ucfirst( $key );
                            ?>
                            <option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" class="button" data-widget-add><?php esc_html_e( 'Ajouter le widget', 'sidebar-jlg' ); ?></button>
                </div>

                <div class="sidebar-jlg-widgets-layout">
                    <div class="sidebar-jlg-widgets-list" id="sidebar-jlg-widgets-list" data-widget-list role="list" aria-label="<?php esc_attr_e( 'Liste des widgets configurés', 'sidebar-jlg' ); ?>"></div>

                    <div class="sidebar-jlg-widgets-editor" data-widget-editor>
                        <h3><?php esc_html_e( 'Configuration du widget', 'sidebar-jlg' ); ?></h3>
                        <p class="description" data-widget-editor-empty><?php esc_html_e( 'Sélectionnez un widget pour modifier son contenu et son apparence.', 'sidebar-jlg' ); ?></p>
                    </div>

                    <aside class="sidebar-jlg-widgets-preview" data-widget-preview>
                        <h3><?php esc_html_e( 'Aperçu', 'sidebar-jlg' ); ?></h3>
                        <div class="sidebar-jlg-widgets-preview-frame" data-widget-preview-frame></div>
                    </aside>
                </div>
            </div>
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
        <div id="tab-analytics" class="tab-content" role="tabpanel" aria-labelledby="tab-analytics-tab" aria-hidden="true" hidden>
            <div class="card">
                <h2><?php esc_html_e( 'Tableau de bord Insights & Analytics', 'sidebar-jlg' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Visualisez les interactions clés (ouvertures, clics de navigation, conversions CTA) pour rapprocher votre expérience de ce que proposent les suites professionnelles.', 'sidebar-jlg' ); ?></p>
                <?php $analyticsEnabled = ! empty( $options['enable_analytics'] ); ?>
                <?php if ( ! $analyticsEnabled ) : ?>
                    <p><?php esc_html_e( 'Activez la collecte dans l’onglet « Général & Comportement » pour commencer à enregistrer les métriques.', 'sidebar-jlg' ); ?></p>
                <?php
                else :
                    $analyticsTotals        = isset( $analyticsSummary['totals'] ) && is_array( $analyticsSummary['totals'] ) ? $analyticsSummary['totals'] : [];
                    $analyticsDaily         = isset( $analyticsSummary['daily'] ) && is_array( $analyticsSummary['daily'] ) ? $analyticsSummary['daily'] : [];
                    $analyticsProfilesData  = isset( $analyticsSummary['profiles'] ) && is_array( $analyticsSummary['profiles'] ) ? $analyticsSummary['profiles'] : [];
                    $analyticsTargets       = isset( $analyticsSummary['targets'] ) && is_array( $analyticsSummary['targets'] ) ? $analyticsSummary['targets'] : [];
                    $analyticsWindows       = isset( $analyticsSummary['windows'] ) && is_array( $analyticsSummary['windows'] ) ? $analyticsSummary['windows'] : [];
                    $analyticsSessions      = isset( $analyticsSummary['sessions'] ) && is_array( $analyticsSummary['sessions'] ) ? $analyticsSummary['sessions'] : [];
                    $sidebarOpensTotal      = (int) ( $analyticsTotals['sidebar_open'] ?? 0 );
                    $menuClicksTotal        = (int) ( $analyticsTotals['menu_link_click'] ?? 0 );
                    $ctaViewsTotal          = (int) ( $analyticsTotals['cta_view'] ?? 0 );
                    $ctaClicksTotal         = (int) ( $analyticsTotals['cta_click'] ?? 0 );
                    $sessionCount           = isset( $analyticsSessions['count'] ) ? (int) $analyticsSessions['count'] : 0;
                    $sessionAverageDuration = isset( $analyticsSessions['average_duration_ms'] ) ? (int) $analyticsSessions['average_duration_ms'] : 0;
                    $sessionMaxDuration     = isset( $analyticsSessions['max_duration_ms'] ) ? (int) $analyticsSessions['max_duration_ms'] : 0;
                    $sessionCloseReasons    = isset( $analyticsSessions['close_reasons'] ) && is_array( $analyticsSessions['close_reasons'] ) ? $analyticsSessions['close_reasons'] : [];
                    $sessionOpenTargets     = isset( $analyticsSessions['open_targets'] ) && is_array( $analyticsSessions['open_targets'] ) ? $analyticsSessions['open_targets'] : [];
                    $sessionInteractions    = isset( $analyticsSessions['interactions'] ) && is_array( $analyticsSessions['interactions'] ) ? $analyticsSessions['interactions'] : [];
                    $totalInteractions      = array_sum( array_map( 'intval', $analyticsTotals ) );
                    $clickRate              = $sidebarOpensTotal > 0 ? ( $menuClicksTotal / $sidebarOpensTotal ) * 100 : 0.0;
                    $ctaRate                = $ctaViewsTotal > 0 ? ( $ctaClicksTotal / $ctaViewsTotal ) * 100 : 0.0;
                    $recentDaily            = $analyticsDaily ? array_slice( array_reverse( $analyticsDaily, true ), 0, 7, true ) : [];
                    $analyticsLastEvent     = isset( $analyticsSummary['last_event_at'] ) ? $analyticsSummary['last_event_at'] : null;
                    $formattedLastEvent     = '';
                    if ( $analyticsLastEvent ) {
                        $lastTimestamp = strtotime( (string) $analyticsLastEvent );
                        if ( $lastTimestamp ) {
                            if ( function_exists( 'wp_date' ) ) {
                                $dateFormat = function_exists( 'get_option' ) ? ( get_option( 'date_format' ) ?: 'Y-m-d' ) : 'Y-m-d';
                                $timeFormat = function_exists( 'get_option' ) ? ( get_option( 'time_format' ) ?: 'H:i' ) : 'H:i';
                                $formattedLastEvent = wp_date( $dateFormat . ' ' . $timeFormat, $lastTimestamp );
                            } else {
                                $formattedLastEvent = date( 'Y-m-d H:i', $lastTimestamp );
                            }
                        }
                    }
                    $targetLabels = [
                        'menu_link'      => __( 'Navigation principale', 'sidebar-jlg' ),
                        'social_link'    => __( 'Icônes sociales', 'sidebar-jlg' ),
                        'cta_button'     => __( 'Bouton CTA', 'sidebar-jlg' ),
                        'form_submit'    => __( 'Soumission formulaire', 'sidebar-jlg' ),
                        'slider_interaction' => __( 'Interaction slider', 'sidebar-jlg' ),
                        'product'        => __( 'Produit WooCommerce', 'sidebar-jlg' ),
                        'toggle_button'  => __( 'Bouton hamburger', 'sidebar-jlg' ),
                        'gesture_swipe'  => __( 'Geste tactile', 'sidebar-jlg' ),
                        'auto_timer'     => __( 'Déclencheur minuterie', 'sidebar-jlg' ),
                        'auto_scroll'    => __( 'Déclencheur scroll', 'sidebar-jlg' ),
                        'auto_trigger'   => __( 'Déclenchement automatique', 'sidebar-jlg' ),
                    ];
                    $eventLabels = [
                        'sidebar_open'    => __( 'Ouvertures', 'sidebar-jlg' ),
                        'menu_link_click' => __( 'Clics de navigation', 'sidebar-jlg' ),
                        'cta_view'        => __( 'Vues CTA', 'sidebar-jlg' ),
                        'cta_click'       => __( 'Clics CTA', 'sidebar-jlg' ),
                        'cta_conversion'  => __( 'Conversions CTA', 'sidebar-jlg' ),
                        'form_submit'     => __( 'Soumissions formulaire', 'sidebar-jlg' ),
                        'slider_interaction' => __( 'Interactions slider', 'sidebar-jlg' ),
                        'woocommerce_widget_click' => __( 'Interactions WooCommerce', 'sidebar-jlg' ),
                        'sidebar_session' => __( 'Sessions suivies', 'sidebar-jlg' ),
                    ];
                    $windowLast7 = isset( $analyticsWindows['last7'] ) && is_array( $analyticsWindows['last7'] ) ? $analyticsWindows['last7'] : [];
                    $windowLast30 = isset( $analyticsWindows['last30'] ) && is_array( $analyticsWindows['last30'] ) ? $analyticsWindows['last30'] : [];
                    $last7Totals = isset( $windowLast7['totals'] ) && is_array( $windowLast7['totals'] ) ? $windowLast7['totals'] : [];
                    $last30Totals = isset( $windowLast30['totals'] ) && is_array( $windowLast30['totals'] ) ? $windowLast30['totals'] : [];
                    $last7Days = isset( $windowLast7['days'] ) ? (int) $windowLast7['days'] : 0;
                    $last30Days = isset( $windowLast30['days'] ) ? (int) $windowLast30['days'] : 0;
                    ?>
                    <?php if ( $totalInteractions === 0 ) : ?>
                        <p><?php esc_html_e( 'Les métriques apparaîtront dès que vos visiteurs interagiront avec la sidebar.', 'sidebar-jlg' ); ?></p>
                    <?php else : ?>
                        <div class="sidebar-jlg-analytics-kpis">
                            <p><strong><?php echo esc_html( sidebar_jlg_format_metric_number( $sidebarOpensTotal ) ); ?></strong><br><span class="description"><?php esc_html_e( 'Ouvertures de la sidebar', 'sidebar-jlg' ); ?></span></p>
                            <p><strong><?php echo esc_html( sidebar_jlg_format_metric_number( $menuClicksTotal ) ); ?></strong><br><span class="description"><?php esc_html_e( 'Clics de navigation', 'sidebar-jlg' ); ?></span></p>
                            <p><strong><?php echo esc_html( sidebar_jlg_format_metric_number( $ctaClicksTotal ) ); ?></strong><br><span class="description"><?php esc_html_e( 'Conversions CTA', 'sidebar-jlg' ); ?></span></p>
                            <p><strong><?php echo esc_html( sidebar_jlg_format_percentage_label( $ctaRate ) ); ?></strong><br><span class="description"><?php esc_html_e( 'Taux de clic CTA', 'sidebar-jlg' ); ?></span></p>
                        </div>
                        <?php if ( $formattedLastEvent ) : ?>
                            <p><em><?php printf( esc_html__( 'Dernier événement enregistré : %s.', 'sidebar-jlg' ), esc_html( $formattedLastEvent ) ); ?></em></p>
                        <?php endif; ?>
                        <h3><?php esc_html_e( 'Synthèse globale', 'sidebar-jlg' ); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th scope="col"><?php esc_html_e( 'Événement', 'sidebar-jlg' ); ?></th>
                                    <th scope="col"><?php esc_html_e( 'Total', 'sidebar-jlg' ); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td><?php esc_html_e( 'Ouvertures', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $sidebarOpensTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Clics de navigation', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $menuClicksTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Taux de clic navigation / ouverture', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_percentage_label( $clickRate ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Vues CTA', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $ctaViewsTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Clics CTA', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $ctaClicksTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Conversions CTA', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $ctaConversionsTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Soumissions formulaire', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $formSubmitsTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Interactions slider', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $sliderInteractions ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Interactions WooCommerce', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $woocommerceClicksTotal ) ); ?></td></tr>
                                <tr><td><?php esc_html_e( 'Sessions suivies', 'sidebar-jlg' ); ?></td><td><?php echo esc_html( sidebar_jlg_format_metric_number( $analyticsTotals['sidebar_session'] ?? 0 ) ); ?></td></tr>
                            </tbody>
                        </table>
                        <?php
                        $hasWindowComparison = array_sum( array_map( 'intval', $last7Totals ) ) > 0 || array_sum( array_map( 'intval', $last30Totals ) ) > 0;
                        if ( $hasWindowComparison ) :
                            ?>
                            <h3><?php esc_html_e( 'Comparatif 7j / 30j', 'sidebar-jlg' ); ?></h3>
                            <p class="description"><?php esc_html_e( 'Visualisez le poids de la dernière semaine par rapport à la fenêtre des 30 derniers jours.', 'sidebar-jlg' ); ?></p>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e( 'Événement', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( '7 derniers jours', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( '30 derniers jours', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Part des 7 derniers jours', 'sidebar-jlg' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $eventLabels as $eventKey => $eventLabel ) :
                                        $value7 = (int) ( $last7Totals[ $eventKey ] ?? 0 );
                                        $value30 = (int) ( $last30Totals[ $eventKey ] ?? 0 );
                                        $share = $value30 > 0 ? ( $value7 / $value30 ) * 100 : ( $value7 > 0 ? 100 : 0 );
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $eventLabel ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $value7 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $value30 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_percentage_label( $share ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php if ( $last30Days > 0 && $last30Days < 30 ) : ?>
                                <p class="description"><?php printf( esc_html__( 'Fenêtre 30j calculée sur %s jour(s) de données disponibles.', 'sidebar-jlg' ), esc_html( sidebar_jlg_format_metric_number( $last30Days ) ) ); ?></p>
                            <?php endif; ?>
                            <?php if ( $last7Days > 0 && $last7Days < 7 ) : ?>
                                <p class="description"><?php printf( esc_html__( 'Fenêtre 7j calculée sur %s jour(s) de données disponibles.', 'sidebar-jlg' ), esc_html( sidebar_jlg_format_metric_number( $last7Days ) ) ); ?></p>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php
                        if ( $sessionCount > 0 ) :
                            $averageDurationLabel = sidebar_jlg_format_duration_label( $sessionAverageDuration );
                            $maxDurationLabel     = sidebar_jlg_format_duration_label( $sessionMaxDuration );
                            $sessionReasonLabels  = [
                                'user'          => __( 'Action manuelle', 'sidebar-jlg' ),
                                'overlay'       => __( 'Clic sur l’arrière-plan', 'sidebar-jlg' ),
                                'gesture'       => __( 'Geste tactile', 'sidebar-jlg' ),
                                'responsive'    => __( 'Adaptation responsive', 'sidebar-jlg' ),
                                'close_button'  => __( 'Bouton de fermeture', 'sidebar-jlg' ),
                                'toggle_button' => __( 'Bouton hamburger', 'sidebar-jlg' ),
                                'restart'       => __( 'Session relancée', 'sidebar-jlg' ),
                            ];
                            $sessionTargetLabels = [
                                'toggle_button' => __( 'Bouton hamburger', 'sidebar-jlg' ),
                                'gesture_swipe' => __( 'Geste tactile', 'sidebar-jlg' ),
                                'auto_timer'    => __( 'Déclencheur minuterie', 'sidebar-jlg' ),
                                'auto_scroll'   => __( 'Déclencheur scroll', 'sidebar-jlg' ),
                                'auto_trigger'  => __( 'Déclenchement automatique', 'sidebar-jlg' ),
                            ];
                            $interactionLabels = [
                                'menu_link_click'   => __( 'Clics de navigation', 'sidebar-jlg' ),
                                'social_link_click' => __( 'Clics réseaux sociaux', 'sidebar-jlg' ),
                                'cta_click'         => __( 'Conversions CTA', 'sidebar-jlg' ),
                            ];
                            $hasInteractionRows = false;
                            foreach ( $interactionLabels as $interactionKey => $_label ) {
                                if ( (int) ( $sessionInteractions[ $interactionKey ] ?? 0 ) > 0 ) {
                                    $hasInteractionRows = true;
                                    break;
                                }
                            }
                            ?>
                            <h3><?php esc_html_e( 'Sessions & engagement', 'sidebar-jlg' ); ?></h3>
                            <div class="sidebar-jlg-analytics-kpis">
                                <p><strong><?php echo esc_html( sidebar_jlg_format_metric_number( $sessionCount ) ); ?></strong><br><span class="description"><?php esc_html_e( 'Sessions suivies', 'sidebar-jlg' ); ?></span></p>
                                <p><strong><?php echo esc_html( $averageDurationLabel ); ?></strong><br><span class="description"><?php esc_html_e( 'Durée moyenne', 'sidebar-jlg' ); ?></span></p>
                                <p><strong><?php echo esc_html( $maxDurationLabel ); ?></strong><br><span class="description"><?php esc_html_e( 'Durée maximale', 'sidebar-jlg' ); ?></span></p>
                            </div>
                            <?php if ( $hasInteractionRows ) : ?>
                                <table class="widefat striped">
                                    <thead>
                                        <tr>
                                            <th scope="col"><?php esc_html_e( 'Interaction', 'sidebar-jlg' ); ?></th>
                                            <th scope="col"><?php esc_html_e( 'Total', 'sidebar-jlg' ); ?></th>
                                            <th scope="col"><?php esc_html_e( 'Moyenne / session', 'sidebar-jlg' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ( $interactionLabels as $interactionKey => $interactionLabel ) :
                                            $interactionTotal = (int) ( $sessionInteractions[ $interactionKey ] ?? 0 );
                                            if ( $interactionTotal <= 0 ) {
                                                continue;
                                            }
                                            $averagePerSession = $sessionCount > 0 ? $interactionTotal / $sessionCount : 0;
                                            ?>
                                            <tr>
                                                <td><?php echo esc_html( $interactionLabel ); ?></td>
                                                <td><?php echo esc_html( sidebar_jlg_format_metric_number( $interactionTotal ) ); ?></td>
                                                <td><?php echo esc_html( sidebar_jlg_format_metric_number( $averagePerSession, 2 ) ); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
                            <?php if ( ! empty( $sessionCloseReasons ) ) : ?>
                                <h4><?php esc_html_e( 'Motifs de fermeture', 'sidebar-jlg' ); ?></h4>
                                <ul class="ul-disc">
                                    <?php foreach ( array_slice( $sessionCloseReasons, 0, 5, true ) as $reasonKey => $count ) :
                                        $label = $sessionReasonLabels[ $reasonKey ] ?? (string) $reasonKey;
                                        ?>
                                        <li><?php echo esc_html( $label ); ?> — <?php echo esc_html( sidebar_jlg_format_metric_number( $count ) ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                            <?php if ( ! empty( $sessionOpenTargets ) ) : ?>
                                <h4><?php esc_html_e( 'Déclencheurs principaux', 'sidebar-jlg' ); ?></h4>
                                <ul class="ul-disc">
                                    <?php foreach ( array_slice( $sessionOpenTargets, 0, 5, true ) as $targetKey => $count ) :
                                        $label = $sessionTargetLabels[ $targetKey ] ?? (string) $targetKey;
                                        ?>
                                        <li><?php echo esc_html( $label ); ?> — <?php echo esc_html( sidebar_jlg_format_metric_number( $count ) ); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if ( ! empty( $recentDaily ) ) : ?>
                            <h3><?php esc_html_e( '7 derniers jours', 'sidebar-jlg' ); ?></h3>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e( 'Date', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Ouvertures', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Clics de navigation', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Clics CTA', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Sessions suivies', 'sidebar-jlg' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $recentDaily as $dateKey => $counts ) :
                                        $counts = is_array( $counts ) ? $counts : [];
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( sidebar_jlg_format_local_date( (string) $dateKey ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $counts['sidebar_open'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $counts['menu_link_click'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $counts['cta_click'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $counts['sidebar_session'] ?? 0 ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if ( ! empty( $analyticsProfilesData ) ) : ?>
                            <h3><?php esc_html_e( 'Performance par profil', 'sidebar-jlg' ); ?></h3>
                            <table class="widefat striped">
                                <thead>
                                    <tr>
                                        <th scope="col"><?php esc_html_e( 'Profil', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Ouvertures', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Clics navigation', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Clics CTA', 'sidebar-jlg' ); ?></th>
                                        <th scope="col"><?php esc_html_e( 'Sessions', 'sidebar-jlg' ); ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ( $analyticsProfilesData as $profileId => $profileData ) :
                                        if ( ! is_array( $profileData ) ) {
                                            continue;
                                        }
                                        $profileTotals = isset( $profileData['totals'] ) && is_array( $profileData['totals'] ) ? $profileData['totals'] : [];
                                        $profileLabel  = isset( $profileData['label'] ) && $profileData['label'] !== ''
                                            ? $profileData['label']
                                            : ( 'default' === $profileId ? __( 'Réglages globaux', 'sidebar-jlg' ) : (string) $profileId );
                                        if ( ! empty( $profileData['is_fallback'] ) ) {
                                            $profileLabel = sprintf( '%s · %s', $profileLabel, __( 'profil de repli', 'sidebar-jlg' ) );
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html( $profileLabel ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $profileTotals['sidebar_open'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $profileTotals['menu_link_click'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $profileTotals['cta_click'] ?? 0 ) ); ?></td>
                                            <td><?php echo esc_html( sidebar_jlg_format_metric_number( $profileTotals['sidebar_session'] ?? 0 ) ); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php
                        $targetsToDisplay = array_filter(
                            $analyticsTargets,
                            static fn( $counts ) => is_array( $counts ) && ! empty( $counts )
                        );
                        if ( ! empty( $targetsToDisplay ) ) :
                            ?>
                            <h3><?php esc_html_e( 'Répartition des surfaces', 'sidebar-jlg' ); ?></h3>
                            <ul>
                                <?php foreach ( $targetsToDisplay as $eventKey => $distribution ) :
                                    if ( ! is_array( $distribution ) || empty( $distribution ) ) {
                                        continue;
                                    }
                                    $eventLabel = $eventLabels[ $eventKey ] ?? $eventKey;
                                    ?>
                                    <li>
                                        <strong><?php echo esc_html( $eventLabel ); ?> :</strong>
                                        <ul>
                                            <?php foreach ( $distribution as $targetKey => $count ) :
                                                $label = $targetLabels[ $targetKey ] ?? (string) $targetKey;
                                                ?>
                                                <li><?php echo esc_html( $label ); ?> — <?php echo esc_html( sidebar_jlg_format_metric_number( $count ) ); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <div id="tab-accessibility" class="tab-content" role="tabpanel" aria-labelledby="tab-accessibility-tab" aria-hidden="true" hidden>
            <?php
            $reminderData = [];
            if ( isset( $lastAccessibilityAudit ) && is_array( $lastAccessibilityAudit ) ) {
                $reminderData = $lastAccessibilityAudit;
            }

            $reminderHasRun = ! empty( $reminderData['has_run'] );
            $reminderIsStale = array_key_exists( 'is_stale', $reminderData ) ? (bool) $reminderData['is_stale'] : true;
            $reminderClass = 'sidebar-jlg-accessibility-audit__reminder ' . ( $reminderHasRun && ! $reminderIsStale ? 'sidebar-jlg-accessibility-audit__reminder--fresh' : 'sidebar-jlg-accessibility-audit__reminder--stale' );
            $reminderIcon = $reminderHasRun && ! $reminderIsStale ? 'dashicons-yes-alt' : 'dashicons-warning';

            $reminderRelative = isset( $reminderData['relative'] ) && is_string( $reminderData['relative'] ) ? $reminderData['relative'] : '';
            $reminderReadable = isset( $reminderData['readable'] ) && is_string( $reminderData['readable'] ) ? $reminderData['readable'] : '';
            $reminderSummary = isset( $reminderData['summary_text'] ) && is_string( $reminderData['summary_text'] ) ? $reminderData['summary_text'] : '';
            $reminderTarget = isset( $reminderData['target_label'] ) && is_string( $reminderData['target_label'] ) ? $reminderData['target_label'] : '';

            $reminderTitle = '';
            $reminderMessage = '';

            if ( ! $reminderHasRun ) {
                $reminderTitle   = __( 'Aucun audit Pa11y n’a encore été enregistré.', 'sidebar-jlg' );
                $reminderMessage = __( 'Lancez un audit pour établir une base de référence et documenter votre conformité.', 'sidebar-jlg' );
            } elseif ( $reminderIsStale ) {
                $relativeText = $reminderRelative !== '' ? $reminderRelative : __( 'quelques semaines', 'sidebar-jlg' );
                $readableText = $reminderReadable !== '' ? $reminderReadable : __( 'date non disponible', 'sidebar-jlg' );
                $reminderTitle = sprintf(
                    /* translators: 1: relative time, 2: formatted date. */
                    __( 'Votre dernier audit remonte à %1$s (%2$s).', 'sidebar-jlg' ),
                    $relativeText,
                    $readableText
                );
                $reminderMessage = __( 'Relancez Pa11y pour garder vos contrôles à jour et éviter les régressions d’accessibilité.', 'sidebar-jlg' );
            } else {
                $relativeText = $reminderRelative !== '' ? $reminderRelative : __( 'quelques heures', 'sidebar-jlg' );
                $readableText = $reminderReadable !== '' ? $reminderReadable : __( 'date récente', 'sidebar-jlg' );
                $reminderTitle = sprintf(
                    /* translators: 1: relative time, 2: formatted date. */
                    __( 'Dernier audit réalisé il y a %1$s (%2$s).', 'sidebar-jlg' ),
                    $relativeText,
                    $readableText
                );
                $reminderMessage = __( 'Poursuivez vos vérifications régulières pour maintenir la conformité WCAG.', 'sidebar-jlg' );
            }

            $reminderCta = $reminderHasRun && ! $reminderIsStale
                ? __( 'Afficher les contrôles d’audit', 'sidebar-jlg' )
                : __( 'Lancer un audit maintenant', 'sidebar-jlg' );
            ?>
            <div id="sidebar-jlg-accessibility-audit-reminder" class="<?php echo esc_attr( $reminderClass ); ?>" role="status" aria-live="polite" data-has-run="<?php echo esc_attr( $reminderHasRun ? '1' : '0' ); ?>" data-is-stale="<?php echo esc_attr( $reminderIsStale ? '1' : '0' ); ?>">
                <span class="dashicons <?php echo esc_attr( $reminderIcon ); ?> sidebar-jlg-accessibility-audit__reminder-icon" id="sidebar-jlg-accessibility-audit-reminder-icon" aria-hidden="true"></span>
                <div class="sidebar-jlg-accessibility-audit__reminder-content">
                    <p class="sidebar-jlg-accessibility-audit__reminder-title" id="sidebar-jlg-accessibility-audit-reminder-title"><?php echo esc_html( $reminderTitle ); ?></p>
                    <p class="sidebar-jlg-accessibility-audit__reminder-message" id="sidebar-jlg-accessibility-audit-reminder-message"><?php echo esc_html( $reminderMessage ); ?></p>
                    <?php if ( $reminderSummary !== '' ) : ?>
                        <p class="sidebar-jlg-accessibility-audit__reminder-meta" id="sidebar-jlg-accessibility-audit-reminder-summary"><?php echo esc_html( $reminderSummary ); ?></p>
                    <?php endif; ?>
                    <?php if ( $reminderTarget !== '' ) : ?>
                        <p class="sidebar-jlg-accessibility-audit__reminder-meta" id="sidebar-jlg-accessibility-audit-reminder-url"><?php echo esc_html( $reminderTarget ); ?></p>
                    <?php endif; ?>
                </div>
                <div class="sidebar-jlg-accessibility-audit__reminder-actions">
                    <a href="#sidebar-jlg-accessibility-audit" class="button button-secondary" id="sidebar-jlg-accessibility-audit-reminder-action"><?php echo esc_html( $reminderCta ); ?></a>
                </div>
            </div>
            <div id="sidebar-jlg-accessibility-audit" class="sidebar-jlg-accessibility-audit" data-is-available="<?php echo esc_attr( $auditIsAvailable ? '1' : '0' ); ?>">
                <h3><?php esc_html_e( 'Audit automatisé (Pa11y)', 'sidebar-jlg' ); ?></h3>
                <p class="description"><?php esc_html_e( 'Exécutez Pa11y depuis WordPress pour contrôler une URL de votre site. Nécessite Node.js et la commande Pa11y sur le serveur.', 'sidebar-jlg' ); ?></p>
                <?php if ( ! empty( $auditChecks ) ) : ?>
                    <ul class="sidebar-jlg-accessibility-audit__checks">
                        <?php foreach ( $auditChecks as $check ) :
                            $check = is_array( $check ) ? $check : [];
                            $passed = ! empty( $check['passed'] );
                            $label = isset( $check['label'] ) && is_string( $check['label'] ) ? $check['label'] : '';
                            $help  = isset( $check['help'] ) && is_string( $check['help'] ) ? $check['help'] : '';
                            ?>
                            <li class="<?php echo esc_attr( $passed ? 'is-passed' : 'is-failed' ); ?>">
                                <span class="dashicons <?php echo esc_attr( $passed ? 'dashicons-yes' : 'dashicons-warning' ); ?>" aria-hidden="true"></span>
                                <span class="sidebar-jlg-accessibility-audit__check-label"><?php echo esc_html( $label ); ?></span>
                                <?php if ( $help && ! $passed ) : ?>
                                    <span class="description"><?php echo esc_html( $help ); ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ( ! $auditIsAvailable ) : ?>
                    <p class="sidebar-jlg-accessibility-audit__unavailable" role="alert"><?php esc_html_e( 'Pa11y n’est pas disponible actuellement. Vérifiez les prérequis ci-dessus avant de relancer un audit.', 'sidebar-jlg' ); ?></p>
                <?php endif; ?>
                <div class="sidebar-jlg-accessibility-audit__controls">
                    <label for="sidebar-jlg-audit-url" class="sidebar-jlg-accessibility-audit__label"><?php esc_html_e( 'URL à analyser', 'sidebar-jlg' ); ?></label>
                    <div class="sidebar-jlg-accessibility-audit__inputs">
                        <input type="url" id="sidebar-jlg-audit-url" class="regular-text sidebar-jlg-accessibility-audit__url" value="<?php echo esc_attr( $auditDefaultUrl ); ?>" placeholder="https://example.com/" />
                        <button type="button" class="button button-secondary sidebar-jlg-accessibility-audit__launch"><?php esc_html_e( 'Lancer l’audit', 'sidebar-jlg' ); ?></button>
                    </div>
                </div>
                <div class="sidebar-jlg-accessibility-audit__status" aria-live="polite" role="status"></div>
                <div id="sidebar-jlg-audit-result" class="sidebar-jlg-accessibility-audit__result" role="region" aria-live="polite" hidden></div>
            </div>
            <div class="sidebar-jlg-accessibility" data-total-items="<?php echo esc_attr( $totalAccessibilityItems ); ?>" data-progress-template="<?php echo esc_attr( $accessibilityProgressTemplate ); ?>" data-progress-aria-template="<?php echo esc_attr( $accessibilityProgressAriaTemplate ); ?>">
                <header class="sidebar-jlg-accessibility__intro">
                    <h2><?php esc_html_e( 'Checklist d’accessibilité WCAG 2.2', 'sidebar-jlg' ); ?></h2>
                    <p class="description"><?php esc_html_e( 'Validez chaque critère avant publication pour garantir une expérience conforme aux exigences WCAG 2.2 niveau AA.', 'sidebar-jlg' ); ?></p>
                </header>
                <section class="sidebar-jlg-accessibility__progress" aria-live="polite">
                    <p class="sidebar-jlg-accessibility__progress-status"><?php echo esc_html( $accessibilityProgressText ); ?></p>
                    <progress class="sidebar-jlg-accessibility__progress-meter" max="<?php echo esc_attr( $totalAccessibilityItems ); ?>" value="<?php echo esc_attr( $completedAccessibilityItems ); ?>" aria-label="<?php echo esc_attr( $accessibilityProgressAria ); ?>"></progress>
                </section>
                <fieldset class="sidebar-jlg-accessibility__list">
                    <legend><?php esc_html_e( 'Critères à vérifier pour chaque profil', 'sidebar-jlg' ); ?></legend>
                    <ul class="sidebar-jlg-accessibility__items">
                        <?php foreach ( $accessibilityItems as $item ) :
                            $itemId = isset( $item['id'] ) && is_string( $item['id'] ) ? $item['id'] : '';
                            if ( '' === $itemId ) {
                                continue;
                            }

                            $checkboxId = 'sidebar-jlg-accessibility-' . sanitize_html_class( $itemId );
                            $descriptionId = $checkboxId . '-description';
                            $wcagId = $checkboxId . '-wcag';
                            $isChecked = ! empty( $normalizedAccessibilityStatuses[ $itemId ] );
                            $principle = isset( $item['principle'] ) && is_string( $item['principle'] ) ? $item['principle'] : '';
                            $title = isset( $item['title'] ) && is_string( $item['title'] ) ? $item['title'] : '';
                            $description = isset( $item['description'] ) && is_string( $item['description'] ) ? $item['description'] : '';
                            $wcagCodes = isset( $item['wcag'] ) && is_array( $item['wcag'] ) ? array_filter( array_map( 'strval', $item['wcag'] ) ) : [];
                            $resources = isset( $item['resources'] ) && is_array( $item['resources'] ) ? $item['resources'] : [];
                            $describedby = trim( $descriptionId . ' ' . $wcagId );
                            ?>
                            <li class="sidebar-jlg-accessibility__item">
                                <div class="sidebar-jlg-accessibility__item-header">
                                    <input type="checkbox" name="sidebar_jlg_accessibility_checklist[<?php echo esc_attr( $itemId ); ?>]" value="1" id="<?php echo esc_attr( $checkboxId ); ?>" <?php checked( $isChecked ); ?> aria-describedby="<?php echo esc_attr( $describedby ); ?>" />
                                    <label for="<?php echo esc_attr( $checkboxId ); ?>">
                                        <span class="sidebar-jlg-accessibility__item-title"><?php echo esc_html( $title ); ?></span>
                                        <?php if ( '' !== $principle ) : ?>
                                            <span class="sidebar-jlg-accessibility__item-principle"><?php echo esc_html( $principle ); ?></span>
                                        <?php endif; ?>
                                    </label>
                                </div>
                                <?php if ( '' !== $description ) : ?>
                                    <p class="sidebar-jlg-accessibility__item-description" id="<?php echo esc_attr( $descriptionId ); ?>"><?php echo esc_html( $description ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $wcagCodes ) ) :
                                    $wcagList = implode( ', ', $wcagCodes );
                                    ?>
                                    <p class="sidebar-jlg-accessibility__item-wcag" id="<?php echo esc_attr( $wcagId ); ?>"><?php printf( esc_html__( 'WCAG %s', 'sidebar-jlg' ), esc_html( $wcagList ) ); ?></p>
                                <?php endif; ?>
                                <?php if ( ! empty( $resources ) ) :
                                    $validResources = array_values( array_filter(
                                        $resources,
                                        static function ( $resource ): bool {
                                            if ( ! is_array( $resource ) ) {
                                                return false;
                                            }

                                            $label = $resource['label'] ?? '';
                                            $url = $resource['url'] ?? '';

                                            return is_string( $label ) && '' !== $label && is_string( $url ) && '' !== $url;
                                        }
                                    ) );

                                    if ( ! empty( $validResources ) ) :
                                        ?>
                                        <p class="sidebar-jlg-accessibility__item-resources">
                                            <?php esc_html_e( 'Ressources :', 'sidebar-jlg' ); ?>
                                            <?php foreach ( $validResources as $resourceIndex => $resource ) :
                                                $resourceLabel = (string) $resource['label'];
                                                $resourceUrl = (string) $resource['url'];
                                                ?>
                                                <a href="<?php echo esc_url( $resourceUrl ); ?>" target="_blank" rel="noopener noreferrer" class="sidebar-jlg-accessibility__resource-link"><?php echo esc_html( $resourceLabel ); ?></a><?php if ( $resourceIndex < count( $validResources ) - 1 ) : ?> <span aria-hidden="true">·</span> <?php endif; ?>
                                            <?php endforeach; ?>
                                        </p>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </fieldset>
                <p class="description sidebar-jlg-accessibility__export-hint"><?php esc_html_e( 'Les critères cochés sont enregistrés dans la base WordPress : exportez vos réglages pour partager l’état d’avancement avec votre équipe.', 'sidebar-jlg' ); ?></p>
            </div>
        </div>

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
