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
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($path), $output, $code);
        if ($code !== 0) {
            $errors[] = "PHP syntax error: {$relative}";
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

if ($errors) {
    fwrite(STDERR, implode(PHP_EOL, $errors).PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Quality checks passed.\n");
