<?php
// API to get vehicle models for a specific make
require '../config.php';

header('Content-Type: application/json');

$make_id = $_GET['make_id'] ?? null;

if (!$make_id) {
    http_response_code(400);
    echo json_encode(['error' => 'make_id parameter is required']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM vehicle_models WHERE make_id = ? ORDER BY name");
    $stmt->execute([$make_id]);
    $models = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // If no data in database, return sample models based on make_id
    if (empty($models)) {
        $sampleModels = [
            1 => [ // Toyota
                ['id' => 101, 'name' => 'Camry'],
                ['id' => 102, 'name' => 'Corolla'],
                ['id' => 103, 'name' => 'RAV4'],
                ['id' => 104, 'name' => 'Highlander'],
                ['id' => 105, 'name' => 'Prius']
            ],
            2 => [ // Honda
                ['id' => 201, 'name' => 'Civic'],
                ['id' => 202, 'name' => 'Accord'],
                ['id' => 203, 'name' => 'CR-V'],
                ['id' => 204, 'name' => 'Pilot'],
                ['id' => 205, 'name' => 'Fit']
            ],
            3 => [ // BMW
                ['id' => 301, 'name' => '3 Series'],
                ['id' => 302, 'name' => '5 Series'],
                ['id' => 303, 'name' => 'X3'],
                ['id' => 304, 'name' => 'X5'],
                ['id' => 305, 'name' => 'X1']
            ],
            4 => [ // Mercedes-Benz
                ['id' => 401, 'name' => 'C-Class'],
                ['id' => 402, 'name' => 'E-Class'],
                ['id' => 403, 'name' => 'GLC'],
                ['id' => 404, 'name' => 'GLE'],
                ['id' => 405, 'name' => 'A-Class']
            ],
            5 => [ // Ford
                ['id' => 501, 'name' => 'F-150'],
                ['id' => 502, 'name' => 'Explorer'],
                ['id' => 503, 'name' => 'Escape'],
                ['id' => 504, 'name' => 'Mustang'],
                ['id' => 505, 'name' => 'Focus']
            ]
        ];

        $models = $sampleModels[$make_id] ?? [['id' => 999, 'name' => 'Sample Model']];
    }

    echo json_encode($models);
} catch (Exception $e) {
    // Fallback sample data if database error
    $models = [['id' => 999, 'name' => 'Sample Model']];
    echo json_encode($models);
}
?>