<?php
// Usage: include 'partials/sidebar.php';
// Determine script directory and app root (handles subfolders)
$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($scriptDir === '/' || $scriptDir === '\\') $scriptDir = '';
$parts = array_values(array_filter(explode('/', $scriptDir), 'strlen'));
if (end($parts) === 'admin') array_pop($parts); // move up if currently in admin folder
$appRoot = '/' . implode('/', $parts);
if ($appRoot === '/') $appRoot = '';

$menu = [
    ['label' => 'New Invoice', 'href' => $appRoot.'/index.php', 'icon' => 'plus', 'permission' => null],
    ['label' => 'Dashboard', 'href' => $appRoot.'/admin/index.php', 'icon' => 'home', 'permission' => null],
    ['label' => 'Invoices', 'href' => $appRoot.'/manager.php', 'icon' => 'file-text', 'permission' => null],
    ['label' => 'Customers', 'href' => $appRoot.'/admin/customers.php', 'icon' => 'users', 'permission' => 'manage_customers'],
    ['label' => 'Export CSV', 'href' => $appRoot.'/admin/export_invoices.php', 'icon' => 'download', 'permission' => 'export_csv'],
    ['label' => 'Users', 'href' => $appRoot.'/admin/index.php', 'icon' => 'user', 'permission' => 'manage_users'],
    ['label' => 'Roles & Permissions', 'href' => $appRoot.'/admin/permissions.php', 'icon' => 'shield', 'permission' => null],
    ['label' => 'Audit Logs', 'href' => $appRoot.'/admin/logs.php', 'icon' => 'clock', 'permission' => 'view_logs']
];

// If current user is a manager, restrict sidebar to only New Invoice and Invoices
if (isset($_SESSION['role']) && $_SESSION['role'] === 'manager') {
    $menu = array_values(array_filter($menu, function($it){
        return in_array($it['label'], ['New Invoice', 'Invoices']);
    }));
}
$logoutHref = $appRoot.'/logout.php';

function svgIcon($name){
    $icons = [
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L2 8v8a2 2 0 002 2h3v-6h6v6h3a2 2 0 002-2V8L10 2z"/></svg>',
        'file-text' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v12a2 2 0 01-2 2z"/></svg>',
        'users' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'download' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.58 9.74-8 10-4.42-.26-8-5-8-10V6l8-4z"/></svg>',
        'clock' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>',
        'plus' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>'
    ];
    return $icons[$name] ?? '';
}
?>

<!-- Sidebar -->
<div class="fixed inset-y-0 left-0 z-40 w-64 bg-slate-800 text-white transform -translate-x-full md:translate-x-0 transition-transform duration-200" id="site-sidebar">
    <div class="h-full flex flex-col">
        <div class="p-4 border-b border-slate-700 flex items-center justify-between">
            <div class="font-bold text-lg">AutoShop</div>
            <button id="closeSidebar" class="md:hidden text-slate-300">✕</button>
        </div>
        <nav class="flex-1 overflow-y-auto p-4 space-y-1">
            <?php foreach ($menu as $item):
                if ($item['permission'] && !function_exists('currentUserCan')) continue;
                if ($item['permission'] && !currentUserCan($item['permission'])) continue;

                $raw = $item['href'];
                if (preg_match('#^https?://#i', $raw)) {
                    $href = $raw;
                } else {
                    // Collapse duplicate segments and ensure leading slash
                    $parts = array_values(array_filter(explode('/', $raw), 'strlen'));
                    $clean = [];
                    foreach ($parts as $p) {
                        if (count($clean) === 0 || end($clean) !== $p) $clean[] = $p;
                    }
                    $href = '/' . implode('/', $clean);
                }

                $isActive = strpos($_SERVER['SCRIPT_NAME'], $href) !== false || basename($_SERVER['SCRIPT_NAME']) === basename($href);
            ?>
            <a href="<?php echo htmlspecialchars($href); ?>" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-700 <?php echo $isActive ? 'bg-yellow-500 text-slate-900 font-semibold' : 'text-slate-200'; ?>">
                <span class="w-5 h-5"><?php echo svgIcon($item['icon']); ?></span>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-700">
            <?php if (isset($_SESSION['username'])): ?>
            <div class="flex items-center justify-between mb-4">
                <div class="text-sm text-slate-300">
                    Logged in as: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                </div>
                <!-- Notification Bell -->
                <div class="relative" id="notification-bell">
                    <button onclick="toggleNotifications()" class="text-slate-300 hover:text-white">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6 6 0 10-12 0v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span id="notification-count" class="absolute -top-1 -right-1 bg-red-600 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center hidden">0</span>
                    </button>
                    <div id="notification-dropdown" class="absolute right-0 mt-2 w-80 bg-white rounded-md shadow-lg z-50 hidden">
                        <div class="py-2 text-gray-700">
                            <div class="px-4 py-2 font-semibold">Notifications</div>
                            <div id="notification-list" class="max-h-64 overflow-y-auto">
                                <!-- Notifications will be injected here -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            <a href="<?php echo htmlspecialchars($logoutHref); ?>" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500 text-white text-center">Logout</a>
        </div>
    </div>
