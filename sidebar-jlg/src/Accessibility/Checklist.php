<?php

namespace JLG\Sidebar\Accessibility;

use function sanitize_key;

class Checklist
{
    public const DEFAULT_CONTEXT_KEY = '__global__';
    public const PROFILE_CONTEXT_PREFIX = 'profile__';

    /**
     * Returns the WCAG 2.2 AA items tracked in the accessibility checklist.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function getItems(): array
    {
        return [
            [
                'id' => 'text_contrast',
                'principle' => __('Perceptible', 'sidebar-jlg'),
                'title' => __('Contraste du texte et des composants', 'sidebar-jlg'),
                'description' => __('Vérifiez que les couleurs de texte, d’icônes et de boutons respectent un ratio de contraste d’au moins 4,5:1 (3:1 pour les éléments UI) dans tous les profils et préréglages.', 'sidebar-jlg'),
                'wcag' => ['1.4.3', '1.4.11'],
                'resources' => [
                    [
                        'label' => __('Guide W3C sur le contraste minimum', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html',
                    ],
                    [
                        'label' => __('Guide W3C sur le contraste des éléments non textuels', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/non-text-contrast.html',
                    ],
                ],
            ],
            [
                'id' => 'focus_visible',
                'principle' => __('Opérable', 'sidebar-jlg'),
                'title' => __('Focus visible et non masqué', 'sidebar-jlg'),
                'description' => __('Contrôlez que chaque lien, bouton et toggle affiche un indicateur de focus contrasté qui n’est jamais masqué par la sidebar (incluant le menu hamburger et les sous-menus).', 'sidebar-jlg'),
                'wcag' => ['2.4.7', '2.4.11'],
                'resources' => [
                    [
                        'label' => __('Focus visible (W3C)', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/focus-visible.html',
                    ],
                    [
                        'label' => __('Focus non masqué minimum (W3C)', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/focus-not-obscured-minimum.html',
                    ],
                ],
            ],
            [
                'id' => 'keyboard_navigation',
                'principle' => __('Opérable', 'sidebar-jlg'),
                'title' => __('Navigation clavier complète', 'sidebar-jlg'),
                'description' => __('Validez que toutes les interactions (ouverture/fermeture de la sidebar, navigation dans les sous-menus, CTA, recherche) sont réalisables au clavier sans piège de focus.', 'sidebar-jlg'),
                'wcag' => ['2.1.1', '2.1.2'],
                'resources' => [
                    [
                        'label' => __('Comprendre 2.1.1 Clavier', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/keyboard.html',
                    ],
                    [
                        'label' => __('Comprendre 2.1.2 Pas de piège clavier', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/no-keyboard-trap.html',
                    ],
                ],
            ],
            [
                'id' => 'target_size',
                'principle' => __('Opérable', 'sidebar-jlg'),
                'title' => __('Cibles tactiles suffisantes', 'sidebar-jlg'),
                'description' => __('Assurez-vous que les boutons d’ouverture, les icônes sociales et les éléments de menu respectent une taille cible d’au moins 24×24 px ou disposent d’un espacement équivalent.', 'sidebar-jlg'),
                'wcag' => ['2.5.8'],
                'resources' => [
                    [
                        'label' => __('Taille des cibles (minimum)', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/target-size-minimum.html',
                    ],
                ],
            ],
            [
                'id' => 'drag_alternatives',
                'principle' => __('Opérable', 'sidebar-jlg'),
                'title' => __('Alternative aux actions de glisser-déposer', 'sidebar-jlg'),
                'description' => __('Vérifiez que chaque fonctionnalité basée sur le glisser-déposer (réorganisation des menus, profils) possède une alternative accessible via boutons ou clavier.', 'sidebar-jlg'),
                'wcag' => ['2.5.7'],
                'resources' => [
                    [
                        'label' => __('Comprendre 2.5.7 Mouvements de glisser', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/dragging-movements.html',
                    ],
                ],
            ],
            [
                'id' => 'consistent_help',
                'principle' => __('Compréhensible', 'sidebar-jlg'),
                'title' => __('Aide et labels cohérents', 'sidebar-jlg'),
                'description' => __('Confirmez que les textes d’aide, infobulles et labels restent identiques entre les profils et que les instructions sont disponibles à l’endroit où l’utilisateur en a besoin.', 'sidebar-jlg'),
                'wcag' => ['3.2.6'],
                'resources' => [
                    [
                        'label' => __('Comprendre 3.2.6 Aide cohérente', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/consistent-help.html',
                    ],
                ],
            ],
            [
                'id' => 'motion_safety',
                'principle' => __('Robuste', 'sidebar-jlg'),
                'title' => __('Animations sûres et préférences utilisateurs', 'sidebar-jlg'),
                'description' => __('Testez que les animations rapides ou lumineuses respectent les préférences « réduire les animations » et ne déclenchent pas d’effets susceptibles de provoquer de l’inconfort.', 'sidebar-jlg'),
                'wcag' => ['2.3.3', '2.2.2'],
                'resources' => [
                    [
                        'label' => __('Animations et préférences réduites', 'sidebar-jlg'),
                        'url' => 'https://www.w3.org/WAI/WCAG22/Understanding/prefers-reduced-motion.html',
                    ],
                ],
            ],
        ];
    }

    /**
     * Returns the default (all unchecked) state for the checklist.
     */
    public static function getDefaultStatuses(): array
    {
        $defaults = [];

        foreach (self::getItems() as $item) {
            $defaults[$item['id']] = false;
        }

        return $defaults;
    }

