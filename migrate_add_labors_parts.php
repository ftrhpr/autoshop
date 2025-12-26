<?php
require 'config.php';

// Add labors and parts tables
try {

    // Insert default labor operations
    $labors = [
        ['name' => 'brake pad replacement', 'description' => 'Replace brake pads', 'default_price' => 150.00],
        ['name' => 'brake disc replacement', 'description' => 'Replace brake discs', 'default_price' => 300.00],
        ['name' => 'brake rotor replacement', 'description' => 'Replace brake rotors', 'default_price' => 250.00],
        ['name' => 'brake caliper replacement', 'description' => 'Replace brake caliper', 'default_price' => 200.00],
        ['name' => 'brake drum replacement', 'description' => 'Replace brake drum', 'default_price' => 180.00],
        ['name' => 'brake shoe replacement', 'description' => 'Replace brake shoes', 'default_price' => 120.00],
        ['name' => 'oil change', 'description' => 'Engine oil change service', 'default_price' => 80.00],
        ['name' => 'air filter replacement', 'description' => 'Replace air filter', 'default_price' => 50.00],
        ['name' => 'fuel filter replacement', 'description' => 'Replace fuel filter', 'default_price' => 100.00],
        ['name' => 'cabin air filter replacement', 'description' => 'Replace cabin air filter', 'default_price' => 40.00],
        ['name' => 'battery replacement', 'description' => 'Replace car battery', 'default_price' => 120.00],
        ['name' => 'alternator replacement', 'description' => 'Replace alternator', 'default_price' => 400.00],
        ['name' => 'starter replacement', 'description' => 'Replace starter motor', 'default_price' => 250.00],
        ['name' => 'spark plug replacement', 'description' => 'Replace spark plugs', 'default_price' => 90.00],
        ['name' => 'tire replacement', 'description' => 'Replace tire', 'default_price' => 100.00],
        ['name' => 'shock absorber replacement', 'description' => 'Replace shock absorber', 'default_price' => 180.00],
        ['name' => 'strut replacement', 'description' => 'Replace strut', 'default_price' => 220.00],
        ['name' => 'timing belt replacement', 'description' => 'Replace timing belt', 'default_price' => 500.00],
        ['name' => 'serpentine belt replacement', 'description' => 'Replace serpentine belt', 'default_price' => 150.00],
        ['name' => 'drive belt replacement', 'description' => 'Replace drive belt', 'default_price' => 120.00],
        ['name' => 'radiator replacement', 'description' => 'Replace radiator', 'default_price' => 350.00],
        ['name' => 'water pump replacement', 'description' => 'Replace water pump', 'default_price' => 280.00],
        ['name' => 'thermostat replacement', 'description' => 'Replace thermostat', 'default_price' => 80.00],
        ['name' => 'exhaust pipe replacement', 'description' => 'Replace exhaust pipe', 'default_price' => 200.00],
        ['name' => 'catalytic converter replacement', 'description' => 'Replace catalytic converter', 'default_price' => 600.00],
        ['name' => 'muffler replacement', 'description' => 'Replace muffler', 'default_price' => 180.00],
        ['name' => 'part installation', 'description' => 'General part installation service', 'default_price' => 50.00]
    ];

    $stmt = $pdo->prepare("INSERT INTO labors (name, description, default_price) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description), default_price = VALUES(default_price)");
    $inserted = 0;
    foreach ($labors as $labor) {
        try {
            $stmt->execute([$labor['name'], $labor['description'], $labor['default_price']]);
            $inserted++;
        } catch (Exception $e) {
            echo "Error inserting labor '{$labor['name']}': " . $e->getMessage() . "\n";
        }
    }

    echo " $inserted labor operations processed successfully.\n";

    // Verify the data was inserted
    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM labors");
    $count = $countStmt->fetch()['count'];
    echo "Total labors in database: $count\n";

    // Show a few examples
    $sampleStmt = $pdo->query("SELECT name, default_price FROM labors LIMIT 5");
    $samples = $sampleStmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Sample labors:\n";
    foreach ($samples as $sample) {
        echo "- {$sample['name']}: {$sample['default_price']} ₾\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>