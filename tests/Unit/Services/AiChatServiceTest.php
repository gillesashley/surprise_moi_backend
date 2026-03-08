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
        $this->assertEquals('browse', $result['metadata']['display_type']);
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

    public function test_parse_suggestions_without_analysis_key(): void
    {
        $json = json_encode([
            'type' => 'suggestions',
            'suggestions' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Gift',
                    'vendor_name' => 'Vendor',
                    'price' => 50.00,
                    'thumbnail' => 'https://example.com/img.jpg',
                    'personalization_reason' => 'Great match.',
                ],
            ],
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('suggestions', $result['type']);
        $this->assertEquals('browse', $result['metadata']['display_type']);
        $this->assertNotEmpty($result['content']);
        $this->assertEquals('Here are some gift suggestions for you:', $result['content']);
    }

    public function test_parse_suggestions_with_message_fallback(): void
    {
        $json = json_encode([
            'type' => 'suggestions',
            'message' => 'I found these for you!',
            'suggestions' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Test Gift',
                    'vendor_name' => 'Vendor',
                    'price' => 50.00,
                    'thumbnail' => 'https://example.com/img.jpg',
                    'personalization_reason' => 'Great match.',
                ],
            ],
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('suggestions', $result['type']);
        $this->assertEquals('browse', $result['metadata']['display_type']);
        $this->assertEquals('I found these for you!', $result['content']);
    }

    public function test_parse_deeply_nested_suggestions_in_text(): void
    {
        $json = json_encode([
            'type' => 'suggestions',
            'analysis' => 'Based on their interests...',
            'suggestions' => [
                [
                    'product_id' => 1,
                    'product_name' => 'Cooking Set',
                    'vendor_name' => 'Kitchen Pro',
                    'price' => 250.00,
                    'thumbnail' => 'https://example.com/img1.jpg',
                    'personalization_reason' => 'Loves cooking.',
                ],
                [
                    'product_id' => 2,
                    'product_name' => 'Art Supplies',
                    'vendor_name' => 'Creative Hub',
                    'price' => 180.00,
                    'thumbnail' => 'https://example.com/img2.jpg',
                    'personalization_reason' => 'Very creative.',
                ],
            ],
        ]);

        // Simulate AI wrapping response in extra text
        $text = "Here is my response: {$json} Hope this helps!";

        $result = $this->service->parseAiResponse($text);

        $this->assertEquals('suggestions', $result['type']);
        $this->assertNotEmpty($result['content']);
        $this->assertEquals('browse', $result['metadata']['display_type']);
        $this->assertCount(2, $result['metadata']['suggestions']);
    }

    public function test_parse_empty_string_response(): void
    {
        $result = $this->service->parseAiResponse('');

        $this->assertEquals('text', $result['type']);
        $this->assertEquals('', $result['content']);
        $this->assertNull($result['metadata']);
    }

    public function test_parse_product_card_response(): void
    {
        $json = json_encode([
            'type' => 'product_card',
            'selected_product_id' => 42,
            'personalization_reason' => 'Perfect for her love of gardening.',
            'message' => 'Great choice! Here are the full details.',
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('product_card', $result['type']);
        $this->assertEquals('Great choice! Here are the full details.', $result['content']);
        $this->assertEquals(42, $result['metadata']['selected_product_id']);
        $this->assertEquals('Perfect for her love of gardening.', $result['metadata']['personalization_reason']);
    }

    public function test_parse_product_card_without_message_uses_default(): void
    {
        $json = json_encode([
            'type' => 'product_card',
            'selected_product_id' => 10,
            'personalization_reason' => 'Great fit.',
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('product_card', $result['type']);
        $this->assertNotEmpty($result['content']);
        $this->assertEquals(10, $result['metadata']['selected_product_id']);
    }

    public function test_parse_product_card_without_selected_product_id(): void
    {
        $json = json_encode([
            'type' => 'product_card',
            'personalization_reason' => 'A thoughtful choice.',
            'message' => 'Here you go!',
        ]);

        $result = $this->service->parseAiResponse($json);

        $this->assertEquals('product_card', $result['type']);
        $this->assertNull($result['metadata']['selected_product_id']);
    }
}
