<?php

namespace Tests\Unit;

use App\Services\Summarization\Providers\GeminiProvider;
use PHPUnit\Framework\TestCase;

class GeminiExtractJsonTest extends TestCase
{
    public function test_returns_clean_json_unchanged(): void
    {
        $json = '{"title":"X","decisions":["a"]}';
        $this->assertSame($json, GeminiProvider::extractJson($json));
    }

    public function test_strips_markdown_fences(): void
    {
        $raw = "```json\n{\"title\":\"X\",\"decisions\":[]}\n```";
        $this->assertSame('{"title":"X","decisions":[]}', GeminiProvider::extractJson($raw));
    }

    public function test_extracts_object_from_prose_preferring_title(): void
    {
        // A reasoning model may echo the schema then emit the real object.
        $raw = 'Schema: {"title":string}. Here it is: {"title":"Real","decisions":["d1"]} done.';
        $decoded = json_decode(GeminiProvider::extractJson($raw), true);
        $this->assertSame('Real', $decoded['title']);
        $this->assertSame(['d1'], $decoded['decisions']);
    }

    public function test_returns_input_when_no_json(): void
    {
        $this->assertSame('no json here', GeminiProvider::extractJson('no json here'));
    }
}
