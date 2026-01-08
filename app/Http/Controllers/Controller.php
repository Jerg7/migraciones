<?php

namespace App\Http\Controllers;

use OpenApi\Attributes as OA;

#[OA\Info(
    version: "1.0.0",
    title: "Migraciones API",
    description: "Documentación de la API de Migraciones"
)]
#[OA\Contact(email: "joseangelrabel99@gmail.com")]
#[OA\License(name: "Apache 2.0", url: "http://www.apache.org/licenses/LICENSE-2.0.html")]
#[OA\Server(url: "http://localhost:8000", description: "API Server")]
#[OA\SecurityScheme(
    securityScheme: "bearerAuth",
    type: "http",
    scheme: "bearer",
    bearerFormat: "JWT",
    description: "Ingrese el token generado en el login"
)]
class Controller
{
    #[OA\Get(
        path: "/api/check",
        tags: ["Health"],
        summary: "Check API Status"
    )]
    #[OA\Response(response: 200, description: "API is healthy")]
    public function checkStatus()
    {
        return response()->json(['status' => 'ok']);
    }
}
