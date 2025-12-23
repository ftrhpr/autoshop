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
    ['label' => 'Users', 'href' => $appRoot . '/admin/index.php', 'icon' => 'user', 'permission' => 'manage_users'],
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

<!-- Sidebar (desktop) & Off-canvas (mobile) -->
<div class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-800 text-white transform -translate-x-full md:translate-x-0 transition-all duration-300 shadow-lg" id="site-sidebar" aria-hidden="false">
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
            <button id="closeSidebar" class="text-slate-300 hover:text-white">✕</button>
            <button id="collapseSidebar" class="hidden md:block text-slate-300 hover:text-white ml-2" title="Collapse sidebar">◀</button>
        </div>

        <nav class="flex-1 overflow-y-auto p-4 space-y-1" aria-label="Primary">
            <?php foreach ($menu as $item):
                if ($item['permission'] && !function_exists('currentUserCan')) continue;
                if ($item['permission'] && !currentUserCan($item['permission'])) continue;

                $raw = $item['href'];
                if (preg_match('#^https?://#i', $raw)) {
                    $href = $raw;
                } else {
                    // Collapse duplicate segments and ensure path relative to app root
                    $parts = array_values(array_filter(explode('/', $raw), 'strlen'));
                    $clean = [];
                    foreach ($parts as $p) {
                        if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p;
                    }
                    $href = rtrim($appRoot, '/') . '/' . implode('/', $clean);
                }

                $isActive = strpos($_SERVER['SCRIPT_NAME'], $href) !== false || basename($_SERVER['SCRIPT_NAME']) === basename($href);
            ?>
            <a href="<?php echo htmlspecialchars($href); ?>" class="flex items-center gap-3 px-3 py-3 rounded-lg hover:bg-slate-700 transition-colors duration-200 <?php echo $isActive ? 'bg-yellow-500 text-slate-900 font-semibold shadow-md' : 'text-slate-200'; ?>" title="<?php echo htmlspecialchars($item['label']); ?>">
                <span class="w-5 h-5 flex-shrink-0"><?php echo svgIcon($item['icon']); ?></span>
                <span class="sidebar-text transition-opacity duration-300"><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-700">
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500 text-white text-center">Logout</a>
        </div>
    </div>
</div>

<style>
#site-sidebar.collapsed {
    width: 4rem;
}
#site-sidebar.collapsed .sidebar-text {
    opacity: 0;
    pointer-events: none;
}
#site-sidebar.collapsed a {
    justify-content: center;
}
</style>

<!-- Mobile: floating menu button -->
<button id="openSidebar" class="md:hidden fixed bottom-4 left-4 z-50 bg-slate-800 text-white p-3 rounded-full shadow-lg" aria-label="Open menu">
    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
</button>

<script>
(function(){
    function open() { 
        document.getElementById('site-sidebar').classList.remove('-translate-x-full'); 
        document.body.classList.add('overflow-hidden');
        const main = document.querySelector('main.ml-0, div.ml-0');
        if (main && window.innerWidth >= 768) {
            main.classList.add('md:ml-64');
            main.classList.remove('md:ml-0');
        }
    }
    function close() { 
        document.getElementById('site-sidebar').classList.add('-translate-x-full'); 
        document.body.classList.remove('overflow-hidden');
        const main = document.querySelector('main.ml-0, div.ml-0');
        if (main && window.innerWidth >= 768) {
            main.classList.remove('md:ml-64');
            main.classList.add('md:ml-0');
        }
    }
    var openBtn = document.getElementById('openSidebar');
    var closeBtn = document.getElementById('closeSidebar');
    var collapseBtn = document.getElementById('collapseSidebar');
    if (openBtn) openBtn.addEventListener('click', open);
    if (closeBtn) closeBtn.addEventListener('click', close);
    if (collapseBtn) collapseBtn.addEventListener('click', function(){
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
    // Close on escape
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') close(); });
    // Close when tapping outside on mobile
    document.addEventListener('click', function(e){
        var sidebar = document.getElementById('site-sidebar');
        if (!sidebar.contains(e.target) && !openBtn.contains(e.target) && window.innerWidth < 768) close();
    });
})();
</script>

<!-- Mobile overlay and toggle -->
<button id="sidebarToggle" class="fixed bottom-6 right-6 z-50 md:hidden bg-yellow-400 text-slate-900 p-3 rounded-full shadow-lg">☰</button>

<script>
    const sidebar = document.getElementById('site-sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');
    if (toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
    if (closeBtn) closeBtn.addEventListener('click', () => sidebar.classList.add('-translate-x-full'));

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

        // Initial setup
        (function init(){
            // Request Notification permission if not denied
            if (window.Notification && Notification.permission === 'default'){
                try { Notification.requestPermission(); } catch(e) {}
            }
            // fetch start id then start polling
            fetchLatestId().then(()=>{ poll(); pollingTimer = setInterval(poll, pollingInterval); });
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