<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CareerJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CareerJobController extends Controller
{
    // Список вакансий с фильтрацией
    public function index(Request $request)
    {
        $query = CareerJob::where('status', 'active')->with('employer');

        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('location')) {
            $query->where('location', 'like', "%" . $request->input('location') . "%");
        }

        // Salary Filters
        if ($request->has('salary')) {
            $salary = $request->input('salary');
            $query->where(function($q) use ($salary) {
                $q->where('salary_from', '>=', $salary)
                  ->orWhere('salary_to', '>=', $salary);
            });
        }
        if ($request->has('only_with_salary') && $request->input('only_with_salary') == 'true') {
            $query->where(function($q) {
                $q->whereNotNull('salary_from')->orWhereNotNull('salary_to');
            });
        }

        // Enum Filters
        if ($request->has('experience') && $request->input('experience') !== 'all') {
            $query->where('experience', $request->input('experience'));
        }
        if ($request->has('schedule') && $request->input('schedule') !== 'all') {
            $query->where('schedule', $request->input('schedule'));
        }
        if ($request->has('employment') && $request->input('employment') !== 'all') {
            $query->where('employment', $request->input('employment'));
        }

        // Фильтр по доступности (JSON)
        if ($request->has('accessibility')) {
            $accessibility = $request->input('accessibility');
            // Если передан массив или строка
            if (is_array($accessibility)) {
                 foreach ($accessibility as $feature) {
                     $query->whereJsonContains('accessibility_features', $feature);
                 }
            } else {
                $query->whereJsonContains('accessibility_features', $accessibility);
            }
        }

        return response()->json($query->latest()->paginate(10));
    }

    // Создание вакансии (Работодатель)
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'location' => 'required|string',
            'salary_from' => 'nullable|integer',
            'salary_to' => 'nullable|integer',
            'currency' => 'string|in:RUR,USD,EUR',
            'experience' => 'required|in:noExperience,between1And3,between3And6,moreThan6',
            'schedule' => 'required|in:fullDay,shift,flexible,remote,flyInFlyOut',
            'employment' => 'required|in:full,part,project,volunteer,probation',
            'accessibility_features' => 'array',
        ]);

        $job = CareerJob::create([
            'employer_id' => Auth::id(),
            'title' => $request->title,
            'description' => $request->description,
            'company_name' => $request->company_name,
            'location' => $request->location,
            'salary_from' => $request->salary_from,
            'salary_to' => $request->salary_to,
            'currency' => $request->currency ?? 'RUR',
            'experience' => $request->experience,
            'schedule' => $request->schedule,
            'employment' => $request->employment,
            'accessibility_features' => $request->accessibility_features,
            'status' => 'active', // Или 'draft' по умолчанию
            'published_at' => now(),
        ]);

        return response()->json($job, 201);
    }

    public function show($id)
    {
        $job = CareerJob::with('employer')->findOrFail($id);
        return response()->json($job);
    }

    public function update(Request $request, $id)
    {
        $job = CareerJob::where('employer_id', Auth::id())->findOrFail($id);
        
        $job->update($request->all());
        
        return response()->json($job);
    }

    public function destroy($id)
    {
        $job = CareerJob::where('employer_id', Auth::id())->findOrFail($id);
        $job->delete();
        
        return response()->json(['message' => 'Vacancy deleted']);
    }

    // Вакансии текущего работодателя
    public function myJobs()
    {
        $jobs = CareerJob::where('employer_id', Auth::id())->latest()->get();
        return response()->json($jobs);
    }
}
