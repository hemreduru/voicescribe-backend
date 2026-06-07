<?php

namespace App\Services\Summarization;

/**
 * Canonical meeting-minutes prompt + JSON schema shared by every LLM provider.
 *
 * The mobile app mirrors {@see self::system()} in
 * lib/domain/services/meeting_minutes_prompt.dart so the on-device model and the
 * cloud model emit the SAME MeetingSummary JSON contract (schema_version 1).
 */
class MeetingMinutesPrompt
{
    public const SCHEMA_VERSION = 1;

    /**
     * Executive-summary sentence budget per requested length.
     */
    private const SENTENCE_BUDGET = [
        'short' => '2-3',
        'medium' => '3-5',
        'long' => '5-7',
    ];

    /**
     * Build the system instruction that drives structured meeting minutes.
     */
    public static function system(string $length = 'medium', string $locale = 'tr'): string
    {
        $budget = self::SENTENCE_BUDGET[$length] ?? self::SENTENCE_BUDGET['medium'];
        $language = self::languageName($locale);

        return <<<PROMPT
        You are an expert meeting-minutes (toplantı tutanağı) writer. You are given a
        raw, possibly noisy speech-to-text transcript of a meeting. Produce a faithful,
        well-structured set of minutes as a SINGLE JSON object that matches the schema
        below. Output ONLY the JSON — no markdown, no code fences, no commentary.

        WRITING RULES
        - Write ALL output (every title, sentence, label and value) in {$language} — the
          user's app language — REGARDLESS of the transcript's language. If the transcript
          is in another language (or mixes languages), translate the content into
          {$language}. Keep proper names, product names and numbers as spoken.
        - Use a neutral, third-person, past-tense reporting tone ("tartışıldı,
          kararlaştırıldı, görevlendirildi"). Never add your own opinion or evaluation.
        - Separate DECISIONS from DISCUSSION. A decision is a settled outcome stated in
          one indisputable sentence ("X projesi Eylül'e ertelendi.").
        - An ACTION ITEM is only an action item if it has an owner AND a task. Include a
          due date when stated; otherwise leave due_date null. Distinguish an
          instruction the chair/boss gave (action item) from a mere opinion (discussion).
        - In agenda_items, for each topic capture: what it was (title), which
          views/options were argued and by whom (discussion, attribute positions briefly
          by name), and what was concluded (conclusion).
        - DROP greetings, small talk, weather, jokes and off-topic digressions. If the
          same point was repeated, state it once. If a long explanation leads to a single
          decision, record the decision, not the narrative.
        - DO NOT invent or guess facts, numbers, names or dates. When something important
          was unclear or not stated, add a short note to "notes" (e.g. "Bütçe rakamı net
          konuşulmadı") instead of fabricating it. A wrong number is worse than none.
        - executive_summary: {$budget} sentences (each item one sentence) capturing the
          essence and the 2-3 most critical decisions — readable on its own.
        - Use null for unknown single fields and [] for empty lists. Never omit a field.
        - Set "schema_version" to 1.

        JSON SCHEMA (field : meaning)
        {
          "schema_version": 1,
          "title": meeting title (string),
          "subtitle": one-line topic descriptor or null,
          "metadata": {
            "topic": string|null,
            "date": string|null,
            "start_time": string|null,
            "end_time": string|null,
            "location": physical place or platform, string|null,
            "attendees": [string],            // who was present
            "absentees": [string]             // invited but absent (important for the record)
          },
          "executive_summary": [string],      // {$budget} sentences
          "agenda_items": [
            { "title": string, "discussion": string, "conclusion": string|null }
          ],
          "decisions": [string],
          "action_items": [
            { "owner": string|null, "task": string, "due_date": string|null }
          ],
          "open_questions": [string],         // unresolved / postponed topics
          "next_meeting": string|null,
          "notes": [string]                   // your flagged uncertainties
        }
        PROMPT;
    }

    /**
     * Map an app locale (e.g. "tr", "tr_TR", "en-US") to the English name of the
     * language the summary must be written in. Defaults to Turkish.
     */
    public static function languageName(string $locale): string
    {
        $code = strtolower(substr(str_replace('-', '_', trim($locale)), 0, 2));

        return match ($code) {
            'en' => 'English',
            'de' => 'German',
            'fr' => 'French',
            'es' => 'Spanish',
            'ar' => 'Arabic',
            'ru' => 'Russian',
            default => 'Turkish',
        };
    }

    /**
     * Gemini responseSchema (OpenAPI-subset dialect) enforcing the contract above.
     *
     * @return array<string, mixed>
     */
    public static function schemaForGemini(): array
    {
        $stringArray = ['type' => 'ARRAY', 'items' => ['type' => 'STRING']];

        return [
            'type' => 'OBJECT',
            'properties' => [
                'schema_version' => ['type' => 'INTEGER'],
                'title' => ['type' => 'STRING'],
                'subtitle' => ['type' => 'STRING', 'nullable' => true],
                'metadata' => [
                    'type' => 'OBJECT',
                    'properties' => [
                        'topic' => ['type' => 'STRING', 'nullable' => true],
                        'date' => ['type' => 'STRING', 'nullable' => true],
                        'start_time' => ['type' => 'STRING', 'nullable' => true],
                        'end_time' => ['type' => 'STRING', 'nullable' => true],
                        'location' => ['type' => 'STRING', 'nullable' => true],
                        'attendees' => $stringArray,
                        'absentees' => $stringArray,
                    ],
                ],
                'executive_summary' => $stringArray,
                'agenda_items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'title' => ['type' => 'STRING'],
                            'discussion' => ['type' => 'STRING'],
                            'conclusion' => ['type' => 'STRING', 'nullable' => true],
                        ],
                    ],
                ],
                'decisions' => $stringArray,
                'action_items' => [
                    'type' => 'ARRAY',
                    'items' => [
                        'type' => 'OBJECT',
                        'properties' => [
                            'owner' => ['type' => 'STRING', 'nullable' => true],
                            'task' => ['type' => 'STRING'],
                            'due_date' => ['type' => 'STRING', 'nullable' => true],
                        ],
                    ],
                ],
                'open_questions' => $stringArray,
                'next_meeting' => ['type' => 'STRING', 'nullable' => true],
                'notes' => $stringArray,
            ],
        ];
    }
}
