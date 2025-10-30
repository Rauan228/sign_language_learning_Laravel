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

$controller = new CareerTestController();
$reflection = new ReflectionClass($controller);

$disabilityInfo = ['type' => 'hearing', 'severity' => 'moderate'];

// Получаем analyzeAnswersStatistics
$analyzeMethod = $reflection->getMethod('analyzeAnswersStatistics');
$analyzeMethod->setAccessible(true);
$categoryStats = $analyzeMethod->invoke($controller, $testAnswers, $disabilityInfo);

echo "=== СТРУКТУРА categoryStats ===\n";
echo "Тип: " . gettype($categoryStats) . "\n";
echo "Ключи: " . implode(', ', array_keys($categoryStats)) . "\n\n";

foreach ($categoryStats as $key => $value) {
    echo "Ключ: '$key'\n";
    echo "Тип значения: " . gettype($value) . "\n";
    if (is_array($value)) {
        echo "Подключи массива: " . implode(', ', array_keys($value)) . "\n";
        if ($key === 'category_stats') {
            echo "Детали category_stats:\n";
            foreach ($value as $catKey => $catValue) {
                echo "  Категория '$catKey':\n";
                if (is_array($catValue)) {
                    echo "    Ключи: " . implode(', ', array_keys($catValue)) . "\n";
                    if (isset($catValue['percentage'])) {
                        echo "    Percentage: " . $catValue['percentage'] . "\n";
                    }
                } else {
                    echo "    Значение: " . $catValue . "\n";
                }
            }
        }
        if (isset($value['percentage'])) {
            echo "Percentage: " . $value['percentage'] . "\n";
        }
    } else {
        echo "Значение: " . $value . "\n";
    }
    echo "---\n";
}

// Теперь проверим calculateCareerMatches
echo "\n=== ТЕСТ calculateCareerMatches ===\n";
$calculateMethod = $reflection->getMethod('calculateCareerMatches');
$calculateMethod->setAccessible(true);

try {
    // Передаем правильную структуру данных
    $careerMatches = $calculateMethod->invoke($controller, $categoryStats['category_stats'], $disabilityInfo);
    echo "Результат calculateCareerMatches:\n";
    foreach ($careerMatches as $career) {
        echo "- {$career['title']}: {$career['match_percentage']}%\n";
    }
} catch (Exception $e) {
    echo "ОШИБКА в calculateCareerMatches: " . $e->getMessage() . "\n";
    echo "Файл: " . $e->getFile() . "\n";
    echo "Строка: " . $e->getLine() . "\n";
}