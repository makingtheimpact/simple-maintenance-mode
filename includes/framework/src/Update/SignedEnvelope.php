<?php
declare(strict_types=1);

namespace MTI\Plugins\SimpleMaintenanceMode\Framework\Update;

/**
 * Ed25519 trust anchor. The signed bytes are the exact UTF-8 JSON payload string,
 * not a re-serialized JSON object. Private signing keys NEVER belong in plugins.
 */
final class SignedEnvelope
{
    /**
     * @param mixed $raw Decoded outer JSON {key_id, payload, signature}.
     * @param array<string, string> $publicKeys key ID => base64 Ed25519 public key.
     * @param list<string> $allowedHosts
     * @return array<string, mixed>|null
     */
    public static function verify($raw, array $publicKeys, string $slug, string $updateUri, array $allowedHosts): ?array
    {
        if (!function_exists('sodium_crypto_sign_verify_detached') ||
            !is_array($raw) ||
            !isset($raw['key_id'], $raw['payload'], $raw['signature']) ||
            !is_string($raw['key_id']) || !is_string($raw['payload']) || !is_string($raw['signature']) ||
            !preg_match('/^[a-zA-Z0-9_-]{1,40}$/D', $raw['key_id']) ||
            strlen($raw['payload']) > 120000 ||
            !isset($publicKeys[$raw['key_id']]) || !is_string($publicKeys[$raw['key_id']])) {
            return null;
        }
        $key = base64_decode($publicKeys[$raw['key_id']], true);
        $signature = base64_decode($raw['signature'], true);
        if ($key === false || $signature === false || strlen($key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ||
            strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES ||
            !sodium_crypto_sign_verify_detached($signature, $raw['payload'], $key)) {
            return null;
        }
        $payload = json_decode($raw['payload'], true);
        return Metadata::normalize($payload, $slug, $updateUri, $allowedHosts);
    }
}
