<?php

namespace Tests\Unit\Services;

use App\Services\ScheduleService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleServiceTest extends TestCase
{
    use RefreshDatabase;

    protected ScheduleService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ScheduleService;
    }

    public function test_parses_schedule_list_output_correctly(): void
    {
        $output = '* * * * *  Send verification email .. Next Due: 2024-01-01 12:00:00
0 0 * * *  Process payouts .. Next Due: 2024-01-02 00:00:00
* */2 * * *  php artisan command:overdue .. Next Due: 2024-01-01 10:00:00';

        $result = $this->service->parseScheduleListOutput($output);

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
        $this->assertEquals('Send verification email', $result[0]['command']);
        $this->assertEquals('Every minute', $result[0]['frequency']);
        $this->assertEquals('2024-01-01 12:00:00', $result[0]['next_due']);
        $this->assertEquals('', $result[0]['overdue']);
    }

    public function test_handles_empty_output(): void
    {
        $output = '';

        $result = $this->service->parseScheduleListOutput($output);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_handles_malformed_output(): void
    {
        $output = 'Not a valid schedule output';

        $result = $this->service->parseScheduleListOutput($output);

        $this->assertIsArray($result);
        $this->assertCount(0, $result);
    }

    public function test_validates_schedule_output(): void
    {
        $validOutput = '* * * * *  command .. Next Due: some time';

        $this->assertTrue($this->service->isValidScheduleOutput($validOutput));
    }

    public function test_invalidates_non_schedule_output(): void
    {
        $invalidOutput = 'Some random output';

        $this->assertFalse($this->service->isValidScheduleOutput($invalidOutput));
    }

    public function test_clears_schedule_cache(): void
    {
        cache()->put('admin_schedule_data', ['test' => 'data'], now()->addMinutes(5));

        $this->service->clearScheduleCache();

        $this->assertNull(cache()->get('admin_schedule_data'));
    }

    public function test_get_schedule_data_with_cache(): void
    {
        cache()->put('admin_schedule_data', ['success' => true, 'data' => []], now()->addMinutes(5));

        $result = $this->service->getScheduleDataWithCache(5);

        $this->assertTrue($result['success']);
        $this->assertEquals([], $result['data']);
    }
}
