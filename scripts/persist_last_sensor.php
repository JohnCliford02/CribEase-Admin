<?php
// Copy /devices/{deviceId}/sensor -> /devices/{deviceId}/lastSensor
// Run this periodically (cron / Windows Task Scheduler) or manually.

$databaseUrl = 'https://esp32-connecttest-default-rtdb.asia-southeast1.firebasedatabase.app';

function fetchJson($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 20);
    $resp = curl_exec($ch);
    $err = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($resp === false) {
        throw new Exception('Request failed: ' . $err);
    }
    if ($code < 200 || $code >= 300) {
        throw new Exception('HTTP error: ' . $code . ' response: ' . $resp);
    }
    return json_decode($resp, true);
}

function patchJson($url, $payload) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PATCH");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [ 'Content-Type: application/json' ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['resp' => $resp, 'code' => $code, 'err' => $err];
}

try {
    echo "Fetching devices...\n";
    $devices = fetchJson(rtrim($databaseUrl, '/') . '/devices.json');

    if (!is_array($devices) || count($devices) === 0) {
        echo "No devices found.\n";
        exit(0);
    }

    foreach ($devices as $deviceId => $device) {
        if (!is_array($device)) continue;
        if (isset($device['sensor']) && is_array($device['sensor']) && count($device['sensor']) > 0) {
            $payload = json_encode($device['sensor']);
            $path = '/devices/' . rawurlencode($deviceId) . '/lastSensor.json';
            $url = rtrim($databaseUrl, '/') . $path;

            $res = patchJson($url, $payload);
            if ($res['code'] >= 200 && $res['code'] < 300) {
                echo "Updated lastSensor for {$deviceId}\n";
            } else {
                echo "Failed to update lastSensor for {$deviceId}: HTTP {$res['code']} err={$res['err']} resp={$res['resp']}\n";
            }
        }
    }

    echo "Done.\n";
} catch (Exception $e) {
    echo 'Error: ' . $e->getMessage() . "\n";
    exit(1);
}
