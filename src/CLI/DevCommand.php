<?php declare(strict_types=1);
namespace Gratis\DevTools\CLI;

use Gratis\DevTools\StagingManager;
use Gratis\DevTools\MigrationManager;

final class DevCommand
{
    /**
     * Create a staging environment.
     * [<name>] : Staging name (default: staging).
     * @subcommand staging-create
     * @when after_wp_load
     */
    public function staging_create($args, $assoc_args): void
    {
        $name = $args[0] ?? 'staging';
        \WP_CLI::log("Creating staging environment: {$name}...");
        $result = StagingManager::create($name);
        \WP_CLI::success("Staging '{$name}' created: {$result['tables']} tables copied (prefix: {$result['prefix']})");
    }

    /**
     * Delete a staging environment.
     * <name> : Staging name.
     * @subcommand staging-delete
     * @when after_wp_load
     */
    public function staging_delete($args, $assoc_args): void
    {
        if (StagingManager::delete($args[0])) {
            \WP_CLI::success("Staging '{$args[0]}' deleted.");
        } else {
            \WP_CLI::error("Staging '{$args[0]}' not found.");
        }
    }

    /**
     * List staging environments.
     * @subcommand staging-list
     * @when after_wp_load
     */
    public function staging_list($args, $assoc_args): void
    {
        $stagings = StagingManager::list();
        if (empty($stagings)) { \WP_CLI::log("No staging environments."); return; }
        $rows = [];
        foreach ($stagings as $name => $info) {
            $rows[] = ['Name' => $name, 'Prefix' => $info['prefix'], 'Tables' => $info['tables'], 'Created' => $info['created']];
        }
        \WP_CLI\Utils\format_items('table', $rows, ['Name', 'Prefix', 'Tables', 'Created']);
    }

    /**
     * Create a new database migration.
     * <name> : Migration name.
     * @subcommand migrate-create
     * @when after_wp_load
     */
    public function migrate_create($args, $assoc_args): void
    {
        $file = MigrationManager::create($args[0]);
        \WP_CLI::success("Migration created: {$file}");
        \WP_CLI::log("Edit: " . WP_CONTENT_DIR . "/gratis-migrations/{$file}");
    }

    /**
     * Run pending migrations.
     * @when after_wp_load
     */
    public function migrate($args, $assoc_args): void
    {
        $results = MigrationManager::migrate();
        if (empty($results)) {
            \WP_CLI::success("No pending migrations.");
        } else {
            foreach ($results as $r) \WP_CLI::log("  ✓ {$r}");
            \WP_CLI::success(count($results) . " migration(s) ran.");
        }
    }

    /**
     * Rollback last migration.
     * @when after_wp_load
     */
    public function rollback($args, $assoc_args): void
    {
        $rolled = MigrationManager::rollback();
        if ($rolled) {
            \WP_CLI::success("Rolled back: {$rolled}");
        } else {
            \WP_CLI::warning("Nothing to rollback.");
        }
    }

    /**
     * Show migration status.
     * @subcommand migrate-status
     * @when after_wp_load
     */
    public function migrate_status($args, $assoc_args): void
    {
        $status = MigrationManager::status();
        \WP_CLI::log("Migrations: {$status['total']} total, " . count($status['ran']) . " ran, " . count($status['pending']) . " pending");
        if (!empty($status['pending'])) {
            \WP_CLI::log("\nPending:");
            foreach ($status['pending'] as $p) \WP_CLI::log("  • {$p}");
        }
    }
}
