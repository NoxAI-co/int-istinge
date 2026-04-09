<?php

namespace App\Http\Controllers\API\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\User;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Autenticar al usuario y proporcionarle un token.
     */
    public function login(Request $request)
    {
        $request->validate([
            'email_o_username' => 'required',
            'password' => 'required',
        ]);

        $loginField = filter_var($request->email_o_username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

        $user = User::where($loginField, $request->email_o_username)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'status' => 401,
                'message' => 'Credenciales incorrectas.',
            ], 401);
        }

        // Generar un nuevo token
        $token = $user->createToken('API Token')->plainTextToken;

        return response()->json([
            'status' => 200,
            'message' => 'Login exitoso.',
            'data' => [
                'user' => $user->only(['id', 'nombres', 'email', 'username', 'rol']),
                'access_token' => $token,
                'token_type' => 'Bearer',
            ],
        ], 200);
    }
}
