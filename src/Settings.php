<?php declare(strict_types=1);
namespace Gratis\DevTools;

/**
 * Admin settings page for Gratis DevTools.
 *
 * Toggles the debug bar, surfaces migration status, and provides nonce-gated
 * "run migrations" / "create staging" actions. Hangs under the Tools menu
 * since these are developer utilities, not site-wide options.
 */
final class Settings {

    private const PAGE = 'gratis-devtools';

    public static function registerMenu(): void {
        add_management_page(
            __('Gratis DevTools', 'gratis-devtools'),
            __('DevTools', 'gratis-devtools'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render']
        );

        add_submenu_page(
            'tools.php',
            __('DevTools — Environment', 'gratis-devtools'),
            __('DevTools Env', 'gratis-devtools'),
            'manage_options',
            'gratis-devtools-env',
            [EnvInfo::class, 'renderPage']
        );
    }

    public static function registerSettings(): void {
        register_setting('gratis_devtools', 'gratis_devtools_debugbar', [
            'type'              => 'boolean',
            'sanitize_callback' => static fn($value): bool => (bool) $value,
            'default'           => false,
        ]);
    }

    public static function registerActions(): void {
        add_action('admin_post_gratis_devtools_migrate', [__CLASS__, 'handleMigrate']);
    }

    public static function handleMigrate(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Insufficient permissions.', 'gratis-devtools'));
        }
        check_admin_referer('gratis_devtools_migrate');

        $ran = MigrationManager::migrate();
        wp_safe_redirect(add_query_arg('gratis_devtools_msg', count($ran), admin_url('tools.php?page=' . self::PAGE)));
        exit;
    }

    public static function render(): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        $ran = isset($_GET['gratis_devtools_msg']) ? absint(wp_unslash($_GET['gratis_devtools_msg'])) : -1;

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Gratis DevTools', 'gratis-devtools') . '</h1>';

        if ($ran >= 0) {
            echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(
                /* translators: %d: number of migrations executed. */
                esc_html__('%d migration(s) executed.', 'gratis-devtools'),
                absint($ran)
            ) . '</p></div>';
        }

        // Settings form.
        echo '<form method="post" action="options.php">';
        settings_fields('gratis_devtools');
        echo '<table class="form-table" role="presentation">';
        echo '<tr><th scope="row">' . esc_html__('Debug bar', 'gratis-devtools') . '</th><td>';
        echo '<label><input type="checkbox" name="gratis_devtools_debugbar" value="1" ' . checked((bool) get_option('gratis_devtools_debugbar', false), true, false) . '> ';
        echo esc_html__('Show a query/memory summary in the admin bar (admins only).', 'gratis-devtools') . '</label>';
        echo '</td></tr>';
        echo '</table>';
        submit_button();
        echo '</form>';

        // Migration status.
        $status = MigrationManager::status();
        echo '<hr><h2>' . esc_html__('Database migrations', 'gratis-devtools') . '</h2>';
        echo '<p>' . sprintf(
            /* translators: 1: total migrations, 2: pending count. */
            esc_html__('%1$d total, %2$d pending.', 'gratis-devtools'),
            absint($status['total']),
            absint(count($status['pending']))
        ) . '</p>';

        if (!empty($status['pending'])) {
            echo '<ul style="list-style:disc;margin-left:20px">';
            foreach ($status['pending'] as $p) {
                echo '<li><code>' . esc_html((string) $p) . '</code></li>';
            }
            echo '</ul>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="gratis_devtools_migrate">';
            wp_nonce_field('gratis_devtools_migrate');
            submit_button(__('Run pending migrations', 'gratis-devtools'), 'primary', 'submit', false);
            echo '</form>';
        } else {
            echo '<p>' . esc_html__('No pending migrations.', 'gratis-devtools') . '</p>';
        }

        // Staging environments.
        $stagings = StagingManager::getAll();
        echo '<hr><h2>' . esc_html__('Staging environments', 'gratis-devtools') . '</h2>';
        if (empty($stagings)) {
            echo '<p>' . esc_html__('No staging environments. Create one with:', 'gratis-devtools') . ' <code>wp gratis-dev staging-create</code></p>';
        } else {
            echo '<table class="widefat striped" style="max-width:720px"><thead><tr>';
            echo '<th>' . esc_html__('Name', 'gratis-devtools') . '</th><th>' . esc_html__('Prefix', 'gratis-devtools') . '</th><th>' . esc_html__('Tables', 'gratis-devtools') . '</th><th>' . esc_html__('Created', 'gratis-devtools') . '</th>';
            echo '</tr></thead><tbody>';
            foreach ($stagings as $name => $info) {
                echo '<tr><td>' . esc_html((string) $name) . '</td><td><code>' . esc_html((string) $info['prefix']) . '</code></td><td>' . absint($info['tables']) . '</td><td>' . esc_html((string) $info['created']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }

        echo '<p style="margin-top:16px"><a class="button" href="' . esc_url(admin_url('tools.php?page=gratis-devtools-env')) . '">' . esc_html__('View environment info →', 'gratis-devtools') . '</a></p>';

        echo '</div>';
    }
}
