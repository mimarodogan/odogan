<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Models\AnalyticsEvent;
use App\Services\RateLimiter;

/**
 * Analytics Event ingest (Tier 8).
 *
 * POST /analytics/event — { type, post_id, value_int, value_str }
 * Throttle: aynı session + aynı type + aynı value max 1/dk (model'in unique constraint'i yok ama
 * controller'da basit dedup).
 */
final class AnalyticsController
{
    public function event(Request $req): Response
    {
        if (!function_exists('feature') || !feature('analytics_events_enabled')) {
            return Response::json(['ok' => false, 'error' => 'disabled'], 404);
        }

        // Y3 — Per-session throttle: 60 event / 60 s.
        // Tek bir kötücül istemci event ingest'i taşmasın diye fail-open
        // bir RateLimiter (storage erişilemezse trafiği engellemez).
        if (session_status() === \PHP_SESSION_NONE) {
            session_start();
        }
        $sid = (string) session_id();
        if ($sid !== '') {
            $rl = RateLimiter::hit('analytics:sess:' . $sid, 60, 60);
            if (!$rl['ok']) {
                return Response::json(['ok' => false, 'error' => 'rate_limited'], 429);
            }
        }

        $type = (string) $req->input('type', '');
        $allowed = ['read_depth', 'time_on_page', 'outbound_click'];
        if (!in_array($type, $allowed, true)) {
            return Response::json(['ok' => false, 'error' => 'invalid_type'], 400);
        }
        $postId = (int) $req->input('post_id', 0);
        $valueInt = (int) $req->input('value_int', 0);
        $valueStr = mb_substr(trim((string) $req->input('value_str', '')), 0, 500) ?: null;

        // Sanity bounds
        if ($type === 'read_depth') {
            $valueInt = max(0, min(100, $valueInt));
        } elseif ($type === 'time_on_page') {
            $valueInt = max(0, min(86400, $valueInt));
        }

        AnalyticsEvent::record($type, $postId > 0 ? $postId : null, $valueInt, $valueStr);
        return Response::json(['ok' => true]);
    }
}
