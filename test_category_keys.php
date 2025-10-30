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
$method = $reflection->getMethod('analyzeAnswersStatistics');
$method->setAccessible(true);

$statistics = $method->invoke($controller, $answers, $disabilityInfo);

echo "Category Stats Keys:\n";
foreach ($statistics['category_stats'] as $key => $stats) {
    echo "- $key: {$stats['percentage']}%\n";
}

echo "\nCareer requirements expect:\n";
echo "- Навыки\n";
echo "- Обучаемость и мотивация\n";
echo "- Интересы\n";
echo "- Личностные качества\n";
echo "- Ценности\n";
echo "- Рабочая среда\n";