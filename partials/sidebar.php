<?php
// Usage: include 'partials/sidebar.php';
// Determine script directory and app root (handles subfolders)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$parts = array_values(array_filter(explode('/', $scriptDir), 'strlen'));
$originalDepth = count($parts);
if (end($parts) === 'admin') array_pop($parts);
$appRoot = str_repeat('../', $originalDepth - count($parts));

// Badges placeholder (allow pages to inject counts)
$badges = $badges ?? [];

// New menu sections with nested children and optional badge keys
$menu_sections = [
    ['label' => 'Analytics', 'items' => [
        ['label' => 'Overview', 'href' => $appRoot . '/admin/index.php', 'icon' => 'home', 'permission' => null]
    ]],
    ['label' => 'Management', 'items' => [
        ['label' => 'Create Invoice', 'href' => $appRoot . '/index.php', 'icon' => 'plus', 'permission' => null],
        ['label' => 'Invoices', 'href' => $appRoot . '/manager.php', 'icon' => 'file-text', 'permission' => null],
        ['label' => 'Customers', 'href' => $appRoot . '/admin/customers.php', 'icon' => 'users', 'permission' => 'manage_customers'],
        ['label' => 'Labors & Parts', 'href' => $appRoot . '/admin/labors_parts_pro.php', 'icon' => 'wrench', 'permission' => 'manage_prices'],
        ['label' => 'Users & Access', 'icon' => 'user', 'permission' => 'manage_users', 'children' => [
            ['label' => 'Users', 'href' => $appRoot . '/admin/users.php', 'permission' => 'manage_users'],
            ['label' => 'Roles & Permissions', 'href' => $appRoot . '/admin/permissions.php', 'permission' => 'manage_permissions']
        ]]
    ]],
    ['label' => 'Settings', 'items' => [
        ['label' => 'System Settings', 'href' => $appRoot . '/admin/settings.php', 'icon' => 'shield', 'permission' => 'manage_settings'],
        ['label' => 'Audit Logs', 'href' => $appRoot . '/admin/logs.php', 'icon' => 'clock', 'permission' => 'view_logs']
    ]]
];

// Manager restriction: only show 'New Invoice' and 'Invoices' for managers
if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
    $menu_sections = array_values(array_filter($menu_sections, function($sec){
        $sec['items'] = array_values(array_filter($sec['items'], function($it){
            return in_array($it['label'], ['New Invoice', 'Invoices', 'Overview']);
        }));
        return count($sec['items']) > 0;
    }));
}

$logoutHref = $appRoot . '/logout.php';

// Load initial inbox badge count from DB (if messages table and user exist)
try {
    if (isset($_SESSION['user_id']) && isset($pdo)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE recipient_id = ? AND is_read = 0");
        $stmt->execute([ (int)$_SESSION['user_id'] ]);
        $unread = (int)$stmt->fetchColumn();
        if ($unread > 0) $badges['inbox'] = $unread;
    }
} catch (Exception $e) {
    // ignore if table doesn't exist
}

// Helper to check permission & active state
function isItemVisible($it){
    if (isset($it['permission']) && $it['permission'] && function_exists('currentUserCan')) return currentUserCan($it['permission']);
    return true;
}

function isActive($href){
    if (!$href) return false;
    return (strpos($_SERVER['SCRIPT_NAME'], $href) !== false) || basename($_SERVER['SCRIPT_NAME']) === basename($href);
}

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

