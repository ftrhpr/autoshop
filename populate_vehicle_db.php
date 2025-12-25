<?php
// Script to populate vehicle database from Car2DB API
// Run this script to fetch and insert vehicle data

require 'config.php';

$apiKey = 'n.bikashvili88@gmail.com4680eb612be3f4a9d2a55b5065f738bd';
$baseUrl = 'https://api.car2db.com/api/auto/v1/';

function fetchCSV($url) {
    $data = file_get_contents($url);
    if ($data === false) {
        throw new Exception("Failed to fetch data from $url");
    }
    return $data;
}

function parseCSV($csvData) {
    $lines = explode("\n", trim($csvData));
    if (empty($lines)) return [];
    
    $header = str_getcsv(array_shift($lines));
    echo "CSV Headers: " . json_encode($header) . "\n";
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line);
            if (count($row) === count($header)) {
                $rows[] = array_combine($header, $row);
            }
        }
    }
    return $rows;
}

try {
    // Create tables if not exist
    echo "Creating tables if not exist...\n";
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vehicle_types (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vehicle_makes (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            type_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (type_id) REFERENCES vehicle_types(id),
            INDEX (name)
        );
    ");
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS vehicle_models (
            id INT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            make_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (make_id) REFERENCES vehicle_makes(id),
            INDEX (name)
        );
    ");
    echo "Tables created.\n";

    // Fetch and insert vehicle types
    echo "Fetching vehicle types...\n";
    $typesUrl = $baseUrl . 'type.getAll.csv.en?api_key=' . $apiKey;
    $typesCSV = fetchCSV($typesUrl);
    echo "Types CSV length: " . strlen($typesCSV) . "\n";
    echo "First 200 chars of types CSV:\n" . substr($typesCSV, 0, 200) . "\n\n";
    $types = parseCSV($typesCSV);
    echo "Parsed types: " . count($types) . " records\n";
    if (!empty($types)) {
        echo "First type: " . json_encode($types[0]) . "\n";
    }

    foreach ($types as $type) {
        $typeId = $type['id'] ?? $type['ID'] ?? $type['type_id'] ?? null;
        $typeName = $type['name'] ?? $type['Name'] ?? $type['NAME'] ?? $type['type_name'] ?? null;
        
        if ($typeId && $typeName) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_types (id, name) VALUES (?, ?)");
            $stmt->execute([$typeId, $typeName]);
        } else {
            echo "Skipping type - missing id or name: " . json_encode($type) . "\n";
        }
    }
    echo "Inserted " . count($types) . " vehicle types.\n";

    // Fetch and insert makes for each type
    echo "Fetching vehicle makes...\n";
    $makesCount = 0;
    foreach ($types as $type) {
        $typeId = $type['id'] ?? null;
        $typeName = $type['name'] ?? 'Unknown';
        
        if (!$typeId) {
            echo "Skipping type '$typeName' - no ID found\n";
            continue;
        }
        
        echo "Processing type ID: $typeId, Name: $typeName\n";
        $makesUrl = $baseUrl . 'make.getAll.csv.en?api_key=' . $apiKey . '&id_type=' . $typeId;
        echo "Makes URL: $makesUrl\n";
        
        try {
            $makesCSV = fetchCSV($makesUrl);
            if (empty($makesCSV) || strlen($makesCSV) < 10) {
                echo "Empty or invalid response for type $typeId\n";
                continue;
            }
            echo "Makes CSV length: " . strlen($makesCSV) . "\n";
            if (strlen($makesCSV) > 0) {
                echo "First 200 chars of makes CSV:\n" . substr($makesCSV, 0, 200) . "\n\n";
            }
            $makes = parseCSV($makesCSV);
            echo "Parsed makes for type $typeId: " . count($makes) . " records\n";
            if (!empty($makes)) {
                echo "First make: " . json_encode($makes[0]) . "\n";
            }

            foreach ($makes as $make) {
                $makeId = $make['id'] ?? $make['ID'] ?? null;
                $makeName = $make['name'] ?? $make['Name'] ?? $make['NAME'] ?? null;
                
                if ($makeId && $makeName) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_makes (id, name, type_id) VALUES (?, ?, ?)");
                    $stmt->execute([$makeId, $makeName, $typeId]);
                    $makesCount++;
                } else {
                    echo "Skipping make - missing id or name: " . json_encode($make) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Error fetching makes for type $typeId: " . $e->getMessage() . "\n";
            continue;
        }
    }
    echo "Inserted $makesCount vehicle makes.\n";

    // Fetch and insert models for each type
    echo "Fetching vehicle models...\n";
    $modelsCount = 0;
    foreach ($types as $type) {
        $typeId = $type['id'] ?? $type['ID'] ?? $type['type_id'] ?? null;
        
        if (!$typeId) continue;
        
        echo "Fetching models for type $typeId...\n";
        $modelsUrl = $baseUrl . 'model.getAll.csv.en?api_key=' . $apiKey . '&id_type=' . $typeId;
        
        try {
            $modelsCSV = fetchCSV($modelsUrl);
            if (empty($modelsCSV) || strlen($modelsCSV) < 10) {
                echo "Empty response for models of type $typeId\n";
                continue;
            }
            $models = parseCSV($modelsCSV);
            echo "Parsed models for type $typeId: " . count($models) . " records\n";

            foreach ($models as $model) {
                $modelId = $model['id'] ?? $model['ID'] ?? null;
                $modelName = $model['name'] ?? $model['Name'] ?? $model['NAME'] ?? null;
                $makeId = $model['make_id'] ?? $model['makeId'] ?? $model['make'] ?? null;
                
                if ($modelId && $modelName && $makeId) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_models (id, name, make_id) VALUES (?, ?, ?)");
                    $stmt->execute([$modelId, $modelName, $makeId]);
                    $modelsCount++;
                } else {
                    echo "Skipping model - missing data: " . json_encode($model) . "\n";
                }
            }
        } catch (Exception $e) {
            echo "Error fetching models for type $typeId: " . $e->getMessage() . "\n";
        }
    }
    echo "Inserted $modelsCount vehicle models.\n";

    echo "Vehicle database populated successfully!\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>