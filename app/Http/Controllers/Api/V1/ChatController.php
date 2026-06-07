<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Chat\SendChatMessageRequest;
use App\Http\Resources\ChatMessageResource;
use App\Http\Resources\ChatSessionResource;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Chat\ChatPrompt;
use App\Services\Chat\TranscriptRetriever;
use App\Services\Summarization\Exceptions\SummarizationException;
use App\Services\Summarization\LlmProviderFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Chat over the user's own transcriptions (RAG). Sessions + messages persist in
 * the DB; answers cite the transcripts they draw from.
 */
class ChatController extends Controller
{
    public function __construct(
        private readonly LlmProviderFactory $factory,
        private readonly TranscriptRetriever $retriever,
    ) {}

    /** List the user's chat sessions (most recent first). */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $sessions = ChatSession::query()
            ->where('user_id', $user->id)
            ->withCount('messages')
            ->orderByRaw('COALESCE(last_message_at, created_at) DESC')
            ->get();

        return $this->successResponse(
            data: ChatSessionResource::collection($sessions),
            message: 'Chat sessions fetched successfully',
        );
    }

    /** A single session with its full message history. */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $session = ChatSession::query()
            ->where('user_id', $user->id)
            ->with('messages')
            ->find($id);

        if ($session === null) {
            return $this->notFoundResponse('Chat session not found.');
        }

        return $this->successResponse(
            data: new ChatSessionResource($session),
            message: 'Chat session fetched successfully',
        );
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $session = ChatSession::query()->where('user_id', $user->id)->find($id);
        if ($session === null) {
            return $this->notFoundResponse('Chat session not found.');
        }
        $session->delete();

        return $this->successResponse(message: 'Chat session deleted successfully');
    }

    /**
     * Send a message; retrieves relevant transcripts, asks the LLM, persists both
     * the user and assistant messages, and returns them with source attribution.
     */
    public function sendMessage(SendChatMessageRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if ($user === null) {
            return $this->unauthorizedResponse();
        }

        $validated = $request->validated();
        $question = trim((string) $validated['content']);

        $session = $this->resolveSession($user, $validated['session_id'] ?? null, $question);
        if ($session === null) {
            return $this->notFoundResponse('Chat session not found.');
        }

        // Persist the user's message first so it's never lost.
        $userMessage = $session->messages()->create([
            'role' => ChatMessage::ROLE_USER,
            'content' => $question,
        ]);

        $sources = $this->retriever->retrieve($user->id, $question);
        $history = $session->messages()
            ->where('id', '<', $userMessage->id)
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->reverse()
            ->map(fn (ChatMessage $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        try {
            $provider = $this->factory->make();
            $answer = $provider->complete(
                ChatPrompt::system(),
                ChatPrompt::buildUserPrompt($sources, $history, $question),
            );
        } catch (SummarizationException $e) {
            report($e);

            return $this->errorResponse(
                'Yapay zekâ şu an yanıt veremedi. Lütfen biraz sonra tekrar deneyin.',
                502,
            );
        }

        $assistantMessage = $session->messages()->create([
            'role' => ChatMessage::ROLE_ASSISTANT,
            'content' => $answer,
            'sources' => array_map(static fn ($s) => [
                'transcript_id' => $s['transcript_id'],
                'title' => $s['title'],
                'date' => $s['date'],
            ], $sources),
        ]);

        $session->forceFill(['last_message_at' => now()])->save();

        return $this->successResponse(
            data: [
                'session' => new ChatSessionResource($session->loadCount('messages')),
                'user_message' => new ChatMessageResource($userMessage),
                'assistant_message' => new ChatMessageResource($assistantMessage),
            ],
            message: 'Message sent successfully',
        );
    }

    private function resolveSession(User $user, ?int $sessionId, string $question): ?ChatSession
    {
        if ($sessionId !== null) {
            return ChatSession::query()->where('user_id', $user->id)->find($sessionId);
        }

        return ChatSession::create([
            'user_id' => $user->id,
            'title' => Str::limit($question, 48),
            'last_message_at' => now(),
        ]);
    }
}
