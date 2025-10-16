<?php

namespace JLG\Sidebar\Settings;

class SettingsMaintenanceRunner
{
    public const CRON_HOOK = 'sidebar_jlg_run_settings_maintenance';

    private SettingsRepository $settings;

    public function __construct(SettingsRepository $settings)
    {
        $this->settings = $settings;
    }

    public function registerHooks(): void
    {
        if (!function_exists('add_action')) {
            return;
        }

        add_action('admin_init', [$this, 'maybeApplyQueuedRevalidations'], 20, 0);
        add_action(self::CRON_HOOK, [$this, 'applyQueuedRevalidations'], 10, 0);
        add_action(SettingsRepository::REVALIDATION_QUEUED_ACTION, [$this, 'scheduleMaintenanceRun'], 10, 0);

        if ($this->settings->hasQueuedRevalidation()) {
            $this->scheduleMaintenanceRun();
        }
    }

    public function maybeApplyQueuedRevalidations(): void
    {
        if (!$this->settings->hasQueuedRevalidation()) {
            return;
        }

        if (!$this->canCurrentUserRunMaintenance()) {
            return;
        }

        $this->applyQueuedRevalidations();
    }

    public function applyQueuedRevalidations(): void
    {
        if (!$this->settings->applyQueuedRevalidation()) {
            return;
        }

        if (function_exists('wp_clear_scheduled_hook')) {
            wp_clear_scheduled_hook(self::CRON_HOOK);
        }
    }

    public function scheduleMaintenanceRun(): void
    {
        if (!$this->settings->hasQueuedRevalidation()) {
            return;
        }

        if (!function_exists('wp_schedule_single_event') || !function_exists('wp_next_scheduled')) {
            return;
        }

        if (wp_next_scheduled(self::CRON_HOOK) !== false) {
            return;
        }

        $timestamp = time() + 60;
        wp_schedule_single_event($timestamp, self::CRON_HOOK);
    }

    private function canCurrentUserRunMaintenance(): bool
    {
        if (function_exists('wp_doing_cron') && wp_doing_cron()) {
            return true;
        }

        if (function_exists('current_user_can') && !current_user_can('manage_options')) {
            return false;
        }

        if (function_exists('is_user_logged_in') && !is_user_logged_in()) {
            return false;
        }

        if (function_exists('is_admin')) {
            return is_admin();
        }

        return false;
    }
}

