<?php

return [
    'hosted' => (bool) env('COOLMETER_SERVERLESS', false),
    // Keep the local home setup optional, but require authentication online.
    'require_login' => (bool) env('COOLMETER_REQUIRE_LOGIN', env('APP_ENV') === 'production'),
    'setup_token_hash' => env('COOLMETER_SETUP_TOKEN_HASH'),
];
