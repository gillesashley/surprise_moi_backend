<?php

namespace Tests\Unit\Notifications;

use App\Channels\SmsChannel;
use App\Contracts\Sms\SmsProviderInterface;
use App\Notifications\Messages\SmsMessage;
use App\Notifications\Sms\OtpNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Notifications\Notification;
use Mockery;
use Tests\TestCase;

class SmsChannelTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test SmsChannel can be instantiated.
     */
    public function test_sms_channel_can_be_instantiated(): void
    {
        $provider = Mockery::mock(SmsProviderInterface::class);
        $channel = new SmsChannel($provider);

        $this->assertInstanceOf(SmsChannel::class, $channel);
    }

    /**
     * Test SMS notification via channel.
     */
    public function test_sms_notification_is_sent_via_channel(): void
    {
        $provider = Mockery::mock(SmsProviderInterface::class);
        $provider->shouldReceive('send')
            ->once()
            ->with('233559400612', Mockery::pattern('/1234/'))
            ->andReturn([
                'success' => true,
                'message' => 'SMS sent',
                'data' => ['transactionId' => 'test-123'],
            ]);

        $channel = new SmsChannel($provider);

        $notifiable = new class
        {
            use Notifiable;

            public $phone = '233559400612';
        };

        $notification = new OtpNotification('1234');

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result['success']);
    }

    /**
     * Test SmsMessage fluent API.
     */
    public function test_sms_message_fluent_api(): void
    {
        $message = (new SmsMessage)
            ->to('233559400612')
            ->content('Test message')
            ->from('TestSender');

        $this->assertEquals('233559400612', $message->getTo());
        $this->assertEquals('Test message', $message->getContent());
        $this->assertEquals('TestSender', $message->getFrom());
    }

    /**
     * Test SmsMessage returns self for chaining.
     */
    public function test_sms_message_returns_self(): void
    {
        $message = new SmsMessage;

        $this->assertSame($message, $message->to('233559400612'));
        $this->assertSame($message, $message->content('Test'));
        $this->assertSame($message, $message->from('Sender'));
    }

    /**
     * Test OtpNotification uses correct channel.
     */
    public function test_otp_notification_uses_sms_channel(): void
    {
        $notification = new OtpNotification('1234');
        $notifiable = new class
        {
            public $phone = '233559400612';
        };

        $channels = $notification->via($notifiable);

        $this->assertContains(SmsChannel::class, $channels);
    }

    /**
     * Test OtpNotification generates correct SMS message.
     */
    public function test_otp_notification_generates_correct_message(): void
    {
        $notification = new OtpNotification('5678');
        $notifiable = new class
        {
            public $phone = '233559400612';
        };

        $message = $notification->toSms($notifiable);

        $this->assertInstanceOf(SmsMessage::class, $message);
        $this->assertStringContainsString('5678', $message->getContent());
    }

    /**
     * Test OtpNotification with custom message.
     */
    public function test_otp_notification_with_custom_message(): void
    {
        $customMessage = 'Your verification code is {code}. Valid for 5 minutes.';
        $notification = new OtpNotification('9999', $customMessage);
        $notifiable = new class
        {
            public $phone = '233559400612';
        };

        $message = $notification->toSms($notifiable);

        $this->assertStringContainsString('9999', $message->getContent());
        $this->assertStringContainsString('verification code', $message->getContent());
    }

    /**
     * Test channel extracts phone from routeNotificationForSms method.
     */
    public function test_channel_uses_route_notification_for_sms(): void
    {
        $provider = Mockery::mock(SmsProviderInterface::class);
        $provider->shouldReceive('send')
            ->once()
            ->with('233559400612', Mockery::any())
            ->andReturn([
                'success' => true,
                'message' => 'Sent',
                'data' => [],
            ]);

        $channel = new SmsChannel($provider);

        $notifiable = new class
        {
            public function routeNotificationForSms(): string
            {
                return '233559400612';
            }
        };

        $notification = Mockery::mock(Notification::class);
        $notification->shouldReceive('toSms')->andReturn(
            (new SmsMessage)->content('Test')
        );

        $result = $channel->send($notifiable, $notification);

        $this->assertTrue($result['success']);
    }

    /**
     * Test channel throws exception when no phone number available.
     */
    public function test_channel_throws_exception_without_phone(): void
    {
        $provider = Mockery::mock(SmsProviderInterface::class);
        $channel = new SmsChannel($provider);

        $notifiable = new class
        {
            // No phone attribute
        };

        $notification = Mockery::mock(Notification::class);
        $notification->shouldReceive('toSms')->andReturn(
            (new SmsMessage)->content('Test')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('SMS notification requires a recipient phone number');

        $channel->send($notifiable, $notification);
    }
}
