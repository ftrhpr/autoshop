<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$id) {
    die('Invoice ID required');
}

$stmt = $pdo->prepare('SELECT * FROM invoices WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) die('Invoice not found');

$items = json_decode($invoice['items'], true) ?: [];

// Resolve customer
$customer = null;
if (!empty($invoice['customer_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$invoice['customer_id']]);
    $customer = $stmt->fetch();
}

// Resolve service manager username if id present
$sm_username = '';
if (!empty($invoice['service_manager_id'])) {
    $stmt = $pdo->prepare('SELECT username FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int)$invoice['service_manager_id']]);
    $sm = $stmt->fetch();
    if ($sm) $sm_username = $sm['username'];
}

// Totals
$partsTotal = number_format((float)$invoice['parts_total'], 2);
$svcTotal = number_format((float)$invoice['service_total'], 2);
$grandTotal = number_format((float)$invoice['grand_total'], 2);

?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Print Invoice #<?php echo $invoice['id']; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Print Styles - Force A4 layout regardless of screen size */
        @media print {
            @page {
                margin: 3mm;
                size: A4;
            }
            html, body {
                height: 100%;
                margin: 0 !important;
                padding: 0 !important;
                overflow: visible;
                -webkit-print-color-adjust: exact !important;
                color-adjust: exact !important;
            }
            .print-hidden { display: none !important; }
            .print-visible { display: block !important; }
            .print-no-shadow { box-shadow: none !important; }

            /* Prevent page breaks */
            .invoice-container,
            .a4-container,
            table,
            tr,
            td,
            th {
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }

            /* Force single page */
            @page {
                size: A4;
                margin: 3mm;
            }

            /* Force A4 container dimensions - optimized for single page */
            .a4-container {
                width: 204mm !important; /* 210mm - 2*3mm margins */
                min-width: 204mm !important;
                max-width: 204mm !important;
                min-height: 291mm !important; /* 297mm - 2*3mm margins */
                max-height: 291mm !important;
                padding: 6mm !important; /* Reduced padding for more content space */
                margin: 0 !important;
                box-sizing: border-box !important;
                overflow: visible !important;
            }

            /* Compact padding for more content */
            .invoice-container {
                padding: 0 !important;
                margin: 0 !important;
            }

            /* Force desktop header layout - more compact */
            .invoice-container > div:first-child {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1.5rem !important;
                margin-bottom: 0.5rem !important;
            }

            /* Force desktop info grid layout - more compact */
            .info-grid {
                grid-template-columns: 1fr 1fr !important;
                gap: 2rem 1.5rem !important;
                margin-bottom: 0.5rem !important;
            }

            /* Force desktop info section layout */
            .info-section {
                grid-template-columns: 120px 1fr !important;
            }
            .info-section:nth-child(2) {
                grid-template-columns: 160px 1fr !important;
            }

            /* Compact info fields */
            .info-section .border-b {
                height: 20px !important;
                padding: 0 4px !important;
                margin-bottom: 2px !important;
            }

            /* Force desktop text sizes - slightly smaller for compactness */
            .invoice-container {
                font-size: 12px !important;
            }
            .invoice-container .text-xs { font-size: 10px !important; }
            .invoice-container .text-sm { font-size: 12px !important; }
            .invoice-container .text-base { font-size: 14px !important; }
            .invoice-container .text-lg { font-size: 16px !important; }
            .invoice-container .text-\[8px\] { font-size: 8px !important; }
            .invoice-container .text-\[10px\] { font-size: 10px !important; }
            .invoice-container .text-\[12px\] { font-size: 12px !important; }

            /* Force desktop logo size */
            .invoice-container img {
                width: 45% !important;
                height: auto !important;
            }

            /* Compact HR */
            .invoice-container hr {
                margin: 0.25rem 0 !important;
            }

            /* Exact A4 Table Styling - ultra compact */
            table {
                border-collapse: collapse !important;
                border-color: #000 !important;
                width: 100% !important;
                font-size: 7px !important; /* Even smaller for more rows */
                min-width: auto !important;
                margin-bottom: 0.25rem !important;
            }
            td, th {
                border: 1px solid #000 !important;
                color: #000 !important;
                padding: 1px 2px !important;
                line-height: 1 !important;
            }

            /* Force desktop table layout */
            .invoice-container table th:nth-child(1) { width: 6% !important; }
            .invoice-container table th:nth-child(2) { width: auto !important; min-width: 120px !important; }
            .invoice-container table th:nth-child(3) { width: 10% !important; }
            .invoice-container table th:nth-child(4) { width: 15% !important; }
            .invoice-container table th:nth-child(5) { width: 15% !important; }
            .invoice-container table th:nth-child(6) { width: 15% !important; }
            .invoice-container table th:nth-child(7) { width: 15% !important; }
            .invoice-container table th:nth-child(8) { width: 20% !important; }

            /* Compact table rows */
            .invoice-container table tr {
                height: 16px !important;
            }

            /* Compact grand total */
            .invoice-container table + div {
                margin-top: 0 !important;
                margin-bottom: 0.25rem !important;
            }
            .invoice-container table + div > div {
                padding: 2px 6px !important;
                margin: 0 !important;
            }

            /* Compact legal text */
            .invoice-container .text-\[8px\],
            .invoice-container .text-\[10px\],
            .invoice-container .text-\[12px\] {
                font-size: 6px !important;
                line-height: 1.2 !important;
                margin-bottom: 0.25rem !important;
            }

            /* Compact signatures */
            .invoice-container .grid-cols-1 {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 1rem !important;
                margin-top: 0.25rem !important;
            }
            .invoice-container .border-t {
                padding-top: 1px !important;
                margin-top: 0.25rem !important;
            }

            /* Remove responsive overflow */
            .overflow-x-auto {
                overflow: visible !important;
            }

            /* Ensure background is white for printing */
            .bg-gray-200\/50 { background: white !important; }
            .bg-white { background: white !important; }

            /* Final single-page enforcement */
            .invoice-container {
                max-height: 279mm !important; /* Available height after margins */
                overflow: visible !important;
            }
        }

        /* Responsive adjustments for mobile - screen only */
        @media screen and (max-width: 768px) {
            .invoice-container {
                padding: 1rem !important;
                margin: 0 !important;
                min-width: 100% !important;
                width: 100% !important;
            }

            .info-grid {
                grid-template-columns: 1fr !important;
                gap: 1rem !important;
            }

            .info-section {
                grid-template-columns: 1fr !important;
            }

            .table-responsive {
                font-size: 10px !important;
            }

            .table-responsive th,
            .table-responsive td {
                padding: 2px !important;
            }
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Sticky New Invoice Button -->
    <div class="fixed bottom-6 right-6 z-50 print-hidden">
        <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg shadow-lg hover:shadow-xl transition-all duration-300 flex items-center gap-2 font-medium">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            New Invoice
        </a>
    </div>

    <!-- Print Controls (Mobile Friendly) -->
    <div class="bg-white border-b border-gray-200 px-4 py-3 print-hidden sticky top-0 z-40">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="text-lg font-semibold text-gray-900">Invoice #<?php echo $invoice['id']; ?></h1>
                <span class="text-sm text-gray-500">Print Preview</span>
            </div>
            <div class="flex items-center gap-2">
                <button onclick="window.print()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Print
                </button>
                <a href="index.php" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm font-medium transition-colors flex items-center gap-2">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                    </svg>
                    New
                </a>
            </div>
        </div>
    </div>

    <!-- Invoice Content -->
    <div class="max-w-7xl mx-auto p-4 pb-24">
        <?php include 'partials/invoice_print_template.php'; ?>
    </div>

    <script>
        // Auto print when loaded (only on desktop or when explicitly requested)
        window.addEventListener('load', function() {
            const isMobile = window.innerWidth < 768;
            const urlParams = new URLSearchParams(window.location.search);
            const autoPrint = urlParams.get('print') === '1';

            if (autoPrint && !isMobile) {
                setTimeout(() => { window.print(); }, 500);
            }
        });

        // Handle responsive table scrolling
        document.addEventListener('DOMContentLoaded', function() {
            const tables = document.querySelectorAll('table');
            tables.forEach(table => {
                const wrapper = table.parentElement;
                if (wrapper && !wrapper.classList.contains('overflow-x-auto')) {
                    wrapper.classList.add('overflow-x-auto');
                }
            });
        });
    </script>
</body>
</html>