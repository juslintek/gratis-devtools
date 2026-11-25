<?php declare(strict_types=1);
namespace Gratis\DevTools;

defined('ABSPATH') || exit;
/**
 * Environment / system information collector.
 *
 * Gathers WordPress, PHP, server, and database facts useful for debugging
 * and support — the same data WP's Site Health "Info" tab surfaces, exposed
 * here for the DevTools page and the `wp gratis-dev env` command.
 */
final class EnvInfo {

    /**
     * Collect grouped environment information.
     *
     * @return array<string,array<string,scalar>>
     */
    public static function collect(): array {
        global $wpdb, $wp_version;

        return [
            'WordPress' => [
                'Version'        => (string) $wp_version,
                'Site URL'       => site_url(),
                'Home URL'       => home_url(),
                'Multisite'      => is_multisite(),
                'Language'       => get_locale(),
                'Active theme'   => self::activeTheme(),
                'Active plugins' => count((array) get_option('active_plugins', [])),
                'Debug mode'     => defined('WP_DEBUG') && WP_DEBUG,
                'Debug log'      => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
                'Environment'    => function_exists('wp_get_environment_type') ? wp_get_environment_type() : 'unknown',
            ],
            'PHP' => [
                'Version'           => PHP_VERSION,
                'SAPI'              => PHP_SAPI,
                'Memory limit'      => (string) ini_get('memory_limit'),
                'Max execution'     => (string) ini_get('max_execution_time') . 's',
                'Upload max'        => (string) ini_get('upload_max_filesize'),
                'Post max'          => (string) ini_get('post_max_size'),
                'OPcache'           => function_exists('opcache_get_status'),
                'cURL'              => function_exists('curl_version'),
                'Imagick'           => extension_loaded('imagick'),
                'GD'                => extension_loaded('gd'),
            ],
            'Server' => [
                'Software'  => isset($_SERVER['SERVER_SOFTWARE']) ? sanitize_text_field(wp_unslash($_SERVER['SERVER_SOFTWARE'])) : 'unknown',
                'OS'        => PHP_OS,
                'Time zone' => wp_timezone_string(),
                'HTTPS'     => is_ssl(),
            ],
            'Database' => [
                'Server version' => (string) $wpdb->db_version(),
                'Table prefix'   => $wpdb->prefix,
                'Charset'        => (string) $wpdb->charset,
                'Collation'      => (string) $wpdb->collate,
            ],
        ];
    }

    private static function activeTheme(): string {
        $theme = wp_get_theme();
        return $theme->get('Name') . ' ' . $theme->get('Version');
    }

    /** Render the environment info admin page. */
    public static function renderPage(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('DevTools — Environment', 'gratis-devtools') . '</h1>';
        echo '<p>' . esc_html__('Read-only system information for debugging and support.', 'gratis-devtools') . '</p>';

        foreach (self::collect() as $group => $rows) {
            echo '<h2>' . esc_html($group) . '</h2>';
            echo '<table class="widefat striped" style="max-width:720px"><tbody>';
            foreach ($rows as $label => $value) {
                if (is_bool($value)) {
                    $value = $value ? __('Yes', 'gratis-devtools') : __('No', 'gratis-devtools');
                }
                echo '<tr><th scope="row" style="width:220px">' . esc_html((string) $label) . '</th><td><code>' . esc_html((string) $value) . '</code></td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
    }
}
