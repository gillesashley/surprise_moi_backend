<?php

namespace Tests\Unit;

use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupportTicketModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_ticket_number_is_generated_on_creation(): void
    {
        $ticket = SupportTicket::factory()->create();

        $this->assertMatchesRegularExpression('/^CST-\d{8}-\d{4}$/', $ticket->ticket_number);
    }

    public function test_ticket_numbers_increment_within_a_day(): void
    {
        $first = SupportTicket::factory()->create();
        $second = SupportTicket::factory()->create();

        $firstSeq = (int) substr($first->ticket_number, -4);
        $secondSeq = (int) substr($second->ticket_number, -4);

        $this->assertSame($firstSeq + 1, $secondSeq);
    }
}
