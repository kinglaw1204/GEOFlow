<?php

namespace App\Support;

use InvalidArgumentException;

class GeoFlowSsoToken
{
    /**
     * @return array{user_id: string, username: string, email: string, name: string, role: string, iat: int, exp: int, nonce: string}
     */
    public static function decode(string $token): array
    {
        $secret = self::secret();
        if ($secret === '') {
            throw new InvalidArgumentException('GEOFlow SSO secret is not configured.');
        }

        [$encodedPayload, $signature] = array_pad(explode('.', $token, 2), 2, '');
        if ($encodedPayload === '' || $signature === '') {
            throw new InvalidArgumentException('Invalid GEOFlow SSO token format.');
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret);
        if (! hash_equals($expectedSignature, $signature)) {
            throw new InvalidArgumentException('Invalid GEOFlow SSO token signature.');
        }

        $base64Payload = strtr($encodedPayload, '-_', '+/');
        $base64Payload .= str_repeat('=', (4 - strlen($base64Payload) % 4) % 4);
        $payloadJson = base64_decode($base64Payload, true);
        $payload = is_string($payloadJson) ? json_decode($payloadJson, true) : null;
        if (! is_array($payload)) {
            throw new InvalidArgumentException('Invalid GEOFlow SSO token payload.');
        }

        $now = time();
        if ((int) ($payload['exp'] ?? 0) < $now || (int) ($payload['iat'] ?? 0) > $now + 60) {
            throw new InvalidArgumentException('GEOFlow SSO token has expired.');
        }

        $username = trim((string) ($payload['username'] ?? ''));
        $nonce = trim((string) ($payload['nonce'] ?? ''));
        if ($username === '' || $nonce === '') {
            throw new InvalidArgumentException('GEOFlow SSO token is missing required identity fields.');
        }

        return [
            'user_id' => (string) ($payload['user_id'] ?? ''),
            'username' => $username,
            'email' => trim((string) ($payload['email'] ?? '')),
            'name' => trim((string) ($payload['name'] ?? '')),
            'role' => trim((string) ($payload['role'] ?? '')),
            'iat' => (int) ($payload['iat'] ?? 0),
            'exp' => (int) ($payload['exp'] ?? 0),
            'nonce' => $nonce,
        ];
    }

    private static function secret(): string
    {
        return trim((string) config('geoflow.sso_secret', ''));
    }
}
