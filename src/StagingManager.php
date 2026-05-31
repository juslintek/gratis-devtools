<?php declare(strict_types=1);
namespace Gratis\DevTools;

/**
 * Staging environment manager.
 * Creates a copy of the database with a different prefix for safe testing.
 * What WP Engine charges $20/mo for. Free in Gratis.
 */
final class StagingManager
{
    /** Create a staging copy of the database. */
    public static function create(string $name = 'staging'): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $stagingPrefix = "stg_{$name}_";

        // Get all tables with current prefix
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}%'");
        $copied = 0;

        foreach ($tables as $table) {
            $newTable = str_replace($prefix, $stagingPrefix, $table);
            $wpdb->query("DROP TABLE IF EXISTS `{$newTable}`");
            $wpdb->query("CREATE TABLE `{$newTable}` LIKE `{$table}`");
            $wpdb->query("INSERT INTO `{$newTable}` SELECT * FROM `{$table}`");
            $copied++;
        }

        // Update siteurl and home in staging options
        $wpdb->update($stagingPrefix . 'options', ['option_value' => home_url('/staging/')], ['option_name' => 'siteurl']);
        $wpdb->update($stagingPrefix . 'options', ['option_value' => home_url('/staging/')], ['option_name' => 'home']);

        // Store staging info
        $stagings = get_option('gratis_devtools_stagings', []);
        $stagings[$name] = ['prefix' => $stagingPrefix, 'tables' => $copied, 'created' => gmdate('c')];
        update_option('gratis_devtools_stagings', $stagings);

        return ['name' => $name, 'prefix' => $stagingPrefix, 'tables' => $copied];
    }

    /** Delete a staging environment. */
    public static function delete(string $name): bool
    {
        global $wpdb;
        $stagings = get_option('gratis_devtools_stagings', []);
        if (!isset($stagings[$name])) return false;

        $prefix = $stagings[$name]['prefix'];
        $tables = $wpdb->get_col("SHOW TABLES LIKE '{$prefix}%'");
        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        unset($stagings[$name]);
        update_option('gratis_devtools_stagings', $stagings);
        return true;
    }

    /** List staging environments. */
    public static function list(): array
    {
        return get_option('gratis_devtools_stagings', []);
    }
}
