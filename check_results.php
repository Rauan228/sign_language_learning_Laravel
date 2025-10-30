<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;

echo "Results count: " . CareerTestResult::count() . PHP_EOL;

if (CareerTestResult::count() > 0) {
    $result = CareerTestResult::latest()->first();
    echo "Latest result ID: " . $result->id . PHP_EOL;
    echo "AI Analysis exists: " . (empty($result->ai_analysis) ? 'No' : 'Yes') . PHP_EOL;
    
    if (!empty($result->ai_analysis)) {
        $analysis = $result->ai_analysis;
        if (is_string($analysis)) {
            $analysis = json_decode($analysis, true);
        }
        
        echo "Analysis keys: " . implode(', ', array_keys($analysis)) . PHP_EOL;
        
        if (isset($analysis['detailed_report'])) {
            echo "Detailed report exists: Yes" . PHP_EOL;
        } else {
            echo "Detailed report exists: No" . PHP_EOL;
        }
    }
} else {
    echo "No results found" . PHP_EOL;
}