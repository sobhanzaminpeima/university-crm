<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $relative = str_replace('\\', '/', substr($path, strlen($root) + 1));
    if (preg_match('#^(vendor|outputs|storage|tmp)/#', $relative)) {
        continue;
    }
    if ($file->getExtension() === 'php') {
        try {
            token_get_all((string) file_get_contents($path), TOKEN_PARSE);
        } catch (ParseError $exception) {
            $errors[] = "PHP syntax error: {$relative}: {$exception->getMessage()}";
        }
    }
    if (preg_match('/\.(php|blade\.php|js|ts|tsx|json|yml|yaml|md)$/', $relative)) {
        $contents = file_get_contents($path);
        if ($contents !== false && preg_match('/^(<<<<<<<|=======|>>>>>>>|\s*codex\/create-)/m', $contents)) {
            $errors[] = "Unresolved merge marker: {$relative}";
        }
    }
}

$routes = file_get_contents($root.'/routes/web.php') ?: '';
foreach (['auth.crm', 'tenant', 'subscription.active', 'permission:telegram.use'] as $required) {
    if (!str_contains($routes, $required)) {
        $errors[] = "Missing required route protection: {$required}";
    }
}

$securityExpectations = [
    'routes/web.php' => [
        "telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])->middleware('throttle:",
        "webhooks/student-status', [WebhookController::class, 'studentStatus'])->middleware('throttle:",
        "Route::get('/up'",
    ],
    'app/Http/Controllers/HealthController.php' => [
        'Hash::check',
        'hash_hmac',
        'hash_equals',
        'ensureSuperAdmin',
    ],
    'app/Http/Middleware/SecurityHeaders.php' => [
        'X-Content-Type-Options',
        'X-Frame-Options',
        'Strict-Transport-Security',
    ],
    'app/Http/Controllers/PasswordResetController.php' => [
        "hash('sha256'",
        "now()->subMinutes(60)",
        "Str::random(64)",
    ],
    'app/Support/SecretValue.php' => [
        'Crypt::encryptString',
        'Crypt::decryptString',
    ],
];
foreach ($securityExpectations as $file => $needles) {
    $contents = file_get_contents($root.'/'.$file) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $errors[] = "Missing security control in {$file}: {$needle}";
        }
    }
}

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Quality checks passed.\n");
