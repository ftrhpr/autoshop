<?php
// Usage: include 'partials/sidebar.php';
// Determine base path for links
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
if ($base === '/') $base = '';
$current = basename($_SERVER['SCRIPT_NAME']);

$menu = [
    ['label' => 'Dashboard', 'href' => $base.'/admin/index.php', 'icon' => 'home', 'permission' => null],
    ['label' => 'Invoices', 'href' => $base.'/manager.php', 'icon' => 'file-text', 'permission' => null],
    ['label' => 'Customers', 'href' => $base.'/admin/customers.php', 'icon' => 'users', 'permission' => 'manage_customers'],
    ['label' => 'Export CSV', 'href' => $base.'/admin/export_invoices.php', 'icon' => 'download', 'permission' => 'export_csv'],
    ['label' => 'Users', 'href' => $base.'/admin/index.php', 'icon' => 'user', 'permission' => 'manage_users'],
    ['label' => 'Roles & Permissions', 'href' => $base.'/admin/permissions.php', 'icon' => 'shield', 'permission' => null],
    ['label' => 'Audit Logs', 'href' => $base.'/admin/logs.php', 'icon' => 'clock', 'permission' => 'view_logs']
];

function svgIcon($name){
    $icons = [
        'home' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path d="M10 2L2 8v8a2 2 0 002 2h3v-6h6v6h3a2 2 0 002-2V8L10 2z"/></svg>',
        'file-text' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 4H7a2 2 0 01-2-2V5a2 2 0 012-2h5l5 5v12a2 2 0 01-2 2z"/></svg>',
        'users' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87M16 7a4 4 0 11-8 0 4 4 0 018 0z"/></svg>',
        'download' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5 5-5M12 15V3"/></svg>',
        'user' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A9 9 0 0112 15a9 9 0 016.879 2.804M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>',
        'shield' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-width="2" stroke-linecap="round" stroke-linejoin="round" d="M12 2l8 4v6c0 5-3.58 9.74-8 10-4.42-.26-8-5-8-10V6l8-4z"/></svg>',
        'clock' => '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3M12 22a10 10 0 110-20 10 10 0 010 20z"/></svg>'
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
                $isActive = strpos($_SERVER['SCRIPT_NAME'], $item['href']) !== false || basename($_SERVER['SCRIPT_NAME']) === basename($item['href']);
            ?>
            <a href="<?php echo htmlspecialchars($item['href']); ?>" class="flex items-center gap-3 px-3 py-2 rounded hover:bg-slate-700 <?php echo $isActive ? 'bg-yellow-500 text-slate-900 font-semibold' : 'text-slate-200'; ?>">
                <span class="w-5 h-5"><?php echo svgIcon($item['icon']); ?></span>
                <span><?php echo htmlspecialchars($item['label']); ?></span>
            </a>
            <?php endforeach; ?>
        </nav>

        <div class="p-4 border-t border-slate-700">
            <a href="<?php echo $base; ?>/logout.php" class="block px-3 py-2 rounded bg-red-600 hover:bg-red-500 text-white text-center">Logout</a>
        </div>
    </div>
</div>

<!-- Mobile overlay and toggle -->
<button id="sidebarToggle" class="fixed bottom-6 right-6 z-50 md:hidden bg-yellow-400 text-slate-900 p-3 rounded-full shadow-lg">☰</button>

<script>
    const sidebar = document.getElementById('site-sidebar');
    const toggle = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('closeSidebar');
    if (toggle) toggle.addEventListener('click', () => sidebar.classList.toggle('-translate-x-full'));
    if (closeBtn) closeBtn.addEventListener('click', () => sidebar.classList.add('-translate-x-full'));
</script>

<style>
    /* Ensure main content has left padding on md+ screens */
    @media (min-width: 768px) {
        body { padding-left: 16rem; }
    }
</style>