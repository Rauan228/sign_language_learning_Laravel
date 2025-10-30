<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Module;
use App\Models\Lesson;
use App\Models\LessonMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    // Course Management
    public function getCourses()
    {
        $courses = Course::with(['modules.lessons'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $courses
        ]);
    }

    public function createCourse(Request $request)
    {
        // Debug: log incoming request data
        \Log::info('Course creation request data:', $request->all());
        
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'difficulty_level' => 'required|in:beginner,intermediate,advanced',
            'duration_hours' => 'required|integer|min:1',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'tags' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            \Log::error('Course creation validation failed:', $validator->errors()->toArray());
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $courseData = $request->only([
            'title', 'description', 'difficulty_level', 'duration_hours', 'price', 'is_free', 'tags'
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('courses/images', $imageName, 'public');
            $courseData['image'] = $imagePath;
        }

        $courseData['instructor_id'] = auth()->id();
        $courseData['is_published'] = false;

        $course = Course::create($courseData);

        return response()->json([
            'success' => true,
            'data' => $course->load(['modules.lessons'])
        ], 201);
    }

    public function updateCourse(Request $request, $id)
    {
        $course = Course::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'difficulty_level' => 'sometimes|required|in:beginner,intermediate,advanced',
            'price' => 'nullable|numeric|min:0',
            'is_free' => 'boolean',
            'is_published' => 'boolean',
            'tags' => 'nullable|array',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $courseData = $request->only([
            'title', 'description', 'difficulty_level', 'price', 'is_free', 'is_published', 'tags'
        ]);

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($course->image) {
                Storage::disk('public')->delete($course->image);
            }

            $image = $request->file('image');
            $imageName = time() . '_' . Str::random(10) . '.' . $image->getClientOriginalExtension();
            $imagePath = $image->storeAs('courses/images', $imageName, 'public');
            $courseData['image'] = $imagePath;
        }

        $course->update($courseData);

        return response()->json([
            'success' => true,
            'data' => $course->load(['modules.lessons'])
        ]);
    }

    public function deleteCourse($id)
    {
        $course = Course::findOrFail($id);

        // Delete course image
        if ($course->image) {
            Storage::disk('public')->delete($course->image);
        }

        // Delete all related media files
        foreach ($course->modules as $module) {
            foreach ($module->lessons as $lesson) {
                $this->deleteLessonMedia($lesson);
            }
        }

        $course->delete();

        return response()->json([
            'success' => true,
            'message' => 'Course deleted successfully'
        ]);
    }

    public function toggleCoursePublication($id)
    {
        $course = Course::findOrFail($id);
        $course->is_published = !$course->is_published;
        $course->save();

        return response()->json([
            'success' => true,
            'data' => $course->load(['modules.lessons'])
        ]);
    }

    // Module Management
    public function getModules(Request $request)
    {
        $query = Module::with(['course', 'lessons']);
        
        if ($request->has('course_id')) {
            $query->where('course_id', $request->course_id);
        }
        
        $modules = $query->orderBy('order_index')->get();
        
        return response()->json([
            'success' => true,
            'data' => $modules
        ]);
    }

    public function createModule(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'course_id' => 'required|exists:courses,id',
            'order_index' => 'nullable|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $moduleData = $request->only(['title', 'description', 'course_id']);
        
        // Auto-set order_index if not provided
        if (!$request->has('order_index')) {
            $maxOrder = Module::where('course_id', $request->course_id)->max('order_index');
            $moduleData['order_index'] = ($maxOrder ?? -1) + 1;
        } else {
            $moduleData['order_index'] = $request->order_index;
        }

        $moduleData['is_published'] = false;

        $module = Module::create($moduleData);

        return response()->json([
            'success' => true,
            'data' => $module->load('lessons')
        ], 201);
    }

    public function updateModule(Request $request, $id)
    {
        $module = Module::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'order_index' => 'sometimes|integer|min:0',
            'is_published' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $module->update($request->only(['title', 'description', 'order_index', 'is_published']));

        return response()->json([
            'success' => true,
            'data' => $module->load('lessons')
        ]);
    }

    public function deleteModule($id)
    {
        $module = Module::findOrFail($id);

        // Delete all lesson media files
        foreach ($module->lessons as $lesson) {
            $this->deleteLessonMedia($lesson);
        }

        $module->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }

    public function toggleModulePublication($id)
    {
        $module = Module::findOrFail($id);
        $module->is_published = !$module->is_published;
        $module->save();

        return response()->json([
            'success' => true,
            'data' => $module->load('lessons')
        ]);
    }

    // Lesson Management
    public function getLessons(Request $request)
    {
        $query = Lesson::with(['module.course']);
        
        if ($request->has('module_id')) {
            $query->where('module_id', $request->module_id);
        }
        
        $lessons = $query->orderBy('order_index')->get();
        
        return response()->json([
            'success' => true,
            'data' => $lessons
        ]);
    }

    public function createLesson(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'module_id' => 'required|exists:modules,id',
            'type' => 'required|in:video,gesture,text,quiz',
            'content' => 'nullable|string',
            'order_index' => 'nullable|integer|min:0',
            'video' => 'nullable|mimes:mp4,avi,mov,wmv|max:102400', // 100MB max
            'gesture_model' => 'nullable|mimes:glb,gltf|max:51200', // 50MB max
            'subtitles' => 'nullable|mimes:vtt,srt|max:1024' // 1MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $lessonData = $request->only(['title', 'description', 'module_id', 'type', 'content']);
        
        // Auto-set order_index if not provided
        if (!$request->has('order_index')) {
            $maxOrder = Lesson::where('module_id', $request->module_id)->max('order_index');
            $lessonData['order_index'] = ($maxOrder ?? -1) + 1;
        } else {
            $lessonData['order_index'] = $request->order_index;
        }

        $lesson = Lesson::create($lessonData);

        // Handle video upload
        if ($request->hasFile('video')) {
            $video = $request->file('video');
            $videoName = time() . '_' . Str::random(10) . '.' . $video->getClientOriginalExtension();
            $videoPath = $video->storeAs('lessons/videos', $videoName, 'public');
            
            $lesson->media()->create([
                'type' => 'video',
                'provider' => 'local',
                'storage_path' => $videoPath,
                'url' => asset('storage/' . $videoPath),
                'mime' => $video->getMimeType(),
                'is_default' => true
            ]);
        }

        // Handle 3D model upload
        if ($request->hasFile('gesture_model')) {
            $model = $request->file('gesture_model');
            $modelName = time() . '_' . Str::random(10) . '.' . $model->getClientOriginalExtension();
            $modelPath = $model->storeAs('lessons/models', $modelName, 'public');
            
            $lesson->media()->create([
                'type' => 'sign3d',
                'provider' => 'local',
                'storage_path' => $modelPath,
                'url' => asset('storage/' . $modelPath),
                'mime' => $model->getMimeType(),
                'is_default' => true
            ]);
        }

        // Handle subtitles upload
        if ($request->hasFile('subtitles')) {
            $subtitles = $request->file('subtitles');
            $subtitlesName = time() . '_' . Str::random(10) . '.' . $subtitles->getClientOriginalExtension();
            $subtitlesPath = $subtitles->storeAs('lessons/subtitles', $subtitlesName, 'public');
            
            $lesson->media()->create([
                'type' => 'subtitles',
                'provider' => 'local',
                'storage_path' => $subtitlesPath,
                'url' => asset('storage/' . $subtitlesPath),
                'mime' => $subtitles->getMimeType(),
                'captions' => ['vtt', 'srt'],
                'is_default' => true
            ]);
        }

        $lessonData['is_published'] = false;

        return response()->json([
            'success' => true,
            'data' => $lesson->load('media')
        ], 201);
    }

    public function updateLesson(Request $request, $id)
    {
        $lesson = Lesson::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'type' => 'sometimes|required|in:video,gesture,text,quiz',
            'content' => 'nullable|string',
            'order_index' => 'sometimes|integer|min:0',
            'is_published' => 'boolean',
            'video' => 'nullable|mimes:mp4,avi,mov,wmv|max:102400',
            'gesture_model' => 'nullable|mimes:glb,gltf|max:51200',
            'subtitles' => 'nullable|mimes:vtt,srt|max:1024'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $lessonData = $request->only(['title', 'description', 'type', 'content', 'order_index', 'is_published']);
        $gestureData = $lesson->gesture_data ?? [];

        // Handle video upload
        if ($request->hasFile('video')) {
            // Delete old video
            if ($lesson->video_url) {
                Storage::disk('public')->delete($lesson->video_url);
            }

            $video = $request->file('video');
            $videoName = time() . '_' . Str::random(10) . '.' . $video->getClientOriginalExtension();
            $videoPath = $video->storeAs('lessons/videos', $videoName, 'public');
            $lessonData['video_url'] = $videoPath;
        }

        // Handle 3D model upload
        if ($request->hasFile('gesture_model')) {
            // Delete old model
            if (isset($gestureData['model_url'])) {
                Storage::disk('public')->delete($gestureData['model_url']);
            }

            $model = $request->file('gesture_model');
            $modelName = time() . '_' . Str::random(10) . '.' . $model->getClientOriginalExtension();
            $modelPath = $model->storeAs('lessons/models', $modelName, 'public');
            $gestureData['model_url'] = $modelPath;
        }

        // Handle subtitles upload
        if ($request->hasFile('subtitles')) {
            // Delete old subtitles
            if (isset($gestureData['subtitles_url'])) {
                Storage::disk('public')->delete($gestureData['subtitles_url']);
            }

            $subtitles = $request->file('subtitles');
            $subtitlesName = time() . '_' . Str::random(10) . '.' . $subtitles->getClientOriginalExtension();
            $subtitlesPath = $subtitles->storeAs('lessons/subtitles', $subtitlesName, 'public');
            $gestureData['subtitles_url'] = $subtitlesPath;

            // Also persist in lesson_media with correct type so LessonController can resolve
            $existingSubtitles = $lesson->media()->where('type', 'subtitles')->first();
            $mediaPayload = [
                'type' => 'subtitles',
                'provider' => 'local',
                'storage_path' => $subtitlesPath,
                'url' => asset('storage/' . $subtitlesPath),
                'mime' => $subtitles->getMimeType(),
                'captions' => ['vtt', 'srt'],
                'is_default' => true
            ];
            if ($existingSubtitles) {
                $existingSubtitles->update($mediaPayload);
            } else {
                $lesson->media()->create($mediaPayload);
            }
        }

        if (!empty($gestureData)) {
            $lessonData['gesture_data'] = $gestureData;
        }

        $lesson->update($lessonData);

        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    public function deleteLesson($id)
    {
        $lesson = Lesson::findOrFail($id);
        
        $this->deleteLessonMedia($lesson);
        $lesson->delete();

        return response()->json([
            'success' => true,
            'message' => 'Lesson deleted successfully'
        ]);
    }

    public function toggleLessonPublication($id)
    {
        $lesson = Lesson::findOrFail($id);
        $lesson->is_published = !$lesson->is_published;
        $lesson->save();

        return response()->json([
            'success' => true,
            'data' => $lesson
        ]);
    }

    // Reorder items
    public function reorderModules(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'modules' => 'required|array',
            'modules.*.id' => 'required|exists:modules,id',
            'modules.*.order_index' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->modules as $moduleData) {
            Module::where('id', $moduleData['id'])
                ->update(['order_index' => $moduleData['order_index']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Modules reordered successfully'
        ]);
    }

    public function reorderLessons(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'lessons' => 'required|array',
            'lessons.*.id' => 'required|exists:lessons,id',
            'lessons.*.order_index' => 'required|integer|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        foreach ($request->lessons as $lessonData) {
            Lesson::where('id', $lessonData['id'])
                ->update(['order_index' => $lessonData['order_index']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Lessons reordered successfully'
        ]);
    }

    // Helper method to delete lesson media files
    private function deleteLessonMedia($lesson)
    {
        // Delete video
        if ($lesson->video_url) {
            Storage::disk('public')->delete($lesson->video_url);
        }

        // Delete gesture data files
        if ($lesson->gesture_data) {
            $gestureData = $lesson->gesture_data;
            if (isset($gestureData['model_url'])) {
                Storage::disk('public')->delete($gestureData['model_url']);
            }
            if (isset($gestureData['subtitles_url'])) {
                Storage::disk('public')->delete($gestureData['subtitles_url']);
            }
        }
    }

    // Statistics
    public function getStats()
    {
        $totalUsers = \App\Models\User::count();
        $totalCourses = Course::count();
        $totalModules = Module::count();
        $totalLessons = Lesson::count();
        
        // Calculate active students (users who have enrolled in at least one course)
        $activeStudents = \App\Models\Purchase::where('status', 'completed')
            ->distinct('user_id')
            ->count('user_id');
        
        // Calculate completed lessons count across all users
        $completedLessons = \App\Models\Progress::where('is_completed', true)->count();
        
        // Calculate average rating from courses
        $averageRating = Course::where('rating', '>', 0)->avg('rating') ?? 0;
        
        // Calculate total revenue from purchases
        $totalRevenue = \App\Models\Purchase::where('status', 'completed')->sum('amount');
        
        // Calculate completion rate (percentage of lessons completed vs total possible)
        $totalPossibleLessons = $activeStudents * $totalLessons;
        $completionRate = $totalPossibleLessons > 0 ? round(($completedLessons / $totalPossibleLessons) * 100, 1) : 0;
        
        // Get recent activity data (last 7 days)
        $recentUsers = \App\Models\User::where('created_at', '>=', now()->subDays(7))->count();
        $recentEnrollments = \App\Models\Purchase::where('status', 'completed')
            ->where('purchased_at', '>=', now()->subDays(7))
            ->count();
        $recentCompletedLessons = \App\Models\Progress::where('is_completed', true)
            ->where('completed_at', '>=', now()->subDays(7))
            ->count();
        
        // Get popular courses data
        $popularCourses = Course::select('id', 'title', 'enrollment_count', 'rating')
            ->where('enrollment_count', '>', 0)
            ->orderBy('enrollment_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($course) {
                $revenue = \App\Models\Purchase::where('course_id', $course->id)
                    ->where('status', 'completed')
                    ->sum('amount');
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'enrollments' => $course->enrollment_count,
                    'rating' => $course->rating ?? 0,
                    'revenue' => $revenue
                ];
            });
        
        // Get user activity data (last 7 days)
        $userActivity = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $activeUsersCount = \App\Models\Progress::whereDate('updated_at', $date->toDateString())
                ->distinct('user_id')
                ->count('user_id');
            $newRegistrations = \App\Models\User::whereDate('created_at', $date->toDateString())->count();
            
            $userActivity[] = [
                'date' => $date->format('Y-m-d'),
                'activeUsers' => $activeUsersCount,
                'newRegistrations' => $newRegistrations
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'totalUsers' => $totalUsers,
                'totalCourses' => $totalCourses,
                'totalModules' => $totalModules,
                'totalLessons' => $totalLessons,
                'activeStudents' => $activeStudents,
                'completedLessons' => $completedLessons,
                'totalRevenue' => $totalRevenue,
                'averageRating' => round($averageRating, 1),
                'completionRate' => $completionRate,
                'recentActivity' => [
                    'newUsers' => $recentUsers,
                    'newEnrollments' => $recentEnrollments,
                    'completedLessons' => $recentCompletedLessons
                ],
                'popularCourses' => $popularCourses,
                'userActivity' => $userActivity
            ]
        ]);
    }

    // Media Upload Methods
    public function uploadVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'video' => 'required|mimes:mp4,avi,mov,wmv|max:102400' // 100MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $video = $request->file('video');
        $videoName = time() . '_' . Str::random(10) . '.' . $video->getClientOriginalExtension();
        $videoPath = $video->storeAs('lessons/videos', $videoName, 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::url($videoPath)
        ]);
    }

    public function upload3DModel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'model' => 'required|mimes:glb,gltf|max:51200' // 50MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $model = $request->file('model');
        $modelName = time() . '_' . Str::random(10) . '.' . $model->getClientOriginalExtension();
        $modelPath = $model->storeAs('lessons/models', $modelName, 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::url($modelPath)
        ]);
    }

    public function uploadSubtitles(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'subtitles' => 'required|mimes:vtt,srt|max:1024' // 1MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $subtitles = $request->file('subtitles');
        $subtitlesName = time() . '_' . Str::random(10) . '.' . $subtitles->getClientOriginalExtension();
        $subtitlesPath = $subtitles->storeAs('lessons/subtitles', $subtitlesName, 'public');

        return response()->json([
            'success' => true,
            'url' => Storage::url($subtitlesPath)
        ]);
    }
}