<?php

return [
    'name' => env('ADMIN_NAME', 'Admin'),
    'username' => env('ADMIN_USERNAME', 'admin'),
    'email' => env('ADMIN_EMAIL') ?: null,
    'mobile' => env('ADMIN_MOBILE'),
    'password' => env('ADMIN_PASSWORD'),
];
