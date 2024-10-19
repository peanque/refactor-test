<?php

namespace DTApi\Enums;

enum UserType: int
{
    case ADMIN_ROLE_ID = 1;
    case SUPERADMIN_ROLE_ID = 2;

    public function name(): string
    {
        return match ($this) {
            self::ADMIN_ROLE_ID => 'Admin',
            self::SUPERADMIN_ROLE_ID => 'Super Admin',
        };
    }

    public static function fromValue(int $value): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->value === $value) {
                return $case;
            }
        }
    }
}