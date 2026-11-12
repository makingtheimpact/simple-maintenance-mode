<?php
declare(strict_types=1);

/**
 * Disposable CI-only signing fixture.
 * Generates a random private key in memory, never in Git or on public hosts.
 * Does not publish any release.
 */
$root = dirname(__DIR__);
$fixtureDir = '/tmp/smm-fixture/simple-maintenance-mode';
$installedDir = '/tmp/wp-ci/wp-content/plugins/simple-maintenance-mode';
$releaseDir = dirname($fixtureDir);
$main = $fixtureDir.'/simple-maintenance-mode.php';
$readme = $fixtureDir.'/readme.txt';
$keyId = 'ci-ephemeral-2026';

function requireFixture(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException('Signing fixture: '.$message);
    }
}

/** @param list<string> $args */
function executeFixture(array $args, array $environment = []): void
{
    $pipes = [];
    $process = proc_open($args, [0=>['pipe','r'], 1=>['pipe','w'], 2=>['pipe','w']], $pipes,
        null, array_merge($_ENV, $environment));
    requireFixture(is_resource($process), 'Cannot start subprocess.');
    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);
    requireFixture($status === 0, 'Subprocess failed: '.$stderr.' '.$stdout);
}
requireFixture(is_file($main) && is_file($readme)
    && is_file($installedDir.'/mti-plugin.json'), 'Required source files not present.');
requireFixture(function_exists('sodium_crypto_sign_keypair'), 'Ed25519 support required.');

$pair = sodium_crypto_sign_keypair();
$secret = sodium_crypto_sign_secretkey($pair);
$public = base64_encode(sodium_crypto_sign_publickey($pair));
foreach ([$fixtureDir, $installedDir] as $directory) {
    $path = $directory.'/mti-plugin.json';
    $manifest = json_decode((string) file_get_contents($path), true, 32, JSON_THROW_ON_ERROR);
    requireFixture(is_array($manifest) && ($manifest['slug'] ?? '') === 'simple-maintenance-mode',
        'Incorrect plugin manifest.');
    $manifest['public_keys'] = [$keyId => $public];
    if ($directory === $fixtureDir) {
        $manifest['version'] = '2.0.1';
    }
    file_put_contents($path, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n");
}

$mainSource = (string) file_get_contents($main);
$updatedMain = str_replace(
    ["* Version:             2.0.0", "define( 'SMM_VERSION', '2.0.0' );"],
    ["* Version:             2.0.1", "define( 'SMM_VERSION', '2.0.1' );"],
    $mainSource
);
requireFixture($mainSource !== $updatedMain &&
    strpos($updatedMain, "define( 'SMM_VERSION', '2.0.1' );") !== false, 'Cannot safely bump CI release version.');
file_put_contents($main, $updatedMain);

$readmeSource = (string) file_get_contents($readme);
requireFixture(strpos($readmeSource, 'Stable tag: 2.0.0') !== false, 'Unexpected readme version.');
file_put_contents($readme, str_replace('Stable tag: 2.0.0', 'Stable tag: 2.0.1', $readmeSource));

$zipPath = $releaseDir.'/simple-maintenance-mode-2.0.1.zip';
executeFixture([PHP_BINARY, $root.'/tools/validate-manifest.php', $fixtureDir.'/mti-plugin.json', '--production']);
executeFixture([PHP_BINARY, $root.'/tools/build-release.php', $fixtureDir, $zipPath, '--production']);

$payload = [
    'slug' => 'simple-maintenance-mode',
    'update_uri' => 'https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/',
    'version' => '2.0.1',
    'expires' => time() + 86400,
    'package' => 'https://github.com/makingtheimpact/simple-maintenance-mode/releases/download/v2.0.1/simple-maintenance-mode.zip',
    'mirrors' => ['https://downloads.thepluginmarket.com/simple-maintenance-mode/v2.0.1.zip'],
    'name' => 'Simple Maintenance Mode',
    'requires_php' => '8.1',
];
$draft = $releaseDir.'/payload.json';
$signed = $releaseDir.'/signed.json';
file_put_contents($draft, json_encode($payload, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
executeFixture([
    PHP_BINARY, $root.'/tools/sign-release.php',
    '--manifest='.$fixtureDir.'/mti-plugin.json',
    '--payload='.$draft,
    '--zip='.$zipPath,
    '--key-id='.$keyId,
    '--out='.$signed,
], ['MTI_SIGNING_SECRET_B64' => base64_encode($secret)]);

// Validate the same signed envelope through the actual embedded runtime.
require_once $root.'/includes/framework/src/Update/Metadata.php';
require_once $root.'/includes/framework/src/Update/SignedEnvelope.php';
$envelope = json_decode((string) file_get_contents($signed), true, 32, JSON_THROW_ON_ERROR);
$data = \MTI\Plugins\SimpleMaintenanceMode\Framework\Update\SignedEnvelope::verify(
    $envelope,
    [$keyId => $public],
    'simple-maintenance-mode',
    'https://api.nightmarketplugins.com/plugins/simple-maintenance-mode/',
    ['github.com', 'downloads.thepluginmarket.com']
);
requireFixture(is_array($data) && $data['sha256'] === hash_file('sha256', $zipPath),
    'Offline signer and plugin verifier disagree.');
unset($secret, $pair);
echo "PASS: Ephemeral signed v2.0.1 release fixture, no publishing.\n";
