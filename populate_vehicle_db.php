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
    // Clean headers - remove surrounding quotes
    $header = array_map(function($h) {
        return trim($h, "'\"");
    }, $header);
    echo "CSV Headers: " . json_encode($header) . "\n";
    $rows = [];
    foreach ($lines as $line) {
        if (trim($line)) {
            $row = str_getcsv($line);
            if (count($row) === count($header)) {
                // Clean row values - remove surrounding quotes
                $row = array_map(function($v) {
                    return trim($v, "'\"");
                }, $row);
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
    $types = parseCSV($typesCSV);
    echo "Parsed " . count($types) . " vehicle types\n";

    foreach ($types as $type) {
        $typeId = $type['id_car_type'] ?? $type['id'] ?? $type['ID'] ?? $type['type_id'] ?? null;
        $typeName = $type['name'] ?? $type['Name'] ?? $type['NAME'] ?? $type['type_name'] ?? null;
        
        if ($typeId && $typeName) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_types (id, name) VALUES (?, ?)");
            $stmt->execute([$typeId, $typeName]);
        } else {
            echo "Skipping type - missing id or name: " . json_encode($type) . "\n";
        }
    }
    echo "Inserted vehicle types successfully.\n";

    // Fetch and insert makes for each type
    echo "Fetching vehicle makes...\n";
    $makesCount = 0;
    foreach ($types as $type) {
        $typeId = $type['id_car_type'] ?? $type['id'] ?? $type['ID'] ?? $type['type_id'] ?? null;
        $typeName = $type['name'] ?? $type['Name'] ?? $type['NAME'] ?? $type['type_name'] ?? 'Unknown';
        
        if (!$typeId) {
            echo "Skipping type '$typeName' - no ID found\n";
            continue;
        }
        
        echo "Processing type $typeId ($typeName)...\n";
        $makesUrl = $baseUrl . 'make.getAll.csv.en?api_key=' . $apiKey . '&id_type=' . $typeId;
        
        try {
            $makesCSV = fetchCSV($makesUrl);
            if (empty($makesCSV) || strlen($makesCSV) < 10) {
                echo "No makes found for type $typeId\n";
                continue;
            }
            $makes = parseCSV($makesCSV);
            echo "Found " . count($makes) . " makes for type $typeId\n";

            foreach ($makes as $make) {
                $makeId = $make['id_car_make'] ?? $make['id'] ?? $make['ID'] ?? null;
                $makeName = $make['name'] ?? $make['Name'] ?? $make['NAME'] ?? null;
                
                if ($makeId && $makeName) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_makes (id, name, type_id) VALUES (?, ?, ?)");
                    $stmt->execute([$makeId, $makeName, $typeId]);
                    $makesCount++;
                }
            }
        } catch (Exception $e) {
            echo "Error fetching makes for type $typeId: " . $e->getMessage() . "\n";
        }
    }
    echo "Inserted $makesCount vehicle makes.\n";

    // Fetch and insert models for each type
    echo "Fetching vehicle models...\n";
    $modelsCount = 0;
    foreach ($types as $type) {
        $typeId = $type['id_car_type'] ?? $type['id'] ?? $type['ID'] ?? $type['type_id'] ?? null;
        
        if (!$typeId) continue;
        
        echo "Fetching models for type $typeId...\n";
        $modelsUrl = $baseUrl . 'model.getAll.csv.en?api_key=' . $apiKey . '&id_type=' . $typeId;
        
        try {
            $modelsCSV = fetchCSV($modelsUrl);
            if (empty($modelsCSV) || strlen($modelsCSV) < 10) {
                echo "No models found for type $typeId\n";
                continue;
            }
            $models = parseCSV($modelsCSV);
            echo "Found " . count($models) . " models for type $typeId\n";

            foreach ($models as $model) {
                $modelId = $model['id_car_model'] ?? $model['id'] ?? $model['ID'] ?? null;
                $modelName = $model['name'] ?? $model['Name'] ?? $model['NAME'] ?? null;
                $makeId = $model['id_car_make'] ?? $model['make_id'] ?? $model['makeId'] ?? $model['make'] ?? null;
                
                if ($modelId && $modelName && $makeId) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO vehicle_models (id, name, make_id) VALUES (?, ?, ?)");
                    $stmt->execute([$modelId, $modelName, $makeId]);
                    $modelsCount++;
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