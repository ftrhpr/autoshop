<?php
require '../config.php';
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager'])) {
    header('Location: ../login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Item Price Usage</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <?php include '../partials/sidebar.php'; ?>
    <div class="ml-0 md:ml-64 p-6">
        <div class="max-w-6xl mx-auto">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold">Item Price Usage</h1>
                <a href="labors_parts_pro.php" class="bg-blue-600 text-white px-3 py-2 rounded">Back</a>
            </div>

            <div class="bg-white shadow rounded-lg p-6 mb-6">
                <div class="flex gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700">Type</label>
                        <select id="select-type" class="mt-1 block w-48 rounded border-gray-200 p-2">
                            <option value="part">Part</option>
                            <option value="labor">Labor</option>
                        </select>
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700">Item</label>
                        <select id="select-item" class="mt-1 block w-full rounded border-gray-200 p-2"></select>
                    </div>
                    <div>
                        <button id="btn-refresh" class="bg-blue-600 text-white px-4 py-2 rounded">Load Usage</button>
                    </div>
                </div>
            </div>

            <div id="usage-container" class="bg-white shadow rounded-lg p-6 hidden">
                <h2 class="text-lg font-medium mb-4">Usage for <span id="item-name"></span></h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200" id="usage-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Vehicle</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Price (₾)</th>
                                <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Usage Count</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Used</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usage-body" class="bg-white divide-y divide-gray-200"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

<script>
    const selectType = document.getElementById('select-type');
    const selectItem = document.getElementById('select-item');
    const btnRefresh = document.getElementById('btn-refresh');
    const usageContainer = document.getElementById('usage-container');
    const usageBody = document.getElementById('usage-body');
    const itemNameEl = document.getElementById('item-name');

    function fetchItems() {
        const type = selectType.value;
        fetch('api_labors_parts.php?type=' + encodeURIComponent(type))
            .then(r => r.json())
            .then(resp => {
                const data = resp && resp.data ? resp.data : [];
                selectItem.innerHTML = data.map(d => `<option value="${d.id}">${escapeHtml(d.name)} (${d.vehicle_make_model || 'no vehicle'})</option>`).join('');
            }).catch(e => console.error(e));
    }

    function loadUsage() {
        const itemId = selectItem.value;
        const type = selectType.value;
        if (!itemId) return;
        fetch('api_labors_parts.php?action=usage_list&item_id=' + encodeURIComponent(itemId) + '&item_type=' + encodeURIComponent(type))
            .then(r => r.json())
            .then(resp => {
                if (!resp.success) return alert('Failed to load usage');
                const rows = resp.data || [];
                itemNameEl.textContent = (resp.item && resp.item.name) ? resp.item.name : '';
                usageContainer.classList.remove('hidden');
                usageBody.innerHTML = rows.map(r => `
                    <tr data-id="${r.id}">
                        <td class="px-4 py-2 text-sm">${r.id}</td>
                        <td class="px-4 py-2 text-sm">${escapeHtml(r.vehicle_make_model || '(any)')}</td>
                        <td class="px-4 py-2 text-right text-sm">${Number(r.price).toFixed(2)}</td>
                        <td class="px-4 py-2 text-right text-sm"><input type="number" class="usage-count p-1 border rounded w-24 text-right" value="${r.usage_count}"></td>
                        <td class="px-4 py-2 text-sm">${r.last_used_at}</td>
                        <td class="px-4 py-2 text-sm">
                            <button class="save-btn bg-green-600 text-white px-2 py-1 rounded mr-2">Save</button>
                            <button class="reset-btn bg-yellow-600 text-white px-2 py-1 rounded mr-2">Reset</button>
                            <button class="delete-btn bg-red-600 text-white px-2 py-1 rounded">Delete</button>
                        </td>
                    </tr>
                `).join('');

                // Attach handlers
                document.querySelectorAll('.save-btn').forEach(btn => btn.addEventListener('click', onSave));
                document.querySelectorAll('.reset-btn').forEach(btn => btn.addEventListener('click', onReset));
                document.querySelectorAll('.delete-btn').forEach(btn => btn.addEventListener('click', onDelete));
            }).catch(e => { console.error(e); alert('Error loading usage'); });
    }

    function onSave(e) {
        const tr = e.target.closest('tr');
        const id = tr.dataset.id;
        const count = tr.querySelector('.usage-count').value || 0;
        fetch('api_labors_parts.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'usage_edit', id: id, usage_count: parseInt(count,10) }) })
            .then(r => r.json()).then(resp => {
                if (!resp.success) return alert('Failed to save');
                alert('Saved');
                loadUsage();
            }).catch(e => alert('Error saving'));
    }

    function onReset(e) {
        const tr = e.target.closest('tr');
        const id = tr.dataset.id;
        if (!confirm('Reset usage count to 0?')) return;
        fetch('api_labors_parts.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'usage_edit', id: id, usage_count: 0 }) })
            .then(r => r.json()).then(resp => { if (!resp.success) return alert('Failed to reset'); alert('Reset'); loadUsage(); }).catch(e => alert('Error resetting'));
    }

    function onDelete(e) {
        const tr = e.target.closest('tr');
        const id = tr.dataset.id;
        if (!confirm('Delete this usage entry?')) return;
        fetch('api_labors_parts.php', { method: 'POST', headers: {'Content-Type':'application/json'}, body: JSON.stringify({ action: 'usage_delete', id: id }) })
            .then(r => r.json()).then(resp => { if (!resp.success) return alert('Failed to delete'); alert('Deleted'); loadUsage(); }).catch(e => alert('Error deleting'));
    }

    selectType.addEventListener('change', fetchItems);
    btnRefresh.addEventListener('click', loadUsage);

    // escape helper
    function escapeHtml(s) { return (s||'').replace(/[&<>"']/g, function (c) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

    // Initial load
    fetchItems();
</script>
</body>
</html>