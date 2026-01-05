<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;

class AuthController extends Controller
{
    /**
     * @OA\Post(
     *      path="/api/login",
     *      operationId="login",
     *      tags={"Auth"},
     *      summary="Iniciar sesión",
     *      description="Devuelve un token de acceso",
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={"email","password"},
     *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *              @OA\Property(property="password", type="string", format="password", example="secret")
     *          )
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Inicio de sesión exitoso",
     *          @OA\JsonContent(
     *              @OA\Property(property="status", type="string", example="success"),
     *              @OA\Property(property="message", type="string", example="Inicio de sesión exitoso."),
     *              @OA\Property(property="token", type="string", example="1|AbCdEf123456..."),
     *              @OA\Property(property="user", type="object")
     *          )
     *      ),
     *      @OA\Response(response=401, description="Credenciales inválidas"),
     *      @OA\Response(response=422, description="Error de validación")
     * )
     *
     * Iniciar sesión y generar token
     * 
     * @param Request $request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            
            // Eliminamos tokens anteriores para limpieza (opcional)
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
