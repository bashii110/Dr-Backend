<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;

class AppointmentController extends Controller
{
    /**
     * BOOK APPOINTMENT
     */
    public function book(Request $request)
    {
        $user = $request->user();

        if ($user->type !== 'patient') {
            return response()->json(['status' => 403, 'message' => 'Only patients can book appointments.'], 403);
        }

        $validator = Validator::make($request->all(), [
            'doctor_id'        => 'required|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'notes'            => 'nullable|string|max:500',
            'type'             => 'nullable|in:in_person,video',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $doctor = Doctor::find($request->doctor_id);

        if (!$doctor) {
            return response()->json(['status' => 404, 'message' => 'Doctor not found.'], 404);
        }

        $exists = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($exists) {
            return response()->json(['status' => 409, 'message' => 'Slot already booked.'], 409);
        }

        $appointment = Appointment::create([
            'patient_id'       => $user->id,
            'doctor_id'        => $doctor->id,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'notes'            => $request->notes,
            'type'             => $request->type ?? 'in_person',
            'status'           => 'pending',
            'fee'              => $doctor->consultation_fee ?? 0,
        ]);

        $doctor->increment('patients');

        try {
            Notification::send(
                $doctor->user,
                new \App\Notifications\NewAppointmentNotification($appointment)
            );
        } catch (\Exception $e) {
            Log::warning('Notification failed: ' . $e->getMessage());
        }

        return response()->json([
            'status'      => 201,
            'message'     => 'Appointment booked successfully!',
            'appointment' => $this->format($appointment->load(['doctor.user', 'patient'])),
        ], 201);
    }

    /**
     * GET APPOINTMENTS (PATIENT)
     * Flutter calls: GET /api/appointments
     * Flutter reads: res['status'], res['data']['data'] as List
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Appointment::with(['doctor.user', 'patient'])
            ->whereNotNull('id')
            ->whereNotNull('doctor_id')
            ->whereNotNull('patient_id');

        if ($user->type === 'patient') {
            $query->where('patient_id', $user->id);
        } else {
            $doctor = $user->doctor;

            if (!$doctor) {
                return response()->json([
                    'status' => 200,
                    'data'   => ['data' => []],
                ]);
            }

            $query->where('doctor_id', $doctor->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->get();

        return response()->json([
            'status' => 200,
            'data'   => [
                'data' => $appointments->map(fn($a) => $this->format($a))->values(),
            ],
        ]);
    }

    /**
     * DOCTOR APPOINTMENTS
     * Flutter calls: GET /api/doctor/appointments
     * Flutter reads: res['status'], res['data']['data'] as List
     */
    public function doctorAppointments(Request $request)
    {
        $user   = $request->user();
        $doctor = $user->doctor;

        if (!$doctor) {
            return response()->json([
                'status' => 200,
                'data'   => ['data' => []],
            ]);
        }

        $query = Appointment::with(['doctor.user', 'patient'])
            ->where('doctor_id', $doctor->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query
            ->orderByDesc('appointment_date')
            ->orderByDesc('appointment_time')
            ->get();

        return response()->json([
            'status' => 200,
            'data'   => [
                'data' => $appointments->map(fn($a) => $this->format($a))->values(),
            ],
        ]);
    }

    /**
     * SHOW SINGLE APPOINTMENT
     */
    public function show(Request $request, int $id)
    {
        $user        = $request->user();
        $appointment = Appointment::with(['doctor.user', 'patient'])->find($id);

        if (!$appointment) {
            return response()->json(['status' => 404, 'message' => 'Not found.'], 404);
        }

        // Only the patient or the assigned doctor may view
        $doctor = $user->doctor;
        $isOwner = ($user->type === 'patient' && $appointment->patient_id === $user->id)
            || ($user->type === 'doctor'  && $doctor && $appointment->doctor_id === $doctor->id);

        if (!$isOwner) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        return response()->json([
            'status' => 200,
            'data'   => $this->format($appointment),
        ]);
    }

    /**
     * CANCEL APPOINTMENT
     * Flutter calls: PATCH /api/appointments/{id}/cancel
     */
    public function cancel(Request $request, int $id)
    {
        $user        = $request->user();
        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['status' => 404, 'message' => 'Not found.'], 404);
        }

        if ($user->type === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status'  => 422,
                'message' => 'Only pending or confirmed appointments can be cancelled.',
            ], 422);
        }

