<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','manager'])){
    header('Location: ../admin/index.php'); exit;
}
$title = 'Technicians';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Technicians - AutoShop</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen overflow-auto font-sans antialiased pb-20">
    <?php include __DIR__ . '/../partials/sidebar.php'; ?>
    <main class="min-h-full overflow-auto ml-0 md:ml-64 pt-6 pl-6" role="main">
        <div class="max-w-7xl mx-auto p-6">
            <nav aria-label="Breadcrumb" class="mb-4">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="index.php" class="hover:text-blue-600 transition">Dashboard</a></li>
                    <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                    <li aria-current="page">Technicians</li>
                </ol>
            </nav>
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Technicians</h1>
        <div class="flex items-center gap-3">
            <button id="btnNewTech" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded shadow">Add Technician</button>
            <button id="btnRefresh" class="bg-slate-200 hover:bg-slate-300 text-slate-800 px-3 py-2 rounded shadow">Refresh</button>
        </div>
    </div>

    <div class="bg-white shadow rounded-lg p-4 overflow-x-auto">
        <table id="techTable" class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50"><tr>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Name</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">User</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Email</th>
                <th class="px-4 py-2 text-left text-slate-500 font-medium">Actions</th>
            </tr></thead>
            <tbody class="bg-white"></tbody>
        </table>
    </div>

    <!-- Add Technician Modal -->
    <div id="modalNewTech" class="fixed inset-0 z-50 hidden items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
            <h2 class="text-lg font-semibold mb-4">Add Technician</h2>
            <label class="block text-sm">Name</label>
            <input id="modalName" class="w-full border p-2 rounded mb-3" />
            <label class="block text-sm">Email</label>
            <input id="modalEmail" class="w-full border p-2 rounded mb-3" />
            <div class="flex justify-end gap-2">
                <button id="modalCancel" class="px-4 py-2 rounded bg-slate-200">Cancel</button>
                <button id="modalSave" class="px-4 py-2 rounded bg-blue-600 text-white">Save</button>
            </div>
        </div>
    </div>

    <div id="panel" class="mt-6 bg-white p-4 shadow rounded hidden">
        <h2 class="font-semibold">Payroll & Earnings</h2>
        <div class="mt-3">
            <label class="block text-sm text-slate-600">Select technician</label>
            <select id="selectTech" class="border p-2 rounded w-full"></select>
        </div>
        <div class="mt-3 flex gap-2">
            <input type="date" id="startDate" class="border p-2 rounded" />
            <input type="date" id="endDate" class="border p-2 rounded" />
            <button id="btnCompute" class="bg-green-600 text-white px-4 py-2 rounded">Compute</button>
        </div>
        <div id="earningsResult" class="mt-4"></div>

        <h3 class="mt-6 font-semibold">Payroll Rules</h3>
        <div id="rulesList" class="mt-2"></div>
        <div class="mt-3">
            <h4 class="font-medium">Add Rule</h4>
            <select id="ruleType" class="border p-2 rounded mt-2"><option value="percentage">Percentage (%) of labor</option><option value="fixed_per_invoice">Fixed per invoice</option></select>
            <input id="ruleValue" class="border p-2 rounded mt-2" placeholder="Value (e.g., 10 for 10%)" />
            <input id="ruleDesc" class="border p-2 rounded mt-2" placeholder="Description (optional)" />
            <div class="mt-2"><button id="btnAddRule" class="bg-blue-600 text-white px-4 py-2 rounded">Add Rule</button></div>
        </div>
    </div>
</div>
</main>

