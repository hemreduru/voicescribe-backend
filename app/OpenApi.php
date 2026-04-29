<?php

namespace App;

/**
 * @OA\Info(
 *     title="VoiceScribe Backend API",
 *     version="1.0.0",
 *     description="Supabase auth proxy + local-first sync API for VoiceScribe mobile."
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="VoiceScribe API server"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="bearerAuth",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="JWT"
 * )
 */
final class OpenApi
{
}

