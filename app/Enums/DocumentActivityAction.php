<?php

namespace App\Enums;

enum DocumentActivityAction: string
{
    case CREATED = 'created';
    case EDITED = 'edited';
    case SUBMITTED = 'submitted';
    case PREPARED = 'prepared';
    case CHECKED = 'checked';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case RECALLED = 'recalled';
    case DELETED = 'deleted';

    public function label(): string
    {
        return match($this) {
            self::CREATED => 'Created',
            self::EDITED => 'Edited',
            self::SUBMITTED => 'Submitted',
             self::PREPARED => 'Prepared',
            self::CHECKED => 'Checked',
            self::APPROVED => 'Approved',
            self::REJECTED => 'Rejected',
            self::RECALLED => 'Recalled',
            self::DELETED => 'Deleted',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CREATED => 'heroicon-o-plus-circle',
            self::EDITED => 'heroicon-o-pencil',
            self::SUBMITTED => 'heroicon-o-paper-airplane',
             self::PREPARED => 'heroicon-o-document-check',
            self::CHECKED => 'heroicon-o-clipboard-document-check',
            self::APPROVED => 'heroicon-o-check-circle',
            self::REJECTED => 'heroicon-o-x-circle',
            self::RECALLED => 'heroicon-o-arrow-uturn-left',
            self::DELETED => 'heroicon-o-trash',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CREATED => 'gray',
            self::EDITED => 'info',
            self::SUBMITTED => 'warning',
            self::PREPARED => 'info',
           self::CHECKED => 'primary',
            self::APPROVED => 'success',
            self::REJECTED => 'danger',
            self::RECALLED => 'warning',
            self::DELETED => 'danger',
        };
    }
}