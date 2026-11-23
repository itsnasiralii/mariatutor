<?php

namespace App\Services;

use App\Models\TutorProfile;
use App\Models\TuitionRequest;
use Illuminate\Support\Str;

class SecurityService
{
    public static function pepper(): string
    {
        return config('app.key') ?: 'maria-tutor-secret-pepper-2026';
    }

    public static function hashIdentifier(string $identifier, string $type = 'email'): string
    {
        $normalized = strtolower(trim($identifier));
        return hash_hmac('sha256', $normalized, static::pepper());
    }

    public static function normalizeCnic(string $cnic): string
    {
        $digits = preg_replace('/\D/', '', $cnic);
        if (strlen($digits) === 13) {
            return substr($digits, 0, 5) . '-' . substr($digits, 5, 7) . '-' . substr($digits, 12, 1);
        }
        return trim($cnic);
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
            $ref = 'REQ-' . strtoupper(Str::random(6));
        } while (TuitionRequest::where('public_ref', $ref)->exists());

        return $ref;
    }
}
