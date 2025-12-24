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

// Support loading a saved invoice into the editor for editing
$serverInvoice = null;
if (isset($_GET['edit_id'])) {
    $loadId = (int)$_GET['edit_id'];
    try {
        if (!isset($pdo)) {
            throw new Exception("Database connection not available");
        }
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$loadId]);
        $inv = $stmt->fetch();
        if ($inv) {
            $inv_items = json_decode($inv['items'], true) ?: [];
            $inv_customer = null;
            if (!empty($inv['vehicle_id'])) {
                $s = $pdo->prepare('SELECT v.*, c.full_name, c.phone, c.email, c.notes FROM vehicles v JOIN customers c ON v.customer_id = c.id WHERE v.id = ? LIMIT 1');
                $s->execute([(int)$inv['vehicle_id']]);
                $inv_customer = $s->fetch();
            }
            $sm_username = '';
            if (!empty($inv['service_manager_id'])) {
                $s = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
                $s->execute([(int)$inv['service_manager_id']]);
                $sm = $s->fetch();
                if ($sm) $sm_username = $sm['username'];
            }
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
                'oils' => !empty($inv['oils']) ? json_decode($inv['oils'], true) : [],
                'images' => !empty($inv['images']) ? json_decode($inv['images'], true) : [],
                'grand_total' => (float)$inv['grand_total'],
                'parts_total' => (float)$inv['parts_total'],
                'service_total' => (float)$inv['service_total'],
                'parts_discount_percent' => isset($inv['parts_discount_percent']) ? (float)$inv['parts_discount_percent'] : 0.0,
                'service_discount_percent' => isset($inv['service_discount_percent']) ? (float)$inv['service_discount_percent'] : 0.0,
            ];
            if ($inv_customer) $serverInvoice['customer'] = $inv_customer;
            if (!empty($sm_username)) $serverInvoice['service_manager_username'] = $sm_username;
        }
    } catch (Exception $e) {
        error_log("Database error loading invoice $loadId for mobile edit: " . $e->getMessage());
    }
}

