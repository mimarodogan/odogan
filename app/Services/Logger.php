<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * Lightweight PSR-3-ish logger. Writes both to the `logs` table (so the
 * admin viewer can filter by date/level/channel via SQL) and to a daily
 * file under storage/logs/<channel>-<date>.log so we keep a tail-able
 * stream even if the DB is down.
 */
final class Logger
{
    public const DEBUG = 'debug';
    public const INFO = 'info';
    public const NOTICE = 'notice';
    public const WARNING = 'warning';
    public const ERROR = 'error';
    public const CRITICAL = 'critical';
    public const ALERT = 'alert';
    public const EMERGENCY = 'emergency';

    public const LEVELS = [
        self::DEBUG, self::INFO, self::NOTICE, self::WARNING,
        self::ERROR, self::CRITICAL, self::ALERT, self::EMERGENCY,
    ];

    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        $level = in_array($level, self::LEVELS, true) ? $level : self::INFO;
        $message = mb_substr($message, 0, 500);
        $userId = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $uri = $_SERVER['REQUEST_URI'] ?? null;

        try {
            Database::instance()->insert('logs', [
                'level' => $level,
                'channel' => mb_substr($channel, 0, 60),
                'message' => $message,
                'context_json' => $context ? (string) json_encode($context, JSON_UNESCAPED_UNICODE) : null,
                'user_id' => $userId,
                'ip_address' => $ip,
                'request_uri' => $uri ? mb_substr($uri, 0, 500) : null,
                'log_date' => date('Y-m-d'),
            ]);
        } catch (\Throwable) {
            // DB unavailable — keep going; file log still works.
        }

        $line = sprintf(
            "[%s] %s.%s: %s%s\n",
            date('Y-m-d H:i:s'),
            $channel,
            strtoupper($level),
            $message,
            $context ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : ''
        );
        $dir = Config::root() . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents(
            $dir . '/' . $channel . '-' . date('Y-m-d') . '.log',
            $line,
            FILE_APPEND | LOCK_EX
        );
    }

    public static function info(string $msg, array $ctx = [], string $ch = 'app'): void { self::log(self::INFO, $msg, $ctx, $ch); }
    public static function warning(string $msg, array $ctx = [], string $ch = 'app'): void { self::log(self::WARNING, $msg, $ctx, $ch); }
    public static function error(string $msg, array $ctx = [], string $ch = 'app'): void { self::log(self::ERROR, $msg, $ctx, $ch); }
    public static function debug(string $msg, array $ctx = [], string $ch = 'app'): void { self::log(self::DEBUG, $msg, $ctx, $ch); }

    /**
     * Stream the last N lines of a daily file from disk without loading
     * the whole file into memory. Used by the admin viewer.
     *
     * @return iterable<int,string>
     */
    public static function tailFile(string $path, int $lines = 500): iterable
    {
        if (!is_file($path)) {
            return [];
        }
        $fh = @fopen($path, 'rb');
        if ($fh === false) {
            return [];
        }
        $size = filesize($path) ?: 0;
        $chunk = 4096;
        $buffer = '';
        $found = [];
        $pos = $size;
        while ($pos > 0 && count($found) < $lines) {
            $read = (int) min($chunk, $pos);
            $pos -= $read;
            fseek($fh, $pos);
            $buffer = fread($fh, $read) . $buffer;
            $parts = explode("\n", $buffer);
            // Keep the first fragment; it may be incomplete until the next read.
            $buffer = $pos > 0 ? array_shift($parts) : '';
            foreach (array_reverse($parts) as $p) {
                if ($p !== '') {
                    $found[] = $p;
                    if (count($found) >= $lines) {
                        break;
                    }
                }
            }
        }
        fclose($fh);
        if ($pos === 0 && $buffer !== '' && count($found) < $lines) {
            $found[] = $buffer;
        }
        return array_reverse($found);
    }
}
