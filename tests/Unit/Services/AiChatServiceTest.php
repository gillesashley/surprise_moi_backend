<?php

namespace Tests\Unit\Services;

use App\Services\AiChatService;
use Tests\TestCase;

class AiChatServiceTest extends TestCase
{
    protected AiChatService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new AiChatService;
    }

    public function test_parse_greeting_response(): void
    {
        $json = '{"type":"greeting","message":"Hi! I\'m your gift assistant."}';

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('greeting', $result['type']);
        $this->assertStringContainsString('gift assistant', $result['content']);
    }

    public function test_parse_clarification_response(): void
    {
        $json = '{"type":"clarification","message":"Tell me more!","questions":["What are their hobbies?","What is the occasion?"]}';

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('clarification', $result['type']);
        $this->assertEquals('Tell me more!', $result['content']);
        $this->assertCount(2, $result['metadata']['questions']);
    }

    public function test_parse_suggestions_response(): void
    {
        $json = json_encode([
            'type' => 'suggestions',
            'analysis' => 'Based on their calm personality...',
            'suggestions' => [
                [
                    'product_id' => 42,
                    'product_name' => 'Bonsai Kit',
                    'vendor_name' => 'GreenThumb',
                    'price' => 150.00,
                    'thumbnail' => 'https://example.com/img.jpg',
                    'personalization_reason' => 'Perfect for calm temperaments.',
                ],
            ],
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('suggestions', $result['type']);
        $this->assertNotEmpty($result['metadata']['suggestions']);
        $this->assertEquals(42, $result['metadata']['suggestions'][0]['product_id']);
    }

    public function test_parse_plain_text_response(): void
    {
        $text = 'Just a plain text response without JSON.';

        $result = $this->service->parseAiResponse($text);

        $this->assertEquals('text', $result['type']);
        $this->assertEquals($text, $result['content']);
        $this->assertNull($result['metadata']);
    }

    public function test_parse_json_in_markdown_code_block(): void
    {
        $text = "Here's the response:\n```json\n{\"type\":\"greeting\",\"message\":\"Hello!\"}\n```";

        $result = $this->service->parseAiResponse($text);

        $this->assertEquals('greeting', $result['type']);
        $this->assertEquals('Hello!', $result['content']);
    }

    public function test_parse_json_embedded_in_text(): void
    {
        $text = 'Some text before {"type":"clarification","message":"Need more info","questions":["Question?"]} and after';

        $result = $this->service->parseAiResponse($text);

        $this->assertEquals('clarification', $result['type']);
    }
}
