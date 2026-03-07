<?php

namespace App\Support;

class EmailHelper
{
    public static function suggest(?string $email): ?string
    {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }

        [$name, $domain] = explode('@', strtolower(trim($email)), 2);

        $commonDomains = [
            'gmail.com',
            'yahoo.com',
            'outlook.com',
            'hotmail.com',
            'icloud.com',
            'live.com',
        ];

        if (in_array($domain, $commonDomains, true)) {
            return null;
        }

        $closest = null;
        $shortest = PHP_INT_MAX;

        foreach ($commonDomains as $validDomain) {
            $distance = levenshtein($domain, $validDomain);

            if ($distance < $shortest) {
                $shortest = $distance;
                $closest = $validDomain;
            }
        }

        if ($closest && $shortest > 0 && $shortest <= 2) {
            return $name . '@' . $closest;
        }

        return null;
    }
}