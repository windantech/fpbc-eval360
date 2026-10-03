<?php
/**
 * rbac.php — Server-side Role-Based Access Control.
 *
 * Mirrors the v13 demo's roleCan() logic but on the server, where it can't
 * be bypassed by editing JS in the browser. PHP entry points must call
 * rbac_require() before doing sensitive work.
 *
 * Usage:
 *   $access = rbac_can('ea-grm');        // 'full' | 'read' | 'none'
 *   rbac_require('ea-grm', 'full');      // throws / redirects if not authorised
 *   rbac_audit('view', 'ea_gbv_register', $id);
 */

if (!function_exists('rbac_current_role')) {

    /**
     * Get the current user's role record from fpc_roles (joined via fpc_user_roles).
     * Returns ['key' => ..., 'label' => ..., 'scope' => [...]] or null.
     */
    function rbac_current_role(): ?array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        $uid = current_user_id();
        if (!$uid) {
            $cached = null;
            return null;
        }

        $row = db_one("
            SELECT r.id, r.role_key, r.label, r.long_label, r.is_leader,
                   r.is_read_only_all, r.scope_json
            FROM fpc_user_roles ur
            INNER JOIN fpc_roles r ON r.id = ur.role_id
            WHERE ur.user_id = ? AND ur.is_active = 1 AND r.is_active = 1
              AND (ur.expires_at IS NULL OR ur.expires_at > NOW())
            ORDER BY r.display_order ASC
            LIMIT 1
        ", [$uid]);

        if (!$row) {
            $cached = null;
            return null;
        }

        $scope = [];
        if (!empty($row['scope_json'])) {
            $decoded = json_decode($row['scope_json'], true);
            if (is_array($decoded)) $scope = $decoded;
        }

        $cached = [
            'id'              => (int) $row['id'],
            'key'             => $row['role_key'],
            'label'           => $row['label'],
            'long_label'      => $row['long_label'],
            'is_leader'       => (bool) $row['is_leader'],
            'is_read_only_all'=> (bool) $row['is_read_only_all'],
            'scope'           => $scope,
        ];
        return $cached;
    }

    /**
     * Check what level of access the current user has to a view.
     * Returns 'full', 'read', or 'none'.
     */
    function rbac_can(string $viewKey): string
    {
        $role = rbac_current_role();
        if (!$role) return 'none';
        if ($role['is_leader']) return 'full';  // Centre Leader sees all

        if ($role['is_read_only_all']) {
            $explicit = $role['scope'][$viewKey] ?? null;
            return $explicit === 'none' ? 'none' : 'read';
        }

        // Default DENY — every module a role is not explicitly granted is blocked.
        // This prevents newly-added modules from being silently world-accessible.
        return $role['scope'][$viewKey] ?? 'none';
    }

    /**
     * Return the track code this user's role is scoped to, or null for full access.
     * Used by pages to filter lists to a single track (Track Lead roles).
     * Pages call: $myTrack = rbac_track_code(); if ($myTrack) { add WHERE track_code = ? }
     */
    function rbac_track_code(): ?string
    {
        $role = rbac_current_role();
        if (!$role) return null;
        if ($role['is_leader']) return null; // Centre Leader sees all tracks

        // Check scope_json for explicit track_code restriction
        $tc = $role['scope']['track_code'] ?? null;
        return ($tc && preg_match('/^[A-Ea-e]$/', $tc)) ? strtoupper($tc) : null;
    }

    /**
     * Require a minimum access level. If user falls short, render a
     * locked-screen response (HTML) or JSON error (AJAX).
     *
     *   rbac_require('ea-grm', 'full');
     */
    function rbac_require(string $viewKey, string $minLevel = 'full'): void
    {
        $access = rbac_can($viewKey);
        $rank   = ['none' => 0, 'read' => 1, 'full' => 2];
        if (($rank[$access] ?? 0) >= ($rank[$minLevel] ?? 0)) return;

        // Log denied access attempt
        rbac_audit('access_denied', "view:$viewKey", null, [
            'required' => $minLevel,
            'granted'  => $access,
        ]);

        $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
        if ($isAjax) {
            json_response([
                'error' => 'access_denied',
                'message' => 'You do not have access to this resource.',
                'required' => $minLevel,
                'granted'  => $access,
            ], 403);
        }

        http_response_code(403);
        $role      = rbac_current_role();
        $roleLabel = $role['label'] ?? 'Unknown';
        $homeUrl   = rbac_home_url();
        echo "<!doctype html><meta charset='utf-8'><title>Access restricted</title>
            <div style='max-width:520px;margin:80px auto;font-family:Georgia,serif;text-align:center;padding:40px 20px;'>
              <div style='font-size:48px;color:#7f1d1d;'>&#x1F512;</div>
              <h1 style='font-size:22px;color:#1a1a1a;'>Access restricted</h1>
              <p style='font-size:14px;color:#3c3c3c;line-height:1.6;'>This section is not available for the <b>" . e($roleLabel) . "</b> role.</p>
              <a href='" . e($homeUrl) . "' style='display:inline-block;margin-top:16px;padding:10px 18px;background:var(--green);color:#fff;border-radius:6px;text-decoration:none;font-size:13px;'>Back to dashboard</a>
            </div>";
        exit;
    }

    /**
     * Return the best landing URL for the current user based on their role scope.
     * Priority order matches the sidebar nav structure.
     */
    function rbac_home_url(): string
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        // FPC: fill this in once the module set is known. Order matters — the
        // first entry the user can reach becomes their landing page, so list
        // them most-specific first. Keys must match those used in rbac_can().
        $candidates = [
            // 'dashboard' => '/modules/<module>/pages/dashboard.php',
        ];

        foreach ($candidates as $key => $path) {
            if (rbac_can($key) !== 'none') {
                $cached = app_url($path);
                return $cached;
            }
        }

        // Fallback while no modules exist yet.
        $cached = app_url('/');
        return $cached;
    }

    /**
     * Write an audit log entry. Always safe to call; never throws.
     */
    function rbac_audit(string $action, string $resourceType, $resourceId = null, array $details = []): void
    {
        try {
            $role = rbac_current_role();
            db_insert('fpc_access_log', [
                'org_id'        => 1,
                'user_id'       => current_user_id(),
                'user_role_key' => $role['key']   ?? null,
                'user_name'     => $role['label'] ?? null,
                'action'        => $action,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
                'details'       => $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent'    => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
            ]);
        } catch (Throwable $e) {
            logger('Audit log write failed: ' . $e->getMessage(), 'error', 'rbac');
        }
    }
}
