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
    // For debugging, show a simple error instead of redirect
    if (isset($_GET['debug'])) {
        echo "<div style='padding: 20px; background: #fee; border: 1px solid red; margin: 20px;'>";
        echo "<h2>Debug Information</h2>";
        echo "<p>Session user_id not set</p>";
        echo "<p>Session data: " . json_encode($_SESSION) . "</p>";
        echo "<p>Server: " . $_SERVER['HTTP_HOST'] . "</p>";
        echo "</div>";
        exit;
    }
    header('Location: login.php');
    exit;
}

// Support loading a saved invoice into the editor for editing or printing (index.php?edit_id=123 or ?print_id=123)
$serverInvoice = null;
$invoiceNotFound = false;
$isEdit = isset($_GET['edit_id']);
$isPrint = isset($_GET['print_id']);
$loadId = $isEdit ? (int)$_GET['edit_id'] : ($isPrint ? (int)$_GET['print_id'] : null);
if ($loadId) {
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
                'items' => $inv_items,
                'images' => !empty($inv['images']) ? json_decode($inv['images'], true) : [],
                'grand_total' => (float)$inv['grand_total'],
                'parts_total' => (float)$inv['parts_total'],
                'service_total' => (float)$inv['service_total'],
                '_print' => true
            ];
            if ($inv_customer) $serverInvoice['customer'] = $inv_customer;
            if (!empty($sm_username)) $serverInvoice['service_manager_username'] = $sm_username;
        } else {
            $invoiceNotFound = true;
            error_log("Invoice with ID $pid not found");
        }
    } catch (Exception $e) {
        error_log("Database error loading invoice $pid: " . $e->getMessage());
        $invoiceNotFound = true;
    }
}
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auto Shop Manager - Invoice</title>

    <!-- Tailwind CSS -->
    <link href="./dist/output.css" rel="stylesheet">
    <style>
        /* Fallback styles in case CSS fails to load */
        body { font-family: system-ui, -apple-system, sans-serif; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        .error { color: red; padding: 20px; border: 1px solid red; background: #fee; }
    </style>
    <style>
        /* Print Styles */
        @media print {
            @page { margin: 0; size: A4; }
            html, body { height: 100%; margin: 0 !important; padding: 0 !important; overflow: hidden; }
            .print-hidden { display: none !important; }
            .print-visible { display: block !important; }
            .print-no-shadow { box-shadow: none !important; }
            /* Exact A4 Table Styling */
            table { border-collapse: collapse !important; border-color: #000 !important; width: 100% !important; }
            td, th { border: 1px solid #000 !important; color: #000 !important; }

            /* Compact padding for print to fit one page */
            td { padding-top: 2px !important; padding-bottom: 2px !important; padding-left: 4px !important; padding-right: 4px !important; }

            /* Ensure container is exact A4 height */
            .a4-container { height: 297mm !important; max-height: 297mm !important; overflow: hidden !important; }
        }

        .tab-active { @apply bg-yellow-500 text-slate-900 font-semibold shadow-md; }
        .tab-inactive { @apply bg-white text-gray-700 border border-gray-300; }
        .tab-inactive:hover { @apply bg-gray-50 border-gray-400; }

        /* Progress bar animations */
        #progress-bar, #step-progress-bar {
            transition: width 0.5s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* Step indicator hover effects */
        .step-indicator:not(.completed):not(.active):hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        /* Navigation button enhancements */
        #prev-step:not(:disabled):hover svg {
            transform: translateX(-2px);
        }

        #next-step:hover svg {
            transform: translateX(2px);
        }

        /* Validation message animation */
        #step-validation-message {
            animation: slideInRight 0.3s ease-out;
        }    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 h-screen overflow-hidden font-sans text-gray-800 antialiased pb-20">
    <?php include 'partials/sidebar.php'; ?>

    <main class="h-full overflow-hidden ml-0 md:ml-64 pt-4 pl-4" role="main">
        <!-- Header -->
        <header class="flex-shrink-0 p-4 md:p-8 print-hidden">
            <nav aria-label="Breadcrumb" class="mb-6">
                <ol class="flex items-center space-x-2 text-sm text-gray-500">
                    <li><a href="admin/index.php" class="hover:text-blue-600 transition">Dashboard</a></li>
                    <li><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"/></svg></li>
                    <li aria-current="page">Invoice Editor</li>
                </ol>
            </nav>
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h1 class="text-4xl font-bold text-gray-900 flex items-center">
                        <svg class="w-10 h-10 mr-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <?php if ($isEdit && !empty($serverInvoice)): ?>
                            Editing Invoice #<?php echo htmlspecialchars($loadId); ?>
                        <?php elseif ($isPrint && !empty($serverInvoice)): ?>
                            Invoice #<?php echo htmlspecialchars($loadId); ?> - Ready for Print
                        <?php else: ?>
                            Invoice Editor
                        <?php endif; ?>
                    </h1>
                    <p class="mt-2 text-gray-600">
                        <?php if ($isEdit && !empty($serverInvoice)): ?>
                            Invoice loaded for editing. Make changes and save to update.
                        <?php elseif ($isPrint && !empty($serverInvoice)): ?>
                            Invoice loaded successfully. Use the Preview or Print buttons below to view or print the invoice.
                        <?php else: ?>
                            Create and manage auto shop invoices with ease.
                        <?php endif; ?>
                    </p>

                    <?php if ($isEdit && !empty($serverInvoice)): ?>
                    <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-blue-700">
                                    Invoice #<?php echo htmlspecialchars($loadId); ?> has been loaded and all data has been populated. You can now edit the invoice details and save changes.
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($isPrint && !empty($serverInvoice)): ?>
                    <div class="mt-4 bg-green-50 border border-green-200 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800">
                                    Invoice Loaded Successfully
                                </h3>
                                <div class="mt-2 text-sm text-green-700">
                                    <p>Invoice #<?php echo htmlspecialchars($loadId); ?> has been loaded and all data has been populated. You can now preview the invoice or print it directly.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($invoiceNotFound): ?>
                <div class="mt-4 bg-red-50 border border-red-200 rounded-lg p-4">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-red-800">
                                Invoice Not Found
                            </h3>
                            <div class="mt-2 text-sm text-red-700">
                                <p>The invoice with ID <?php echo htmlspecialchars($loadId); ?> could not be found. It may have been deleted or the ID may be incorrect.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </header>
        
        <div class="h-full overflow-auto p-4 md:p-8">
        <div id="edit-mode" class="block print-hidden animate-fade-in">
            <form id="invoice-form" action="save_invoice.php" method="post" enctype="multipart/form-data" onsubmit="return handleSave()" role="form" aria-label="Invoice form">
                <?php if ($isEdit && !empty($serverInvoice)): ?>
                <input type="hidden" name="existing_invoice_id" value="<?php echo $serverInvoice['id']; ?>">
                <?php endif; ?>
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
                <input type="hidden" name="print_after_save" id="print_after_save">
                <?php if ($serverInvoice): ?>
                <input type="hidden" name="existing_invoice_id" id="existing_invoice_id" value="<?php echo $serverInvoice['id']; ?>">
                <?php endif; ?>

                <!-- Tab Navigation -->
                <div class="mb-6">
                    <nav class="flex space-x-1 bg-gray-100 p-1 rounded-lg">
                        <button type="button" class="tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors" data-tab="vehicle">Vehicle</button>
                        <button type="button" class="tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors" data-tab="customer">Customer</button>
                        <button type="button" class="tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors" data-tab="items">Items</button>
                        <button type="button" class="tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors" data-tab="photos">Photos</button>
                        <button type="button" class="tab-btn flex-1 py-2 px-4 text-sm font-medium rounded-md transition-colors" data-tab="review">Review</button>
                    </nav>
                </div>

                <!-- Tab Content -->
                <div class="tab-content" id="vehicle-tab">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                            <svg class="h-6 w-6 text-green-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            </svg>
                            Vehicle Details
                        </h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="input_car_mark" class="block text-sm font-medium text-gray-700 mb-2">Car Make/Model <span class="text-xs text-gray-500"> <button type="button" title="Format: 'Make Model' - e.g., 'Toyota Corolla'" class="ml-1 text-gray-400 hover:text-gray-600">ℹ️</button></span></label>
                                <input type="text" id="input_car_mark" placeholder="e.g., Toyota Camry" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 p-3 text-base transition">
                            </div>
                            <div>
                                <label for="input_plate_number" class="block text-sm font-medium text-gray-700 mb-2">Plate Number</label>
                                <input type="text" id="input_plate_number" placeholder="ZZ-000-ZZ" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 p-3 text-base transition">
                            </div>
                            <div>
                                <label for="input_vin" class="block text-sm font-medium text-gray-700 mb-2">VIN</label>
                                <input type="text" id="input_vin" placeholder="Vehicle VIN" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 p-3 text-base transition">
                            </div>
                            <div>
                                <label for="input_mileage" class="block text-sm font-medium text-gray-700 mb-2">Mileage</label>
                                <input type="text" id="input_mileage" placeholder="150000 km" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500 p-3 text-base transition">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <button type="button" onclick="nextTab()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Next Step</button>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="customer-tab">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                            <svg class="h-6 w-6 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Customer Details
                        </h2>
                        <div class="space-y-4">
                            <div>
                                <label for="input_creation_date" class="block text-sm font-medium text-gray-700 mb-2">Creation Date</label>
                                <input type="datetime-local" id="input_creation_date" value="<?php echo $currentDate; ?>" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 text-base transition">
                            </div>
                            <div>
                                <label for="input_customer_name" class="block text-sm font-medium text-gray-700 mb-2">Customer Name</label>
                                <input type="text" id="input_customer_name" placeholder="Enter customer name" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 text-base transition">
                            </div>
                            <div>
                                <label for="input_phone_number" class="block text-sm font-medium text-gray-700 mb-2">Phone Number</label>
                                <input type="text" id="input_phone_number" placeholder="Phone number" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 text-base transition">
                            </div>
                            <input type="hidden" id="input_customer_id" name="customer_id" value="">
                            <div>
                                <label for="input_service_manager" class="block text-sm font-medium text-gray-700 mb-2">Service Manager</label>
                                <input type="text" id="input_service_manager" placeholder="Manager Name" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 text-base transition">
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="prevTab()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Previous</button>
                            <button type="button" onclick="nextTab()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Next Step</button>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="items-tab">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                            <svg class="h-6 w-6 text-purple-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                            Service & Parts
                        </h2>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm bg-white rounded-lg overflow-hidden shadow-sm min-w-[600px]">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="px-4 py-3 text-left">#</th>
                                        <th class="px-4 py-3 text-left">Item Name</th>
                                        <th class="px-4 py-3 text-center">Qty</th>
                                        <th class="px-4 py-3 text-right">Part Price</th>
                                        <th class="px-4 py-3 text-right">Svc Price</th>
                                        <th class="px-4 py-3 text-left">Technician</th>
                                        <th class="px-4 py-3 text-center">Action</th>
                                    </tr>
                                </thead>
                                <tbody id="items-table-body" class="divide-y divide-gray-100">
                                    <!-- Rows added via JS -->
                                </tbody>
                            </table>
                        </div>
                        <button type="button" onclick="addItemRow()" class="mt-4 flex items-center gap-2 text-sm bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            Add Item
                        </button>
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-xs text-gray-600">Parts Total</p>
                                <p class="font-bold text-lg" id="display_parts_total"></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-xs text-gray-600">Service Total</p>
                                <p class="font-bold text-lg" id="display_service_total"></p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-xs text-green-700">Grand Total</p>
                                <p class="font-bold text-xl text-green-800" id="display_grand_total"></p>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="prevTab()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Previous</button>
                            <div class="flex gap-2">
                                <button type="button" onclick="skipToReview()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Skip to Review</button>
                                <button type="button" onclick="nextTab()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">Next Step</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="photos-tab">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                            <svg class="h-6 w-6 text-orange-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                            Photos
                        </h2>
                        <div class="flex gap-2 items-center mb-4">
                            <button type="button" id="btn_take_photo" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700 transition-colors" title="Click for single photo, long-press for multi-capture">📷 Take Photo</button>
                            <button type="button" id="btn_take_multiple" class="px-4 py-2 bg-green-600 text-white rounded-lg text-sm hover:bg-green-700 transition-colors" title="Take multiple photos in sequence">📸 Multi-Capture</button>
                            <button type="button" id="btn_upload_photo" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg text-sm hover:bg-gray-300 transition-colors">📁 Choose Files</button>
                            <input type="file" id="input_images" name="images[]" accept="image/*" multiple class="hidden">
                        </div>
                        <div id="input_images_preview" class="space-y-3"></div>
                        <div class="mt-2 flex items-center justify-between">
                            <p class="text-xs text-gray-500">Upload multiple vehicle photos (max 10MB each). Long-press "Take Photo" or use "Multi-Capture" for continuous photo taking.</p>
                            <span id="image_count" class="text-xs text-gray-400">0 images</span>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <button type="button" onclick="prevTab()" class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">Previous</button>
                            <button type="button" onclick="skipToReview()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">Skip to Review</button>
                        </div>
                    </div>
                </div>

                <div class="tab-content hidden" id="review-tab">
                    <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                        <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                            <svg class="h-6 w-6 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Review & Save
                        </h2>
                        <div id="review-content" class="space-y-4">
                            <!-- Review content will be populated by JS -->
                        </div>
                        <div class="mt-6 flex gap-4">
                            <button type="button" onclick="handleSave()" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition"><?php echo $isEdit ? 'Update Invoice' : 'Save Invoice'; ?></button>
                            <button type="button" onclick="handlePrint()" class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition"><?php echo $isEdit ? 'Update & Print' : 'Save & Print'; ?></button>
                        </div>
                    </div>
                </div>

                <!-- Hidden inputs for service manager -->
                <input type="hidden" id="input_service_manager_id" name="service_manager_id" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
                <input type="hidden" id="input_vehicle_id" name="vehicle_id">
            </form>
        </div>

        <!-- ================= PREVIEW / PRINT MODE ================= -->
        <div id="preview-mode" class="hidden print-visible flex-col items-center">
            <div class="mb-4 print-hidden text-gray-500 text-sm flex items-center gap-2">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Click the <strong>Print</strong> button above to download as PDF or print.</span>
            </div>

            <!-- A4 Container -->
<?php
if (!empty($serverInvoice)) {
    $invoice = $serverInvoice;
    $items = $serverInvoice['items'];
    $customer = $serverInvoice['customer'] ?? null;
    $sm_username = $serverInvoice['service_manager_username'] ?? '';
}
?>
<?php include 'partials/invoice_print_template.php'; ?>
        </div>
    </main>

    <!-- Multi-Capture Modal -->
    <div id="multi-capture-modal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full max-h-[90vh] overflow-hidden">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">📸 Multi-Photo Capture</h3>
                    <button id="close-multi-capture" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>

                <div class="text-center mb-6">
                    <div class="w-32 h-32 mx-auto mb-4 bg-gray-100 rounded-lg flex items-center justify-center">
                        <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                    <p class="text-sm text-gray-600 mb-2">Take multiple photos in sequence</p>
                    <p class="text-xs text-gray-500">Each photo will be added to your collection</p>
                </div>

                <div class="flex gap-3">
                    <button id="start-multi-capture" class="flex-1 bg-blue-600 text-white py-3 px-4 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                        Start Capturing
                    </button>
                    <button id="cancel-multi-capture" class="flex-1 bg-gray-200 text-gray-800 py-3 px-4 rounded-lg hover:bg-gray-300 transition-colors font-medium">
                        Cancel
                    </button>
                </div>

                <div id="capture-controls" class="hidden mt-4">
                    <div class="flex gap-2 justify-center mb-3">
                        <button id="capture-photo" class="bg-green-600 text-white p-3 rounded-full hover:bg-green-700 transition-colors">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </button>
                        <button id="finish-multi-capture" class="bg-red-600 text-white px-4 py-2 rounded-lg hover:bg-red-700 transition-colors text-sm font-medium">
                            Finish
                        </button>
                    </div>
                    <div class="text-center">
                        <span id="capture-count" class="text-sm text-gray-600">Photos taken: 0</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Logic -->
<?php if (!empty($serverInvoice)): ?>
    <script>
        // Server-provided invoice for preview/print
        window.serverInvoice = <?php echo json_encode($serverInvoice, JSON_UNESCAPED_UNICODE); ?>;
    </script>
<?php else: ?>
    <?php if (isset($_GET['print_id'])): ?>
        <script>
            console.log('No server invoice found for print_id: <?php echo htmlspecialchars($_GET['print_id']); ?>');
        </script>
    <?php endif; ?>
<?php endif; ?>
    <script>
        // Store items state
        let rowCount = 0;

        // Global defaults for service manager (prefill with current logged in user)
        let smDefault = <?php echo json_encode($_SESSION['username'] ?? ''); ?>;
        let smDefaultId = <?php echo (int)($_SESSION['user_id'] ?? 0); ?>;

        // Initialize with 4 rows
        document.addEventListener('DOMContentLoaded', () => {
            for(let i=0; i<4; i++) addItemRow();
            calculateTotals();

            // Prefill service manager with current logged in user (set if empty)
            (function() {
                const smInput = document.getElementById('input_service_manager');
                const smIdInput = document.getElementById('input_service_manager_id');
                if (smInput) {
                    if (!smInput.value || smInput.value.trim() === '') smInput.value = smDefault || '';
                    if (smIdInput && (!smIdInput.value || smIdInput.value == 0) && smDefaultId) smIdInput.value = smDefaultId;
                }
            })();

            // API base for AJAX endpoints (handles subfolder installs)
            const apiBase = '<?php $appRoot = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/'); if (basename($appRoot) === 'admin') $appRoot = dirname($appRoot); echo $appRoot === '/' ? '' : $appRoot; ?>';

            // Auto-fill customer fields when plate number loses focus (only if fields are empty)
            const plateInput = document.getElementById('input_plate_number');
            if (plateInput) {
                plateInput.addEventListener('blur', () => {
                    const plate = plateInput.value.trim();
                    if (!plate) return;

                    // Only auto-fill if customer fields are empty
                    const customerName = document.getElementById('input_customer_name').value.trim();
                    const phoneNumber = document.getElementById('input_phone_number').value.trim();
                    const vehicleId = document.getElementById('input_vehicle_id').value;

                    if (customerName || phoneNumber || vehicleId) {
                        // Customer info already filled, don't overwrite
                        return;
                    }

                    fetch('./admin/api_customers.php?plate=' + encodeURIComponent(plate))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            document.getElementById('input_customer_name').value = data.full_name || '';
                            document.getElementById('input_phone_number').value = data.phone || '';
                            document.getElementById('input_car_mark').value = data.car_mark || '';
                            const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = data.id || '';
                            const custIdInput = document.getElementById('input_customer_id'); if (custIdInput) custIdInput.value = data.customer_id || '';
                            // Populate VIN, make/model, and mileage if available
                            const vinInput = document.getElementById('input_vin'); if (vinInput) vinInput.value = data.vin || '';
                            const carMarkInput = document.getElementById('input_car_mark'); if (carMarkInput) carMarkInput.value = data.car_mark || '';
                            const mileageInput = document.getElementById('input_mileage'); if (mileageInput) mileageInput.value = data.mileage || '';
                        }).catch(e => {
                            // ignore errors
                        });
                });
            }



            // Typeahead for service manager and customers
            function debounce(fn, wait=250) {
                let t;
                return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
            }

            function attachTypeahead(input, endpoint, formatItem, onSelect) {
                const box = document.createElement('div');
                box.className = 'absolute bg-white border rounded mt-1 shadow z-50 w-full';
                box.style.maxHeight = '220px';
                box.style.overflow = 'auto';
                input.parentElement.style.position = 'relative';
                input.parentElement.appendChild(box);

                input.addEventListener('input', debounce(async () => {
                    const q = input.value.trim();
                    if (!q) { box.innerHTML = ''; return; }
                    try {
                        console.log('Searching for:', q);
                        const res = await fetch(endpoint + encodeURIComponent(q));
                        if (!res.ok) { 
                            console.log('Response not ok:', res.status);
                            box.innerHTML = ''; return; 
                        }
                        const list = await res.json();
                        console.log('Results:', list);
                        if (!Array.isArray(list)) { box.innerHTML = ''; return; }
                        box.innerHTML = list.map(item => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-id="${item.id}" data-json='${JSON.stringify(item).replace(/'/g, "\\'") }'>${formatItem(item)}</div>`).join('');
                        box.querySelectorAll('div').forEach(el => el.addEventListener('click', () => {
                            const item = JSON.parse(el.getAttribute('data-json'));
                            onSelect(item);
                            box.innerHTML = '';
                        }));
                    } catch (e) {
                        console.log('Error:', e);
                        box.innerHTML = '';
                    }
                }));

                document.addEventListener('click', (ev) => { if (!input.contains(ev.target) && !box.contains(ev.target)) box.innerHTML = ''; });
            }

            // Attach service manager typeahead
            const sm = document.getElementById('input_service_manager');
            if (sm) {
                attachTypeahead(sm, './admin/api_users.php?q=', u => u.username, (it) => {
                    sm.value = it.username;
                    const hid = document.getElementById('input_service_manager_id'); if (hid) hid.value = it.id;
                });
            }

            // Attach customer name typeahead (search customers)
            const cn = document.getElementById('input_customer_name');
            if (cn) {
                attachTypeahead(cn, './admin/api_customers.php?customer_q=', c => `${c.full_name} — ${c.phone || ''}` , (it) => {
                    cn.value = it.full_name || '';
                    document.getElementById('input_phone_number').value = it.phone || '';
                    const custIdInput = document.getElementById('input_customer_id'); if (custIdInput) custIdInput.value = it.id;
                    // clear any previously selected vehicle id
                    const vid = document.getElementById('input_vehicle_id'); if (vid) vid.value = '';

                    // If the plate field is empty, try to autofill from the customer's most recent vehicle
                    const plateField = document.getElementById('input_plate_number');
                    if (plateField && (!plateField.value || plateField.value.trim() === '')) {
                        fetch('./admin/api_customers.php?customer_id=' + encodeURIComponent(it.id))
                            .then(r => r.json())
                            .then(cust => {
                                if (!cust || !Array.isArray(cust.vehicles) || cust.vehicles.length === 0) return;
                                const first = cust.vehicles[0];
                                plateField.value = first.plate_number || '';
                                const vinInput = document.getElementById('input_vin'); if (vinInput) vinInput.value = first.vin || '';
                                const mileageInput = document.getElementById('input_mileage'); if (mileageInput) mileageInput.value = first.mileage || '';
                                const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = first.id || '';
                            }).catch(()=>{});
                    }
                });
            }

            // Attach plate number typeahead
            const pn = document.getElementById('input_plate_number');
            if (pn) {
                attachTypeahead(pn, './admin/api_customers.php?q=', c => `${c.plate_number} — ${c.full_name}` , (it) => {
                    pn.value = it.plate_number || '';
                    document.getElementById('input_customer_name').value = it.full_name || '';
                    document.getElementById('input_phone_number').value = it.phone || '';
                    const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = it.id;
                    const custIdInput = document.getElementById('input_customer_id'); if (custIdInput) custIdInput.value = it.customer_id || '';
                    // Populate VIN, make/model, and mileage if present
                    const vinInput = document.getElementById('input_vin'); if (vinInput) vinInput.value = it.vin || '';
                    const carMarkInput = document.getElementById('input_car_mark'); if (carMarkInput) carMarkInput.value = it.car_mark || '';
                    const mileageInput = document.getElementById('input_mileage'); if (mileageInput) mileageInput.value = it.mileage || '';
                });

                // Auto-fill on blur if exact plate match
                pn.addEventListener('blur', () => {
                    const val = pn.value.trim(); if (!val) return;

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
                            const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = data.id;
                            const vinInput = document.getElementById('input_vin'); if (vinInput) vinInput.value = data.vin || '';
                            const carMarkInput = document.getElementById('input_car_mark'); if (carMarkInput) carMarkInput.value = data.car_mark || '';
                            const mileageInput = document.getElementById('input_mileage'); if (mileageInput) mileageInput.value = data.mileage || '';
                        }).catch(e=>{});
                });
            }

            // Attach phone lookup (exact match) - only if other fields are empty
            const ph = document.getElementById('input_phone_number');
            if (ph) {
                ph.addEventListener('blur', () => {
                    const val = ph.value.trim(); if (!val) return;

                    // Only auto-fill if customer fields are empty
                    const customerName = document.getElementById('input_customer_name').value.trim();
                    const plateNumber = document.getElementById('input_plate_number').value.trim();
                    const vehicleId = document.getElementById('input_vehicle_id').value;

                    if (customerName || plateNumber || vehicleId) {
                        // Customer info already filled, don't overwrite
                        return;
                    }

                    fetch('./admin/api_customers.php?phone=' + encodeURIComponent(val))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            document.getElementById('input_customer_name').value = data.full_name || '';
                            document.getElementById('input_plate_number').value = data.plate_number || '';
                            const cid = document.getElementById('input_vehicle_id'); if (cid) cid.value = data.id;
                        }).catch(e=>{});
                });
            }



            // Enhanced Image input preview with delete functionality
            const imgInput = document.getElementById('input_images');
            const imgPreview = document.getElementById('input_images_preview');
            const imageCount = document.getElementById('image_count');
            let selectedFiles = [];
            let isMultiCaptureMode = false;

            if (imgInput && imgPreview) {
                // Click helpers for separate buttons
                const btnTake = document.getElementById('btn_take_photo');
                const btnTakeMultiple = document.getElementById('btn_take_multiple');
                const btnUpload = document.getElementById('btn_upload_photo');

                if (btnTake) {
                    let pressTimer;
                    let isLongPress = false;

                    btnTake.addEventListener('mousedown', () => {
                        isLongPress = false;
                        pressTimer = setTimeout(() => {
                            isLongPress = true;
                            btnTake.textContent = '📸 Multi-Capture...';
                            btnTake.classList.add('bg-green-600', 'hover:bg-green-700');
                            btnTake.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            startMultiCapture();
                        }, 1000); // Long press for 1 second
                    });

                    btnTake.addEventListener('mouseup', () => {
                        clearTimeout(pressTimer);
                        if (!isLongPress) {
                            takeSinglePhoto();
                        }
                        // Reset button appearance
                        btnTake.textContent = '📷 Take Photo';
                        btnTake.classList.add('bg-blue-600', 'hover:bg-blue-700');
                        btnTake.classList.remove('bg-green-600', 'hover:bg-green-700');
                    });

                    btnTake.addEventListener('mouseleave', () => {
                        clearTimeout(pressTimer);
                        // Reset button appearance
                        btnTake.textContent = '📷 Take Photo';
                        btnTake.classList.add('bg-blue-600', 'hover:bg-blue-700');
                        btnTake.classList.remove('bg-green-600', 'hover:bg-green-700');
                    });

                    // For touch devices
                    btnTake.addEventListener('touchstart', (e) => {
                        isLongPress = false;
                        pressTimer = setTimeout(() => {
                            isLongPress = true;
                            btnTake.textContent = '📸 Multi-Capture...';
                            btnTake.classList.add('bg-green-600', 'hover:bg-green-700');
                            btnTake.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                            startMultiCapture();
                            e.preventDefault();
                        }, 1000);
                    });

                    btnTake.addEventListener('touchend', () => {
                        clearTimeout(pressTimer);
                        if (!isLongPress) {
                            takeSinglePhoto();
                        }
                        // Reset button appearance
                        btnTake.textContent = '📷 Take Photo';
                        btnTake.classList.add('bg-blue-600', 'hover:bg-blue-700');
                        btnTake.classList.remove('bg-green-600', 'hover:bg-green-700');
                    });
                }

                // Multi-capture functionality
                if (btnTakeMultiple) btnTakeMultiple.addEventListener('click', () => {
                    startMultiCapture();
                });

                if (btnUpload) btnUpload.addEventListener('click', () => {
                    imgInput.removeAttribute('capture');
                    imgInput.click();
                });

                // Drag and drop support
                const photoSection = imgPreview.closest('.space-y-3')?.parentElement;
                if (photoSection) {
                    photoSection.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        photoSection.classList.add('bg-blue-50', 'border-blue-200');
                    });

                    photoSection.addEventListener('dragleave', (e) => {
                        e.preventDefault();
                        photoSection.classList.remove('bg-blue-50', 'border-blue-200');
                    });

                    photoSection.addEventListener('drop', (e) => {
                        e.preventDefault();
                        photoSection.classList.remove('bg-blue-50', 'border-blue-200');

                        const files = Array.from(e.dataTransfer.files);
                        const imageFiles = files.filter(f => f.type.startsWith('image/'));

                        if (imageFiles.length > 0) {
                            // Create a new DataTransfer to add to existing files
                            const dt = new DataTransfer();
                            selectedFiles.forEach(f => dt.items.add(f));
                            imageFiles.forEach(f => dt.items.add(f));

                            imgInput.files = dt.files;
                            imgInput.dispatchEvent(new Event('change'));
                        }
                    });
                }

                imgInput.addEventListener('change', (ev) => {
                    const files = Array.from(imgInput.files || []);
                    selectedFiles = files;

                    // Validate files
                    const validFiles = files.filter(f => {
                        if (!f.type.startsWith('image/')) {
                            alert(`${f.name} is not an image file.`);
                            return false;
                        }
                        if (f.size > 10 * 1024 * 1024) { // 10MB limit
                            alert(`${f.name} is too large. Maximum size is 10MB.`);
                            return false;
                        }
                        return true;
                    });

                    if (validFiles.length !== files.length) {
                        // Re-filter the input
                        const dt = new DataTransfer();
                        validFiles.forEach(f => dt.items.add(f));
                        imgInput.files = dt.files;
                        selectedFiles = validFiles;
                    }

                    // Update preview
                    imgPreview.innerHTML = '';
                    selectedFiles.forEach((f, index) => {
                        const container = document.createElement('div');
                        container.className = 'relative inline-block';

                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.style.width = '120px';
                            img.style.height = '90px';
                            img.style.objectFit = 'cover';
                            img.className = 'rounded-lg border border-gray-300 shadow-sm';

                            const deleteBtn = document.createElement('button');
                            deleteBtn.type = 'button';
                            deleteBtn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition-colors';
                            deleteBtn.innerHTML = '×';
                            deleteBtn.title = 'Remove image';
                            deleteBtn.onclick = () => {
                                selectedFiles.splice(index, 1);
                                const dt = new DataTransfer();
                                selectedFiles.forEach(f => dt.items.add(f));
                                imgInput.files = dt.files;
                                imgInput.dispatchEvent(new Event('change'));
                            };

                            container.appendChild(img);
                            container.appendChild(deleteBtn);
                            imgPreview.appendChild(container);
                        };
                        reader.readAsDataURL(f);
                    });

                    // Update counter
                    if (imageCount) {
                        imageCount.textContent = `${selectedFiles.length} image${selectedFiles.length !== 1 ? 's' : ''}`;
                    }
                });
            }

            // Multi-capture functionality
            function startMultiCapture() {
                const modal = document.getElementById('multi-capture-modal');
                const startBtn = document.getElementById('start-multi-capture');
                const controls = document.getElementById('capture-controls');
                const captureBtn = document.getElementById('capture-photo');
                const finishBtn = document.getElementById('finish-multi-capture');
                const countDisplay = document.getElementById('capture-count');

                modal.classList.remove('hidden');
                startBtn.classList.remove('hidden');
                controls.classList.add('hidden');

                let captureCount = 0;

                startBtn.onclick = () => {
                    startBtn.classList.add('hidden');
                    controls.classList.remove('hidden');
                    captureCount = 0;
                    countDisplay.textContent = `Photos taken: ${captureCount}`;
                };

                captureBtn.onclick = () => {
                    takePhotoForMultiCapture((file) => {
                        captureCount++;
                        countDisplay.textContent = `Photos taken: ${captureCount}`;
                    });
                };

                finishBtn.onclick = () => {
                    modal.classList.add('hidden');
                    // Update the main file input with all captured files
                    const dt = new DataTransfer();
                    selectedFiles.forEach(f => dt.items.add(f));
                    imgInput.files = dt.files;
                    imgInput.dispatchEvent(new Event('change'));
                };

                // Close modal handlers
                document.getElementById('close-multi-capture').onclick = () => {
                    modal.classList.add('hidden');
                };

                document.getElementById('cancel-multi-capture').onclick = () => {
                    modal.classList.add('hidden');
                };

                modal.onclick = (e) => {
                    if (e.target === modal) {
                        modal.classList.add('hidden');
                    }
                };
            }

            // Function to take a single photo
            function takeSinglePhoto() {
                const tempInput = document.createElement('input');
                tempInput.type = 'file';
                tempInput.accept = 'image/*';
                tempInput.capture = 'environment';
                tempInput.multiple = false;

                tempInput.onchange = (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        selectedFiles.push(file);
                        const dt = new DataTransfer();
                        selectedFiles.forEach(f => dt.items.add(f));
                        imgInput.files = dt.files;
                        imgInput.dispatchEvent(new Event('change'));
                    }
                };

                tempInput.click();
            }

            // Function to take photo for multi-capture
            function takePhotoForMultiCapture(callback) {
                const tempInput = document.createElement('input');
                tempInput.type = 'file';
                tempInput.accept = 'image/*';
                tempInput.capture = 'environment';
                tempInput.multiple = false;

                tempInput.onchange = (e) => {
                    const file = e.target.files[0];
                    if (file) {
                        selectedFiles.push(file);

                        // Create preview immediately
                        const reader = new FileReader();
                        reader.onload = (e) => {
                            const container = document.createElement('div');
                            container.className = 'relative inline-block';

                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.style.width = '80px';
                            img.style.height = '60px';
                            img.style.objectFit = 'cover';
                            img.className = 'rounded border border-gray-300';

                            const deleteBtn = document.createElement('button');
                            deleteBtn.type = 'button';
                            deleteBtn.className = 'absolute -top-1 -right-1 bg-red-500 text-white rounded-full w-4 h-4 flex items-center justify-center text-xs hover:bg-red-600 transition-colors';
                            deleteBtn.innerHTML = '×';
                            deleteBtn.onclick = () => {
                                const index = selectedFiles.indexOf(file);
                                if (index > -1) {
                                    selectedFiles.splice(index, 1);
                                    container.remove();
                                    updateImageCount();
                                }
                            };

                            container.appendChild(img);
                            container.appendChild(deleteBtn);
                            imgPreview.appendChild(container);
                            updateImageCount();

                            if (callback) callback(file);
                        };
                        reader.readAsDataURL(file);
                    }
                };

                tempInput.click();
            }

            // Function to update image count
            function updateImageCount() {
                const imageCount = document.getElementById('image_count');
                if (imageCount) {
                    const preview = document.getElementById('input_images_preview');
                    const existingImages = preview.querySelectorAll('img').length;
                    imageCount.textContent = `${existingImages} image${existingImages !== 1 ? 's' : ''}`;
                }
            }

            // Function to update image count
            function updateImageCount() {
                const imageCount = document.getElementById('image_count');
                if (imageCount) {
                    const preview = document.getElementById('input_images_preview');
                    const existingImages = preview.querySelectorAll('img').length;
                    imageCount.textContent = `${existingImages} image${existingImages !== 1 ? 's' : ''}`;
                }
            }

            // If server supplied invoice data, populate and optionally print
            if (window.serverInvoice) {
                loadServerInvoice(window.serverInvoice);
            }

            // Initialize tabs
            initializeTabs();
        });

        function initializeTabs() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');

            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabName = button.getAttribute('data-tab');

                    // Remove active classes from all buttons
                    tabButtons.forEach(btn => {
                        btn.classList.remove('bg-blue-500', 'text-white', 'font-semibold');
                        btn.classList.add('bg-gray-100', 'text-gray-700');
                    });

                    // Add active class to clicked button
                    button.classList.remove('bg-gray-100', 'text-gray-700');
                    button.classList.add('bg-blue-500', 'text-white', 'font-semibold');

                    // Hide all tab contents
                    tabContents.forEach(content => {
                        content.classList.add('hidden');
                    });

                    // Show selected tab content
                    const selectedTab = document.getElementById(tabName + '-tab');
                    if (selectedTab) {
                        selectedTab.classList.remove('hidden');
                    }

                    // Update review tab content when switching to it
                    if (tabName === 'review') {
                        updateReviewContent();
                    }
                });
            });

            // Set first tab as active by default
            if (tabButtons.length > 0) {
                tabButtons[0].click();
            }
        }

        function nextTab() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const activeButton = document.querySelector('.tab-btn.bg-blue-500');
            if (!activeButton) return;
            const currentIndex = Array.from(tabButtons).indexOf(activeButton);
            if (currentIndex < tabButtons.length - 1) {
                tabButtons[currentIndex + 1].click();
            }
        }

        function prevTab() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const activeButton = document.querySelector('.tab-btn.bg-blue-500');
            if (!activeButton) return;
            const currentIndex = Array.from(tabButtons).indexOf(activeButton);
            if (currentIndex > 0) {
                tabButtons[currentIndex - 1].click();
            }
        }

        function skipToReview() {
            const reviewButton = document.querySelector('.tab-btn[data-tab="review"]');
            if (reviewButton) reviewButton.click();
        }

        function updateReviewContent() {
            const reviewContent = document.getElementById('review-content');
            if (!reviewContent) return;

            const customerName = document.getElementById('input_customer_name').value || 'Not specified';
            const phone = document.getElementById('input_phone_number').value || 'Not specified';
            const carMark = document.getElementById('input_car_mark').value || 'Not specified';
            const plateNumber = document.getElementById('input_plate_number').value || 'Not specified';
            const vin = document.getElementById('input_vin').value || 'Not specified';
            const mileage = document.getElementById('input_mileage').value || 'Not specified';

            const totals = calculateTotals();

            reviewContent.innerHTML = `
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800">Customer Information</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p><strong>Name:</strong> ${customerName}</p>
                            <p><strong>Phone:</strong> ${phone}</p>
                        </div>

                        <h3 class="text-lg font-semibold text-gray-800">Vehicle Information</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p><strong>Make/Model:</strong> ${carMark}</p>
                            <p><strong>Plate Number:</strong> ${plateNumber}</p>
                            <p><strong>VIN:</strong> ${vin}</p>
                            <p><strong>Mileage:</strong> ${mileage}</p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <h3 class="text-lg font-semibold text-gray-800">Invoice Summary</h3>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <p><strong>Parts Total:</strong> ${totals.partTotal > 0 ? totals.partTotal.toFixed(2) + ' ₾' : '0.00 ₾'}</p>
                            <p><strong>Service Total:</strong> ${totals.svcTotal > 0 ? totals.svcTotal.toFixed(2) + ' ₾' : '0.00 ₾'}</p>
                            <p class="text-lg font-bold text-green-600"><strong>Grand Total:</strong> ${totals.grandTotal > 0 ? totals.grandTotal.toFixed(2) + ' ₾' : '0.00 ₾'}</p>
                        </div>

                        <div class="bg-blue-50 p-4 rounded-lg">
                            <p class="text-sm text-blue-800">Ready to <?php echo $isEdit ? 'update' : 'save'; ?> this invoice? Click "<?php echo $isEdit ? 'Update Invoice' : 'Save Invoice'; ?>" to store it or "<?php echo $isEdit ? 'Update & Print' : 'Save & Print'; ?>" to <?php echo $isEdit ? 'update' : 'save'; ?> and print immediately.</p>
                        </div>
                    </div>
                </div>
            `;
        }

        function addItemRow() {
            rowCount++;
            const tbody = document.getElementById('items-table-body');
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 item-row";
            tr.id = `row-${rowCount}`;
            tr.innerHTML = `
                <td class="px-3 py-3 font-medium text-center text-gray-400 row-number"></td>
                <td class="px-3 py-3 relative flex items-center"><input type="text" placeholder="Description" class="item-name flex-1 border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"><button class="ml-2 p-2 bg-blue-500 text-white rounded hover:bg-blue-600" onclick="openItemSearch(this)" title="Search items"><svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" /></svg></button><div class="suggestions absolute z-10 bg-white border border-gray-300 rounded-b shadow-lg max-h-40 overflow-y-auto w-full hidden"></div></td>
                <td class="px-3 py-3"><input type="number" min="1" value="1" oninput="calculateTotals()" class="item-qty w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="number" min="0" value="0" oninput="calculateTotals()" class="item-price-part w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="number" min="0" value="0" oninput="calculateTotals()" class="item-price-svc w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3"><input type="text" placeholder="Name" class="item-tech w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
                <td class="px-3 py-3 text-center">
                    <button onclick="removeRow(${rowCount})" class="text-red-400 hover:text-red-600 p-2 rounded-full hover:bg-red-50 transition-colors">
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                    </button>
                </td>
            `;
            tbody.appendChild(tr);
            renumberRows();
        }

        function loadServerInvoice(inv) {
            console.log('Loading server invoice:', inv);
            // Fill fields
            if (inv.creation_date) {
                // Convert "YYYY-MM-DD HH:MM:SS" to datetime-local value "YYYY-MM-DDTHH:MM"
                document.getElementById('input_creation_date').value = inv.creation_date.replace(' ', 'T').substring(0,16);
            }
            document.getElementById('input_service_manager').value = inv.service_manager || inv.service_manager_username || smDefault || '';
            if (document.getElementById('input_service_manager_id')) document.getElementById('input_service_manager_id').value = inv.service_manager_id || '';

            if (inv.customer_name) document.getElementById('input_customer_name').value = inv.customer_name;
            if (inv.phone) document.getElementById('input_phone_number').value = inv.phone;
            if (inv.car_mark) document.getElementById('input_car_mark').value = inv.car_mark;
            if (inv.plate_number) document.getElementById('input_plate_number').value = inv.plate_number;
            if (inv.vin) document.getElementById('input_vin').value = inv.vin || '';
            if (inv.mileage) document.getElementById('input_mileage').value = inv.mileage;
            if (inv.customer && inv.customer.id) document.getElementById('input_vehicle_id').value = inv.customer.id;

            // Render existing images (if server provided) with delete functionality
            if (inv.images && Array.isArray(inv.images) && inv.images.length > 0) {
                const preview = document.getElementById('input_images_preview');
                preview.innerHTML = '';
                inv.images.forEach((src, index) => {
                    const container = document.createElement('div');
                    container.className = 'relative inline-block';

                    const img = document.createElement('img');
                    img.src = src;
                    img.style.width = '120px';
                    img.style.height = '90px';
                    img.style.objectFit = 'cover';
                    img.className = 'rounded-lg border border-gray-300 shadow-sm';

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'absolute -top-2 -right-2 bg-red-500 text-white rounded-full w-6 h-6 flex items-center justify-center text-xs hover:bg-red-600 transition-colors';
                    deleteBtn.innerHTML = '×';
                    deleteBtn.title = 'Remove existing image';
                    deleteBtn.onclick = () => {
                        if (confirm('Remove this existing image from the invoice?')) {
                            // Mark for deletion by adding to a hidden field
                            let deletedImages = JSON.parse(document.getElementById('deleted_images')?.value || '[]');
                            deletedImages.push(index);
                            if (!document.getElementById('deleted_images')) {
                                const hidden = document.createElement('input');
                                hidden.type = 'hidden';
                                hidden.name = 'deleted_images';
                                hidden.id = 'deleted_images';
                                document.querySelector('form').appendChild(hidden);
                            }
                            document.getElementById('deleted_images').value = JSON.stringify(deletedImages);
                            container.remove();
                            updateImageCount();
                        }
                    };

                    container.appendChild(img);
                    container.appendChild(deleteBtn);
                    preview.appendChild(container);
                });
                updateImageCount();
            }

            // Replace existing rows with invoice items
            document.querySelectorAll('.item-row').forEach(r => r.remove());
            (inv.items || []).forEach(it => {
                addItemRow();
                const tr = document.querySelector('.item-row:last-child'); if (!tr) return;
                tr.querySelector('.item-name').value = it.name || '';
                tr.querySelector('.item-qty').value = it.qty || 1;
                tr.querySelector('.item-price-part').value = it.price_part || 0;
                tr.querySelector('.item-price-svc').value = it.price_svc || 0;
                tr.querySelector('.item-tech').value = it.tech || '';
            });
            calculateTotals();
        }

        function removeRow(id) {
            const row = document.getElementById(`row-${id}`);
            if(row) {
                row.remove();
                renumberRows();
                calculateTotals();
            }
        }

        function renumberRows() {
            const rows = document.querySelectorAll('.item-row');
            rows.forEach((row, index) => {
                row.querySelector('.row-number').innerText = index + 1;
            });
        }

        function calculateTotals() {
            let partTotal = 0;
            let svcTotal = 0;
            
            const rows = document.querySelectorAll('.item-row');
            rows.forEach(row => {
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const pPart = parseFloat(row.querySelector('.item-price-part').value) || 0;
                const pSvc = parseFloat(row.querySelector('.item-price-svc').value) || 0;
                
                partTotal += (qty * pPart);
                svcTotal += (qty * pSvc);
            });

            // Ensure totals are valid numbers
            partTotal = isNaN(partTotal) ? 0 : partTotal;
            svcTotal = isNaN(svcTotal) ? 0 : svcTotal;
            const grandTotal = partTotal + svcTotal;

            // Update desktop totals
            document.getElementById('display_parts_total').innerText = partTotal > 0 ? partTotal.toFixed(2) + ' ₾' : '';
            document.getElementById('display_service_total').innerText = svcTotal > 0 ? svcTotal.toFixed(2) + ' ₾' : '';
            document.getElementById('display_grand_total').innerText = grandTotal > 0 ? grandTotal.toFixed(2) + ' ₾' : '';

            return { partTotal, svcTotal, grandTotal };
        }

        function updatePreviewData() {
            // Ensure service manager fallback is set if empty
            const smInput = document.getElementById('input_service_manager');
            const smIdInput = document.getElementById('input_service_manager_id');
            if (smInput && (!smInput.value || smInput.value.trim() === '')) {
                smInput.value = smDefault || '';
            }
            if (smIdInput && (!smIdInput.value || smIdInput.value == 0) && smDefaultId) {
                smIdInput.value = smDefaultId;
            }

            // Map Inputs
            const map = {
                'input_creation_date': 'out_creation_date',
                'input_customer_name': 'out_customer_name',
                'input_car_mark': 'out_car_mark',
                'input_plate_number': 'out_plate_number',
                'input_phone_number': 'out_phone_number',
                'input_mileage': 'out_mileage',
                'input_service_manager': 'out_service_manager',
                'input_vin': 'out_vin'
            };

            for(const [inId, outId] of Object.entries(map)) {
                let val = document.getElementById(inId).value;
                if(inId === 'input_creation_date') val = val.replace('T', ' ');
                document.getElementById(outId).innerText = val;
            }

            // Build Table
            const totals = calculateTotals();
            const tbody = document.getElementById('preview-table-body');
            tbody.innerHTML = ''; // Clear

            // Render VIN and images for client-side preview
            const outImages = document.getElementById('out_images');
            if (outImages) outImages.innerHTML = '';
            const imgInput = document.getElementById('input_images');
            if (imgInput && imgInput.files && imgInput.files.length > 0 && outImages) {
                Array.from(imgInput.files).forEach(f => {
                    if (!f.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.style.width = '120px';
                        img.style.height = 'auto';
                        img.style.objectFit = 'cover';
                        img.className = 'rounded border mr-2 mb-2';
                        outImages.appendChild(img);
                    };
                    reader.readAsDataURL(f);
                });
            }

            const rows = document.querySelectorAll('.item-row');
            let index = 1;

            const formatPrice = (val) => val > 0 ? val.toFixed(2) : "";

            rows.forEach(row => {
                const name = row.querySelector('.item-name').value;
                const qty = parseFloat(row.querySelector('.item-qty').value) || 0;
                const pPart = parseFloat(row.querySelector('.item-price-part').value) || 0;
                const pSvc = parseFloat(row.querySelector('.item-price-svc').value) || 0;
                const tech = row.querySelector('.item-tech').value;

                // Do not display zeros in rows (if empty name or 0 value)
                if (!name && pPart === 0 && pSvc === 0) {
                     // If name is empty and values are 0, assume it's an empty/filler row from edit mode
                     // We will still render it to maintain the row count visual from edit mode, but blank.
                }

                const totalPart = qty * pPart;
                const totalSvc = qty * pSvc;

                // Logic: If Name is empty, show blank row (even if default values exist)
                // If Name exists, show values, but if specific price is 0, show blank.
                
                let displayQty = qty;
                let displayPPart = formatPrice(pPart);
                let displayTotalPart = formatPrice(totalPart);
                let displayPSvc = formatPrice(pSvc);
                let displayTotalSvc = formatPrice(totalSvc);

                if (!name) {
                    displayQty = "";
                    displayPPart = "";
                    displayTotalPart = "";
                    displayPSvc = "";
                    displayTotalSvc = "";
                }

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-black p-1 text-center">${index++}</td>
                    <td class="border border-black p-1">${name}</td>
                    <td class="border border-black p-1 text-center">${displayQty}</td>
                    <td class="border border-black p-1 text-right">${displayPPart}</td>
                    <td class="border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50">${displayTotalPart}</td>
                    <td class="border border-black p-1 text-right">${displayPSvc}</td>
                    <td class="border border-black p-1 text-right font-semibold bg-gray-50 print:bg-gray-50">${displayTotalSvc}</td>
                    <td class="border border-black p-1">${tech}</td>
                `;
                tbody.appendChild(tr);
            });

            // Fill Empty Rows
            const needed = Math.max(0, 20 - rows.length);
            for(let i=0; i<needed; i++) {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="border border-black p-1 text-center text-white">.</td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1 bg-gray-50 print:bg-gray-50"></td>
                    <td class="border border-black p-1"></td>
                    <td class="border border-black p-1 bg-gray-50 print:bg-gray-50"></td>
                    <td class="border border-black p-1"></td>
                `;
                tbody.appendChild(tr);
            }

            // Add Footer Row
            const footerRow = document.createElement('tr');
            footerRow.className = "font-bold bg-gray-100 print:bg-gray-100";
            
            // Do not display zeros in summary
            const displayPartTotal = totals.partTotal > 0 ? totals.partTotal.toFixed(2) : "";
            const displaySvcTotal = totals.svcTotal > 0 ? totals.svcTotal.toFixed(2) : "";

            footerRow.innerHTML = `
                <td class="border border-black p-2 text-right" colSpan="4">ჯამი:</td>
                <td class="border border-black p-2 text-right">${displayPartTotal}</td>
                <td class="border border-black p-2 text-right">ჯამი:</td>
                <td class="border border-black p-2 text-right">${displaySvcTotal}</td>
                <td class="border border-black p-2 bg-gray-300 print:bg-gray-300"></td>
            `;
            tbody.appendChild(footerRow);

            // Update Grand Total Text
            document.getElementById('out_grand_total').innerText = totals.grandTotal > 0 ? totals.grandTotal.toFixed(2) + ' ₾' : '';
        }

        function prepareData() {
            // Update hidden fields
            const creationDate = document.getElementById('input_creation_date').value;
            const serviceManager = document.getElementById('input_service_manager').value;
            const customerName = document.getElementById('input_customer_name').value;
            const phoneNumber = document.getElementById('input_phone_number').value;
            const carMark = document.getElementById('input_car_mark').value;
            const plateNumber = document.getElementById('input_plate_number').value;
            const mileage = document.getElementById('input_mileage').value;

            document.getElementById('hidden_creation_date').value = creationDate;
            document.getElementById('hidden_service_manager').value = serviceManager;
            document.getElementById('hidden_customer_name').value = customerName;
            document.getElementById('hidden_phone_number').value = phoneNumber;
            document.getElementById('hidden_car_mark').value = carMark;
            document.getElementById('hidden_plate_number').value = plateNumber;
            document.getElementById('hidden_vin').value = document.getElementById('input_vin') ? document.getElementById('input_vin').value : '';
            document.getElementById('hidden_mileage').value = mileage;
            // Hidden customer/vehicle ids
            document.getElementById('hidden_customer_id').value = document.getElementById('input_customer_id') ? document.getElementById('input_customer_id').value : '';
            document.getElementById('hidden_vehicle_id').value = document.getElementById('input_vehicle_id') ? document.getElementById('input_vehicle_id').value : '';
            
            const totals = calculateTotals();
            // Ensure totals are valid numbers, default to 0.00 if NaN
            const partsTotal = isNaN(totals.partTotal) ? 0.00 : totals.partTotal;
            const serviceTotal = isNaN(totals.svcTotal) ? 0.00 : totals.svcTotal;
            const grandTotal = isNaN(totals.grandTotal) ? 0.00 : totals.grandTotal;

            document.getElementById('hidden_parts_total').value = partsTotal.toFixed(2);
            document.getElementById('hidden_service_total').value = serviceTotal.toFixed(2);
            document.getElementById('hidden_grand_total').value = grandTotal.toFixed(2);

            // Ensure service manager is set (prevent empty)
            const smEl = document.getElementById('input_service_manager');
            const smIdEl = document.getElementById('input_service_manager_id');
            if (smEl && (!smEl.value || smEl.value.trim() === '')) {
                smEl.value = smDefault || '';
            }
            if (smIdEl && (!smIdEl.value || smIdEl.value == 0) && smDefaultId) {
                smIdEl.value = smDefaultId;
            }

            // Add hidden for items
            let form = document.getElementById('invoice-form');
            let index = 0;
            document.querySelectorAll('.item-row').forEach(row => {
                let name = row.querySelector('.item-name').value;
                if (name.trim() !== '') {
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_name_${index}" value="${name}">`);
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_qty_${index}" value="${row.querySelector('.item-qty').value}">`);
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_part_${index}" value="${row.querySelector('.item-price-part').value}">`);
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_price_svc_${index}" value="${row.querySelector('.item-price-svc').value}">`);
                    form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_tech_${index}" value="${row.querySelector('.item-tech').value}">`);
                    // include matched db id/type if suggestion was used
                    if (row.dataset && row.dataset.itemDbId) {
                        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_db_id_${index}" value="${row.dataset.itemDbId}">`);
                    }
                    if (row.dataset && row.dataset.itemDbType) {
                        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_db_type_${index}" value="${row.dataset.itemDbType}">`);
                    }
                    if (row.dataset && row.dataset.itemDbVehicle) {
                        form.insertAdjacentHTML('beforeend', `<input type="hidden" name="item_db_vehicle_${index}" value="${row.dataset.itemDbVehicle}">`);
                    }
                    index++;
                }
            });
            return true;
        }

        function handleSave() {
            // Basic validation before preparing data
            const customerName = document.getElementById('input_customer_name').value.trim();
            const serviceManager = document.getElementById('input_service_manager').value.trim();
            const vehicleId = document.getElementById('input_vehicle_id').value.trim();
            const plateNumber = document.getElementById('input_plate_number').value.trim();

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

            // If no customer is selected, plate number is required
            if (!vehicleId && customerName && !plateNumber) {
                alert('Please enter a plate number when creating a new customer.');
                document.getElementById('input_plate_number').focus();
                return false;
            }

            return prepareData();
        }

        function handlePrint() {
            // Set print flag so server will redirect to print view after saving/updating
            document.getElementById('print_after_save').value = '1';

            // Run validation/prepare logic. handleSave() calls prepareData() and returns false on validation failure.
            if (handleSave()) {
                document.getElementById('invoice-form').submit();
            } else {
                // Clear flag when validation fails to avoid accidental print after failed save
                document.getElementById('print_after_save').value = '';
            }
        }

        // Autocomplete for item names
        let currentSuggestions = [];
        let currentInput = null;

        function showSuggestions(input, suggestions) {
            const container = input.parentElement.querySelector('.suggestions');
            container.innerHTML = '';
            if (Array.isArray(suggestions) && suggestions.length > 0) {
                suggestions.forEach(suggestion => {
                    const div = document.createElement('div');
                    div.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer text-sm';
                    div.textContent = suggestion.name + (suggestion.default_price > 0 ? ` (${suggestion.default_price} ₾)` : '') + (suggestion.vehicle_make_model ? ' — ' + suggestion.vehicle_make_model : '') + ` [${suggestion.type}]`;
                    div.addEventListener('click', () => {
                        input.value = suggestion.name;
                        const row = input.closest('tr');

                        // Record matched DB item id/type on the row for server-side processing
                        if (suggestion.id) row.dataset.itemDbId = suggestion.id;
                        if (suggestion.type) row.dataset.itemDbType = suggestion.type;
                        if (suggestion.vehicle_make_model) row.dataset.itemDbVehicle = suggestion.vehicle_make_model;

                        // Fill appropriate price field depending on type
                        if (suggestion.type === 'part') {
                            const partInput = row.querySelector('.item-price-part');
                            if (suggestion.default_price > 0 && (!partInput.value || partInput.value == '0')) {
                                partInput.value = suggestion.default_price;
                            }
                        } else if (suggestion.type === 'labor') {
                            const svcInput = row.querySelector('.item-price-svc');
                            if (suggestion.default_price > 0 && (!svcInput.value || svcInput.value == '0')) {
                                svcInput.value = suggestion.default_price;
                            }
                        }

                        calculateTotals();
                        hideSuggestions();
                        input.focus();
                    });
                    container.appendChild(div);
                });
                container.classList.remove('hidden');
            } else {
                container.classList.add('hidden');
            }
        }

        function hideSuggestions() {
            document.querySelectorAll('.suggestions').forEach(s => s.classList.add('hidden'));
        }

        function fetchSuggestions(query) {
            if (query.length < 2) {
                hideSuggestions();
                return;
            }
            const vehicle = (document.getElementById('input_car_mark')?.value || '').trim();
            const params = new URLSearchParams({ q: query });
            if (vehicle) params.set('vehicle', vehicle);

            fetch(`admin/api_labors_parts.php?` + params.toString())
                .then(response => response.json())
                .then(resp => {
                    const data = resp && resp.data ? resp.data : resp;
                    currentSuggestions = Array.isArray(data) ? data : [];
                    if (currentInput) {
                        showSuggestions(currentInput, currentSuggestions);
                    }
                })
                .catch(error => console.error('Error fetching suggestions:', error));
        }

        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('item-name')) {
                currentInput = e.target;
                const query = e.target.value.trim();
                // Determine if it's labor or part based on context or assume labor for now
                // For simplicity, check if "part" in name or something, but let's assume labor
                fetchSuggestions(query);
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.relative')) {
                hideSuggestions();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.target.classList.contains('item-name')) {
                const container = e.target.parentElement.querySelector('.suggestions');
                if (e.key === 'Escape') {
                    hideSuggestions();
                } else if (e.key === 'ArrowDown' && !container.classList.contains('hidden')) {
                    const first = container.querySelector('div');
                    if (first) first.focus();
                }
            }
        });

        // Item Search Modal Functions
        let currentSearchInput = null;

        function openItemSearch(button) {
            currentSearchInput = button.previousElementSibling; // The input before the button
            document.getElementById('item-search-modal').classList.remove('hidden');
            document.getElementById('item-search-input').focus();
            document.getElementById('item-search-results').innerHTML = '<p class="text-gray-500 text-center py-4">Start typing to search...</p>';
        }

        function closeItemSearch() {
            document.getElementById('item-search-modal').classList.add('hidden');
            currentSearchInput = null;
        }

        function performItemSearch(query) {
            if (query.length < 2) {
                document.getElementById('item-search-results').innerHTML = '<p class="text-gray-500 text-center py-4">Start typing to search...</p>';
                return;
            }
            const vehicle = (document.getElementById('input_car_mark')?.value || '').trim();
            const params = new URLSearchParams({ q: query });
            if (vehicle) params.set('vehicle', vehicle);

            fetch(`admin/api_labors_parts.php?` + params.toString())
                .then(response => response.json())
                .then(resp => {
                    const data = resp && resp.data ? resp.data : resp;
                    const results = Array.isArray(data) ? data : [];
                    displaySearchResults(results);
                })
                .catch(error => {
                    console.error('Error fetching search results:', error);
                    document.getElementById('item-search-results').innerHTML = '<p class="text-red-500 text-center py-4">Error loading results</p>';
                });
        }

        function displaySearchResults(results) {
            const container = document.getElementById('item-search-results');
            if (results.length === 0) {
                container.innerHTML = '<p class="text-gray-500 text-center py-4">No items found</p>';
                return;
            }
            container.innerHTML = results.map(item => `
                <div class="border-b border-gray-200 p-3 hover:bg-gray-50 cursor-pointer" data-id="${item.id}" data-name="${item.name.replace(/"/g, '&quot;')}" data-type="${item.type}" data-price="${item.default_price || 0}" onclick="selectSearchItem(this)">
                    <div class="flex justify-between items-center">
                        <div>
                            <div class="font-medium">${item.name}</div>
                            <div class="text-sm text-gray-600">${item.description || ''}${item.vehicle_make_model ? ` — <span class="text-xs text-gray-500">${item.vehicle_make_model}</span>` : ''}</div>
                        </div>
                        <div class="text-right">
                            <div class="text-sm font-medium text-blue-600">${item.default_price > 0 ? item.default_price + ' ₾' : ''}</div>
                            <div class="text-xs text-gray-500 uppercase">${item.type}</div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        function selectSearchItem(element) {
            const id = element.dataset.id;
            const name = element.dataset.name;
            const type = element.dataset.type;
            const price = parseFloat(element.dataset.price) || 0;
            const vehicle = element.dataset.vehicle || '';
            selectItem(id, name, type, price, vehicle);
        }

        function selectItem(id, name, type, price, vehicle) {
            if (currentSearchInput) {
                currentSearchInput.value = name;
                const row = currentSearchInput.closest('tr');

                // Record matched DB item id/type on the row for server-side processing
                if (id) row.dataset.itemDbId = id;
                if (type) row.dataset.itemDbType = type;
                if (vehicle) row.dataset.itemDbVehicle = vehicle;

                // Fill appropriate price field depending on type
                if (type === 'part') {
                    const partInput = row.querySelector('.item-price-part');
                    if (price > 0 && (!partInput.value || partInput.value == '0')) {
                        partInput.value = price;
                    }
                } else if (type === 'labor') {
                    const svcInput = row.querySelector('.item-price-svc');
                    if (price > 0 && (!svcInput.value || svcInput.value == '0')) {
                        svcInput.value = price;
                    }
                }

                calculateTotals();
                closeItemSearch();
            }
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            // Add event listener for search input
            document.getElementById('item-search-input').addEventListener('input', function() {
                performItemSearch(this.value.trim());
            });
            // ... existing code ...
        });
    </script>

    <!-- Item Search Modal -->
    <div id="item-search-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-11/12 max-w-2xl shadow-lg rounded-md bg-white">
            <div class="mt-3">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-medium text-gray-900">Search Items</h3>
                    <button onclick="closeItemSearch()" class="text-gray-400 hover:text-gray-600">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <div class="mb-4">
                    <input type="text" id="item-search-input" placeholder="Type to search labors and parts..." class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div id="item-search-results" class="max-h-96 overflow-y-auto">
                    <!-- Results will be populated here -->
                </div>
            </div>
        </div>
    </div>
</body>
</html>
