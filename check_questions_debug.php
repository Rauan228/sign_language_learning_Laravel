<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CareerQuestion;

echo "Checking questions in database:\n";

$questions = CareerQuestion::orderBy('order')->take(10)->get(['id', 'order', 'question_text']);

foreach ($questions as $q) {
    echo "ID: {$q->id}, Order: {$q->order}, Question: " . substr($q->question_text, 0, 50) . "...\n";
}

echo "\nTotal questions: " . CareerQuestion::count() . "\n";

// Проверим конкретные ID 1-5
echo "\nChecking specific IDs 1-5:\n";
for ($i = 1; $i <= 5; $i++) {
    $question = CareerQuestion::find($i);
    if ($question) {
        echo "ID $i: Order {$question->order}, Text: " . substr($question->question_text, 0, 30) . "...\n";
    } else {
        echo "ID $i: NOT FOUND\n";
    }
}