        $appointment->update(['status' => 'cancelled']);

        return response()->json([
            'status'      => 200,
            'message'     => 'Appointment cancelled.',
            'appointment' => $this->format($appointment->load(['doctor.user', 'patient'])),
        ]);
    }

    /**
     * CONFIRM APPOINTMENT (doctor)
     */
    public function confirm(Request $request, int $id)
    {
        return $this->updateStatus(
            $request->merge(['status' => 'confirmed']),
            $id
        );
    }

    /**
     * COMPLETE APPOINTMENT (doctor)
     */
    public function complete(Request $request, int $id)
    {
        return $this->updateStatus(
            $request->merge(['status' => 'completed']),
            $id
        );
    }

    /**
     * UPDATE STATUS (doctor)
     * Flutter calls: PATCH /api/doctor/appointments/{id}/status
     */
    public function updateStatus(Request $request, int $id)
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

        $doctor      = $user->doctor;
        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $doctor->id)
            ->first();

        if (!$appointment) {
            return response()->json(['status' => 404, 'message' => 'Not found.'], 404);
        }

        $appointment->update(['status' => $request->status]);

        return response()->json([
            'status'      => 200,
            'message'     => 'Status updated.',
            'appointment' => $this->format($appointment->load(['doctor.user', 'patient'])),
        ]);
    }

    /**
     * RESCHEDULE APPOINTMENT
     */
    public function reschedule(Request $request, int $id)
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 422, 'errors' => $validator->errors()], 422);
        }

        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(['status' => 404, 'message' => 'Not found.'], 404);
        }

        if ($user->type === 'patient' && $appointment->patient_id !== $user->id) {
            return response()->json(['status' => 403, 'message' => 'Access denied.'], 403);
        }

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status'           => 'pending',
        ]);

        return response()->json([
            'status'      => 200,
            'message'     => 'Appointment rescheduled.',
            'appointment' => $this->format($appointment->load(['doctor.user', 'patient'])),
        ]);
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * Format an appointment into the shape Flutter's AppointmentModel.fromJson() expects.
     *
     * Flutter model keys required:
     *   id, patient_id, doctor_id,
     *   appointment_date, appointment_time,
     *   status, notes, consultation_fee,
     *   doctor { id, doc_id, category, consultation_fee,
     *            available_from, available_to, status,
     *            user { name } },
     *   patient { id, name }
     */
    private function format(Appointment $apt): array
    {
        $doctor  = $apt->doctor;
        $patient = $apt->patient;

        return [
            'id'               => $apt->id,
            'patient_id'       => $apt->patient_id,
            'doctor_id'        => $apt->doctor_id,
            'appointment_date' => $apt->appointment_date ?? '',
            'appointment_time' => $apt->appointment_time ?? '',
            'status'           => $apt->status ?? 'pending',
            'notes'            => $apt->notes ?? null,
            'consultation_fee' => (float) ($apt->fee ?? 0),

            'doctor' => $doctor ? [
                'id'               => $doctor->id,
                'doc_id'           => $doctor->doc_id,
                'category'         => $doctor->category ?? null,
                'consultation_fee' => (float) ($doctor->consultation_fee ?? 0),
                'available_from'   => $doctor->available_from ?? null,
                'available_to'     => $doctor->available_to ?? null,
                'status'           => $doctor->status ?? 'offline',
                'user'             => $doctor->user ? [
                    'name' => $doctor->user->name ?? '',
                ] : null,
            ] : null,

            'patient' => $patient ? [
                'id'   => $patient->id,
                'name' => $patient->name ?? '',
            ] : null,
        ];
    }
}