<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CareerTest;
use App\Models\CareerQuestion;
use App\Models\CareerTestResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CareerTestController extends Controller
{
    /**
     * Получить список доступных тестов
     */
    public function index(): JsonResponse
    {
        $tests = CareerTest::where('is_active', true)
            ->with('questions')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tests
        ]);
    }

    /**
     * Получить конкретный тест с вопросами
     */
    public function show($id): JsonResponse
    {
        $test = CareerTest::with(['questions' => function($query) {
            $query->orderBy('order');
        }])->find($id);

        if (!$test || !$test->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Тест не найден или неактивен'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $test
        ]);
    }

    /**
     * Сохранить результаты теста и получить анализ ИИ
     */
    public function submitTest(Request $request, $id): JsonResponse
    {
        $request->validate([
            'answers' => 'required|array',
            'disability_info' => 'nullable|string',
            'completion_time' => 'required|integer'
        ]);

        $test = CareerTest::find($id);
        if (!$test) {
            return response()->json([
                'success' => false,
                'message' => 'Тест не найден'
            ], 404);
        }

        // Получаем анализ от ИИ
        $aiAnalysis = $this->getAIAnalysis($test, $request->answers, $request->disability_info);
        
        // Очищаем данные от некорректных UTF-8 символов
        $aiAnalysis = $this->cleanUtf8Data($aiAnalysis);

        // Сохраняем результат
        $result = CareerTestResult::create([
            'user_id' => Auth::id(),
            'career_test_id' => $id,
            'answers' => $request->answers,
            'disability_info' => $request->disability_info,
            'ai_analysis' => $aiAnalysis,
            'recommendations' => $aiAnalysis['learning_recommendations'] ?? [],
            'completion_time' => $request->completion_time
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                'result_id' => $result->id,
                'analysis' => $aiAnalysis,
                'recommendations' => $aiAnalysis['learning_recommendations'] ?? []
            ]
        ]);
    }

    /**
     * Получить результаты пользователя
     */
    public function getUserResults(): JsonResponse
    {
        $results = CareerTestResult::where('user_id', Auth::id())
            ->with('careerTest')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Получить конкретный результат
     */
    public function getResult($id): JsonResponse
    {
        $result = CareerTestResult::with('careerTest')
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Результат не найден'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    /**
     * Получить анализ от ИИ
     */
    private function getAIAnalysis($test, $answers, $disabilityInfo = null): array
    {
        try {
            // Используем нашу новую систему детального анализа
            $statistics = $this->analyzeAnswersStatistics($answers, $disabilityInfo);
            $inclinations = $this->determineProfessionalInclinations($statistics['category_stats']);
            $careerMatches = $this->calculateCareerMatches($statistics['category_stats'], $disabilityInfo);
            
            // Формируем детальный анализ
            $analysis = [
                'personality_traits' => $this->generatePersonalityTraits($statistics['category_stats']),
                'suitable_careers' => $careerMatches,
                'skills_to_develop' => $this->generateSkillsToImprove($statistics['category_stats']),
                'learning_recommendations' => $this->generateLearningRecommendations($statistics['category_stats']),
                'accessibility_considerations' => $this->generateAccessibilityConsiderations($disabilityInfo),
                'summary' => $this->generateAnalysisSummary($statistics, $inclinations),
                'detailed_report' => $this->generateDetailedReport($statistics, $inclinations, $careerMatches, $disabilityInfo)
            ];
            
            return $analysis;
            
        } catch (\Exception $e) {
            Log::error('AI Analysis Error: ' . $e->getMessage());
            return $this->getFallbackAnalysis($answers, $disabilityInfo);
        }
    }

    /**
     * Формирование промпта для ИИ с детальной статистикой
     */
    private function buildAIPrompt($test, $answers, $disabilityInfo): string
    {
        // Получаем детальную статистику ответов
        $statistics = $this->analyzeAnswersStatistics($answers, $disabilityInfo);
        
        $prompt = "Проанализируй результаты профориентационного теста для людей с особенностями развития:\n\n";
        $prompt .= "Название теста: {$test->title}\n";
        $prompt .= "Описание: {$test->description}\n\n";

        $prompt .= "ДЕТАЛЬНАЯ СТАТИСТИКА ОТВЕТОВ:\n\n";
        $prompt .= "Общий результат: {$statistics['overall_score']}/{$statistics['total_questions']} вопросов ({$statistics['overall_percentage']}%)\n\n";

        // Выводим статистику по категориям
        foreach ($statistics['category_stats'] as $categoryName => $stats) {
            $prompt .= "=== {$categoryName} ===\n";
            $prompt .= "Результат: {$stats['total_score']}/{$stats['max_score']} баллов ({$stats['percentage']}%)\n";
            $prompt .= "Уровень: {$stats['level']}\n";
            $prompt .= "Средний балл: {$stats['average_score']}/4\n";
            $prompt .= "Количество высоких оценок (3-4): " . count($stats['high_scores']) . "\n";
            $prompt .= "Количество низких оценок (0-1): " . count($stats['low_scores']) . "\n\n";
        }

        $prompt .= "ВЫЯВЛЕННЫЕ ПРОФЕССИОНАЛЬНЫЕ СКЛОННОСТИ:\n";
        foreach ($statistics['professional_inclinations'] as $inclination) {
            $prompt .= "• {$inclination}\n";
        }
        $prompt .= "\n";

        $prompt .= "РАССЧИТАННЫЕ СООТВЕТСТВИЯ ПРОФЕССИЯМ:\n";
        foreach ($statistics['career_matches'] as $career) {
            $prompt .= "• {$career['title']}: {$career['match_percentage']}% соответствия\n";
            $prompt .= "  Описание: {$career['description']}\n";
            foreach ($career['detailed_analysis'] as $analysis) {
                $prompt .= "  - {$analysis['category']}: {$analysis['user_score']}% (требуется {$analysis['required']}%) - {$analysis['analysis']}\n";
            }
            $prompt .= "\n";
        }

        if ($disabilityInfo) {
            $prompt .= "ВАЖНАЯ ИНФОРМАЦИЯ ОБ ОСОБЕННОСТЯХ ЗДОРОВЬЯ:\n{$disabilityInfo}\n";
            $prompt .= "Обязательно учти эту информацию при формировании рекомендаций!\n\n";
        }

        $prompt .= "На основе РЕАЛЬНОЙ СТАТИСТИКИ выше предоставь детальные рекомендации в следующем формате:\n\n";
        $prompt .= "АНАЛИЗ ЛИЧНОСТИ:\n";
        $prompt .= "[Используй конкретные проценты и статистику из анализа выше для описания личности]\n\n";
        
        $prompt .= "ПОДХОДЯЩИЕ ПРОФЕССИИ:\n";
        $prompt .= "[Используй рассчитанные соответствия профессиям выше, добавь детальное обоснование для каждой профессии]\n\n";
        
        $prompt .= "НАВЫКИ ДЛЯ РАЗВИТИЯ:\n";
        $prompt .= "[Основывайся на низких показателях в категориях для определения навыков к развитию]\n\n";
        
        $prompt .= "РЕКОМЕНДАЦИИ ПО ОБУЧЕНИЮ:\n";
        $prompt .= "[Конкретные курсы и программы для улучшения слабых категорий]\n\n";
        
        $prompt .= "ОСОБЕННОСТИ ТРУДОУСТРОЙСТВА:\n";
        $prompt .= "[Учти доступность профессий и особенности здоровья]\n\n";
        
        $prompt .= "ПОДРОБНЫЙ ОТЧЕТ О ПРОЦЕССЕ АНАЛИЗА:\n";
        $prompt .= "[Создай детальный отчет, используя КОНКРЕТНЫЕ ЦИФРЫ И ПРОЦЕНТЫ из статистики выше:\n";
        $prompt .= "1. Анализ каждой категории с указанием точных процентов и их интерпретации\n";
        $prompt .= "2. Объяснение, как конкретные проценты привели к выбору профессий\n";
        $prompt .= "3. Детальное обоснование процента соответствия для каждой рекомендуемой профессии\n";
        $prompt .= "4. Статистическое обоснование рекомендаций по развитию навыков\n";
        $prompt .= "5. Влияние особенностей здоровья на итоговые рекомендации]\n\n";

        return $prompt;
    }

    /**
     * Определение категории по порядку вопроса
     */
    private function getCategoryByOrder($order): string
    {
        if ($order >= 1 && $order <= 24) return 'Интересы';
        if ($order >= 25 && $order <= 36) return 'Навыки';
        if ($order >= 37 && $order <= 45) return 'Ценности';
        if ($order >= 46 && $order <= 52) return 'Рабочая среда';
        if ($order >= 53 && $order <= 58) return 'Личностные качества';
        if ($order >= 59 && $order <= 64) return 'Обучаемость и мотивация';
        if ($order >= 65 && $order <= 76) return 'Инвалидность и доступность';
        return 'Другое';
    }

    /**
     * Парсинг ответа ИИ
     */
    private function parseAIResponse($content): array
    {
        try {
            // Проверяем, является ли контент уже структурированными данными (из нашего нового анализа)
            if (is_array($content)) {
                return $content;
            }
            
            // Если это JSON строка, декодируем
            if (is_string($content) && (strpos($content, '{') === 0 || strpos($content, '[') === 0)) {
                $decoded = json_decode($content, true);
                if ($decoded !== null) {
                    return $decoded;
                }
            }
            
            // Разбиваем контент на секции (старый формат)
            $sections = [
                'personality_analysis' => $this->extractSection($content, 'АНАЛИЗ ЛИЧНОСТИ'),
                'suitable_careers' => $this->extractSection($content, 'ПОДХОДЯЩИЕ ПРОФЕССИИ'),
                'skills_to_develop' => $this->extractSection($content, 'НАВЫКИ ДЛЯ РАЗВИТИЯ'),
                'learning_recommendations' => $this->extractSection($content, 'РЕКОМЕНДАЦИИ ПО ОБУЧЕНИЮ'),
                'employment_features' => $this->extractSection($content, 'ОСОБЕННОСТИ ТРУДОУСТРОЙСТВА'),
                'detailed_report' => $this->extractSection($content, 'ПОДРОБНЫЙ ОТЧЕТ О ПРОЦЕССЕ АНАЛИЗА'),
            ];

            // Извлекаем профессии с процентами соответствия
            $careers = $this->extractCareersWithPercentages($sections['suitable_careers']);
            
            return [
                'personality_traits' => $this->formatPersonalityTraits($sections['personality_analysis']),
                'suitable_careers' => $careers,
                'skills_to_develop' => $this->formatSkillsList($sections['skills_to_develop']),
                'learning_recommendations' => $this->formatLearningRecommendations($sections['learning_recommendations']),
                'accessibility_considerations' => $this->formatAccessibilityConsiderations($sections['employment_features']),
                'summary' => $this->generateSummary($sections),
                'detailed_analysis' => $content, // Сохраняем полный анализ
                'detailed_report' => $sections['detailed_report'] // Подробный отчет о процессе анализа
            ];
        } catch (\Exception $e) {
            Log::error('Ошибка парсинга ответа ИИ: ' . $e->getMessage());
            return $this->getFallbackAnalysis();
        }
    }

    /**
     * Извлечение секции из текста
     */
    private function extractSection($content, $sectionName): string
    {
        $pattern = "/{$sectionName}:\s*(.*?)(?=\n[А-Я\s]+:|$)/s";
        preg_match($pattern, $content, $matches);
        return isset($matches[1]) ? trim($matches[1]) : '';
    }

    /**
     * Извлечение профессий с процентами
     */
    private function extractCareersWithPercentages($careersText): array
    {
        $careers = [];
        
        // Если это уже массив (из нашего нового анализа), возвращаем как есть
        if (is_array($careersText)) {
            return $careersText;
        }
        
        $lines = explode("\n", $careersText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '•') === false) continue;
            
            // Ищем паттерн: • Профессия (XX%) - описание
            if (preg_match('/•\s*([^(]+)\s*\((\d+)%\)\s*-?\s*(.*)/', $line, $matches)) {
                $careers[] = [
                    'name' => trim($matches[1]),
                    'title' => trim($matches[1]),
                    'match_percentage' => (int)$matches[2],
                    'description' => trim($matches[3]),
                    'reasoning' => trim($matches[3]),
                    'requirements' => [],
                    'accessibility_notes' => ''
                ];
            } else {
                // Если нет процента, добавляем как есть
                $careerName = str_replace('•', '', $line);
                $careers[] = [
                    'name' => trim($careerName),
                    'title' => trim($careerName),
                    'match_percentage' => 0,
                    'description' => '',
                    'reasoning' => '',
                    'requirements' => [],
                    'accessibility_notes' => ''
                ];
            }
        }
        
        return $careers;
    }

    /**
     * Форматирование черт личности
     */
    private function formatPersonalityTraits($analysisText): array
    {
        $traits = [];
        $sentences = preg_split('/[.!?]+/', $analysisText);
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 10) {
                $traits[] = $sentence;
            }
        }
        
        return array_slice($traits, 0, 5); // Ограничиваем 5 основными чертами
    }

    /**
     * Форматирование списка навыков
     */
    private function formatSkillsList($skillsText): array
    {
        $skills = [];
        $lines = explode("\n", $skillsText);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $line = preg_replace('/^[•\-\*]\s*/', '', $line);
            if (strlen($line) > 3) {
                $skills[] = $line;
            }
        }
        
        return $skills;
    }

    /**
     * Форматирование рекомендаций по обучению
     */
    private function formatLearningRecommendations($learningText): array
    {
        return $this->formatSkillsList($learningText); // Используем тот же формат
    }

    /**
     * Форматирование особенностей доступности
     */
    private function formatAccessibilityConsiderations($accessibilityText): array
    {
        return $this->formatSkillsList($accessibilityText); // Используем тот же формат
    }

    /**
     * Генерация краткого резюме
     */
    private function generateSummary($sections): string
    {
        $summary = "На основе анализа ваших ответов выявлены следующие ключевые особенности: ";
        
        if (!empty($sections['personality_analysis'])) {
            $firstSentence = explode('.', $sections['personality_analysis'])[0];
            $summary .= trim($firstSentence) . ". ";
        }
        
        $summary .= "Рекомендуется рассмотреть профессии в области, соответствующей вашим интересам и способностям, ";
        $summary .= "с учетом особенностей здоровья и необходимых условий трудовой деятельности.";
        
        return $summary;
    }

    /**
     * Резервный анализ с реальными расчетами на основе ответов
     */
    private function getFallbackAnalysis($answers = null, $disabilityInfo = null): array
    {
        // Если есть ответы, используем реальную статистику
        if ($answers) {
            $statistics = $this->analyzeAnswersStatistics($answers, $disabilityInfo);
            
            return [
                'analysis' => [
                    'personality_traits' => $this->generatePersonalityFromStats($statistics),
                    'suitable_careers' => $statistics['career_matches'],
                    'skills_to_develop' => $this->generateSkillsFromStats($statistics),
                    'learning_recommendations' => $this->generateLearningFromStats($statistics),
                    'accessibility_considerations' => $this->generateAccessibilityFromStats($statistics, $disabilityInfo),
                    'summary' => $this->generateSummaryFromStats($statistics),
                    'detailed_report' => $this->generateDetailedReportFromStats($statistics, $disabilityInfo)
                ],
                'recommendations' => [
                    'careers' => $this->generateCareerRecommendationsFromStats($statistics),
                    'advice' => 'Рекомендации основаны на детальном анализе ваших ответов и статистических расчетах.'
                ]
            ];
        }

        // Если нет ответов, используем базовый анализ
        return [
            'analysis' => [
                'personality_traits' => [
                    'Готовность к обучению',
                    'Стремление к самостоятельности',
                    'Внимательность к деталям',
                    'Желание помогать другим',
                    'Творческий подход к решению задач'
                ],
                'suitable_careers' => [
                    [
                        'title' => 'Специалист по работе с данными',
                        'match_percentage' => 70,
                        'description' => 'Обработка и анализ информации, работа с компьютером',
                        'requirements' => ['Внимательность', 'Компьютерная грамотность'],
                        'accessibility_notes' => 'Подходит для удаленной работы'
                    ],
                    [
                        'title' => 'Консультант по социальным вопросам',
                        'match_percentage' => 65,
                        'description' => 'Помощь людям в решении социальных вопросов',
                        'requirements' => ['Эмпатия', 'Коммуникативные навыки'],
                        'accessibility_notes' => 'Возможна работа в адаптированной среде'
                    ],
                    [
                        'title' => 'Творческий работник',
                        'match_percentage' => 60,
                        'description' => 'Создание творческого контента, рукоделие, искусство',
                        'requirements' => ['Творческие способности', 'Терпение'],
                        'accessibility_notes' => 'Гибкий график, индивидуальный подход'
                    ]
                ],
                'skills_to_develop' => [
                    'Компьютерная грамотность',
                    'Коммуникативные навыки',
                    'Навыки самоорганизации',
                    'Профессиональные навыки в выбранной области',
                    'Навыки работы в команде'
                ],
                'learning_recommendations' => [
                    'Курсы компьютерной грамотности',
                    'Программы профессиональной реабилитации',
                    'Онлайн-обучение в удобном темпе',
                    'Практические мастер-классы',
                    'Индивидуальные консультации с наставником'
                ],
                'accessibility_considerations' => [
                    'Поиск работодателей, поддерживающих инклюзивность',
                    'Использование ассистивных технологий при необходимости',
                    'Обращение в центры занятости для людей с инвалидностью',
                    'Изучение трудовых прав и льгот',
                    'Рассмотрение возможности удаленной работы'
                ],
                'summary' => 'Для получения более точных рекомендаций рекомендуется консультация со специалистом по профориентации.',
                'detailed_report' => 'Данный анализ является базовым. Для получения персонализированного отчета необходимо пройти полный тест.'
            ],
            'recommendations' => [
                'careers' => 'Рекомендуется рассмотреть профессии в сфере услуг, творчества или работы с информацией.',
                'advice' => 'Обратитесь в центр занятости или к специалисту по профориентации для получения персональных рекомендаций.'
            ]
        ];
    }

    /**
     * Генерация личностных качеств на основе статистики
     */
    private function generatePersonalityFromStats($statistics): array
    {
        $traits = [];
        
        foreach ($statistics['category_stats'] as $category => $stats) {
            if ($stats['percentage'] >= 60) {
                switch ($category) {
                    case 'Интересы':
                        $traits[] = "Четко выраженные профессиональные интересы ({$stats['percentage']}%)";
                        break;
                    case 'Навыки':
                        $traits[] = "Развитые профессиональные навыки ({$stats['percentage']}%)";
                        break;
                    case 'Ценности':
                        $traits[] = "Сформированная система трудовых ценностей ({$stats['percentage']}%)";
                        break;
                    case 'Личностные качества':
                        $traits[] = "Выраженные лидерские качества ({$stats['percentage']}%)";
                        break;
                    case 'Обучаемость и мотивация':
                        $traits[] = "Высокая мотивация к обучению ({$stats['percentage']}%)";
                        break;
                }
            }
        }
        
        return $traits ?: ['Базовые профессиональные качества'];
    }

    /**
     * Генерация навыков для развития на основе статистики
     */
    private function generateSkillsFromStats($statistics): array
    {
        $skills = [];
        
        foreach ($statistics['category_stats'] as $category => $stats) {
            if ($stats['percentage'] < 50) {
                switch ($category) {
                    case 'Навыки':
                        $skills[] = "Профессиональные навыки (текущий уровень: {$stats['percentage']}%)";
                        break;
                    case 'Личностные качества':
                        $skills[] = "Лидерские и коммуникативные навыки (текущий уровень: {$stats['percentage']}%)";
                        break;
                    case 'Обучаемость и мотивация':
                        $skills[] = "Навыки самообучения и мотивации (текущий уровень: {$stats['percentage']}%)";
                        break;
                }
            }
        }
        
        return $skills ?: ['Общие профессиональные навыки'];
    }

    /**
     * Генерация рекомендаций по обучению на основе статистики
     */
    private function generateLearningFromStats($statistics): array
    {
        $recommendations = [];
        
        foreach ($statistics['category_stats'] as $category => $stats) {
            if ($stats['percentage'] < 60) {
                switch ($category) {
                    case 'Навыки':
                        $recommendations[] = "Курсы профессиональных навыков для повышения с {$stats['percentage']}%";
                        break;
                    case 'Личностные качества':
                        $recommendations[] = "Тренинги личностного роста для развития с {$stats['percentage']}%";
                        break;
                    case 'Обучаемость и мотивация':
                        $recommendations[] = "Курсы по самоорганизации для улучшения с {$stats['percentage']}%";
                        break;
                }
            }
        }
        
        return $recommendations ?: ['Общие курсы профессионального развития'];
    }

    /**
     * Генерация рекомендаций по доступности на основе статистики
     */
    private function generateAccessibilityFromStats($statistics, $disabilityInfo): array
    {
        $considerations = [
            'Поиск работодателей, поддерживающих инклюзивность',
            'Использование ассистивных технологий при необходимости'
        ];
        
        if ($disabilityInfo) {
            $considerations[] = 'Адаптация рабочего места с учетом особенностей здоровья';
            $considerations[] = 'Консультация с реабилитологом по трудоустройству';
        }
        
        return $considerations;
    }

    /**
     * Генерация общего резюме на основе статистики
     */
    private function generateSummaryFromStats($statistics): string
    {
        $overallPercentage = $statistics['overall_percentage'];
        
        if ($overallPercentage >= 70) {
            return "Высокий уровень готовности к профессиональной деятельности ({$overallPercentage}%). Рекомендуется активный поиск работы в выбранных направлениях.";
        } elseif ($overallPercentage >= 50) {
            return "Средний уровень готовности ({$overallPercentage}%). Рекомендуется дополнительное обучение в слабых областях.";
        } else {
            return "Требуется значительное развитие профессиональных навыков ({$overallPercentage}%). Рекомендуется комплексная подготовка.";
        }
    }

    /**
     * Генерация детального отчета на основе статистики
     */
    private function generateDetailedReportFromStats($statistics, $disabilityInfo): string
    {
        $report = "ДЕТАЛЬНЫЙ ОТЧЕТ НА ОСНОВЕ РЕАЛЬНОЙ СТАТИСТИКИ:\n\n";
        
        $report .= "1. ОБЩИЙ АНАЛИЗ:\n";
        $report .= "Общий результат: {$statistics['overall_score']} из {$statistics['total_questions']} вопросов ({$statistics['overall_percentage']}%)\n\n";
        
        $report .= "2. АНАЛИЗ ПО КАТЕГОРИЯМ:\n";
        foreach ($statistics['category_stats'] as $category => $stats) {
            $report .= "• {$category}: {$stats['percentage']}% (уровень: {$stats['level']})\n";
            $report .= "  Детали: {$stats['total_score']}/{$stats['max_score']} баллов, средний балл: {$stats['average_score']}\n";
        }
        
        $report .= "\n3. ПРОФЕССИОНАЛЬНЫЕ СООТВЕТСТВИЯ:\n";
        foreach ($statistics['career_matches'] as $career) {
            $report .= "• {$career['title']}: {$career['match_percentage']}% соответствия\n";
            $report .= "  {$career['reasoning']}\n";
        }
        
        if ($disabilityInfo) {
            $report .= "\n4. УЧЕТ ОСОБЕННОСТЕЙ ЗДОРОВЬЯ:\n";
            $report .= "При анализе учтены особенности здоровья и адаптивные возможности профессий.\n";
        }
        
        return $report;
    }

    /**
     * Генерация карьерных рекомендаций на основе статистики
     */
    private function generateCareerRecommendationsFromStats($statistics): string
    {
        $topCareer = $statistics['career_matches'][0] ?? null;
        
        if ($topCareer) {
            return "Наиболее подходящая профессия: {$topCareer['title']} ({$topCareer['match_percentage']}% соответствия). " .
                   "Рекомендуется изучить требования и начать подготовку в данном направлении.";
        }
        
        return "Рекомендуется дополнительная консультация для определения оптимального карьерного пути.";
    }

    /**
     * Детальный анализ ответов пользователя с реальной статистикой
     */
    private function analyzeAnswersStatistics($answers, $disabilityInfo): array
    {
        $categories = [
            'Интересы' => [],
            'Навыки' => [],
            'Ценности' => [],
            'Рабочая среда' => [],
            'Личностные качества' => [],
            'Обучаемость и мотивация' => [],
            'Инвалидность и доступность' => []
        ];

        $categoryStats = [];
        $totalAnswers = count($answers);
        $overallScore = 0;

        // Группируем ответы по категориям и считаем статистику
        foreach ($answers as $questionId => $answer) {
            $question = CareerQuestion::find($questionId);
            if ($question) {
                $category = $this->getCategoryByOrder($question->order);
                $categories[$category][] = [
                    'question' => $question->question_text,
                    'answer' => $answer,
                    'score' => (int)$answer
                ];
                $overallScore += (int)$answer;
            }
        }

        // Анализируем каждую категорию
        foreach ($categories as $categoryName => $categoryQuestions) {
            if (!empty($categoryQuestions)) {
                $totalScore = array_sum(array_column($categoryQuestions, 'score'));
                $maxScore = count($categoryQuestions) * 4;
                $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
                
                // Определяем уровень по процентам
                $level = 'низкий';
                if ($percentage >= 75) $level = 'очень высокий';
                elseif ($percentage >= 60) $level = 'высокий';
                elseif ($percentage >= 40) $level = 'средний';
                elseif ($percentage >= 25) $level = 'ниже среднего';

                // Анализируем конкретные ответы
                $highScores = array_filter($categoryQuestions, fn($q) => $q['score'] >= 3);
                $lowScores = array_filter($categoryQuestions, fn($q) => $q['score'] <= 1);

                $categoryStats[$categoryName] = [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'level' => $level,
                    'questions_count' => count($categoryQuestions),
                    'high_scores' => $highScores,
                    'low_scores' => $lowScores,
                    'average_score' => round($totalScore / count($categoryQuestions), 1)
                ];
            }
        }

        // Определяем профессиональные склонности на основе статистики
        $professionalInclinations = $this->determineProfessionalInclinations($categoryStats);
        
        // Рассчитываем соответствие профессиям
        $careerMatches = $this->calculateCareerMatches($categoryStats, $disabilityInfo);

        return [
            'category_stats' => $categoryStats,
            'overall_percentage' => round(($overallScore / ($totalAnswers * 4)) * 100),
            'professional_inclinations' => $professionalInclinations,
            'career_matches' => $careerMatches,
            'total_questions' => $totalAnswers,
            'overall_score' => $overallScore
        ];
    }

    /**
     * Определение профессиональных склонностей на основе статистики
     */
    private function determineProfessionalInclinations($categoryStats): array
    {
        $inclinations = [];

        foreach ($categoryStats as $category => $stats) {
            if ($stats['percentage'] >= 60) {
                switch ($category) {
                    case 'Интересы':
                        if ($stats['percentage'] >= 75) {
                            $inclinations[] = "Ярко выраженные профессиональные интересы ({$stats['percentage']}%)";
                        } else {
                            $inclinations[] = "Четкие профессиональные предпочтения ({$stats['percentage']}%)";
                        }
                        break;
                    case 'Навыки':
                        $inclinations[] = "Высокий уровень профессиональных навыков ({$stats['percentage']}%)";
                        break;
                    case 'Ценности':
                        $inclinations[] = "Четко сформированные трудовые ценности ({$stats['percentage']}%)";
                        break;
                    case 'Рабочая среда':
                        $inclinations[] = "Определенные предпочтения к рабочей среде ({$stats['percentage']}%)";
                        break;
                    case 'Личностные качества':
                        $inclinations[] = "Выраженные лидерские и личностные качества ({$stats['percentage']}%)";
                        break;
                    case 'Обучаемость и мотивация':
                        $inclinations[] = "Высокая мотивация к обучению и развитию ({$stats['percentage']}%)";
                        break;
                    case 'Инвалидность и доступность':
                        $inclinations[] = "Четкое понимание потребностей в адаптации рабочего места ({$stats['percentage']}%)";
                        break;
                }
            }
        }

        return $inclinations;
    }

    /**
     * Расчет соответствия профессиям с детальным обоснованием
     */
    private function calculateCareerMatches($categoryStats, $disabilityInfo): array
    {
        $careers = [
            [
                'title' => 'Специалист по работе с данными',
                'base_match' => 0,
                'requirements' => ['Способности' => 60, 'Мотивация' => 50],
                'description' => 'Обработка и анализ информации, работа с компьютером',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'low'
            ],
            [
                'title' => 'Консультант по социальным вопросам',
                'base_match' => 0,
                'requirements' => ['Интересы' => 60, 'Стиль работы' => 55, 'Ценности' => 60],
                'description' => 'Помощь людям в решении социальных вопросов',
                'accessibility_friendly' => true,
                'remote_work_possible' => false,
                'physical_demands' => 'low',
                'social_interaction' => 'high'
            ],
            [
                'title' => 'Творческий работник (дизайн, рукоделие)',
                'base_match' => 0,
                'requirements' => ['Интересы' => 50, 'Способности' => 45],
                'description' => 'Создание творческого контента, рукоделие, искусство',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'medium',
                'social_interaction' => 'low'
            ],
            [
                'title' => 'Администратор/Делопроизводитель',
                'base_match' => 0,
                'requirements' => ['Способности' => 55, 'Рабочая среда' => 60],
                'description' => 'Ведение документооборота, административная работа',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'medium'
            ],
            [
                'title' => 'Преподаватель/Тренер',
                'base_match' => 0,
                'requirements' => ['Интересы' => 65, 'Стиль работы' => 60, 'Мотивация' => 70],
                'description' => 'Обучение и развитие других людей',
                'accessibility_friendly' => false,
                'remote_work_possible' => false,
                'physical_demands' => 'medium',
                'social_interaction' => 'high'
            ],
            [
                'title' => 'IT-поддержка/Техническая поддержка',
                'base_match' => 0,
                'requirements' => ['Способности' => 65, 'Рабочая среда' => 50],
                'description' => 'Решение технических проблем, поддержка пользователей',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'medium'
            ],
            [
                'title' => 'Переводчик/Редактор',
                'base_match' => 0,
                'requirements' => ['Способности' => 70, 'Интересы' => 55],
                'description' => 'Работа с текстами, переводы, редактирование',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'low'
            ]
        ];

        // Анализируем особенности здоровья для корректировки рекомендаций
        $healthAnalysis = $this->analyzeHealthForCareers($disabilityInfo);

        foreach ($careers as &$career) {
            $matchScore = 0;
            $maxPossibleScore = 0;
            $detailedAnalysis = [];

            foreach ($career['requirements'] as $category => $requiredPercentage) {
                $maxPossibleScore += 100;
                if (isset($categoryStats[$category])) {
                    $userPercentage = $categoryStats[$category]['percentage'];
                    $categoryMatch = min(100, ($userPercentage / $requiredPercentage) * 100);
                    $matchScore += $categoryMatch;

                    $detailedAnalysis[] = [
                        'category' => $category,
                        'required' => $requiredPercentage,
                        'user_score' => $userPercentage,
                        'match_percentage' => round($categoryMatch),
                        'analysis' => $this->getCategoryAnalysis($category, $userPercentage, $requiredPercentage)
                    ];
                }
            }

            $career['match_percentage'] = round($matchScore / count($career['requirements']));
            
            // Корректируем соответствие на основе особенностей здоровья
            $career = $this->adjustCareerForHealth($career, $healthAnalysis);
            
            $career['detailed_analysis'] = $detailedAnalysis;
            $career['reasoning'] = $this->generateCareerReasoning($career, $detailedAnalysis, $disabilityInfo);
        }

        // Сортируем по соответствию
        usort($careers, fn($a, $b) => $b['match_percentage'] <=> $a['match_percentage']);

        return array_slice($careers, 0, 5); // Возвращаем топ-5
    }
    
    /**
     * Анализ особенностей здоровья для карьерных рекомендаций
     */
    private function analyzeHealthForCareers($disabilityInfo): array
    {
        $analysis = [
            'needs_remote_work' => false,
            'needs_low_physical' => false,
            'needs_low_social' => false,
            'needs_flexible_schedule' => false,
            'vision_considerations' => false,
            'hearing_considerations' => false,
            'mobility_considerations' => false,
            'cognitive_considerations' => false
        ];
        
        if (!$disabilityInfo) {
            return $analysis;
        }
        
        if (is_string($disabilityInfo) && !empty(trim($disabilityInfo))) {
            $text = mb_strtolower(trim($disabilityInfo));
            $keywordAnalysis = $this->analyzeHealthKeywords($text);
            
            if ($keywordAnalysis['mobility_issues']) {
                $analysis['needs_remote_work'] = true;
                $analysis['needs_low_physical'] = true;
                $analysis['mobility_considerations'] = true;
            }
            
            if ($keywordAnalysis['vision_issues']) {
                $analysis['vision_considerations'] = true;
                $analysis['needs_low_physical'] = true;
            }
            
            if ($keywordAnalysis['hearing_issues']) {
                $analysis['hearing_considerations'] = true;
                $analysis['needs_low_social'] = true;
            }
            
            if ($keywordAnalysis['cognitive_issues']) {
                $analysis['cognitive_considerations'] = true;
                $analysis['needs_low_social'] = true;
            }
            
            if ($keywordAnalysis['chronic_conditions']) {
                $analysis['needs_flexible_schedule'] = true;
                $analysis['needs_remote_work'] = true;
            }
            
            if ($keywordAnalysis['social_anxiety']) {
                $analysis['needs_low_social'] = true;
                $analysis['needs_remote_work'] = true;
            }
        }
        
        return $analysis;
    }
    
    /**
     * Корректировка карьерных рекомендаций на основе особенностей здоровья
     */
    private function adjustCareerForHealth($career, $healthAnalysis): array
    {
        $adjustments = [];
        $bonus = 0;
        $penalty = 0;
        
        // Проверяем соответствие требованиям здоровья
        if ($healthAnalysis['needs_remote_work'] && $career['remote_work_possible']) {
            $bonus += 15;
            $adjustments[] = "Возможность удаленной работы (+15%)";
        } elseif ($healthAnalysis['needs_remote_work'] && !$career['remote_work_possible']) {
            $penalty += 20;
            $adjustments[] = "Требуется присутствие в офисе (-20%)";
        }
        
        if ($healthAnalysis['needs_low_physical'] && $career['physical_demands'] === 'low') {
            $bonus += 10;
            $adjustments[] = "Низкие физические требования (+10%)";
        } elseif ($healthAnalysis['needs_low_physical'] && $career['physical_demands'] === 'high') {
            $penalty += 25;
            $adjustments[] = "Высокие физические требования (-25%)";
        }
        
        if ($healthAnalysis['needs_low_social'] && $career['social_interaction'] === 'low') {
            $bonus += 10;
            $adjustments[] = "Минимальное социальное взаимодействие (+10%)";
        } elseif ($healthAnalysis['needs_low_social'] && $career['social_interaction'] === 'high') {
            $penalty += 15;
            $adjustments[] = "Высокие требования к социальному взаимодействию (-15%)";
        }
        
        // Специфические корректировки
        if ($healthAnalysis['vision_considerations'] && 
            in_array($career['title'], ['Специалист по работе с данными', 'Переводчик/Редактор'])) {
            $adjustments[] = "Может потребоваться адаптация рабочего места для работы с компьютером";
        }
        
        if ($healthAnalysis['hearing_considerations'] && 
            $career['title'] === 'Преподаватель/Тренер') {
            $penalty += 30;
            $adjustments[] = "Профессия требует активного слухового восприятия (-30%)";
        }
        
        // Применяем корректировки
        $career['match_percentage'] = max(0, min(100, $career['match_percentage'] + $bonus - $penalty));
        $career['health_adjustments'] = $adjustments;
        
        return $career;
    }

    /**
     * Анализ категории для профессии
     */
    private function getCategoryAnalysis($category, $userScore, $required): string
    {
        $difference = $userScore - $required;
        
        if ($difference >= 20) {
            return "Отлично подходит - ваш результат значительно превышает требования";
        } elseif ($difference >= 0) {
            return "Хорошо подходит - ваш результат соответствует требованиям";
        } elseif ($difference >= -15) {
            return "Частично подходит - небольшой недостаток можно компенсировать обучением";
        } else {
            return "Требует развития - значительный разрыв с требованиями профессии";
        }
    }

    /**
     * Генерация детального обоснования для профессии
     */
    private function generateCareerReasoning($career, $detailedAnalysis, $disabilityInfo): string
    {
        $reasoning = "ОБОСНОВАНИЕ РЕКОМЕНДАЦИИ:\n\n";
        
        $reasoning .= "Анализ соответствия по категориям:\n";
        foreach ($detailedAnalysis as $analysis) {
            $reasoning .= "• {$analysis['category']}: {$analysis['user_score']}% (требуется {$analysis['required']}%) - {$analysis['analysis']}\n";
        }
        
        $reasoning .= "\nОбщее соответствие: {$career['match_percentage']}%\n";
        
        // Добавляем информацию о корректировках по здоровью
        if (isset($career['health_adjustments']) && !empty($career['health_adjustments'])) {
            $reasoning .= "\nКорректировки с учетом особенностей здоровья:\n";
            foreach ($career['health_adjustments'] as $adjustment) {
                $reasoning .= "• {$adjustment}\n";
            }
        }
        
        $reasoning .= "\n";
        
        if ($career['match_percentage'] >= 70) {
            $reasoning .= "ВЫВОД: Высокое соответствие профессии. Рекомендуется к рассмотрению.\n";
        } elseif ($career['match_percentage'] >= 50) {
            $reasoning .= "ВЫВОД: Умеренное соответствие. Возможно при дополнительном обучении.\n";
        } else {
            $reasoning .= "ВЫВОД: Низкое соответствие. Требует значительного развития навыков.\n";
        }

        if ($disabilityInfo && $career['accessibility_friendly']) {
            $reasoning .= "\nДОСТУПНОСТЬ: Профессия адаптируема для людей с особенностями здоровья.\n";
            
            // Добавляем специфические рекомендации по доступности
            if ($career['remote_work_possible']) {
                $reasoning .= "• Возможность удаленной работы\n";
            }
            if ($career['physical_demands'] === 'low') {
                $reasoning .= "• Низкие физические требования\n";
            }
            if ($career['social_interaction'] === 'low') {
                $reasoning .= "• Минимальное социальное взаимодействие\n";
            }
        }

        return $reasoning;
    }

    /**
     * Генерация черт личности на основе статистики
     */
    private function generatePersonalityTraits($statistics): array
    {
        $traits = [];
        
        foreach ($statistics as $category => $stats) {
            if ($stats['percentage'] >= 75) {
                $traits[] = "Высокий уровень в категории '{$category}' ({$stats['percentage']}%)";
            } elseif ($stats['percentage'] >= 50) {
                $traits[] = "Средний уровень в категории '{$category}' ({$stats['percentage']}%)";
            } else {
                $traits[] = "Требует развития в категории '{$category}' ({$stats['percentage']}%)";
            }
        }
        
        return array_slice($traits, 0, 5);
    }

    /**
     * Генерация навыков для улучшения
     */
    private function generateSkillsToImprove($statistics): array
    {
        $skills = [];
        
        foreach ($statistics as $category => $stats) {
            if ($stats['percentage'] < 60) {
                switch ($category) {
                    case 'Интересы':
                        $skills[] = 'Исследование новых областей деятельности';
                        $skills[] = 'Развитие профессиональных интересов';
                        break;
                    case 'Ценности':
                        $skills[] = 'Определение жизненных приоритетов';
                        $skills[] = 'Развитие системы ценностей';
                        break;
                    case 'Личностные качества':
                        $skills[] = 'Развитие коммуникативных навыков';
                        $skills[] = 'Работа над эмоциональным интеллектом';
                        break;
                    case 'Навыки':
                        $skills[] = 'Развитие профессиональных навыков';
                        $skills[] = 'Освоение новых технологий и инструментов';
                        break;
                    case 'Рабочая среда':
                        $skills[] = 'Адаптация к различным условиям работы';
                        $skills[] = 'Развитие навыков работы в команде';
                        break;
                    case 'Обучаемость и мотивация':
                        $skills[] = 'Развитие навыков самообучения';
                        $skills[] = 'Повышение мотивации к обучению';
                        break;
                }
            }
        }
        
        return array_unique($skills);
    }

    /**
     * Генерация рекомендаций по обучению
     */
    private function generateLearningRecommendations($statistics): array
    {
        $recommendations = [];
        
        foreach ($statistics as $category => $stats) {
            if ($stats['percentage'] < 70) {
                switch ($category) {
                    case 'Интересы':
                        $recommendations[] = 'Пройти курсы профориентации';
                        $recommendations[] = 'Изучить различные профессиональные области';
                        break;
                    case 'Ценности':
                        $recommendations[] = 'Работа с карьерным консультантом';
                        $recommendations[] = 'Тренинги по личностному развитию';
                        break;
                    case 'Личностные качества':
                        $recommendations[] = 'Курсы развития soft skills';
                        $recommendations[] = 'Тренинги по коммуникации';
                        break;
                    case 'Навыки':
                        $recommendations[] = 'Профессиональные курсы и сертификации';
                        $recommendations[] = 'Практические мастер-классы';
                        break;
                    case 'Рабочая среда':
                        $recommendations[] = 'Тренинги по адаптации к рабочей среде';
                        $recommendations[] = 'Курсы по организации рабочего места';
                        break;
                    case 'Обучаемость и мотивация':
                        $recommendations[] = 'Курсы по эффективному обучению';
                        $recommendations[] = 'Тренинги по тайм-менеджменту';
                        break;
                }
            }
        }
        
        return array_unique($recommendations);
    }

    /**
     * Генерация рекомендаций по доступности
     */
    private function generateAccessibilityConsiderations($disabilityInfo): array
    {
        if (!$disabilityInfo) {
            return [];
        }
        
        $considerations = [];
        
        // Если это структурированные данные (старый формат)
        if (is_array($disabilityInfo)) {
            if (isset($disabilityInfo['disability_type'])) {
                switch ($disabilityInfo['disability_type']) {
                    case 'hearing':
                        $considerations[] = 'Предпочтение профессий с визуальной коммуникацией';
                        $considerations[] = 'Необходимость адаптированного рабочего места';
                        $considerations[] = 'Возможность удаленной работы';
                        break;
                    case 'vision':
                        $considerations[] = 'Профессии с минимальными визуальными требованиями';
                        $considerations[] = 'Использование ассистивных технологий';
                        break;
                    case 'mobility':
                        $considerations[] = 'Доступность рабочего места';
                        $considerations[] = 'Возможность гибкого графика';
                        break;
                }
            }
            
            if (isset($disabilityInfo['needs_adaptation']) && $disabilityInfo['needs_adaptation']) {
                $considerations[] = 'Необходимость адаптации рабочего места';
                $considerations[] = 'Поддержка со стороны работодателя';
            }
        } 
        // Если это текстовая информация (новый формат)
        else if (is_string($disabilityInfo) && !empty(trim($disabilityInfo))) {
            $text = mb_strtolower(trim($disabilityInfo));
            
            // Анализируем текст на ключевые слова и фразы
            $keywordAnalysis = $this->analyzeHealthKeywords($text);
            
            // Базовые рекомендации для всех случаев
            $considerations[] = 'Поиск работодателей, поддерживающих инклюзивную среду';
            $considerations[] = 'Консультация с центром занятости для людей с инвалидностью';
            
            // Специфические рекомендации на основе анализа текста
            if ($keywordAnalysis['mobility_issues']) {
                $considerations[] = 'Рассмотрение возможности удаленной работы или гибкого графика';
                $considerations[] = 'Обеспечение физической доступности рабочего места';
                $considerations[] = 'Адаптация рабочего места под потребности мобильности';
            }
            
            if ($keywordAnalysis['vision_issues']) {
                $considerations[] = 'Использование ассистивных технологий для работы с компьютером';
                $considerations[] = 'Профессии с минимальными визуальными требованиями';
                $considerations[] = 'Адаптация рабочего места для людей с нарушениями зрения';
            }
            
            if ($keywordAnalysis['hearing_issues']) {
                $considerations[] = 'Предпочтение профессий с письменной коммуникацией';
                $considerations[] = 'Использование визуальных средств коммуникации';
                $considerations[] = 'Адаптация рабочего места для людей с нарушениями слуха';
            }
            
            if ($keywordAnalysis['cognitive_issues']) {
                $considerations[] = 'Профессии с четкой структурой и предсказуемыми задачами';
                $considerations[] = 'Возможность работы в спокойной обстановке';
                $considerations[] = 'Дополнительное время для выполнения задач при необходимости';
            }
            
            if ($keywordAnalysis['chronic_conditions']) {
                $considerations[] = 'Гибкий график работы с учетом состояния здоровья';
                $considerations[] = 'Возможность работы из дома в периоды обострений';
                $considerations[] = 'Профессии с низким уровнем стресса';
            }
            
            if ($keywordAnalysis['social_anxiety']) {
                $considerations[] = 'Профессии с минимальным социальным взаимодействием';
                $considerations[] = 'Возможность удаленной работы';
                $considerations[] = 'Постепенная адаптация к рабочему коллективу';
            }
            
            // Общие рекомендации
            $considerations[] = 'Изучение трудовых прав и льгот для людей с инвалидностью';
            $considerations[] = 'Рассмотрение программ профессиональной реабилитации';
            
            // Добавляем персонализированную рекомендацию на основе конкретного текста
            $considerations[] = "Индивидуальный подход с учетом описанных особенностей: \"" . 
                              mb_substr($disabilityInfo, 0, 100) . (mb_strlen($disabilityInfo) > 100 ? "..." : "") . "\"";
        }
        
        return array_unique($considerations);
    }
    
    /**
     * Анализ ключевых слов в тексте об особенностях здоровья
     */
    private function analyzeHealthKeywords($text): array
    {
        $analysis = [
            'mobility_issues' => false,
            'vision_issues' => false,
            'hearing_issues' => false,
            'cognitive_issues' => false,
            'chronic_conditions' => false,
            'social_anxiety' => false
        ];
        
        // Ключевые слова для различных типов особенностей
        $keywords = [
            'mobility_issues' => [
                'колясочник', 'коляска', 'инвалидная коляска', 'ходить', 'передвигаться', 
                'опорно-двигательный', 'ноги', 'руки', 'конечности', 'паралич', 'ампутация',
                'костыли', 'трость', 'протез', 'мобильность', 'передвижение'
            ],
            'vision_issues' => [
                'слепой', 'слепота', 'зрение', 'глаза', 'видеть', 'слабовидящий', 
                'очки', 'линзы', 'катаракта', 'глаукома', 'сетчатка', 'близорукость',
                'дальнозоркость', 'читать', 'экран', 'текст'
            ],
            'hearing_issues' => [
                'глухой', 'глухота', 'слух', 'слышать', 'слабослышащий', 'уши',
                'слуховой аппарат', 'кохлеарный имплант', 'жестовый язык', 'сурдопереводчик'
            ],
            'cognitive_issues' => [
                'аутизм', 'аутист', 'синдром дауна', 'умственная отсталость', 'деменция',
                'память', 'концентрация', 'внимание', 'обучение', 'понимание', 'мышление',
                'интеллект', 'когнитивный', 'ментальный', 'психический'
            ],
            'chronic_conditions' => [
                'диабет', 'эпилепсия', 'астма', 'артрит', 'рассеянный склероз', 'болезнь',
                'хроническое', 'лечение', 'лекарства', 'терапия', 'обострение', 'ремиссия',
                'усталость', 'боль', 'приступы'
            ],
            'social_anxiety' => [
                'социофобия', 'тревожность', 'стресс', 'депрессия', 'общение', 'люди',
                'толпа', 'социальный', 'замкнутость', 'стеснительность', 'паника'
            ]
        ];
        
        foreach ($keywords as $category => $wordList) {
            foreach ($wordList as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $analysis[$category] = true;
                    break;
                }
            }
        }
        
        return $analysis;
    }

    /**
     * Генерация краткого резюме анализа
     */
    private function generateAnalysisSummary($statistics, $inclinations): string
    {
        $summary = "КРАТКОЕ РЕЗЮМЕ АНАЛИЗА:\n\n";
        
        $summary .= "Общий балл: {$statistics['overall_score']}/{$statistics['total_questions']} ({$statistics['overall_percentage']}%)\n";
        
        // Подсчитываем сильные и слабые стороны из category_stats
        $strongCategories = 0;
        $weakCategories = 0;
        foreach ($statistics['category_stats'] as $category => $stats) {
            if ($stats['percentage'] >= 70) $strongCategories++;
            if ($stats['percentage'] < 50) $weakCategories++;
        }
        
        $summary .= "Сильные стороны: {$strongCategories} категорий\n";
        $summary .= "Области для развития: {$weakCategories} категорий\n\n";
        
        $summary .= "Основные профессиональные склонности:\n";
        foreach (array_slice($inclinations, 0, 3) as $inclination) {
            $summary .= "• {$inclination}\n";
        }
        
        return $summary;
    }

    /**
     * Генерация детального отчета
     */
    private function generateDetailedReport($statistics, $inclinations, $careerMatches, $disabilityInfo): string
    {
        $report = "ДЕТАЛЬНЫЙ ОТЧЕТ О ПРОЦЕССЕ АНАЛИЗА\n\n";
        
        $report .= "1. СТАТИСТИЧЕСКИЙ АНАЛИЗ ОТВЕТОВ:\n";
        foreach ($statistics['category_stats'] as $category => $stats) {
            $report .= "- {$category}: {$stats['total_score']}/{$stats['max_score']} баллов ({$stats['percentage']}%)\n";
        }
        
        // Подсчитываем общие показатели
        $totalHighScores = 0;
        $totalLowScores = 0;
        foreach ($statistics['category_stats'] as $stats) {
            $totalHighScores += count($stats['high_scores']);
            $totalLowScores += count($stats['low_scores']);
        }
        
        $averageScore = round($statistics['overall_score'] / $statistics['total_questions'], 1);
        $report .= "Средний балл: {$averageScore}/4\n";
        $report .= "Количество высоких оценок (3-4): {$totalHighScores}\n";
        $report .= "Количество низких оценок (0-1): {$totalLowScores}\n\n";
        
        $report .= "2. ИНТЕРПРЕТАЦИЯ РЕЗУЛЬТАТОВ:\n";
        $report .= "На основе анализа ваших ответов были выявлены ключевые профессиональные склонности:\n";
        foreach ($inclinations as $inclination) {
            $report .= "- {$inclination}\n";
        }
        $report .= "\n";
        
        $report .= "3. РАССЧИТАННЫЕ СООТВЕТСТВИЯ ПРОФЕССИЯМ:\n";
        foreach ($careerMatches as $career) {
            $report .= "- {$career['title']}: {$career['match_percentage']}% соответствия\n";
            if (isset($career['reasoning'])) {
                $report .= "  Обоснование: " . substr($career['reasoning'], 0, 200) . "...\n";
            }
        }
        $report .= "\n";
        
        if ($disabilityInfo) {
            $report .= "4. ВЛИЯНИЕ ОСОБЕННОСТЕЙ ЗДОРОВЬЯ:\n";
            $report .= "При формировании рекомендаций учитывались:\n";
            $report .= "- Необходимость адаптированной рабочей среды\n";
            $report .= "- Возможности гибкого графика работы\n";
            $report .= "- Доступность рабочего места и инструментов\n\n";
        }
        
        $report .= "5. ЛОГИЧЕСКАЯ ЦЕПОЧКА РЕКОМЕНДАЦИЙ:\n";
        $report .= "Анализ → Выявление склонностей → Учет ограничений → Подбор профессий → Рекомендации по развитию\n\n";
        
        $report .= "Данный отчет основан на реальной статистике ваших ответов и научно обоснованных методиках профориентации.";
        
        return $report;
    }
    
    /**
     * Очистка данных от некорректных UTF-8 символов
     */
    private function cleanUtf8Data($data)
    {
        if (is_string($data)) {
            // Удаляем некорректные UTF-8 символы
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // Удаляем управляющие символы кроме переносов строк
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
            return $data;
        }
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->cleanUtf8Data($value);
            }
            return $data;
        }
        
        return $data;
    }
}
