<?php

declare(strict_types=1);

namespace SifrBolt\Lite\Features;

final class RedisAdvisor
{
    public function detect(): array
    {
        $extension = extension_loaded('redis') || class_exists('\\Redis');
        $dropin = defined('WP_REDIS_PLUGIN_VERSION') || file_exists(WP_CONTENT_DIR . '/object-cache.php');

        return [
            'extension' => $extension,
            'dropin' => $dropin,
        ];
    }

    public function get_recommendation_url(): string
    {
        return 'https://wordpress.org/plugins/redis-cache/';
    }
}
