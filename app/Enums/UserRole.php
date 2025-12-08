<?php

namespace App\Enums;

enum UserRole: string
{
    case ADMIN = 'admin';
    case LD = 'LD';
    case DM = 'DM';
    case DCC = 'DCC';
    case MD = 'MD';
    case ACC = 'ACC';
    case IT = 'IT';

    public static function adminRoles(): array
    {
        return [
            self::ADMIN->value,
        ];
    }

    public static function userRoles(): array
    {
        return [
            self::LD->value,
            self::DM->value,
            self::DCC->value,
            self::MD->value,
            self::ACC->value,
            self::IT->value,
        ];
    }

    public function label(): string
    {
        return match($this) {
            self::ADMIN => 'Admin',
            self::LD => 'LD',
            self::DM => 'DM',
            self::DCC => 'DCC',
            self::MD => 'MD',
            self::ACC => 'ACC',
            self::IT => 'IT',
        };
    }
}