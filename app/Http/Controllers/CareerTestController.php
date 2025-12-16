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
     * Получить анализ от ИИ (или эмуляцию)
     */
    private function getAIAnalysis($test, $answers, $disabilityInfo = null): array
    {
        try {
            // Анализируем ответы для получения базовой статистики
            $statistics = $this->analyzeAnswersStatistics($answers, $disabilityInfo);
            
            // Генерируем "Человеческий" отчет в идеальной структуре Visual Mind Career
            return [
                'intro' => "Этот отчёт — не оценка и не приговор. Это навигатор, который показывает варианты, подходящие именно вам, с учётом ваших особенностей, возможностей и комфорта.",
                'personal_profile' => $this->generatePersonalProfile($statistics, $disabilityInfo),
                'accessibility_map' => $this->generateAccessibilityMap($statistics, $disabilityInfo),
                'strengths' => $this->generateStrengths($statistics),
                'professional_scenarios' => $this->generateProfessionalScenarios($statistics, $disabilityInfo),
                'risks_and_limits' => $this->generateRisksAndLimits($statistics, $disabilityInfo),
                'growth_areas' => $this->generateGrowthAreas($statistics),
                'next_steps' => $this->generateNextSteps($statistics),
                'ai_explanation' => $this->generateAIExplanation($statistics),
                'summary' => "Анализ завершен успешно."
            ];
            
        } catch (\Exception $e) {
            Log::error('AI Analysis Error: ' . $e->getMessage());
            return $this->getFallbackAnalysis($answers, $disabilityInfo);
        }
    }

    /**
     * Фоллбэк анализ
     */
    private function getFallbackAnalysis($answers, $disabilityInfo): array
    {
        return [
            'intro' => "Этот отчёт — не оценка и не приговор.",
            'personal_profile' => "Мы проанализировали ваши ответы. Вы — человек, стремящийся к развитию.",
            'accessibility_map' => [
                'suitable' => ["Индивидуальный темп"],
                'conditional' => ["Офис (тихий)"],
                'not_recommended' => ["Шумные производства"]
            ],
            'strengths' => ["Стремление к росту", "Ответственность"],
            'professional_scenarios' => [
                'main' => [
                    'title' => 'Удаленная работа с информацией',
                    'reasoning' => 'Минимизирует стресс и физическую нагрузку.',
                    'roles' => ['Копирайтер', 'Оператор ввода данных'],
                    'format' => 'Удаленно',
                    'risks' => 'Монотонность'
                ],
                'alternative' => [
                    'title' => 'Административная поддержка',
                    'reasoning' => 'Структурированные задачи.',
                    'roles' => ['Ассистент'],
                    'format' => 'Удаленно/Гибрид',
                    'risks' => 'Коммуникационная перегрузка'
                ],
                'potential' => [
                    'title' => 'IT-специальности',
                    'reasoning' => 'Высокий потенциал роста.',
                    'roles' => ['QA-тестировщик'],
                    'format' => 'Удаленно',
                    'risks' => 'Высокая когнитивная нагрузка'
                ]
            ],
            'risks_and_limits' => ["Избегайте перегрузок"],
            'growth_areas' => ["Изучение цифровых инструментов"],
            'next_steps' => [
                'immediate' => "Пройти вводный урок",
                'short_term' => "Составить резюме",
                'long_term' => "Найти стажировку"
            ],
            'ai_explanation' => "Базовый анализ на основе ключевых слов.",
            'summary' => "Базовый анализ."
        ];
    }

    /**
     * 1. ПЕРСОНАЛЬНЫЙ ПРОФИЛЬ (глубокий)
     */
    private function generatePersonalProfile($statistics, $disabilityInfo): string
    {
        $profile = [];
        $cats = $statistics['category_stats'];
        $transcript = $statistics['transcript'];

        // Стиль работы и среда
        $introvert = false;
        foreach ($transcript as $item) {
             if (mb_stripos($item['question'], 'интроверт') !== false && mb_stripos($item['answer'], 'Интроверт') !== false) {
                 $introvert = true;
             }
        }
        
        if ($introvert) {
            $profile[] = "Вы лучше всего чувствуете себя в спокойной, предсказуемой рабочей среде, где можно сосредоточиться на задачах без лишнего социального давления.";
        } else {
             $profile[] = "Вам комфортно в динамичной среде, где есть возможность общаться и взаимодействовать с людьми.";
        }

        // Автономность
        if (($cats['Самостоятельность']['percentage'] ?? 0) > 70) {
            $profile[] = "Вы достаточно самостоятельны, умеете работать без постоянного контроля и берёте ответственность за результат в комфортных для себя рамках.";
        } else {
            $profile[] = "Вам комфортнее работать с четкими инструкциями и поддержкой наставника на первых этапах.";
        }

        // Отношение к нагрузке (из новых вопросов)
        $energyIssues = false;
        foreach ($transcript as $t) {
            if (mb_stripos($t['question'], 'усталость') !== false && mb_stripos($t['answer'], 'часто') !== false) $energyIssues = true;
        }

        if ($energyIssues || $disabilityInfo) {
            $profile[] = "Для вас важно сохранять баланс между работой и отдыхом — это не слабость, а условие, при котором вы работаете устойчиво и качественно.";
        } else {
             $profile[] = "Вы обладаете хорошим запасом энергии, но важно не забывать о перерывах для профилактики выгорания.";
        }
        
        // Сильная особенность
        if (($cats['Внимание']['percentage'] ?? 0) > 70) {
            $profile[] = "В комфортных условиях вы проявляете исключительную внимательность и способность к глубокой концентрации.";
        } elseif (($cats['Коммуникация']['percentage'] ?? 0) > 70) {
            $profile[] = "Ваша сильная сторона — умение находить общий язык и договариваться.";
        }

        return implode("\n\n", $profile);
    }

    /**
     * 2. КАРТА ДОСТУПНОСТИ (структурированная)
     */
    private function generateAccessibilityMap($statistics, $disabilityInfo): array
    {
        $map = [
            'suitable' => [],       // ✔ Подходит
            'conditional' => [],    // ⚠ Допустимо при условиях
            'not_recommended' => [] // ✖ Не рекомендуется
        ];

        $transcript = $statistics['transcript'];

        // Базовые (безопасные) предположения
        $map['suitable'][] = "Гибкий или предсказуемый график";
        $map['suitable'][] = "Задачи с чётким описанием";

        // Анализ ответов
        foreach ($transcript as $t) {
            // Формат работы
            if (mb_stripos($t['question'], 'формат работы') !== false) {
                if (mb_stripos($t['answer'], 'Удалённо') !== false) {
                    $map['suitable'][] = "Удалённая работа";
                    $map['conditional'][] = "Гибридный формат (если есть возможность отдыха)";
                    $map['not_recommended'][] = "Работа в шумном офисе open-space";
                } elseif (mb_stripos($t['answer'], 'Офис') !== false) {
                    $map['suitable'][] = "Работа в офисе";
                    $map['conditional'][] = "Удаленная работа (при наличии самодисциплины)";
                }
            }

            // Нагрузка
            if (mb_stripos($t['question'], 'переутомлению') !== false) {
                 $risks = explode(',', $t['answer']); // Множественный выбор часто через запятую
                 foreach ($risks as $risk) {
                     $risk = trim($risk);
                     if (mb_stripos($risk, 'Физическая') !== false) {
                         $map['not_recommended'][] = "Физическая нагрузка";
                     }
                     if (mb_stripos($risk, 'Шум') !== false) {
                         $map['not_recommended'][] = "Высокая сенсорная перегрузка (шум, суета)";
                         $map['conditional'][] = "Офисная работа — только при тишине";
                     }
                     if (mb_stripos($risk, 'Дедлайны') !== false) {
                         $map['not_recommended'][] = "Работа в условиях постоянного давления и срочных дедлайнов";
                     }
                      if (mb_stripos($risk, 'общение') !== false) {
                         $map['not_recommended'][] = "Работа с большим потоком людей (колл-центр, ресепшн)";
                         $map['suitable'][] = "Работа с документами и текстами";
                     }
                 }
            }
        }
        
        // Анализ disabilityInfo
        if ($disabilityInfo) {
            $text = mb_strtolower($disabilityInfo);
            if (mb_strpos($text, 'спина') !== false || mb_strpos($text, 'ноги') !== false) {
                $map['not_recommended'][] = "Работа «на ногах» или с поднятием тяжестей";
                $map['suitable'][] = "Сидячая работа с возможностью разминки";
            }
             if (mb_strpos($text, 'зрение') !== false) {
                $map['conditional'][] = "Работа с компьютером — при наличии программ экранного доступа или увеличении шрифта";
            }
        }

        // Убираем дубли
        $map['suitable'] = array_unique($map['suitable']);
        $map['conditional'] = array_unique($map['conditional']);
        $map['not_recommended'] = array_unique($map['not_recommended']);

        return $map;
    }

    /**
     * 3. СИЛЬНЫЕ СТОРОНЫ И РЕСУРСЫ (фокус на опору)
     */
    private function generateStrengths($statistics): array
    {
        $strengths = [];
        $cats = $statistics['category_stats'];

        if (($cats['Навыки']['percentage'] ?? 0) > 50) $strengths[] = "Уверенное владение цифровыми инструментами";
        if (($cats['Самостоятельность']['percentage'] ?? 0) > 60) $strengths[] = "Способность работать самостоятельно и доводить задачи до конца";
        $strengths[] = "Ответственность и надёжность"; // Базовое для всех, кто прошел тест до конца
        $strengths[] = "Честное и зрелое понимание своих возможностей"; // Сам факт прохождения теста
        
        if (($cats['Обучение']['percentage'] ?? 0) > 60) $strengths[] = "Готовность развиваться и искать подходящий формат";

        return array_slice($strengths, 0, 5);
    }

    /**
     * 4. ПРОФЕССИОНАЛЬНЫЕ СЦЕНАРИИ (сценарии с рисками)
     */
    private function generateProfessionalScenarios($statistics, $disabilityInfo): array
    {
        $cats = $statistics['category_stats'];
        $transcript = $statistics['transcript'];

        // Логика выбора сценариев
        $scenarios = [
            'main' => null,
            'alternative' => null,
            'potential' => null
        ];

        // 1. Сценарий "Информация" (базовый для многих)
        $infoScenario = [
            'title' => 'Работа с информацией и документами',
            'reasoning' => 'Этот формат опирается на внимательность и цифровые навыки. Он минимизирует физическую нагрузку и снижает стресс от интенсивного общения.',
            'roles' => ['Оператор ввода данных', 'Транскрибатор', 'Модератор контента', 'Копирайтер / Рерайтер'],
            'format' => 'Удалённо или гибко',
            'risks' => 'Монотонность — важно чередовать задачи и делать перерывы.'
        ];

        // 2. Сценарий "Сервис/Поддержка" (для коммуникабельных)
        $supportScenario = [
            'title' => 'Поддерживающие и сервисные цифровые роли',
            'reasoning' => 'Подходит, если вы хотите больше разнообразия и общения, но в комфортном текстовом формате без звонков.',
            'roles' => ['Помощник администратора (онлайн)', 'Специалист чат-поддержки', 'Ассистент в онлайн-проектах'],
            'format' => 'Удалённо, с чёткими инструкциями',
            'risks' => 'Перегрузка при плохой организации — важно заранее обсуждать объём задач.'
        ];

        // 3. Сценарий "Творчество" (для креативных)
        $creativeScenario = [
            'title' => 'Творческая работа и дизайн',
            'reasoning' => 'Позволяет выразить себя и работать на результат, часто в свободном графике.',
            'roles' => ['Графический дизайнер (junior)', 'Обработка фото', 'Иллюстратор'],
            'format' => 'Фриланс или проектная работа',
            'risks' => 'Нестабильность заказов и творческое выгорание.'
        ];

        // 4. Сценарий "Аналитика/IT" (для системных)
        $techScenario = [
            'title' => 'Технические и аналитические задачи',
            'reasoning' => 'Работа с четкими системами и логикой. Минимум хаоса, максимум структуры.',
            'roles' => ['Тестировщик (QA Manual)', 'Младший аналитик данных', 'Контент-менеджер (технический)'],
            'format' => 'Удаленно, полный день',
            'risks' => 'Высокая когнитивная нагрузка и необходимость постоянного обучения.'
        ];

        // Определение основного сценария
        if (($cats['Мышление']['percentage'] ?? 0) > 60 && ($cats['Навыки']['percentage'] ?? 0) > 60) {
            $scenarios['main'] = $techScenario;
            $scenarios['alternative'] = $infoScenario;
        } elseif (($cats['Интересы']['percentage'] ?? 0) > 70) { // Условно творческий
            $scenarios['main'] = $creativeScenario;
            $scenarios['alternative'] = $supportScenario;
        } else {
            $scenarios['main'] = $infoScenario;
            $scenarios['alternative'] = $supportScenario;
        }

        // Потенциальный сценарий (на вырост)
        $scenarios['potential'] = [
            'title' => 'Расширение профессиональных горизонтов',
            'reasoning' => 'Если со временем вы захотите усложнить задачи.',
            'roles' => ['Обучение смежным цифровым навыкам', 'Более сложная работа с текстами'],
            'format' => 'Обучение + практика',
            'risks' => 'Этот сценарий не обязателен сейчас и может рассматриваться позже.'
        ];

        return $scenarios;
    }

    /**
     * 5. РИСКИ И БЕРЕЖНЫЕ ОГРАНИЧЕНИЯ
     */
    private function generateRisksAndLimits($statistics, $disabilityInfo): array
    {
        $risks = [];
        $transcript = $statistics['transcript'];

        foreach ($transcript as $t) {
            if (mb_stripos($t['question'], 'переутомлению') !== false) {
                if (mb_stripos($t['answer'], 'Физическая') !== false) $risks[] = "Чрезмерная физическая нагрузка может быстро привести к ухудшению самочувствия.";
                if (mb_stripos($t['answer'], 'Шум') !== false) $risks[] = "Сенсорная перегрузка (шум, яркий свет) снижает концентрацию и качество работы.";
                if (mb_stripos($t['answer'], 'Дедлайны') !== false) $risks[] = "Работа в режиме «горящих сроков» создает опасный уровень стресса.";
                if (mb_stripos($t['answer'], 'Монотонность') !== false) $risks[] = "Длительная монотонная работа может вызывать утомление быстрее, чем сложные задачи.";
            }
        }
        
        if (empty($risks)) {
             $risks[] = "Если вы чувствуете усталость или напряжение — это сигнал замедлиться, а не «пересилить себя».";
             $risks[] = "Неопределённость задач и постоянные изменения повышают стресс.";
        }

        return array_unique($risks);
    }

    /**
     * 6. ЧТО СТОИТ РАЗВИВАТЬ (мягко)
     */
    private function generateGrowthAreas($statistics): array // Дубликат, но оставим для совместимости
    {
         return [
            "Попробовать короткие онлайн-курсы (без долгих обязательств).",
            "Поэкспериментировать с разным временем работы, чтобы найти свой ритм.",
            "Постепенно формировать понимание, какие задачи приносят больше удовлетворения."
        ];
    }

    /**
     * 7. ПЕРСОНАЛЬНЫЕ СЛЕДУЮЩИЕ ШАГИ (Timeline)
     */
    private function generateNextSteps($statistics): array
    {
        return [
            'immediate' => "Пройти 1–2 бесплатных вводных урока по выбранным сценариям.",
            'short_term' => "Составить простое резюме, делая акцент на сильных сторонах и комфортных условиях работы.",
            'medium_term' => "Посмотреть вакансии с фильтрами «удалённо» и «доступно для людей с инвалидностью».",
            'long_term' => "Попробовать выполнить тестовое задание для себя, без давления и отправки работодателю."
        ];
    }

    /**
     * 8. КАК ИИ ЭТО ПОНЯЛ
     */
    private function generateAIExplanation($statistics): string
    {
        return "Эти рекомендации основаны на ваших ответах о формате работы, уровне самостоятельности, реакции на стресс и предпочтениях в задачах. Мы анализировали не только навыки, но и условия, в которых вы сможете работать устойчиво.";
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

        $prompt .= "ПОЛНЫЙ ТРАНСКРИПТ ОТВЕТОВ:\n";
        foreach ($statistics['transcript'] as $item) {
            $prompt .= "В: {$item['question']} (Категория: {$item['category']})\n";
            $prompt .= "О: {$item['answer']}\n\n";
        }

        $prompt .= "ДЕТАЛЬНАЯ СТАТИСТИКА (для шкалированных вопросов):\n\n";
        // Выводим статистику по категориям только если есть баллы
        foreach ($statistics['category_stats'] as $categoryName => $stats) {
            if ($stats['max_score'] > 0) {
                $prompt .= "=== {$categoryName} ===\n";
                $prompt .= "Результат: {$stats['total_score']}/{$stats['max_score']} баллов ({$stats['percentage']}%)\n";
                $prompt .= "Средний балл: {$stats['average_score']}/4\n\n";
            }
        }

        if ($disabilityInfo) {
            $prompt .= "ВАЖНАЯ ИНФОРМАЦИЯ ОБ ОСОБЕННОСТЯХ ЗДОРОВЬЯ:\n{$disabilityInfo}\n";
            $prompt .= "Обязательно учти эту информацию при формировании рекомендаций!\n\n";
        }

        $prompt .= "На основе ОТВЕТОВ ПОЛЬЗОВАТЕЛЯ выше предоставь детальные рекомендации в следующем формате:\n\n";
        $prompt .= "АНАЛИЗ ЛИЧНОСТИ:\n";
        $prompt .= "[Используй ответы на вопросы о личности, мотивации и стиле работы]\n\n";
        
        $prompt .= "ПОДХОДЯЩИЕ ПРОФЕССИИ:\n";
        $prompt .= "[Предложи 5-7 профессий, которые идеально подходят под описанный профиль, интересы и ограничения]\n\n";
        
        $prompt .= "НАВЫКИ ДЛЯ РАЗВИТИЯ:\n";
        $prompt .= "[Какие навыки стоит подтянуть исходя из ответов]\n\n";
        
        $prompt .= "РЕКОМЕНДАЦИИ ПО ОБУЧЕНИЮ:\n";
        $prompt .= "[Конкретные форматы и направления обучения]\n\n";
        
        $prompt .= "ОСОБЕННОСТИ ТРУДОУСТРОЙСТВА:\n";
        $prompt .= "[Учти ответы про здоровье, доступность и условия работы]\n\n";
        
        $prompt .= "ПОДРОБНЫЙ ОТЧЕТ О ПРОЦЕССЕ АНАЛИЗА:\n";
        $prompt .= "[Обоснуй свои выводы, ссылаясь на конкретные ответы пользователя]\n\n";

        return $prompt;
    }

    /**
     * Детальный анализ ответов пользователя с реальной статистикой
     */
    private function analyzeAnswersStatistics($answers, $disabilityInfo): array
    {
        $categories = [];
        $transcript = [];
        $totalAnswers = count($answers);
        $overallScore = 0;
        $maxOverallScore = 0;

        // Группируем ответы по категориям и считаем статистику
        foreach ($answers as $questionId => $answer) {
            $question = CareerQuestion::find($questionId);
            if ($question) {
                $category = $question->category;
                
                // Собираем транскрипт
                $transcript[] = [
                    'question' => $question->question_text,
                    'category' => $category,
                    'answer' => $this->formatAnswer($answer, $question)
                ];

                if (!isset($categories[$category])) {
                    $categories[$category] = [];
                }

                // Если вопрос шкалированный (числовой ответ 0-4), добавляем в статистику
                if ($question->question_type === 'scale' && is_numeric($answer)) {
                    $categories[$category][] = [
                        'question' => $question->question_text,
                        'answer' => $answer,
                        'score' => (int)$answer
                    ];
                    $overallScore += (int)$answer;
                    $maxOverallScore += 4;
                }
            }
        }

        $categoryStats = [];
        
        // Анализируем каждую категорию
        foreach ($categories as $categoryName => $categoryQuestions) {
            $count = count($categoryQuestions);
            if ($count > 0) {
                $totalScore = array_sum(array_column($categoryQuestions, 'score'));
                $maxScore = $count * 4;
                $percentage = $maxScore > 0 ? round(($totalScore / $maxScore) * 100) : 0;
                
                $categoryStats[$categoryName] = [
                    'total_score' => $totalScore,
                    'max_score' => $maxScore,
                    'percentage' => $percentage,
                    'level' => $this->getLevelByPercentage($percentage),
                    'average_score' => round($totalScore / $count, 1),
                    'high_scores' => array_filter($categoryQuestions, fn($q) => $q['score'] >= 3),
                    'low_scores' => array_filter($categoryQuestions, fn($q) => $q['score'] <= 1)
                ];
            } else {
                 $categoryStats[$categoryName] = [
                    'total_score' => 0,
                    'max_score' => 0,
                    'percentage' => 0,
                    'level' => 'нет данных',
                    'average_score' => 0,
                    'high_scores' => [],
                    'low_scores' => []
                ];
            }
        }

        // Базовые склонности (можно расширить логику)
        $professionalInclinations = [];

        // Рассчитываем соответствие профессиям (упрощенно, так как теперь больше зависит от ИИ)
        $careerMatches = $this->calculateCareerMatches($categoryStats, $disabilityInfo);

        return [
            'category_stats' => $categoryStats,
            'transcript' => $transcript,
            'overall_percentage' => $maxOverallScore > 0 ? round(($overallScore / $maxOverallScore) * 100) : 0,
            'professional_inclinations' => $professionalInclinations,
            'career_matches' => $careerMatches,
            'total_questions' => $totalAnswers,
            'overall_score' => $overallScore
        ];
    }

    private function formatAnswer($answer, $question)
    {
        if (is_array($answer)) {
            return implode(', ', $answer);
        }

        if ($question->question_type === 'scale') {
            $map = ['0' => '0 — совсем не про меня', '1' => '1 — скорее нет', '2' => '2 — затрудняюсь', '3' => '3 — скорее да', '4' => '4 — полностью про меня'];
            return $map[$answer] ?? $answer;
        }

        if ($question->question_type === 'multiple_choice' && is_numeric($answer) && !empty($question->options)) {
             return $question->options[$answer] ?? $answer;
        }
        return $answer;
    }

    private function getLevelByPercentage($percentage)
    {
        if ($percentage >= 75) return 'очень высокий';
        if ($percentage >= 60) return 'высокий';
        if ($percentage >= 40) return 'средний';
        if ($percentage >= 25) return 'ниже среднего';
        return 'низкий';
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
                    case 'Рабочая среда':
                        $inclinations[] = "Определенные предпочтения к рабочей среде ({$stats['percentage']}%)";
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
                'requirements' => ['Навыки' => 60],
                'description' => 'Обработка и анализ информации, работа с компьютером',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'low'
            ],
            [
                'title' => 'Консультант по социальным вопросам',
                'base_match' => 0,
                'requirements' => ['Интересы' => 60],
                'description' => 'Помощь людям в решении социальных вопросов',
                'accessibility_friendly' => true,
                'remote_work_possible' => false,
                'physical_demands' => 'low',
                'social_interaction' => 'high'
            ],
            [
                'title' => 'Творческий работник (дизайн, рукоделие)',
                'base_match' => 0,
                'requirements' => ['Интересы' => 50, 'Навыки' => 45],
                'description' => 'Создание творческого контента, рукоделие, искусство',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'medium',
                'social_interaction' => 'low'
            ],
            [
                'title' => 'Администратор/Делопроизводитель',
                'base_match' => 0,
                'requirements' => ['Навыки' => 55, 'Рабочая среда' => 60],
                'description' => 'Ведение документооборота, административная работа',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'medium'
            ],
            [
                'title' => 'Преподаватель/Тренер',
                'base_match' => 0,
                'requirements' => ['Интересы' => 65, 'Навыки' => 50],
                'description' => 'Обучение и развитие других людей',
                'accessibility_friendly' => false,
                'remote_work_possible' => false,
                'physical_demands' => 'medium',
                'social_interaction' => 'high'
            ],
            [
                'title' => 'IT-поддержка/Техническая поддержка',
                'base_match' => 0,
                'requirements' => ['Навыки' => 65, 'Рабочая среда' => 50],
                'description' => 'Решение технических проблем, поддержка пользователей',
                'accessibility_friendly' => true,
                'remote_work_possible' => true,
                'physical_demands' => 'low',
                'social_interaction' => 'medium'
            ],
            [
                'title' => 'Переводчик/Редактор',
                'base_match' => 0,
                'requirements' => ['Навыки' => 70, 'Интересы' => 55],
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
    private function analyzeHealthForCareers($disabilityInfo, $categoryStats = null): array
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
        
        // Анализ низких оценок в категории "Инвалидность и доступность"
        if ($categoryStats && isset($categoryStats['Инвалидность и доступность'])) {
            $disabilityStats = $categoryStats['Инвалидность и доступность'];
            
            // Если есть низкие оценки (0-1), значит у пользователя есть трудности в этих областях
            foreach ($disabilityStats['low_scores'] as $question) {
                $text = mb_strtolower($question['question']);
                
                // "Мне несложно работать стоя..." (низкая оценка -> сложно)
                if (mb_strpos($text, 'стоя') !== false || mb_strpos($text, 'расстояния') !== false) {
                    $analysis['mobility_considerations'] = true;
                    $analysis['needs_low_physical'] = true;
                }
                
                // "поднимать" -> физическая нагрузка
                if (mb_strpos($text, 'поднимать') !== false) {
                    $analysis['needs_low_physical'] = true;
                }
                
                // "слух" -> слух
                if (mb_strpos($text, 'слух') !== false) {
                    $analysis['hearing_considerations'] = true;
                }
                
                // "зрение" -> зрение
                if (mb_strpos($text, 'зрение') !== false) {
                    $analysis['vision_considerations'] = true;
                }
                
                // "спокойный" -> стресс/когнитивные
                if (mb_strpos($text, 'спокойный') !== false || mb_strpos($text, 'тихая') !== false) {
                    $analysis['needs_flexible_schedule'] = true; // Или спокойная обстановка
                }

                // "дом" -> удаленка (низкая оценка -> "мне НЕ легче дома"? Нет, вопрос: "Мне легче работать из дома")
                // Если "легче из дома" = 4 (Высокая), значит нужна удаленка.
                // Если "легче из дома" = 0 (Низкая), значит НЕ обязательно.
                // Значит здесь нужно смотреть ВЫСОКИЕ оценки для позитивных утверждений о потребностях.
                // Вопросы в тесте сформулированы по-разному.
                // 1-5: "Мне несложно..." (способности). Низкий балл = проблема.
                // 6: "Мне нужен спокойный темп..." (потребность). Высокий балл = потребность.
                // 7: "Я лучше работаю..." (потребность). Высокий балл = потребность.
                // 8: "необходимо... перерывы" (потребность). Высокий балл = потребность.
                // 9: "поездки... не вызывают трудностей" (способность). Низкий балл = проблема.
                // 10: "легче работать из дома" (потребность). Высокий балл = потребность.
                // 11: "лучше... по сокращенному" (потребность). Высокий балл = потребность.
                // 12: "тихая... помогает" (потребность). Высокий балл = потребность.
            }
            
            // Анализ высоких оценок (потребности)
            foreach ($disabilityStats['high_scores'] as $question) {
                $text = mb_strtolower($question['question']);
                
                if (mb_strpos($text, 'дома') !== false) {
                    $analysis['needs_remote_work'] = true;
                }
                
                if (mb_strpos($text, 'спокойный') !== false || mb_strpos($text, 'тихая') !== false) {
                    $analysis['needs_flexible_schedule'] = true; // Предпочтение спокойной среды
                }
                
                if (mb_strpos($text, 'сокращённому') !== false || mb_strpos($text, 'перерывы') !== false) {
                    $analysis['needs_flexible_schedule'] = true;
                }
            }
        }
        
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
