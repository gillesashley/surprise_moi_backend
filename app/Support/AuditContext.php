<?php

namespace App\Support;

class AuditContext
{
    protected static ?string $ip = null;

    protected static ?string $userAgent = null;

    public static function set(?string $ip, ?string $userAgent): void
    {
        self::$ip = $ip;
        self::$userAgent = $userAgent;
    }

    public static function ip(): ?string
    {
        return self::$ip;
    }

    public static function userAgent(): ?string
    {
        return self::$userAgent;
    }

    public static function forget(): void
    {
        self::$ip = null;
        self::$userAgent = null;
    }

    /** @return array{ip: ?string, user_agent: ?string} */
    public static function toArray(): array
    {
        return [
            'ip' => self::$ip,
            'user_agent' => self::$userAgent,
        ];
    }
}
