<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php'); exit;
}
$now = date('Y-m-d\TH:i');
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Mobile Invoice - AutoShop</title>
    <link rel="stylesheet" href="main.css">
    <style>
        /* small extra tweaks for this mobile page */
        body { -webkit-tap-highlight-color: transparent; }
        .suggestions { position: absolute; z-index: 2147483647; background: #fff; border: 1px solid #e5e7eb; border-radius: .375rem; box-shadow: 0 8px 24px rgba(0,0,0,.08); max-height: 240px; overflow:auto; }
        .item-card { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .75rem; background: #fff; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen text-gray-800">
    <header class="bg-white shadow p-4 sticky top-0 z-40">
        <div class="max-w-2xl mx-auto flex items-center justify-between">
            <h1 class="text-lg font-semibold">Create Invoice (Mobile)</h1>
            <div class="space-x-2">
                <a href="index.php" class="text-sm text-blue-600">Desktop</a>
                <a href="manager.php" class="text-sm text-gray-600">Back</a>
            </div>
        </div>
    </header>

    <main class="max-w-2xl mx-auto p-4">
        <form id="invoice-form" action="save_invoice.php" method="post" enctype="multipart/form-data" onsubmit="return handleSave()" class="space-y-4">
            <input type="hidden" name="print_after_save" id="print_after_save" value="">
            <input type="hidden" name="existing_invoice_id" id="existing_invoice_id" value="">

            <section class="bg-white p-4 rounded-lg shadow-sm">
                <h2 class="font-semibold mb-3">Vehicle</h2>
                <div class="space-y-3">
                    <div class="relative">
                        <label class="text-sm text-gray-700">Plate Number</label>
                        <input type="text" id="input_plate_mobile" name="plate_number" placeholder="Plate" class="w-full mt-1 p-3 rounded border" autocomplete="off">
                        <div id="plate-suggestions" class="suggestions hidden mt-1"></div>
                    </div>
                    <div>
                        <label class="text-sm text-gray-700">Car Make/Model</label>
                        <input type="text" id="input_car_mark_mobile" name="car_mark" class="w-full mt-1 p-3 rounded border">
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="text-sm text-gray-700">VIN</label>
                            <input type="text" id="input_vin_mobile" name="vin" class="w-full mt-1 p-3 rounded border">
                        </div>
                        <div>
                            <label class="text-sm text-gray-700">Mileage</label>
                            <input type="text" id="input_mileage_mobile" name="mileage" class="w-full mt-1 p-3 rounded border">
                        </div>
                    </div>
                </div>
            </section>

            <section class="bg-white p-4 rounded-lg shadow-sm">
                <h2 class="font-semibold mb-3">Customer</h2>
                <div class="space-y-3">
                    <div>
                        <label class="text-sm text-gray-700">Customer Name</label>
                        <input type="text" id="input_customer_name_mobile" name="customer_name" class="w-full mt-1 p-3 rounded border">
                    </div>
                    <div>
                        <label class="text-sm text-gray-700">Phone</label>
                        <input type="text" id="input_phone_mobile" name="phone_number" class="w-full mt-1 p-3 rounded border">
                    </div>
                    <div>
                        <label class="text-sm text-gray-700">Creation Date</label>
                        <input type="datetime-local" id="input_creation_date_mobile" name="creation_date" value="<?php echo $now; ?>" class="w-full mt-1 p-3 rounded border">
                    </div>
                </div>
            </section>

            <section id="items-section" class="space-y-3">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Items</h2>
                    <button type="button" onclick="addItem()" class="bg-green-600 text-white px-3 py-2 rounded">Add</button>
                </div>
                <div id="items-list" class="space-y-3">
                    <!-- item cards inserted here -->
                </div>
            </section>

            <section class="bg-white p-4 rounded-lg shadow-sm">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-sm text-gray-600">Parts</div>
                        <div id="display_parts_total" class="text-lg font-bold">0.00 ₾</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Service</div>
                        <div id="display_service_total" class="text-lg font-bold">0.00 ₾</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600">Grand</div>
                        <div id="display_grand_total" class="text-lg font-bold">0.00 ₾</div>
                    </div>
                </div>
            </section>

            <div class="flex gap-3">
                <button type="button" onclick="submitAndPrint()" class="flex-1 bg-blue-600 text-white p-3 rounded">Save & Print</button>
                <button type="button" onclick="handleSave()" class="flex-1 bg-gray-800 text-white p-3 rounded">Save</button>
            </div>
        </form>
    </main>

<script>
// Simple mobile invoice JS
let itemIndex = 0;
function addItem(data={}){
    const list = document.getElementById('items-list');
    const id = 'item-'+(itemIndex++);
    const div = document.createElement('div');
    div.className = 'item-card';
    div.dataset.idx = id;
    div.innerHTML = `
        <div class="flex justify-between items-start">
            <strong>Item</strong>
            <button type="button" class="text-red-600" onclick="this.closest('.item-card').remove(); calculateTotals();">Remove</button>
        </div>
        <div class="mt-2 space-y-2">
            <input type="text" placeholder="Description" class="w-full p-2 border rounded item-name" value="${(data.name||'')}">
            <div class="grid grid-cols-3 gap-2">
                <input type="number" min="1" class="p-2 border rounded item-qty" value="${(data.qty || 1)}">
                <input type="number" min="0" class="p-2 border rounded item-price-part" placeholder="Part" value="${(data.price_part||0)}">
                <input type="number" min="0" class="p-2 border rounded item-price-svc" placeholder="Service" value="${(data.price_svc||0)}">
            </div>
            <div class="grid grid-cols-2 gap-2">
                <input type="number" min="0" max="100" class="p-2 border rounded item-discount-part" placeholder="Part %" value="${(data.discount_part||0)}">
                <input type="number" min="0" max="100" class="p-2 border rounded item-discount-svc" placeholder="Service %" value="${(data.discount_svc||0)}">
            </div>
            <input type="text" placeholder="Technician" class="w-full p-2 border rounded item-tech" value="${(data.tech||'')}">
        </div>
    `;
    list.appendChild(div);
    // attach simple input listeners
    div.querySelectorAll('input').forEach(el => el.addEventListener('input', calculateTotals));
    calculateTotals();
}

function calculateTotals(){
    let pTotal = 0, sTotal = 0;
    document.querySelectorAll('.item-card').forEach(card => {
        const qty = parseFloat(card.querySelector('.item-qty').value) || 0;
        const pp = parseFloat(card.querySelector('.item-price-part').value) || 0;
        const ps = parseFloat(card.querySelector('.item-price-svc').value) || 0;
        const dp = parseFloat(card.querySelector('.item-discount-part').value) || 0;
        const ds = parseFloat(card.querySelector('.item-discount-svc').value) || 0;
        pTotal += qty * pp * Math.max(0, (1 - dp/100));
        sTotal += qty * ps * Math.max(0, (1 - ds/100));
    });
    const finalP = Math.max(0, pTotal); const finalS = Math.max(0, sTotal);
    document.getElementById('display_parts_total').innerText = finalP.toFixed(2) + ' ₾';
    document.getElementById('display_service_total').innerText = finalS.toFixed(2) + ' ₾';
    document.getElementById('display_grand_total').innerText = (finalP+finalS).toFixed(2) + ' ₾';
}

function prepareData(){
    const form = document.getElementById('invoice-form');
    // remove previous dynamic inputs
    form.querySelectorAll('input[name^="item_"]').forEach(n => n.remove());
    let idx = 0;
    document.querySelectorAll('.item-card').forEach(card => {
        const name = card.querySelector('.item-name').value.trim();
        if (!name) return;
        const qty = card.querySelector('.item-qty').value || 1;
        const pp = card.querySelector('.item-price-part').value || 0;
        const ps = card.querySelector('.item-price-svc').value || 0;
        const dp = card.querySelector('.item-discount-part').value || 0;
        const ds = card.querySelector('.item-discount-svc').value || 0;
        const tech = card.querySelector('.item-tech').value || '';
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_name_${idx}" value="${name}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_qty_${idx}" value="${qty}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_part_${idx}" value="${pp}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_svc_${idx}" value="${ps}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_discount_part_${idx}" value="${dp}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_discount_svc_${idx}" value="${ds}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_tech_${idx}" value="${tech}">`);
        idx++;
    });
    // set totals hidden (so server accepts)
    const totals = {
        parts_total: (document.getElementById('display_parts_total').innerText || '0').replace(' ₾','') || 0,
        service_total: (document.getElementById('display_service_total').innerText || '0').replace(' ₾','') || 0,
        grand_total: (document.getElementById('display_grand_total').innerText || '0').replace(' ₾','') || 0
    };
    ['parts_total','service_total','grand_total'].forEach(k => {
        const v = totals[k];
        const el = document.createElement('input'); el.type = 'hidden'; el.name = k; el.value = v; form.appendChild(el);
    });
    // copy vehicle/customer fields into hidden names expected by save_invoice.php
    const fields = {
        customer_name: document.getElementById('input_customer_name_mobile').value || '',
        phone_number: document.getElementById('input_phone_mobile').value || '',
        car_mark: document.getElementById('input_car_mark_mobile').value || '',
        plate_number: document.getElementById('input_plate_mobile').value || '',
        vin: document.getElementById('input_vin_mobile').value || '',
        mileage: document.getElementById('input_mileage_mobile').value || '',
        creation_date: document.getElementById('input_creation_date_mobile').value || ''
    };
    for (const [k,v] of Object.entries(fields)){
        const el = document.createElement('input'); el.type='hidden'; el.name=k; el.value=v; form.appendChild(el);
    }
    return true;
}

function handleSave(){
    // Basic validation: plate or customer required
    const customer = document.getElementById('input_customer_name_mobile').value.trim();
    const plate = document.getElementById('input_plate_mobile').value.trim();
    if (!customer && !plate) { alert('Please enter a customer name or plate number.'); return false; }
    if (!prepareData()) return false;
    document.getElementById('invoice-form').submit();
    return true;
}

function submitAndPrint(){ document.getElementById('print_after_save').value = '1'; handleSave(); }

// minimal plate suggestion logic
(function(){
    const input = document.getElementById('input_plate_mobile');
    const box = document.getElementById('plate-suggestions');
    let timer;
    input.addEventListener('input', () => {
        clearTimeout(timer);
        const q = input.value.trim();
        if (!q) { box.classList.add('hidden'); box.innerHTML=''; return; }
        timer = setTimeout(async ()=>{
            try{
                const res = await fetch('./admin/api_customers.php?q=' + encodeURIComponent(q));
                if (!res.ok) { box.classList.add('hidden'); return; }
                const rows = await res.json();
                if (!Array.isArray(rows) || rows.length === 0) { box.classList.add('hidden'); return; }
                box.innerHTML = rows.map(r => `<div class='px-3 py-2 cursor-pointer hover:bg-gray-100' data-json='${JSON.stringify(r).replace(/'/g,"\\'") }'>${r.plate_number} — ${r.full_name || ''}${r.car_mark? ' — ' + r.car_mark : ''}${r.vin? ' — VIN:'+r.vin : ''}</div>`).join('');
                box.classList.remove('hidden');
                box.querySelectorAll('div').forEach(el=>el.addEventListener('click', ()=>{
                    const it = JSON.parse(el.getAttribute('data-json'));
                    input.value = it.plate_number || '';
                    document.getElementById('input_customer_name_mobile').value = it.full_name || '';
                    document.getElementById('input_phone_mobile').value = it.phone || '';
                    document.getElementById('input_car_mark_mobile').value = it.car_mark || '';
                    document.getElementById('input_vin_mobile').value = it.vin || '';
                    document.getElementById('input_mileage_mobile').value = it.mileage || '';
                    box.classList.add('hidden');
                }));
            }catch(e){ box.classList.add('hidden'); }
        }, 250);
    });
    document.addEventListener('click', (ev)=>{ if (!input.contains(ev.target) && !box.contains(ev.target)) box.classList.add('hidden'); });
})();

// Start with one blank item row
addItem();
</script>
</body>
</html>