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

// Support loading a saved invoice into the editor/preview for printing (index.php?print_id=123)
$serverInvoice = null;
$invoiceNotFound = false;
if (isset($_GET['print_id']) && is_numeric($_GET['print_id'])) {
    $pid = (int)$_GET['print_id'];
    try {
        if (!isset($pdo)) {
            throw new Exception("Database connection not available");
        }
        $stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
        $stmt->execute([$pid]);
        $inv = $stmt->fetch();
        if ($inv) {
            $inv_items = json_decode($inv['items'], true) ?: [];
            $inv_customer = null;
            if (!empty($inv['customer_id'])) {
                $s = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
                $s->execute([(int)$inv['customer_id']]);
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
                'mileage' => $inv['mileage'],
                'service_manager' => $inv['service_manager'],
                'service_manager_id' => isset($inv['service_manager_id']) ? (int)$inv['service_manager_id'] : 0,
                'items' => $inv_items,
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
        }
        /* Floating Action Button Styles */
        .fab-button {
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            animation: slideInFAB 0.6s ease-out;
        }

        .fab-button:hover {
            backdrop-filter: blur(15px);
        }

        @keyframes slideInFAB {
            from {
                opacity: 0;
                transform: translateY(100px) scale(0.8);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .fab-button:nth-child(1) { animation-delay: 0.1s; }
        .fab-button:nth-child(2) { animation-delay: 0.2s; }
        .fab-button:nth-child(3) { animation-delay: 0.3s; }
        .fab-button:nth-child(4) { animation-delay: 0.4s; }

        /* Pulse animation for primary action */
        @keyframes pulse-ring {
            0% { transform: scale(0.33); }
            40%, 50% { opacity: 1; }
            100% { opacity: 0; transform: scale(1.5); }
        }

        .fab-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 100%;
            height: 100%;
            border-radius: inherit;
            background: inherit;
            animation: pulse-ring 2s infinite;
            transform: translate(-50%, -50%);
        }    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen font-sans text-gray-800 antialiased overflow-x-hidden">
    <?php include 'partials/sidebar.php'; ?>

    <main class="max-w-7xl mx-auto p-4 md:p-8 pb-24 md:pb-32 print:p-0 print:max-w-none print:pb-0 ml-0 md:ml-64" role="main">
        <!-- Header -->
        <header class="mb-8 print-hidden">
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
                        <?php if (isset($_GET['print_id']) && !empty($serverInvoice)): ?>
                            Invoice #<?php echo htmlspecialchars($_GET['print_id']); ?> - Ready for Print
                        <?php else: ?>
                            Invoice Editor
                        <?php endif; ?>
                    </h1>
                    <p class="mt-2 text-gray-600">
                        <?php if (isset($_GET['print_id']) && !empty($serverInvoice)): ?>
                            Invoice loaded successfully. Use the Preview or Print buttons below to view or print the invoice.
                        <?php else: ?>
                            Create and manage auto shop invoices with ease.
                        <?php endif; ?>
                    </p>

                    <?php if (isset($_GET['print_id']) && !empty($serverInvoice)): ?>
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
                                    <p>Invoice #<?php echo htmlspecialchars($_GET['print_id']); ?> has been loaded and all data has been populated. You can now preview the invoice or print it directly.</p>
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
                                <p>The invoice with ID <?php echo htmlspecialchars($_GET['print_id']); ?> could not be found. It may have been deleted or the ID may be incorrect.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Floating Action Buttons - Bottom Sticky -->
                <div class="fixed bottom-0 left-0 right-0 bg-white/95 backdrop-blur-sm border-t border-gray-200 px-4 py-3 z-50 print-hidden shadow-lg">
                    <div class="max-w-7xl mx-auto flex items-center justify-center gap-4">
                        <!-- Edit Details FAB -->
                        <div class="group relative">
                            <button onclick="switchTab('edit')" id="fab-edit" class="fab-button w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                            </button>
                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <div class="bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap shadow-lg">
                                    Edit Details
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Invoice FAB -->
                        <div class="group relative">
                            <button onclick="switchTab('preview')" id="fab-preview" class="fab-button w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                            </button>
                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <div class="bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap shadow-lg">
                                    Preview Invoice
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Save FAB - Primary Action -->
                        <div class="group relative">
                            <button type="submit" form="invoice-form" class="fab-button fab-primary w-16 h-16 bg-gradient-to-r from-green-500 to-emerald-600 hover:from-green-600 hover:to-emerald-700 text-white rounded-full shadow-xl hover:shadow-2xl transform hover:scale-110 transition-all duration-300 flex items-center justify-center group ring-4 ring-white relative overflow-hidden">
                                <svg class="w-7 h-7 relative z-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                </svg>
                            </button>
                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <div class="bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap shadow-lg">
                                    Save Invoice
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>

                        <!-- Print FAB -->
                        <div class="group relative">
                            <button onclick="handlePrint()" class="fab-button w-12 h-12 bg-gradient-to-r from-indigo-500 to-blue-600 hover:from-indigo-600 hover:to-blue-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                </svg>
                            </button>
                            <div class="absolute bottom-full mb-2 left-1/2 transform -translate-x-1/2 opacity-0 group-hover:opacity-100 transition-opacity duration-300 pointer-events-none">
                                <div class="bg-gray-800 text-white px-3 py-2 rounded-lg text-sm font-medium whitespace-nowrap shadow-lg">
                                    Print Invoice
                                    <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-800"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>
        
        <!-- ================= EDIT MODE ================= -->
        <div id="edit-mode" class="block print-hidden animate-fade-in">
            <form id="invoice-form" action="save_invoice.php" method="post" onsubmit="return handleSave()" role="form" aria-label="Invoice form">
                <input type="hidden" name="creation_date" id="hidden_creation_date">
                <input type="hidden" name="service_manager" id="hidden_service_manager">
                <input type="hidden" name="customer_name" id="hidden_customer_name">
                <input type="hidden" name="phone_number" id="hidden_phone_number">
                <input type="hidden" name="car_mark" id="hidden_car_mark">
                <input type="hidden" name="plate_number" id="hidden_plate_number">
                <input type="hidden" name="mileage" id="hidden_mileage">
                <input type="hidden" name="parts_total" id="hidden_parts_total">
                <input type="hidden" name="service_total" id="hidden_service_total">
                <input type="hidden" name="grand_total" id="hidden_grand_total">
                <input type="hidden" name="print_after_save" id="print_after_save">

                <!-- Desktop Layout (unchanged) -->
                <div id="desktop-layout" class="block">
                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

                    <!-- Left Column: Inputs -->
                    <div class="lg:col-span-1 space-y-6">

                        <!-- Workflow Card -->
                        <div class="bg-white p-6 rounded-xl shadow-lg border border-gray-200">
                            <h2 class="text-xl font-bold mb-6 flex items-center gap-3 text-slate-700">
                                <svg class="h-6 w-6 text-yellow-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                </svg>
                                Workflow Details
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="input_creation_date" class="block text-sm font-medium text-gray-700 mb-2">Creation Date (შემოსვლის დრო)</label>
                                    <input type="datetime-local" id="input_creation_date" name="creation_date" value="<?php echo $currentDate; ?>" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 p-3 text-base transition" aria-describedby="date-help">
                                    <p id="date-help" class="mt-1 text-xs text-gray-500">Select the date and time when the vehicle arrived.</p>
                                </div>
                                <div>
                                    <label for="input_service_manager" class="block text-sm font-medium text-gray-700 mb-2">Service Manager (სერვისის მენეჯერი)</label>
                                    <input type="text" id="input_service_manager" placeholder="Manager Name" value="<?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?>" class="w-full border border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-yellow-500 focus:border-yellow-500 p-3 text-base transition" aria-describedby="manager-help">
                                    <input type="hidden" id="input_service_manager_id" name="service_manager_id" value="<?php echo (int)($_SESSION['user_id'] ?? 0); ?>">
                                    <p id="manager-help" class="mt-1 text-xs text-gray-500">The service manager handling this invoice.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Customer Card -->
                        <div class="bg-gradient-to-br from-blue-50 to-indigo-100 p-4 md:p-6 rounded-xl shadow-lg border border-blue-200 hover:shadow-xl transition-all duration-300">
                            <h2 class="text-lg md:text-xl font-bold mb-4 flex items-center gap-2 text-slate-700">
                                <svg class="h-5 w-5 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                </svg>
                                Customer Info
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="input_customer_name" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                        <svg class="h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                        Customer Name (კლიენტი)
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="input_customer_name" placeholder="First Last Name" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 border text-base pl-10 transition-all duration-200" aria-describedby="customer-name-help">
                                        <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                                        </svg>
                                    </div>
                                    <input type="hidden" id="input_customer_id" name="customer_id">
                                    <p id="customer-name-help" class="mt-1 text-xs text-gray-500">Enter the full name of the customer as it appears on their ID.</p>
                                </div>
                                <div>
                                    <label for="input_phone_number" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                        <svg class="h-4 w-4 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                        Phone Number (ტელეფონი)
                                    </label>
                                    <div class="relative">
                                        <input type="tel" id="input_phone_number" placeholder="+995 555 00 00 00" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 p-3 border text-base pl-10 transition-all duration-200" aria-describedby="phone-help">
                                        <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                        </svg>
                                    </div>
                                    <p id="phone-help" class="mt-1 text-xs text-gray-500">Include country code for international numbers.</p>
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Card -->
                        <div class="bg-gradient-to-br from-red-50 to-pink-100 p-4 md:p-6 rounded-xl shadow-lg border border-red-200 hover:shadow-xl transition-all duration-300">
                            <h2 class="text-lg md:text-xl font-bold mb-4 flex items-center gap-2 text-slate-700">
                                <svg class="h-5 w-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                                Vehicle Data
                            </h2>
                            <div class="space-y-4">
                                <div>
                                    <label for="input_car_mark" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                        <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                        Car Mark/Model (ავტომანქანა)
                                    </label>
                                    <div class="relative">
                                        <input type="text" id="input_car_mark" placeholder="e.g., Toyota Camry" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 p-3 border text-base pl-10 transition-all duration-200" aria-describedby="car-mark-help">
                                        <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                        </svg>
                                    </div>
                                    <p id="car-mark-help" class="mt-1 text-xs text-gray-500">Enter the make and model of the vehicle.</p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="input_plate_number" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                            <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Plate Number (ნომერი)
                                        </label>
                                        <div class="relative">
                                            <input type="text" id="input_plate_number" placeholder="ZZ-000-ZZ" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 p-3 border text-base pl-10 transition-all duration-200" aria-describedby="plate-help">
                                            <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                        </div>
                                        <p id="plate-help" class="mt-1 text-xs text-gray-500">License plate in Georgian format.</p>
                                    </div>
                                    <div>
                                        <label for="input_mileage" class="block text-sm font-medium text-gray-700 mb-2 flex items-center gap-2">
                                            <svg class="h-4 w-4 text-red-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                            Mileage (გარბენი)
                                        </label>
                                        <div class="relative">
                                            <input type="text" id="input_mileage" placeholder="150000 km" class="w-full border-gray-300 rounded-lg shadow-sm focus:ring-2 focus:ring-red-500 focus:border-red-500 p-3 border text-base pl-10 transition-all duration-200" aria-describedby="mileage-help">
                                            <svg class="absolute left-3 top-3.5 h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                                            </svg>
                                        </div>
                                        <p id="mileage-help" class="mt-1 text-xs text-gray-500">Current odometer reading in kilometers.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Line Items -->
                    <div class="lg:col-span-2">
                        <div class="bg-gradient-to-br from-gray-50 to-slate-100 p-4 md:p-6 rounded-xl shadow-lg border border-gray-200 h-full flex flex-col hover:shadow-xl transition-all duration-300">
                            <div class="flex justify-between items-center mb-4">
                                <h2 class="text-lg md:text-xl font-bold text-slate-700 flex items-center gap-2">
                                    <svg class="h-5 w-5 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                                    </svg>
                                    Service & Parts
                                </h2>
                                <button type="button" onclick="addItemRow()" class="flex items-center gap-2 text-sm bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-200 font-medium shadow-md hover:shadow-lg active:scale-95 focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                                    </svg>
                                    Add Row
                                </button>
                            </div>

                            <div class="overflow-x-auto -mx-4 md:mx-0 px-4 md:px-0 flex-grow">
                                <table class="w-full text-xs sm:text-sm text-left min-w-[500px] md:min-w-[600px] bg-white rounded-lg overflow-hidden shadow-sm">
                                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 uppercase font-semibold">
                                        <tr>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-6 md:w-8 text-center">#</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-1/3">Item Name</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-12 md:w-16 text-center">Qty</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-20 md:w-24 text-right">Part Price</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-20 md:w-24 text-right">Svc Price</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-24 md:w-32">Technician</th>
                                            <th class="px-2 md:px-4 py-2 md:py-3 w-10 md:w-12 text-center">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="items-table-body" class="divide-y divide-gray-100 hover:bg-gray-50 transition-colors">
                                        <!-- Rows added via JS -->
                                    </tbody>
                                </table>
                            </div>

                            <!-- Live Totals -->
                            <div class="mt-6 border-t border-gray-200 pt-6 grid grid-cols-1 md:grid-cols-3 gap-4 text-right">
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                                    <p class="text-xs text-gray-600 uppercase font-medium mb-1">Parts Total</p>
                                    <p class="font-bold text-lg text-gray-800" id="display_parts_total"></p>
                                </div>
                                <div class="bg-gradient-to-r from-gray-50 to-gray-100 p-4 rounded-lg border border-gray-200 hover:shadow-md transition-all duration-200">
                                    <p class="text-xs text-gray-600 uppercase font-medium mb-1">Service Total</p>
                                    <p class="font-bold text-lg text-gray-800" id="display_service_total"></p>
                                </div>
                                <div class="bg-gradient-to-r from-green-50 to-emerald-100 p-4 rounded-lg border-2 border-green-200 hover:shadow-lg transition-all duration-200">
                                    <p class="text-xs text-green-700 uppercase font-medium mb-1">Grand Total</p>
                                    <p class="font-bold text-xl text-green-800" id="display_grand_total"></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        <!-- ================= PREVIEW / PRINT MODE ================= -->
        <div id="preview-mode" class="hidden print-visible flex-col items-center">
            <div class="mb-4 print-hidden text-gray-500 text-sm flex items-center gap-2">
                <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
                <span>Click the <strong>Print</strong> button above to download as PDF or print.</span>
            </div>

            <!-- A4 Container -->
<?php include 'partials/invoice_print_template.php'; ?>
        </div>

    </main>

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
                    const customerId = document.getElementById('input_customer_id').value;

                    if (customerName || phoneNumber || customerId) {
                        // Customer info already filled, don't overwrite
                        return;
                    }

                    fetch(apiBase + '/admin/api_customers.php?plate=' + encodeURIComponent(plate))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            document.getElementById('input_customer_name').value = data.full_name || '';
                            document.getElementById('input_phone_number').value = data.phone || '';
                            document.getElementById('input_car_mark').value = data.car_mark || '';
                            const cid = document.getElementById('input_customer_id'); if (cid) cid.value = data.id || '';
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
                        const res = await fetch(endpoint + encodeURIComponent(q));
                        if (!res.ok) { box.innerHTML = ''; return; }
                        const list = await res.json();
                        if (!Array.isArray(list)) { box.innerHTML = ''; return; }
                        box.innerHTML = list.map(item => `<div class="px-3 py-2 cursor-pointer hover:bg-gray-100" data-id="${item.id}" data-json='${JSON.stringify(item).replace(/'/g, "\\'") }'>${formatItem(item)}</div>`).join('');
                        box.querySelectorAll('div').forEach(el => el.addEventListener('click', () => {
                            const item = JSON.parse(el.getAttribute('data-json'));
                            onSelect(item);
                            box.innerHTML = '';
                        }));
                    } catch (e) {
                        box.innerHTML = '';
                    }
                }));

                document.addEventListener('click', (ev) => { if (!input.contains(ev.target) && !box.contains(ev.target)) box.innerHTML = ''; });
            }

            // Attach service manager typeahead
            const sm = document.getElementById('input_service_manager');
            if (sm) {
                attachTypeahead(sm, apiBase + '/admin/api_users.php?q=', u => u.username, (it) => {
                    sm.value = it.username;
                    const hid = document.getElementById('input_service_manager_id'); if (hid) hid.value = it.id;
                });
            }

            // Attach customer name typeahead
            const cn = document.getElementById('input_customer_name');
            if (cn) {
                attachTypeahead(cn, apiBase + '/admin/api_customers.php?q=', c => `${c.plate_number} — ${c.full_name}` , (it) => {
                    cn.value = it.full_name || '';
                    document.getElementById('input_phone_number').value = it.phone || '';
                    document.getElementById('input_plate_number').value = it.plate_number || '';
                    const cid = document.getElementById('input_customer_id'); if (cid) cid.value = it.id;
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
                    const customerId = document.getElementById('input_customer_id').value;

                    if (customerName || plateNumber || customerId) {
                        // Customer info already filled, don't overwrite
                        return;
                    }

                    fetch(apiBase + '/admin/api_customers.php?phone=' + encodeURIComponent(val))
                        .then(r => { if(!r.ok) throw new Error('no'); return r.json(); })
                        .then(data => {
                            if (!data) return;
                            document.getElementById('input_customer_name').value = data.full_name || '';
                            document.getElementById('input_plate_number').value = data.plate_number || '';
                            const cid = document.getElementById('input_customer_id'); if (cid) cid.value = data.id;
                        }).catch(e=>{});
                });
            }

            // If server supplied invoice data, populate and optionally print
            if (window.serverInvoice) {
                loadServerInvoice(window.serverInvoice);
            }
        });

        function addItemRow() {
            rowCount++;
            const tbody = document.getElementById('items-table-body');
            const tr = document.createElement('tr');
            tr.className = "hover:bg-gray-50 item-row";
            tr.id = `row-${rowCount}`;
            tr.innerHTML = `
                <td class="px-3 py-3 font-medium text-center text-gray-400 row-number"></td>
                <td class="px-3 py-3"><input type="text" placeholder="Description" class="item-name w-full border-gray-200 rounded p-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500"></td>
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
            if (inv.mileage) document.getElementById('input_mileage').value = inv.mileage;
            if (inv.customer && inv.customer.id) document.getElementById('input_customer_id').value = inv.customer.id;

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

        function switchTab(tab) {
            const editMode = document.getElementById('edit-mode');
            const previewMode = document.getElementById('preview-mode');
            const fabEdit = document.getElementById('fab-edit');
            const fabPreview = document.getElementById('fab-preview');

            if (tab === 'edit') {
                editMode.classList.remove('hidden');
                previewMode.classList.add('hidden');
                previewMode.classList.remove('flex');

                // Update FAB buttons - Edit active
                fabEdit.className = "fab-button w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group ring-4 ring-blue-300";
                fabPreview.className = "fab-button w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group";
            } else {
                updatePreviewData();
                editMode.classList.add('hidden');
                previewMode.classList.remove('hidden');
                previewMode.classList.add('flex');

                // Update FAB buttons - Preview active
                fabEdit.className = "fab-button w-12 h-12 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group";
                fabPreview.className = "fab-button w-12 h-12 bg-gradient-to-r from-purple-500 to-purple-600 hover:from-purple-600 hover:to-purple-700 text-white rounded-full shadow-md hover:shadow-lg transform hover:scale-110 transition-all duration-300 flex items-center justify-center group ring-4 ring-purple-300";
            }
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
                'input_service_manager': 'out_service_manager'
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
            document.getElementById('hidden_mileage').value = mileage;
            
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
                    index++;
                }
            });
            return true;
        }

        function handleSave() {
            // Basic validation before preparing data
            const customerName = document.getElementById('input_customer_name').value.trim();
            const serviceManager = document.getElementById('input_service_manager').value.trim();

            if (!customerName) {
                alert('Please enter a customer name.');
                document.getElementById('input_customer_name').focus();
                return false;
            }

            if (!serviceManager) {
                alert('Please enter a service manager.');
                document.getElementById('input_service_manager').focus();
                return false;
            }

            return prepareData();
        }

        function handlePrint() {
            document.getElementById('print_after_save').value = '1';
            document.getElementById('invoice-form').submit();
        }

        // Initialize on load
        document.addEventListener('DOMContentLoaded', () => {
            // ... existing code ...
        });
    </script>
</body>
</html>
