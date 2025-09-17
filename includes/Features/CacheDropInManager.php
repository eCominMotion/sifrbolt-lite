<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class CacheDropInManager
{
    private const DROPIN_PATH = 'advanced-cache.php';
    private const SIGNATURE = 'SIFRBOLT_SPARK_LITE_DROPIN v1';

    public function __construct(private readonly CalmSwitch $calm_switch)
    {
    }

    public function install(): void
    {
        $this->calm_switch->ensure_config_file();
        $this->maybe_refresh();
    }

    public function maybe_refresh(): void
    {
        $template = $this->get_composed_dropin();
        $target = $this->get_dropin_path();

        if (! file_exists($target) || ! str_contains((string) file_get_contents($target), self::SIGNATURE)) {
            $this->write_dropin($template, $target);
            return;
        }

        if (hash('sha256', (string) file_get_contents($target)) !== hash('sha256', $template)) {
            $this->write_dropin($template, $target);
        }
    }

    public function maybe_remove_on_deactivate(): void
    {
        $target = $this->get_dropin_path();
        if (file_exists($target) && str_contains((string) file_get_contents($target), self::SIGNATURE)) {
            unlink($target);
        }
    }

    private function get_dropin_path(): string
    {
        return WP_CONTENT_DIR . '/' . self::DROPIN_PATH;
    }

    private function get_template_path(): string
    {
        return SIFRBOLT_LITE_PATH . '/dropins/advanced-cache.php';
    }

    private function get_composed_dropin(): string
    {
        $template = (string) file_get_contents($this->get_template_path());
        $config_path = $this->calm_switch->get_config_path();
        return str_replace('%%CONFIG_PATH%%', addslashes($config_path), $template);
    }

    private function write_dropin(string $contents, string $target): void
    {
        if (! is_dir(WP_CONTENT_DIR)) {
            return;
        }

        $bytes = file_put_contents($target, $contents, LOCK_EX);
        if ($bytes === false) {
            error_log('[sifrbolt-lite] Failed to write advanced-cache drop-in.');
            return;
        }

        @chmod($target, 0644);
    }
}
