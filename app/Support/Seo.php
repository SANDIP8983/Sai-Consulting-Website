<?php

namespace App\Support;

class Seo
{
    public static function route(string $name, array|string $parameters = []): string
    {
        return self::url(route($name, $parameters, false));
    }

    public static function url(string $path = '/'): string
    {
        $baseUrl = rtrim((string) config('app.url'), '/');
        $path = '/'.ltrim(parse_url($path, PHP_URL_PATH) ?: '/', '/');

        return $baseUrl.$path;
    }
}
