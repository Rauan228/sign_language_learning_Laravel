<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectProfessional;
use Illuminate\Http\Request;

class ConnectProfessionalController extends Controller
{
    // List professionals
    public function index(Request $request)
    {
        $query = ConnectProfessional::with('user:id,name,avatar');

        if ($request->has('specialization')) {
            $query->where('specialization', $request->specialization);
        }

        if ($request->has('min_price')) {
            $query->where('price_per_hour', '>=', $request->min_price);
        }

        if ($request->has('max_price')) {
            $query->where('price_per_hour', '<=', $request->max_price);
        }
        
        if ($request->has('language')) {
            // Since languages is JSON, we can't easily query with standard WHERE in all DBs, 
            // but for MySQL 5.7+ we can use JSON_CONTAINS or LIKE
            $lang = $request->language;
            $query->where('languages', 'like', "%\"$lang\"%");
        }

        $professionals = $query->paginate(20);

        return response()->json($professionals);
    }

    // Show professional details
    public function show($id)
    {
        $professional = ConnectProfessional::with(['user:id,name,avatar', 'services', 'reviews.user:id,name,avatar'])
            ->findOrFail($id);

        return response()->json($professional);
    }

    // Register as a professional (Simple version)
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'specialization' => 'required|string',
            'bio' => 'nullable|string',
            'languages' => 'required|array',
            'price_per_hour' => 'required|numeric',
        ]);

        $professional = ConnectProfessional::updateOrCreate(
            ['user_id' => auth()->id()],
            [
                'title' => $request->title,
                'specialization' => $request->specialization,
                'bio' => $request->bio,
                'languages' => $request->input('languages'),
                'price_per_hour' => $request->price_per_hour,
            ]
        );

        return response()->json($professional);
    }
}