<script>
async function fetchTechs(){
    const res = await fetch('api_technicians.php?action=list');
    const data = await res.json(); if (!data.success) return;
    const tbody = document.querySelector('#techTable tbody'); tbody.innerHTML='';
    const sel = document.getElementById('selectTech'); sel.innerHTML='';
    data.technicians.forEach(t=>{
        const tr = document.createElement('tr');
        tr.className = 'border-b';
        tr.innerHTML = `
            <td class="px-4 py-3">${escapeHtml(t.name)}</td>
            <td class="px-4 py-3">${escapeHtml(t.username||'')}</td>
            <td class="px-4 py-3">${escapeHtml(t.email||'')}</td>
            <td class="px-4 py-3">
                <button data-id="${t.id}" data-name="${escapeHtml(t.name)}" data-email="${escapeHtml(t.email||'')}" class="btnEdit bg-yellow-400 px-3 py-1 rounded">Edit</button>
                <button data-id="${t.id}" class="btnDel bg-red-500 px-3 py-1 rounded text-white">Delete</button>
            </td>`;
        tbody.appendChild(tr);
        const opt = document.createElement('option'); opt.value = t.id; opt.textContent = t.name; sel.appendChild(opt);
    });
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

fetchTechs();

// Modal handlers
const modal = document.getElementById('modalNewTech');
const modalName = document.getElementById('modalName');
const modalEmail = document.getElementById('modalEmail');
let editId = null;
document.getElementById('btnNewTech').addEventListener('click', ()=>{
    editId = null; modalName.value = ''; modalEmail.value = ''; modal.classList.remove('hidden');
});
document.getElementById('modalCancel').addEventListener('click', ()=>{ modal.classList.add('hidden'); });
document.getElementById('modalSave').addEventListener('click', ()=>{
    const name = modalName.value.trim(); const email = modalEmail.value.trim();
    if (!name) return alert('Name required');
    const params = new URLSearchParams({name, email});
    if (editId) params.append('id', editId);
    const action = editId ? 'update' : 'create';
    fetch('api_technicians.php?action='+action, {method:'POST', body: params}).then(r=>r.json()).then(d=>{ if (d.success){ fetchTechs(); modal.classList.add('hidden'); } else alert(d.message||'Failed'); });
});

// Refresh
document.getElementById('btnRefresh').addEventListener('click', fetchTechs);

// Delegate delete/edit
document.querySelector('#techTable').addEventListener('click', (e)=>{
    if (e.target.classList.contains('btnDel')){
        const id = e.target.getAttribute('data-id'); if (!confirm('Delete?')) return;
        fetch('api_technicians.php?action=delete',{method:'POST', body:new URLSearchParams({id})}).then(r=>r.json()).then(d=>{ if (d.success) fetchTechs(); else alert(d.message||'Failed'); });
    }
    if (e.target.classList.contains('btnEdit')){
        const id = e.target.getAttribute('data-id'); const name = e.target.getAttribute('data-name'); const email = e.target.getAttribute('data-email');
        editId = id; modalName.value = name; modalEmail.value = email; modal.classList.remove('hidden');
    }
});

// Show panel
document.getElementById('selectTech').addEventListener('change', ()=> loadRules());
document.getElementById('btnCompute').addEventListener('click', async ()=>{
    const tech = document.getElementById('selectTech').value; if (!tech) return alert('Select');
    const start = document.getElementById('startDate').value; const end = document.getElementById('endDate').value;
    const res = await fetch('api_technicians.php?action=compute_earnings&technician_id='+encodeURIComponent(tech)+(start?('&start='+encodeURIComponent(start)):'')+(end?('&end='+encodeURIComponent(end)):''));
    const d = await res.json(); if (!d.success) return alert(d.message||'Failed');
    document.getElementById('earningsResult').innerHTML = `<div>Total labor: <strong>${d.total_labor}</strong></div><div>Total earned: <strong>${d.total_earned}</strong></div>`;
    let html = '<h4 class="mt-4">Details</h4><table class="w-full text-sm"><thead><tr><th>ID</th><th>Labor</th><th>Earnings</th></tr></thead><tbody>';
    d.details.forEach(row=> html += `<tr><td>${row.invoice_id}</td><td>${row.labor}</td><td>${row.earnings}</td></tr>`);
    html += '</tbody></table>'; document.getElementById('earningsResult').innerHTML += html;
});

// Rules
async function loadRules(){
    const tech = document.getElementById('selectTech').value; if (!tech) return;
    const res = await fetch('api_technicians.php?action=list_rules&technician_id='+encodeURIComponent(tech)); const d = await res.json(); if (!d.success) return;
    const container = document.getElementById('rulesList'); container.innerHTML = '';
    d.rules.forEach(r=>{ const el = document.createElement('div'); el.className='p-2 border rounded mb-2'; el.innerHTML = `<div><strong>${r.rule_type}</strong> — ${r.value} ${r.description?(' — '+r.description):''}</div>`; container.appendChild(el); });
    // show panel
    document.getElementById('panel').classList.remove('hidden');
}

document.getElementById('btnAddRule').addEventListener('click', ()=>{
    const tech = document.getElementById('selectTech').value; if (!tech) return alert('Select tech');
    const type = document.getElementById('ruleType').value; const value = document.getElementById('ruleValue').value; const desc = document.getElementById('ruleDesc').value;
    fetch('api_technicians.php?action=add_rule',{method:'POST', body:new URLSearchParams({technician_id:tech, rule_type:type, value, description:desc})}).then(r=>r.json()).then(d=>{ if (d.success) loadRules(); else alert(d.message||'Failed'); });
});


</script>

    </main>
    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>