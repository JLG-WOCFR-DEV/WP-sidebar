# Modèle de rapport d’audit accessibilité

Ce gabarit sert à consigner les résultats de `npm run audit:accessibility` ou d’audits manuels complémentaires. Dupliquez-le pour chaque page testée et rattachez-le à votre ticket QA.

## 1. Métadonnées
- **Page / URL** :
- **Date de l’audit** :
- **Auditeur·rice** :
- **Outil(s) utilisé(s)** : Pa11y, axe DevTools, NVDA, VoiceOver…
- **Profil Sidebar JLG actif** : (ID, libellé)

## 2. Résultats synthétiques
| Critère | Statut | Détails |
| --- | --- | --- |
| Contrastes (1.4.3 / 1.4.11) | ✅ / ⚠️ / ❌ | _Résumé + éléments concernés_ |
| Navigation clavier / focus (2.1.1 / 2.4.3 / 2.4.7) | ✅ / ⚠️ / ❌ | |
| Annonces lecteurs d’écran (1.3.x / 4.1.x) | ✅ / ⚠️ / ❌ | |
| Gestes & alternatives (2.5.x) | ✅ / ⚠️ / ❌ | |
| Animations & préférences (2.3.3 / 2.3.4) | ✅ / ⚠️ / ❌ | |

## 3. Constats détaillés
### 3.1 Problèmes bloquants (priorité haute)
- _ID/Description_ — _Étapes de reproduction_ — _Composant_ — _Statut_

### 3.2 Problèmes majeurs (priorité moyenne)
- …

### 3.3 Améliorations mineures / recommandations
- …

## 4. Captures & artefacts
- **Capture écran** :
- **Rapport JSON/HTML** : (joindre le fichier Pa11y / axe)

## 5. Actions & suivi
- **Correctifs ouverts** :
- **Retests prévus** : (date / responsable)
- **Notes complémentaires** :

> Astuce : enregistrez ce rapport à côté de vos exports `docs/a11y/*.md` afin de garder un historique des audits.
