<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    /**
     * Handle customer support chat messages.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function chat(Request $request)
    {
        $validated = $request->validate([
            'message' => 'required|string|max:1000',
            'history' => 'sometimes|array',
            'history.*.role' => 'required_with:history|string|in:system,user,assistant',
            'history.*.content' => 'required_with:history|string',
            'temperature' => 'sometimes|numeric|min:0|max:2',
            'debug' => 'sometimes|boolean',
        ]);

        $apiKey = config('services.openai.api_key');
        $model = config('services.openai.model');
        $baseUrl = rtrim(config('services.openai.base_url', 'https://api.openai.com'), '/');
        $systemPrompt = config('services.openai.system_prompt');
        $mock = (bool) config('services.openai.mock');
        $provider = config('services.openai.provider', 'openai');
        $ollamaBaseUrl = rtrim(config('services.openai.ollama.base_url', 'http://localhost:11434'), '/');
        $ollamaModel = config('services.openai.ollama.model', 'llama3');

        if (!$model) {
            return response()->json([
                'message' => 'OpenAI is not configured. Please set OPENAI_MODEL.'
            ], 500);
        }

        $messages = [];
        if (!empty($systemPrompt)) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        if (!empty($validated['history'])) {
            foreach ($validated['history'] as $item) {
                $messages[] = [
                    'role' => $item['role'],
                    'content' => $item['content'],
                ];
            }
        }

        $messages[] = [
            'role' => 'user',
            'content' => $validated['message'],
        ];

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (isset($validated['temperature'])) {
            $payload['temperature'] = $validated['temperature'];
        }

        if ($mock || $provider === 'mock') {
            return response()->json([
                'user_message' => $validated['message'],
                'reply' => 'This is a mock reply for local testing.',
                'usage' => null,
                'id' => 'mock',
            ], 200);
        }

        if ($provider === 'ollama') {
            $payload = [
                'model' => $ollamaModel,
                'messages' => $messages,
                'stream' => false,
            ];

            if (isset($validated['temperature'])) {
                $payload['options'] = [
                    'temperature' => $validated['temperature'],
                ];
            }

            try {
                $response = Http::asJson()
                    ->timeout(30)
                    ->post("{$ollamaBaseUrl}/api/chat", $payload);
            } catch (\Throwable $e) {
                return response()->json([
                    'message' => 'Ollama request error.',
                    'error' => $e->getMessage(),
                    'debug' => !empty($validated['debug']) ? [
                        'request' => $payload,
                    ] : null,
                ], 500);
            }

            $data = $response->json();

            if ($response->failed()) {
                return response()->json([
                    'message' => 'Ollama request failed.',
                    'details' => $data,
                    'debug' => !empty($validated['debug']) ? [
                        'status' => $response->status(),
                        'request' => $payload,
                    ] : null,
                ], 400);
            }

            $reply = data_get($data, 'message.content');

            return response()->json([
                'user_message' => $validated['message'],
                'reply' => $reply,
                'usage' => null,
                'id' => data_get($data, 'id', 'ollama'),
                'debug' => !empty($validated['debug']) ? [
                    'status' => $response->status(),
                    'request' => $payload,
                    'raw' => $data,
                ] : null,
            ], 200);
        }

        if (!$apiKey) {
            return response()->json([
                'message' => 'OpenAI is not configured. Please set OPENAI_API_KEY.'
            ], 500);
        }

        try {
            $response = Http::withToken($apiKey)
                ->asJson()
                ->timeout(30)
                ->post("{$baseUrl}/v1/chat/completions", $payload);
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'OpenAI request error.',
                'error' => $e->getMessage(),
                'debug' => !empty($validated['debug']) ? [
                    'request' => $payload,
                ] : null,
            ], 500);
        }

        $data = $response->json();

        if ($response->failed()) {
            return response()->json([
                'message' => 'OpenAI request failed.',
                'details' => $data,
                'debug' => !empty($validated['debug']) ? [
                    'status' => $response->status(),
                    'request' => $payload,
                ] : null,
            ], 400);
        }

        $reply = data_get($data, 'choices.0.message.content');

        return response()->json([
            'user_message' => $validated['message'],
            'reply' => $reply,
            'usage' => $data['usage'] ?? null,
            'id' => $data['id'] ?? null,
            'debug' => !empty($validated['debug']) ? [
                'status' => $response->status(),
                'request' => $payload,
                'raw' => $data,
            ] : null,
        ], 200);
    }
}
