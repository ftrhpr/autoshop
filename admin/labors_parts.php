<?php
require '../config.php';

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}

// Server-side fallback (non-AJAX): handle POST actions so forms still work without JS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['type'])) {
    $type = $_POST['type'];
    $action = $_POST['action'] ?? '';
    $table = $type === 'part' ? 'parts' : 'labors';

    try {
        if ($action === 'add') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $default_price = (float)($_POST['default_price'] ?? 0);
            if ($name === '') throw new Exception('Name is required');
            $stmt = $pdo->prepare("INSERT INTO $table (name, description, default_price, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $description, $default_price, $_SESSION['user_id']]);
            $success = ucfirst($type) . ' added successfully';
        } elseif ($action === 'edit') {
            $id = (int)($_POST['id'] ?? 0);
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $default_price = (float)($_POST['default_price'] ?? 0);
            if ($id <= 0) throw new Exception('Invalid id');
            $stmt = $pdo->prepare("UPDATE $table SET name = ?, description = ?, default_price = ? WHERE id = ?");
            $stmt->execute([$name, $description, $default_price, $id]);
            $success = ucfirst($type) . ' updated successfully';
        } elseif ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare("DELETE FROM $table WHERE id = ?");
            $stmt->execute([$id]);
            $success = ucfirst($type) . ' deleted successfully';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// We'll load data via API on the client side; but provide initial server-rendered data for noscript users
$labors = [];
$parts = [];
try {
    $labors = $pdo->query("SELECT * FROM labors ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $parts = $pdo->query("SELECT * FROM parts ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // If DB not available, leave arrays empty; client-side will handle gracefully
}
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Labors & Parts — Auto Shop</title>
<script src="https://cdn.tailwindcss.com"></script>
<style>
    .modal { display:none; position:fixed; inset:0; align-items:center; justify-content:center; background:rgba(0,0,0,0.45); z-index:60 }
    .modal.show { display:flex; }
    .toast { position:fixed; right:1rem; top:1rem; z-index:70 }
</style>
<script>
window.testAddPartHandler = async function(btn){
    try{
        alert('Handler fired');
        const debug = document.getElementById('debug-output');
        if(debug) debug.textContent = 'Handler fired\n' + (debug.textContent || '');

        // check session
        const st = await fetch('api_status.php');
        const stJson = await st.json().catch(()=>null);
        if (!st.ok || !stJson || !stJson.success){
            alert('API status failed: ' + (stJson && stJson.message ? stJson.message : st.status));
            if(debug) debug.textContent += 'API status: ' + JSON.stringify(stJson) + '\n';
            return;
        }

        // send add request
        const payload = { action: 'add', type: 'part', name: 'Inline Test', description: 'inline', default_price: 1.00, debug: true };
        if(debug) debug.textContent += 'Sending: ' + JSON.stringify(payload) + '\n';
        const r = await fetch('api_labors_parts.php', { method: 'POST', headers: {'Content-Type': 'application/json'}, body: JSON.stringify(payload) });
        const j = await r.json().catch(()=>null);
        alert('Response: ' + JSON.stringify(j));
        if(debug) debug.textContent += 'Response: ' + JSON.stringify(j) + '\n';
    }catch(e){
        alert('Handler error: ' + e.message);
        const debug = document.getElementById('debug-output'); if(debug) debug.textContent += 'Handler error: ' + e.message + '\n';
    }
};
</script>
</head>
<body class="bg-gray-100 min-h-screen font-sans antialiased">
<?php include '../partials/sidebar.php'; ?>

<div class="ml-0 md:ml-64 p-6">
    <div class="max-w-7xl mx-auto">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold">Labors & Parts</h1>
            <div class="flex items-center space-x-3">
                <input id="global-search" placeholder="Search labors / parts" class="px-3 py-2 border rounded-md" />
                <button id="export-labors" class="px-3 py-2 bg-gray-200 rounded-md">Export Labors</button>
                <button id="export-parts" class="px-3 py-2 bg-gray-200 rounded-md">Export Parts</button>
                <button id="test-api" class="px-3 py-2 bg-yellow-200 rounded-md">Test API</button>
                <button id="test-add-part" onclick="testAddPartHandler(this)" class="px-3 py-2 bg-green-200 rounded-md">Test Add Part</button>
            </div>
            <pre id="debug-output" class="mt-2 p-2 bg-gray-100 rounded text-sm" style="max-height:6rem; overflow:auto"></pre>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Labors card -->
            <section class="bg-white shadow rounded-lg p-4">
                <h2 class="font-semibold mb-3">Labors</h2>

                <form id="add-labor-form" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <input type="text" name="name" placeholder="Name" required class="border p-2 rounded" />
                    <input type="text" name="description" placeholder="Description" class="border p-2 rounded" />
                    <div class="flex items-center space-x-2">
                        <input type="number" name="default_price" placeholder="Price" step="0.01" class="border p-2 rounded w-full" />
                        <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded">Add</button>
                    </div>
                </form>

                <div id="labors-list" class="overflow-auto max-h-[60vh]">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-left">Name</th>
                                <th class="p-2 text-left">Description</th>
                                <th class="p-2 text-left">Price</th>
                                <th class="p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="labors-tbody">
                            <?php foreach ($labors as $l): ?>
                            <tr data-id="<?php echo $l['id']; ?>">
                                <td class="p-2"><?php echo htmlspecialchars($l['name']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($l['description']); ?></td>
                                <td class="p-2"><?php echo number_format($l['default_price'],2); ?></td>
                                <td class="p-2 text-right">
                                    <button class="edit-btn text-indigo-600" data-type="labor" data-id="<?php echo $l['id']; ?>" data-name="<?php echo htmlspecialchars($l['name']); ?>" data-description="<?php echo htmlspecialchars($l['description']); ?>" data-price="<?php echo $l['default_price']; ?>">Edit</button>
                                    <button class="delete-btn text-red-600 ml-3" data-type="labor" data-id="<?php echo $l['id']; ?>">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Parts card -->
            <section class="bg-white shadow rounded-lg p-4">
                <h2 class="font-semibold mb-3">Parts</h2>

                <form id="add-part-form" class="grid grid-cols-1 md:grid-cols-3 gap-3 mb-4">
                    <input type="text" name="name" placeholder="Name" required class="border p-2 rounded" />
                    <input type="text" name="description" placeholder="Description" class="border p-2 rounded" />
                    <div class="flex items-center space-x-2">
                        <input type="number" name="default_price" placeholder="Price" step="0.01" class="border p-2 rounded w-full" />
                        <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded">Add</button>
                    </div>
                </form>

                <div id="parts-list" class="overflow-auto max-h-[60vh]">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="p-2 text-left">Name</th>
                                <th class="p-2 text-left">Description</th>
                                <th class="p-2 text-left">Price</th>
                                <th class="p-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="parts-tbody">
                            <?php foreach ($parts as $p): ?>
                            <tr data-id="<?php echo $p['id']; ?>">
                                <td class="p-2"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td class="p-2"><?php echo htmlspecialchars($p['description']); ?></td>
                                <td class="p-2"><?php echo number_format($p['default_price'],2); ?></td>
                                <td class="p-2 text-right">
                                    <button class="edit-btn text-indigo-600" data-type="part" data-id="<?php echo $p['id']; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>" data-description="<?php echo htmlspecialchars($p['description']); ?>" data-price="<?php echo $p['default_price']; ?>">Edit</button>
                                    <button class="delete-btn text-red-600 ml-3" data-type="part" data-id="<?php echo $p['id']; ?>">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div id="edit-modal" class="modal" aria-hidden="true">
    <div class="bg-white w-full max-w-xl rounded-md shadow p-4">
        <h3 id="modal-title" class="font-semibold mb-2">Edit Item</h3>
        <form id="modal-form" class="grid grid-cols-1 gap-3">
            <input type="hidden" name="id" id="modal-id">
            <input type="hidden" name="type" id="modal-type">
            <div>
                <label class="block text-sm">Name</label>
                <input id="modal-name" name="name" required class="border p-2 w-full rounded" />
            </div>
            <div>
                <label class="block text-sm">Description</label>
                <input id="modal-description" name="description" class="border p-2 w-full rounded" />
            </div>
            <div>
                <label class="block text-sm">Price</label>
                <input id="modal-price" name="default_price" type="number" step="0.01" class="border p-2 w-full rounded" />
            </div>
            <div class="flex justify-end space-x-2 mt-2">
                <button type="button" id="modal-cancel" class="px-3 py-2 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-3 py-2 bg-blue-600 text-white rounded">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Toasts -->
<div id="toast-container" class="toast"></div>

<script>
const apiUrl = 'api_labors_parts.php';

// Helpers
function toast(message, type = 'success'){
    const div = document.createElement('div');
    div.className = 'mb-2 px-4 py-2 rounded shadow ' + (type === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800');
    div.textContent = message;
    document.getElementById('toast-container').appendChild(div);
    setTimeout(()=>div.remove(), 4000);
}

async function apiPost(payload){
    const res = await fetch(apiUrl, {method:'POST', headers:{'Content-Type':'application/json'}, body: JSON.stringify(payload)});
    let text = await res.text();
    try {
        const json = text ? JSON.parse(text) : {};
        if (!res.ok) throw new Error((json && json.message) ? json.message : ('HTTP ' + res.status));
        return json;
    } catch (err) {
        console.error('apiPost parsing/error:', err, 'raw:', text);
        throw err;
    }
}

function renderRows(type, rows){
    const tbody = document.getElementById(type === 'labor' ? 'labors-tbody' : 'parts-tbody');
    tbody.innerHTML = '';
    rows.forEach(r => {
        const tr = document.createElement('tr');
        tr.dataset.id = r.id;
        tr.innerHTML = `
            <td class="p-2">${escapeHtml(r.name)}</td>
            <td class="p-2">${escapeHtml(r.description ?? '')}</td>
            <td class="p-2">${Number(r.default_price).toFixed(2)}</td>
            <td class="p-2 text-right">
                <button class="edit-btn text-indigo-600" data-type="${type}" data-id="${r.id}" data-name="${escapeAttr(r.name)}" data-description="${escapeAttr(r.description ?? '')}" data-price="${r.default_price}">Edit</button>
                <button class="delete-btn text-red-600 ml-3" data-type="${type}" data-id="${r.id}">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function escapeHtml(s){ return (s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'":"'"})[c] ); }
function escapeAttr(s){ return (s||'').replace(/"/g, '&quot;'); }

async function loadList(type){
    const res = await fetch(apiUrl + '?type=' + (type === 'part' ? 'part' : 'labor'));
    const json = await res.json();
    if(json.success){ renderRows(type, json.data); attachRowEvents(); }
}

function attachRowEvents(){
    document.querySelectorAll('.edit-btn').forEach(btn => btn.removeEventListener('click', onEditClick));
    document.querySelectorAll('.edit-btn').forEach(btn => btn.addEventListener('click', onEditClick));
    document.querySelectorAll('.delete-btn').forEach(btn => btn.removeEventListener('click', onDeleteClick));
    document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', onDeleteClick));
}

function onEditClick(e){
    const btn = e.currentTarget;
    document.getElementById('modal-type').value = btn.dataset.type;
    document.getElementById('modal-id').value = btn.dataset.id;
    document.getElementById('modal-name').value = btn.dataset.name || '';
    document.getElementById('modal-description').value = btn.dataset.description || '';
    document.getElementById('modal-price').value = btn.dataset.price || '';
    document.getElementById('modal-title').textContent = 'Edit ' + (btn.dataset.type === 'labor' ? 'Labor' : 'Part');
    document.getElementById('edit-modal').classList.add('show');
}

async function onDeleteClick(e){
    const btn = e.currentTarget;
    if (!confirm('Delete this item?')) return;
    const type = btn.dataset.type;
    const id = btn.dataset.id;
    const res = await apiPost({action:'delete', type, id});
    if(res.success){ toast('Deleted'); loadList(type); } else toast(res.message || 'Error', 'error');
}

// Form handlers
document.getElementById('add-labor-form').addEventListener('submit', async function(e){
    e.preventDefault();
    const fd = new FormData(this);
    const payload = {action:'add', type:'labor', name:fd.get('name'), description:fd.get('description'), default_price:fd.get('default_price')};
    const res = await apiPost(payload);
    if(res.success){ toast('Labor added'); this.reset(); loadList('labor'); } else toast(res.message || 'Error','error');
});

document.getElementById('add-part-form').addEventListener('submit', async function(e){
    e.preventDefault();
    const submitBtn = this.querySelector('button[type="submit"]');
    submitBtn.disabled = true;
    try {
        const fd = new FormData(this);
        const payload = {action:'add', type:'part', name:fd.get('name'), description:fd.get('description'), default_price:fd.get('default_price')};
        console.log('Adding part payload:', payload);
        const res = await apiPost(payload);
        console.log('Add part response:', res);
        if(res && res.success){
            toast('Part added');
            this.reset();
            await loadList('part');
        } else {
            toast(res && res.message ? res.message : 'Error adding part', 'error');
        }
    } catch (err) {
        console.error('Add part error:', err);
        toast('Network or server error', 'error');
    } finally {
        submitBtn.disabled = false;
    }
});

// Modal save
document.getElementById('modal-form').addEventListener('submit', async function(e){
    e.preventDefault();
    const f = Object.fromEntries(new FormData(this));
    const payload = {action:'edit', type:f.type, id:f.id, name:f.name, description:f.description, default_price:f.default_price};
    const res = await apiPost(payload);
    if(res.success){ toast('Updated'); document.getElementById('edit-modal').classList.remove('show'); loadList(f.type); } else toast(res.message || 'Error','error');
});

// Modal cancel
document.getElementById('modal-cancel').addEventListener('click', function(){ document.getElementById('edit-modal').classList.remove('show'); });

// Export buttons
document.getElementById('export-labors').addEventListener('click', function(){ window.location = apiUrl + '?action=export&type=labors'; });
document.getElementById('export-parts').addEventListener('click', function(){ window.location = apiUrl + '?action=export&type=parts'; });

// Test API button
document.getElementById('test-api').addEventListener('click', async function(){
    try {
        const res = await fetch('api_status.php');
        const json = await res.json();
        if (res.ok && json.success) {
            toast('API OK — user ' + json.user_id + ' role: ' + (json.role || 'none'));
        } else {
            toast('API error: ' + (json.message || 'unknown'), 'error');
            console.error('API status response:', json);
        }
    } catch (err) {
        console.error('API status fetch error:', err);
        toast('API request failed — check server and session', 'error');
    }
});

// Test Add Part button (sends debug payload to API and prints response)
document.getElementById('test-add-part').addEventListener('click', async function(){
    alert('Test Add clicked');
    const btn = this;
    btn.disabled = true;
    debugLog('Starting test add...');
    toast('Starting test add...');
    try {
        // Verify session/status first
        const st = await fetch('api_status.php');
        const stJson = await st.json().catch(()=>null);
        debugLog('api_status: ' + (stJson ? JSON.stringify(stJson) : 'no-json'));
        if (!st.ok || !stJson || !stJson.success) {
            toast('API status check failed: ' + (stJson && stJson.message ? stJson.message : 'unauthenticated'), 'error');
            console.error('API status:', st.status, stJson);
            btn.disabled = false;
            return;
        }

        const payload = { action: 'add', type: 'part', name: 'Debug Part (JS Test)', description: 'Debug from test button', default_price: 1.00, debug: true };
        debugLog('Sending payload: ' + JSON.stringify(payload));
        console.log('Test Add Part payload:', payload);
        const res = await apiPost(payload);
        debugLog('Response: ' + JSON.stringify(res));
        console.log('Test Add Part response:', res);
        if (res && res.success) {
            toast('Test Add Part succeeded');
            if (res.debug) {
                console.log('Server debug:', res.debug);
                debugLog('Server debug: ' + JSON.stringify(res.debug));
            }
            await loadList('part');
        } else {
            toast('Test Add Part failed: ' + (res && res.message ? res.message : 'unknown'), 'error');
            console.error('Test Add response:', res);
        }
    } catch (err) {
        console.error('Test Add Part error:', err);
        debugLog('Error: ' + (err && err.message ? err.message : err));
        toast('Test Add request failed: ' + (err.message || 'network'), 'error');
    } finally {
        btn.disabled = false;
    }
});

function debugLog(msg){
    const el = document.getElementById('debug-output');
    if (!el) return;
    const now = new Date().toISOString().substr(11,8);
    el.textContent = now + ' — ' + msg + '\n' + el.textContent;
}

// Search filtering across both lists (simple client-side)
document.getElementById('global-search').addEventListener('input', function(){
    const q = this.value.toLowerCase();
    document.querySelectorAll('#labors-tbody tr, #parts-tbody tr').forEach(tr => {
        const text = tr.textContent.toLowerCase();
        tr.style.display = text.includes(q) ? '' : 'none';
    });
});

// Initial load
(async function(){
    await loadList('labor');
    await loadList('part');
})();
</script>
</body>
</html>

        <div class="max-w-7xl mx-auto">
            <h1 class="text-3xl font-bold mb-6">Labors & Parts Management</h1>

            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Labors Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Labors</h2>
                
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Labor</h3>
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="type" value="labor">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                                    <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Add Labor</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow overflow-hidden rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900">Existing Labors</h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($labors as $labor): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($labor['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($labor['description'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $labor['default_price'] > 0 ? number_format($labor['default_price'], 2) . ' ₾' : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" class="edit-btn text-indigo-600 hover:text-indigo-900 mr-4" data-type="labor" data-id="<?php echo $labor['id']; ?>" data-name="<?php echo htmlspecialchars($labor['name']); ?>" data-description="<?php echo htmlspecialchars($labor['description'] ?? ''); ?>" data-price="<?php echo $labor['default_price']; ?>">Edit</button>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this labor?');">
                                            <input type="hidden" name="type" value="labor">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $labor['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($labors)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No labors found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Parts Section -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Parts</h2>
                
                <div class="bg-white shadow rounded-lg mb-6">
                    <div class="px-4 py-5 sm:p-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">Add New Part</h3>
                        <form method="post" class="space-y-4">
                            <input type="hidden" name="type" value="part">
                            <input type="hidden" name="action" value="add">
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                                    <input type="text" name="name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Description</label>
                                    <input type="text" name="description" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                                    <input type="number" name="default_price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                                </div>
                            </div>
                            <div class="flex justify-end">
                                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Add Part</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="bg-white shadow overflow-hidden rounded-lg">
                    <div class="px-4 py-5 sm:px-6">
                        <h3 class="text-lg font-medium text-gray-900">Existing Parts</h3>
                    </div>
                    <div class="border-t border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Default Price</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($parts as $part): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($part['name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($part['description'] ?? ''); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $part['default_price'] > 0 ? number_format($part['default_price'], 2) . ' ₾' : '-'; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button type="button" class="edit-btn text-indigo-600 hover:text-indigo-900 mr-4" data-type="part" data-id="<?php echo $part['id']; ?>" data-name="<?php echo htmlspecialchars($part['name']); ?>" data-description="<?php echo htmlspecialchars($part['description'] ?? ''); ?>" data-price="<?php echo $part['default_price']; ?>">Edit</button>
                                        <form method="post" class="inline" onsubmit="return confirm('Delete this part?');">
                                            <input type="hidden" name="type" value="part">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?php echo $part['id']; ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($parts)): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-4 text-center text-gray-500">No parts found</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Modal -->
    <div id="edit-modal" class="modal">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900" id="modal-title">Edit Item</h3>
            </div>
            <form method="post" class="p-6">
                <input type="hidden" name="type" id="modal-type">
                <input type="hidden" name="action" id="modal-action" value="edit">
                <input type="hidden" name="id" id="modal-id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Name *</label>
                    <input type="text" name="name" id="modal-name" required class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700">Description</label>
                    <textarea name="description" id="modal-description" rows="3" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500"></textarea>
                </div>
                
                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700">Default Price (₾)</label>
                    <input type="number" name="default_price" id="modal-price" step="0.01" min="0" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                
                <div class="flex justify-end space-x-3">
                    <button type="button" id="modal-cancel" class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 border border-gray-300 rounded-md hover:bg-gray-200">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700">Update</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functionality
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded');
            
            // Edit button functionality
            const editButtons = document.querySelectorAll('.edit-btn');
            console.log('Found edit buttons:', editButtons.length);
            
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    alert('Edit button clicked for: ' + this.getAttribute('data-name'));
                    console.log('Edit button clicked');
                    const type = this.getAttribute('data-type');
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    const description = this.getAttribute('data-description');
                    const price = this.getAttribute('data-price');

                    console.log('Data:', {type, id, name, description, price});

                    document.getElementById('modal-title').textContent = 'Edit ' + (type === 'labor' ? 'Labor' : 'Part');
                    document.getElementById('modal-type').value = type;
                    document.getElementById('modal-id').value = id;
                    document.getElementById('modal-name').value = name;
                    document.getElementById('modal-description').value = description;
                    document.getElementById('modal-price').value = price;

                    document.getElementById('edit-modal').classList.add('show');
                });
            });

            // Modal close functionality
            document.getElementById('modal-cancel').addEventListener('click', function() {
                document.getElementById('edit-modal').classList.remove('show');
            });

            document.getElementById('edit-modal').addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>