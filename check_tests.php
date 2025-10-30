<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\CareerTest;

echo "=== ПРОВЕРКА ТЕСТОВ В БАЗЕ ДАННЫХ ===\n";

try {
    $tests = CareerTest::where('is_active', true)->get();
    
    echo "Количество активных тестов: " . $tests->count() . "\n\n";
    
    if ($tests->count() > 0) {
        foreach ($tests as $test) {
            echo "ID: " . $test->id . "\n";
            echo "Название: " . $test->title . "\n";
            echo "Описание: " . $test->description . "\n";
            echo "Количество вопросов: " . $test->questions_count . "\n";
            echo "Время: " . $test->time_limit . " минут\n";
            echo "Статус: " . ($test->is_active ? 'Активен' : 'Неактивен') . "\n";
            echo "Создан: " . $test->created_at . "\n";
            echo "---\n";
        }
    } else {
        echo "Активных тестов не найдено!\n";
        
        // Проверим все тесты
        $allTests = CareerTest::all();
        echo "Всего тестов в базе: " . $allTests->count() . "\n";
        
        if ($allTests->count() > 0) {
            echo "\nВсе тесты:\n";
            foreach ($allTests as $test) {
                echo "ID: " . $test->id . ", Название: " . $test->title . ", Активен: " . ($test->is_active ? 'Да' : 'Нет') . "\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage() . "\n";
}