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

if ( ! function_exists( 'sidebar_jlg_uninstall_is_assoc' ) ) {
    function sidebar_jlg_uninstall_is_assoc( array $array ): bool {
        foreach ( array_keys( $array ) as $key ) {
            if ( ! is_int( $key ) ) {
                return true;
            }
        }

        return false;
    }
}

if ( ! function_exists( 'sidebar_jlg_uninstall_normalize_suffix_key' ) ) {
    function sidebar_jlg_uninstall_normalize_suffix_key( $key ): string {
        if ( ! is_string( $key ) || '' === $key || '__default__' === $key ) {
            return '__default__';
        }

        $normalized = sidebar_jlg_uninstall_normalize_suffix( $key );

        return null === $normalized ? '__default__' : $normalized;
    }
}

if ( ! function_exists( 'sidebar_jlg_uninstall_suffix_from_key' ) ) {
    function sidebar_jlg_uninstall_suffix_from_key( string $key ): ?string {
        if ( '__default__' === $key ) {
            return null;
        }

        return $key;
    }
}

if ( ! function_exists( 'sidebar_jlg_uninstall_build_transient_key' ) ) {
    function sidebar_jlg_uninstall_build_transient_key( string $locale, ?string $suffix = null ): string {
        return 'sidebar_jlg_full_html_' . $locale . ( null === $suffix || '' === $suffix ? '' : '_' . $suffix );
    }
}

if ( ! is_array( $cached_locales ) ) {
    $cached_locales = [];
}

if ( sidebar_jlg_uninstall_is_assoc( $cached_locales ) ) {
    foreach ( $cached_locales as $locale_key => $profiles ) {
        $normalized_locale = sidebar_jlg_uninstall_normalize_locale( $locale_key );

        if ( '' === $normalized_locale ) {
            continue;
        }

        if ( ! is_array( $profiles ) ) {
            delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale ) );

            continue;
        }

        foreach ( $profiles as $suffix_key => $transient_key ) {
            $normalized_suffix_key = sidebar_jlg_uninstall_normalize_suffix_key( $suffix_key );
            $suffix_value        = sidebar_jlg_uninstall_suffix_from_key( $normalized_suffix_key );

            if ( is_string( $transient_key ) && '' !== $transient_key ) {
                delete_transient( $transient_key );
            } else {
                delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale, $suffix_value ) );
            }

            if ( null !== $suffix_value ) {
                delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale ) );
            }
        }
    }
} else {
    foreach ( $cached_locales as $entry ) {
        if ( ! is_array( $entry ) ) {
            continue;
        }

        $locale = isset( $entry['locale'] ) ? (string) $entry['locale'] : '';

        if ( '' === $locale ) {
            continue;
        }

        $normalized_locale = sidebar_jlg_uninstall_normalize_locale( $locale );

        delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale ) );

        $normalized_suffix = sidebar_jlg_uninstall_normalize_suffix( $entry['suffix'] ?? null );

        if ( null !== $normalized_suffix && '' !== $normalized_suffix ) {
            delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale, $normalized_suffix ) );
            delete_transient( sidebar_jlg_uninstall_build_transient_key( $normalized_locale ) );
        }
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
