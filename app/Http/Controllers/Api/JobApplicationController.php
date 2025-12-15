<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\CareerJob;
use App\Models\Resume;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class JobApplicationController extends Controller
{
    // Откликнуться на вакансию
    public function store(Request $request)
    {
        $request->validate([
            'job_id' => 'required|exists:career_jobs,id',
            'cover_letter' => 'nullable|string',
        ]);

        // Проверка наличия резюме
        $resume = Resume::where('user_id', Auth::id())->first();
        if (!$resume) {
            return response()->json(['message' => 'Please create a resume first'], 400);
        }

        // Проверка на дубликат
        $exists = JobApplication::where('job_id', $request->job_id)
            ->where('user_id', Auth::id())
            ->exists();

        if ($exists) {
            return response()->json(['message' => 'You have already applied for this job'], 400);
        }

        $application = JobApplication::create([
            'job_id' => $request->job_id,
            'user_id' => Auth::id(),
            'resume_id' => $resume->id,
            'cover_letter' => $request->cover_letter,
            'status' => 'applied',
        ]);

        return response()->json($application, 201);
    }

    // Просмотр откликов на мои вакансии (для Работодателя)
    public function index(Request $request)
    {
        $jobId = $request->input('job_id');
        
        // Проверяем, что вакансия принадлежит текущему пользователю
        $job = CareerJob::where('id', $jobId)->where('employer_id', Auth::id())->firstOrFail();

        $applications = JobApplication::where('job_id', $jobId)
            ->with(['user', 'resume'])
            ->latest()
            ->get();

        return response()->json($applications);
    }

    // Мои отклики (для Соискателя)
    public function myApplications()
    {
        $applications = JobApplication::where('user_id', Auth::id())
            ->with(['job', 'job.employer'])
            ->latest()
            ->get();

        return response()->json($applications);
    }

    // Обновление статуса (Работодатель)
    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:applied,viewed,interview,rejected,hired',
        ]);

        $application = JobApplication::findOrFail($id);
        
        // Проверка прав (вакансия должна принадлежать пользователю)
        $job = CareerJob::where('id', $application->job_id)->where('employer_id', Auth::id())->first();
        
        if (!$job) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $application->update(['status' => $request->status]);

        return response()->json($application);
    }
}
