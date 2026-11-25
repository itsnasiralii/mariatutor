<?php

namespace App\Enums;

enum VerificationStatus: string
{
    case Pending = 'Pending';
    case UnderReview = 'Under Review';
    case Approved = 'Approved';
    case Rejected = 'Rejected';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $status) => $status->value, self::cases());
    }

    public static function fromFilter(?string $filter): ?self
    {
        if (! $filter || strtolower($filter) === 'all') {
            return null;
        }

        $normalized = strtolower(str_replace(['_', '-'], ' ', trim($filter)));

        return match ($normalized) {
            'pending' => self::Pending,
            'under review' => self::UnderReview,
            'approved' => self::Approved,
            'rejected' => self::Rejected,
            default => null,
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'badge-success',
            self::Rejected => 'badge-danger',
            self::UnderReview => 'badge-info',
            self::Pending => 'badge-warning',
        };
    }
}
