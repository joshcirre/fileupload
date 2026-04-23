<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

use function Pest\Laravel\get;
use function Pest\Laravel\postJson;

test('direct upload page is publicly accessible', function () {
    get('/direct')->assertOk();
});

test('initiate requires a file name', function () {
    postJson('/direct/init', [])->assertStatus(422);
});

test('sign validates input shape', function () {
    postJson('/direct/sign', ['uploadId' => 'x', 'key' => 'nope', 'partNumber' => 0])
        ->assertStatus(422);
});

test('complete validates input shape', function () {
    postJson('/direct/complete', [])->assertStatus(422);
});

test('abort validates input shape', function () {
    postJson('/direct/abort', [])->assertStatus(422);
});

test('initiate returns 503 when S3 is not configured', function () {
    Config::set('filesystems.disks.s3.bucket', null);
    Config::set('filesystems.disks.s3.key', null);
    Config::set('filesystems.disks.s3.secret', null);

    postJson('/direct/init', ['name' => 'test.bin'])->assertStatus(503);
});
