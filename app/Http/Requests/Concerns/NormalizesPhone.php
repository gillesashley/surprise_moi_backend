<?php

namespace App\Http\Requests\Concerns;

use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

trait NormalizesPhone
{
    public function prepareForValidation(): void
    {
        $raw = $this->input('phone');

        if (! is_string($raw) || $raw === '') {
            return;
        }

        try {
            $e164 = (new PhoneNumber($raw, ['GH']))->formatE164();
            $this->merge(['phone' => $e164]);
        } catch (Throwable) {
            // Leave the raw value; the Phone validation rule will reject it with a 422.
        }
    }
}
