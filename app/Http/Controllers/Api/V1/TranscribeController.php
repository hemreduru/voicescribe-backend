<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Transcription\TranscribeRequest;
use App\Services\Transcription\GroqTranscriber;
use App\Services\Transcription\TranscriptionException;
use Illuminate\Http\JsonResponse;

class TranscribeController extends Controller
{
    public function __construct(private readonly GroqTranscriber $transcriber) {}

    /**
     * @OA\Post(
     *     path="/api/v1/transcribe",
     *     tags={"Transcription"},
     *     summary="Transcribe one audio chunk (Groq whisper relay)",
     *     security={{"bearerAuth":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\MediaType(
     *             mediaType="multipart/form-data",
     *
     *             @OA\Schema(
     *                 required={"audio"},
     *
     *                 @OA\Property(property="audio", type="string", format="binary"),
     *                 @OA\Property(property="language", type="string", example="tr"),
     *                 @OA\Property(property="prompt", type="string", maxLength=500)
     *             )
     *         )
     *     ),
     *
     *     @OA\Response(response=200, description="Transcribed text in data.text"),
     *     @OA\Response(response=422, description="Validation failed"),
     *     @OA\Response(response=429, description="Rate limited (Retry-After may be set)"),
     *     @OA\Response(response=502, description="Upstream transcription failure"),
     *     @OA\Response(response=503, description="Transcription not configured")
     * )
     */
    public function __invoke(TranscribeRequest $request): JsonResponse
    {
        try {
            $text = $this->transcriber->transcribe(
                $request->file('audio'),
                (string) ($request->validated('language') ?? 'tr'),
                $request->validated('prompt'),
            );
        } catch (TranscriptionException $e) {
            $response = $this->errorResponse($e->getMessage(), $e->status);

            return $e->retryAfter !== null
                ? $response->header('Retry-After', $e->retryAfter)
                : $response;
        }

        return $this->successResponse(
            data: ['text' => $text],
            message: 'Audio transcribed successfully',
        );
    }
}
