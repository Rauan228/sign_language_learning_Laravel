<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerQuestion;

echo "=== ПРОВЕРКА ВОПРОСОВ В БАЗЕ ===\n\n";

$questions = CareerQuestion::select('id', 'order')->orderBy('order')->get();

echo "Всего вопросов: " . $questions->count() . "\n\n";

echo "ID => Order:\n";
foreach ($questions as $question) {
    echo "ID: {$question->id} => Order: {$question->order}\n";
}