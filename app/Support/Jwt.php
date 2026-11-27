<?php
namespace App\Support;

final class Jwt
{
    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
    private static function secret(): string
    {
        $secret = config('services.jwt.secret');
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new \RuntimeException('Configure JWT_SECRET with a randomly generated secret of at least 32 characters.');
        }
        return $secret;
    }
    public static function issue($id): string
    {
        $now = time();
        $header = self::encode(json_encode(['typ'=>'JWT', 'alg'=>'HS256']));
        $payload = self::encode(json_encode(['user_id'=>$id, 'type'=>'access', 'iat'=>$now, 'exp'=>$now + 86400 * max(1, (int) config('services.jwt.days', 1))]));
        return "$header.$payload.".self::encode(hash_hmac('sha256', "$header.$payload", self::secret(), true));
    }
    public static function read(string $token): ?array
    {
        if (strlen($token) > 4096) return null;
        $parts = explode('.', $token);
        if (count($parts) !== 3) return null;
        [$header, $payload, $signature] = $parts;
        $h = json_decode(base64_decode(strtr($header, '-_', '+/'), true) ?: '', true);
        $p = json_decode(base64_decode(strtr($payload, '-_', '+/'), true) ?: '', true);
        if (!is_array($h) || !is_array($p) || ($h['alg'] ?? null) !== 'HS256' || ($h['typ'] ?? null) !== 'JWT') return null;
        $expected = self::encode(hash_hmac('sha256', "$header.$payload", self::secret(), true));
        return hash_equals($expected, $signature)
            && ($p['type'] ?? null) === 'access'
            && is_int($p['exp'] ?? null) && $p['exp'] > time()
            && is_int($p['iat'] ?? null) && $p['iat'] <= time()
            && is_int($p['user_id'] ?? null) && $p['user_id'] > 0 ? $p : null;
    }
}
