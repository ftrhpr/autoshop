<?php
// Enable error logging for debugging
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
error_reporting(E_ALL);

// Set default timezone to Tbilisi
date_default_timezone_set('Asia/Tbilisi');
// Get current date in format required for datetime-local input (YYYY-MM-DDTHH:MM)
$currentDate = date('Y-m-d\TH:i');

require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Mobile Invoice Creator - Auto Shop Manager</title>

    <!-- Tailwind CSS -->
    <link href="./dist/output.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        /* Mobile-first responsive design */
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .mobile-container {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            min-height: 100vh;
            box-shadow: 0 0 30px rgba(0,0,0,0.1);
        }

        .form-section {
            padding: 1.5rem;
            border-bottom: 1px solid #f0f0f0;
        }

        .form-section:last-child {
            border-bottom: none;
        }

        .input-group {
            margin-bottom: 1.5rem;
        }

        .input-label {
            display: block;
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .input-field {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid #e5e7eb;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.2s ease;
            background: #fafafa;
        }

        .input-field:focus {
            outline: none;
            border-color: #3b82f6;
            background: white;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn-primary {
            width: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            width: 100%;
            background: #f3f4f6;
            color: #374151;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            border: 2px solid #e5e7eb;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .section-header {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .item-card {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            position: relative;
        }

        .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .item-name {
            font-weight: 600;
            color: #1f2937;
        }

        .remove-item {
            color: #ef4444;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 0.25rem;
            transition: background-color 0.2s;
        }

        .remove-item:hover {
            background: #fef2f2;
        }

        .price-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .price-input {
            display: flex;
            flex-direction: column;
        }

        .price-label {
            font-size: 0.75rem;
            color: #6b7280;
            margin-bottom: 0.25rem;
        }

        .totals-section {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin: 1rem 0;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .total-label {
            font-weight: 500;
        }

        .total-value {
            font-weight: 700;
            font-size: 1.1rem;
        }

        .grand-total {
            border-top: 1px solid rgba(255,255,255,0.3);
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }

        .grand-total .total-value {
            font-size: 1.5rem;
        }

        .suggestions-box {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
        }

        .suggestion-item {
            padding: 0.75rem;
            border-bottom: 1px solid #f3f4f6;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .suggestion-item:hover {
            background: #f3f4f6;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .price-source {
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .photo-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 0.75rem;
            margin-top: 1rem;
        }

        .photo-item {
            position: relative;
            aspect-ratio: 4/3;
            border-radius: 0.5rem;
            overflow: hidden;
            background: #f3f4f6;
        }

        .photo-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-remove {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border-radius: 50%;
            width: 1.5rem;
            height: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
        }

        .fab {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            width: 3.5rem;
            height: 3.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
            transition: all 0.2s ease;
            z-index: 100;
        }

        .fab:hover {
            transform: scale(1.1);
        }

        .progress-indicator {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: #e5e7eb;
            z-index: 50;
        }

        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
        }

        .toast {
            position: fixed;
            bottom: 5rem;
            left: 1rem;
            right: 1rem;
            background: #10b981;
            color: white;
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s ease;
            z-index: 1000;
        }

        .toast.show {
            transform: translateY(0);
            opacity: 1;
        }

        @media (max-width: 480px) {
            .mobile-container {
                margin: 0;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="progress-indicator">
        <div class="progress-bar" id="progress-bar" style="width: 0%"></div>
    </div>

    <div class="mobile-container">
        <header style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; text-align: center;">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                <i class="fas fa-file-invoice-dollar mr-2"></i>
                Mobile Invoice Creator
            </h1>
            <p style="font-size: 0.9rem; opacity: 0.9;">Create invoices on the go</p>
        </header>

        <form id="mobile-invoice-form" action="save_invoice.php" method="post" enctype="multipart/form-data">
            <!-- Hidden fields -->
            <input type="hidden" name="creation_date" id="hidden_creation_date">
            <input type="hidden" name="service_manager" id="hidden_service_manager">
            <input type="hidden" name="customer_name" id="hidden_customer_name">
            <input type="hidden" name="phone_number" id="hidden_phone_number">
            <input type="hidden" name="car_mark" id="hidden_car_mark">
            <input type="hidden" name="plate_number" id="hidden_plate_number">
            <input type="hidden" name="vin" id="hidden_vin">
            <input type="hidden" name="customer_id" id="hidden_customer_id">
            <input type="hidden" name="vehicle_id" id="hidden_vehicle_id">
            <input type="hidden" name="mileage" id="hidden_mileage">
            <input type="hidden" name="parts_total" id="hidden_parts_total">
            <input type="hidden" name="service_total" id="hidden_service_total">
            <input type="hidden" name="grand_total" id="hidden_grand_total">
            <input type="hidden" name="parts_discount_percent" id="hidden_parts_discount">
            <input type="hidden" name="service_discount_percent" id="hidden_service_discount">
            <input type="hidden" name="service_manager_id" id="input_service_manager_id" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
            <input type="hidden" name="vehicle_id" id="input_vehicle_id">

            <!-- Vehicle Section -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-car"></i>
                    Vehicle Details
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_plate_number">
                        <i class="fas fa-id-card mr-1"></i>
                        Plate Number *
                    </label>
                    <input type="text" id="input_plate_number" class="input-field" placeholder="ZZ-000-ZZ">
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_car_mark">
                        <i class="fas fa-car-side mr-1"></i>
                        Make/Model
                    </label>
                    <input type="text" id="input_car_mark" class="input-field" placeholder="Toyota Camry">
                    <div class="suggestions-box" style="display: none;"></div>
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_vin">
                        <i class="fas fa-hashtag mr-1"></i>
                        VIN
                    </label>
                    <input type="text" id="input_vin" class="input-field" placeholder="Vehicle VIN">
                    <div class="suggestions-box" style="display: none;"></div>
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_mileage">
                        <i class="fas fa-tachometer-alt mr-1"></i>
                        Mileage
                    </label>
                    <input type="text" id="input_mileage" class="input-field" placeholder="150000 km">
                </div>
            </div>

            <!-- Customer Section -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-user"></i>
                    Customer Details
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_creation_date">
                        <i class="fas fa-calendar mr-1"></i>
                        Creation Date
                    </label>
                    <input type="datetime-local" id="input_creation_date" class="input-field" value="<?php echo $currentDate; ?>">
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_customer_name">
                        <i class="fas fa-user-tag mr-1"></i>
                        Customer Name *
                    </label>
                    <input type="text" id="input_customer_name" class="input-field" placeholder="Enter customer name">
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_phone_number">
                        <i class="fas fa-phone mr-1"></i>
                        Phone Number
                    </label>
                    <input type="text" id="input_phone_number" class="input-field" placeholder="Phone number">
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_service_manager">
                        <i class="fas fa-user-tie mr-1"></i>
                        Service Manager
                    </label>
                    <input type="text" id="input_service_manager" class="input-field" placeholder="Manager Name" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>">
                </div>
            </div>

            <!-- Items Section -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-tools"></i>
                    Service & Parts
                </div>

                <div id="items-container">
                    <!-- Items will be added here -->
                </div>

                <button type="button" onclick="addItem()" class="btn-secondary" style="margin-bottom: 1rem;">
                    <i class="fas fa-plus mr-2"></i>
                    Add Item
                </button>

                <!-- Totals -->
                <div class="totals-section">
                    <div class="total-row">
                        <span class="total-label">Parts Total:</span>
                        <span class="total-value" id="display_parts_total">0 ₾</span>
                    </div>
                    <div class="total-row">
                        <span class="total-label">Service Total:</span>
                        <span class="total-value" id="display_service_total">0 ₾</span>
                    </div>
                    <div class="total-row grand-total">
                        <span class="total-label">Grand Total:</span>
                        <span class="total-value" id="display_grand_total">0 ₾</span>
                    </div>
                </div>

                <!-- Discounts -->
                <div class="input-group">
                    <label class="input-label" for="input_parts_discount">
                        <i class="fas fa-percent mr-1"></i>
                        Parts Discount (%)
                    </label>
                    <input type="number" id="input_parts_discount" class="input-field" min="0" max="100" value="0" oninput="calculateTotals()">
                </div>

                <div class="input-group">
                    <label class="input-label" for="input_service_discount">
                        <i class="fas fa-percent mr-1"></i>
                        Service Discount (%)
                    </label>
                    <input type="number" id="input_service_discount" class="input-field" min="0" max="100" value="0" oninput="calculateTotals()">
                </div>
            </div>

            <!-- Photos Section -->
            <div class="form-section">
                <div class="section-header">
                    <i class="fas fa-camera"></i>
                    Photos
                </div>

                <div style="display: flex; gap: 0.5rem; margin-bottom: 1rem;">
                    <button type="button" id="btn_take_photo" class="btn-secondary" style="flex: 1;">
                        <i class="fas fa-camera mr-1"></i>
                        Take Photo
                    </button>
                    <button type="button" id="btn_upload_photo" class="btn-secondary" style="flex: 1;">
                        <i class="fas fa-upload mr-1"></i>
                        Upload
                    </button>
                </div>

                <input type="file" id="input_images" name="images[]" accept="image/*" multiple style="display: none;">

                <div id="photo-preview" class="photo-grid">
                    <!-- Photos will be displayed here -->
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="form-section" style="padding-bottom: 5rem;">
                <button type="button" onclick="handleSave()" class="btn-primary">
                    <i class="fas fa-save mr-2"></i>
                    Save Invoice
                </button>
                <button type="button" onclick="handleSaveAndPrint()" class="btn-secondary">
                    <i class="fas fa-print mr-2"></i>
                    Save & Print
                </button>
            </div>
        </form>
    </div>

    <!-- Floating Action Button for Quick Add Item -->
    <div class="fab" onclick="addItem()">
        <i class="fas fa-plus"></i>
    </div>

    <!-- Toast Notifications -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="toast-message">Invoice saved successfully!</span>
    </div>

    <script>
        // Global variables
        let itemCount = 0;
        let selectedFiles = [];

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Add initial items
            for(let i = 0; i < 3; i++) {
                addItem();
            }
            calculateTotals();
            updateProgress();

            // Set default service manager
            const smInput = document.getElementById('input_service_manager');
            const smIdInput = document.getElementById('input_service_manager_id');
            if (smInput && (!smInput.value || smInput.value.trim() === '')) {
                smInput.value = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
            }
            if (smIdInput && (!smIdInput.value || smIdInput.value == 0)) {
                smIdInput.value = '<?php echo (int)($_SESSION['user_id'] ?? 0); ?>';
            }
        });

        // Add item function
        function addItem() {
            itemCount++;
            const container = document.getElementById('items-container');

            const itemCard = document.createElement('div');
            itemCard.className = 'item-card';
            itemCard.id = `item-${itemCount}`;

            itemCard.innerHTML = `
                <div class="item-header">
                    <span class="item-name">Item ${itemCount}</span>
                    <button type="button" class="remove-item" onclick="removeItem(${itemCount})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>

                <div class="input-group">
                    <input type="text" class="input-field item-name-input" placeholder="Item description" oninput="fetchItemSuggestions(this)">
                    <div class="suggestions-box" style="display: none;"></div>
                    <div class="price-source text-xs text-gray-500 mt-1"></div>
                </div>

                <div class="price-grid">
                    <div class="price-input">
                        <label class="price-label">Qty</label>
                        <input type="number" class="input-field item-qty" min="1" value="1" oninput="calculateTotals()">
                    </div>
                    <div class="price-input">
                        <label class="price-label">Part Price</label>
                        <input type="number" class="input-field item-price-part" min="0" value="0" oninput="calculateTotals()">
                    </div>
                    <div class="price-input">
                        <label class="price-label">Part Disc %</label>
                        <input type="number" class="input-field item-discount-part" min="0" max="100" value="0" oninput="calculateTotals()">
                    </div>
                    <div class="price-input">
                        <label class="price-label">Svc Price</label>
                        <input type="number" class="input-field item-price-svc" min="0" value="0" oninput="calculateTotals()">
                    </div>
                    <div class="price-input">
                        <label class="price-label">Svc Disc %</label>
                        <input type="number" class="input-field item-discount-svc" min="0" max="100" value="0" oninput="calculateTotals()">
                    </div>
                    <div class="price-input">
                        <label class="price-label">Technician</label>
                        <input type="text" class="input-field item-tech" placeholder="Name">
                        <div class="suggestions-box" style="display: none;"></div>
                    </div>
                </div>

                <input type="hidden" class="item-db-id">
                <input type="hidden" class="item-db-type">
                <input type="hidden" class="item-db-vehicle">
                <input type="hidden" class="item-db-price-source">
                <input type="hidden" class="item-tech-id">
            `;

            container.appendChild(itemCard);
            updateProgress();

            // Attach technician typeahead to the new field
            const techInput = itemCard.querySelector('.item-tech');
            if (techInput) {
                attachTypeahead(
                    techInput,
                    'api_technicians_search.php?q=',
                    t => t.name,
                    (item) => {
                        techInput.value = item.name;
                        itemCard.querySelector('.item-tech-id').value = item.id;
                    }
                );
            }
        }

        // Remove item function
        function removeItem(id) {
            const item = document.getElementById(`item-${id}`);
            if (item) {
                item.remove();
                calculateTotals();
                updateProgress();
            }
        }

        // Calculate totals
        function calculateTotals() {
            let partsTotal = 0;
            let serviceTotal = 0;

            document.querySelectorAll('.item-card').forEach(card => {
                const qty = parseFloat(card.querySelector('.item-qty').value) || 0;
                const partPrice = parseFloat(card.querySelector('.item-price-part').value) || 0;
                const partDiscount = parseFloat(card.querySelector('.item-discount-part').value) || 0;
                const svcPrice = parseFloat(card.querySelector('.item-price-svc').value) || 0;
                const svcDiscount = parseFloat(card.querySelector('.item-discount-svc').value) || 0;

                const partDiscounted = partPrice * (1 - partDiscount / 100);
                const svcDiscounted = svcPrice * (1 - svcDiscount / 100);

                partsTotal += qty * partDiscounted;
                serviceTotal += qty * svcDiscounted;
            });

            const globalPartsDiscount = parseFloat(document.getElementById('input_parts_discount').value) || 0;
            const globalServiceDiscount = parseFloat(document.getElementById('input_service_discount').value) || 0;

            partsTotal *= (1 - globalPartsDiscount / 100);
            serviceTotal *= (1 - globalServiceDiscount / 100);

            const grandTotal = partsTotal + serviceTotal;

            document.getElementById('display_parts_total').textContent = partsTotal.toFixed(2) + ' ₾';
            document.getElementById('display_service_total').textContent = serviceTotal.toFixed(2) + ' ₾';
            document.getElementById('display_grand_total').textContent = grandTotal.toFixed(2) + ' ₾';

            // Update hidden fields
            document.getElementById('hidden_parts_total').value = partsTotal.toFixed(2);
            document.getElementById('hidden_service_total').value = serviceTotal.toFixed(2);
            document.getElementById('hidden_grand_total').value = grandTotal.toFixed(2);
            document.getElementById('hidden_parts_discount').value = globalPartsDiscount;
            document.getElementById('hidden_service_discount').value = globalServiceDiscount;
        }

        // Update progress bar
        function updateProgress() {
            const totalFields = 8; // Basic required fields
            const itemFields = document.querySelectorAll('.item-card').length * 2; // Rough estimate
            const totalPossible = totalFields + itemFields;

            let filledFields = 0;

            // Check basic fields
            ['input_plate_number', 'input_customer_name', 'input_service_manager'].forEach(id => {
                if (document.getElementById(id).value.trim()) filledFields++;
            });

            // Check items
            document.querySelectorAll('.item-card').forEach(card => {
                if (card.querySelector('.item-name-input').value.trim()) filledFields++;
                if (card.querySelector('.item-price-part').value > 0 || card.querySelector('.item-price-svc').value > 0) filledFields++;
            });

            const progress = Math.min((filledFields / totalPossible) * 100, 100);
            document.getElementById('progress-bar').style.width = progress + '%';
        }

        // Typeahead functionality
        function debounce(fn, wait=250) {
            let t;
            return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
        }

        function attachTypeahead(input, endpoint, formatItem, onSelect) {
            const box = document.createElement('div');
            box.className = 'bg-white border rounded shadow';
            box.style.maxHeight = '220px';
            box.style.overflow = 'auto';
            box.style.position = 'absolute';
            // Use very large z-index so the suggestions always appear above other UI elements
            box.style.zIndex = '2147483647';
            box.style.display = 'none';
            box.style.boxSizing = 'border-box';
            box.style.boxShadow = '0 10px 20px rgba(0,0,0,0.15)';
            box.style.backgroundColor = '#fff';
            box.style.pointerEvents = 'auto';
            document.body.appendChild(box);

            const updatePos = () => {
                const r = input.getBoundingClientRect();
                const viewportH = window.innerHeight || document.documentElement.clientHeight;
                const spaceBelow = viewportH - r.bottom;
                const spaceAbove = r.top;

                // Default placement below the input
                let top = r.bottom + window.scrollY;
                let maxH = Math.min(220, Math.max(80, spaceBelow - 10));

                // If there's not enough space below and more space above, place it above the input
                if (spaceBelow < 120 && spaceAbove > spaceBelow) {
                    maxH = Math.min(220, Math.max(80, spaceAbove - 10));
                    top = r.top + window.scrollY - maxH;
                }

                box.style.left = (r.left + window.scrollX) + 'px';
                box.style.top = top + 'px';
                box.style.width = r.width + 'px';
                box.style.maxHeight = maxH + 'px';
            };

            let scrollHandler = () => updatePos();
            let resizeHandler = () => updatePos();

            input.addEventListener('input', debounce(async () => {
                const q = input.value.trim();
                if (!q) { box.innerHTML = ''; box.style.display = 'none'; return; }
                try {
                    updatePos();
                    // console.log('Searching for:', q);
                    const res = await fetch(endpoint + encodeURIComponent(q));
                    if (!res.ok) {
                        // console.log('Response not ok:', res.status);
                        box.innerHTML = ''; box.style.display = 'none'; return;
                    }
                    const list = await res.json();
                    // Accept multiple payload shapes: raw array, wrapper {success:true, technicians:[]}, or generic rows array
                    let items = [];
                    if (Array.isArray(list)) items = list;
                    else if (list && Array.isArray(list.technicians)) items = list.technicians;
                    else if (list && Array.isArray(list.rows)) items = list.rows;
                    else if (list && Array.isArray(list.customers)) items = list.customers;

                    if (!Array.isArray(items) || items.length === 0) { box.innerHTML = ''; box.style.display = 'none'; return; }
                    box.innerHTML = items.map(item => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-id="${item.id || item.customer_id || ''}" data-json='${JSON.stringify(item).replace(/'/g, "\\'") }'>${formatItem(item)}</div>`).join('');
                    box.style.display = 'block';
                    box.querySelectorAll('div').forEach(el => el.addEventListener('click', () => {
                        const item = JSON.parse(el.getAttribute('data-json'));
                        onSelect(item);
                        box.innerHTML = '';
                        box.style.display = 'none';
                    }));
                    window.addEventListener('scroll', scrollHandler, true);
                    window.addEventListener('resize', resizeHandler);
                } catch (e) {
                    console.log('Error:', e);
                    box.innerHTML = '';
                    box.style.display = 'none';
                }
            }));

            input.addEventListener('focus', async () => {
                try {
                    updatePos();
                    const res = await fetch(endpoint);
                    if (!res.ok) return;
                    const list = await res.json();
                    // Accept array or wrapper shapes
                    let items = [];
                    if (Array.isArray(list)) items = list;
                    else if (list && Array.isArray(list.technicians)) items = list.technicians;
                    else if (list && Array.isArray(list.rows)) items = list.rows;
                    else if (list && Array.isArray(list.customers)) items = list.customers;
                    if (!Array.isArray(items) || items.length === 0) return;
                    box.innerHTML = items.map(item => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-id="${item.id || item.customer_id || ''}" data-json='${JSON.stringify(item).replace(/'/g, "\\'") }'>${formatItem(item)}</div>`).join('');
                    box.style.display = 'block';
                    box.querySelectorAll('div').forEach(el => el.addEventListener('click', () => {
                        const item = JSON.parse(el.getAttribute('data-json'));
                        onSelect(item);
                        box.innerHTML = '';
                        box.style.display = 'none';
                    }));
                    window.addEventListener('scroll', scrollHandler, true);
                    window.addEventListener('resize', resizeHandler);
                } catch (e) {
                    box.innerHTML = '';
                    box.style.display = 'none';
                }
            });

            document.addEventListener('click', (ev) => { if (!input.contains(ev.target) && !box.contains(ev.target)) { box.innerHTML = ''; box.style.display = 'none'; window.removeEventListener('scroll', scrollHandler, true); window.removeEventListener('resize', resizeHandler); } });
        }

        // Attach typeaheads
        document.addEventListener('DOMContentLoaded', () => {
            // Plate number
            const pn = document.getElementById('input_plate_number');
            if (pn) {
                attachTypeahead(pn, 'admin/api_customers.php?q=', c => `${c.plate_number} — ${c.full_name}${c.car_mark ? ' — ' + c.car_mark : ''}${c.vin ? ' — VIN:'+c.vin : ''}` , (it) => {
                    pn.value = it.plate_number || '';
                    document.getElementById('input_customer_name').value = it.full_name || '';
                    document.getElementById('input_phone_number').value = it.phone || '';
                    const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = it.id || '';
                    const custIdInput = document.getElementById('input_customer_id'); if (custIdInput) custIdInput.value = it.customer_id || '';
                    // Populate VIN, make/model, and mileage if present
                    const vinInput = document.getElementById('input_vin'); if (vinInput) vinInput.value = it.vin || '';
                    const carMarkInput = document.getElementById('input_car_mark'); if (carMarkInput) carMarkInput.value = it.car_mark || '';
                    const mileageInput = document.getElementById('input_mileage'); if (mileageInput) mileageInput.value = it.mileage || '';
                    // When selecting a plate, also focus the customer name for quick edits
                    document.getElementById('input_customer_name').focus();
                });
            }

            // Customer name
            const cn = document.getElementById('input_customer_name');
            if (cn) {
                attachTypeahead(cn, 'admin/api_customers.php?customer_q=', c => `${c.full_name} — ${c.phone || ''}` , (it) => {
                    cn.value = it.full_name || '';
                    document.getElementById('input_phone_number').value = it.phone || '';
                    const custIdInput = document.getElementById('input_customer_id'); if (custIdInput) custIdInput.value = it.id;
                    // clear any previously selected vehicle id
                    const vid = document.getElementById('input_vehicle_id'); if (vid) vid.value = '';

                    // If the plate field is empty, try to autofill from the customer's most recent vehicle
                    const plateField = document.getElementById('input_plate_number');
                    if (plateField && (!plateField.value || plateField.value.trim() === '')) {
                        fetch('./admin/api_customers.php?customer_id=' + encodeURIComponent(item.id))
                            .then(r => r.json())
                            .then(cust => {
                                if (!cust || !Array.isArray(cust.vehicles) || cust.vehicles.length === 0) return;
                                const first = cust.vehicles[0];
                                plateField.value = first.plate_number || '';
                                document.getElementById('input_vin').value = first.vin || '';
                                document.getElementById('input_mileage').value = first.mileage || '';
                                document.getElementById('input_vehicle_id').value = first.id || '';
                            }).catch(()=>{});
                    }
                }
            );

            // Service manager
            const sm = document.getElementById('input_service_manager');
            if (sm) {
                attachTypeahead(sm, 'admin/api_users.php?q=', u => u.username, (it) => {
                    sm.value = it.username;
                    const hid = document.getElementById('input_service_manager_id'); if (hid) hid.value = it.id;
                });
            }

            // Car Make/Model suggestions
            attachTypeahead(
                document.getElementById('input_car_mark'),
                'admin/api_customers.php?car_mark_q=',
                mark => mark,
                (mark) => {
                    document.getElementById('input_car_mark').value = mark;
                }
            );

            // VIN suggestions
            attachTypeahead(
                document.getElementById('input_vin'),
                'admin/api_customers.php?vin_q=',
                vin => vin,
                (vin) => {
                    document.getElementById('input_vin').value = vin;
                }
            );

            // Add blur events for smart auto-fill
            const plateInput = document.getElementById('input_plate_number');
            if (plateInput) {
                plateInput.addEventListener('blur', () => {
                    const val = plateInput.value.trim();
                    if (!val) return;

                    const customerNameElem = document.getElementById('input_customer_name');
                    const customerName = customerNameElem.value.trim();

                    fetch('./admin/api_customers.php?plate=' + encodeURIComponent(val))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            // Only set customer name/phone if empty
                            if (!customerName && data.full_name) customerNameElem.value = data.full_name || '';
                            if (data.phone) document.getElementById('input_phone_number').value = data.phone || '';
                            // Always set VIN, car make/model and vehicle id
                            document.getElementById('input_vehicle_id').value = data.id;
                            document.getElementById('input_vin').value = data.vin || '';
                            document.getElementById('input_car_mark').value = data.car_mark || '';
                            document.getElementById('input_mileage').value = data.mileage || '';
                        }).catch(e=>{});
                });
            }

            // Phone number blur for auto-fill
            const phoneInput = document.getElementById('input_phone_number');
            if (phoneInput) {
                phoneInput.addEventListener('blur', () => {
                    const val = phoneInput.value.trim();
                    if (!val) return;

                    // Only auto-fill if customer fields are empty
                    const customerName = document.getElementById('input_customer_name').value.trim();
                    const plateNumber = document.getElementById('input_plate_number').value.trim();
                    const vehicleId = document.getElementById('input_vehicle_id').value;

                    if (customerName || plateNumber || vehicleId) {
                        return;
                    }

                    fetch('./admin/api_customers.php?phone=' + encodeURIComponent(val))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            document.getElementById('input_customer_name').value = data.full_name || '';
                            document.getElementById('input_plate_number').value = data.plate_number || '';
                            document.getElementById('input_vehicle_id').value = data.id;
                        }).catch(e=>{});
                });
            }
        });

        // Fetch item suggestions
        function fetchItemSuggestions(input) {
            const query = input.value.trim();
            const box = input.nextElementSibling;

            if (query.length < 2) {
                box.style.display = 'none';
                return;
            }

            const vehicle = document.getElementById('input_car_mark').value.trim();
            const params = new URLSearchParams({ q: query });
            if (vehicle) params.set('vehicle', vehicle);

            fetch(`admin/api_labors_parts.php?${params}`)
                .then(r => r.json())
                .then(data => {
                    const items = data.data || data;
                    if (!Array.isArray(items) || items.length === 0) {
                        box.style.display = 'none';
                        return;
                    }

                    box.innerHTML = items.map(item => {
                        const vehicleVal = document.getElementById('input_car_mark').value.trim();
                        const priceToShow = item.suggested_price > 0 ? item.suggested_price : item.default_price;
                        const priceIndicator = vehicleVal ? (item.has_vehicle_price ? '<div class="text-xs text-green-700">vehicle price</div>' : '<div class="text-xs text-yellow-700">default price</div>') : '';

                        return `
                            <div class="suggestion-item" onclick="selectItem(this, ${JSON.stringify(item).replace(/"/g, '&quot;')})">
                                <div class="flex justify-between items-start">
                                    <div class="flex-1">
                                        <div class="font-medium text-sm">${item.name}</div>
                                        <div class="text-xs text-gray-600">${item.description || ''}${item.vehicle_make_model ? ` — <span class="text-gray-500">${item.vehicle_make_model}</span>` : ''}</div>
                                    </div>
                                    <div class="text-right ml-3">
                                        <div class="text-sm font-medium text-blue-600">${priceToShow ? priceToShow + ' ₾' : ''}</div>
                                        ${priceIndicator}
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('');
                    box.style.display = 'block';
                })
                .catch(() => box.style.display = 'none');
        }

        // Select item from suggestions
        function selectItem(el, item) {
            const card = el.closest('.item-card');
            const input = card.querySelector('.item-name-input');
            input.value = item.name;

            // Set DB metadata
            card.querySelector('.item-db-id').value = item.id || '';
            card.querySelector('.item-db-type').value = item.type || '';
            card.querySelector('.item-db-vehicle').value = item.vehicle_make_model || '';
            card.querySelector('.item-db-price-source').value = item.has_vehicle_price ? 'vehicle' : 'default';

            // Fill appropriate price field
            const priceToUse = (typeof item.suggested_price !== 'undefined' && item.suggested_price !== null) ? item.suggested_price : item.default_price;
            if (item.type === 'part') {
                const partInput = card.querySelector('.item-price-part');
                if (priceToUse > 0 && (!partInput.value || partInput.value == '0')) {
                    partInput.value = priceToUse;
                }
            } else if (item.type === 'labor') {
                const svcInput = card.querySelector('.item-price-svc');
                if (priceToUse > 0 && (!svcInput.value || svcInput.value == '0')) {
                    svcInput.value = priceToUse;
                }
            }

            // Add price source indicator
            let badgeEl = card.querySelector('.price-source');
            if (!badgeEl) {
                badgeEl = document.createElement('div');
                badgeEl.className = 'price-source text-xs text-gray-500 mt-1';
                input.parentNode.appendChild(badgeEl);
            }

            const vehicleVal = document.getElementById('input_car_mark').value.trim();
            if (vehicleVal) {
                badgeEl.textContent = item.has_vehicle_price ? 'Vehicle price' : 'Default price';
                badgeEl.className = item.has_vehicle_price ? 'price-source text-xs text-green-700 mt-1' : 'price-source text-xs text-yellow-700 mt-1';
            } else {
                badgeEl.textContent = '';
                badgeEl.className = 'price-source text-xs text-gray-500 mt-1';
            }

            el.closest('.suggestions-box').style.display = 'none';
            calculateTotals();
            updateProgress();
        }

        // Photo handling
        document.getElementById('btn_take_photo').addEventListener('click', () => {
            document.getElementById('input_images').setAttribute('capture', 'environment');
            document.getElementById('input_images').click();
        });

        document.getElementById('btn_upload_photo').addEventListener('click', () => {
            document.getElementById('input_images').removeAttribute('capture');
            document.getElementById('input_images').click();
        });

        document.getElementById('input_images').addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            selectedFiles = files;

            const preview = document.getElementById('photo-preview');
            preview.innerHTML = '';

            files.forEach((file, index) => {
                if (file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const div = document.createElement('div');
                        div.className = 'photo-item';
                        div.innerHTML = `
                            <img src="${e.target.result}" alt="Photo ${index + 1}">
                            <button type="button" class="photo-remove" onclick="removePhoto(${index})">
                                <i class="fas fa-times"></i>
                            </button>
                        `;
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });

        function removePhoto(index) {
            selectedFiles.splice(index, 1);
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            document.getElementById('input_images').files = dt.files;
            document.getElementById('input_images').dispatchEvent(new Event('change'));
        }

        // Form preparation and validation
        function prepareData() {
            // Update hidden fields
            document.getElementById('hidden_creation_date').value = document.getElementById('input_creation_date').value;
            document.getElementById('hidden_service_manager').value = document.getElementById('input_service_manager').value;
            document.getElementById('hidden_customer_name').value = document.getElementById('input_customer_name').value;
            document.getElementById('hidden_phone_number').value = document.getElementById('input_phone_number').value;
            document.getElementById('hidden_car_mark').value = document.getElementById('input_car_mark').value;
            document.getElementById('hidden_plate_number').value = document.getElementById('input_plate_number').value;
            document.getElementById('hidden_vin').value = document.getElementById('input_vin').value;
            document.getElementById('hidden_vehicle_id').value = document.getElementById('input_vehicle_id').value;
            document.getElementById('hidden_mileage').value = document.getElementById('input_mileage').value;

            // Prepare items data
            const items = [];
            document.querySelectorAll('.item-card').forEach((card, index) => {
                const item = {
                    name: card.querySelector('.item-name-input').value.trim(),
                    qty: parseFloat(card.querySelector('.item-qty').value) || 1,
                    price_part: parseFloat(card.querySelector('.item-price-part').value) || 0,
                    discount_part: parseFloat(card.querySelector('.item-discount-part').value) || 0,
                    price_svc: parseFloat(card.querySelector('.item-price-svc').value) || 0,
                    discount_svc: parseFloat(card.querySelector('.item-discount-svc').value) || 0,
                    technician: card.querySelector('.item-tech').value.trim(),
                    tech_id: card.querySelector('.item-tech-id').value || null,
                    db_id: card.querySelector('.item-db-id').value,
                    db_type: card.querySelector('.item-db-type').value,
                    db_vehicle: card.querySelector('.item-db-vehicle').value,
                    db_price_source: card.querySelector('.item-db-price-source').value
                };

                if (item.name || item.price_part > 0 || item.price_svc > 0) {
                    items.push(item);
                }
            });

            // Add items as hidden inputs
            items.forEach((item, index) => {
                const fields = {
                    'item_name_': item.name,
                    'item_qty_': item.qty,
                    'item_price_part_': item.price_part,
                    'item_discount_part_': item.discount_part,
                    'item_price_svc_': item.price_svc,
                    'item_discount_svc_': item.discount_svc,
                    'item_tech_': item.technician,
                    'item_tech_id_': item.tech_id,
                    'item_db_id_': item.db_id,
                    'item_db_type_': item.db_type,
                    'item_db_vehicle_': item.db_vehicle,
                    'item_db_price_source_': item.db_price_source
                };

                Object.keys(fields).forEach(key => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key + index;
                    input.value = fields[key];
                    document.getElementById('mobile-invoice-form').appendChild(input);
                });
            });

            return true;
        }

        // Handle save
        function handleSave() {
            // Validation
            const customerName = document.getElementById('input_customer_name').value.trim();
            const serviceManager = document.getElementById('input_service_manager').value.trim();
            const plateNumber = document.getElementById('input_plate_number').value.trim();
            const vehicleId = document.getElementById('input_vehicle_id').value.trim();

            if (!customerName) {
                alert('Please enter a customer name.');
                document.getElementById('input_customer_name').focus();
                return false;
            }

            if (!vehicleId && !plateNumber.trim()) {
                alert('Please select a vehicle or enter a plate number.');
                document.getElementById('input_plate_number').focus();
                return false;
            }

            if (!serviceManager) {
                alert('Please enter a service manager.');
                document.getElementById('input_service_manager').focus();
                return false;
            }

            if (prepareData()) {
                showToast('Saving invoice...');
                document.getElementById('mobile-invoice-form').submit();
                return true;
            }
            return false;
        }

        // Handle save and print
        function handleSaveAndPrint() {
            const printInput = document.createElement('input');
            printInput.type = 'hidden';
            printInput.name = 'print_after_save';
            printInput.value = '1';
            document.getElementById('mobile-invoice-form').appendChild(printInput);
            handleSave();
        }

        // Show toast
        function showToast(message) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-message').textContent = message;
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Update progress on input changes
        document.addEventListener('input', updateProgress);
    </script>
</body>
</html>