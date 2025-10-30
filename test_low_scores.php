<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Тестовые ответы с низкими оценками для проверки рекомендаций
$answers = [
    // Интересы (низкие оценки)
    59 => 1, 60 => 0, 61 => 1, 62 => 0, 63 => 1, 64 => 0, 65 => 1,
    // Навыки (средние оценки)
    66 => 2, 67 => 2, 68 => 2, 69 => 2, 70 => 2, 71 => 2, 72 => 2,
    // Ценности (низкие оценки)
    73 => 0, 74 => 1, 75 => 0, 76 => 1, 77 => 0, 78 => 1, 79 => 0,
    // Рабочая среда (средние оценки)
    80 => 2, 81 => 2, 82 => 2, 83 => 2, 84 => 2, 85 => 2, 86 => 2,
    // Личностные качества (низкие оценки)
    87 => 1, 88 => 0, 89 => 1, 90 => 0, 91 => 1, 92 => 0
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
    
    echo "AI Analysis Result (Low Scores):\n";
    echo "================================\n\n";
    
    echo "Full result keys: " . implode(', ', array_keys($result)) . "\n\n";
    
    echo "Suitable Careers (" . count($result['suitable_careers']) . "):\n";
    foreach ($result['suitable_careers'] as $i => $career) {
        echo "  " . ($i + 1) . ". {$career['title']}: {$career['match_percentage']}%\n";
    }
    echo "\n";
    
    echo "Personality traits: " . (empty($result['personality_traits']) ? 'EMPTY' : 'OK (' . count($result['personality_traits']) . ' items)') . "\n";
    if (!empty($result['personality_traits'])) {
        foreach ($result['personality_traits'] as $trait) {
            echo "  - $trait\n";
        }
    }
    echo "\n";
    
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
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}