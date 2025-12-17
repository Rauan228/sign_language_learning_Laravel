<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectBooking;
use App\Models\ConnectService;
use App\Models\ConnectSchedule;
use Illuminate\Http\Request;

class ConnectBookingController extends Controller
{
    // My bookings
    public function index()
    {
        $bookings = ConnectBooking::with(['professional.user:id,name', 'service', 'schedule'])
            ->where('user_id', auth()->id())
            ->latest()
            ->get();

        return response()->json($bookings);
    }

    // Create booking
    public function store(Request $request)
    {
        $request->validate([
            'professional_id' => 'required|exists:connect_professionals,id',
            'service_id' => 'required|exists:connect_services,id',
            'schedule_id' => 'nullable|exists:connect_schedules,id', // Required for private sessions usually
            'is_anonymous' => 'boolean',
        ]);

        // Logic to check availability would go here
        if ($request->schedule_id) {
            $schedule = ConnectSchedule::findOrFail($request->schedule_id);
            if ($schedule->is_booked) {
                return response()->json(['message' => 'Slot already booked'], 409);
            }
            // Mark as booked
            $schedule->update(['is_booked' => true]);
        }

        $booking = ConnectBooking::create([
            'user_id' => auth()->id(),
            'professional_id' => $request->professional_id,
            'service_id' => $request->service_id,
            'schedule_id' => $request->schedule_id,
            'status' => 'confirmed', // Auto-confirm for now
            'is_anonymous' => $request->boolean('is_anonymous'),
            'notes' => $request->notes,
        ]);

        return response()->json($booking, 201);
    }
}