<!-- Sidebar (desktop) & Off-canvas (mobile) -->
<div id="site-sidebar" class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-800 text-white shadow-lg transform -translate-x-full md:translate-x-0 transition-all duration-300" role="navigation" aria-hidden="true">
    <div class="h-full flex flex-col">
        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="font-bold text-lg">AutoShop</div>
                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager'])): ?>
                <div id="notif-root" class="relative">
                    <button id="notifButton" class="ml-2 text-slate-300 hover:text-white p-1 rounded focus:outline-none" title="Notifications" aria-label="Notifications">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                    </button>
                    <button id="notifTestButton" class="ml-2 text-slate-300 hover:text-white p-1 rounded focus:outline-none" title="Test sound" aria-label="Test sound">🔊</button>
                    <button id="notifMuteButton" class="ml-1 text-slate-300 hover:text-white p-1 rounded focus:outline-none" title="Mute notifications" aria-label="Mute notifications">🔈</button>
                    <audio id="notifAudio" preload="auto" aria-hidden="true" style="display:none">
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.mp3" type="audio/mpeg">
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.ogg" type="audio/ogg">
                        <!-- Fallback to server-served WAV if mp3/ogg not present -->
                        <source src="<?php echo $appRoot; ?>assets/sounds/notify.php" type="audio/wav">
                    </audio>
                    <span id="notifBadge" class="hidden absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full px-1 py-0.5">0</span>
                </div>
                <?php endif; ?>
            </div>
            <!-- Mobile close button (explicit close affordance) -->
            <button id="closeSidebarMobile" class="md:hidden ml-2 text-slate-300 hover:text-white p-2 rounded" title="Close menu" aria-label="Close menu" aria-controls="site-sidebar">✕</button> 
        </div>

        <nav class="flex-1 overflow-y-auto p-3" aria-label="Primary navigation">
            <?php foreach ($menu_sections as $section): ?>
                <div class="mt-4">
                    <div class="px-3 text-xs uppercase tracking-wide text-slate-400 font-semibold mb-2"><?php echo htmlspecialchars($section['label']); ?></div>
                    <ul role="menu" class="space-y-1">
                        <?php foreach ($section['items'] as $item):
                            if (!isItemVisible($item)) continue;

                            // resolve href
                            $href = $item['href'] ?? '#';
                            if (!preg_match('#^https?://#i', $href) && $href !== '#'){
                                $parts = array_values(array_filter(explode('/', $href), 'strlen'));
                                $clean = [];
                                foreach ($parts as $p) { if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p; }
                                $href = rtrim($appRoot, '/') . '/' . implode('/', $clean);
                            }

                            $hasChildren = !empty($item['children']);
                            $badge = (isset($item['badge_key']) && isset($badges[$item['badge_key']])) ? (int)$badges[$item['badge_key']] : 0;
                            $active = isActive($href);
                            $id = 'menu-' . md5($section['label'] . '|' . ($item['label'] ?? ''));
                        ?>
                        <li role="none">
                            <?php if ($hasChildren): ?>
                                <button role="menuitem" aria-haspopup="true" aria-expanded="false" data-accordion-button="<?php echo $id; ?>" class="w-full flex items-center justify-between gap-3 px-3 py-2 rounded-lg text-left hover:bg-slate-700 transition">
                                    <span class="flex items-center gap-3">
                                        <span class="w-5 h-5 flex-shrink-0" aria-hidden="true"><?php echo svgIcon($item['icon'] ?? 'file-text'); ?></span>
                                        <span class="sidebar-text"><?php echo htmlspecialchars($item['label']); ?></span>
                                    </span>
                                    <span class="flex items-center gap-2">
                                        <?php if ($badge > 0): ?>
                                            <span id="badge-<?php echo htmlspecialchars($item['badge_key']); ?>" class="inline-flex items-center justify-center bg-red-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo $badge; ?></span>
                                        <?php else: ?>
                                            <?php if (isset($item['badge_key'])): ?><span id="badge-<?php echo htmlspecialchars($item['badge_key']); ?>" class="inline-flex items-center justify-center bg-red-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full hidden">0</span><?php endif; ?>
                                        <?php endif; ?>
                                        <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                                    </span>
                                </button>
                                <ul id="<?php echo $id; ?>" role="menu" aria-hidden="true" class="mt-1 ml-4 pl-1 border-l border-slate-700 hidden space-y-1">
                                    <?php foreach ($item['children'] as $child):
                                        if (!isItemVisible($child)) continue;
                                        $chHref = $child['href'] ?? '#';
                                        if (!preg_match('#^https?://#i', $chHref) && $chHref !== '#'){
                                            $parts = array_values(array_filter(explode('/', $chHref), 'strlen'));
                                            $clean = [];
                                            foreach ($parts as $p) { if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p; }
                                            $chHref = rtrim($appRoot, '/') . '/' . implode('/', $clean);
                                        }
                                        $chActive = isActive($chHref);
                                    ?>
                                    <li role="none"><a role="menuitem" href="<?php echo htmlspecialchars($chHref); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-700 transition <?php echo $chActive ? 'bg-yellow-500 text-slate-900 font-semibold' : 'text-slate-200'; ?>"><?php echo htmlspecialchars($child['label']); ?></a></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <a role="menuitem" href="<?php echo htmlspecialchars($href); ?>" class="flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-slate-700 transition <?php echo $active ? 'bg-yellow-500 text-slate-900 font-semibold' : 'text-slate-200'; ?>">
                                    <span class="w-5 h-5 flex-shrink-0" aria-hidden="true"><?php echo svgIcon($item['icon'] ?? 'file-text'); ?></span>
                                    <span class="sidebar-text"><?php echo htmlspecialchars($item['label']); ?></span>
                                    <?php if ($badge > 0): ?>
                                        <span id="badge-<?php echo htmlspecialchars($item['badge_key']); ?>" class="ml-auto inline-flex items-center justify-center bg-red-600 text-white text-xs font-semibold px-2 py-0.5 rounded-full"><?php echo $badge; ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-700">
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500 text-white text-center">Logout</a>
        </div>
    </div>
