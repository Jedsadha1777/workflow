<?php

namespace App\Enums;

enum DocumentActivityAction: string
{
    case CREATED = 'created';
    case EDITED = 'edited';
    case SUBMITTED = 'submitted';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECALLED = 'recalled';

    public function label(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::EDITED => 'Edited',
            self::SUBMITTED => 'Submitted',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::RECALLED => 'Recalled',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CREATED => 'heroicon-o-plus-circle',
            self::EDITED => 'heroicon-o-pencil',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
            self::APPROVED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::RECALLED => 'heroicon-o-arrow-uturn-left',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CREATED => 'gray',
            self::EDITED => 'info',
            self::SUBMITTED => 'warning',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::RECALLED => 'warning',
        };
    }
}