<?php

namespace App\Enums;

enum StepType: string
{
    case PREPARE = 'prepare';
    case CHECKING = 'checking';
    case APPROVE = 'approve';

    public function label(): string
    {
        return match($this) {
            self::PREPARE => 'Prepare',
            self::CHECKING => 'Checking',
            self::APPROVE => 'Approve',
        };
    }

    public function shouldSendEmail(): bool
    {
        return match($this) {
            self::PREPARE => false,
            self::CHECKING => true,
            self::APPROVE => true,
        };
    }

    public function canRecallAfterApprove(): bool
    {
        return match($this) {
            self::PREPARE => true,
            self::CHECKING => true,
            self::APPROVE => false,
        };
    }
}