</div>

<!-- Mobile overlay -->
<div id="sidebarOverlay" class="fixed inset-0 bg-black bg-opacity-40 z-30 hidden md:hidden" aria-hidden="true"></div>

<!-- Mobile open button -->
<button id="openSidebar" class="md:hidden fixed bottom-4 left-4 z-50 bg-yellow-400 text-slate-900 p-3 rounded-full shadow-lg" aria-label="Open menu" aria-expanded="false" aria-controls="site-sidebar">☰</button>

<style>
/* Focus ring and press animation for interactive buttons */
button:focus-visible, [role="menuitem"]:focus-visible { outline: 3px solid rgba(99,102,241,0.95); outline-offset: 2px; border-radius: 6px; }
button.btn-press-anim:active { transform: translateY(1px) scale(0.995); transition: transform 80ms ease; }

/* Tooltip */
.sidebar-tooltip { position: fixed; z-index: 60; background: rgba(20,20,20,0.95); color: #fff; padding: 6px 8px; font-size: 12px; border-radius: 6px; box-shadow: 0 6px 18px rgba(0,0,0,0.25); pointer-events: none; transform-origin: center bottom; opacity: 0; transition: opacity 120ms ease, transform 120ms cubic-bezier(.2,.9,.2,1); }
.sidebar-tooltip.show { opacity: 1; transform: translateY(-6px) scale(1); }

</style>



<script>
(function(){
    var openBtn = document.getElementById('openSidebar');
    var overlay = document.getElementById('sidebarOverlay');
    var sidebar = document.getElementById('site-sidebar');

    function open() {
        if (!sidebar) return;
        sidebar.classList.remove('-translate-x-full');
        sidebar.setAttribute('aria-hidden','false');
        document.body.classList.add('overflow-hidden');
        if (overlay) overlay.classList.remove('hidden');
        if (openBtn) openBtn.setAttribute('aria-expanded','true');
    }
    function close() {
        if (!sidebar) return;
        sidebar.classList.add('-translate-x-full');
        sidebar.setAttribute('aria-hidden','true');
        document.body.classList.remove('overflow-hidden');
        if (overlay) overlay.classList.add('hidden');
        if (openBtn) openBtn.setAttribute('aria-expanded','false');
    }

    if (openBtn) openBtn.addEventListener('click', open);
    if (overlay) overlay.addEventListener('click', close);
    // Mobile explicit close button
    const closeMobileBtn = document.getElementById('closeSidebarMobile');
    if (closeMobileBtn) closeMobileBtn.addEventListener('click', close);
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && sidebar && !sidebar.classList.contains('-translate-x-full')) close(); });

    // Tooltip helper (hover on desktop, long-press on touch devices)
    (function(){
        const tooltipEl = document.createElement('div');
        tooltipEl.id = 'sidebarTooltip';
        tooltipEl.className = 'sidebar-tooltip';
        document.body.appendChild(tooltipEl);

        function showTooltipFor(btn, text){
            if (!btn || !tooltipEl) return;
            tooltipEl.textContent = text;
            tooltipEl.classList.add('show');
            // position
            const r = btn.getBoundingClientRect();
            // allow browser to measure
            const tRect = tooltipEl.getBoundingClientRect();
            let left = r.left + (r.width - tRect.width) / 2;
            left = Math.max(6, left);
            let top = r.top - tRect.height - 8;
            if (top < 6) top = r.bottom + 8;
            tooltipEl.style.left = left + 'px';
            tooltipEl.style.top = top + 'px';
            btn.setAttribute('aria-describedby', 'sidebarTooltip');
        }
        function hideTooltipFor(btn){ if (!tooltipEl) return; tooltipEl.classList.remove('show'); if (btn) btn.removeAttribute('aria-describedby'); }

        function attach(btn, text){
            if (!btn) return;
            btn.classList.add('btn-press-anim');
            let longpress = null;
            btn.addEventListener('mouseenter', ()=> showTooltipFor(btn, text));
            btn.addEventListener('mouseleave', ()=> hideTooltipFor(btn));
            btn.addEventListener('focus', ()=> showTooltipFor(btn, text));
            btn.addEventListener('blur', ()=> hideTooltipFor(btn));
            btn.addEventListener('pointerdown', ()=> { longpress = setTimeout(()=> showTooltipFor(btn, text), 600); });
            btn.addEventListener('pointerup', ()=> { if (longpress) clearTimeout(longpress); });
            btn.addEventListener('pointercancel', ()=> { if (longpress) clearTimeout(longpress); });
        }

        attach(openBtn, 'Open menu');
        attach(closeMobileBtn, 'Close menu');
    })();





    // --- Accordion behavior: nested menus, keyboard navigable ---
    (function(){
        const buttons = document.querySelectorAll('[data-accordion-button]');
        buttons.forEach(btn => {
            const targetId = btn.getAttribute('data-accordion-button');
            const panel = document.getElementById(targetId);
            btn.addEventListener('click', ()=>{
                const expanded = btn.getAttribute('aria-expanded') === 'true';
                btn.setAttribute('aria-expanded', expanded ? 'false' : 'true');
                if (panel){
                    panel.classList.toggle('hidden');
                    panel.setAttribute('aria-hidden', expanded ? 'true' : 'false');
                }
            });

            // Keyboard: Enter/Space toggles, Arrow keys move focus
            btn.addEventListener('keydown', (e)=>{
                if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); btn.click(); }
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp'){
                    e.preventDefault();
                    const focusable = Array.from(document.querySelectorAll('[role="menuitem"]')).filter(n => n.offsetParent !== null);
                    const idx = focusable.indexOf(btn);
                    if (idx !== -1){
                        const next = e.key === 'ArrowDown' ? focusable[idx+1] : focusable[idx-1];
                        if (next) next.focus();
                    }
                }
                if (e.key === 'Home') { e.preventDefault(); const first = document.querySelector('[role="menuitem"]'); if (first) first.focus(); }
                if (e.key === 'End') { e.preventDefault(); const all = document.querySelectorAll('[role="menuitem"]'); if (all.length) all[all.length-1].focus(); }
            });
        });
    })();
})();
</script>


