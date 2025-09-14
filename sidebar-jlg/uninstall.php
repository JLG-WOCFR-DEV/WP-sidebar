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

// 2. Supprimer le transient (cache) du menu.
// Cela garantit qu'aucune donnée temporaire ne reste dans la base de données.
delete_transient( 'sidebar_jlg_full_html' );

// Note pour l'avenir : 
// Si le plugin devait créer des tables de base de données personnalisées ou d'autres options,
// il faudrait ajouter le code pour les supprimer ici.
// Exemple :
// global $wpdb;
// $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}ma_table_personnalisee" );