    /**
     * Normalizes a list of checklist entries into a boolean map keyed by item id.
     *
     * @param array<string, mixed> $input
     *
     * @return array<string, bool>
     */
    public static function normalizeStatuses(array $input): array
    {
        $defaults = self::getDefaultStatuses();

        foreach ($defaults as $id => $default) {
            $defaults[$id] = !empty($input[$id]);
        }

        return $defaults;
    }

    public static function getContextKeyForProfile(string $profileId): string
    {
        $sanitized = sanitize_key($profileId);

        if ($sanitized === '') {
            return '';
        }

        return self::PROFILE_CONTEXT_PREFIX . $sanitized;
    }

    /**
     * @param mixed $contextKey
     */
    public static function normalizeContextKey($contextKey): string
    {
        if (!is_string($contextKey) || $contextKey === '') {
            return '';
        }

        if ($contextKey === self::DEFAULT_CONTEXT_KEY) {
            return self::DEFAULT_CONTEXT_KEY;
        }

        if (strpos($contextKey, self::PROFILE_CONTEXT_PREFIX) === 0) {
            $profileId = substr($contextKey, strlen(self::PROFILE_CONTEXT_PREFIX));
            $normalized = sanitize_key($profileId);

            return $normalized === '' ? '' : self::PROFILE_CONTEXT_PREFIX . $normalized;
        }

        $fallback = sanitize_key($contextKey);

        if ($fallback === '') {
            return '';
        }

        return self::PROFILE_CONTEXT_PREFIX . $fallback;
    }

    /**
     * @param mixed $stored
     *
     * @return array<string, array<string, bool>>
     */
    public static function normalizeStoredContexts($stored): array
    {
        $contexts = [];

        if (!is_array($stored)) {
            $contexts[self::DEFAULT_CONTEXT_KEY] = self::getDefaultStatuses();

            return $contexts;
        }

        $hasNested = false;

        foreach ($stored as $value) {
            if (is_array($value)) {
                $hasNested = true;

                break;
            }
        }

        if (!$hasNested) {
            $contexts[self::DEFAULT_CONTEXT_KEY] = self::normalizeStatuses($stored);
        } else {
            foreach ($stored as $contextKey => $statuses) {
                if (!is_array($statuses)) {
                    continue;
                }

                $normalizedKey = self::normalizeContextKey($contextKey);

                if ($normalizedKey === '') {
                    continue;
                }

                $contexts[$normalizedKey] = self::normalizeStatuses($statuses);
            }
        }

        if (!isset($contexts[self::DEFAULT_CONTEXT_KEY])) {
            $contexts[self::DEFAULT_CONTEXT_KEY] = self::getDefaultStatuses();
        }

        ksort($contexts);

        return $contexts;
    }
}
