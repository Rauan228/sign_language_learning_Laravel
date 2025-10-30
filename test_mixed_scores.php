<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Тестовые ответы со смешанными оценками для проверки всех категорий
$answers = [
    // Интересы (низкие оценки - должны попасть в рекомендации)
    59 => 1, 60 => 0, 61 => 1, 62 => 0, 63 => 1, 64 => 0, 65 => 1,
    // Навыки (низкие оценки - должны попасть в рекомендации)
    66 => 0, 67 => 1, 68 => 0, 69 => 1, 70 => 0, 71 => 1, 72 => 0,
    // Ценности (высокие оценки - не должны попасть в рекомендации)
    73 => 4, 74 => 4, 75 => 4, 76 => 4, 77 => 4, 78 => 4, 79 => 4,
    // Рабочая среда (низкие оценки - должны попасть в рекомендации)
    80 => 1, 81 => 0, 82 => 1, 83 => 0, 84 => 1, 85 => 0, 86 => 1,
    // Личностные качества (высокие оценки - не должны попасть в рекомендации)
    87 => 4, 88 => 4, 89 => 4, 90 => 4, 91 => 4, 92 => 4
];

// Создаем тестовый объект теста
$testData = (object)[
    'id' => 6,
    'title' => 'Тест профориентации',
    'description' => 'Тестовое описание'
];

$disabilityInfo = ['type' => 'hearing', 'severity' => 'moderate'];

// Создаем контроллер и вызываем getAIAnalysis
$controller = new \App\Http\Controllers\CareerTestController();
$reflection = new ReflectionClass($controller);

$method = $reflection->getMethod('getAIAnalysis');
$method->setAccessible(true);

try {
    $result = $method->invoke($controller, $testData, $answers, $disabilityInfo);
    
    echo "AI Analysis Result (Mixed Scores):\n";
    echo "==================================\n\n";
    
    echo "Skills to develop: " . (empty($result['skills_to_develop']) ? 'EMPTY' : 'OK (' . count($result['skills_to_develop']) . ' items)') . "\n";
    if (!empty($result['skills_to_develop'])) {
        foreach ($result['skills_to_develop'] as $skill) {
            echo "  - $skill\n";
        }
    }
    echo "\n";
    
    echo "Learning recommendations: " . (empty($result['learning_recommendations']) ? 'EMPTY' : 'OK (' . count($result['learning_recommendations']) . ' items)') . "\n";
    if (!empty($result['learning_recommendations'])) {
        foreach ($result['learning_recommendations'] as $rec) {
            echo "  - $rec\n";
        }
    }
    echo "\n";
    
    // Дополнительно покажем статистику по категориям
    $statsMethod = $reflection->getMethod('analyzeAnswersStatistics');
    $statsMethod->setAccessible(true);
    $stats = $statsMethod->invoke($controller, $answers, $disabilityInfo);
    
    echo "Category Statistics:\n";
    foreach ($stats['category_stats'] as $category => $categoryStats) {
        echo "  - $category: {$categoryStats['percentage']}%\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}