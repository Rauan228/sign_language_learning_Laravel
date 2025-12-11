<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\LessonText;
use App\Models\Progress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class LessonController extends Controller
{
    /**
     * Parse VTT file and convert to subtitle format.
     */
    private function parseVttFile($vttPath)
    {
        $subtitles = [];
        $content = file_get_contents($vttPath);
        $lines = explode("\n", $content);
        
        $currentSubtitle = null;
        $id = 1;
        
        foreach ($lines as $line) {
            $line = trim($line);
            
            // Skip empty lines and WEBVTT header
            if (empty($line) || $line === 'WEBVTT') {
                if ($currentSubtitle && isset($currentSubtitle['text'])) {
                    $subtitles[] = $currentSubtitle;
                    $currentSubtitle = null;
                }
                continue;
            }
            
            // Check if line contains timestamp
            if (preg_match('/^(\d{2}:\d{2}:\d{2}\.\d{3}) --> (\d{2}:\d{2}:\d{2}\.\d{3})/', $line, $matches)) {
                $currentSubtitle = [
                    'id' => (string)$id++,
                    'start' => $this->timeToSeconds($matches[1]),
                    'end' => $this->timeToSeconds($matches[2]),
                    'text' => '',
                    'language' => 'ru',
                    'speaker' => null,
                    'position' => 'bottom'
                ];
            } elseif ($currentSubtitle && !is_numeric($line)) {
                // This is subtitle text
                $currentSubtitle['text'] .= ($currentSubtitle['text'] ? ' ' : '') . $line;
            }
        }
        
        // Add last subtitle if exists
        if ($currentSubtitle && isset($currentSubtitle['text'])) {
            $subtitles[] = $currentSubtitle;
        }
        
        return $subtitles;
    }
    
    /**
     * Convert VTT timestamp to seconds.
     */
    private function timeToSeconds($time)
    {
        $parts = explode(':', $time);
        $seconds = explode('.', $parts[2]);
        
        return ($parts[0] * 3600) + ($parts[1] * 60) + $seconds[0] + ($seconds[1] / 1000);
    }
    /**
     * Display the specified lesson with subtitles.
     */
    public function show(string $id)
    {
        $lesson = Lesson::with([
            'module.course.modules.lessons', 
            'primaryText',
            'media',
            'defaultVideo',
            'videos',
            'subtitles'
        ])->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        // Check if user has access to the course
        $user = auth('sanctum')->user();
        $userProgress = null;

        if ($user) {
            // Check if course is free or user has purchased it
            $course = $lesson->module->course;
            $hasAccess = $course->is_free || $user->purchases()
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->exists();
                
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Please purchase the course.'
                ], 403);
            }

            // Get user progress
            $userProgress = Progress::where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->first();
        }

        // Get video URL from lesson_media table or fallback to lesson.video_url
        $videoUrl = null;
        if ($lesson->defaultVideo) {
            $videoUrl = $lesson->defaultVideo->full_url;
        } elseif ($lesson->videos->isNotEmpty()) {
            $videoUrl = $lesson->videos->first()->full_url;
        } elseif ($lesson->video_url) {
            // Fallback to old video_url field
            $videoUrl = asset('storage/' . ltrim($lesson->video_url, '/'));
        }

        // Get subtitles from VTT files in lesson_media or generate from lesson_texts
        $subtitles = [];
        $fullText = '';
        
        // First, try to get VTT subtitles from lesson_media (prefer type 'subtitles', fallback to legacy 'document')
        $vttMedia = $lesson->media()
            ->whereIn('type', ['subtitles', 'document'])
            ->orderByRaw("CASE WHEN type='subtitles' THEN 0 ELSE 1 END")
            ->first();
        if ($vttMedia && isset($vttMedia->storage_path)) {
            $vttPath = storage_path('app/public/' . ltrim($vttMedia->storage_path, '/'));
            if (file_exists($vttPath)) {
                $subtitles = $this->parseVttFile($vttPath);
            }
        }

        // Fallback: if gesture_data contains subtitles_url, try parsing it
        if (empty($subtitles) && is_array($lesson->gesture_data) && isset($lesson->gesture_data['subtitles_url'])) {
            $vttPath = storage_path('app/public/' . ltrim($lesson->gesture_data['subtitles_url'], '/'));
            if (file_exists($vttPath)) {
                $subtitles = $this->parseVttFile($vttPath);
            }
        }
        
        // If no VTT subtitles found, generate from lesson_texts or content
        if (empty($subtitles)) {
            if ($lesson->primaryText) {
                $sentences = $lesson->primaryText->getSentences();
                $currentTime = 0;
                
                foreach ($sentences as $sentence) {
                    $duration = $sentence['duration'] ?? 5;
                    $subtitles[] = [
                        'id' => (string)$sentence['id'],
                        'start' => $currentTime,
                        'end' => $currentTime + $duration,
                        'text' => $sentence['text'],
                        'language' => 'ru',
                        'speaker' => null,
                        'position' => 'bottom'
                    ];
                    $currentTime += $duration;
                }
                
                $fullText = $lesson->primaryText->getFullText();
            } else {
                // Fallback to old method if no lesson_texts exist
                $baseTime = 0;
                $sentences = preg_split('/[.!?]+/', $lesson->content);
                foreach ($sentences as $index => $sentence) {
                    $sentence = trim($sentence);
                    if (!empty($sentence)) {
                        $subtitles[] = [
                            'id' => (string)($index + 1),
                            'start' => $baseTime,
                            'end' => $baseTime + 5,
                            'text' => $sentence . '.',
                            'language' => 'ru',
                            'speaker' => null,
                            'position' => 'bottom'
                        ];
                        $baseTime += 5;
                    }
                }
                $fullText = strip_tags($lesson->content);
            }

            // Add title as first subtitle
            array_unshift($subtitles, [
                'id' => '0',
                'start' => 0,
                'end' => 3,
                'text' => $lesson->title,
                'language' => 'ru',
                'speaker' => null,
                'position' => 'bottom'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'lesson' => $lesson,
                'user_progress' => $userProgress,
                'subtitles' => $subtitles,
                'fullText' => $fullText,
                'gesture_data' => $lesson->gesture_data,
                'video_url' => $videoUrl ?: '/placeholder-video.mp4'
            ]
        ]);
    }

    /**
     * Get subtitles for a specific lesson.
     */
    public function getSubtitles(string $id)
    {
        $lesson = Lesson::with('primaryText')->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        // Check if user has access to the course
        $user = auth()->user();
        if ($user) {
            // Check if course is free or user has purchased it
            $course = $lesson->module->course;
            $hasAccess = $course->is_free || $user->purchases()
                ->where('course_id', $course->id)
                ->where('status', 'completed')
                ->exists();
                
            if (!$hasAccess) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Please purchase the course.'
                ], 403);
            }
        }

        $subtitles = [];
        $fullText = '';
        
        if ($lesson->primaryText) {
            $sentences = $lesson->primaryText->getSentences();
            $currentTime = 0;
            
            foreach ($sentences as $sentence) {
                $duration = $sentence['duration'] ?? 5;
                $subtitles[] = [
                    'id' => (string)$sentence['id'],
                    'start' => $currentTime,
                    'end' => $currentTime + $duration,
                    'text' => $sentence['text'],
                    'language' => 'ru',
                    'speaker' => null,
                    'position' => 'bottom'
                ];
                $currentTime += $duration;
            }
            
            $fullText = $lesson->primaryText->getFullText();
        } else {
            // Fallback to old method if no lesson_texts exist
            $baseTime = 0;
            $sentences = preg_split('/[.!?]+/', $lesson->content);
            foreach ($sentences as $index => $sentence) {
                $sentence = trim($sentence);
                if (!empty($sentence)) {
                    $subtitles[] = [
                        'id' => (string)($index + 1),
                        'start' => $baseTime,
                        'end' => $baseTime + 5,
                        'text' => $sentence . '.',
                        'language' => 'ru',
                        'speaker' => null,
                        'position' => 'bottom'
                    ];
                    $baseTime += 5;
                }
            }
            $fullText = strip_tags($lesson->content);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subtitles' => $subtitles,
                'fullText' => $fullText
            ]
        ]);
    }

    /**
     * Mark lesson as completed.
     */
    public function markComplete(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $lesson = Lesson::with('module')->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        // Idempotency: if already completed, return early
        $existing = Progress::where('user_id', $user->id)
            ->where('lesson_id', $id)
            ->where('course_id', $lesson->module->course_id)
            ->first();
        if ($existing && ($existing->status === 'completed' || (bool)$existing->is_completed === true)) {
            return response()->json([
                'success' => true,
                'message' => 'Lesson already completed',
                'data' => $existing
            ]);
        }

        // Update or create progress record
        $progress = Progress::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $lesson->module->course_id,
                'lesson_id' => $lesson->id,
            ],
            [
                'status' => 'completed',
                'completion_percentage' => 100,
                'completed_at' => now(),
                'started_at' => now()
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Lesson marked as completed',
            'data' => $progress
        ]);
    }

    /**
     * Get lesson progress for current user.
     */
    public function getProgress(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $lesson = Lesson::with('module')->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        // Get progress record
        $progress = Progress::where('user_id', $user->id)
            ->where('lesson_id', $id)
            ->where('course_id', $lesson->module->course_id)
            ->first();

        if (!$progress) {
            // Create initial progress record
            $progress = Progress::create([
                'user_id' => $user->id,
                'course_id' => $lesson->module->course_id,
                'lesson_id' => $id,
                'status' => 'not_started',
                'completion_percentage' => 0,
                'started_at' => null,
                'completed_at' => null,
                'time_spent_minutes' => 0,
                'watched_duration' => 0,
                'is_completed' => false,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $progress->id,
                'lessonId' => (int)$id,
                'watchedDuration' => $progress->watched_duration ?? 0,
                'isCompleted' => $progress->is_completed ?? false,
                'completedAt' => $progress->completed_at ? $progress->completed_at->toISOString() : null,
                'lastWatchedAt' => $progress->updated_at->toISOString(),
                'progress_percentage' => $progress->completion_percentage,
                'time_spent' => $progress->time_spent_minutes * 60,
                'completed' => $progress->status === 'completed',
                'sessions' => [] // Mock sessions for now
            ]
        ]);
    }

    /**
     * Save lesson progress.
     */
    public function saveProgress(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $lesson = Lesson::with('module')->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'watchedDuration' => 'required|integer|min:0',
            'isCompleted' => 'boolean',
            'lastPositionSeconds' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        $watchedDuration = $request->input('watchedDuration', 0);
        $isCompleted = $request->input('isCompleted', false);
        $timeSpentMinutes = ceil($watchedDuration / 60);
        $lastPosition = $request->input('lastPositionSeconds', $watchedDuration);

        // Calculate percentage based on lesson duration
        $durationMinutes = $lesson->duration_minutes ?: 10; // Fallback to 10 minutes if not set
        $durationSeconds = $durationMinutes * 60;
        
        $completionPercentage = 0;
        if ($isCompleted) {
            $completionPercentage = 100;
        } else {
            // If watchedDuration is provided, calculate percentage relative to lesson duration
            if ($durationSeconds > 0) {
                $completionPercentage = min(100, ceil(($watchedDuration / $durationSeconds) * 100));
            }
        }

        // Short-circuit if no meaningful changes to reduce DB load
        $existing = Progress::where('user_id', $user->id)
            ->where('course_id', $lesson->module->course_id)
            ->where('lesson_id', $id)
            ->first();
        if ($existing && !$isCompleted) {
            $previousWatched = (int)($existing->watched_duration ?? 0);
            $minStep = 5; // seconds granularity
            if ($watchedDuration < $previousWatched + $minStep) {
                return response()->json([
                    'success' => true,
                    'message' => 'No changes',
                    'data' => [
                        'id' => $existing->id,
                        'lessonId' => (int)$id,
                        'watchedDuration' => $existing->watched_duration ?? 0,
                        'isCompleted' => (bool)($existing->is_completed ?? false),
                        'completedAt' => $existing->completed_at ? $existing->completed_at->toISOString() : null,
                        'lastWatchedAt' => $existing->updated_at->toISOString(),
                        'progress_percentage' => $existing->completion_percentage,
                        'time_spent' => $existing->time_spent_minutes * 60,
                        'completed' => $existing->status === 'completed'
                    ]
                ]);
            }
        }

        // Update or create progress record
        $progress = Progress::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $lesson->module->course_id,
                'lesson_id' => $id,
            ],
            [
                'status' => $isCompleted ? 'completed' : 'in_progress',
                'completion_percentage' => $completionPercentage,
                'time_spent_minutes' => $timeSpentMinutes,
                'watched_duration' => $watchedDuration,
                'last_position_seconds' => $lastPosition,
                'is_completed' => $isCompleted,
                'started_at' => now(),
                'completed_at' => $isCompleted ? now() : null,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Progress saved successfully',
            'data' => [
                'id' => $progress->id,
                'lessonId' => (int)$id,
                'watchedDuration' => $progress->watched_duration,
                'lastPositionSeconds' => $progress->last_position_seconds,
                'isCompleted' => $progress->is_completed,
                'completedAt' => $progress->completed_at ? $progress->completed_at->toISOString() : null,
                'lastWatchedAt' => $progress->updated_at->toISOString(),
                'progress_percentage' => $progress->completion_percentage,
                'time_spent' => $progress->time_spent_minutes * 60,
                'completed' => $progress->status === 'completed'
            ]
        ]);
    }

    /**
     * Save watching session.
     */
    public function saveSession(Request $request, string $id)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        $lesson = Lesson::with('module')->find($id);
        
        if (!$lesson) {
            return response()->json([
                'success' => false,
                'message' => 'Lesson not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'startTime' => 'required|integer|min:0',
            'endTime' => 'required|integer|min:0',
            'duration' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $validator->errors()
            ], 422);
        }

        // For now, just update the progress with session info
        $duration = $request->input('duration', 0);
        $timeSpentMinutes = ceil($duration / 60);

        $progress = Progress::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $lesson->module->course_id,
                'lesson_id' => $id,
            ],
            [
                'status' => 'in_progress',
                'time_spent_minutes' => $timeSpentMinutes,
                'started_at' => now(),
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Session saved successfully',
            'data' => [
                'sessionId' => uniqid(),
                'lessonId' => (int)$id,
                'startTime' => $request->input('startTime'),
                'endTime' => $request->input('endTime'),
                'duration' => $duration
            ]
        ]);
    }
}
