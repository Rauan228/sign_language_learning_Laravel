<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerTestResult;

// Получаем последний результат
$result = CareerTestResult::latest()->first();
if ($result) {
    // Очищаем ai_analysis чтобы система пересчитала
    $result->ai_analysis = null;
    $result->save();
    echo 'Очищен анализ для результата ID: ' . $result->id . PHP_EOL;
} else {
    echo 'Результаты не найдены' . PHP_EOL;
}