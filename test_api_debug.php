<?php

require_once 'bootstrap/app.php';

// Get first user for testing
$user = App\Models\User::first();
if (!$user) {
    echo 'No users found in database' . PHP_EOL;
    exit(1);
}

// Create token
$token = $user->createToken('test-token')->plainTextToken;
echo 'Generated token: ' . $token . PHP_EOL;

// Test API call
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api/v1/admin/stats');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $token,
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo 'HTTP Code: ' . $httpCode . PHP_EOL;
echo 'Response: ' . $response . PHP_EOL;

// Parse and display the data structure
if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data && isset($data['data'])) {
        echo PHP_EOL . 'Parsed data structure:' . PHP_EOL;
        foreach ($data['data'] as $key => $value) {
            if (is_array($value)) {
                echo "$key: " . json_encode($value) . PHP_EOL;
            } else {
                echo "$key: $value" . PHP_EOL;
            }
        }
    }
}