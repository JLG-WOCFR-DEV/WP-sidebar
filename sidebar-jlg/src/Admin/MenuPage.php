<?php

namespace JLG\Sidebar\Admin;

use JLG\Sidebar\Accessibility\AuditRunner;
use JLG\Sidebar\Accessibility\Checklist;
use JLG\Sidebar\Admin\View\ColorPickerField;
use JLG\Sidebar\Analytics\AnalyticsRepository;
use JLG\Sidebar\Icons\IconLibrary;
use JLG\Sidebar\Settings\SettingsRepository;
use function current_time;
use function esc_url;
use function esc_url_raw;
use function get_option;
use function home_url;
use function human_time_diff;
use function sanitize_key;
use function sanitize_text_field;
use function wp_create_nonce;
use function wp_date;
use const DAY_IN_SECONDS;

class MenuPage
{
    private SettingsRepository $settings;
    private SettingsSanitizer $sanitizer;
    private IconLibrary $icons;
    private ColorPickerField $colorPicker;
    private AnalyticsRepository $analytics;
    private string $pluginFile;
    private string $version;
    private AuditRunner $auditRunner;

    public function __construct(
        SettingsRepository $settings,
        SettingsSanitizer $sanitizer,
        IconLibrary $icons,
        ColorPickerField $colorPicker,
        AnalyticsRepository $analytics,
        string $pluginFile,
        string $version,
        AuditRunner $auditRunner
    ) {
        $this->settings = $settings;
        $this->sanitizer = $sanitizer;
        $this->icons = $icons;
        $this->colorPicker = $colorPicker;
        $this->analytics = $analytics;
        $this->pluginFile = $pluginFile;
        $this->version = $version;
        $this->auditRunner = $auditRunner;
    }

    public function registerHooks(): void
    {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_action('admin_notices', [$this, 'renderCustomIconNotice']);
    }

    public function addAdminMenu(): void
    {
        add_menu_page(
            __('Sidebar JLG Settings', 'sidebar-jlg'),
            __('Sidebar JLG', 'sidebar-jlg'),
            'manage_options',
            'sidebar-jlg',
            [$this, 'render'],
            'dashicons-slides',
            100
        );
    }

