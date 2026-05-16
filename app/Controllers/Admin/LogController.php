<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Logger;

final class LogController
{
    public function index(Request $req): Response
    {
        $level = (string) $req->input('level', '');
        $channel = (string) $req->input('channel', '');
        $date = (string) $req->input('date', date('Y-m-d'));
        $source = (string) $req->input('source', 'db'); // 'db' | 'file'
        $limit = max(50, min(1000, (int) $req->input('limit', 200)));

        $entries = $source === 'file'
            ? self::tail($channel ?: 'app', $date, $limit)
            : self::query($level, $channel, $date, $limit);

        $channels = self::knownChannels();

        return view('admin.logs', [
            'title' => 'Loglar',
            'entries' => $entries,
            'levels' => Logger::LEVELS,
            'channels' => $channels,
            'filters' => [
                'level' => $level,
                'channel' => $channel,
                'date' => $date,
                'source' => $source,
                'limit' => $limit,
            ],
        ]);
    }

    private static function query(string $level, string $channel, string $date, int $limit): array
    {
        $sql = 'SELECT id, level, channel, message, context_json, user_id, ip_address, request_uri, created_at
                FROM logs WHERE log_date = :d';
        $params = [':d' => self::safeDate($date)];
        if ($level !== '' && in_array($level, Logger::LEVELS, true)) {
            $sql .= ' AND level = :l';
            $params[':l'] = $level;
        }
        if ($channel !== '') {
            $sql .= ' AND channel = :c';
            $params[':c'] = mb_substr($channel, 0, 60);
        }
        $sql .= ' ORDER BY id DESC LIMIT ' . $limit;
        return Database::instance()->fetchAll($sql, $params);
    }

    /**
     * Stream the tail of a daily file rather than slurping it.
     */
    private static function tail(string $channel, string $date, int $limit): array
    {
        $path = Config::root() . '/storage/logs/' . preg_replace('#[^a-z0-9_-]#i', '', $channel)
            . '-' . self::safeDate($date) . '.log';
        $lines = Logger::tailFile($path, $limit);
        $out = [];
        foreach ($lines as $line) {
            // Parse "[YYYY-MM-DD HH:MM:SS] channel.LEVEL: message {context}"
            if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(.*)$/u', $line, $m)) {
                $rest = $m[4];
                $context = null;
                if (preg_match('/\s(\{.*\})\s*$/', $rest, $cm)) {
                    $context = $cm[1];
                    $rest = trim(substr($rest, 0, -strlen($cm[0])));
                }
                $out[] = [
                    'created_at' => $m[1],
                    'channel' => $m[2],
                    'level' => strtolower($m[3]),
                    'message' => $rest,
                    'context_json' => $context,
                ];
            } else {
                $out[] = ['created_at' => '', 'channel' => 'raw', 'level' => 'info', 'message' => $line, 'context_json' => null];
            }
        }
        return $out;
    }

    private static function knownChannels(): array
    {
        try {
            $rows = Database::instance()->fetchAll('SELECT DISTINCT channel FROM logs ORDER BY channel ASC LIMIT 50');
            return array_map(static fn($r) => $r['channel'], $rows);
        } catch (\Throwable) {
            return ['app'];
        }
    }

    private static function safeDate(string $d): string
    {
        return preg_match('#^\d{4}-\d{2}-\d{2}$#', $d) ? $d : date('Y-m-d');
    }
}
