<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ResumeController extends Controller
{
    // Получить резюме текущего пользователя
    public function myResume()
    {
        $resume = Resume::with('user')->where('user_id', Auth::id())->first();
        return response()->json($resume);
    }

    // Создать или обновить резюме
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'about' => 'nullable|string',
            'skills' => 'nullable|array',
            'experience' => 'nullable|array',
            'education' => 'nullable|array',
            'accessibility_needs' => 'nullable|array',
            'video_cv_url' => 'nullable|string',
        ]);

        $resume = Resume::updateOrCreate(
            ['user_id' => Auth::id()],
            [
                'title' => $request->title,
                'about' => $request->about,
                'skills' => $request->skills ?? [],
                'experience' => $request->experience ?? [],
                'education' => $request->education ?? [],
                'video_cv_url' => $request->video_cv_url,
                'accessibility_needs' => $request->accessibility_needs ?? [],
                'is_public' => $request->input('is_public', true),
            ]
        );

        return response()->json($resume);
    }

    public function show($id)
    {
        $resume = Resume::where('is_public', true)->with('user')->findOrFail($id);
        return response()->json($resume);
    }
}
