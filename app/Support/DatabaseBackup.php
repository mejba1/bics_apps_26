<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Database backups in pure PHP (works on shared hosting where mysqldump / exec are not available).
 * Files: storage/app/backups/*.sql.gz (outside the public web folder).
 * Each backup is a complete MySQL script: DROP + CREATE TABLE and INSERTs, foreign key checks off.
 */
class DatabaseBackup
{
    public const DIR = 'app/backups';
    private const ROWS_PER_INSERT = 100;

    public static function dir(): string
    {
        $dir = storage_path(static::DIR);
        if (!is_dir($dir)) {
            File::ensureDirectoryExists($dir);
            // Belt and braces: never served even if storage/ is ever exposed
            File::put($dir . '/.htaccess', "Require all denied\nDeny from all\n");
        }
        return $dir;
    }

    /** Base tables of the database. */
    public static function tables(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $rows = $db->select('SHOW FULL TABLES WHERE Table_type = ?', ['BASE TABLE']);
        return array_values(array_map(fn ($r) => array_values((array) $r)[0], $rows));
    }

    /** Tables with row counts and sizes. */
    public static function tableStats(?string $connection = null): array
    {
        $db = DB::connection($connection);
        $rows = $db->select('SELECT TABLE_NAME AS name, TABLE_ROWS AS approx_rows, DATA_LENGTH + INDEX_LENGTH AS bytes
            FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_TYPE = ? ORDER BY TABLE_NAME', [$db->getDatabaseName(), 'BASE TABLE']);
        return array_map(fn ($r) => ['name' => $r->name, 'rows' => (int) $db->table($r->name)->count(), 'bytes' => (int) $r->bytes], $rows);
    }

    /**
     * Write a backup file. Returns its file name.
     * @param array|null $tables null = every table
     */
    public static function create(?array $tables = null, string $label = 'manual', bool $gzip = true, ?string $connection = null): string
    {
        $db = DB::connection($connection);
        $pdo = $db->getPdo();
        $all = static::tables($connection);
        $tables = $tables === null ? $all : array_values(array_intersect($all, $tables));

        $name = 'bics-' . Str::slug($db->getDatabaseName()) . '-' . now()->format('Y-m-d_His') . '-' . Str::slug($label) . '.sql' . ($gzip ? '.gz' : '');
        $path = static::dir() . '/' . $name;
        $fh = $gzip ? gzopen($path, 'wb6') : fopen($path, 'wb');
        $write = fn (string $s) => $gzip ? gzwrite($fh, $s) : fwrite($fh, $s);

        try {
            $write("-- BICS database backup\n-- Database: " . $db->getDatabaseName() . "\n-- Created: " . now()->toDateTimeString() . ' by ' . (optional(auth()->user())->email ?: 'system') . "\n-- Tables: " . count($tables) . "\n\n");
            $write("SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\nSET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\nSET time_zone = '+00:00';\n\n");
            foreach ($tables as $table) {
                $q = '`' . str_replace('`', '``', $table) . '`';
                $create = (array) $db->selectOne('SHOW CREATE TABLE ' . $q);
                $write("-- Table $q\nDROP TABLE IF EXISTS $q;\n" . array_values($create)[1] . ";\n\n");

                $columns = null;
                $buffer = [];
                // Unbuffered-friendly: walk the table in primary-key/offset chunks
                $offset = 0;
                while (true) {
                    $rows = $db->select("SELECT * FROM $q LIMIT 500 OFFSET $offset");
                    if (!$rows) break;
                    foreach ($rows as $row) {
                        $row = (array) $row;
                        $columns ??= '(' . implode(', ', array_map(fn ($c) => '`' . str_replace('`', '``', $c) . '`', array_keys($row))) . ')';
                        $buffer[] = '(' . implode(', ', array_map(fn ($v) => $v === null ? 'NULL' : (is_int($v) || is_float($v) ? (string) $v : $pdo->quote((string) $v)), $row)) . ')';
                        if (count($buffer) >= static::ROWS_PER_INSERT) {
                            $write("INSERT INTO $q $columns VALUES\n" . implode(",\n", $buffer) . ";\n");
                            $buffer = [];
                        }
                    }
                    $offset += 500;
                }
                if ($buffer) $write("INSERT INTO $q $columns VALUES\n" . implode(",\n", $buffer) . ";\n");
                $write("\n");
            }
            $write("SET FOREIGN_KEY_CHECKS = 1;\n-- End of backup\n");
        } catch (\Throwable $e) {
            $gzip ? gzclose($fh) : fclose($fh);
            File::delete($path);
            throw $e;
        }
        $gzip ? gzclose($fh) : fclose($fh);
        return $name;
    }

    /** Backups, newest first. */
    public static function list(): array
    {
        $files = glob(static::dir() . '/*.{sql,gz}', GLOB_BRACE) ?: [];
        $out = array_map(fn ($f) => [
            'name' => basename($f),
            'bytes' => filesize($f),
            'time' => filemtime($f),
            'label' => static::labelOf(basename($f)),
        ], $files);
        usort($out, fn ($a, $b) => $b['time'] <=> $a['time']);
        return $out;
    }

    public static function labelOf(string $name): string
    {
        if (str_starts_with($name, 'bics-upload-')) return 'uploaded';
        return preg_match('/-\d{4}-\d{2}-\d{2}_\d{6}-([a-z0-9-]+)\.sql/', $name, $m) ? str_replace('-', ' ', $m[1]) : 'uploaded';
    }

    /** Full path of a backup by file name (null if not a valid backup name). */
    public static function path(string $name): ?string
    {
        if (!preg_match('/^[A-Za-z0-9._-]+\.sql(\.gz)?$/', $name)) return null;
        $path = static::dir() . '/' . $name;
        return is_file($path) ? $path : null;
    }

    /**
     * Run an SQL script (plain or .gz) statement by statement.
     * USE / CREATE DATABASE / DROP DATABASE statements are skipped, so a dump can never switch or drop the database.
     * Returns ['statements' => n, 'skipped' => n].
     */
    public static function import(string $path, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $count = 0; $skipped = 0;
        $db->unprepared('SET FOREIGN_KEY_CHECKS = 0');
        try {
            foreach (static::statements($path) as $sql) {
                if (preg_match('/^\s*(USE\s|CREATE\s+(DATABASE|SCHEMA)\b|DROP\s+(DATABASE|SCHEMA)\b)/i', $sql)) { $skipped++; continue; }
                try {
                    $db->unprepared($sql);
                } catch (\Throwable $e) {
                    throw new \RuntimeException('Statement ' . ($count + 1) . ' failed: ' . Str::limit($e->getMessage(), 300) . ' — near: ' . Str::limit(preg_replace('/\s+/', ' ', $sql), 120), 0, $e);
                }
                $count++;
            }
        } finally {
            $db->unprepared('SET FOREIGN_KEY_CHECKS = 1');
        }
        return ['statements' => $count, 'skipped' => $skipped];
    }

    /** Is the file an SQL script we can read? (first bytes are text, gz header for .gz) */
    public static function looksLikeSql(string $path): bool
    {
        $fh = @gzopen($path, 'rb');
        if (!$fh) return false;
        $head = gzread($fh, 4096);
        gzclose($fh);
        if ($head === false || $head === '') return false;
        if (preg_match('/[\x00-\x08\x0E-\x1F]/', $head)) return false; // binary
        return (bool) preg_match('/\b(CREATE|INSERT|DROP|SET|ALTER|UPDATE|DELETE|LOCK)\b/i', $head);
    }

    /**
     * Split an SQL script into statements (streaming; quotes, backticks, escapes and comments handled).
     * @return \Generator<string>
     */
    public static function statements(string $path): \Generator
    {
        $fh = gzopen($path, 'rb'); // reads plain files too
        $buf = '';
        $quote = null;      // ' " or `
        $comment = null;    // 'line' or 'block'
        $prev = '';
        while (!gzeof($fh)) {
            $chunk = gzread($fh, 65536);
            $len = strlen($chunk);
            for ($i = 0; $i < $len; $i++) {
                $c = $chunk[$i];
                if ($comment === 'line') {
                    if ($c === "\n") { $comment = null; $buf .= "\n"; }
                    $prev = $c; continue;
                }
                if ($comment === 'block') {
                    if ($prev === '*' && $c === '/') { $comment = null; $prev = ''; continue; }
                    $prev = $c; continue;
                }
                if ($quote !== null) {
                    $buf .= $c;
                    if ($c === '\\' && $quote !== '`') {
                        if ($i + 1 < $len) { $buf .= $chunk[++$i]; }
                        else { $next = gzread($fh, 1); $buf .= $next; }
                        $prev = ''; continue;
                    }
                    if ($c === $quote) $quote = null;
                    $prev = $c; continue;
                }
                // Outside quotes and comments
                if ($c === "'" || $c === '"' || $c === '`') { $quote = $c; $buf .= $c; $prev = $c; continue; }
                if ($c === '#' ) { $comment = 'line'; $prev = $c; continue; }
                if ($c === '-' && $prev === '-' && substr($buf, -1) === '-') {
                    // "-- " comment: drop the first dash already buffered
                    $buf = substr($buf, 0, -1); $comment = 'line'; $prev = ''; continue;
                }
                if ($c === '*' && $prev === '/' && substr($buf, -1) === '/') {
                    // /* ... */ (also MySQL /*!40101 ... */ hints, which are only compatibility settings)
                    $buf = substr($buf, 0, -1); $comment = 'block'; $prev = ''; continue;
                }
                if ($c === ';') {
                    $sql = trim($buf);
                    if ($sql !== '') yield $sql;
                    $buf = ''; $prev = ''; continue;
                }
                $buf .= $c; $prev = $c;
            }
        }
        gzclose($fh);
        $sql = trim($buf);
        if ($sql !== '') yield $sql;
    }

    /** Remove old automatic backups, keeping the newest $keep of each automatic kind. */
    public static function prune(int $keep = 10): void
    {
        foreach (['before import', 'before restore', 'scheduled'] as $label) {
            $files = array_values(array_filter(static::list(), fn ($b) => $b['label'] === $label));
            foreach (array_slice($files, $keep) as $old) File::delete(static::dir() . '/' . $old['name']);
        }
    }
}
