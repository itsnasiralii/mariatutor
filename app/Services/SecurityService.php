<?php

namespace App\Services;

use App\Models\TuitionRequest;
use App\Models\TutorProfile;
use Illuminate\Support\Str;
use RuntimeException;

class SecurityService
{
    public static function hashKey(): string
    {
        $key = config('app.cnic_hash_key') ?: config('app.key');
        if (empty($key)) {
            throw new RuntimeException('Neither CNIC_HASH_KEY nor APP_KEY is configured for identifier hashing.');
        }

        return (string) $key;
    }

    public static function hashIdentifier(string $identifier, string $type = 'email'): string
    {
        $normalized = strtolower(trim($identifier));

        return hash_hmac('sha256', "{$type}:{$normalized}", static::hashKey());
    }

    public static function normalizeCnic(string $cnic): string
    {
        $digits = preg_replace('/\D/', '', $cnic);
        if (strlen($digits) === 13) {
            return substr($digits, 0, 5).'-'.substr($digits, 5, 7).'-'.substr($digits, 12, 1);
        }

        return trim($cnic);
    }

    public static function cnicGenderHint(string $cnic): ?string
    {
        $digits = preg_replace('/\D/', '', $cnic);
        if (strlen($digits) !== 13) {
            return null;
        }

        $lastDigit = (int) substr($digits, -1);

        return ($lastDigit % 2 === 0) ? 'Female' : 'Male';
    }

    public static function generateTutorCode(): string
    {
        $last = TutorProfile::orderBy('id', 'desc')->first();
        $next = ($last ? $last->id : 0) + 1;

        return sprintf('T-BW%03d', $next);
    }

    public static function generateRequestRef(): string
    {
        do {
            $ref = 'REQ-'.strtoupper(Str::random(6));
        } while (TuitionRequest::where('public_ref', $ref)->exists());

        return $ref;
    }
}