// Load oil data for the mobile invoice form
$oilBrands = $pdo->query("SELECT * FROM oil_brands ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$oilViscosities = $pdo->query("SELECT * FROM oil_viscosities ORDER BY viscosity")->fetchAll(PDO::FETCH_ASSOC);
$oilPrices = $pdo->query("SELECT * FROM oil_prices ORDER BY brand_id, viscosity_id, package_type")->fetchAll(PDO::FETCH_ASSOC);

$oilPriceLookup = [];
foreach ($oilPrices as $price) {
    $key = $price['brand_id'] . '_' . $price['viscosity_id'] . '_' . $price['package_type'];
    $oilPriceLookup[$key] = $price['price'];
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
            position: relative; /* For suggestion dropdowns */
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
            position: relative; /* For suggestion dropdowns */
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

        .fab-container {
            position: fixed;
            top: 50%;
            right: 1.5rem;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column-reverse;
            gap: 0.75rem;
            align-items: center;
            z-index: 100;
        }

        .fab {
            position: relative;
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
        .fab.fab-secondary {
            background: #4b5563;
            width: 3rem;
            height: 3rem;
            font-size: 1.1rem;
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

        /* Multi-step form styles */
        .form-step {
            display: none;
            animation: fadeIn 0.5s;
        }
        .form-step.active-step {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .progress-container {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 1.5rem;
            max-width: 100%;
        }
        .progress-bar-steps {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            background: #667eea;
            width: 0%;
            z-index: 1;
            transition: width 0.4s ease;
        }
        .progress-line {
            position: absolute;
            top: 50%;
            left: 0;
            transform: translateY(-50%);
            height: 4px;
            background: #e5e7eb;
            width: 100%;
            z-index: 0;
        }
        .step-indicator {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: white;
            border: 2px solid #e5e7eb;
            color: #9ca3af;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            z-index: 2;
            transition: all 0.4s ease;
        }
        .step-indicator.active {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .step-indicator.completed {
            border-color: #667eea;
            background: #667eea;
            color: white;
        }
        .step-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5rem;
        }
        .step-navigation button {
            width: auto;
            min-width: 120px;
        }
        .review-section {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
        }
        .review-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 0.5rem;
        }
        .review-item {
            display: flex;
            justify-content: space-between;
            padding: 0.25rem 0;
            font-size: 0.9rem;
        }
        .review-item span:first-child {
            color: #4b5563;
        }
        .review-item span:last-child {
            font-weight: 500;
            color: #111827;
        }

        /* Mileage unit toggle */
        .unit-toggle {
            display: flex;
            border-radius: 0.5rem;
            overflow: hidden;
            border: 1px solid #d1d5db;
        }
        .unit-toggle button {
            padding: 0.5rem 0.75rem;
            border: none;
            background-color: #f9fafb;
            color: #6b7280;
            cursor: pointer;
            transition: background-color 0.2s, color 0.2s;
        }
        .unit-toggle button.active {
            background-color: #3b82f6;
            color: white;
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
        <header style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 1.5rem; text-align: center; position: relative;">
            <h1 style="font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem;">
                <i class="fas fa-file-invoice-dollar mr-2"></i>
                Mobile Invoice Creator
            </h1>
            <p style="font-size: 0.9rem; opacity: 0.9;">Create invoices on the go</p>
        </header>

        <!-- Progress Bar -->
        <div class="progress-container">
            <div class="progress-line"></div>
            <div class="progress-bar-steps" id="progress-bar-steps"></div>
            <div class="step-indicator" data-step="1">1</div>
            <div class="step-indicator" data-step="2">2</div>
            <div class="step-indicator" data-step="3">3</div>
            <div class="step-indicator" data-step="4">4</div>
            <div class="step-indicator" data-step="5">✓</div>
        </div>

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
            <input type="hidden" name="mileage" id="hidden_mileage">
            <input type="hidden" name="parts_total" id="hidden_parts_total">
            <input type="hidden" name="service_total" id="hidden_service_total">
            <input type="hidden" name="grand_total" id="hidden_grand_total">
            <input type="hidden" name="parts_discount_percent" id="hidden_parts_discount">
            <input type="hidden" name="service_discount_percent" id="hidden_service_discount">
            <input type="hidden" name="service_manager_id" id="input_service_manager_id" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
            <input type="hidden" name="vehicle_id" id="input_vehicle_id">

            <!-- Step 1: Vehicle Section -->
            <div class="form-step active-step" data-step="1">
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
                        <div class="suggestions-box" style="display: none;"></div>
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="input_car_mark">
                            <i class="fas fa-car-side mr-1"></i>
                            Make/Model
                        </label>
                        <input type="text" id="input_car_mark" class="input-field" placeholder="Toyota Camry">
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="input_vin">
                            <i class="fas fa-hashtag mr-1"></i>
                            VIN
                        </label>
                        <input type="text" id="input_vin" class="input-field" placeholder="Vehicle VIN">
                    </div>

                    <div class="input-group">
                        <label class="input-label" for="input_mileage">
                            <i class="fas fa-tachometer-alt mr-1"></i>
                            Mileage
                        </label>
                        <div class="flex items-center gap-2">
                            <input type="text" id="input_mileage" class="input-field" placeholder="150000 km" style="width: auto; flex-grow: 1;">
                             <div class="unit-toggle" style="flex-shrink: 0;">
                                <button type="button" class="unit-btn active" data-unit="km">KM</button>
                                <button type="button" class="unit-btn" data-unit="mi">MI</button>
                            </div>
                            <input type="hidden" id="mileage_unit" value="km">
                        </div>
                    </div>
                </div>
                <div class="step-navigation form-section">
                    <button type="button" class="btn-primary next-btn" style="margin-left: auto;">Next Step</button>
                </div>
            </div>

            <!-- Step 2: Customer Section -->
            <div class="form-step" data-step="2">
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
                        <div class="suggestions-box" style="display: none;"></div>
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
                        <div class="suggestions-box" style="display: none;"></div>
                    </div>
                </div>
                <div class="step-navigation form-section">
                    <button type="button" class="btn-secondary prev-btn">Previous</button>
                    <button type="button" class="btn-primary next-btn">Next Step</button>
                </div>
            </div>

            <!-- Step 3: Items Section -->
            <div class="form-step" data-step="3">
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

                    <!-- Oils Section -->
                    <div class="section-header" style="margin-top:0.75rem;">
                        <i class="fas fa-oil-can"></i>
                        Oils
                    </div>
                    <div id="oils-container">
                        <!-- Oil cards will be added here -->
                    </div>

                    <button type="button" onclick="addOil()" class="btn-secondary" style="margin-bottom: 1rem;">
                        <i class="fas fa-plus mr-2"></i>
                        Add Oil
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
                        <div class="total-row">
                            <span class="total-label">Oils Total:</span>
                            <span class="total-value" id="display_oils_total">0 ₾</span>
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
                <div class="step-navigation form-section">
                    <button type="button" class="btn-secondary prev-btn">Previous</button>
                    <button type="button" class="btn-primary next-btn">Next Step</button>
                </div>
            </div>

            <!-- Step 4: Photos Section -->
            <div class="form-step" data-step="4">
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
                        <button type="button" id="btn_multi_capture" class="btn-secondary" style="flex: 1;">
                            <i class="fas fa-images mr-1"></i>
                            Multi-Capture
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
                <div class="step-navigation form-section">
                    <button type="button" class="btn-secondary prev-btn">Previous</button>
                    <button type="button" class="btn-primary next-btn">Review Invoice</button>
                </div>
            </div>

            <!-- Step 5: Review & Save Section -->
            <div class="form-step" data-step="5">
                <div class="form-section">
                    <div class="section-header">
                        <i class="fas fa-check-circle"></i>
                        Review & Save
                    </div>
                    <div id="review-container">
                        <!-- Review content will be populated by JS -->
                    </div>
                </div>
                <div class="step-navigation form-section" style="padding-bottom: 5rem;">
                    <button type="button" class="btn-secondary prev-btn">Previous</button>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; width: 100%;">
                        <button type="button" onclick="handleSave()" class="btn-primary">
                            <i class="fas fa-save mr-2"></i>
                            Save Invoice
                        </button>
                        <button type="button" onclick="handleSaveAndPrint()" class="btn-secondary">
                            <i class="fas fa-print mr-2"></i>
                            Save & Print
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Floating Action Button Group -->
    <div class="fab-container">
        <a href="manager.php" class="fab fab-secondary">
            <i class="fas fa-list-ul"></i>
        </a>
        <div class="fab" onclick="addItem()">
            <i class="fas fa-plus"></i>
        </div>
    </div>

    <!-- Toast Notifications -->
    <div id="toast" class="toast">
        <i class="fas fa-check-circle mr-2"></i>
        <span id="toast-message">Invoice saved successfully!</span>
    </div>

    <?php if (!empty($serverInvoice)): ?>
    <script>
        window.serverInvoice = <?php echo json_encode($serverInvoice, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <?php endif; ?>

    <script>
        // Global variables
        let itemCount = 0;
        let selectedFiles = [];
        let currentStep = 1;
        const totalSteps = 5;

        // Oil data
        let oilBrands = <?php echo json_encode($oilBrands ?? []); ?>;
        let oilViscosities = <?php echo json_encode($oilViscosities ?? []); ?>;
        let oilPriceLookup = <?php echo json_encode($oilPriceLookup ?? []); ?>;
        let oilCount = 0;

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            // Add initial items
            for(let i = 0; i < 1; i++) {
                addItem();
            }
            calculateTotals();
            updateStep();

            // Mileage unit toggle logic
            const mileageInput = document.getElementById('input_mileage');
            const mileageUnitInput = document.getElementById('mileage_unit');
            const unitToggle = document.querySelector('.unit-toggle');

            if (unitToggle) {
                unitToggle.addEventListener('click', (e) => {
                    if (e.target.matches('.unit-btn')) {
                        const selectedUnit = e.target.dataset.unit;
                        
                        unitToggle.querySelectorAll('.unit-btn').forEach(btn => btn.classList.remove('active'));
                        e.target.classList.add('active');
                        
                        mileageUnitInput.value = selectedUnit;
                        mileageInput.placeholder = `Enter mileage in ${selectedUnit}`;
                    }
                });
            }

            // Set default service manager
            const smInput = document.getElementById('input_service_manager');
            const smIdInput = document.getElementById('input_service_manager_id');
            if (smInput && (!smInput.value || smInput.value.trim() === '')) {
                smInput.value = '<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>';
            }
            if (smIdInput && (!smIdInput.value || smIdInput.value == 0)) {
                smIdInput.value = '<?php echo (int)($_SESSION['user_id'] ?? 0); ?>';
            }

            // Load server invoice if in edit mode
            if (window.serverInvoice) {
                loadServerInvoice(window.serverInvoice);
            }

            // Multi-step form logic
            const nextBtns = document.querySelectorAll('.next-btn');
            const prevBtns = document.querySelectorAll('.prev-btn');

            nextBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (validateStep(currentStep)) {
                        if (currentStep < totalSteps) {
                            currentStep++;
                            updateStep();
                        }
                    }
                });
            });

            prevBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    if (currentStep > 1) {
                        currentStep--;
                        updateStep();
                    }
                });
            });

            // Pre-fill form with serverInvoice data if available (handled by loadServerInvoice if present)
            // If a server invoice is provided, loadServerInvoice(window.serverInvoice) will populate fields, items, images and oils.
        });

        function updateStep() {
            // Update step indicators
            document.querySelectorAll('.step-indicator').forEach(indicator => {
                const step = parseInt(indicator.dataset.step);
                indicator.classList.remove('active', 'completed');
                if (step < currentStep) {
                    indicator.classList.add('completed');
                    indicator.innerHTML = '✓';
                } else if (step === currentStep) {
                    indicator.classList.add('active');
                } else {
                     indicator.innerHTML = step === 5 ? '✓' : step;
                }
            });

            // Update progress bar
            const progress = ((currentStep - 1) / (totalSteps - 1)) * 100;
            document.getElementById('progress-bar-steps').style.width = `${progress}%`;

            // Show current step form
            document.querySelectorAll('.form-step').forEach(step => {
                step.classList.remove('active-step');
            });
            document.querySelector(`.form-step[data-step="${currentStep}"]`).classList.add('active-step');

            // Populate review step if active
            if (currentStep === 5) {
                populateReview();
            }
        }

        function validateStep(step) {
            if (step === 1) {
                if (!document.getElementById('input_plate_number').value.trim()) {
                    showToast('Plate number is required.', true);
                    return false;
                }
            }
            if (step === 2) {
                if (!document.getElementById('input_customer_name').value.trim()) {
                    showToast('Customer name is required.', true);
                    return false;
                }
            }
            return true;
        }

        function populateReview() {
            const reviewContainer = document.getElementById('review-container');
            const items = [];
            document.querySelectorAll('.item-card').forEach(card => {
                const nameEl = card.querySelector('.item-name-input');
                const qtyEl = card.querySelector('.item-qty');
                const partEl = card.querySelector('.item-price-part');
                const svcEl = card.querySelector('.item-price-svc');
                const name = nameEl ? nameEl.value.trim() : '';
                const qty = qtyEl ? qtyEl.value : '1';
                const partPrice = partEl ? partEl.value : '0';
                const svcPrice = svcEl ? svcEl.value : '0';
                if (name) {
                    items.push(name + ' (Qty: ' + qty + ') - Part: ' + partPrice + '₾, Svc: ' + svcPrice + '₾');
                }
            });

            const itemsHtml = items.length ? items.map(item => '<div class="review-item"><span>-</span> <span>' + item + '</span></div>').join('') : '<div class="review-item"><span>No items added.</span></div>';
            const oilsTotalText = document.getElementById('display_oils_total') ? document.getElementById('display_oils_total').textContent : '0 ₾';

            const plate = document.getElementById('input_plate_number') ? document.getElementById('input_plate_number').value : '';
            const carMark = document.getElementById('input_car_mark') ? (document.getElementById('input_car_mark').value || 'N/A') : 'N/A';
            const customerName = document.getElementById('input_customer_name') ? document.getElementById('input_customer_name').value : '';
            const phone = document.getElementById('input_phone_number') ? (document.getElementById('input_phone_number').value || 'N/A') : 'N/A';
            const partsTotal = document.getElementById('display_parts_total') ? document.getElementById('display_parts_total').textContent : '0 ₾';
            const serviceTotal = document.getElementById('display_service_total') ? document.getElementById('display_service_total').textContent : '0 ₾';
            const grandTotal = document.getElementById('display_grand_total') ? document.getElementById('display_grand_total').textContent : '0 ₾';

            reviewContainer.innerHTML = '' +
                '<div class="review-section">' +
                    '<div class="review-title">Vehicle & Customer</div>' +
                    '<div class="review-item"><span>Plate Number:</span> <span>' + plate + '</span></div>' +
                    '<div class="review-item"><span>Make/Model:</span> <span>' + carMark + '</span></div>' +
                    '<div class="review-item"><span>Customer:</span> <span>' + customerName + '</span></div>' +
                    '<div class="review-item"><span>Phone:</span> <span>' + phone + '</span></div>' +
                '</div>' +
                '<div class="review-section">' +
                    '<div class="review-title">Items</div>' +
                    itemsHtml +
                '</div>' +
                '<div class="review-section">' +
                    '<div class="review-title">Totals</div>' +
                    '<div class="review-item"><span>Parts Total:</span> <span>' + partsTotal + '</span></div>' +
                    '<div class="review-item"><span>Service Total:</span> <span>' + serviceTotal + '</span></div>' +
                    '<div class="review-item"><span>Oils Total:</span> <span>' + oilsTotalText + '</span></div>' +
                    '<div class="review-item grand-total"><span>Grand Total:</span> <span>' + grandTotal + '</span></div>' +
                '</div>' +
                '<div class="review-section">' +
                    '<div class="review-title">Photos</div>' +
                    '<div class="review-item"><span>' + selectedFiles.length + ' photo(s) attached.</span></div>' +
                '</div>';
        }

        // Add item function
        function addItem(existingItem = null) {
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

                <input type="hidden" class="item-tech-id">
                <input type="hidden" class="item-db-id">
                <input type="hidden" class="item-db-type">
                <input type="hidden" class="item-db-vehicle">
                <input type="hidden" class="item-db-price-source">
            `;

            container.appendChild(itemCard);

            // Attach typeahead to the new technician input
            const techInput = itemCard.querySelector('.item-tech');
            const techIdInput = itemCard.querySelector('.item-tech-id');

            attachTypeahead(
                techInput,
                'api_technicians_search.php?q=',
                (item) => item.name,
                (item) => {
                    techInput.value = item.name;
                    if (techIdInput) techIdInput.value = item.id;
                }
            );

            // Clear tech ID if user types a custom name
            techInput.addEventListener('input', () => {
                if (techIdInput) techIdInput.value = '';
            });

            // If editing an existing item, populate the fields
            if (existingItem) {
                itemCard.querySelector('.item-name-input').value = existingItem.name;
                itemCard.querySelector('.item-qty').value = existingItem.qty;
                itemCard.querySelector('.item-price-part').value = existingItem.price_part;
                itemCard.querySelector('.item-discount-part').value = existingItem.discount_part;
                itemCard.querySelector('.item-price-svc').value = existingItem.price_svc;
                itemCard.querySelector('.item-discount-svc').value = existingItem.discount_svc;
                itemCard.querySelector('.item-tech').value = existingItem.technician;

                // (Oil functions moved to top-level scope)
                itemCard.querySelector('.item-db-id').value = existingItem.db_id || '';
                itemCard.querySelector('.item-db-type').value = existingItem.db_type || '';
                itemCard.querySelector('.item-db-vehicle').value = existingItem.db_vehicle || '';
                itemCard.querySelector('.item-db-price-source').value = existingItem.has_vehicle_price ? 'vehicle' : 'default';

                // Fill appropriate price field
                const priceToUse = (typeof existingItem.suggested_price !== 'undefined' && existingItem.suggested_price !== null) ? existingItem.suggested_price : existingItem.default_price;
                if (existingItem.type === 'part') {
                    const partInput = itemCard.querySelector('.item-price-part');
                    if (priceToUse > 0 && (!partInput.value || partInput.value == '0')) {
                        partInput.value = priceToUse;
                    }
                } else if (existingItem.type === 'labor') {
                    const svcInput = itemCard.querySelector('.item-price-svc');
                    if (priceToUse > 0 && (!svcInput.value || svcInput.value == '0')) {
                        svcInput.value = priceToUse;
                    }
                }
            }

            updateProgress();
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

// Oil functions (moved here to top-level scope)
            function addOil(existingOil = null) {
                oilCount++;
                const container = document.getElementById('oils-container');
                const card = document.createElement('div');
                card.className = 'item-card oil-card';
                card.id = `oil-${oilCount}`;

                const brandOptions = oilBrands.map(b => `<option value="${b.id}">${b.name}</option>`).join('');
                const viscosityOptions = oilViscosities.map(v => `<option value="${v.id}">${v.viscosity}</option>`).join('');

                card.innerHTML = `
                    <div class="item-header">
                        <span class="item-name">Oil ${oilCount}</span>
                        <button type="button" class="remove-item" onclick="removeOil(${oilCount})">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="input-group">
                        <label class="price-label">Brand</label>
                        <select class="input-field oil-brand" onchange="updateOilCardPrice(this.closest('.oil-card'))">
                            <option value="">Select Brand</option>
                            ${brandOptions}
                        </select>
                    </div>
                    <div class="input-group">
                        <label class="price-label">Viscosity</label>
                        <select class="input-field oil-viscosity" onchange="updateOilCardPrice(this.closest('.oil-card'))">
                            <option value="">Select Viscosity</option>
                            ${viscosityOptions}
                        </select>
                    </div>
                    <div class="price-grid">
                        <div class="price-input">
                            <label class="price-label">Package</label>
                            <select class="input-field oil-package" onchange="updateOilCardPrice(this.closest('.oil-card'))">
                                <option value="">Select Package</option>
                                <option value="canned">Canned</option>
                                <option value="1lt">1 Liter</option>
                                <option value="4lt">4 Liter</option>
                                <option value="5lt">5 Liter</option>
                            </select>
                        </div>
                        <div class="price-input">
                            <label class="price-label">Qty</label>
                            <input type="number" min="1" value="1" class="input-field oil-qty" oninput="updateOilCardPrice(this.closest('.oil-card'))">
                        </div>
                        <div class="price-input">
                            <label class="price-label">Unit</label>
                            <input type="number" class="input-field oil-unit-price" readonly value="0">
                        </div>
                        <div class="price-input">
                            <label class="price-label">Disc %</label>
                            <input type="number" min="0" max="100" value="0" class="input-field oil-discount" oninput="updateOilCardPrice(this.closest('.oil-card'))">
                        </div>
                        <div class="price-input" style="flex:1;">
                            <label class="price-label">Total</label>
                            <div class="input-field" style="padding:0.5rem;"> <span class="oil-total">0.00 ₾</span> </div>
                        </div>
                    </div>
                `;

                container.appendChild(card);

                // If editing, populate
                if (existingOil) {
                    card.querySelector('.oil-brand').value = existingOil.brand_id || '';
                    card.querySelector('.oil-viscosity').value = existingOil.viscosity_id || '';
                    card.querySelector('.oil-package').value = existingOil.package_type || '';
                    card.querySelector('.oil-qty').value = existingOil.qty || 1;
                    card.querySelector('.oil-discount').value = existingOil.discount || 0;
                    updateOilCardPrice(card);
                }
            }

            function updateOilCardPrice(card) {
                if (!card) return;
                const brandId = card.querySelector('.oil-brand')?.value || '';
                const viscosityId = card.querySelector('.oil-viscosity')?.value || '';
                const packageType = card.querySelector('.oil-package')?.value || '';
                const qty = parseFloat(card.querySelector('.oil-qty')?.value) || 0;
                const discount = parseFloat(card.querySelector('.oil-discount')?.value) || 0;
                if (brandId && viscosityId && packageType) {
                    const key = brandId + '_' + viscosityId + '_' + packageType;
                    const unit = parseFloat(oilPriceLookup[key]) || 0;
                    const discounted = unit * (1 - discount / 100);
                    const total = qty * discounted;
                    card.querySelector('.oil-unit-price').value = unit.toFixed(2);
                    card.querySelector('.oil-total').textContent = total.toFixed(2) + ' ₾';
                } else {
                    card.querySelector('.oil-unit-price').value = '0.00';
                    card.querySelector('.oil-total').textContent = '0.00 ₾';
                }
                updateOilsTotal();
            }

            function updateOilsTotal() {
                let total = 0;
                document.querySelectorAll('.oil-card').forEach(card => {
                    const t = parseFloat(card.querySelector('.oil-total').textContent.replace(' ₾','')) || 0;
                    total += t;
                });
                const oilsDisplayEl = document.getElementById('display_oils_total'); if (oilsDisplayEl) oilsDisplayEl.textContent = total.toFixed(2) + ' ₾';
            }

            function removeOil(id) {
                const card = document.getElementById(`oil-${id}`);
                if (card) { card.remove(); updateOilsTotal(); }
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

            let oilsTotal = 0;
            document.querySelectorAll('.oil-card').forEach(card => {
                const t = parseFloat(card.querySelector('.oil-total')?.textContent.replace(' ₾','')) || 0;
                oilsTotal += t;
            });

            const grandTotal = partsTotal + serviceTotal + oilsTotal;

            document.getElementById('display_parts_total').textContent = partsTotal.toFixed(2) + ' ₾';
            document.getElementById('display_service_total').textContent = serviceTotal.toFixed(2) + ' ₾';
            const oilsDisplayEl = document.getElementById('display_oils_total'); if (oilsDisplayEl) oilsDisplayEl.textContent = oilsTotal.toFixed(2) + ' ₾';
            document.getElementById('display_grand_total').textContent = grandTotal.toFixed(2) + ' ₾';

            // Update hidden fields
            document.getElementById('hidden_parts_total').value = partsTotal.toFixed(2);
            document.getElementById('hidden_service_total').value = serviceTotal.toFixed(2);
            document.getElementById('hidden_grand_total').value = grandTotal.toFixed(2);
            document.getElementById('hidden_parts_discount').value = globalPartsDiscount;
            document.getElementById('hidden_service_discount').value = globalServiceDiscount;
            // Also set hidden oils if present
            const hiddenOilsField = document.querySelector('input[name="hidden_oils_json"]');
            if (hiddenOilsField) hiddenOilsField.value = JSON.stringify(Array.from(document.querySelectorAll('.oil-card')).map(card => ({
                brand_id: parseInt(card.querySelector('.oil-brand')?.value || 0) || null,
                viscosity_id: parseInt(card.querySelector('.oil-viscosity')?.value || 0) || null,
                package_type: card.querySelector('.oil-package')?.value || '',
                qty: parseInt(card.querySelector('.oil-qty')?.value || 1) || 1,
                discount: parseFloat(card.querySelector('.oil-discount')?.value || 0) || 0
            })));

        }

        // Update progress bar
        function updateProgress() {
            const totalFields = 8; // Basic required fields
            const itemFields = document.querySelectorAll('.item-card').length * 2; // Rough estimate
            const totalPossible = totalFields + itemFields;

            let filledFields = 0;

            // Check basic fields
            ['input_plate_number', 'input_customer_name', 'input_service_manager'].forEach(id => {
                const el = document.getElementById(id);
                if (el && el.value && el.value.trim()) filledFields++;
            });

            // Check items
            document.querySelectorAll('.item-card').forEach(card => {
                const nameEl = card.querySelector('.item-name-input');
                if (nameEl && nameEl.value && nameEl.value.trim()) filledFields++;
                const partEl = card.querySelector('.item-price-part');
                const svcEl = card.querySelector('.item-price-svc');
                const partVal = partEl ? parseFloat(partEl.value) : 0;
                const svcVal = svcEl ? parseFloat(svcEl.value) : 0;
                if (partVal > 0 || svcVal > 0) filledFields++;
            });

            const progress = Math.min((filledFields / totalPossible) * 100, 100);
            const bar = document.getElementById('progress-bar');
            if (bar) bar.style.width = progress + '%';
        }

        // Typeahead functionality (simplified version)
        function attachTypeahead(input, endpoint, formatItem, onSelect) {
            const box = input.nextElementSibling;
            let debounceTimer;

            input.addEventListener('input', () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(async () => {
                    const q = input.value.trim();
                    if (!q) {
                        box.style.display = 'none';
                        return;
                    }

                    try {
                        const res = await fetch(endpoint + encodeURIComponent(q));
                        if (!res.ok) return;

                        const list = await res.json();
                        let items = Array.isArray(list) ? list : (list.technicians || list.rows || list.customers || []);

                        if (items.length === 0) {
                            box.style.display = 'none';
                            return;
                        }

                        box.innerHTML = items.map(item => `<div class="suggestion-item">${formatItem(item)}</div>`).join('');
                        box.style.display = 'block';

                        box.querySelectorAll('.suggestion-item').forEach((el, index) => {
                            el.addEventListener('click', () => {
                                onSelect(items[index]);
                                box.style.display = 'none';
                            });
                        });

                    } catch (e) {
                        box.style.display = 'none';
                    }
                }, 300);
            });

            // Show all suggestions on focus
            input.addEventListener('focus', async () => {
                try {
                    const res = await fetch(endpoint);
                    if (!res.ok) return;

                    const list = await res.json();
                    let items = Array.isArray(list) ? list : (list.technicians || list.rows || list.customers || []);

                    if (items.length === 0) return;

                    box.innerHTML = items.map(item => `<div class="suggestion-item">${formatItem(item)}</div>`).join('');
                    box.style.display = 'block';

                    box.querySelectorAll('.suggestion-item').forEach((el, index) => {
                        el.addEventListener('click', () => {
                            onSelect(items[index]);
                            box.style.display = 'none';
                        });
                    });

                } catch (e) {
                    box.style.display = 'none';
                }
            });

            document.addEventListener('click', (e) => {
                if (box && !input.contains(e.target) && !box.contains(e.target)) {
                    box.style.display = 'none';
                }
            });
        }

        // Attach typeaheads
        document.addEventListener('DOMContentLoaded', () => {
            // Plate number
            attachTypeahead(
                document.getElementById('input_plate_number'),
                './admin/api_customers.php?q=',
                c => `${c.plate_number} — ${c.full_name}${c.car_mark ? ' — ' + c.car_mark : ''}${c.vin ? ' — VIN:'+c.vin : ''}`,
                (item) => {
                    document.getElementById('input_plate_number').value = item.plate_number || '';
                    document.getElementById('input_customer_name').value = item.full_name || '';
                    document.getElementById('input_phone_number').value = item.phone || '';
                    document.getElementById('input_car_mark').value = item.car_mark || '';
                    document.getElementById('input_vin').value = item.vin || '';
                    document.getElementById('input_mileage').value = item.mileage || '';
                    document.getElementById('input_vehicle_id').value = item.id || '';
                    document.getElementById('hidden_customer_id').value = item.customer_id || '';
                    // Focus customer name for quick edits
                    document.getElementById('input_customer_name').focus();
                }
            );

            // Customer name
            attachTypeahead(
                document.getElementById('input_customer_name'),
                './admin/api_customers.php?customer_q=',
                c => `${c.full_name} — ${c.phone || ''}`,
                (item) => {
                    document.getElementById('input_customer_name').value = item.full_name || '';
                    document.getElementById('input_phone_number').value = item.phone || '';
                    document.getElementById('hidden_customer_id').value = item.id;
                    // Clear vehicle id
                    document.getElementById('input_vehicle_id').value = '';

                    // Autofill from customer's most recent vehicle if plate is empty
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
            attachTypeahead(
                document.getElementById('input_service_manager'),
                './admin/api_users.php?q=',
                u => u.username,
                (item) => {
                    document.getElementById('input_service_manager').value = item.username;
                    document.getElementById('input_service_manager_id').value = item.id;
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

            const carMarkEl = document.getElementById('input_car_mark');
            const vehicle = carMarkEl && carMarkEl.value ? carMarkEl.value.trim() : '';
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
                        const carMarkEl2 = document.getElementById('input_car_mark');
                        const vehicleVal = carMarkEl2 && carMarkEl2.value ? carMarkEl2.value.trim() : '';
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
        const imageInput = document.getElementById('input_images');
        
        document.getElementById('btn_take_photo').addEventListener('click', () => {
            imageInput.removeAttribute('multiple');
            imageInput.setAttribute('capture', 'environment');
            imageInput.click();
        });

        document.getElementById('btn_multi_capture').addEventListener('click', () => {
            imageInput.setAttribute('multiple', '');
            imageInput.setAttribute('capture', 'environment');
            imageInput.click();
        });

        document.getElementById('btn_upload_photo').addEventListener('click', () => {
            imageInput.setAttribute('multiple', '');
            imageInput.removeAttribute('capture');
            imageInput.click();
        });

        imageInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            
            // Append new files to the existing list
            const dt = new DataTransfer();
            selectedFiles.forEach(f => dt.items.add(f));
            files.forEach(f => {
                if (!selectedFiles.find(ef => ef.name === f.name && ef.lastModified === f.lastModified)) {
                    dt.items.add(f);
                }
            });

            imageInput.files = dt.files;
            selectedFiles = Array.from(dt.files);

            const preview = document.getElementById('photo-preview');
            preview.innerHTML = '';

            selectedFiles.forEach((file, index) => {
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
            // Update hidden fields safely
            const in_creation = document.getElementById('input_creation_date'); if (in_creation) document.getElementById('hidden_creation_date').value = in_creation.value;
            const in_sm = document.getElementById('input_service_manager'); if (in_sm) document.getElementById('hidden_service_manager').value = in_sm.value;
            const in_cust = document.getElementById('input_customer_name'); if (in_cust) document.getElementById('hidden_customer_name').value = in_cust.value;
            const in_phone = document.getElementById('input_phone_number'); if (in_phone) document.getElementById('hidden_phone_number').value = in_phone.value;
            const in_car = document.getElementById('input_car_mark'); if (in_car) document.getElementById('hidden_car_mark').value = in_car.value;
            const in_plate = document.getElementById('input_plate_number'); if (in_plate) document.getElementById('hidden_plate_number').value = in_plate.value;
            const in_vin = document.getElementById('input_vin'); if (in_vin) document.getElementById('hidden_vin').value = in_vin.value;
            const in_vehicle_id = document.getElementById('input_vehicle_id'); if (in_vehicle_id) in_vehicle_id.value = in_vehicle_id.value; // keep as-is if present
            const in_hidden_cust = document.getElementById('hidden_customer_id'); if (in_hidden_cust) in_hidden_cust.value = in_hidden_cust.value;

            const mi = document.getElementById('input_mileage'); const mu = document.getElementById('mileage_unit');
            const mileageValue = mi ? mi.value : '';
            const mileageUnit = mu ? mu.value : '';
            const hiddenMileageEl = document.getElementById('hidden_mileage'); if (hiddenMileageEl) hiddenMileageEl.value = `${mileageValue} ${mileageUnit}`;

            // Prepare items data
            const items = [];
            document.querySelectorAll('.item-card').forEach((card, index) => {
                const nameEl = card.querySelector('.item-name-input');
                const qtyEl = card.querySelector('.item-qty');
                const partEl = card.querySelector('.item-price-part');
                const discPartEl = card.querySelector('.item-discount-part');
                const svcEl = card.querySelector('.item-price-svc');
                const discSvcEl = card.querySelector('.item-discount-svc');
                const techEl = card.querySelector('.item-tech');
                const techIdEl = card.querySelector('.item-tech-id');
                const dbIdEl = card.querySelector('.item-db-id');
                const dbTypeEl = card.querySelector('.item-db-type');
                const dbVehicleEl = card.querySelector('.item-db-vehicle');
                const dbPriceSourceEl = card.querySelector('.item-db-price-source');

                const item = {
                    name: nameEl ? nameEl.value.trim() : '',
                    qty: qtyEl ? parseFloat(qtyEl.value) || 1 : 1,
                    price_part: partEl ? parseFloat(partEl.value) || 0 : 0,
                    discount_part: discPartEl ? parseFloat(discPartEl.value) || 0 : 0,
                    price_svc: svcEl ? parseFloat(svcEl.value) || 0 : 0,
                    discount_svc: discSvcEl ? parseFloat(discSvcEl.value) || 0 : 0,
                    technician: techEl ? techEl.value.trim() : '',
                    tech_id: techIdEl ? techIdEl.value || null : null,
                    db_id: dbIdEl ? dbIdEl.value : '',
                    db_type: dbTypeEl ? dbTypeEl.value : '',
                    db_vehicle: dbVehicleEl ? dbVehicleEl.value : '',
                    db_price_source: dbPriceSourceEl ? dbPriceSourceEl.value : ''
                };

                if (item.name || item.price_part > 0 || item.price_svc > 0) {
                    items.push(item);
                }
            });

            // Remove previously added prepared hidden inputs to avoid duplicates
            document.getElementById('mobile-invoice-form').querySelectorAll('.prepared-input').forEach(el => el.remove());

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
                    input.className = 'prepared-input';
                    input.name = key + index;
                    input.value = fields[key];
                    document.getElementById('mobile-invoice-form').appendChild(input);
                });
            });

            // Add hidden for oils (remove previous ones first)
            document.getElementById('mobile-invoice-form').querySelectorAll('input[name^="oil_"]').forEach(el => el.remove());
            let oilIndex = 0;
            document.querySelectorAll('.oil-card').forEach(card => {
                const brand = card.querySelector('.oil-brand')?.value || '';
                const viscosity = card.querySelector('.oil-viscosity')?.value || '';
                const packageType = card.querySelector('.oil-package')?.value || '';
                const qty = card.querySelector('.oil-qty')?.value || '1';
                const discount = card.querySelector('.oil-discount')?.value || '0';
                if (brand && viscosity && packageType) {
                    ['brand','viscosity','package','qty','discount'].forEach((k, i) => {
                        const nameMap = ['oil_brand_','oil_viscosity_','oil_package_','oil_qty_','oil_discount_'];
                        const val = [brand, viscosity, packageType, qty, discount][i];
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.className = 'prepared-input';
                        input.name = nameMap[i] + oilIndex;
                        input.value = val;
                        document.getElementById('mobile-invoice-form').appendChild(input);
                    });
                    oilIndex++;
                }
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
        function showToast(message, isError = false) {
            const toast = document.getElementById('toast');
            document.getElementById('toast-message').textContent = message;
            toast.style.background = isError ? '#ef4444' : '#10b981';
            toast.classList.add('show');
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Update progress on input changes
        document.addEventListener('input', updateProgress);

        function loadServerInvoice(inv) {
            // Populate basic fields
            document.getElementById('input_plate_number').value = inv.plate_number || '';
            document.getElementById('input_car_mark').value = inv.car_mark || '';
            document.getElementById('input_vin').value = inv.vin || '';
            
            // Handle mileage with units
            if (inv.mileage) {
                const mileageParts = inv.mileage.toString().match(/^([0-9.]+)\s*(km|mi)$/i);
                if (mileageParts) {
                    document.getElementById('input_mileage').value = mileageParts[1];
                    const unit = mileageParts[2].toLowerCase();
                    document.getElementById('mileage_unit').value = unit;
                    document.querySelectorAll('.unit-btn').forEach(btn => {
                        btn.classList.toggle('active', btn.dataset.unit === unit);
                    });
                    document.getElementById('input_mileage').placeholder = `Enter mileage in ${unit}`;
                } else {
                    document.getElementById('input_mileage').value = inv.mileage;
                }
            }

            document.getElementById('input_creation_date').value = inv.creation_date ? inv.creation_date.replace(' ', 'T').substring(0, 16) : '';
            document.getElementById('input_customer_name').value = inv.customer_name || '';
            document.getElementById('input_phone_number').value = inv.phone || '';
            document.getElementById('input_service_manager').value = inv.service_manager || inv.service_manager_username || '';
            document.getElementById('input_service_manager_id').value = inv.service_manager_id || '';
            document.getElementById('input_vehicle_id').value = inv.customer ? inv.customer.id : (inv.vehicle_id || '');
            document.getElementById('hidden_customer_id').value = inv.customer ? inv.customer.customer_id : '';
            
            // Add existing invoice ID to form
            const form = document.getElementById('mobile-invoice-form');
            const existingIdInput = document.createElement('input');
            existingIdInput.type = 'hidden';
            existingIdInput.name = 'existing_invoice_id';
            existingIdInput.value = inv.id;
            form.appendChild(existingIdInput);

            // Populate items
            const itemsContainer = document.getElementById('items-container');
            itemsContainer.innerHTML = ''; // Clear initial empty rows
            itemCount = 0;
            if (inv.items && inv.items.length > 0) {
                inv.items.forEach(item => {
                    addItem();
                    const card = document.getElementById(`item-${itemCount}`);
                    if (card) {
                        card.querySelector('.item-name-input').value = item.name || '';
                        card.querySelector('.item-qty').value = item.qty || 1;
                        card.querySelector('.item-price-part').value = item.price_part || 0;
                        card.querySelector('.item-discount-part').value = item.discount_part || 0;
                        card.querySelector('.item-price-svc').value = item.price_svc || 0;
                        card.querySelector('.item-discount-svc').value = item.discount_svc || 0;
                        card.querySelector('.item-tech').value = item.tech || '';
                        card.querySelector('.item-tech-id').value = item.tech_id || '';
                    }
                });
            } else {
                 for(let i = 0; i < 1; i++) addItem(); // Add empty rows if no items
            }

            // Populate photos
            if (inv.images && Array.isArray(inv.images) && inv.images.length > 0) {
                const preview = document.getElementById('photo-preview');
                preview.innerHTML = '';
                inv.images.forEach((src, index) => {
                    const div = document.createElement('div');
                    div.className = 'photo-item';
                    div.innerHTML = `
                        <img src="${src}" alt="Photo ${index + 1}">
                        <button type="button" class="photo-remove" onclick="removeExistingPhoto(this, ${index})">
                            <i class="fas fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(div);
                });
            }

            // Populate oils
            document.querySelectorAll('.oil-card').forEach(c => c.remove());
            if (inv.oils && Array.isArray(inv.oils) && inv.oils.length > 0) {
                inv.oils.forEach(o => addOil(o));
            }

            // Populate discounts and calculate totals
            document.getElementById('input_parts_discount').value = inv.parts_discount_percent || 0;
            document.getElementById('input_service_discount').value = inv.service_discount_percent || 0;
            calculateTotals();
        }

        function removeExistingPhoto(btn, index) {
            if (confirm('Are you sure you want to remove this existing image?')) {
                btn.parentElement.remove();
                
                let deletedImagesInput = document.querySelector('input[name="deleted_images_json"]');
                if (!deletedImagesInput) {
                    deletedImagesInput = document.createElement('input');
                    deletedImagesInput.type = 'hidden';
                    deletedImagesInput.name = 'deleted_images_json';
                    document.getElementById('mobile-invoice-form').appendChild(deletedImagesInput);
                }
                
                let deleted = deletedImagesInput.value ? JSON.parse(deletedImagesInput.value) : [];
                deleted.push(index);
                deletedImagesInput.value = JSON.stringify(deleted);
            }
        }
    </script>
</body>
</html>