<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // ── POST /api/reviews ─────────────────────────────────────────────────
    // Auth (patient): submit review for a doctor

    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'rating'    => 'required|numeric|min:1|max:5',
            'comment'   => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

        // Check patient has a completed appointment with this doctor
        $hasCompleted = \App\Models\Appointment::where('patient_id', $user->id)
            ->where('doctor_id', $request->doctor_id)
            ->where('status', 'completed')
            ->exists();

        if (!$hasCompleted) {
            return response()->json([
                'status'  => 403,
                'message' => 'You can only review doctors after a completed appointment.',
            ]);
        }

        // Upsert (one review per patient per doctor)
        $review = Review::updateOrCreate(
            ['patient_id' => $user->id, 'doctor_id' => $request->doctor_id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        return response()->json([
            'status'  => 201,
            'message' => 'Review submitted.',
            'data'    => $review,
        ]);
    }
}