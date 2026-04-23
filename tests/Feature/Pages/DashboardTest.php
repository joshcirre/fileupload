<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('home page is public', function () {
    get('/')->assertOk();
});

test('home route is named "home"', function () {
    expect(app('router')->getRoutes()->getByName('home'))->not->toBeNull();
});
