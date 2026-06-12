<?php
/**
 * Plugin Name: Gratis DevTools
 * Plugin URI: https://github.com/juslintek/gratis-devtools
 * Description: WP-CLI helpers, an admin-bar debug bar, an environment-info page, staging environments, and database migrations. What WP Engine ($20/mo) and Kinsta ($35/mo) charge for.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-devtools
 */

defined('ABSPATH') || exit;

define('GRATIS_DEVTOOLS_VERSION', '1.0.0');
define('GRATIS_DEVTOOLS_FILE', __FILE__);
define('GRATIS_DEVTOOLS_DIR', plugin_dir_path(__FILE__));
define('GRATIS_DEVTOOLS_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('plugins_loaded', function () {
    \Gratis\DevTools\DebugBar::init();
    \Gratis\DevTools\Settings::registerActions();

    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-dev', \Gratis\DevTools\CLI\DevCommand::class);
    }
});

add_action('admin_menu', [\Gratis\DevTools\Settings::class, 'registerMenu']);
add_action('admin_init', [\Gratis\DevTools\Settings::class, 'registerSettings']);
