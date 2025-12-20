<?php
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = $_POST;

    // Process items
    $items = [];
    for ($i = 0; isset($data["item_name_$i"]); $i++) {
        if (!empty($data["item_name_$i"])) {
            $items[] = [
                'name' => $data["item_name_$i"],
                'qty' => $data["item_qty_$i"],
                'price_part' => $data["item_price_part_$i"],
                'price_svc' => $data["item_price_svc_$i"],
                'tech' => $data["item_tech_$i"]
            ];
        }
    }

    $stmt = $pdo->prepare("INSERT INTO invoices (creation_date, service_manager, customer_name, phone, car_mark, plate_number, mileage, items, parts_total, service_total, grand_total, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['creation_date'],
        $data['service_manager'],
        $data['customer_name'],
        $data['phone_number'],
        $data['car_mark'],
        $data['plate_number'],
        $data['mileage'],
        json_encode($items),
        $data['parts_total'],
        $data['service_total'],
        $data['grand_total'],
        $_SESSION['user_id']
    ]);

    $invoice_id = $pdo->lastInsertId();

    // Redirect to view or back
    header('Location: view_invoice.php?id=' . $invoice_id);
    exit;
}
?>