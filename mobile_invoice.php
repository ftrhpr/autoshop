<?php
require 'config.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// Preload invoice if editing/viewing
$serverInvoice = null;
if (!empty($_GET['edit_id']) || !empty($_GET['print_id'])) {
    $loadId = !empty($_GET['edit_id']) ? (int)$_GET['edit_id'] : (int)$_GET['print_id'];
    $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1'); $stmt->execute([$loadId]); $inv = $stmt->fetch();
    if ($inv) {
        $inv_items = json_decode($inv['items'], true) ?: [];
        $serverInvoice = [
            'id' => (int)$inv['id'],
            'creation_date' => $inv['creation_date'],
            'customer_name' => $inv['customer_name'],
            'phone' => $inv['phone'],
            'car_mark' => $inv['car_mark'],
            'plate_number' => $inv['plate_number'],
            'vin' => $inv['vin'] ?? '',
            'mileage' => $inv['mileage'],
            'service_manager' => $inv['service_manager'],
            'service_manager_id' => isset($inv['service_manager_id']) ? (int)$inv['service_manager_id'] : 0,
            'technician' => $inv['technician'] ?? '',
            'technician_id' => isset($inv['technician_id']) ? (int)$inv['technician_id'] : 0,
            'items' => $inv_items,
            'grand_total' => (float)$inv['grand_total'],
            'parts_total' => (float)$inv['parts_total'],
            'service_total' => (float)$inv['service_total'],
            'parts_discount_percent' => isset($inv['parts_discount_percent']) ? (float)$inv['parts_discount_percent'] : 0.0,
            'service_discount_percent' => isset($inv['service_discount_percent']) ? (float)$inv['service_discount_percent'] : 0.0,
        ];
    }
}

