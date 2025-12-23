<?php
require_once __DIR__ . '/../config.php';
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','manager'])){
    header('Location: ../admin/index.php'); exit;
}
$title = 'Technicians';
include __DIR__ . '/../partials/header.php';
?>
<div class="max-w-7xl mx-auto p-6">
    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-bold">Technicians</h1>
        <button id="btnNewTech" class="bg-blue-600 text-white px-4 py-2 rounded">Add Technician</button>
    </div>
    <div class="bg-white shadow rounded-lg p-4">
        <table id="techTable" class="min-w-full text-sm">
            <thead class="text-left text-slate-500"><tr><th>Name</th><th>User</th><th>Email</th><th>Actions</th></tr></thead>
            <tbody></tbody>
        </table>
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

<script>
async function fetchTechs(){
    const res = await fetch('api_technicians.php?action=list');
    const data = await res.json(); if (!data.success) return;
    const tbody = document.querySelector('#techTable tbody'); tbody.innerHTML='';
    const sel = document.getElementById('selectTech'); sel.innerHTML='';
    data.technicians.forEach(t=>{
        const tr = document.createElement('tr'); tr.innerHTML = `<td class="py-2">${escapeHtml(t.name)}</td><td class="py-2">${escapeHtml(t.username||'')}</td><td class="py-2">${escapeHtml(t.email||'')}</td><td class="py-2"><button data-id="${t.id}" class="btnEdit bg-yellow-400 px-2 py-1 rounded">Edit</button> <button data-id="${t.id}" class="btnDel bg-red-500 px-2 py-1 rounded text-white">Delete</button></td>`;
        tbody.appendChild(tr);
        const opt = document.createElement('option'); opt.value = t.id; opt.textContent = t.name; sel.appendChild(opt);
    });
}

function escapeHtml(s){ return String(s||'').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":"&#39;"})[c]); }

fetchTechs();

// Add technician
document.getElementById('btnNewTech').addEventListener('click', ()=>{
    const name = prompt('Technician name'); if (!name) return;
    fetch('api_technicians.php?action=create',{method:'POST', body:new URLSearchParams({name})}).then(r=>r.json()).then(d=>{ if (d.success) fetchTechs(); else alert(d.message||'Failed'); });
});

// Delegate delete/edit
document.querySelector('#techTable').addEventListener('click', (e)=>{
    if (e.target.classList.contains('btnDel')){
        const id = e.target.getAttribute('data-id'); if (!confirm('Delete?')) return;
        fetch('api_technicians.php?action=delete',{method:'POST', body:new URLSearchParams({id})}).then(r=>r.json()).then(d=>{ if (d.success) fetchTechs(); else alert(d.message||'Failed'); });
    }
    if (e.target.classList.contains('btnEdit')){
        const id = e.target.getAttribute('data-id'); const name = prompt('Name'); if (!name) return;
        fetch('api_technicians.php?action=update',{method:'POST', body:new URLSearchParams({id,name})}).then(r=>r.json()).then(d=>{ if (d.success) fetchTechs(); else alert('Failed'); });
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
}

document.getElementById('btnAddRule').addEventListener('click', ()=>{
    const tech = document.getElementById('selectTech').value; if (!tech) return alert('Select tech');
    const type = document.getElementById('ruleType').value; const value = document.getElementById('ruleValue').value; const desc = document.getElementById('ruleDesc').value;
    fetch('api_technicians.php?action=add_rule',{method:'POST', body:new URLSearchParams({technician_id:tech, rule_type:type, value, description:desc})}).then(r=>r.json()).then(d=>{ if (d.success) loadRules(); else alert(d.message||'Failed'); });
});

// Show panel toggle
document.getElementById('btnNewTech').addEventListener('click', ()=>{ document.getElementById('panel').classList.toggle('hidden'); });
</script>

<?php include __DIR__ . '/../partials/footer.php'; ?>