<script>

    // Notification system: polls server for new invoices and shows badge/toasts/sound
    (function(){
        const notifButton = document.getElementById('notifButton');
        const notifBadge = document.getElementById('notifBadge');
        let lastId = null;
        let pollingInterval = 8000; // 8 seconds
        let pollingTimer = null;
        let inFlight = false;

        // Create and append minimal styles for animated notifications and bell animation
        (function addNotifStyles(){
            const css = `
                @keyframes notif-slide-in { from { transform: translateX(24px); opacity: 0; } to { transform: translateX(0); opacity:1; } }
                @keyframes bell-pulse { 0%{ transform: scale(1); } 30%{ transform: scale(1.15) rotate(-8deg);} 60%{ transform: scale(1.03) rotate(6deg);} 100%{transform: scale(1);} }
                .notif-bell-anim { animation: bell-pulse 0.9s ease; }
                #notifContainer { position: fixed; top: 3.5rem; right: 1.5rem; z-index: 70; display: flex; flex-direction: column; gap: 0.5rem; align-items: flex-end; }
                .notif-item { width: 20rem; max-width: calc(100vw - 4rem); background: #fff; border: 1px solid #e5e7eb; border-radius: 0.5rem; box-shadow: 0 6px 18px rgba(0,0,0,0.08); padding: 0.75rem; cursor: pointer; transform: translateX(24px); opacity:0; animation: notif-slide-in 320ms forwards ease; }
                .notif-item .notif-dismiss{ background: transparent; border: 0; font-size: 1.05rem; line-height: 1; cursor: pointer; color: #6b7280; }
            `;
            const s = document.createElement('style'); s.appendChild(document.createTextNode(css)); document.head.appendChild(s);
        })();

        function playBeep(){
            // Prefer a DOM audio element when available (better compatibility on some mobile browsers)
            try {
                const audioEl = document.getElementById('notifAudio');
                if (audioEl){
                    audioEl.currentTime = 0;
                    const p = audioEl.play();
                    if (p && typeof p.then === 'function'){
                        p.catch(()=>{
                            // If play() is rejected (autoplay policy), fall back to WebAudio
                            tryWebAudio();
                        });
                    }
                    return;
                }
            } catch (e) { console.warn('notifAudio play error', e); }

            // WebAudio fallback
            function tryWebAudio(){
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    const ctx = new AudioCtx();
                    const now = ctx.currentTime;
                    // Two short tones for a pleasant notification
                    const tones = [880, 660];
                    tones.forEach((freq, i) => {
                        const o = ctx.createOscillator();
                        const g = ctx.createGain();
                        o.type = 'sine';
                        o.frequency.value = freq;
                        o.connect(g);
                        g.connect(ctx.destination);
                        g.gain.setValueAtTime(0.0001, now + i*0.12);
                        g.gain.exponentialRampToValueAtTime(0.12, now + i*0.12 + 0.02);
                        g.gain.exponentialRampToValueAtTime(0.0001, now + i*0.12 + 0.10);
                        o.start(now + i*0.12);
                        o.stop(now + i*0.12 + 0.11);
                    });
                } catch (e) {
                    // Final fallback: small embedded WAV via Audio object
                    try {
                        const fallback = new Audio();
                        fallback.src = 'data:audio/wav;base64,UklGRiQAAABXQVZFZm10IBAAAAABAAEAESsAACJWAAACABAAZGF0YQAAAAA=';
                        fallback.play().catch(()=>{});
                    } catch (err) { console.warn('Audio fallback failed', err); }
                }
            }

            tryWebAudio();
        }

        function ensureNotifContainer(){
            let c = document.getElementById('notifContainer');
            if (!c){ c = document.createElement('div'); c.id = 'notifContainer'; document.body.appendChild(c); }
            return c;
        }

        function escapeHtml(s){ return String(s).replace(/[&<>\"']/g, function(c){ return { '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;" }[c]; }); }

        function showAnimatedNotification(text, invoiceId){
            const container = ensureNotifContainer();
            const item = document.createElement('div');
            item.className = 'notif-item';
            item.innerHTML = `<div style="display:flex;align-items:flex-start;gap:0.5rem"><div style="flex:1"><div style="font-weight:600">${escapeHtml(text)}</div><div style="font-size:12px;color:#6b7280;margin-top:6px">Click to open</div></div><button class="notif-dismiss" aria-label="Dismiss">×</button></div>`;
            item.addEventListener('click', (e)=>{ if (!e.target.classList.contains('notif-dismiss')) window.open('view_invoice.php?id='+invoiceId,'_blank'); });
            item.querySelector('.notif-dismiss').addEventListener('click', (e)=>{ e.stopPropagation(); hide(); });

            function hide(){ item.style.transform = 'translateX(24px)'; item.style.opacity = '0'; setTimeout(()=>{ item.remove(); }, 300); }

            container.appendChild(item);
            // auto-hide after 7s
            setTimeout(hide, 7000);
        }

        function animateBell(){ if (!notifButton) return; notifButton.classList.add('notif-bell-anim'); setTimeout(()=> notifButton.classList.remove('notif-bell-anim'), 1000); }

        function showToast(text, invoiceId){
            // Keep backward-compatible toast (small fade) but also show animated notification
            showAnimatedNotification(text, invoiceId);
        }

        function showBrowserNotification(title, body, invoiceId){
            if (!('Notification' in window)) return;
            if (Notification.permission === 'granted'){
                const n = new Notification(title, { body });
                n.onclick = () => { window.focus(); window.open('view_invoice.php?id='+invoiceId, '_blank'); };
            }
        }

        function updateBadge(count){
            if (!notifBadge) return;
            if (count <= 0){ notifBadge.classList.add('hidden'); notifBadge.textContent = '0'; }
            else { notifBadge.classList.remove('hidden'); notifBadge.textContent = count > 99 ? '99+' : ''+count; }
        }

        async function fetchLatestId(){
            try {
                const res = await fetch('/api_live_invoices.php', { cache: 'no-store' });
                if (!res.ok) throw new Error('Network');
                const data = await res.json();
                if (data && data.success){
                    lastId = data.latest_id || 0;
                }
            } catch (e) { console.warn('fetchLatestId error', e); }
        }

        async function poll(){
            if (inFlight) return;
            inFlight = true;
            try {
                const url = '/api_live_invoices.php' + (lastId ? ('?last_id=' + encodeURIComponent(lastId)) : '');
                const res = await fetch(url, { cache: 'no-store' });
                if (!res.ok) throw new Error('Network');
                const data = await res.json();
                if (data && data.success){
                    if (data.new_count && data.new_count > 0){
                        // Update badge and show animated notification
                        updateBadge(parseInt(notifBadge.textContent || '0') + data.new_count);
                        // Play sound once
                        playBeep();
                        // animate bell
                        animateBell();
                        // show browser notification for the most recent invoice
                        const latestInvoice = data.invoices[data.invoices.length - 1];
                        if (latestInvoice){
                            const title = `New Invoice #${latestInvoice.id}`;
                            const body = `${latestInvoice.customer_name || 'Unknown'} — ${latestInvoice.plate_number || ''} — ${latestInvoice.grand_total ? '$'+latestInvoice.grand_total : ''}`;
                            showBrowserNotification(title, body, latestInvoice.id);
                            // show an animated in-page notification for latest invoice
                            showAnimatedNotification(`${title}: ${latestInvoice.customer_name || ''}`, latestInvoice.id);
                        }
                        // keep lastId advanced
                        lastId = data.latest_id || lastId;
                    }
                }
            } catch (e) {
                console.warn('poll error', e);
            } finally {
                inFlight = false;
            }
        }

        // Inbox count poller
        async function fetchInboxCount(){
            try {
                const res = await fetch('/api_inbox_count.php', { cache: 'no-store' });
                if (!res.ok) throw new Error('Network');
                const data = await res.json();
                if (data && data.success){
                    const el = document.getElementById('badge-inbox');
                    if (!el) return;
                    const n = parseInt(data.unread_count || 0);
                    if (n > 0){ el.textContent = n; el.classList.remove('hidden'); }
                    else { el.textContent = '0'; el.classList.add('hidden'); }
                }
            } catch (e) { /* ignore inbox errors silently */ }
        }

        // Initial setup
        (function init(){
            // Request Notification permission if not denied
            if (window.Notification && Notification.permission === 'default'){
                try { Notification.requestPermission(); } catch(e) {}
            }
            // fetch start id then start polling
            fetchLatestId().then(()=>{ poll(); pollingTimer = setInterval(poll, pollingInterval); });
            // Start inbox polling independent of invoice poll
            fetchInboxCount(); setInterval(fetchInboxCount, 15000);
        })();

        // clicking bell opens manager panel
        if (notifButton){ notifButton.addEventListener('click', ()=>{ window.location.href = 'manager.php'; }); }

        // Mute/unmute and test controls
        const notifTestButton = document.getElementById('notifTestButton');
        const notifMuteButton = document.getElementById('notifMuteButton');
        const audioEl = document.getElementById('notifAudio');
        const MUTE_KEY = 'autoshop_notif_muted';
        function isMuted(){ return localStorage.getItem(MUTE_KEY) === '1'; }
        function setMuted(v){ localStorage.setItem(MUTE_KEY, v ? '1' : '0'); updateMuteUI(); }
        function updateMuteUI(){ if (!notifMuteButton) return; notifMuteButton.textContent = isMuted() ? '🔇' : '🔈'; notifMuteButton.title = isMuted() ? 'Unmute notifications' : 'Mute notifications'; }
        updateMuteUI();

        async function checkFileExists(url){
            try {
                const res = await fetch(url, { method: 'HEAD', cache: 'no-store' });
                return res.ok;
            } catch (e){ return false; }
        }

        async function testAudio(){
            try {
                if (isMuted()){ showAnimatedNotification('Muted — unmute to hear sound', 0); return; }

                // Prefer DOM audio; try to load a presented source so we can detect issues
                if (audioEl){
                    // quick diagnostics: check mp3 and ogg exist
                    const mp3Ok = await checkFileExists('assets/sounds/notify.mp3');
                    const oggOk = await checkFileExists('assets/sounds/notify.ogg');
                    if (!mp3Ok && !oggOk){
                        showAnimatedNotification('No notify.mp3/notify.ogg found — server fallback will be used', 0);
                    }
                    audioEl.currentTime = 0;
                    await audioEl.play();
                    showAnimatedNotification('Sound played', 0);
                    return;
                }
                // otherwise fall back to WebAudio directly
                playBeep();
                showAnimatedNotification('Sound played (WebAudio fallback)', 0);
            } catch (e){
                console.warn('testAudio error', e);
                showAnimatedNotification('Unable to play sound — check browser autoplay settings or file existence', 0);
            }
        }

        if (notifTestButton) notifTestButton.addEventListener('click', testAudio);
        if (notifMuteButton) notifMuteButton.addEventListener('click', ()=>{ setMuted(!isMuted()); });

        // Expose for console debugging
        window.__invoiceNotifications = { poll, fetchLatestId, testAudio, setMuted };
    })();
</script>

<style>
    /* Sidebar is fixed; ensure main content accounts for it across all sizes */
    main, .container, .max-w-7xl { margin-left: 16rem; }
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