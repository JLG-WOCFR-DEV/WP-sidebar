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

// 2. Supprimer tous les transients de cache générés pour les locales mémorisées.
$cached_locales = get_option( 'sidebar_jlg_cached_locales', [] );

if ( ! is_array( $cached_locales ) ) {
    $cached_locales = [];
}

foreach ( $cached_locales as $locale ) {
    $normalized = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $locale );

    if ( null === $normalized || '' === $normalized ) {
        $normalized = 'default';
    }

    delete_transient( 'sidebar_jlg_full_html_' . $normalized );
}

// 3. Après la boucle, supprimer la liste des locales mises en cache
//    ainsi que le transient générique existant.
delete_option( 'sidebar_jlg_cached_locales' );
delete_transient( 'sidebar_jlg_full_html' );

// Note pour l'avenir : 
// Si le plugin devait créer des tables de base de données personnalisées ou d'autres options,
// il faudrait ajouter le code pour les supprimer ici.
// Exemple :
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ma_table_personnalisee" );