// Small helper for escaping in JS
function h_json($v){ return json_encode($v, JSON_UNESCAPED_UNICODE); }
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Mobile Invoice — AutoShop</title>
<link rel="stylesheet" href="main.css">
<style>
/* Mobile-specific tweaks for the testing page */
body { -webkit-tap-highlight-color: transparent; }
.header { position: sticky; top: 0; z-index: 60; background: #fff; border-bottom: 1px solid rgba(0,0,0,0.06); }
.container { padding: 1rem; max-width: 900px; margin: 0 auto; }
.item-row { display:flex; gap:8px; align-items:center; }
.item-row .flex-1 { flex:1 }
.small { font-size:0.9rem }
.touch-btn { padding: 12px 16px; border-radius:10px; }
.toast { position: fixed; left: 50%; transform: translateX(-50%); bottom: 18px; z-index: 9999; }
</style>
</head>
<body class="bg-gray-50">
<header class="header p-3 shadow-sm">
  <div class="container flex items-center justify-between">
    <div class="flex items-center gap-3">
      <button onclick="history.back();" class="text-gray-700">←</button>
      <h1 class="text-lg font-semibold">Quick Invoice (Mobile)</h1>
    </div>
    <div>
      <button id="btnSave" class="bg-green-600 text-white touch-btn">Save</button>
    </div>
  </div>
</header>

<main class="container mt-4">
  <form id="mobile-invoice-form" action="save_invoice.php" method="post" enctype="multipart/form-data" novalidate>
    <input type="hidden" name="existing_invoice_id" id="existing_invoice_id" value="<?php echo $serverInvoice ? (int)$serverInvoice['id'] : ''; ?>">

    <section class="bg-white rounded-lg p-4 mb-4 shadow-sm">
      <h2 class="font-medium mb-2">Vehicle & Customer</h2>
      <div class="space-y-3">
        <div>
          <label class="small mb-1">Plate Number</label>
          <input type="text" id="input_plate_number" name="plate_number" class="w-full p-3 border rounded-lg" placeholder="Plate (e.g., ZZ-000-ZZ)">
        </div>
        <div>
          <label class="small mb-1">Make / Model</label>
          <input type="text" id="input_car_mark" name="car_mark" class="w-full p-3 border rounded-lg" placeholder="Toyota Corolla">
        </div>
        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="small mb-1">VIN</label>
            <input id="input_vin" name="vin" class="w-full p-3 border rounded-lg" placeholder="VIN">
          </div>
          <div>
            <label class="small mb-1">Mileage</label>
            <input id="input_mileage" name="mileage" class="w-full p-3 border rounded-lg" placeholder="km">
          </div>
        </div>
      </div>
    </section>

    <section class="bg-white rounded-lg p-4 mb-4 shadow-sm">
      <h2 class="font-medium mb-2">Customer</h2>
      <div class="space-y-3">
        <div>
          <label class="small mb-1">Name</label>
          <input id="input_customer_name" name="customer_name" class="w-full p-3 border rounded-lg">
        </div>
        <div>
          <label class="small mb-1">Phone</label>
          <input id="input_phone_number" name="phone_number" class="w-full p-3 border rounded-lg">
        </div>
      </div>
    </section>

    <section id="items-section" class="bg-white rounded-lg p-4 mb-4 shadow-sm">
      <div class="flex items-center justify-between mb-3">
        <h2 class="font-medium">Items</h2>
        <button type="button" id="btnAddItem" class="bg-blue-600 text-white px-3 py-2 rounded-md">Add Item</button>
      </div>
      <div id="mobile-items-list" class="space-y-3"></div>
    </section>

    <section class="bg-white rounded-lg p-4 mb-4 shadow-sm">
      <h2 class="font-medium mb-2">Discounts & Totals</h2>
      <div class="grid grid-cols-2 gap-3 mb-3">
        <div>
          <label class="small mb-1">Parts Discount %</label>
          <input id="input_parts_discount" class="w-full p-3 border rounded-lg" name="parts_discount_percent" value="0">
        </div>
        <div>
          <label class="small mb-1">Service Discount %</label>
          <input id="input_service_discount" class="w-full p-3 border rounded-lg" name="service_discount_percent" value="0">
        </div>
      </div>
      <div class="flex items-center justify-between">
        <div>
          <div class="text-sm text-gray-600">Parts</div>
          <div id="display_parts_total" class="text-lg font-semibold">0.00 ₾</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Service</div>
          <div id="display_service_total" class="text-lg font-semibold">0.00 ₾</div>
        </div>
        <div>
          <div class="text-sm text-gray-600">Grand</div>
          <div id="display_grand_total" class="text-lg font-semibold">0.00 ₾</div>
        </div>
      </div>
    </section>

    <section class="flex gap-3 mb-6">
      <button type="button" id="btnSave2" class="flex-1 bg-green-600 text-white touch-btn rounded-md">Save Invoice</button>
      <button type="button" id="btnSavePrint" class="flex-1 bg-indigo-600 text-white touch-btn rounded-md">Save & Print</button>
    </section>

    <!-- Hidden fields prepared by JS -->
    <input type="hidden" id="hidden_creation_date" name="creation_date">
    <input type="hidden" id="hidden_service_manager" name="service_manager" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
    <input type="hidden" id="hidden_service_manager_id" name="service_manager_id" value="<?php echo htmlspecialchars($_SESSION['user_id'] ?? ''); ?>">
    <input type="hidden" id="hidden_customer_id" name="customer_id">
    <input type="hidden" id="hidden_vehicle_id" name="vehicle_id">
    <input type="hidden" id="hidden_parts_total" name="parts_total">
    <input type="hidden" id="hidden_service_total" name="service_total">
    <input type="hidden" id="hidden_grand_total" name="grand_total">

    <input type="hidden" id="print_after_save" name="print_after_save" value="">
  </form>
</main>

<script>
// Paste of minimal attachTypeahead (same behavior as desktop)
function debounce(fn, wait=250){ let t; return (...a)=>{ clearTimeout(t); t=setTimeout(()=>fn(...a), wait); }; }
function attachTypeahead(input, endpoint, formatItem, onSelect){
    const box = document.createElement('div');
    box.className = 'bg-white border rounded shadow';
    box.style.maxHeight = '260px'; box.style.overflow='auto'; box.style.position='absolute'; box.style.zIndex='2147483647'; box.style.display='none'; box.style.boxSizing='border-box'; box.style.boxShadow='0 8px 20px rgba(0,0,0,0.12)'; document.body.appendChild(box);
    const updatePos = ()=>{
        const r = input.getBoundingClientRect(); const viewportH = window.innerHeight || document.documentElement.clientHeight; const spaceBelow = viewportH - r.bottom; const spaceAbove = r.top;
        let top = r.bottom + window.scrollY; let maxH = Math.min(260, Math.max(80, spaceBelow - 10));
        if (spaceBelow < 160 && spaceAbove > spaceBelow){ maxH = Math.min(260, Math.max(80, spaceAbove - 10)); top = r.top + window.scrollY - maxH; }
        box.style.left = (r.left + window.scrollX) + 'px'; box.style.top = top + 'px'; box.style.width = r.width + 'px'; box.style.maxHeight = maxH + 'px';
    };
    let scrollHandler = ()=>updatePos(), resizeHandler=()=>updatePos();
    input.addEventListener('input', debounce(async ()=>{
        const q = input.value.trim(); if (!q){ box.innerHTML=''; box.style.display='none'; return; }
        try{ updatePos(); const res = await fetch(endpoint + encodeURIComponent(q)); if (!res.ok){ box.innerHTML=''; box.style.display='none'; return; } const list = await res.json(); let items = [];
            if (Array.isArray(list)) items = list; else if (list && Array.isArray(list.technicians)) items = list.technicians; else if (list && Array.isArray(list.rows)) items = list.rows; else if (list && Array.isArray(list.customers)) items = list.customers;
            if (!Array.isArray(items) || items.length === 0){ box.innerHTML=''; box.style.display='none'; return; }
            box.innerHTML = items.map(it => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-json='${JSON.stringify(it).replace(/'/g,"\\'") }'>${formatItem(it)}</div>`).join('');
            box.style.display='block'; box.querySelectorAll('div').forEach(el => el.addEventListener('click', ()=>{ const item = JSON.parse(el.getAttribute('data-json')); onSelect(item); box.innerHTML=''; box.style.display='none'; }));
            window.addEventListener('scroll', scrollHandler, true); window.addEventListener('resize', resizeHandler);
        }catch(e){ box.innerHTML=''; box.style.display='none'; }
    }));
    input.addEventListener('focus', async ()=>{ try{ updatePos(); const res = await fetch(endpoint); if (!res.ok) return; const list = await res.json(); let items=[]; if (Array.isArray(list)) items = list; else if (list && Array.isArray(list.technicians)) items = list.technicians; else if (list && Array.isArray(list.rows)) items = list.rows; else if (list && Array.isArray(list.customers)) items = list.customers; if (!items || items.length===0) return; box.innerHTML = items.map(it => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-json='${JSON.stringify(it).replace(/'/g,"\\'") }'>${formatItem(it)}</div>`).join(''); box.style.display='block'; box.querySelectorAll('div').forEach(el => el.addEventListener('click', ()=>{ const item = JSON.parse(el.getAttribute('data-json')); onSelect(item); box.innerHTML=''; box.style.display='none'; })); window.addEventListener('scroll', scrollHandler, true); window.addEventListener('resize', resizeHandler); }catch(e){ box.innerHTML=''; box.style.display='none'; } });
    document.addEventListener('click', ev => { if (!input.contains(ev.target) && !box.contains(ev.target)){ box.innerHTML=''; box.style.display='none'; window.removeEventListener('scroll', scrollHandler, true); window.removeEventListener('resize', resizeHandler); } });
}

// Mobile items handling
let mobileRowCount = 0;
function addItemRow(it=null){
    const list = document.getElementById('mobile-items-list');
    const row = document.createElement('div'); row.className='p-3 border rounded-lg item-row'; row.dataset.idx=mobileRowCount;
    row.innerHTML = `
        <div class="flex-1">
          <input class="item-name w-full p-2 border rounded mb-2" placeholder="Description" value="${it?.name||''}">
          <div class="grid grid-cols-3 gap-2">
            <input class="item-qty p-2 border rounded" type="number" min="1" value="${it?.qty||1}">
            <input class="item-price-part p-2 border rounded" type="number" min="0" value="${it?.price_part||0}" placeholder="Part">
            <input class="item-price-svc p-2 border rounded" type="number" min="0" value="${it?.price_svc||0}" placeholder="Service">
          </div>
          <div class="grid grid-cols-3 gap-2 mt-2">
            <input class="item-discount-part p-2 border rounded" type="number" min="0" max="100" value="${it?.discount_part||0}" placeholder="% parts">
            <input class="item-discount-svc p-2 border rounded" type="number" min="0" max="100" value="${it?.discount_svc||0}" placeholder="% svc">
            <input class="item-tech p-2 border rounded" type="text" placeholder="Technician" value="${it?.tech||''}">
          </div>
        </div>
        <div style="width:48px;text-align:center">
          <button class="remove-item text-red-600" title="Remove">×</button>
        </div>`;
    list.appendChild(row);

    // attach technician typeahead
    const tech = row.querySelector('.item-tech'); attachTypeahead(tech,'api_technicians_search.php?q=', t=>t.name, it2=>{ tech.value = it2.name; row.dataset.itemTechId = it2.id; });
    tech.addEventListener('input', ()=>{ if (row.dataset.itemTechId) delete row.dataset.itemTechId; calculateTotals(); });
    row.querySelectorAll('input').forEach(i => i.addEventListener('input', ()=> calculateTotals()));
    row.querySelector('.remove-item').addEventListener('click', ()=>{ row.remove(); calculateTotals(); });
    mobileRowCount++;
    calculateTotals();
}

function calculateTotals(){
    let partTotal=0, svcTotal=0;
    document.querySelectorAll('#mobile-items-list .item-row').forEach(row=>{
        const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
        const pPart = parseFloat(row.querySelector('.item-price-part').value) || 0; const dPart = parseFloat(row.querySelector('.item-discount-part').value) || 0;
        const pSvc = parseFloat(row.querySelector('.item-price-svc').value) || 0; const dSvc = parseFloat(row.querySelector('.item-discount-svc').value) || 0;
        partTotal += qty * pPart * Math.max(0, (1 - dPart/100)); svcTotal += qty * pSvc * Math.max(0, (1 - dSvc/100));
    });
    const globalP = parseFloat(document.getElementById('input_parts_discount').value) || 0; const globalS = parseFloat(document.getElementById('input_service_discount').value) || 0;
    const finalP = Math.max(0, partTotal * (1 - globalP/100)); const finalS = Math.max(0, svcTotal * (1 - globalS/100)); const grand = finalP + finalS;
    document.getElementById('display_parts_total').innerText = finalP>0? finalP.toFixed(2)+' ₾':'0.00 ₾';
    document.getElementById('display_service_total').innerText = finalS>0? finalS.toFixed(2)+' ₾':'0.00 ₾';
    document.getElementById('display_grand_total').innerText = grand>0? grand.toFixed(2)+' ₾':'0.00 ₾';
    return {partTotal:finalP, svcTotal:finalS, grandTotal:grand};
}

function prepareData(){
    // set hidden fields
    document.getElementById('hidden_creation_date').value = new Date().toISOString().slice(0,16).replace('T',' ');
    document.getElementById('hidden_service_manager').value = document.getElementById('hidden_service_manager').value || '';
    document.getElementById('hidden_service_manager_id').value = document.getElementById('hidden_service_manager_id').value || '';
    // copy vehicle/customer
    document.getElementById('hidden_vehicle_id').value = document.getElementById('input_plate_number').dataset.vehicleId || '';
    document.getElementById('hidden_customer_id').value = document.getElementById('input_customer_name').dataset.customerId || '';

    // totals
    const t = calculateTotals(); document.getElementById('hidden_parts_total').value = t.partTotal.toFixed(2); document.getElementById('hidden_service_total').value = t.svcTotal.toFixed(2); document.getElementById('hidden_grand_total').value = t.grandTotal.toFixed(2);

    // remove previous item hidden inputs to avoid duplicates (form may persist)
    Array.from(document.querySelectorAll('input[name^="item_name_"]')).forEach(el=>el.remove());
    Array.from(document.querySelectorAll('input[name^="item_qty_"]')).forEach(el=>el.remove());

    const form = document.getElementById('mobile-invoice-form');
    let index = 0; document.querySelectorAll('#mobile-items-list .item-row').forEach(row=>{
        const name = row.querySelector('.item-name').value.trim(); if (!name) return;
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_name_${index}" value="${name}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_qty_${index}" value="${row.querySelector('.item-qty').value}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_part_${index}" value="${row.querySelector('.item-price-part').value}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_svc_${index}" value="${row.querySelector('.item-price-svc').value}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_discount_part_${index}" value="${row.querySelector('.item-discount-part').value}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_discount_svc_${index}" value="${row.querySelector('.item-discount-svc').value}">`);
        const tId = row.dataset.itemTechId || '';
        if (tId) form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_tech_id_${index}" value="${tId}">`);
        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_tech_${index}" value="${row.querySelector('.item-tech').value}">`);
        index++;
    });
    return true;
}

function handleSave(printAfter=false){
    // basic validation
    const customerName = document.getElementById('input_customer_name').value.trim(); const plate = document.getElementById('input_plate_number').value.trim();
    if (!customerName){ alert('Please enter customer name'); document.getElementById('input_customer_name').focus(); return; }
    if (!plate){ alert('Please enter plate number'); document.getElementById('input_plate_number').focus(); return; }

    if (!prepareData()) return;
    if (printAfter) document.getElementById('print_after_save').value = '1';
    document.getElementById('mobile-invoice-form').submit();
}

// Attach behaviors
document.getElementById('btnAddItem').addEventListener('click', ()=>addItemRow());
document.getElementById('btnSave').addEventListener('click', ()=>handleSave(false));
document.getElementById('btnSave2').addEventListener('click', ()=>handleSave(false));
document.getElementById('btnSavePrint').addEventListener('click', ()=>handleSave(true));

// Plate typeahead: use admin/api_customers.php?q=
attachTypeahead(document.getElementById('input_plate_number'),'./admin/api_customers.php?q=', c=>`${c.plate_number} — ${c.full_name} ${c.car_mark? '— '+c.car_mark:''}`, it=>{
    document.getElementById('input_plate_number').value = it.plate_number || '';
    document.getElementById('input_customer_name').value = it.full_name || '';
    document.getElementById('input_phone_number').value = it.phone || '';
    document.getElementById('input_car_mark').value = it.car_mark || '';
    document.getElementById('input_vin').value = it.vin || '';
    document.getElementById('input_mileage').value = it.mileage || '';
    // store associated ids on input dataset for prepareData
    document.getElementById('input_plate_number').dataset.vehicleId = it.id || '';
    document.getElementById('input_customer_name').dataset.customerId = it.customer_id || '';
});

// Service manager default is current user; attach typeahead to customer name for search too
attachTypeahead(document.getElementById('input_customer_name'),'./admin/api_customers.php?customer_q=', c=>`${c.full_name} — ${c.phone}`, it=>{
    document.getElementById('input_customer_name').value = it.full_name || '';
    document.getElementById('input_phone_number').value = it.phone || '';
});

// If server invoice provided, load values
(function(){ const inv = <?php echo $serverInvoice ? h_json($serverInvoice) : 'null'; ?>; if (!inv) return; if (inv.creation_date) document.getElementById('hidden_creation_date').value = inv.creation_date.replace('T',' ').substring(0,16);
    if (inv.customer_name) document.getElementById('input_customer_name').value = inv.customer_name; if (inv.phone) document.getElementById('input_phone_number').value = inv.phone; if (inv.car_mark) document.getElementById('input_car_mark').value = inv.car_mark; if (inv.plate_number) document.getElementById('input_plate_number').value = inv.plate_number; if (inv.vin) document.getElementById('input_vin').value = inv.vin; if (inv.mileage) document.getElementById('input_mileage').value=inv.mileage;
    (inv.items||[]).forEach(it=>addItemRow(it)); document.getElementById('input_parts_discount').value = inv.parts_discount_percent||0; document.getElementById('input_service_discount').value = inv.service_discount_percent||0; calculateTotals(); })();
</script>
</body>
</html>