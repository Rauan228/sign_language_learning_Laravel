<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Тестовые ответы с правильными ID вопросов из базы
$answers = [
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

// Создаем тестовый объект теста
$testData = (object)[
    'id' => 6,
    'title' => 'Тест профориентации',
    'description' => 'Тестовое описание'
];

$disabilityInfo = ['type' => 'hearing', 'severity' => 'moderate'];

// Создаем контроллер и вызываем getAIAnalysis
$controller = new \App\Http\Controllers\CareerTestController();
$reflection = new ReflectionClass($controller);

$method = $reflection->getMethod('getAIAnalysis');
$method->setAccessible(true);

try {
    $result = $method->invoke($controller, $testData, $answers, $disabilityInfo);
    
    echo "AI Analysis Result:\n";
    echo "==================\n\n";
    
    echo "Full result keys: " . implode(', ', array_keys($result)) . "\n\n";
    
    // Если результат имеет структуру fallback
    if (isset($result['analysis'])) {
        echo "Using fallback structure\n";
        $analysis = $result['analysis'];
        
        // Проверяем suitable_careers
        if (isset($analysis['suitable_careers'])) {
            echo "Suitable Careers (" . count($analysis['suitable_careers']) . "):\n";
            foreach ($analysis['suitable_careers'] as $i => $career) {
                echo "  " . ($i + 1) . ". {$career['title']}: {$career['match_percentage']}%\n";
                if (isset($career['requirements'])) {
                    echo "     Requirements type: " . gettype($career['requirements']) . "\n";
                    if (is_array($career['requirements']) || is_object($career['requirements'])) {
                        echo "     Requirements keys: " . implode(', ', array_keys((array)$career['requirements'])) . "\n";
                    }
                }
            }
        } else {
            echo "ERROR: suitable_careers not found in analysis!\n";
        }
        
        echo "\n";
        
        // Проверяем другие разделы
        $sections = ['personality_traits', 'skills_to_develop', 'learning_recommendations'];
        foreach ($sections as $section) {
            if (isset($analysis[$section])) {
                $value = $analysis[$section];
                echo ucfirst(str_replace('_', ' ', $section)) . ": ";
                if (empty($value)) {
                    echo "EMPTY\n";
                } else {
                    echo "OK (" . count($value) . " items)\n";
                    foreach ($value as $item) {
                        echo "  - $item\n";
                    }
                }
            } else {
                echo ucfirst(str_replace('_', ' ', $section)) . ": NOT FOUND\n";
            }
        }
    } else {
        // Проверяем suitable_careers
        if (isset($result['suitable_careers'])) {
            echo "Suitable Careers (" . count($result['suitable_careers']) . "):\n";
            foreach ($result['suitable_careers'] as $i => $career) {
                echo "  " . ($i + 1) . ". {$career['title']}: {$career['match_percentage']}%\n";
                if (isset($career['requirements'])) {
                    echo "     Requirements type: " . gettype($career['requirements']) . "\n";
                    if (is_array($career['requirements']) || is_object($career['requirements'])) {
                        echo "     Requirements keys: " . implode(', ', array_keys((array)$career['requirements'])) . "\n";
                    }
                }
            }
        } else {
            echo "ERROR: suitable_careers not found!\n";
        }
        
        echo "\n";
        
        // Проверяем другие разделы
        $sections = ['personality_traits', 'skills_to_develop', 'learning_recommendations'];
        foreach ($sections as $section) {
            if (isset($result[$section])) {
                $value = $result[$section];
                echo ucfirst(str_replace('_', ' ', $section)) . ": ";
                if (empty($value)) {
                    echo "EMPTY\n";
                } else {
                    echo "OK (" . count($value) . " items)\n";
                    foreach ($value as $item) {
                        echo "  - $item\n";
                    }
                }
            } else {
                echo ucfirst(str_replace('_', ' ', $section)) . ": NOT FOUND\n";
            }
        }
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}