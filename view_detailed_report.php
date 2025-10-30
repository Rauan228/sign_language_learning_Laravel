<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;

$result = CareerTestResult::find(2);

if ($result) {
    $analysis = $result->ai_analysis;
    if (is_string($analysis)) {
        $analysis = json_decode($analysis, true);
    }
    
    echo "=== ДЕТАЛЬНЫЙ ОТЧЕТ ===" . PHP_EOL . PHP_EOL;
    
    if (isset($analysis['detailed_report'])) {
        echo $analysis['detailed_report'] . PHP_EOL . PHP_EOL;
    }
    
    echo "=== ПОДХОДЯЩИЕ ПРОФЕССИИ ===" . PHP_EOL . PHP_EOL;
    
    if (isset($analysis['suitable_careers']) && is_array($analysis['suitable_careers'])) {
        foreach ($analysis['suitable_careers'] as $career) {
            if (is_array($career)) {
                echo "Профессия: " . ($career['name'] ?? 'Не указано') . PHP_EOL;
                echo "Соответствие: " . ($career['match_percentage'] ?? 'Не указано') . "%" . PHP_EOL;
                if (isset($career['reasoning'])) {
                    echo "Обоснование: " . $career['reasoning'] . PHP_EOL;
                }
                echo "---" . PHP_EOL;
            } else {
                echo $career . PHP_EOL;
            }
        }
    }
    
} else {
    echo "Результат не найден" . PHP_EOL;
}