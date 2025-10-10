# Écarts fonctionnels observés

## 1. Séparateurs dans le constructeur de menu
- **Attendu dans le programme** : Le README indique que le constructeur d'éléments accepte notamment « liens personnalisés et séparateurs ».【F:README.md†L31-L32】
- **Implémentation actuelle** : Le modèle JavaScript `tmpl-menu-item` ne propose que six types (`custom`, `post`, `page`, `category`, `nav_menu`, `cta`) et aucun chemin de rendu n'accepte un type `separator`.【F:sidebar-jlg/includes/admin-page.php†L1973-L1993】
- **Conséquence** : impossible d'insérer un séparateur manuel dans les menus générés par le plugin, contrairement à ce qui est documenté.

## 2. Calcul d'accessibilité par profil
- **Attendu dans le programme** : Le README présente l'onglet Accessibilité avec un « calcul automatique du taux de conformité par profil ».【F:README.md†L41-L41】
- **Implémentation actuelle** : le calcul de progression lit/écrit l'option globale `sidebar_jlg_accessibility_checklist`, additionne les cases cochées puis affiche une jauge unique sans distinguer les profils ou l'utilisateur actif.【F:sidebar-jlg/includes/admin-page.php†L361-L396】
- **Conséquence** : toutes les équipes partagent un seul état de checklist, ce qui ne permet pas de suivre la conformité par profil ciblé comme annoncé.

Ces constats peuvent orienter les prochaines itérations de débogage afin d'aligner la couverture fonctionnelle avec la documentation du produit.
