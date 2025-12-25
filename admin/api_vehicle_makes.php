<?php
// API to get all vehicle makes for dropdown
require '../config.php';

header('Content-Type: application/json');

try {
    $stmt = $pdo->prepare("SELECT id, name FROM vehicle_makes ORDER BY name");
    $stmt->execute();
    $makes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no data in database, return some sample data for testing
    if (empty($makes)) {
        $makes = [
            ['id' => 1, 'name' => 'Toyota'],
            ['id' => 2, 'name' => 'Honda'],
            ['id' => 3, 'name' => 'BMW'],
            ['id' => 4, 'name' => 'Mercedes-Benz'],
            ['id' => 5, 'name' => 'Ford'],
            ['id' => 6, 'name' => 'Chevrolet'],
            ['id' => 7, 'name' => 'Nissan'],
            ['id' => 8, 'name' => 'Volkswagen'],
            ['id' => 9, 'name' => 'Audi'],
            ['id' => 10, 'name' => 'Hyundai']
        ];
    }

    echo json_encode($makes);
} catch (Exception $e) {
    // Fallback sample data if database error
    $makes = [
        ['id' => 1, 'name' => 'Toyota'],
        ['id' => 2, 'name' => 'Honda'],
        ['id' => 3, 'name' => 'BMW'],
        ['id' => 4, 'name' => 'Mercedes-Benz'],
        ['id' => 5, 'name' => 'Ford']
    ];
    echo json_encode($makes);
}
?>