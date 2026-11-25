<?php declare(strict_types=1);
namespace Gratis\DevTools;

defined('ABSPATH') || exit;
/**
 * Lightweight debug bar.
 *
 * Adds a "Gratis Debug" admin-bar node (visible to admins when enabled)
 * summarising the page-generation cost: SQL query count + time, peak memory,
 * PHP/WP versions, and the number of hooked actions. A cheap, dependency-free
 * alternative to the heavyweight Query Monitor plugin for quick checks.
 */
final class DebugBar {

    public static function init(): void {
        if (!get_option('gratis_devtools_debugbar', false)) {
            return;
        }
        // Ensure query timing is available.
        if (!defined('SAVEQUERIES')) {
            define('SAVEQUERIES', true);
        }
        add_action('admin_bar_menu', [__CLASS__, 'addNode'], 999);
    }

    public static function addNode(\WP_Admin_Bar $bar): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $metrics = self::metrics();

        $bar->add_node([
            'id'    => 'gratis-debug',
            'title' => sprintf('⚙ %d queries · %s ms · %s', $metrics['queries'], $metrics['time_ms'], $metrics['memory']),
        ]);

        foreach ($metrics['detail'] as $label => $value) {
            $bar->add_node([
                'parent' => 'gratis-debug',
                'id'     => 'gratis-debug-' . sanitize_key($label),
                'title'  => esc_html($label . ': ' . $value),
            ]);
        }
    }

    /**
     * @return array{queries:int,time_ms:string,memory:string,detail:array<string,string>}
     */
    private static function metrics(): array {
        global $wpdb, $wp_version;

        $queries = is_array($wpdb->queries) ? count($wpdb->queries) : 0;
        $sqlTime = 0.0;
        if (is_array($wpdb->queries)) {
            foreach ($wpdb->queries as $q) {
                $sqlTime += (float) ($q[1] ?? 0);
            }
        }

        return [
            'queries' => $queries,
            'time_ms' => number_format($sqlTime * 1000, 1),
            'memory'  => size_format(memory_get_peak_usage(true)),
            'detail'  => [
                'SQL queries'   => (string) $queries,
                'SQL time'      => number_format($sqlTime * 1000, 1) . ' ms',
                'Peak memory'   => size_format(memory_get_peak_usage(true)),
                'PHP'           => PHP_VERSION,
                'WordPress'     => (string) $wp_version,
                'Hooked filters' => (string) count((array) ($GLOBALS['wp_filter'] ?? [])),
            ],
        ];
    }
}
