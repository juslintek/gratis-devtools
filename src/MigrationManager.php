<?php declare(strict_types=1);
namespace Gratis\DevTools;

/**
 * Database migration system.
 * Track schema changes, run migrations up/down, version control for DB.
 */
final class MigrationManager
{
    private const OPTION = 'gratis_devtools_migrations';

    /** Get migration directory. */
    private static function dir(): string
    {
        $dir = WP_CONTENT_DIR . '/gratis-migrations';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        return $dir;
    }

    /** Create a new migration file. */
    public static function create(string $name): string
    {
        $timestamp = date('YmdHis');
        $slug = sanitize_file_name($name);
        $filename = "{$timestamp}_{$slug}.php";
        $path = self::dir() . "/{$filename}";

        $template = "<?php\n// Migration: {$name}\n// Created: " . gmdate('c') . "\n\n"
            . "return [\n"
            . "    'up' => function () {\n"
            . "        global \$wpdb;\n"
            . "        // \$wpdb->query(\"ALTER TABLE ...\");\n"
            . "    },\n"
            . "    'down' => function () {\n"
            . "        global \$wpdb;\n"
            . "        // \$wpdb->query(\"ALTER TABLE ...\");\n"
            . "    },\n"
            . "];\n";

        file_put_contents($path, $template);
        return $filename;
    }

    /** Run pending migrations. */
    public static function migrate(): array
    {
        $ran = get_option(self::OPTION, []);
        $files = glob(self::dir() . '/*.php');
        sort($files);

        $results = [];
        foreach ($files as $file) {
            $name = basename($file);
            if (in_array($name, $ran)) continue;

            $migration = require $file;
            if (isset($migration['up']) && is_callable($migration['up'])) {
                ($migration['up'])();
                $ran[] = $name;
                $results[] = $name;
            }
        }

        update_option(self::OPTION, $ran);
        return $results;
    }

    /** Rollback last migration. */
    public static function rollback(): ?string
    {
        $ran = get_option(self::OPTION, []);
        if (empty($ran)) return null;

        $last = array_pop($ran);
        $file = self::dir() . "/{$last}";
        if (file_exists($file)) {
            $migration = require $file;
            if (isset($migration['down']) && is_callable($migration['down'])) {
                ($migration['down'])();
            }
        }

        update_option(self::OPTION, $ran);
        return $last;
    }

    /** Get migration status. */
    public static function status(): array
    {
        $ran = get_option(self::OPTION, []);
        $files = glob(self::dir() . '/*.php');
        $all = array_map('basename', $files ?: []);
        $pending = array_diff($all, $ran);
        return ['ran' => $ran, 'pending' => array_values($pending), 'total' => count($all)];
    }
}