</div>

<!-- Mobile overlay and toggle -->
<button id="sidebarToggle" class="fixed bottom-6 right-6 z-50 md:hidden bg-yellow-400 text-slate-900 p-3 rounded-full shadow-lg">☰</button>

<audio id="notification-sound" src="<?php echo $appRoot; ?>/assets/notification.mp3" preload="auto"></audio>

<script>
    const sidebar = document.getElementById('site-sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');
    if (toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
    if (closeBtn) closeBtn.addEventListener('click', () => sidebar.classList.add('-translate-x-full'));

    function toggleNotifications() {
        document.getElementById('notification-dropdown').classList.toggle('hidden');
    }

    function fetchNotifications() {
        fetch('<?php echo $appRoot; ?>/admin/api_notifications.php?action=get_unread')
            .then(response => response.json())
            .then(data => {
                const countEl = document.getElementById('notification-count');
                const listEl = document.getElementById('notification-list');
                const sound = document.getElementById('notification-sound');
                
                if (data.length > 0) {
                    const currentCount = parseInt(countEl.textContent, 10);
                    if (data.length > currentCount) {
                        sound.play().catch(e => console.error("Audio play failed:", e));
                    }

                    countEl.textContent = data.length;
                    countEl.classList.remove('hidden');
                    listEl.innerHTML = '';

                    data.forEach(notification => {
                        const item = document.createElement('a');
                        item.href = `<?php echo $appRoot; ?>/view_invoice.php?id=${notification.invoice_id}`;
                        item.className = 'block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100';
                        item.textContent = notification.message;
                        item.onclick = (e) => {
                            e.preventDefault();
                            markAsRead(notification.id, item.href);
                        };
                        listEl.appendChild(item);
                    });
                } else {
                    countEl.classList.add('hidden');
                    listEl.innerHTML = '<div class="px-4 py-2 text-sm text-gray-500">No new notifications</div>';
                }
            })
            .catch(error => console.error('Error fetching notifications:', error));
    }

    function markAsRead(id, redirectUrl) {
        const formData = new FormData();
        formData.append('id', id);

        fetch('<?php echo $appRoot; ?>/admin/api_notifications.php?action=mark_as_read', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = redirectUrl;
            }
        })
        .catch(error => console.error('Error marking notification as read:', error));
    }

    // Fetch notifications every 30 seconds
    setInterval(fetchNotifications, 30000);
    // Initial fetch
    fetchNotifications();

</script>

<style>
    /* Ensure main content has left margin on md+ screens so sidebar doesn't overlap */
    @media (min-width: 768px) {
        main, .container, .max-w-7xl { margin-left: 16rem; }
    }
</style>