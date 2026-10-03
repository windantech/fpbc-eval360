<?php
/**
 * lib/notifications.php — topbar notification bell.
 *
 * lib/layout.php calls both functions below on every page render, so they must
 * always exist and must never throw. This is the FPC skeleton: the contract is
 * in place and the bell renders empty until checks are added.
 *
 * ADDING A CHECK — the rule that matters:
 * Wrap every single one in its own try/catch. A notification source is a query
 * against a table that may not exist yet, and an uncaught error here takes down
 * EVERY page in the platform, not just the bell. Returning nothing is always
 * better than throwing.
 *
 * Gate each check by permission too, using the same RBAC key as the module the
 * item belongs to — otherwise the bell tells people about work they cannot open.
 */

if (!function_exists('get_notifications')) {

    /**
     * Live notification items, most urgent first.
     *
     * @return array<int, array{type:string, icon:string, title:string, body:string, url:string, count:int}>
     *         type: 'urgent' | 'warning' | 'info'
     *         icon: Bootstrap Icon name, e.g. 'bi-exclamation-triangle-fill'
     */
    function get_notifications(): array
    {
        $items = [];

        // ── FPC checks go here, one try/catch each. Pattern: ────────────────
        //
        // try {
        //     if (rbac_can('reviews') !== 'none') {
        //         $n = (int) db_value(
        //             "SELECT COUNT(*) FROM fpc_reviews
        //              WHERE org_id = ? AND status = 'overdue'", [1]
        //         );
        //         if ($n > 0) {
        //             $items[] = [
        //                 'type'  => 'urgent',
        //                 'icon'  => 'bi-exclamation-triangle-fill',
        //                 'title' => $n . ' review' . ($n !== 1 ? 's' : '') . ' overdue',
        //                 'body'  => 'Past their scheduled date.',
        //                 'url'   => app_url('/modules/core/pages/reviews.php'),
        //                 'count' => $n,
        //             ];
        //         }
        //     }
        // } catch (\Throwable $e) { /* never break page render */ }

        // Urgent first, then warnings, then info.
        $rank = ['urgent' => 0, 'warning' => 1, 'info' => 2];
        usort($items, static fn($a, $b) =>
            ($rank[$a['type']] ?? 3) <=> ($rank[$b['type']] ?? 3));

        return $items;
    }

    /** Count for the red/amber topbar badge. Info items are not counted. */
    function notifications_badge_count(array $notifications): int
    {
        $n = 0;
        foreach ($notifications as $item) {
            if (($item['type'] ?? '') === 'urgent' || ($item['type'] ?? '') === 'warning') {
                $n++;
            }
        }
        return $n;
    }
}
