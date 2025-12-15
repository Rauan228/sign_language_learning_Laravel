<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;

$result = CareerTestResult::find(2);
if ($result && $result->ai_analysis) {
    $analysisData = $result->ai_analysis;
    if (!is_string($analysisData)) {
        $analysisData = json_encode($analysisData);
    }
    $analysis = is_array($result->ai_analysis) ? $result->ai_analysis : json_decode($analysisData, true);
    if (isset($analysis['analysis']['detailed_report'])) {
        echo 'ДЕТАЛЬНЫЙ ОТЧЕТ:' . PHP_EOL;
        echo $analysis['analysis']['detailed_report'] . PHP_EOL;
        echo PHP_EOL . '=== ПРОФЕССИИ ===' . PHP_EOL;
        if (isset($analysis['analysis']['suitable_careers'])) {
            foreach ($analysis['analysis']['suitable_careers'] as $career) {
                echo "• {$career['title']}: {$career['match_percentage']}%" . PHP_EOL;
                if (isset($career['reasoning'])) {
                    echo "  Обоснование: {$career['reasoning']}" . PHP_EOL;
                }
            }
        }
    } else {
        echo 'detailed_report не найден' . PHP_EOL;
        echo 'Доступные ключи: ' . implode(', ', array_keys($analysis['analysis'] ?? [])) . PHP_EOL;
    }
} else {
    echo 'Результат не найден или анализ пуст' . PHP_EOL;
}