<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\ModuleController;
use App\Http\Controllers\Api\LessonController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\CareerTestController;

// API v1 routes
Route::prefix('v1')->group(function () {
    // CORS preflight for any route under /api/v1
    // Explicitly echo Origin and requested headers to satisfy browser checks
    Route::options('/{any}', function (\Illuminate\Http\Request $request) {
        $origin = $request->headers->get('Origin', '*');
        $reqHeaders = $request->headers->get('Access-Control-Request-Headers', '*');

        return response()->noContent(204)->withHeaders([
            'Access-Control-Allow-Origin' => $origin,
            'Access-Control-Allow-Methods' => 'GET, POST, PUT, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers' => $reqHeaders,
        ]);
    })->where('any', '.*');

    // Public routes
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);
    });
    
    // Public course routes (for browsing)
    Route::get('/courses', [CourseController::class, 'index']);
    Route::get('/courses/enrolled', [CourseController::class, 'enrolled'])->middleware('auth:sanctum');
    Route::get('/courses/simple-test', function() {
        return response()->json(['success' => true, 'message' => 'Simple test works']);
    })->middleware('auth:sanctum');
    Route::get('/courses/{id}', [CourseController::class, 'show']);
    Route::get('/modules/{id}', [ModuleController::class, 'show']);
    
    // Public lesson routes (for testing)
    Route::get('/lessons/{id}', [LessonController::class, 'show']);
    Route::get('/lessons/{id}/subtitles', [LessonController::class, 'getSubtitles']);
    
    // Public career test routes
    Route::get('/career-tests', [CareerTestController::class, 'index']);
    Route::get('/career-tests/{id}', [CareerTestController::class, 'show']);
    
    // Storage file routes with CORS support
    Route::get('/storage/lessons/models/{filename}', function ($filename) {
        $path = storage_path('app/public/lessons/models/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => 'model/gltf-binary',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    });
    
    Route::get('/storage/lessons/subtitles/{filename}', function ($filename) {
        $path = storage_path('app/public/lessons/subtitles/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => 'text/vtt',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    });
    
    Route::get('/storage/lessons/videos/{filename}', function ($filename) {
        $path = storage_path('app/public/lessons/videos/' . $filename);
        if (!file_exists($path)) {
            abort(404);
        }
        return response()->file($path, [
            'Content-Type' => 'video/mp4',
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, OPTIONS',
            'Access-Control-Allow-Headers' => '*',
        ]);
    });
    
    // Protected routes
    Route::middleware('auth:sanctum')->group(function () {
        // Auth routes
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
        });
        
        Route::get('/courses/enrolled-test', function(Request $request) {
            return response()->json(['success' => true, 'message' => 'Test route works', 'user' => $request->user()->id]);
        });
        
        // Career test routes (protected)
        Route::prefix('career-tests')->group(function () {
            Route::post('/{id}/submit', [CareerTestController::class, 'submitTest']);
            Route::get('/results', [CareerTestController::class, 'getUserResults']);
            Route::get('/results/{id}', [CareerTestController::class, 'getResult']);
        });
        
        // Course enrollment (free)
Route::post('/courses/{id}/enroll', [CourseController::class, 'enroll']);
        
        // Course access and progress
        Route::get('/courses/{id}/access', [CourseController::class, 'checkAccess']);
        Route::get('/courses/{id}/progress', [CourseController::class, 'getProgress']);
        Route::post('/courses/{courseId}/lessons/{lessonId}/complete', [CourseController::class, 'markLessonComplete']);
        
        // Course management (admin only - you can add middleware later)
        Route::post('/courses', [CourseController::class, 'store']);
        Route::get('/courses/{id}', [CourseController::class, 'show']);
        Route::put('/courses/{id}', [CourseController::class, 'update']);
        Route::delete('/courses/{id}', [CourseController::class, 'destroy']);
        
        // Module routes
        Route::apiResource('modules', ModuleController::class);
        
        // Lesson routes
        Route::apiResource('lessons', LessonController::class);
        Route::get('/lessons/{id}/subtitles', [LessonController::class, 'getSubtitles']);
        Route::post('/lessons/{id}/complete', [LessonController::class, 'markComplete']);
        Route::get('/lessons/{id}/progress', [LessonController::class, 'getProgress']);
        Route::post('/lessons/{id}/progress', [LessonController::class, 'saveProgress']);
        Route::post('/lessons/{id}/progress/sessions', [LessonController::class, 'saveSession']);
        
        // Progress routes
        Route::get('/progress', [ProgressController::class, 'index']);
        Route::get('/progress/summary', [ProgressController::class, 'summary']);
        Route::post('/progress', [ProgressController::class, 'store']);
        Route::get('/progress/{id}', [ProgressController::class, 'show']);
        Route::put('/progress/{id}', [ProgressController::class, 'update']);
        Route::delete('/progress/{id}', [ProgressController::class, 'destroy']);
        Route::get('/courses/{courseId}/stats', [ProgressController::class, 'courseStats']);
        
        // Admin routes for course management
        Route::prefix('admin')->group(function () {
            // Course management
            Route::get('/courses', [AdminController::class, 'getCourses']);
            Route::post('/courses', [AdminController::class, 'createCourse']);
            Route::put('/courses/{id}', [AdminController::class, 'updateCourse']);
            Route::delete('/courses/{id}', [AdminController::class, 'deleteCourse']);
            Route::patch('/courses/{id}/toggle-publication', [AdminController::class, 'toggleCoursePublication']);
            
            // Module management
            Route::get('/modules', [AdminController::class, 'getModules']);
            Route::post('/modules', [AdminController::class, 'createModule']);
            Route::put('/modules/{id}', [AdminController::class, 'updateModule']);
            Route::delete('/modules/{id}', [AdminController::class, 'deleteModule']);
            Route::post('/modules/reorder', [AdminController::class, 'reorderModules']);
            Route::patch('/modules/{id}/toggle-publication', [AdminController::class, 'toggleModulePublication']);
            
            // Lesson management
            Route::get('/lessons', [AdminController::class, 'getLessons']);
            Route::post('/lessons', [AdminController::class, 'createLesson']);
            Route::put('/lessons/{id}', [AdminController::class, 'updateLesson']);
            Route::delete('/lessons/{id}', [AdminController::class, 'deleteLesson']);
            Route::post('/lessons/reorder', [AdminController::class, 'reorderLessons']);
            Route::patch('/lessons/{id}/toggle-publication', [AdminController::class, 'toggleLessonPublication']);
            
            // Statistics
            Route::get('/stats', [AdminController::class, 'getStats']);
            
            // Media upload
            Route::post('/upload/video', [AdminController::class, 'uploadVideo']);
            Route::post('/upload/3d-model', [AdminController::class, 'upload3DModel']);
            Route::post('/upload/subtitles', [AdminController::class, 'uploadSubtitles']);
        });
        
        // Gesture recognition endpoint (will be implemented later)
        Route::post('/gesture/recognize', function (Request $request) {
            return response()->json([
                'success' => true,
                'message' => 'Gesture recognition endpoint - to be implemented',
                'data' => null
            ]);
        });
    });
});
