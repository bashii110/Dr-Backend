<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | BOOK APPOINTMENT
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $user = $request->user();

        // Prevent duplicate booking
        $exists = Appointment::where('doctor_id', $request->doctor_id)
            ->where('appointment_date', $request->appointment_date)
            ->where('appointment_time', $request->appointment_time)
            ->whereIn('status', ['pending', 'confirmed'])
            ->exists();

        if ($exists) {
            return response()->json([
                'status' => 409,
                'message' => 'This time slot is already booked.',
            ]);
        }

        $doctor = Doctor::findOrFail($request->doctor_id);

        $appointment = Appointment::create([
            'patient_id' => $user->id,
            'doctor_id' => $request->doctor_id,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status' => 'pending',
            'notes' => $request->notes,
            'fee' => $doctor->fee ?? 0,
        ]);

        return response()->json([
            'status' => 201,
            'message' => 'Appointment booked successfully.',
            'data' => $this->formatAppointment(
                $appointment->load(['doctor.user', 'patient'])
            ),
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | PATIENT APPOINTMENTS
    |--------------------------------------------------------------------------
    */
    public function myAppointments(Request $request)
    {
        $query = Appointment::with(['doctor.user', 'patient'])
            ->where('patient_id', $request->user()->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->paginate(50);

        $appointments->getCollection()->transform(function ($appointment) {
            return $this->formatAppointment($appointment);
        });

        return response()->json([
            'status' => 200,
            'data' => $appointments,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | CANCEL APPOINTMENT
    |--------------------------------------------------------------------------
    */
    public function cancel(Request $request, int $id)
    {
        $appointment = Appointment::where('id', $id)
            ->where('patient_id', $request->user()->id)
            ->firstOrFail();

        if (!in_array($appointment->status, ['pending', 'confirmed'])) {
            return response()->json([
                'status' => 400,
                'message' => 'Cannot cancel a ' . $appointment->status . ' appointment.',
            ]);
        }

        $appointment->update([
            'status' => 'cancelled'
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Appointment cancelled successfully.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | DOCTOR APPOINTMENTS
    |--------------------------------------------------------------------------
    */
    public function doctorAppointments(Request $request)
    {
        $user = $request->user();

        $doctor = Doctor::where('doc_id', $user->id)->firstOrFail();

        $query = Appointment::with(['patient', 'doctor.user'])
            ->where('doctor_id', $doctor->id)
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->paginate(50);

        $appointments->getCollection()->transform(function ($appointment) {
            return $this->formatAppointment($appointment);
        });

        return response()->json([
            'status' => 200,
            'data' => $appointments,
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS BY DOCTOR
    |--------------------------------------------------------------------------
    */
    public function updateStatus(Request $request, int $id)
    {
        $request->validate([
            'status' => 'required|in:confirmed,completed,cancelled',
        ]);

        $user = $request->user();

        $doctor = Doctor::where('doc_id', $user->id)->firstOrFail();

        $appointment = Appointment::where('id', $id)
            ->where('doctor_id', $doctor->id)
            ->firstOrFail();

        $appointment->update([
            'status' => $request->status
        ]);

        return response()->json([
            'status' => 200,
            'message' => 'Appointment status updated successfully.',
        ]);
    }


    /*
    |--------------------------------------------------------------------------
    | FORMAT RESPONSE
    |--------------------------------------------------------------------------
    */
    private function formatAppointment(Appointment $a): array
    {
        $doctorData = null;

        if ($a->doctor) {
            $doctorData = [
                'id' => $a->doctor->id,
                'doc_id' => $a->doctor->doc_id,
                'category' => $a->doctor->category,
                'consultation_fee' => $a->doctor->fee,
                'available_from' => $a->doctor->available_from,
                'available_to' => $a->doctor->available_to,
                'status' => $a->doctor->status,
                'name' => $a->doctor->user?->name,
            ];
        }

        return [
            'id' => $a->id,
            'patient_id' => $a->patient_id,
            'doctor_id' => $a->doctor_id,
            'appointment_date' => $a->appointment_date,
            'appointment_time' => $a->appointment_time,
            'status' => $a->status,
            'notes' => $a->notes,
            'consultation_fee' => $a->fee,
            'doctor' => $doctorData,
            'patient' => $a->patient
                ? [
                    'id' => $a->patient->id,
                    'name' => $a->patient->name,
                ]
                : null,
        ];
    }
}