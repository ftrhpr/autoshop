<?php
require_once 'config.php';

// Check if invoice_id is provided
if (!isset($_GET['invoice_id'])) {
    die("Invoice ID is not specified.");
}

$invoice_id = $_GET['invoice_id'];

// Fetch invoice from MySQL
try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        die("Invoice not found in MySQL database.");
    }
} catch (PDOException $e) {
    die("Error fetching invoice from MySQL: " . $e->getMessage());
}

// Transfer to SQL Server
if ($sql_server_conn) {
    // Check if the invoice already exists in SQL Server
    $tsql_check = "SELECT COUNT(*) FROM invoices WHERE id = ?";
    $params_check = [$invoice_id];
    $stmt_check = sqlsrv_query($sql_server_conn, $tsql_check, $params_check);

    if ($stmt_check === false) {
        die("Error checking for existing invoice in SQL Server: " . print_r(sqlsrv_errors(), true));
    }

    $count = sqlsrv_fetch_array($stmt_check)[0];

    if ($count > 0) {
        // Update existing record
        $tsql = "UPDATE invoices SET customer_name = ?, plate_number = ?, vin = ?, created_at = ?, invoice_data = ?, fina_status = ?, transferred_at = GETDATE() WHERE id = ?";
        $params = [
            $invoice['customer_name'],
            $invoice['plate_number'],
            $invoice['vin'],
            $invoice['created_at'],
            $invoice['invoice_data'],
            $invoice['fina_status'],
            $invoice['id']
        ];
    } else {
        // Insert new record
        $tsql = "INSERT INTO invoices (id, customer_name, plate_number, vin, created_at, invoice_data, fina_status) VALUES (?, ?, ?, ?, ?, ?, ?)";
        $params = [
            $invoice['id'],
            $invoice['customer_name'],
            $invoice['plate_number'],
            $invoice['vin'],
            $invoice['created_at'],
            $invoice['invoice_data'],
            $invoice['fina_status']
        ];
    }

    $stmt_sql_server = sqlsrv_query($sql_server_conn, $tsql, $params);

    if ($stmt_sql_server === false) {
        die("Error transferring invoice to SQL Server: " . print_r(sqlsrv_errors(), true));
    }

    // Redirect back to the manager page with a success message
    header("Location: manager.php?transfer_success=1");
    exit();

} else {
    die("SQL Server connection is not available.");
}
?>
