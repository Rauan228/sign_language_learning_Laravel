<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Http\Controllers\CareerTestController;

// Тестовые ответы с правильными ID вопросов из базы
$testAnswers = [
    // Интересы (order 21-24) - ID 59-62
    59 => 4, 60 => 5, 61 => 3, 62 => 4,
    // Навыки (order 25-36) - ID 63-74  
    63 => 3, 64 => 4, 65 => 5, 66 => 3, 67 => 4, 68 => 5, 69 => 3, 70 => 4, 71 => 5, 72 => 3, 73 => 4, 74 => 5,
    // Ценности (order 37-45) - ID 75-83
    75 => 4, 76 => 3, 77 => 5, 78 => 4, 79 => 3, 80 => 5, 81 => 4, 82 => 3, 83 => 5,
    // Рабочая среда (order 46-52) - ID 84-90
    84 => 3, 85 => 4, 86 => 5, 87 => 3, 88 => 4, 89 => 5, 90 => 3,
    // Личностные качества (order 53-54) - ID 91-92
    91 => 4, 92 => 5
];

$disabilityInfo = [
    'has_disability' => true,
    'disability_type' => 'mobility',
    'needs_accommodation' => true
];

echo "=== ТЕСТ ПОЛНОГО АНАЛИЗА С ВСЕМИ КАТЕГОРИЯМИ ===\n\n";

try {
    $controller = new CareerTestController();
    
    // Используем рефлексию для вызова приватного метода
    $reflection = new ReflectionClass($controller);
    $method = $reflection->getMethod('analyzeAnswersStatistics');
    $method->setAccessible(true);
    
    $result = $method->invoke($controller, $testAnswers, $disabilityInfo);
    
    echo "Отладочная информация:\n";
    echo "Количество тестовых ответов: " . count($testAnswers) . "\n";
    echo "Ключи ответов: " . implode(', ', array_keys($testAnswers)) . "\n\n";
    
    echo "Статистика по категориям:\n";
    if (empty($result['category_stats'])) {
        echo "КАТЕГОРИИ ПУСТЫЕ!\n";
        
        // Проверим, какие вопросы найдены в базе
        echo "\nПроверка вопросов в базе:\n";
        foreach ($testAnswers as $questionId => $answer) {
            $question = \App\Models\CareerQuestion::find($questionId);
            if ($question) {
                echo "Вопрос {$questionId}: найден, order = {$question->order}\n";
            } else {
                echo "Вопрос {$questionId}: НЕ НАЙДЕН\n";
            }
        }
    } else {
        foreach ($result['category_stats'] as $category => $stats) {
            echo "- {$category}: {$stats['percentage']}% (уровень: {$stats['level']})\n";
        }
    }
    
    echo "\nОбщий результат: {$result['overall_percentage']}%\n";
    
    echo "\nПрофессиональные склонности:\n";
    foreach ($result['professional_inclinations'] as $inclination) {
        echo "- {$inclination}\n";
    }
    
    echo "\nСоответствие профессиям:\n";
    foreach ($result['career_matches'] as $career) {
        echo "- {$career['title']}: {$career['match_percentage']}%\n";
        if (isset($career['detailed_analysis'])) {
            foreach ($career['detailed_analysis'] as $analysis) {
                echo "  • {$analysis['category']}: {$analysis['user_score']}% (требуется {$analysis['required']}%)\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ОШИБКА: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
}