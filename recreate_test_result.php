<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;
use App\Models\CareerTest;
use App\Http\Controllers\CareerTestController;

// Удаляем существующий результат
$result = CareerTestResult::find(2);
if ($result) {
    $result->delete();
    echo "Результат удален" . PHP_EOL;
}

// Создаем новый результат с тестовыми данными
$test = CareerTest::first();
if (!$test) {
    echo "Тест не найден" . PHP_EOL;
    exit;
}

// Тестовые ответы (имитируем реальные ответы пользователя)
$testAnswers = [
    1 => 3,  // Интересы
    2 => 4,  // Интересы
    3 => 2,  // Ценности
    4 => 3,  // Ценности
    5 => 4,  // Личностные качества
    6 => 3,  // Личностные качества
    7 => 2,  // Обучаемость и мотивация
    8 => 4,  // Обучаемость и мотивация
    9 => 1,  // Инвалидность и доступность
    10 => 2, // Инвалидность и доступность
];

$disabilityInfo = [
    'has_disability' => true,
    'disability_type' => 'hearing',
    'needs_adaptation' => true,
    'adaptation_details' => 'Нужны визуальные сигналы и текстовая коммуникация'
];

// Создаем новый результат
$newResult = CareerTestResult::create([
    'career_test_id' => $test->id,
    'user_id' => 1, // Тестовый пользователь
    'answers' => json_encode($testAnswers),
    'disability_info' => json_encode($disabilityInfo),
    'ai_analysis' => json_encode([]), // Пустой анализ, будет заполнен контроллером
    'recommendations' => json_encode([])
]);

echo "Новый результат создан с ID: " . $newResult->id . PHP_EOL;

// Теперь вызываем контроллер для генерации анализа
$controller = new CareerTestController();
$reflection = new ReflectionClass($controller);

// Получаем приватный метод getAIAnalysis
$method = $reflection->getMethod('getAIAnalysis');
$method->setAccessible(true);

try {
    $analysis = $method->invoke($controller, $test, $testAnswers, $disabilityInfo);
    
    // Обновляем результат с новым анализом
    $newResult->update([
        'ai_analysis' => json_encode($analysis),
        'recommendations' => json_encode($analysis['suitable_careers'] ?? [])
    ]);
    
    echo "Анализ успешно сгенерирован и сохранен" . PHP_EOL;
    echo "Количество профессий: " . count($analysis['suitable_careers'] ?? []) . PHP_EOL;
    
} catch (Exception $e) {
    echo "Ошибка при генерации анализа: " . $e->getMessage() . PHP_EOL;
}