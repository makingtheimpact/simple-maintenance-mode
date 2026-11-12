<?php
declare(strict_types=1);

/**
 * Offline Ed25519 release signer.
 *
 * Environment: MTI_SIGNING_SECRET_B64 = base64-encoded 64-byte Ed25519 secret key.
 * Usage: php tools/sign-release.php --manifest=/path/mti-plugin.json
 *        --payload=/path/release.json --zip=/path/plugin.zip
 *        --key-id=release-key-2026 --out=/path/signed-envelope.json
 *
 * Never put the secret key in a repository, public CI variable or web server.
 */
require_once dirname(__DIR__) . '/includes/framework/src/Update/Metadata.php';
use MTI\Plugins\SimpleMaintenanceMode\Framework\Update\Metadata;

$args = getopt('', ['manifest:', 'payload:', 'zip:', 'key-id:', 'out:']);
foreach (['manifest', 'payload', 'zip', 'key-id', 'out'] as $key) {
    if (!isset($args[$key]) || !is_string($args[$key]) || $args[$key] === '') {
        fwrite(STDERR, "Missing required --$key argument.\n");
        exit(2);
    }
}
if (!function_exists('sodium_crypto_sign_detached')) {
    fwrite(STDERR, "ext-sodium required.\n");
    exit(1);
}
try {
    $manifest = json_decode((string) file_get_contents($args['manifest']), true, 32, JSON_THROW_ON_ERROR);
    $payload = json_decode((string) file_get_contents($args['payload']), true, 32, JSON_THROW_ON_ERROR);
} catch (\Throwable $error) {
    fwrite(STDERR, "Cannot parse release input JSON.\n");
    exit(1);
}
if (!is_array($manifest) || !is_array($payload) || !is_file($args['zip']) ||
    !isset($manifest['public_keys'][$args['key-id']]) ||
    !is_string($manifest['public_keys'][$args['key-id']])) {
    fwrite(STDERR, "Missing manifest signing key, payload, or ZIP file.\n");
    exit(1);
}
$encodedSecret = getenv('MTI_SIGNING_SECRET_B64');
$secret = is_string($encodedSecret) ? base64_decode($encodedSecret, true) : false;
$publishedKey = base64_decode($manifest['public_keys'][$args['key-id']], true);
if ($secret === false || strlen($secret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES ||
    $publishedKey === false || strlen($publishedKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES ||
    !hash_equals(sodium_crypto_sign_publickey_from_secretkey($secret), $publishedKey)) {
    fwrite(STDERR, "Signing key does not match the pinned public key.\n");
    exit(1);
}
// Refuse to sign a non-installable archive or one with the wrong root folder.
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ext-zip required for release validation.\n");
    exit(1);
}
$zip = new ZipArchive();
if ($zip->open($args['zip']) !== true) {
    fwrite(STDERR, "Release ZIP cannot be opened.\n");
    exit(1);
}
$mainPath = (string) ($manifest['slug'] ?? '') . '/' . (string) ($manifest['slug'] ?? '') . '.php';
$mainSource = $zip->getFromName($mainPath);
$zip->close();
if (!is_string($mainSource) ||
    !preg_match('/^\s*\*?\s*Version:\s*([^\s]+)/mi', $mainSource, $matches) ||
    $matches[1] !== ($manifest['version'] ?? null)) {
    fwrite(STDERR, "Release ZIP is missing a matching main plugin header/version.\n");
    exit(1);
}
$payload['sha256'] = hash_file('sha256', $args['zip']);
$normalized = Metadata::normalize(
    $payload, (string) ($manifest['slug'] ?? ''), (string) ($manifest['update_uri'] ?? ''),
    (array) ($manifest['package_hosts'] ?? [])
);
if ($normalized === null || $payload['version'] !== ($manifest['version'] ?? null)) {
    fwrite(STDERR, "Release payload does not match the manifest or approved ZIP.\n");
    exit(1);
}
$encodedPayload = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
$envelope = [
    'key_id' => $args['key-id'],
    'payload' => $encodedPayload,
    'signature' => base64_encode(sodium_crypto_sign_detached($encodedPayload, $secret)),
];
file_put_contents(
    $args['out'],
    json_encode($envelope, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n",
    LOCK_EX
);
echo "Signed release envelope: {$args['out']}\n";
