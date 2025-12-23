<?php
// Usage: include 'partials/sidebar.php';
// Determine script directory and app root (handles subfolders)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$parts = array_values(array_filter(explode('/', $scriptDir), 'strlen'));
$originalDepth = count($parts);
if (end($parts) === 'admin') array_pop($parts);
$appRoot = str_repeat('../', $originalDepth - count($parts));

$menu = [
    ['label' => 'New Invoice', 'href' => $appRoot . '/index.php', 'icon' => 'plus', 'permission' => null],
    ['label' => 'Dashboard', 'href' => $appRoot . '/admin/index.php', 'icon' => 'home', 'permission' => null],
    ['label' => 'Invoices', 'href' => $appRoot . '/manager.php', 'icon' => 'file-text', 'permission' => null],
    ['label' => 'Customers', 'href' => $appRoot . '/admin/customers.php', 'icon' => 'users', 'permission' => 'manage_customers'],
    ['label' => 'Labors & Parts', 'href' => $appRoot . '/admin/labors_parts_pro.php', 'icon' => 'wrench', 'permission' => null],
    ['label' => 'Export CSV', 'href' => $appRoot . '/admin/export_invoices.php', 'icon' => 'download', 'permission' => 'export_csv'],
    ['label' => 'Users', 'href' => $appRoot . '/admin/users.php', 'icon' => 'user', 'permission' => 'manage_users'],
    ['label' => 'Roles & Permissions', 'href' => $appRoot . '/admin/permissions.php', 'icon' => 'shield', 'permission' => null],
    ['label' => 'Audit Logs', 'href' => $appRoot . '/admin/logs.php', 'icon' => 'clock', 'permission' => 'view_logs']
];

// If current user is a manager, restrict sidebar to only New Invoice and Invoices
if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
    $menu = array_values(array_filter($menu, function($it){
        return in_array($it['label'], ['New Invoice', 'Invoices']);
    }));
}
$logoutHref = $appRoot . '/logout.php';

function svgIcon($name){
    $icons = [
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L2 8v8a2 2 0 002 2h3v-6h6v-6h3a2 2 0 002-2V8L10 2z"/></svg>',
        'file-text' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v12a2 2 0 01-2 2z"/></svg>',
        'users' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'download' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.58 9.74-8 10-4.42-.26-8-5-8-10V6l8-4z"/></svg>',
        'clock' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>',
        'plus' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>',
        'wrench' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>'
    ];
    return $icons[$name] ?? '';
}
?>

<!-- Bootstrap CSS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Sidebar (desktop) & Off-canvas (mobile) -->
<div class="bg-dark text-white position-fixed start-0 top-0 vh-100 shadow d-none d-md-block" id="site-sidebar" style="width: 250px; z-index: 1040;">
    <div class="d-flex flex-column h-100">
        <div class="p-3 border-bottom border-secondary d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <div class="fw-bold fs-5">AutoShop</div>
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <div class="position-relative">
                    <button id="notifButton" class="btn btn-link text-secondary p-1" title="Notifications">
                        <svg class="bi bi-bell" width="20" height="20" fill="currentColor" viewBox="0 0 16 16"><path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zM8 1.918l-.797.161A4.002 4.002 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C10.134 8.197 10 6.628 10 6a4.002 4.002 0 0 0-3.203-3.92L8 1.917zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 .99 1.74A3.002 3.002 0 0 1 5 6c0 .646.124 2.368.51 3.822.126.63.272 1.272.45 1.878h6.1z"/></svg>
                    </button>
                    <button id="notifTestButton" class="btn btn-link text-secondary p-1" title="Test sound">🔊</button>
                    <button id="notifMuteButton" class="btn btn-link text-secondary p-1" title="Mute notifications">🔈</button>
                    <audio id="notifAudio" preload="auto" style="display:none">
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.mp3" type="audio/mpeg">
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.ogg" type="audio/ogg">
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.php" type="audio/wav">
                    </audio>
                    <span id="notifBadge" class="badge bg-danger position-absolute top-0 start-100 translate-middle d-none">0</span>
                </div>
                <?php endif; ?>
            </div>
            <button id="closeSidebar" class="btn btn-link text-secondary">✕</button>
            <button id="collapseSidebar" class="btn btn-link text-secondary d-none d-md-inline" title="Collapse sidebar">◀</button>
        </div>

        <nav class="flex-grow-1 overflow-auto p-3">
            <ul class="nav flex-column">
                <?php foreach ($menu as $item):
                    if ($item['permission'] && !function_exists('currentUserCan')) continue;
                    if ($item['permission'] && !currentUserCan($item['permission'])) continue;

                    $raw = $item['href'];
                    if (preg_match('#^https?://#i', $raw)) {
                        $href = $raw;
                    } else {
                        $parts = array_values(array_filter(explode('/', $raw), 'strlen'));
                        $clean = [];
                        foreach ($parts as $p) {
                            if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p;
                        }
                        $href = rtrim($appRoot, '/') . '/' . implode('/', $clean);
                    }

                    $isActive = strpos($_SERVER['SCRIPT_NAME'], $href) !== false || basename($_SERVER['SCRIPT_NAME']) === basename($href);
                ?>
                <li class="nav-item">
                    <a href="<?php echo htmlspecialchars($href); ?>" class="nav-link d-flex align-items-center gap-3 py-2 px-3 rounded <?php echo $isActive ? 'bg-warning text-dark fw-bold' : 'text-white'; ?>" title="<?php echo htmlspecialchars($item['label']); ?>">
                        <span><?php echo svgIcon($item['icon']); ?></span>
                        <span class="sidebar-text"><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>

        <div class="p-3 border-top border-secondary">
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="btn btn-danger w-100">Logout</a>
        </div>
    </div>
