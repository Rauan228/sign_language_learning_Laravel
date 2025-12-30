<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'required|exists:courses,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string',
            'is_anonymous' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $review = Review::create([
                'user_id' => Auth::id(),
                'course_id' => $request->course_id,
                'rating' => $request->rating,
                'comment' => $request->comment,
                'is_anonymous' => $request->is_anonymous ?? false
            ]);

            // Load user relation for frontend display
            $review->load('user');

            // Update course rating
            $course = Course::find($request->course_id);
            if ($course) {
                $avgRating = Review::where('course_id', $course->id)->avg('rating');
                $course->update(['rating' => round($avgRating, 2)]);
            }

            return response()->json([
                'success' => true,
                'data' => $review,
                'message' => 'Review submitted successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Review submission error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error submitting review: ' . $e->getMessage()
            ], 500);
        }
    }
}
