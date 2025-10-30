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

// Тестируем каждый шаг отдельно
echo "=== DEBUGGING getAIAnalysis ===\n\n";

try {
    echo "1. Testing analyzeAnswersStatistics...\n";
    $analyzeMethod = $reflection->getMethod('analyzeAnswersStatistics');
    $analyzeMethod->setAccessible(true);
    $statistics = $analyzeMethod->invoke($controller, $answers, $disabilityInfo);
    echo "   ✓ analyzeAnswersStatistics works\n";
    
    echo "2. Testing determineProfessionalInclinations...\n";
    $inclinationsMethod = $reflection->getMethod('determineProfessionalInclinations');
    $inclinationsMethod->setAccessible(true);
    $inclinations = $inclinationsMethod->invoke($controller, $statistics['category_stats']);
    echo "   ✓ determineProfessionalInclinations works\n";
    
    echo "3. Testing calculateCareerMatches...\n";
    $careerMethod = $reflection->getMethod('calculateCareerMatches');
    $careerMethod->setAccessible(true);
    $careerMatches = $careerMethod->invoke($controller, $statistics['category_stats'], $disabilityInfo);
    echo "   ✓ calculateCareerMatches works\n";
    
    echo "4. Testing generatePersonalityTraits...\n";
    $personalityMethod = $reflection->getMethod('generatePersonalityTraits');
    $personalityMethod->setAccessible(true);
    $personality = $personalityMethod->invoke($controller, $statistics['category_stats']);
    echo "   ✓ generatePersonalityTraits works\n";
    
    echo "5. Testing generateSkillsToImprove...\n";
    $skillsMethod = $reflection->getMethod('generateSkillsToImprove');
    $skillsMethod->setAccessible(true);
    $skills = $skillsMethod->invoke($controller, $statistics['category_stats']);
    echo "   ✓ generateSkillsToImprove works\n";
    
    echo "6. Testing generateLearningRecommendations...\n";
    $learningMethod = $reflection->getMethod('generateLearningRecommendations');
    $learningMethod->setAccessible(true);
    $learning = $learningMethod->invoke($controller, $statistics['category_stats']);
    echo "   ✓ generateLearningRecommendations works\n";
    
    echo "7. Testing generateAccessibilityConsiderations...\n";
    $accessibilityMethod = $reflection->getMethod('generateAccessibilityConsiderations');
    $accessibilityMethod->setAccessible(true);
    $accessibility = $accessibilityMethod->invoke($controller, $disabilityInfo);
    echo "   ✓ generateAccessibilityConsiderations works\n";
    
    echo "8. Testing generateAnalysisSummary...\n";
    $summaryMethod = $reflection->getMethod('generateAnalysisSummary');
    $summaryMethod->setAccessible(true);
    $summary = $summaryMethod->invoke($controller, $statistics, $inclinations);
    echo "   ✓ generateAnalysisSummary works\n";
    
    echo "9. Testing generateDetailedReport...\n";
    $reportMethod = $reflection->getMethod('generateDetailedReport');
    $reportMethod->setAccessible(true);
    $report = $reportMethod->invoke($controller, $statistics, $inclinations, $careerMatches, $disabilityInfo);
    echo "   ✓ generateDetailedReport works\n";
    
    echo "\n=== ALL METHODS WORK INDIVIDUALLY ===\n";
    echo "The issue might be in the main getAIAnalysis method structure.\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}