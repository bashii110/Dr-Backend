<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Exception;

class AuthController extends Controller
{
    public function register(Request $R)
    {
        try {

        \Log::info('Registration request data:', $R->all());

            $R->validate([
                'name' => 'required|string',
                'email' => 'required|email|unique:users',
                'password' => 'required|min:6',
                'type' => 'required|in:patient,doctor',
            ]);

            $user = new User();
            $user->name = $R->name;
            $user->email = $R->email;
            $user->password = Hash::make($R->password);
            $user->type = $R->type; // ADD THIS LINE - You were missing this!

              \Log::info('Saving user with type: ' . $R->type);

            $user->save();

            return response()->json([
                "status" => 200,
                "message" => "Registered Successfully! Please Login."
            ]);

        } catch (Exception $e) {
            \Log::error('Registration error: ' . $e->getMessage());
            return response()->json([
                "status" => 500,
                "message" => $e->getMessage()
            ]);
        }
    }

    public function login(Request $R)
    {
        $R->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $R->email)->first();

        if ($user && Hash::check($R->password, $user->password)) {
            $token = $user->createToken("Personal Access Token")->plainTextToken;

            return response()->json([
                "status" => 200,
                "token" => $token,
                "user" => $user,
                "message" => "Successfully Login!"
            ]);
        }

        return response()->json([
            "status" => 401,
            "message" => "Email or password is wrong!"
        ]);
    }
}