<?php
/**
 * Fichier de désinstallation pour Sidebar - JLG
 *
 * Déclenché lorsque l'utilisateur supprime le plugin depuis l'interface d'administration de WordPress.
 * Ce script nettoie la base de données de toutes les options et données temporaires créées par le plugin.
 *
 * @package   Sidebar_JLG
 * @version   4.1.0
 */

// Si le fichier est appelé directement, on arrête l'exécution pour des raisons de sécurité.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// 1. Supprimer l'option principale du plugin de la table wp_options.
// C'est ici que tous les réglages de la sidebar sont stockés.
delete_option( 'sidebar_jlg_settings' );
delete_option( 'sidebar_jlg_plugin_version' );

// 2. Supprimer tous les transients de cache générés pour les locales mémorisées.
$cached_locales = get_option( 'sidebar_jlg_cached_locales', [] );

if ( ! function_exists( 'sidebar_jlg_uninstall_normalize_locale' ) ) {
    /**
     * Normalise a locale value to match cache key expectations.
     */
    function sidebar_jlg_uninstall_normalize_locale( $locale ) {
        $normalized = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $locale );

        if ( null === $normalized || '' === $normalized ) {
            return 'default';
        }

        return $normalized;
    }
}

if ( ! function_exists( 'sidebar_jlg_uninstall_normalize_suffix' ) ) {
    /**
     * Normalise a suffix value using the same rules as MenuCache::normalizeSuffixValue().
     */
    function sidebar_jlg_uninstall_normalize_suffix( $suffix ) {
        if ( null === $suffix ) {
            return null;
        }

        $trimmed = trim( (string) $suffix );

        if ( '' === $trimmed ) {
            return null;
        }

        $sanitized = preg_replace( '/[^A-Za-z0-9_\-]/', '', $trimmed );

        if ( null === $sanitized || '' === $sanitized ) {
            $sanitized = substr( md5( $trimmed ), 0, 12 );
        }

        return strtolower( $sanitized );
    }
}

if ( ! is_array( $cached_locales ) ) {
    $cached_locales = [];
}

foreach ( $cached_locales as $entry ) {
    if ( ! is_array( $entry ) ) {
        continue;
    }

    $locale = isset( $entry['locale'] ) ? (string) $entry['locale'] : '';

    if ( '' === $locale ) {
        continue;
    }

    $normalized_locale = sidebar_jlg_uninstall_normalize_locale( $locale );

    delete_transient( 'sidebar_jlg_full_html_' . $normalized_locale );

    $normalized_suffix = sidebar_jlg_uninstall_normalize_suffix( $entry['suffix'] ?? null );

    if ( null !== $normalized_suffix && '' !== $normalized_suffix ) {
        delete_transient( 'sidebar_jlg_full_html_' . $normalized_locale . '_' . $normalized_suffix );
    }
}

// 3. Après la boucle, supprimer la liste des locales mises en cache
//    ainsi que le transient générique existant.
delete_option( 'sidebar_jlg_cached_locales' );
delete_transient( 'sidebar_jlg_full_html' );
delete_option( 'sidebar_jlg_custom_icon_index' );
delete_transient( 'sidebar_jlg_custom_icons_cache' );

// Note pour l'avenir : 
// Si le plugin devait créer des tables de base de données personnalisées ou d'autres options,
// il faudrait ajouter le code pour les supprimer ici.
// Exemple :
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ma_table_personnalisee" );