</div>

<!-- Mobile Offcanvas Sidebar -->
<div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
        <h5 class="offcanvas-title" id="mobileSidebarLabel">AutoShop</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <nav class="flex-grow-1">
            <ul class="nav flex-column">
                <?php foreach ($menu as $item):
                    if ($item['permission'] && !function_exists('currentUserCan')) continue;
                    if ($item['permission'] && !currentUserCan($item['permission'])) continue;

                    $raw = $item['href'];
                    if (preg_match('#^https?://#i', $raw)) {
                        $href = $raw;
                    } else {
                        $parts = array_values(array_filter(explode('/', $raw), 'strlen'));
                        $clean = [];
                        foreach ($parts as $p) {
                            if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p;
                        }
                        $href = rtrim($appRoot, '/') . '/' . implode('/', $clean);
                    }

                    $isActive = strpos($_SERVER['SCRIPT_NAME'], $href) !== false || basename($_SERVER['SCRIPT_NAME']) === basename($href);
                ?>
                <li class="nav-item">
                    <a href="<?php echo htmlspecialchars($href); ?>" class="nav-link text-white d-flex align-items-center gap-3 py-2 px-3 rounded <?php echo $isActive ? 'bg-warning text-dark fw-bold' : ''; ?>">
                        <span><?php echo svgIcon($item['icon']); ?></span>
                        <span><?php echo htmlspecialchars($item['label']); ?></span>
                    </a>
                </li>
                <?php endforeach; ?>
            </ul>
        </nav>
        <div class="mt-auto">
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="btn btn-danger w-100">Logout</a>
        </div>
    </div>
</div>

<!-- Mobile menu button -->
<button class="btn btn-dark position-fixed bottom-4 start-4 d-md-none shadow" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
    <svg class="bi bi-list" width="24" height="24" fill="currentColor" viewBox="0 0 16 16"><path fill-rule="evenodd" d="M2.5 12a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5zm0-4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 0 1H3a.5.5 0 0 1-.5-.5z"/></svg>
</button>

<style>
#site-sidebar.collapsed {
    width: 4rem;
}
#site-sidebar.collapsed .sidebar-text {
    opacity: 0;
    pointer-events: none;
}
#site-sidebar.collapsed .nav-link {
    justify-content: center;
}
</style>

