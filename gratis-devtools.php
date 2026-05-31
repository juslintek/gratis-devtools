<?php
/**
 * Plugin Name: Gratis DevTools
 * Description: Staging environments, database migrations, version control. What WP Engine ($20/mo) and Kinsta ($35/mo) charge for.
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-dev', \Gratis\DevTools\CLI\DevCommand::class);
});