    public function registerSettings(): void
    {
        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_settings',
            [$this->sanitizer, 'sanitize_settings']
        );

        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_profiles',
            [
                'type' => 'array',
                'sanitize_callback' => [$this->sanitizer, 'sanitize_profiles'],
                'default' => [],
                'show_in_rest' => [
                    'schema' => $this->buildProfilesSchema(),
                ],
            ]
        );

        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_active_profile',
            [
                'type' => 'string',
                'sanitize_callback' => [$this->sanitizer, 'sanitize_active_profile'],
                'default' => '',
                'show_in_rest' => true,
            ]
        );

        register_setting(
            'sidebar_jlg_options_group',
            'sidebar_jlg_accessibility_checklist',
            [
                'type' => 'array',
                'sanitize_callback' => [$this->sanitizer, 'sanitize_accessibility_checklist'],
                'default' => Checklist::getDefaultStatuses(),
                'show_in_rest' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => $this->buildAccessibilityChecklistSchema(),
                    ],
                ],
            ]
        );
    }

    private function buildProfilesSchema(): array
    {
        return [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'description' => __('Profil de barre latérale enregistré.', 'sidebar-jlg'),
                'properties' => [
                    'id' => [
                        'type' => 'string',
                        'description' => __('Identifiant unique du profil.', 'sidebar-jlg'),
                    ],
                    'title' => [
                        'type' => 'string',
                        'description' => __('Titre du profil affiché dans l’interface d’administration.', 'sidebar-jlg'),
                    ],
                    'priority' => [
                        'type' => 'integer',
                        'description' => __('Priorité utilisée pour ordonner les profils.', 'sidebar-jlg'),
                    ],
                    'enabled' => [
                        'type' => 'boolean',
                        'description' => __('Indique si le profil est activé.', 'sidebar-jlg'),
                    ],
                    'conditions' => [
                        'type' => 'object',
                        'description' => __('Conditions qui déterminent quand le profil est appliqué.', 'sidebar-jlg'),
                        'properties' => [
                            'post_types' => [
                                'type' => 'array',
                                'description' => __('Types de contenu ciblés.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'string',
                                    'description' => __('Identifiant du type de contenu ciblé.', 'sidebar-jlg'),
                                ],
                            ],
                            'content_types' => [
                                'type' => 'array',
                                'description' => __('Contenus spécifiques utilisés pour cibler le profil.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'string',
                                    'description' => __('Identifiant du contenu ciblé.', 'sidebar-jlg'),
                                ],
                            ],
                            'taxonomies' => [
                                'type' => 'array',
                                'description' => __('Règles basées sur les taxonomies.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'object',
                                    'description' => __('Association d’une taxonomie et de ses termes.', 'sidebar-jlg'),
                                    'properties' => [
                                        'taxonomy' => [
                                            'type' => 'string',
                                            'description' => __('Identifiant de la taxonomie ciblée.', 'sidebar-jlg'),
                                        ],
                                        'terms' => [
                                            'type' => 'array',
                                            'description' => __('Termes de la taxonomie utilisés pour la règle.', 'sidebar-jlg'),
                                            'items' => [
                                                'type' => 'string',
                                                'description' => __('Terme de taxonomie ciblé.', 'sidebar-jlg'),
                                            ],
                                        ],
                                    ],
                                    'additionalProperties' => true,
                                ],
                            ],
                            'roles' => [
                                'type' => 'array',
                                'description' => __('Rôles utilisateurs ciblés.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'string',
                                    'description' => __('Identifiant du rôle utilisateur.', 'sidebar-jlg'),
                                ],
                            ],
                            'languages' => [
                                'type' => 'array',
                                'description' => __('Codes de langue ciblés.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'string',
                                    'description' => __('Code de langue ISO ciblé.', 'sidebar-jlg'),
                                ],
                            ],
                            'devices' => [
                                'type' => 'array',
                                'description' => __('Types d’appareils ciblés.', 'sidebar-jlg'),
                                'items' => [
                                    'type' => 'string',
                                    'description' => __('Type d’appareil (desktop ou mobile).', 'sidebar-jlg'),
                                ],
                            ],
                            'logged_in' => [
                                'type' => 'string',
                                'description' => __('Statut de connexion des visiteurs ciblés.', 'sidebar-jlg'),
                            ],
                            'schedule' => [
                                'type' => 'object',
                                'description' => __('Plage horaire durant laquelle le profil est actif.', 'sidebar-jlg'),
                                'properties' => [
                                    'start' => [
                                        'type' => 'string',
                                        'description' => __('Heure de début au format HH:MM.', 'sidebar-jlg'),
                                    ],
                                    'end' => [
                                        'type' => 'string',
                                        'description' => __('Heure de fin au format HH:MM.', 'sidebar-jlg'),
                                    ],
                                    'days' => [
                                        'type' => 'array',
                                        'description' => __('Jours de la semaine où le profil est actif.', 'sidebar-jlg'),
                                        'items' => [
                                            'type' => 'string',
                                            'description' => __('Jour de la semaine ciblé (mon, tue, etc.).', 'sidebar-jlg'),
                                        ],
                                    ],
                                ],
                                'additionalProperties' => true,
                            ],
                        ],
                        'additionalProperties' => true,
                    ],
                    'settings' => [
                        'type' => 'object',
                        'description' => __('Réglages personnalisés appliqués lorsque le profil est actif.', 'sidebar-jlg'),
                        'additionalProperties' => true,
                    ],
                ],
                'additionalProperties' => true,
            ],
        ];
    }

    public function enqueueAssets(string $hook): void
    {
        if ('toplevel_page_sidebar-jlg' !== $hook) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_media();
        wp_enqueue_style(
            'sidebar-jlg-admin-css',
            plugin_dir_url($this->pluginFile) . 'assets/css/admin-style.css',
            [],
            $this->version
        );

        wp_enqueue_style(
            'sidebar-jlg-admin-preview-css',
            plugin_dir_url($this->pluginFile) . 'assets/css/admin-preview.css',
            ['sidebar-jlg-admin-css'],
            $this->version
        );

        wp_enqueue_script(
            'sidebar-jlg-admin-js',
            plugin_dir_url($this->pluginFile) . 'assets/js/admin-script.js',
            ['jquery', 'wp-color-picker', 'jquery-ui-sortable', 'wp-util', 'wp-data', 'wp-api-fetch', 'wp-element', 'wp-components'],
            $this->version,
            true
        );

        $defaults = $this->settings->getDefaultSettings();
        $stylePresets = isset($defaults['style_presets']) && is_array($defaults['style_presets'])
            ? $this->formatStylePresetsForScript($defaults['style_presets'])
            : [];
        $options = $this->settings->getOptionsWithRevalidation();
        $rawProfiles = get_option('sidebar_jlg_profiles', []);
        $profiles = $this->sanitizer->sanitize_profiles_collection($rawProfiles);
        $activeProfile = get_option('sidebar_jlg_active_profile', '');
        $activeProfile = $this->sanitizer->sanitize_active_profile($activeProfile, 'sidebar_jlg_active_profile', $profiles);

        $profileChoices = [
            'post_types' => $this->getProfilePostTypeChoices(),
            'taxonomies' => $this->getProfileTaxonomyChoices(),
            'roles' => $this->getProfileRoleChoices(),
            'languages' => $this->getProfileLanguageChoices(),
            'devices' => $this->getProfileDeviceChoices(),
            'login_states' => $this->getProfileLoginStateChoices(),
            'schedule_days' => $this->getProfileScheduleDayChoices(),
        ];

        $auditReport = $this->auditRunner->getEnvironmentReport();
        $auditChecks = isset($auditReport['checks']) && is_array($auditReport['checks'])
            ? $auditReport['checks']
            : [];
        $lastAudit = $this->getLastAccessibilityAudit();

        wp_localize_script('sidebar-jlg-admin-js', 'sidebarJLG', [
            'ajax_url' => admin_url('admin-ajax.php', 'relative'),
            'nonce' => wp_create_nonce('jlg_ajax_nonce'),
            'reset_nonce' => wp_create_nonce('jlg_reset_nonce'),
            'tools_nonce' => wp_create_nonce('jlg_tools_nonce'),
            'preview_nonce' => wp_create_nonce('jlg_preview_nonce'),
            'options' => wp_parse_args($options, $defaults),
            'profiles' => $profiles,
            'active_profile' => $activeProfile,
            'profiles_nonce' => wp_create_nonce('sidebar_jlg_profiles'),
            'profile_choices' => $profileChoices,
            'icons_manifest' => $this->icons->getIconManifest(),
            'icon_fetch_action' => 'jlg_get_icon_svg',
            'icon_upload_action' => 'jlg_upload_custom_icon',
            'icon_upload_max_size' => IconLibrary::MAX_CUSTOM_ICON_FILESIZE,
            'preview_action' => 'jlg_render_preview',
            'svg_url_restrictions' => $this->sanitizer->getSvgUrlRestrictions(),
            'style_presets' => $stylePresets,
            'accessibility_audit' => [
                'action' => 'jlg_run_accessibility_audit',
                'nonce' => wp_create_nonce('jlg_accessibility_audit'),
                'default_url' => esc_url_raw(home_url('/')),
                'is_available' => !empty($auditReport['can_run']),
                'checks' => $auditChecks,
                'binary' => isset($auditReport['binary']) && is_string($auditReport['binary']) ? $auditReport['binary'] : '',
                'last_run' => $lastAudit,
            ],
            'i18n' => [
                'menuItemDefaultTitle' => __('Nouvel élément', 'sidebar-jlg'),
                'socialIconDefaultTitle' => __('Nouvelle icône', 'sidebar-jlg'),
                'svgUrlOutOfScopeWithDescription' => __('Cette URL ne sera pas enregistrée. Utilisez une adresse dans %s.', 'sidebar-jlg'),
                'svgUrlOutOfScope' => __('Cette URL ne sera pas enregistrée car elle est en dehors de la zone autorisée.', 'sidebar-jlg'),
                'invalidUrl' => __('URL invalide.', 'sidebar-jlg'),
                'httpOnly' => __('Seuls les liens HTTP(S) sont autorisés.', 'sidebar-jlg'),
                'iconPreviewAlt' => __('Aperçu', 'sidebar-jlg'),
                'iconUploadMediaTitle' => __('Sélectionner un fichier SVG', 'sidebar-jlg'),
                'iconUploadMediaButton' => __('Utiliser ce SVG', 'sidebar-jlg'),
                'iconUploadInProgress' => __('Téléversement du SVG en cours…', 'sidebar-jlg'),
                'iconUploadPreparing' => __('Préparation du fichier…', 'sidebar-jlg'),
                'iconUploadSuccess' => __('Icône SVG ajoutée.', 'sidebar-jlg'),
                'iconUploadErrorGeneric' => __('Le téléversement du SVG a échoué.', 'sidebar-jlg'),
                'iconUploadErrorMime' => __('Seuls les fichiers SVG sont acceptés.', 'sidebar-jlg'),
                'iconUploadErrorSize' => __('Le fichier dépasse la taille maximale autorisée de %d Ko.', 'sidebar-jlg'),
                'iconUploadErrorFetch' => __('Impossible de récupérer le fichier depuis la médiathèque.', 'sidebar-jlg'),
                'dismissNotice' => __('Ignorer cette notification.', 'sidebar-jlg'),
                'exportConfirm' => __('Voulez-vous exporter les réglages actuels ?', 'sidebar-jlg'),
                'exportInProgress' => __('Export en cours…', 'sidebar-jlg'),
                'exportSuccess' => __('Export terminé. Le téléchargement va démarrer.', 'sidebar-jlg'),
                'exportError' => __('Impossible de générer l’export.', 'sidebar-jlg'),
                'importConfirm' => __('Importer ces réglages écrasera la configuration actuelle. Continuer ?', 'sidebar-jlg'),
                'importInProgress' => __('Import en cours…', 'sidebar-jlg'),
                'importSuccess' => __('Réglages importés avec succès. Rechargement de la page…', 'sidebar-jlg'),
                'importError' => __('L’import des réglages a échoué.', 'sidebar-jlg'),
                'importMissingFile' => __('Veuillez sélectionner un fichier JSON avant de lancer l’import.', 'sidebar-jlg'),
                'searchNoTarget' => __('Aucun réglage filtrable sur cet onglet.', 'sidebar-jlg'),
                'searchResultsCount' => __('%d sections affichées.', 'sidebar-jlg'),
                'searchNoResults' => __('Aucun résultat ne correspond aux mots-clés saisis.', 'sidebar-jlg'),
                'experienceModeSimpleSummary' => __('Mode simple : %d option(s) avancée(s) masquée(s).', 'sidebar-jlg'),
                'experienceModeExpertSummary' => __('Mode expert : toutes les options sont visibles.', 'sidebar-jlg'),
                'guidedModeNext' => __('Suivant', 'sidebar-jlg'),
                'guidedModeFinish' => __('Terminer', 'sidebar-jlg'),
                'guidedModeStepLabel' => __('Étape %1$s sur %2$s', 'sidebar-jlg'),
                'auditRunning' => __('Audit en cours…', 'sidebar-jlg'),
                'auditMissingUrl' => __('Veuillez saisir une URL à analyser.', 'sidebar-jlg'),
                'auditGenericError' => __('L’audit d’accessibilité a échoué.', 'sidebar-jlg'),
                'auditNoIssues' => __('Pa11y n’a détecté aucun problème critique.', 'sidebar-jlg'),
                'auditSummaryTemplate' => __('%1$d erreur(s), %2$d avertissement(s), %3$d notice(s).', 'sidebar-jlg'),
                'auditTypeError' => __('Erreur', 'sidebar-jlg'),
                'auditTypeWarning' => __('Avertissement', 'sidebar-jlg'),
                'auditTypeNotice' => __('Notice', 'sidebar-jlg'),
                'auditLogTitle' => __('Sortie technique', 'sidebar-jlg'),
                'auditUnavailable' => __('Pa11y n’est pas disponible sur ce serveur. Vérifiez les prérequis listés ci-dessous.', 'sidebar-jlg'),
                'auditCompleted' => __('Audit terminé.', 'sidebar-jlg'),
                'auditExecutionTime' => __('Durée : %s ms', 'sidebar-jlg'),
                'auditDocumentTitle' => __('Titre de page : %s', 'sidebar-jlg'),
                'auditSeeDetails' => __('Afficher les détails', 'sidebar-jlg'),
                'auditHideDetails' => __('Masquer les détails', 'sidebar-jlg'),
                'auditIssueCode' => __('Code : %s', 'sidebar-jlg'),
                'auditIssueSelector' => __('Sélecteur : %s', 'sidebar-jlg'),
                'auditIssueContext' => __('Extrait :', 'sidebar-jlg'),
                'navMenuFieldLabel' => __('Menu WordPress', 'sidebar-jlg'),
                'navMenuSelectPlaceholder' => __('Sélectionnez un menu…', 'sidebar-jlg'),
                'navMenuDepthLabel' => __('Profondeur maximale', 'sidebar-jlg'),
                'navMenuDepthHelp' => __('0 = illimité', 'sidebar-jlg'),
                'navMenuFilterLabel' => __('Filtrage', 'sidebar-jlg'),
                'navMenuFilterAll' => __('Tous les éléments', 'sidebar-jlg'),
                'navMenuFilterTopLevel' => __('Uniquement le niveau 1', 'sidebar-jlg'),
                'navMenuFilterBranch' => __('Branche de la page courante', 'sidebar-jlg'),
                'profilesDefaultTitle' => __('Nouveau profil', 'sidebar-jlg'),
                'profilesListEmpty' => __('Aucun profil n’a encore été créé.', 'sidebar-jlg'),
                'profilesActionsLabel' => __('Actions sur les profils', 'sidebar-jlg'),
                'profilesActiveLabel' => __('Profil actif', 'sidebar-jlg'),
                'profilesDeleteConfirm' => __('Supprimer ce profil ?', 'sidebar-jlg'),
                'profilesSettingsEmpty' => __('Aucun réglage personnalisé n’est défini pour ce profil.', 'sidebar-jlg'),
                'profilesSettingsSummary' => __('Réglages personnalisés : %d champ(s).', 'sidebar-jlg'),
                'profilesCloneSuccess' => __('Les réglages actuels ont été associés au profil.', 'sidebar-jlg'),
                'profilesCloneError' => __('Impossible de copier les réglages actuels.', 'sidebar-jlg'),
                'profilesTaxonomyTermsPlaceholder' => __('Slugs ou IDs séparés par des virgules', 'sidebar-jlg'),
                'profilesConditionsDescription' => __('Définissez les règles qui activent ce profil.', 'sidebar-jlg'),
                'profilesInactiveBadge' => __('Profil désactivé', 'sidebar-jlg'),
                'profilesUseCurrentSettings' => __('Utiliser les réglages actuels', 'sidebar-jlg'),
                'profilesClearSettings' => __('Réinitialiser les réglages du profil', 'sidebar-jlg'),
                'profilesClearActive' => __('Ne sélectionner aucun profil actif', 'sidebar-jlg'),
                'profilesDefaultActiveLabel' => __('Réglages globaux', 'sidebar-jlg'),
                'profilesDeleteLabel' => __('Supprimer', 'sidebar-jlg'),
                'stylePresetCustomLabel' => __('Personnalisé', 'sidebar-jlg'),
                'stylePresetCustomDescription' => __('Utilisez vos propres combinaisons de couleurs, typographies et effets.', 'sidebar-jlg'),
                'stylePresetApplyLabel' => __('Utiliser ce préréglage', 'sidebar-jlg'),
                'stylePresetLoading' => __('Chargement des préréglages…', 'sidebar-jlg'),
                'stylePresetEmpty' => __('Aucun préréglage n’est disponible pour le moment.', 'sidebar-jlg'),
                'stylePresetCompareButton' => __('Comparer avant/après', 'sidebar-jlg'),
                'stylePresetCompareExit' => __('Revenir à l’après', 'sidebar-jlg'),
                'stylePresetCompareBefore' => __('Affichage : avant', 'sidebar-jlg'),
                'stylePresetCompareAfter' => __('Affichage : après', 'sidebar-jlg'),
            ],
            'preview_messages' => [
                'loading' => __('Chargement de l’aperçu…', 'sidebar-jlg'),
                'error' => __('Impossible de charger l’aperçu. Vérifiez vos droits ou votre connexion réseau.', 'sidebar-jlg'),
                'emptyMenu' => __('Ajoutez des éléments de menu pour alimenter la prévisualisation.', 'sidebar-jlg'),
                'refresh' => __('Actualiser l’aperçu', 'sidebar-jlg'),
                'activeProfile' => __('Profil actif : %s', 'sidebar-jlg'),
            ],
            'accessibility_checklist' => get_option(
                'sidebar_jlg_accessibility_checklist',
                Checklist::getDefaultStatuses()
            ),
        ]);
    }

    private function buildAccessibilityChecklistSchema(): array
    {
        $properties = [];

        foreach (Checklist::getItems() as $item) {
            $id = $item['id'] ?? '';
            if (!is_string($id) || $id === '') {
                continue;
            }

            $title = $item['title'] ?? '';
            $properties[$id] = [
                'type' => 'boolean',
                'description' => is_string($title) ? wp_strip_all_tags($title) : '',
            ];
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    private function getLastAccessibilityAudit(): array
    {
        $raw = get_option('sidebar_jlg_accessibility_audit_last_run');
        if (!is_array($raw)) {
            return [
                'has_run' => false,
                'is_stale' => true,
            ];
        }

        $timestamp = isset($raw['timestamp']) ? (int) $raw['timestamp'] : 0;
        $siteTime = isset($raw['site_time']) && is_string($raw['site_time']) ? $raw['site_time'] : '';
        $isoTime = isset($raw['iso_time']) && is_string($raw['iso_time']) ? $raw['iso_time'] : '';
        $targetUrl = isset($raw['target_url']) && is_string($raw['target_url'])
            ? esc_url_raw($raw['target_url'])
            : '';

        $summary = [];
        if (isset($raw['summary']) && is_array($raw['summary'])) {
            foreach ($raw['summary'] as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $summary[$key] = (int) $value;
            }
        }

        $issuesCount = isset($raw['issues_count']) ? max(0, (int) $raw['issues_count']) : 0;

        $meta = [];
        if (isset($raw['meta']) && is_array($raw['meta'])) {
            $meta['document_title'] = isset($raw['meta']['document_title']) && is_string($raw['meta']['document_title'])
                ? sanitize_text_field($raw['meta']['document_title'])
                : '';
            $meta['page_url'] = isset($raw['meta']['page_url']) && is_string($raw['meta']['page_url'])
                ? esc_url_raw($raw['meta']['page_url'])
                : '';
            $meta['execution_time_ms'] = isset($raw['meta']['execution_time_ms'])
                ? max(0, (int) $raw['meta']['execution_time_ms'])
                : 0;
            $meta['binary'] = isset($raw['meta']['binary']) && is_string($raw['meta']['binary'])
                ? sanitize_text_field($raw['meta']['binary'])
                : '';
        }

        $currentTimestamp = current_time('timestamp');
        $dateFormat = (string) get_option('date_format');
        $timeFormat = (string) get_option('time_format');
        $format = trim($dateFormat . ' ' . $timeFormat);
        if ($format === '') {
            $format = 'Y-m-d H:i';
        }

        $readable = $timestamp > 0 ? wp_date($format, $timestamp) : '';
        $relative = $timestamp > 0 ? human_time_diff($timestamp, $currentTimestamp) : '';
        $isStale = $timestamp > 0 ? ($currentTimestamp - $timestamp) >= (30 * DAY_IN_SECONDS) : true;
        $hasRun = $timestamp > 0;

        $summaryText = '';
        if (!empty($summary)) {
            $summaryText = sprintf(
                /* translators: 1: number of errors, 2: number of warnings, 3: number of notices. */
                __('%1$d erreur(s), %2$d avertissement(s), %3$d notice(s).', 'sidebar-jlg'),
                $summary['error'] ?? 0,
                $summary['warning'] ?? 0,
                $summary['notice'] ?? 0
            );
        }

        $targetLabel = '';
        if ($targetUrl !== '') {
            $targetLabel = sprintf(
                /* translators: %s: audited URL. */
                __('URL analysée : %s', 'sidebar-jlg'),
                $targetUrl
            );
        }

        return [
            'timestamp' => $timestamp,
            'site_time' => $siteTime,
            'iso_time' => $isoTime,
            'target_url' => $targetUrl,
            'target_label' => $targetLabel,
            'summary' => $summary,
            'summary_text' => $summaryText,
            'issues_count' => $issuesCount,
            'meta' => $meta,
            'relative' => $relative,
            'readable' => $readable,
            'is_stale' => $isStale,
            'has_run' => $hasRun,
        ];
    }

    public function render(): void
    {
        $colorPicker = $this->colorPicker;
        $defaults = $this->settings->getDefaultSettings();
        $stylePresets = $defaults['style_presets'] ?? [];
        $options = $this->settings->getOptionsWithRevalidation();
        $allIcons = $this->icons->getAllIcons();
        $analyticsSummary = $this->analytics->getSummary();
        $auditStatus = $this->auditRunner->getEnvironmentReport();
        $auditDefaultUrl = esc_url(home_url('/'));
        $lastAccessibilityAudit = $this->getLastAccessibilityAudit();

        require plugin_dir_path($this->pluginFile) . 'includes/admin-page.php';
    }

    public function renderCustomIconNotice(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $rejected = $this->icons->consumeRejectedCustomIcons();
        if (empty($rejected)) {
            return;
        }

        $message = sprintf(
            /* translators: %s: comma-separated list of SVG filenames. */
            __('Sidebar JLG: the following SVG files were ignored: %s.', 'sidebar-jlg'),
            implode(', ', array_map('sanitize_text_field', $rejected))
        );

        printf(
            '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
            esc_html($message)
        );
    }

    /**
     * @param array<string, mixed> $presets
     *
     * @return array<string, array<string, mixed>>
     */
    private function formatStylePresetsForScript(array $presets): array
    {
        $formatted = [];

        foreach ($presets as $key => $preset) {
            if (!is_array($preset)) {
                continue;
            }

            $normalizedKey = sanitize_key((string) $key);
            if ($normalizedKey === '') {
                continue;
            }

            $preview = isset($preset['preview']) && is_array($preset['preview'])
                ? $preset['preview']
                : [];

            $settings = isset($preset['settings']) && is_array($preset['settings'])
                ? $preset['settings']
                : [];

            $formatted[$normalizedKey] = [
                'label' => isset($preset['label']) && is_string($preset['label']) && $preset['label'] !== ''
                    ? __($preset['label'], 'sidebar-jlg')
                    : $normalizedKey,
                'description' => isset($preset['description']) && is_string($preset['description'])
                    ? __($preset['description'], 'sidebar-jlg')
                    : '',
                'preview' => [
                    'background' => isset($preview['background']) && is_string($preview['background'])
                        ? $preview['background']
                        : '',
                    'accent' => isset($preview['accent']) && is_string($preview['accent'])
                        ? $preview['accent']
                        : '',
                    'text' => isset($preview['text']) && is_string($preview['text'])
                        ? $preview['text']
                        : '',
                ],
                'settings' => $settings,
            ];
        }

        return $formatted;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfilePostTypeChoices(): array
    {
        if (!function_exists('get_post_types')) {
            return [
                ['value' => 'post', 'label' => __('Article', 'sidebar-jlg')],
                ['value' => 'page', 'label' => __('Page', 'sidebar-jlg')],
            ];
        }

        $objects = get_post_types(['public' => true], 'objects');
        if (!is_array($objects)) {
            $objects = [];
        }

        $choices = [];
        foreach ($objects as $object) {
            if (!is_object($object)) {
                continue;
            }

            $name = isset($object->name) ? sanitize_key((string) $object->name) : '';
            if ($name === '') {
                continue;
            }

            $label = '';
            if (isset($object->labels->singular_name) && is_string($object->labels->singular_name)) {
                $label = $object->labels->singular_name;
            } elseif (isset($object->label) && is_string($object->label)) {
                $label = $object->label;
            } else {
                $label = ucfirst($name);
            }

            $choices[$name] = [
                'value' => $name,
                'label' => $label,
            ];
        }

        if (!isset($choices['post'])) {
            $choices['post'] = ['value' => 'post', 'label' => __('Article', 'sidebar-jlg')];
        }

        if (!isset($choices['page'])) {
            $choices['page'] = ['value' => 'page', 'label' => __('Page', 'sidebar-jlg')];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileTaxonomyChoices(): array
    {
        if (!function_exists('get_taxonomies')) {
            return [];
        }

        $objects = get_taxonomies(['public' => true], 'objects');
        if (!is_array($objects)) {
            $objects = [];
        }

        $choices = [];
        foreach ($objects as $object) {
            if (!is_object($object)) {
                continue;
            }

            $name = isset($object->name) ? sanitize_key((string) $object->name) : '';
            if ($name === '') {
                continue;
            }

            $label = '';
            if (isset($object->labels->singular_name) && is_string($object->labels->singular_name)) {
                $label = $object->labels->singular_name;
            } elseif (isset($object->label) && is_string($object->label)) {
                $label = $object->label;
            } else {
                $label = ucfirst($name);
            }

            $choices[$name] = [
                'value' => $name,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileRoleChoices(): array
    {
        if (!function_exists('get_editable_roles')) {
            return [];
        }

        $roles = get_editable_roles();
        if (!is_array($roles)) {
            $roles = [];
        }

        $choices = [];
        foreach ($roles as $slug => $role) {
            $roleSlug = sanitize_key((string) $slug);
            if ($roleSlug === '') {
                continue;
            }

            $label = '';
            if (isset($role['name']) && is_string($role['name'])) {
                $label = $role['name'];
            } else {
                $label = ucfirst($roleSlug);
            }

            $choices[$roleSlug] = [
                'value' => $roleSlug,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileLanguageChoices(): array
    {
        if (!function_exists('get_available_languages')) {
            return [];
        }

        $languages = get_available_languages();
        if (!is_array($languages)) {
            $languages = [];
        }

        $choices = [];
        foreach ($languages as $language) {
            $code = is_string($language) ? trim($language) : '';
            if ($code === '') {
                continue;
            }

            $label = $code;
            if (function_exists('locale_get_display_language')) {
                $display = locale_get_display_language($code, get_locale());
                if (is_string($display) && $display !== '') {
                    $label = sprintf('%s (%s)', $display, $code);
                }
            }

            $choices[$code] = [
                'value' => $code,
                'label' => $label,
            ];
        }

        return array_values($choices);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileDeviceChoices(): array
    {
        return [
            ['value' => 'desktop', 'label' => __('Ordinateur (bureau)', 'sidebar-jlg')],
            ['value' => 'mobile', 'label' => __('Mobile', 'sidebar-jlg')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileLoginStateChoices(): array
    {
        return [
            ['value' => 'any', 'label' => __('Tous les visiteurs', 'sidebar-jlg')],
            ['value' => 'logged-in', 'label' => __('Utilisateurs connectés', 'sidebar-jlg')],
            ['value' => 'logged-out', 'label' => __('Visiteurs non connectés', 'sidebar-jlg')],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function getProfileScheduleDayChoices(): array
    {
        return [
            ['value' => 'mon', 'label' => __('Lundi', 'sidebar-jlg')],
            ['value' => 'tue', 'label' => __('Mardi', 'sidebar-jlg')],
            ['value' => 'wed', 'label' => __('Mercredi', 'sidebar-jlg')],
            ['value' => 'thu', 'label' => __('Jeudi', 'sidebar-jlg')],
            ['value' => 'fri', 'label' => __('Vendredi', 'sidebar-jlg')],
            ['value' => 'sat', 'label' => __('Samedi', 'sidebar-jlg')],
            ['value' => 'sun', 'label' => __('Dimanche', 'sidebar-jlg')],
        ];
    }
}
