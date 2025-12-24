<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$username = $_SESSION['username'] ?? '';
$todayLocal = date('Y-m-d\TH:i');
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Invoice — Test UI</title>
<link rel="stylesheet" href="main.css">
<style>
    /* Mobile-first custom styles */
    body { background:#f7fafc; font-family: Inter, ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial; }
    .container { max-width:720px; margin:0 auto; padding:16px; }
    .card { background:#fff; border-radius:12px; padding:14px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); margin-bottom:12px; }
    .field-label { font-weight:600; font-size:0.9rem; color:#334155; margin-bottom:6px; }
    .input { width:100%; padding:12px; border:1px solid #e2e8f0; border-radius:10px; font-size:1rem; }
    .row { display:flex; gap:8px; margin-top:8px; }
    .row .col { flex:1; }
    .btn { display:inline-block; background:#047857; color:#fff; padding:12px 16px; border-radius:10px; font-weight:600; text-align:center; }
    .btn.secondary { background:#0ea5e9; }
    .item-card { background:#fbfdff; border-radius:10px; padding:10px; margin-bottom:8px; border:1px solid #e6eef6; }
    .small { font-size:0.9rem; color:#475569; }
    .sticky-footer { position:sticky; bottom:12px; display:flex; gap:8px; }
    .typeahead-box { position:absolute; background:#fff; border:1px solid #e2e8f0; border-radius:8px; box-shadow:0 8px 20px rgba(2,6,23,0.08); max-height:240px; overflow:auto; z-index:99999; }
    .typeahead-item { padding:10px; border-bottom:1px solid #f1f5f9; }
    .typeahead-item:hover { background:#f8fafc; cursor:pointer; }
    @media(min-width:768px){ .container { padding:24px; } }
</style>
</head>
<body>
<div class="container">
    <header class="flex items-center justify-between mb-4">
        <h1 style="font-size:1.2rem; font-weight:700;">Create Invoice — Mobile Test</h1>
        <a href="manager.php" class="small text-slate-600">Back</a>
    </header>

    <form id="mobile-invoice-form" action="save_invoice.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="created_by" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
        <input type="hidden" name="creation_date" id="hidden_creation_date" value="<?php echo $todayLocal; ?>">

        <div class="card">
            <div class="field-label">Plate Number</div>
            <div style="position:relative">
                <input id="mi_plate" name="plate_number" class="input" placeholder="e.g., ZZ-000-ZZ" autocomplete="off">
                <div id="mi_plate_suggestions" class="typeahead-box" style="display:none;"></div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <div class="field-label">Customer</div>
                    <input id="mi_customer" name="customer_name" class="input" placeholder="Customer name">
                </div>
                <div class="col">
                    <div class="field-label">Phone</div>
                    <input id="mi_phone" name="phone_number" class="input" placeholder="Phone number">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <div class="field-label">Make / Model</div>
                    <input id="mi_car" name="car_mark" class="input" placeholder="Make Model">
                </div>
                <div class="col">
                    <div class="field-label">VIN</div>
                    <input id="mi_vin" name="vin" class="input" placeholder="VIN">
                </div>
            </div>
            <div class="row mt-3">
                <div class="col">
                    <div class="field-label">Mileage</div>
                    <input id="mi_mileage" name="mileage" class="input" placeholder="e.g., 150000">
                </div>
                <div class="col">
                    <div class="field-label">Service Manager</div>
                    <input id="mi_sm" name="service_manager" class="input" placeholder="Service manager" value="<?php echo htmlspecialchars($username); ?>">
                </div>
            </div>
        </div>

        <div class="card">
            <div class="flex items-center justify-between mb-2">
                <div style="font-weight:700">Items</div>
                <button type="button" id="mi_add_item" class="btn secondary">+ Add Item</button>
            </div>
            <div id="mi_items_list"></div>
            <div class="row mt-2">
                <div class="col small">Parts Total: <span id="mi_parts_total">0.00 ₾</span></div>
                <div class="col small">Service Total: <span id="mi_service_total">0.00 ₾</span></div>
            </div>
        </div>

        <div class="card">
            <div class="field-label">Notes (optional)</div>
            <textarea name="notes" class="input" rows="3" placeholder="Extra notes..."></textarea>
        </div>

        <div class="sticky-footer">
            <button type="button" id="mi_save" class="btn">Save</button>
            <button type="button" id="mi_save_print" class="btn">Save & Print</button>
        </div>
    </form>

    <div style="height:60px"></div>
</div>

<script>
// Minimal typeahead implementation for the page (works with existing endpoints)
function attachSimpleTypeahead(input, suggestionsEl, endpoint, formatItem, onSelect){
    let current = -1;
    let items = [];
    input.addEventListener('input', async function(){
        const q = input.value.trim();
        if (!q) { suggestionsEl.style.display='none'; suggestionsEl.innerHTML=''; return; }
        try {
            const res = await fetch(endpoint + encodeURIComponent(q));
            if (!res.ok) { suggestionsEl.style.display='none'; return; }
            const data = await res.json();
            // accept multiple shapes
            if (Array.isArray(data)) items = data;
            else if (data && Array.isArray(data.rows)) items = data.rows;
            else if (data && Array.isArray(data.customers)) items = data.customers;
            else if (data && Array.isArray(data.technicians)) items = data.technicians;
            else items = [];
            if (!items.length) { suggestionsEl.style.display='none'; suggestionsEl.innerHTML=''; return; }
            suggestionsEl.innerHTML = items.map((it, idx) => `<div class="typeahead-item" data-idx="${idx}">${formatItem(it)}</div>`).join('');
            suggestionsEl.style.display = 'block';
        } catch(e){ console.log(e); suggestionsEl.style.display='none'; }
    });
    suggestionsEl.addEventListener('click', (ev) => {
        const tgt = ev.target.closest('.typeahead-item'); if (!tgt) return;
        const idx = parseInt(tgt.getAttribute('data-idx'));
        const it = items[idx]; if (!it) return;
        onSelect(it);
        suggestionsEl.style.display='none'; suggestionsEl.innerHTML='';
    });
    document.addEventListener('click', (e)=>{ if (!input.contains(e.target) && !suggestionsEl.contains(e.target)) { suggestionsEl.style.display='none'; } });
}

// Item management
let itemIdx = 0;
function addItemRow(data = {}){
    const id = itemIdx++;
    const wrap = document.createElement('div'); wrap.className = 'item-card'; wrap.dataset.idx = id;
    wrap.innerHTML = `
        <div class="row">
            <div class="col"><input class="input mi_item_name" placeholder="Description" value="${(data.name||'').replace(/"/g,'&quot;')}"></div>
            <div style="width:86px"><input class="input mi_item_qty" type="number" min="1" value="${data.qty||1}"></div>
        </div>
        <div class="row mt-2">
            <div class="col"><input class="input mi_item_price_part" placeholder="Part price" type="number" min="0" value="${data.price_part||0}"></div>
            <div style="width:110px"><input class="input mi_item_price_svc" placeholder="Svc price" type="number" min="0" value="${data.price_svc||0}"></div>
        </div>
        <div class="row mt-2">
            <div class="col small">Technician</div>
            <div style="width:120px;text-align:right"><button type="button" class="btn" data-action="remove">Remove</button></div>
        </div>
        <div class="row mt-2">
            <div class="col"><input class="input mi_item_tech" placeholder="Technician"></div>
        </div>
    `;
    document.getElementById('mi_items_list').appendChild(wrap);

    // attach simple typeahead for technician on this row
    const techInput = wrap.querySelector('.mi_item_tech');
    attachSimpleTypeahead(techInput, createTypeaheadContainer(techInput), 'api_technicians_search.php?q=', it => it.name, (it) => { techInput.value = it.name || ''; wrap.dataset.techId = it.id; });

    wrap.querySelector('[data-action="remove"]').addEventListener('click', ()=>{ wrap.remove(); calcTotals(); });

    wrap.querySelectorAll('.mi_item_qty, .mi_item_price_part, .mi_item_price_svc').forEach(el => el.addEventListener('input', calcTotals));
    calcTotals();
}

function createTypeaheadContainer(input){
    const container = document.createElement('div'); container.className='typeahead-box'; container.style.display='none'; container.style.position='absolute'; container.style.left='0'; container.style.right='0'; container.style.top=(input.offsetHeight+6)+'px'; input.parentElement.style.position='relative'; input.parentElement.appendChild(container); return container;
}

function calcTotals(){
    let parts = 0, svc = 0;
    document.querySelectorAll('.item-card').forEach(card => {
        const qty = parseFloat(card.querySelector('.mi_item_qty').value) || 0;
        const pp = parseFloat(card.querySelector('.mi_item_price_part').value) || 0;
        const ps = parseFloat(card.querySelector('.mi_item_price_svc').value) || 0;
        parts += qty * pp; svc += qty * ps;
    });
    document.getElementById('mi_parts_total').innerText = parts.toFixed(2) + ' ₾';
    document.getElementById('mi_service_total').innerText = svc.toFixed(2) + ' ₾';
}

function prepareAndSubmit(printAfter){
    // Build the form minimally and submit
    const form = document.getElementById('mobile-invoice-form');
    // Remove previous dynamic hidden fields
    document.querySelectorAll('.mi-dyn').forEach(el=>el.remove());
    // Add items
    let i=0;
    document.querySelectorAll('.item-card').forEach(card => {
        const name = card.querySelector('.mi_item_name').value.trim();
        if (!name) return;
        const qty = card.querySelector('.mi_item_qty').value || 1;
        const pp = card.querySelector('.mi_item_price_part').value || 0;
        const ps = card.querySelector('.mi_item_price_svc').value || 0;
        const tech = card.querySelector('.mi_item_tech').value || '';
        const techId = card.dataset.techId || '';
        const inputs = [`item_name_${i}`, `item_qty_${i}`, `item_price_part_${i}`, `item_price_svc_${i}`, `item_tech_${i}`, `item_tech_id_${i}`];
        const vals = [name, qty, pp, ps, tech, techId];
        inputs.forEach((n, idx)=>{
            const h = document.createElement('input'); h.type='hidden'; h.name=n; h.value=vals[idx]; h.className='mi-dyn'; form.appendChild(h);
        });
        i++;
    });

    // Add global totals (optional)
    const partsTotalEl = document.createElement('input'); partsTotalEl.type='hidden'; partsTotalEl.name='parts_total'; partsTotalEl.value = document.getElementById('mi_parts_total').innerText.replace(' ₾','') || '0.00'; partsTotalEl.className='mi-dyn'; form.appendChild(partsTotalEl);
    const svcTotalEl = document.createElement('input'); svcTotalEl.type='hidden'; svcTotalEl.name='service_total'; svcTotalEl.value = document.getElementById('mi_service_total').innerText.replace(' ₾','') || '0.00'; svcTotalEl.className='mi-dyn'; form.appendChild(svcTotalEl);

    if (printAfter) {
        let paf = document.getElementById('print_after_save'); if (!paf){ paf = document.createElement('input'); paf.type='hidden'; paf.name='print_after_save'; paf.id='print_after_save'; paf.className='mi-dyn'; form.appendChild(paf); }
        paf.value='1';
    }

    // Submit normally so PHP will redirect to print/view page
    form.submit();
}

// Attach plate typeahead
attachSimpleTypeahead(document.getElementById('mi_plate'), document.getElementById('mi_plate_suggestions'), './admin/api_customers.php?q=', (it)=> `${it.plate_number} — ${it.full_name || ''} ${it.car_mark? ' — ' + it.car_mark : ''}`, (it)=>{
    document.getElementById('mi_plate').value = it.plate_number || '';
    document.getElementById('mi_customer').value = it.full_name || '';
    document.getElementById('mi_phone').value = it.phone || '';
    document.getElementById('mi_car').value = it.car_mark || '';
    document.getElementById('mi_vin').value = it.vin || '';
    document.getElementById('mi_mileage').value = it.mileage || '';
});

// Button handlers
document.getElementById('mi_add_item').addEventListener('click', ()=> addItemRow());
document.getElementById('mi_save').addEventListener('click', ()=> prepareAndSubmit(false));
document.getElementById('mi_save_print').addEventListener('click', ()=> prepareAndSubmit(true));

// seed one empty item
addItemRow();
</script>
</body>
</html>