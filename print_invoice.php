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
    </style>
</head>
<body class="bg-white text-black">
<?php include 'partials/invoice_print_template.php'; ?>

    <script>
        // Auto print when loaded
        window.addEventListener('load', function() { setTimeout(() => { window.print(); }, 200); });
    </script>
</body>
</html>