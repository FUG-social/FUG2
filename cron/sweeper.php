<?php
// cron/sweeper.php - The 5-Minute Radar Janitor

// Ensure this script can run indefinitely if there's a lot of data
set_time_limit(0);
ini_set('memory_limit', '256M');

define('RADAR_DIR', __DIR__ . '/../radar/');

// 1. Lock Mechanism to prevent Crons from overlapping
$lockFile = RADAR_DIR . 'sweeper.lock';
if (file_exists($lockFile)) {
    // If lock is older than 10 mins, assume previous cron crashed and override
    if (time() - filemtime($lockFile) < 600) {
        die("Sweeper is already running. Exiting.\n");
    }
}

// Create radar dir if it somehow doesn't exist
if (!is_dir(RADAR_DIR)) {
    mkdir(RADAR_DIR, 0755, true);
}

file_put_contents($lockFile, time());

$now = time();
$globalStats = [
    'last_updated' => $now,
    'total_active_global' => 0,
    'countries' => [],
    'cities' => []
];

// 2. Scan Countries (e.g., /radar/pk/)
$countries = glob(RADAR_DIR . '*', GLOB_ONLYDIR);
if ($countries) {
    foreach ($countries as $countryDir) {
        $countryCode = basename($countryDir);
        $countryNationalData = [];
        $countryActiveCount = 0;

        // 3. Scan Cities in Country (e.g., /radar/pk/bahawalpur/)
        $cities = glob($countryDir . '/*', GLOB_ONLYDIR);
        if ($cities) {
            foreach ($cities as $cityDir) {
                $cityName = basename($cityDir);
                $activeUsers = [];

                // Read all JSONL shards in this city
                $shards = glob($cityDir . '/*-live.jsonl');
                if ($shards) {
                    foreach ($shards as $shardFile) {
                        $lines = file($shardFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        if (!$lines) continue;

                        foreach ($lines as $line) {
                            $data = json_decode($line, true);
                            // Keep if valid AND pinged within the last 5 minutes (300 seconds)
                            if ($data && ($now - $data['time'] <= 300)) {
                                // Overwrites duplicates; keeps only the absolute latest ping per user ID
                                $activeUsers[$data['id']] = $data;
                            }
                        }
                    }
                }

                $cityCount = count($activeUsers);
                if ($cityCount > 0) {
                    $globalStats['cities']["$countryCode/$cityName"] = $cityCount;
                    $countryActiveCount += $cityCount;

                    // Re-shard the deduplicated users
                    $newShards = [];
                    foreach ($activeUsers as $id => $u) {
                        $shardKey = $id[0] ?? '0';
                        $newShards[$shardKey][] = json_encode($u);
                        
                        // Add to national array (Stripped down version for map zooming)
                        $countryNationalData[] = [
                            'id' => $u['id'],
                            'lat' => $u['lat'],
                            'lng' => $u['lng'],
                            'city' => $cityName
                        ];
                    }

                    // Write to temp files, then Atomic Swap (Zero downtime for users reading the radar)
                    foreach ($newShards as $shardKey => $lines) {
                        $tempFile = "$cityDir/temp-$shardKey.jsonl";
                        $liveFile = "$cityDir/$shardKey-live.jsonl";
                        file_put_contents($tempFile, implode("\n", $lines) . "\n");
                        rename($tempFile, $liveFile); // Instant swap
                    }

                    // Cleanup dead shards (if a shard letter is no longer active)
                    $existingShards = glob("$cityDir/*-live.jsonl");
                    foreach ($existingShards as $es) {
                        $sKey = str_replace('-live.jsonl', '', basename($es));
                        if (!isset($newShards[$sKey])) {
                            unlink($es); 
                        }
                    }
                } else {
                    // City is completely empty now, clean up all files
                    $existingShards = glob("$cityDir/*-live.jsonl");
                    if ($existingShards) {
                        foreach ($existingShards as $es) unlink($es);
                    }
                }
            }
        }

        // 4. Write Country National Data File
        if ($countryActiveCount > 0) {
            $globalStats['countries'][$countryCode] = $countryActiveCount;
            $globalStats['total_active_global'] += $countryActiveCount;
            // Write to /radar/pk/pk-national.json
            file_put_contents("$countryDir/$countryCode-national.json", json_encode($countryNationalData));
        }
    }
}

// 5. Write Global Stats for Dashboard/Admin
file_put_contents(RADAR_DIR . 'global-stats.json', json_encode($globalStats, JSON_PRETTY_PRINT));

// Remove Lock File so next cron can run
if (file_exists($lockFile)) {
    unlink($lockFile);
}

echo "Sweeper completed successfully. Global Active Users: {$globalStats['total_active_global']}\n";
?>