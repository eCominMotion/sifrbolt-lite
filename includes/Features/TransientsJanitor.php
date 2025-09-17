<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class TransientsJanitor
{
    private const HOOK = 'sifrbolt_transients_janitor_event';
    private const NONCE = 'sifrbolt_transients_janitor';

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'maybe_add_weekly_schedule']);
        add_action(self::HOOK, [$this, 'run']);
        add_action('admin_post_sifrbolt_run_janitor', [$this, 'handle_manual_run']);
    }

    public function ensure_schedule(): void
    {
        if (! wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'weekly', self::HOOK);
        }
    }

    public function clear_schedule(): void
    {
        $timestamp = wp_next_scheduled(self::HOOK);
        while ($timestamp) {
            wp_unschedule_event($timestamp, self::HOOK);
            $timestamp = wp_next_scheduled(self::HOOK);
        }
    }

    public function maybe_add_weekly_schedule(array $schedules): array
    {
        if (! isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => 7 * DAY_IN_SECONDS,
                'display' => __('Once Weekly', 'sifrbolt'),
            ];
        }

        return $schedules;
    }

    public function run(): void
    {
        if (function_exists('delete_expired_transients')) {
            delete_expired_transients();
        }

        if (function_exists('delete_expired_site_transients')) {
            delete_expired_site_transients();
        }
    }

    public function handle_manual_run(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(__('You do not have permission to run the janitor.', 'sifrbolt'));
        }

        check_admin_referer(self::NONCE);
        $this->run();
        wp_safe_redirect(add_query_arg(['page' => 'sifrbolt-black-box', 'janitor' => 'done'], admin_url('admin.php')));
        exit;
    }

    public function get_nonce_action(): string
    {
        return self::NONCE;
    }
}
