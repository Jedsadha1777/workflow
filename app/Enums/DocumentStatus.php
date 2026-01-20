<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case DRAFT = 'draft';
    case PREPARE = 'prepare';
    case PENDING_CHECKING = 'pending_checking';
    case CHECKING = 'checking';
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Draft',
            self::PREPARE => 'Prepare',
            self::PENDING_CHECKING => 'Pending Checking',
            self::CHECKING => 'Checking',
            self::PENDING => 'Pending Approval',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::PREPARE => 'info',
            self::PENDING_CHECKING => 'warning',
            self::CHECKING => 'primary',
            self::PENDING => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
        };
    }

    public function shouldSendEmail(): bool
    {
        return match($this) {
            self::DRAFT => false,
            self::PREPARE => false,
            default => true,
        };
    }
}