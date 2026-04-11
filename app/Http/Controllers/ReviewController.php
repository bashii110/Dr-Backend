<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // ── GET /api/doctors/{id}/reviews ─────────────────────────────────────
    public function doctorReviews(int $id)
    {
        $reviews = Review::with('patient')
            ->where('doctor_id', $id)
            ->latest()
            ->get()
            ->map(fn($r) => [
                'id'           => $r->id,
                'patient_id'   => $r->patient_id,
                'doctor_id'    => $r->doctor_id,
                'rating'       => (float) $r->rating,
                'comment'      => $r->comment,
                'patient_name' => $r->patient?->name ?? 'Anonymous',
                'created_at'   => $r->created_at?->diffForHumans() ?? '',
            ]);

        return response()->json([
            'status' => 200,
            'data'   => ['data' => $reviews],
        ]);
    }

    // ── POST /api/reviews ─────────────────────────────────────────────────
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'rating'    => 'required|numeric|min:1|max:5',
            'comment'   => 'nullable|string|max:1000',
        ]);

        $user = $request->user();

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

        $review = Review::updateOrCreate(
            ['patient_id' => $user->id, 'doctor_id' => $request->doctor_id],
            ['rating' => $request->rating, 'comment' => $request->comment]
        );

        // Recalculate and update the doctor's rating column
        $doctor = Doctor::find($request->doctor_id);
        if ($doctor) {
            $avg   = Review::where('doctor_id', $doctor->id)->avg('rating') ?? 0;
            $count = Review::where('doctor_id', $doctor->id)->count();
            $doctor->update([
                'rating'       => round($avg, 1),
                'rating_count' => $count,
            ]);
        }

        return response()->json([
            'status'  => 201,
            'message' => 'Review submitted.',
            'data'    => $review,
        ]);
    }
}