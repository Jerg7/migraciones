<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Validator;

use OpenApi\Attributes as OA;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y generar token
     * 
     * @param Request $request
     */
    #[OA\Post(
        path: "/api/login",
        operationId: "login",
        tags: ["Auth"],
        summary: "Iniciar sesión",
        description: "Devuelve un token de acceso"
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ["email", "password"],
            properties: [
                new OA\Property(property: "email", type: "string", format: "email", example: "user@example.com"),
                new OA\Property(property: "password", type: "string", format: "password", example: "secret"),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: "Inicio de sesión exitoso",
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: "status", type: "string", example: "success"),
                new OA\Property(property: "message", type: "string", example: "Inicio de sesión exitoso."),
                new OA\Property(property: "token", type: "string", example: "1|AbCdEf123456..."),
                new OA\Property(property: "user", type: "object")
            ]
        )
    )]
    #[OA\Response(response: 401, description: "Credenciales inválidas")]
    #[OA\Response(response: 422, description: "Error de validación")]
    public function login(Request $request)
    {
        $credentials = Validator::make($request->all(), [
                'email' => 'required|email',
                'password' => 'required',
            ],
            [
                'required' => 'El :attribute es obligatorio.',
                'email' => 'El :attribute debe ser un correo electrónico válido.',
            ]
        );

        if ($credentials->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error de validación',
                'errors' => $credentials->errors()
            ], 422);
        }

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Eliminamos tokens anteriores para limpieza
            // $user->tokens()->delete();
            
            // Creamos un nuevo token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()->json([
                'status' => 'success',
                'message' => 'Inicio de sesión exitoso.',
                'token' => $token,
                'user' => $user
            ], 200);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Las credenciales proporcionadas no son correctas.',
        ], 401);
    }
}
