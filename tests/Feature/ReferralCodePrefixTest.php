<?php

namespace Tests\Feature;

use App\Models\ReferralCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReferralCodePrefixTest extends TestCase
{
    use RefreshDatabase;

    // -------------------------------------------------------------------------
    // getPrefixForRole
    // -------------------------------------------------------------------------

    #[DataProvider('roleProvider')]
    public function test_get_prefix_for_role_returns_correct_prefix(string $role, string $expectedPrefix): void
    {
        $this->assertSame($expectedPrefix, ReferralCode::getPrefixForRole($role));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function roleProvider(): array
    {
        return [
            'customer' => ['customer',    'CU-'],
            'vendor' => ['vendor',      'VD-'],
            'influencer' => ['influencer',  'IN-'],
            'field_agent' => ['field_agent', 'FA-'],
            'employee' => ['employee',    'EM-'],
        ];
    }

    #[DataProvider('unmappedRoleProvider')]
    public function test_get_prefix_for_role_returns_empty_string_for_unmapped_roles(string $role): void
    {
        $this->assertSame('', ReferralCode::getPrefixForRole($role));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unmappedRoleProvider(): array
    {
        return [
            'admin' => ['admin'],
            'super_admin' => ['super_admin'],
        ];
    }

    // -------------------------------------------------------------------------
    // generateUniqueCode
    // -------------------------------------------------------------------------

    public function test_generate_unique_code_with_prefix_has_correct_format(): void
    {
        $code = ReferralCode::generateUniqueCode('VD-');

        $this->assertStringStartsWith('VD-', $code);
        // Total length: 3 (prefix) + 8 (random) = 11
        $this->assertSame(11, strlen($code));
        $this->assertMatchesRegularExpression('/^VD-[A-Z0-9]{8}$/', $code);
    }

    public function test_generate_unique_code_without_prefix_is_8_chars(): void
    {
        $code = ReferralCode::generateUniqueCode();

        $this->assertSame(8, strlen($code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $code);
    }

    public function test_generate_unique_code_with_empty_string_prefix_is_8_chars(): void
    {
        $code = ReferralCode::generateUniqueCode('');

        $this->assertSame(8, strlen($code));
    }

    // -------------------------------------------------------------------------
    // Boot creating event
    // -------------------------------------------------------------------------

    public function test_boot_creating_event_uses_prefix_when_set_on_model(): void
    {
        $referralCode = new ReferralCode;
        $referralCode->prefix = 'VD-';
        $referralCode->influencer_id = User::factory()->influencer()->create()->id;
        $referralCode->save();

        $this->assertStringStartsWith('VD-', $referralCode->code);
        $this->assertMatchesRegularExpression('/^VD-[A-Z0-9]{8}$/', $referralCode->code);
    }

    public function test_boot_creating_event_generates_unprefixed_code_by_default(): void
    {
        $referralCode = new ReferralCode;
        $referralCode->influencer_id = User::factory()->influencer()->create()->id;
        $referralCode->save();

        $this->assertSame(8, strlen($referralCode->code));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{8}$/', $referralCode->code);
    }

    public function test_boot_creating_event_does_not_overwrite_explicitly_set_code(): void
    {
        $referralCode = new ReferralCode;
        $referralCode->code = 'MYCODE01';
        $referralCode->influencer_id = User::factory()->influencer()->create()->id;
        $referralCode->save();

        $this->assertSame('MYCODE01', $referralCode->code);
    }
}
