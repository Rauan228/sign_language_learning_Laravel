<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;

$result = CareerTestResult::find(3);

if ($result) {
    $analysis = $result->ai_analysis;
    if (is_string($analysis)) {
        $analysis = json_decode($analysis, true);
    }
    
    echo "=== СТРУКТУРА АНАЛИЗА ===" . PHP_EOL;
    echo "Ключи: " . implode(', ', array_keys($analysis)) . PHP_EOL . PHP_EOL;
    
    if (isset($analysis['suitable_careers'])) {
        echo "=== ПОДХОДЯЩИЕ ПРОФЕССИИ ===" . PHP_EOL;
        echo "Тип: " . gettype($analysis['suitable_careers']) . PHP_EOL;
        echo "Количество: " . (is_array($analysis['suitable_careers']) ? count($analysis['suitable_careers']) : 'не массив') . PHP_EOL;
        
        if (is_array($analysis['suitable_careers']) && !empty($analysis['suitable_careers'])) {
            foreach ($analysis['suitable_careers'] as $index => $career) {
                echo "Профессия $index:" . PHP_EOL;
                if (is_array($career)) {
                    foreach ($career as $key => $value) {
                        echo "  $key: " . (is_string($value) ? $value : json_encode($value)) . PHP_EOL;
                    }
                } else {
                    echo "  " . $career . PHP_EOL;
                }
                echo "---" . PHP_EOL;
            }
        } else {
            echo "Профессии не найдены или пустой массив" . PHP_EOL;
        }
    }
    
    if (isset($analysis['detailed_report'])) {
        echo PHP_EOL . "=== ДЕТАЛЬНЫЙ ОТЧЕТ ===" . PHP_EOL;
        echo substr($analysis['detailed_report'], 0, 500) . "..." . PHP_EOL;
    }
    
} else {
    echo "Результат не найден" . PHP_EOL;
}