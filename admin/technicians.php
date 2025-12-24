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
    <!-- Use a compiled local Tailwind CSS build in production instead of the CDN -->
    <link rel="stylesheet" href="../dist/output.css">
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
                    <th class="px-4 py-2 text-left text-slate-500 font-medium">Earnings (30d)</th>
            </tr></thead>
            <tbody></tbody>
        <div id="modalNewTech" class="bg-white rounded-lg shadow-lg max-w-md w-full p-6 hidden">
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
document.addEventListener('DOMContentLoaded', function(){

async function fetchTechs(){
    const res = await fetch('api_technicians.php?action=list');
    const data = await res.json(); if (!data.success) return;
    let tbody = document.querySelector('#techTable tbody');
    if (!tbody){
        const table = document.getElementById('techTable'); if (!table) return;
        tbody = table.querySelector('tbody') || document.createElement('tbody');
        if (!table.contains(tbody)) table.appendChild(tbody);
    }
    tbody.innerHTML='';
    const sel = document.getElementById('selectTech'); if (sel) sel.innerHTML='';
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
                <button data-id="${t.id}" class="btnCompute ml-2 bg-green-600 text-white px-2 py-1 rounded">Compute (30d)</button>
            </td>
            <td class="px-4 py-3 text-slate-700" id="earnings-${t.id}">—</td>`;
        tbody.appendChild(tr);
        if (sel){ const opt = document.createElement('option'); opt.value = t.id; opt.textContent = t.name; sel.appendChild(opt); }
    });
}

function escapeHtml(s){ return String(s||'').replace(/[&<>\"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','\"':'&quot;',"'":"&#39;"})[c]); }

fetchTechs();

// Modal handlers
const modal = document.getElementById('modalNewTech');
const modalName = document.getElementById('modalName');
const modalEmail = document.getElementById('modalEmail');
let editId = null;
const btnNew = document.getElementById('btnNewTech');
if (btnNew){
    btnNew.addEventListener('click', ()=>{
        editId = null; if (modalName) modalName.value = ''; if (modalEmail) modalEmail.value = ''; if (modal) modal.classList.remove('hidden');
    });
}
const modalCancel = document.getElementById('modalCancel');
if (modalCancel && modal) modalCancel.addEventListener('click', ()=>{ modal.classList.add('hidden'); });
const modalSave = document.getElementById('modalSave');
if (modalSave){
    modalSave.addEventListener('click', ()=>{
        const name = modalName ? modalName.value.trim() : ''; const email = modalEmail ? modalEmail.value.trim() : '';
        if (!name) return alert('Name required');
        const params = new URLSearchParams({name, email});
        if (editId) params.append('id', editId);
        const action = editId ? 'update' : 'create';
        fetch('api_technicians.php?action='+action, {method:'POST', body: params}).then(r=>r.json()).then(d=>{ if (d.success){ fetchTechs(); if (modal) modal.classList.add('hidden'); } else alert(d.message||'Failed'); });
    });
}

// Refresh
const btnRefresh = document.getElementById('btnRefresh'); if (btnRefresh) btnRefresh.addEventListener('click', fetchTechs);

// Delegate delete/edit/compute for rows
const techTable = document.getElementById('techTable');
if (techTable) techTable.addEventListener('click', (e)=>{
    if (e.target.classList.contains('btnDel')){
        const id = e.target.getAttribute('data-id'); if (!confirm('Delete?')) return;
        fetch('api_technicians.php?action=delete',{method:'POST', body:new URLSearchParams({id})}).then(r=>r.json()).then(d=>{ if (d.success) fetchTechs(); else alert(d.message||'Failed'); });
    }
    if (e.target.classList.contains('btnEdit')){
        const id = e.target.getAttribute('data-id'); const name = e.target.getAttribute('data-name'); const email = e.target.getAttribute('data-email');
        editId = id; if (modalName) modalName.value = name; if (modalEmail) modalEmail.value = email; if (modal) modal.classList.remove('hidden');
    }
    if (e.target.classList.contains('btnCompute')){
        const id = e.target.getAttribute('data-id');
        computeForTech(id);
    }
});

// Show panel
const selectTech = document.getElementById('selectTech'); if (selectTech) selectTech.addEventListener('change', ()=> loadRules());
const btnCompute = document.getElementById('btnCompute'); if (btnCompute) btnCompute.addEventListener('click', async ()=>{
    const tech = selectTech ? selectTech.value : null; if (!tech) return alert('Select');
    const start = document.getElementById('startDate') ? document.getElementById('startDate').value : ''; const end = document.getElementById('endDate') ? document.getElementById('endDate').value : '';
    const d = await res.json(); if (!d.success) return alert(d.message||'Failed');
    const earningsResult = document.getElementById('earningsResult');

    // Display summary totals in modern cards
    if (earningsResult) {
        earningsResult.innerHTML = `
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
                    <p class="text-sm font-medium text-blue-800">Total Net Labor</p>
                    <p class="text-2xl font-bold text-blue-900">${(d.total_labor||0).toFixed(2)} ₾</p>
                </div>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <p class="text-sm font-medium text-green-800">Total Earned</p>
                    <p class="text-2xl font-bold text-green-900">${(d.total_earned||0).toFixed(2)} ₾</p>
                </div>
                <div class="bg-gray-50 p-4 rounded-lg border border-gray-200">
                    <p class="text-sm font-medium text-gray-800">Invoices Worked On</p>
                    <p class="text-2xl font-bold text-gray-900">${d.invoice_count || 0}</p>
                </div>
            </div>`;
    }

    // Build the modern details table
    let tableHtml = `
        <h4 class="text-lg font-semibold text-gray-800 mb-2">Earnings Breakdown</h4>
        <div class="overflow-hidden rounded-lg border border-gray-200 shadow-sm">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Invoice ID</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Raw Labor</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Global Disc. %</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Labor</th>
                        <th scope="col" class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Earnings</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">`;

    if (d.details && d.details.length > 0) {
        d.details.forEach((row, index) => {
            const bgColor = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
            tableHtml += `
                <tr class="${bgColor}">
                    <td class="px-4 py-3 whitespace-nowrap font-medium text-gray-900">
                        <a href="../index.php?print_id=${row.invoice_id}" target="_blank" class="text-blue-600 hover:underline">#${row.invoice_id}</a>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-gray-500">${(row.labor_raw||0).toFixed(2)} ₾</td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-red-500">${(row.service_discount_percent||0).toFixed(2)}%</td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-gray-800 font-semibold">${(row.labor_after_discount||0).toFixed(2)} ₾</td>
                    <td class="px-4 py-3 whitespace-nowrap text-right text-green-600 font-bold">${(row.earnings||0).toFixed(2)} ₾</td>
                </tr>`;
        });
    } else {
        tableHtml += '<tr><td colspan="5" class="text-center text-gray-500 py-6">No earnings details found for this period.</td></tr>';
    }

    tableHtml += `
                </tbody>
            </table>
        </div>`;

    if (earningsResult) earningsResult.innerHTML += tableHtml;
    
    // update earnings placeholder in table if present
    const total = d.total_earned || 0; 
    const el = document.getElementById('earnings-'+tech); 
    if (el) el.textContent = total.toFixed ? total.toFixed(2) + ' ₾' : total;
});

// Rules
async function loadRules(){
    const tech = selectTech ? selectTech.value : null; if (!tech) return;
    const res = await fetch('api_technicians.php?action=list_rules&technician_id='+encodeURIComponent(tech)); const d = await res.json(); if (!d.success) return;
    const container = document.getElementById('rulesList'); if (!container) return; container.innerHTML = '';
    d.rules.forEach(r=>{ 
        const el = document.createElement('div'); el.className='p-2 border rounded mb-2 flex items-center justify-between gap-3';
        el.innerHTML = `<div><strong>${r.rule_type}</strong> — ${r.value} ${r.description?(' — '+r.description):''}</div><div class="flex gap-2"><button data-id="${r.id}" class="btnEditRule bg-yellow-400 px-2 py-1 rounded">Edit</button><button data-id="${r.id}" class="btnDelRule bg-red-500 px-2 py-1 rounded text-white">Delete</button></div>`; 
        container.appendChild(el); 
    });
    // show panel
    const panel = document.getElementById('panel'); if (panel) panel.classList.remove('hidden');
}

// Delete rule handler (delegate)
const rulesList = document.getElementById('rulesList'); if (rulesList) rulesList.addEventListener('click', (e)=>{
    if (e.target.classList.contains('btnDelRule')){
        const id = e.target.getAttribute('data-id'); if (!confirm('Delete rule?')) return;
        fetch('api_technicians.php?action=delete_rule',{method:'POST', body:new URLSearchParams({id})}).then(r=>r.json()).then(d=>{ if (d.success) loadRules(); else alert('Failed'); });
    }
    if (e.target.classList.contains('btnEditRule')){
        const id = e.target.getAttribute('data-id'); const type = prompt('Rule type (percentage|fixed_per_invoice)'); if (!type) return; const value = prompt('Value'); if (value===null) return; const desc = prompt('Description (optional)');
        fetch('api_technicians.php?action=update_rule',{method:'POST', body:new URLSearchParams({id, rule_type:type, value, description:desc})}).then(r=>r.json()).then(d=>{ if (d.success) loadRules(); else alert('Failed'); });
    }
});

// Compute earnings per-row
function computeForTech(techId){
    // default last 30 days
    const end = new Date(); const start = new Date(); start.setDate(end.getDate()-30);
    const s = start.toISOString().slice(0,10); const e = end.toISOString().slice(0,10);
    if (selectTech) selectTech.value = techId; loadRules();
    const sEl = document.getElementById('startDate'); const eEl = document.getElementById('endDate'); const btn = document.getElementById('btnCompute');
    if (sEl) sEl.value = s; if (eEl) eEl.value = e; if (btn) btn.click();
}

const btnAddRule = document.getElementById('btnAddRule'); if (btnAddRule) btnAddRule.addEventListener('click', ()=>{
    const tech = selectTech ? selectTech.value : null; if (!tech) return alert('Select tech');
    const type = document.getElementById('ruleType').value; const value = document.getElementById('ruleValue').value; const desc = document.getElementById('ruleDesc').value;
    fetch('api_technicians.php?action=add_rule',{method:'POST', body:new URLSearchParams({technician_id:tech, rule_type:type, value, description:desc})}).then(r=>r.json()).then(d=>{ if (d.success) loadRules(); else alert(d.message||'Failed'); });
});


});
</script>

    <?php include __DIR__ . '/../partials/footer.php'; ?>
</body>
</html>