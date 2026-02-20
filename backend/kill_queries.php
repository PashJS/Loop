<?php
// Kill stuck ALTER TABLE queries
try {
    $pdo = new PDO('mysql:host=localhost;dbname=floxwatch', 'root', 'root');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $rows = $pdo->query("SHOW FULL PROCESSLIST")->fetchAll(PDO::FETCH_ASSOC);
    $killed = 0;
    foreach ($rows as $r) {
        if (!empty($r['Info']) && (
            stripos($r['Info'], 'ALTER') !== false ||
            stripos($r['Info'], 'CREATE TABLE') !== false
        )) {
            try {
                $pdo->exec("KILL " . $r['Id']);
                echo "Killed #{$r['Id']}: {$r['Info']}\n";
                $killed++;
            } catch (Exception $e) {
                echo "Failed to kill #{$r['Id']}: {$e->getMessage()}\n";
            }
        }
    }
    echo "Total killed: $killed\n";
    
    // Show remaining
    echo "\n--- Remaining processes ---\n";
    $rows = $pdo->query("SHOW FULL PROCESSLIST")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        echo "#{$r['Id']} {$r['Command']} {$r['Time']}s {$r['Info']}\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