<script>
(function(){
    // Desktop sidebar collapse
    const collapseBtn = document.getElementById('collapseSidebar');
    if (collapseBtn) {
        collapseBtn.addEventListener('click', function(){
            const sidebar = document.getElementById('site-sidebar');
            const main = document.querySelector('main.ml-0, div.ml-0');
            sidebar.classList.toggle('collapsed');
            this.textContent = sidebar.classList.contains('collapsed') ? '▶' : '◀';
            if (main && window.innerWidth >= 768) {
                if (sidebar.classList.contains('collapsed')) {
                    main.classList.remove('md:ml-64');
                    main.classList.add('md:ml-16');
                } else {
                    main.classList.remove('md:ml-16');
                    main.classList.add('md:ml-64');
                }
            }
        });
    }

    // Close sidebar on desktop
    const closeBtn = document.getElementById('closeSidebar');
    if (closeBtn) closeBtn.addEventListener('click', function(){
        const sidebar = document.getElementById('site-sidebar');
        sidebar.style.transform = 'translateX(-100%)';
        const main = document.querySelector('main.ml-0, div.ml-0');
        if (main && window.innerWidth >= 768) {
            main.classList.remove('md:ml-64');
            main.classList.add('md:ml-0');
        }
    });

    // Notification system
    (function(){
        const notifButton = document.getElementById('notifButton');
        const notifBadge = document.getElementById('notifBadge');
        let lastId = null;
        let pollingInterval = 8000;
        let pollingTimer = null;
        let inFlight = false;

        function poll(){
            if (inFlight) return;
            inFlight = true;
            fetch('<?php echo $appRoot; ?>/api_live_invoices.php?action=latest_id')
                .then(r => r.json())
                .then(data => {
                    if (data.success && data.id !== lastId) {
                        lastId = data.id;
                        updateBadge(data.count || 0);
                        if (data.count > 0 && !isMuted()) {
                            playSound();
                            showAnimatedNotification(`New invoice(s) created! (${data.count})`, 0);
                        }
                    }
                })
                .catch(e => console.warn('Poll error', e))
                .finally(() => inFlight = false);
        }

        function updateBadge(count){
            if (count > 0) {
                notifBadge.textContent = count > 99 ? '99+' : count;
                notifBadge.classList.remove('d-none');
            } else {
                notifBadge.classList.add('d-none');
            }
        }

        function playSound(){
            const audio = document.getElementById('notifAudio');
            if (audio) {
                audio.currentTime = 0;
                audio.play().catch(e => console.warn('Audio play failed', e));
            }
        }

        function isMuted(){
            return localStorage.getItem('invoiceNotifMuted') === 'true';
        }

        function setMuted(muted){
            localStorage.setItem('invoiceNotifMuted', muted);
            if (muted) {
                notifMuteButton.textContent = '🔇';
                notifMuteButton.title = 'Unmute notifications';
            } else {
                notifMuteButton.textContent = '🔈';
                notifMuteButton.title = 'Mute notifications';
            }
        }

        function showAnimatedNotification(text, type){
            // Assume showToast or similar function exists
            if (typeof showToast === 'function') {
                showToast(text, type);
            } else {
                console.log(text);
            }
        }

        function testAudio(){
            const audio = document.getElementById('notifAudio');
            if (!audio) return;
            audio.currentTime = 0;
            audio.play().then(() => {
                showAnimatedNotification('Sound test played', 0);
            }).catch(e => {
                console.warn('testAudio error', e);
                showAnimatedNotification('Unable to play sound — check browser autoplay settings or file existence', 0);
            });
        }

        if (notifTestButton) notifTestButton.addEventListener('click', testAudio);
        if (notifMuteButton) notifMuteButton.addEventListener('click', ()=>{ setMuted(!isMuted()); });

        // Expose for console debugging
        window.__invoiceNotifications = { poll, fetchLatestId, testAudio, setMuted };
    })();
})();
</script>

<style>
    /* Ensure main content has left margin on md+ screens so sidebar doesn't overlap */
    @media (min-width: 768px) {
        main, .container, .max-w-7xl { margin-left: 16rem; }
    }
</style>

<?php if (!empty($_SESSION['created_items'])): $created_items = $_SESSION['created_items']; unset($_SESSION['created_items']); ?>
<script>
document.addEventListener('DOMContentLoaded', function(){
    const created = <?php echo json_encode($created_items, JSON_UNESCAPED_UNICODE); ?>;
    if (!Array.isArray(created)) return;
    created.forEach((it, idx) => {
        let text = '';
        if (it.type === 'part') text = 'Saved new Part: ' + it.name;
        else if (it.type === 'labor') text = 'Saved new Labor: ' + it.name;
        else if (it.type === 'part_price') text = `Saved price for part '${it.name}' (${it.vehicle}): ${it.price} ₾`;
        else if (it.type === 'labor_price') text = `Saved price for labor '${it.name}' (${it.vehicle}): ${it.price} ₾`;
        else text = (it.name || it.item_id) ? `${it.name || it.item_id}` : 'Created item';
        setTimeout(() => {
            try { if (typeof showToast === 'function') showToast(text, 0); else console.log(text); } catch(e){ console.log(text); }
        }, idx * 500);
    });
});
</script>
<?php endif; ?>