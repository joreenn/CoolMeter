<?php

// Called by the PHP runtime's Composer build hook, never by a public HTTP route.
// vercel.json runtime settings may not be present in the isolated build process.
foreach (['DB_CONNECTION' => 'pgsql', 'DB_SSLMODE' => 'require', 'LOG_CHANNEL' => 'stderr', 'APP_ENV' => 'production', 'APP_DEBUG' => 'false'] as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $_SERVER[$key] = $value;
}
$url = getenv('DB_URL') ?: getenv('POSTGRES_URL') ?: getenv('DATABASE_URL');
if (! $url || ! getenv('APP_KEY')) {
    fwrite(STDERR, "Configure APP_KEY and a hosted PostgreSQL connection before deploying.\n");
    exit(1);
}
if (! in_array(parse_url($url, PHP_URL_SCHEME), ['postgres', 'postgresql', 'pgsql'], true)) {
    fwrite(STDERR, "A PostgreSQL URL is required for this deployment.\n");
    exit(1);
}
passthru(escapeshellarg(PHP_BINARY).' artisan migrate --force --no-interaction', $status);
exit($status);
