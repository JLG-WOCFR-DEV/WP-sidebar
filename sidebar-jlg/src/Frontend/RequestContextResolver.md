# RequestContextResolver

Le service `RequestContextResolver` centralise toutes les données de contexte nécessaires aux composants frontend (rendu de la barre latérale, sélection de profil, etc.). Il expose une méthode unique `resolve()` qui retourne un tableau associatif contenant les informations normalisées sur la requête courante : contenus consultés, taxonomies, URL actuelle, rôles utilisateur, langue, appareil et signaux horaires.

## Ajouter un nouveau signal

1. **Ajouter la collecte dans le service** : étendre `resolve()` (ou une méthode privée dédiée) afin d'enrichir le tableau retourné avec la nouvelle clé. Veiller à garder la structure plate et à normaliser les valeurs (chaînes en minuscules, identifiants nettoyés, etc.).
2. **Documenter la clé** : mettre à jour ce fichier si le nouveau signal nécessite des précisions d'usage ou de format.
3. **Propager la donnée** : vérifier que les consommateurs (`SidebarRenderer`, `ProfileSelector`, tests, gabarits) lisent la nouvelle clé au lieu de recalculer le signal localement.
4. **Couverture de tests** : compléter `tests/request_context_resolver_test.php` (et les suites concernées) pour valider la présence de la nouvelle clé dans les scénarios pertinents.

En centralisant chaque ajout dans ce service et en suivant ces étapes, on évite les divergences entre les différentes implémentations qui consomment le contexte de requête.
