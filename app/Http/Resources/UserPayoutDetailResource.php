<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserPayoutDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payout_method' => $this->payout_method,
            'mobile_money_number' => $this->mobile_money_number,
            'mobile_money_number_masked' => $this->maskNumber($this->mobile_money_number),
            'mobile_money_provider' => $this->mobile_money_provider,
            'account_name' => $this->account_name,
            'is_verified' => (bool) $this->is_verified,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function maskNumber(?string $number): ?string
    {
        if (! $number || strlen($number) < 6) {
            return $number;
        }

        return substr($number, 0, 3).str_repeat('*', strlen($number) - 5).substr($number, -2);
    }
}
