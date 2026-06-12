<?php declare(strict_types=1);
namespace Gratis\DevTools;

/**
 * Staging environment manager.
 *
 * Creates a copy of the database with a different table prefix for safe
 * testing. What WP Engine charges $20/mo for. Free in Gratis.
 *
 * Table identifiers cannot be bound with $wpdb->prepare(), so every
 * identifier is whitelisted with preg_replace() to alphanumerics/underscore
 * before interpolation, and each direct query carries the documented
 * custom-table phpcs ignores.
 */
final class StagingManager {

    private const OPTION = 'gratis_devtools_stagings';

    /** Whitelist a SQL identifier (table/prefix) — strips anything non-alphanumeric. */
    private static function ident(string $name): string {
        return preg_replace('/[^A-Za-z0-9_]/', '', $name);
    }

    /** Create a staging copy of the database. */
    public static function create(string $name = 'staging'): array {
        global $wpdb;
        $prefix        = self::ident($wpdb->prefix);
        $name          = self::ident($name);
        $stagingPrefix = self::ident("stg_{$name}_");

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table identifiers cannot be bound; whitelisted above.
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix) . '%'));
        $copied = 0;

        foreach ($tables as $table) {
            $table    = self::ident($table);
            $newTable = self::ident(str_replace($prefix, $stagingPrefix, $table));
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table identifiers cannot be bound; whitelisted via self::ident().
            $wpdb->query("DROP TABLE IF EXISTS `{$newTable}`");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- whitelisted identifiers.
            $wpdb->query("CREATE TABLE `{$newTable}` LIKE `{$table}`");
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- whitelisted identifiers.
            $wpdb->query("INSERT INTO `{$newTable}` SELECT * FROM `{$table}`");
            ++$copied;
        }

        // Point the staging copy's siteurl/home at a /staging/ path.
        $stagingUrl = home_url('/staging/');
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom (staging) table.
        $wpdb->update($stagingPrefix . 'options', ['option_value' => $stagingUrl], ['option_name' => 'siteurl']);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom (staging) table.
        $wpdb->update($stagingPrefix . 'options', ['option_value' => $stagingUrl], ['option_name' => 'home']);

        $stagings        = get_option(self::OPTION, []);
        $stagings[$name] = ['prefix' => $stagingPrefix, 'tables' => $copied, 'created' => gmdate('c')];
        update_option(self::OPTION, $stagings);

        return ['name' => $name, 'prefix' => $stagingPrefix, 'tables' => $copied];
    }

    /** Delete a staging environment. */
    public static function delete(string $name): bool {
        global $wpdb;
        $stagings = get_option(self::OPTION, []);
        if (!isset($stagings[$name])) {
            return false;
        }

        $prefix = self::ident($stagings[$name]['prefix']);
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- whitelisted prefix.
        $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($prefix) . '%'));
        foreach ($tables as $table) {
            $table = self::ident($table);
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- whitelisted identifier.
            $wpdb->query("DROP TABLE IF EXISTS `{$table}`");
        }

        unset($stagings[$name]);
        update_option(self::OPTION, $stagings);
        return true;
    }

    /** List staging environments. */
    public static function getAll(): array {
        return get_option(self::OPTION, []);
    }
}
