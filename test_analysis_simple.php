// Удаляем старый результат
\App\Models\CareerTestResult::where('id', '>=', 3)->delete();

// Создаем новый результат с тестовыми данными
$testResult = \App\Models\CareerTestResult::create([
    'user_id' => 1,
    'career_test_id' => 6,
    'answers' => json_encode([
        ['question_id' => 1, 'answer' => 4, 'category' => 'Интересы'],
        ['question_id' => 2, 'answer' => 3, 'category' => 'Интересы'],
        ['question_id' => 3, 'answer' => 4, 'category' => 'Ценности'],
        ['question_id' => 4, 'answer' => 2, 'category' => 'Ценности'],
        ['question_id' => 5, 'answer' => 3, 'category' => 'Личностные качества'],
        ['question_id' => 6, 'answer' => 4, 'category' => 'Личностные качества'],
        ['question_id' => 7, 'answer' => 2, 'category' => 'Обучаемость и мотивация'],
        ['question_id' => 8, 'answer' => 3, 'category' => 'Обучаемость и мотивация'],
    ]),
    'disability_info' => json_encode([
        'has_disability' => true,
        'disability_type' => 'hearing',
        'needs_adaptation' => true,
        'description' => 'Нарушение слуха'
    ]),
    'ai_analysis' => json_encode([]),
    'recommendations' => json_encode([]),
    'completed_at' => now()
]);

echo "Создан новый результат теста с ID: " . $testResult->id . "\n";

// Создаем контроллер и генерируем анализ
$controller = new \App\Http\Controllers\CareerTestController();

// Используем рефлексию для вызова приватного метода
$reflection = new ReflectionClass($controller);
$method = $reflection->getMethod('getAIAnalysis');
$method->setAccessible(true);

try {
    $answers = json_decode($testResult->answers, true);
    $disabilityInfo = json_decode($testResult->disability_info, true);
    
    $analysis = $method->invoke($controller, $testResult, $answers, $disabilityInfo);
    
    // Сохраняем анализ
    $testResult->ai_analysis = $analysis;
    $testResult->save();
    
    echo "Анализ успешно сгенерирован и сохранен!\n\n";
    
    // Выводим структуру анализа
    echo "=== СТРУКТУРА АНАЛИЗА ===\n";
    echo "Ключи верхнего уровня: " . implode(', ', array_keys($analysis)) . "\n\n";
    
    // Проверяем suitable_careers
    if (isset($analysis['suitable_careers'])) {
        echo "=== ПОДХОДЯЩИЕ ПРОФЕССИИ ===\n";
        echo "Количество профессий: " . count($analysis['suitable_careers']) . "\n";
        
        foreach ($analysis['suitable_careers'] as $index => $career) {
            echo "Профессия " . ($index + 1) . ":\n";
            echo "  Название: " . ($career['name'] ?? 'Не указано') . "\n";
            echo "  Соответствие: " . ($career['match_percentage'] ?? 'Не указано') . "%\n";
            echo "  Обоснование: " . (isset($career['reasoning']) ? substr($career['reasoning'], 0, 100) . '...' : 'Не указано') . "\n\n";
        }
    } else {
        echo "ОШИБКА: suitable_careers не найден в анализе!\n";
    }
    
    // Проверяем detailed_report
    if (isset($analysis['detailed_report'])) {
        echo "=== ДЕТАЛЬНЫЙ ОТЧЕТ ===\n";
        echo "Длина отчета: " . strlen($analysis['detailed_report']) . " символов\n";
        echo "Первые 300 символов:\n" . substr($analysis['detailed_report'], 0, 300) . "...\n\n";
    } else {
        echo "ОШИБКА: detailed_report не найден в анализе!\n";
    }
    
    // Проверяем другие разделы
    $sections = ['personality_traits', 'skills_to_develop', 'learning_recommendations', 'accessibility_considerations', 'summary'];
    foreach ($sections as $section) {
        if (isset($analysis[$section])) {
            if (is_array($analysis[$section])) {
                echo "✓ {$section}: " . count($analysis[$section]) . " элементов\n";
            } else {
                echo "✓ {$section}: " . strlen($analysis[$section]) . " символов\n";
            }
        } else {
            echo "✗ {$section}: отсутствует\n";
        }
    }
    
} catch (Exception $e) {
    echo "ОШИБКА при генерации анализа: " . $e->getMessage() . "\n";
    echo "Трассировка:\n" . $e->getTraceAsString() . "\n";
}