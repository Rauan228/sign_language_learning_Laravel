<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$answers = [
    59 => 3,  // Order 21 - Интересы
    60 => 4,  // Order 22 - Интересы  
    63 => 2,  // Order 25 - Навыки
    64 => 3,  // Order 26 - Навыки
    65 => 4   // Order 27 - Навыки
];

$disabilityInfo = ['type' => 'hearing', 'severity' => 'moderate'];

$controller = new App\Http\Controllers\CareerTestController();
$reflection = new ReflectionClass($controller);

// Получаем статистику
$analyzeMethod = $reflection->getMethod('analyzeAnswersStatistics');
$analyzeMethod->setAccessible(true);
$statistics = $analyzeMethod->invoke($controller, $answers, $disabilityInfo);

echo "Category Stats:\n";
foreach ($statistics['category_stats'] as $key => $stats) {
    echo "- $key: {$stats['percentage']}%\n";
}

// Тестируем calculateCareerMatches
$careerMethod = $reflection->getMethod('calculateCareerMatches');
$careerMethod->setAccessible(true);
$careerMatches = $careerMethod->invoke($controller, $statistics['category_stats'], $disabilityInfo);

echo "\nCareer Matches:\n";
foreach ($careerMatches as $career) {
    echo "- {$career['title']}: {$career['match_percentage']}%\n";
    if (isset($career['reasoning'])) {
        echo "  Reasoning: " . substr($career['reasoning'], 0, 100) . "...\n";
    }
}