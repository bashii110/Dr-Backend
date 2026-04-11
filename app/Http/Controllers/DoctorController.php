<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Doctor;
use App\Models\Review;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Schema;
use Exception;

class DoctorController extends Controller
{
    /**
     * List doctors — shows pending + approved so newly registered doctors appear.
     */
    public function index(Request $request)
    {
        $query = Doctor::with('user')
            ->whereIn('status', ['approved', 'pending', 'available', 'busy']);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('user', fn($q) => $q->where('name', 'like', "%$search%"));
        }

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        $sort = $request->get('sort', 'rating');
        if ($sort === 'experience') {
            $query->orderByDesc('experience');
        } else {
            $query->orderByDesc('id');
        }

        $doctors = $query->paginate($request->get('per_page', 10));

        // Flutter PatientHomeScreen expects: res['data']['data'] as a list
        return response()->json([
            'status' => 200,
            'data'   => [
                'data'         => $doctors->map(fn($d) => $this->formatDoctor($d))->values(),
                'current_page' => $doctors->currentPage(),
                'last_page'    => $doctors->lastPage(),
                'total'        => $doctors->total(),
            ],
        ]);
    }

    /**
     * Get single doctor details
     */
    public function show(int $id)
    {
        $doctor = Doctor::with(['user', 'reviews'])->find($id);

        if (!$doctor) {
            return response()->json(['status' => 404, 'message' => 'Doctor not found.'], 404);
        }

        return response()->json([
            'status' => 200,
            'data'   => $this->formatDoctor($doctor, true),
        ]);
    }

    /**
     * Update doctor own profile — auto-creates the row if missing.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'doctor') {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'category'         => 'nullable|string|max:100',
            'experience'       => 'nullable|integer|min:0',
            'bio_data'         => 'nullable|string|max:2000',
            'fee'              => 'nullable|numeric|min:0',
            'consultation_fee' => 'nullable|numeric|min:0',
            'phone'            => 'nullable|string|max:20',
            'address'          => 'nullable|string|max:500',
            'hospital'         => 'nullable|string|max:200',
            'education'        => 'nullable|string|max:1000',
            'languages'        => 'nullable|array',
            'status'           => 'nullable|string|in:available,busy,offline,pending,approved',
            'available_from'   => 'nullable|string',
            'available_to'     => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        // Auto-create doctor profile row if it does not exist
        $doctor = Doctor::firstOrCreate(
            ['doc_id' => $user->id],
            ['status' => 'pending', 'experience' => 0, 'patients' => 0]
        );

        $updateData = [];
        foreach (['category', 'experience', 'bio_data', 'address', 'hospital', 'education', 'languages', 'status'] as $field) {
            if ($request->has($field)) {
                $updateData[$field] = $request->input($field);
            }
        }

        // Accept both 'fee' and 'consultation_fee'
        if ($request->has('consultation_fee')) {
            $updateData['fee'] = $request->consultation_fee;
        } elseif ($request->has('fee')) {
            $updateData['fee'] = $request->fee;
        }

        $columns = Schema::getColumnListing('doctors');
        if (in_array('available_from', $columns) && $request->has('available_from')) {
            $updateData['available_from'] = $request->available_from;
        }
        if (in_array('available_to', $columns) && $request->has('available_to')) {
            $updateData['available_to'] = $request->available_to;
        }

        if (!empty($updateData)) {
            $doctor->update($updateData);
        }

        if ($request->filled('phone')) {
            $user->update(['phone' => $request->phone]);
        }

        if ($request->hasFile('profile_photo')) {
            $path = $request->file('profile_photo')->store('profile_photos', 'public');
            $user->update(['profile_photo_path' => $path]);
        }

        return response()->json([
            'status'  => 200,
            'message' => 'Profile updated successfully.',
            'data'    => $this->formatDoctor($doctor->fresh(['user'])),
        ]);
    }

    /**
     * Get available time slots for a doctor on a date
     */
    public function availableSlots(Request $request, int $doctorId)
    {
        $validator = Validator::make($request->all(), [
            'date' => 'required|date|after_or_equal:today',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $doctor = Doctor::find($doctorId);
        if (!$doctor) {
            return response()->json(['status' => 404, 'message' => 'Doctor not found.'], 404);
        }

        $date      = $request->date;
        $dayOfWeek = date('N', strtotime($date));

        if (in_array($dayOfWeek, [6, 7])) {
            return response()->json([
                'status'  => 200,
                'slots'   => [],
                'message' => 'Doctor is not available on weekends.',
            ]);
        }

        $bookedSlots = \App\Models\Appointment::where('doctor_id', $doctorId)
            ->whereDate('appointment_date', $date)
            ->whereIn('status', ['pending', 'confirmed'])
            ->pluck('appointment_time')
            ->toArray();

        $allSlots = [];
        for ($hour = 9; $hour <= 17; $hour++) {
            $time       = sprintf('%02d:00', $hour);
            $allSlots[] = [
                'time'      => $time,
                'available' => !in_array($time, $bookedSlots),
                'label'     => date('g:i A', strtotime($time)),
            ];
        }

        return response()->json(['status' => 200, 'slots' => $allSlots, 'date' => $date]);
    }

    /**
     * Get all categories (public)
     */
    public function categories()
    {
        $categories = Doctor::whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->values();

        $withIcons = $categories->map(fn($cat) => [
            'name' => $cat,
            'icon' => $this->categoryIcon($cat),
        ]);

        // Flutter PatientHomeScreen reads res['data'] as a flat list of strings
        return response()->json([
            'status'     => 200,
            'data'       => $categories,
            'categories' => $withIcons,
        ]);
    }

    /**
     * Doctor dashboard stats
     */
    public function dashboard(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'doctor') {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $doctor = Doctor::firstOrCreate(
            ['doc_id' => $user->id],
            ['status' => 'pending', 'experience' => 0, 'patients' => 0]
        );

        $today = now()->toDateString();

        $totalAppointments   = \App\Models\Appointment::where('doctor_id', $doctor->id)->count();
        $todayAppointments   = \App\Models\Appointment::where('doctor_id', $doctor->id)->whereDate('appointment_date', $today)->count();
        $pendingAppointments = \App\Models\Appointment::where('doctor_id', $doctor->id)->where('status', 'pending')->count();
        $totalPatients       = \App\Models\Appointment::where('doctor_id', $doctor->id)->distinct('patient_id')->count('patient_id');
        $avgRating           = \App\Models\Review::where('doctor_id', $doctor->id)->avg('rating') ?? 0;
        $totalReviews        = \App\Models\Review::where('doctor_id', $doctor->id)->count();

        $recentAppointments = \App\Models\Appointment::with('patient')
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->take(5)
            ->get();

        return response()->json([
            'status' => 200,
            'stats'  => [
                'total_appointments'   => $totalAppointments,
                'today_appointments'   => $todayAppointments,
                'pending_appointments' => $pendingAppointments,
                'total_patients'       => $totalPatients,
                'avg_rating'           => round($avgRating, 1),
                'total_reviews'        => $totalReviews,
            ],
            'recent_appointments' => $recentAppointments,
        ]);
    }

    /**
     * Get doctor own appointments list
     */
    public function myAppointments(Request $request)
    {
        $user = $request->user();
        if ($user->type !== 'doctor') {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $doctor = Doctor::firstOrCreate(
            ['doc_id' => $user->id],
            ['status' => 'pending', 'experience' => 0, 'patients' => 0]
        );

        $query = \App\Models\Appointment::with(['patient'])
            ->where('doctor_id', $doctor->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 200,
            'data'   => [
                'data'         => $appointments->map(fn($a) => $this->formatAppointment($a, $doctor))->values(),
                'current_page' => $appointments->currentPage(),
                'last_page'    => $appointments->lastPage(),
                'total'        => $appointments->total(),
            ],
        ]);
    }

    /**
     * Doctor updates appointment status
     */
    public function updateAppointmentStatus(Request $request, int $id)
    {
        $user = $request->user();
        if ($user->type !== 'doctor') {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:confirmed,completed,cancelled',
        ]);
        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $doctor = Doctor::where('doc_id', $user->id)->first();
        if (!$doctor) {
            return response()->json(['status' => 404, 'message' => 'Doctor profile not found.'], 404);
        }

        $appointment = \App\Models\Appointment::where('id', $id)
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return response()->json(['status' => 404, 'message' => 'Appointment not found.'], 404);
        }

        $appointment->update(['status' => $request->status]);

        return response()->json(['status' => 200, 'message' => 'Appointment updated.']);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function formatDoctor(Doctor $doctor, bool $full = false): array
{
    $columns = Schema::getColumnListing('doctors');

    // Always recalculate fresh from reviews table
    $avgRating   = (float) round(Review::where('doctor_id', $doctor->id)->avg('rating') ?? 0, 1);
    $ratingCount = (int)   Review::where('doctor_id', $doctor->id)->count();

    $data = [
        'id'     => $doctor->id,
        'doc_id' => $doctor->doc_id,

        'user' => [
            'id'                 => $doctor->user->id ?? null,
            'name'               => $doctor->user->name ?? '',
            'email'              => $doctor->user->email ?? '',
            'phone'              => $doctor->user->phone ?? null,
            'profile_photo_path' => $doctor->user->profile_photo_path ?? null,
        ],

        'name'             => $doctor->user->name ?? '',
        'email'            => $doctor->user->email ?? '',
        'phone'            => $doctor->user->phone ?? null,
        'photo'            => $doctor->user->profile_photo_path
                                ? asset('storage/' . $doctor->user->profile_photo_path)
                                : null,
        'category'         => $doctor->category,
        'experience'       => (int)   ($doctor->experience ?? 0),
        'patients'         => (int)   ($doctor->patients   ?? 0),
        'rating'           => $avgRating,    // ✅ fresh from reviews table
        'rating_count'     => $ratingCount,  // ✅ fresh from reviews table
        'fee'              => (float) ($doctor->fee ?? 0),
        'consultation_fee' => (float) ($doctor->fee ?? 0),
        'hospital'         => $doctor->hospital  ?? null,
        'status'           => $doctor->status    ?? 'pending',
        'is_available'     => $doctor->is_available ?? true,
        'available_from'   => in_array('available_from', $columns) ? ($doctor->available_from ?? null) : null,
        'available_to'     => in_array('available_to',   $columns) ? ($doctor->available_to   ?? null) : null,
    ];

    if ($full) {
        $data['bio_data']  = $doctor->bio_data;
        $data['education'] = $doctor->education ?? null;
        $data['address']   = $doctor->address   ?? null;
        $data['languages'] = $doctor->languages ?? [];
        $data['reviews']   = $doctor->reviews   ?? [];
    }

    return $data;
}

    private function formatAppointment($apt, Doctor $doctor): array
    {
        return [
            'id'               => $apt->id,
            'patient_id'       => $apt->patient_id,
            'doctor_id'        => $apt->doctor_id,
            'status'           => $apt->status,
            'appointment_date' => $apt->appointment_date,
            'appointment_time' => $apt->appointment_time,
            'notes'            => $apt->notes,
            'consultation_fee' => (float) ($apt->fee ?? 0),
            'doctor' => [
                'id'       => $doctor->id,
                'doc_id'   => $doctor->doc_id,
                'user'     => ['name' => $doctor->user->name ?? '', 'email' => $doctor->user->email ?? ''],
                'name'     => $doctor->user->name ?? '',
                'category' => $doctor->category,
            ],
            'patient' => $apt->patient ? [
                'id'    => $apt->patient->id,
                'name'  => $apt->patient->name,
                'email' => $apt->patient->email,
                'phone' => $apt->patient->phone ?? null,
            ] : null,
            'created_at' => $apt->created_at,
        ];
    }

    private function categoryIcon(string $category): string
    {
        return match (strtolower($category)) {
            'cardiology'                  => 'heart_pulse',
            'dermatology'                 => 'hand',
            'neurology'                   => 'brain',
            'orthopedics', 'orthopaedics' => 'bone',
            'pediatrics',  'paediatrics'  => 'child',
            'gynecology',  'gynaecology'  => 'pregnant',
            'dental'                      => 'teeth',
            'respiratory', 'respirations' => 'lungs',
            'ophthalmology'               => 'eye',
            'psychiatry'                  => 'brain',
            default                       => 'stethoscope',
        };
    }
}