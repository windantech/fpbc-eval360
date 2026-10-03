<?php
/**
 * lib/sidebar.php — role-aware sidebar.
 *
 * The render engine below is generic and should not need changing: it reads
 * the nav tree from sidebar_programmes(), hides anything the current role
 * cannot reach, handles the accordion and the mobile drawer.
 *
 * Visibility, applied per item by sidebar_render():
 *   rbac_can($item['view']) === 'full' | 'read'  -> rendered
 *   rbac_can($item['view']) === 'none'           -> not rendered at all
 *   $item['view'] empty                          -> always visible to a signed-in user
 *
 * ADDING NAVIGATION: edit sidebar_programmes() below and nothing else.
 *
 * A "programme" is one top-level area of work with its own nav tree. Keep one
 * unless FPC genuinely runs separate programmes that staff switch between —
 * the switcher is dead weight with a single entry.
 */

if (!function_exists('sidebar_programmes')) {

    /**
     * Programme catalogue. Drives both the sidebar nav and the programme switcher.
     *
     * SKELETON — fill in once the FPC module set is agreed. Shape of one item:
     *
     *   ['view'  => 'children',                       // RBAC key; '' = always visible
     *    'icon'  => 'bi-people',                      // any Bootstrap Icon name
     *    'label' => 'Child Register',
     *    'url'   => app_url('/modules/<module>/pages/<page>.php'),
     *    'badge' => '12',                             // optional count
     *    'badge_style' => 'urgent']                   // optional: urgent | warn
     *
     * Section options: 'section' (label), 'section_no_collapse' => true to pin
     * it open, 'section_meta' => 'tooltip text'.
     */
    function sidebar_programmes(): array
    {
        return [

            'fpc' => [
                'key'       => 'fpc',
                'name'      => 'FPC',
                'long_name' => 'Future Planning for the Child',
                'sub'       => '',                      // e.g. 'Demo environment'
                'color'     => '#1b5e8f',               // must match --brand in lib/layout.php
                'icon'      => 'bi-heart-pulse',
                'home'      => '',                      // RBAC key of the landing page
                'who'       => '',                      // shown under the programme name

                'nav' => [
                    // [
                    //     'section' => 'Casework',
                    //     'items' => [
                    //         ['view' => 'dashboard', 'icon' => 'bi-grid-1x2', 'label' => 'Dashboard',
                    //          'url' => app_url('/modules/core/pages/dashboard.php')],
                    //     ],
                    // ],
                    // [
                    //     'section' => 'Administration',
                    //     'section_no_collapse' => true,
                    //     'items' => [
                    //         ['view' => 'users',  'icon' => 'bi-people-fill', 'label' => 'Users & Roles',
                    //          'url' => app_url('/modules/admin/pages/users.php')],
                    //         ['view' => 'config', 'icon' => 'bi-gear',        'label' => 'Configuration',
                    //          'url' => app_url('/modules/admin/pages/config.php')],
                    //     ],
                    // ],
                ],
            ],

        ];
    }

    function current_programme_key(): string
    {
        return $_SESSION['_current_prog'] ?? 'fpc';
    }

    function current_programme(): array
    {
        $programmes = sidebar_programmes();
        $key = current_programme_key();
        return $programmes[$key] ?? $programmes['fpc'];
    }

    function set_current_programme(string $key): void
    {
        if (isset(sidebar_programmes()[$key])) {
            $_SESSION['_current_prog'] = $key;
        }
    }

    /**
     * Render the sidebar nav for the active programme.
     */
    function sidebar_render(string $activeViewKey = ''): void
    {
        $prog = current_programme();
        $nav  = $prog['nav'] ?? [];
        $sgIdx = 0;

        foreach ($nav as $section) {

            $visibleItems = [];
            $sectionHasActive = false;
            foreach ($section['items'] as $item) {
                $access = empty($item['view']) ? 'full' : rbac_can($item['view']);
                if ($access === 'none') continue;
                $item['_access'] = $access;
                $visibleItems[] = $item;
                if (!empty($item['view']) && $item['view'] === $activeViewKey) {
                    $sectionHasActive = true;
                }
            }
            if (empty($visibleItems)) continue;

            $noCollapse = !empty($section['section_no_collapse']);
            $sgId = 'sg-' . $sgIdx++;
            $meta = !empty($section['section_meta']) ? ' title="' . e($section['section_meta']) . '"' : '';

            if ($noCollapse) {
                // Institution footer: label only, no toggle
                ?>
                <div class="sb-section sb-section-fixed"<?= $meta ?>>
                    <span class="sec-lbl"><?= e($section['section']) ?></span>
                </div>
                <div class="sb-nav-group" id="<?= e($sgId) ?>">
                <?php
            } else {
                // Collapsible section — section-has-active forces open via JS
                $activeAttr = $sectionHasActive ? ' data-has-active="1"' : '';
                ?>
                <div class="sb-section" id="hd-<?= e($sgId) ?>" data-grp="<?= e($sgId) ?>"<?= $meta ?><?= $activeAttr ?>>
                    <span class="sec-lbl"><?= e($section['section']) ?></span>
                    <i class="bi bi-chevron-down sec-chev"></i>
                </div>
                <div class="sb-nav-group" id="<?= e($sgId) ?>">
                <?php
            }

            foreach ($visibleItems as $item):
                $isActive   = (!empty($item['view']) && $item['view'] === $activeViewKey);
                $badgeClass = ($item['badge_style'] ?? '') === 'urgent' ? ' urgent' : '';
                $classes    = 'nav-item' . ($isActive ? ' active' : '');
            ?>
                <a href="<?= e($item['url']) ?>" class="<?= e($classes) ?>">
                    <i class="bi <?= e($item['icon']) ?>"></i>
                    <span class="nav-lbl"><?= e($item['label']) ?></span>
                    <?php if (!empty($item['badge'])): ?>
                        <span class="nav-badge<?= $badgeClass ?>"><?= e($item['badge']) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
            </div>
            <?php
        }
    }

    /**
     * Render the programme-switcher button + dropdown for the topbar.
     */
    function programme_switcher_render(): void
    {
        $prog = current_programme();
        ?>
        <button type="button" class="prog-switch-btn"
                onclick="document.getElementById('prog-switcher-menu').classList.toggle('open')">
            <span class="prog-dot" style="background:<?= e($prog['color']) ?>;"></span>
            <span><?= e($prog['name']) ?></span>
            <i class="bi bi-chevron-down" style="font-size:10px;opacity:.7;"></i>
        </button>
        <div id="prog-switcher-menu" class="prog-switcher-menu">
            <?php foreach (sidebar_programmes() as $key => $p): ?>
                <a href="<?= e(app_url('/modules/ea/pages/switch_programme.php?to=' . urlencode($key))) ?>"
                   class="prog-switcher-item <?= $key === $prog['key'] ? 'active' : '' ?>">
                    <span class="prog-dot" style="background:<?= e($p['color']) ?>;"></span>
                    <div>
                        <div class="prog-name"><?= e($p['name']) ?></div>
                        <div class="prog-sub"><?= e($p['sub']) ?></div>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
        <?php
    }
}
