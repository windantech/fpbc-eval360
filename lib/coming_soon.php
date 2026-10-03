<?php
/**
 * lib/coming_soon.php — Shared "coming soon" page renderer.
 *
 * Pages that aren't built yet include this and render a clean placeholder
 * with the proper layout (topbar + sidebar) so the user can still navigate.
 *
 * Usage from a stub page (e.g. modules/<module>/pages/<page>.php):
 *
 *   require_once __DIR__ . '/../../../bootstrap/init.php';
 *   require_once __DIR__ . '/../../../lib/rbac.php';
 *   require_once __DIR__ . '/../../../lib/layout.php';
 *   require_once __DIR__ . '/../../../lib/coming_soon.php';
 *
 *   coming_soon([
 *       'title'      => 'Tracks & Cohorts',
 *       'view_key'   => 'cohorts',
 *       'sub'        => 'Student/staff cohort management for FPC',
 *       'icon'       => 'bi-people',
 *       'description'=> 'Manage admitted students and staff cohorts...',
 *       'features'   => ['Cohort admission tracking', 'Demographic breakdown', ...],
 *   ]);
 */

if (!function_exists('coming_soon')) {

    function coming_soon(array $opts): void
    {
        if (!current_user_id()) {
            redirect(public_url('/login.php'));
        }

        $title       = $opts['title']       ?? 'Coming soon';
        $viewKey     = $opts['view_key']    ?? '';
        $sub         = $opts['sub']         ?? '';
        $icon        = $opts['icon']        ?? 'bi-hourglass-split';
        $description = $opts['description'] ?? '';
        $features    = $opts['features']    ?? [];

        // RBAC gate (if a view_key was provided)
        if ($viewKey) {
            rbac_require($viewKey, 'read');
            rbac_audit('view_stub', $viewKey, null);
        }

        layout_start($title, ['active_view' => $viewKey]);
        ?>

        <style>
            .stub-card {
                background: #fff;
                border: 1px solid #e8e3d3;
                border-radius: 10px;
                padding: 48px 36px;
                text-align: center;
                max-width: 720px;
                margin: 40px auto;
            }
            .stub-icon {
                width: 64px; height: 64px;
                border-radius: 50%;
                background: #f5f1e3;
                color: var(--green);
                display: inline-flex;
                align-items: center;
                justify-content: center;
                font-size: 28px;
                margin-bottom: 16px;
            }
            .stub-card h1 { font-family: Georgia, serif; font-size: 24px; margin: 0 0 6px; }
            .stub-card .stub-sub { font-size: 13px; color: #6e6a5e; margin-bottom: 22px; }
            .stub-card .stub-desc { font-size: 13.5px; color: #3c3c3c; line-height: 1.6; max-width: 480px; margin: 0 auto 20px; }
            .stub-card .stub-features {
                text-align: left;
                background: #fdfcf7;
                border-radius: 6px;
                padding: 14px 18px;
                max-width: 440px;
                margin: 20px auto;
                font-size: 12.5px;
                color: #3c3c3c;
                line-height: 1.7;
            }
            .stub-card .stub-features b {
                display: block;
                font-family: 'Consolas', monospace;
                font-size: 10px;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: var(--green);
                margin-bottom: 6px;
                font-weight: 700;
            }
            .stub-card .stub-features ul {
                margin: 0; padding-left: 18px;
            }
            .stub-card .stub-features li { margin-bottom: 4px; }
            .stub-card .stub-status {
                display: inline-block;
                margin-top: 14px;
                padding: 4px 12px;
                background: #fef6e7;
                border: 1px solid #f4d4a3;
                border-radius: 99px;
                font-size: 10.5px;
                font-family: 'Consolas', monospace;
                letter-spacing: 0.1em;
                text-transform: uppercase;
                color: var(--gold);
                font-weight: 600;
            }
            .stub-back {
                margin-top: 18px;
            }
        </style>

        <div class="stub-card">
            <div class="stub-icon"><i class="bi <?= e($icon) ?>"></i></div>
            <h1><?= e($title) ?></h1>
            <?php if ($sub): ?>
                <div class="stub-sub"><?= e($sub) ?></div>
            <?php endif; ?>

            <?php if ($description): ?>
                <div class="stub-desc"><?= e($description) ?></div>
            <?php endif; ?>

            <?php if (!empty($features)): ?>
                <div class="stub-features">
                    <b>Planned for this page</b>
                    <ul>
                        <?php foreach ($features as $f): ?>
                            <li><?= e($f) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="stub-status">
                <i class="bi bi-hammer"></i> Under construction
            </div>

            <div class="stub-back">
                <a href="<?= e(app_url('/modules/ea/pages/dashboard.php')) ?>" class="btn btn-ghost">
                    <i class="bi bi-arrow-left"></i> Back to dashboard
                </a>
            </div>
        </div>

        <?php
        layout_end();
        exit;
    }
}
