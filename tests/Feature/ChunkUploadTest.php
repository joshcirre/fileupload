<?php

declare(strict_types=1);

use App\Actions\FinalizeUploadAction;
use App\Models\Share;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

test('home page is publicly accessible', function () {
    get('/')->assertOk();
});

test('guest can upload a small file and receive a share link', function () {
    Storage::fake('local');

    $uuid = (string) Str::uuid();
    $payload = random_bytes(1024);

    post('/upload/chunk', [
        'uuid' => $uuid,
        'index' => 0,
        'total' => 1,
        'name' => 'note.bin',
        'chunk' => UploadedFile::fake()->createWithContent('chunk', $payload),
    ])->assertOk();

    post('/upload/finalize', [
        'uuid' => $uuid,
        'total' => 1,
        'name' => 'note.bin',
        'expires_in_days' => 1,
    ])->assertOk();

    $share = Share::query()->sole();

    expect($share->user_id)->toBeNull()
        ->and($share->size)->toBe(mb_strlen($payload, '8bit'));

    Storage::disk($share->disk)->assertExists($share->path);
});

test('guest cannot finalize a file over 20 MB', function () {
    Storage::fake('local');

    $uuid = (string) Str::uuid();
    $oversize = FinalizeUploadAction::GUEST_MAX_BYTES + 1024;
    $chunkBytes = 1024 * 1024;
    $chunks = mb_str_split(str_repeat('x', $oversize), $chunkBytes);
    $total = count($chunks);

    foreach ($chunks as $index => $bytes) {
        post('/upload/chunk', [
            'uuid' => $uuid,
            'index' => $index,
            'total' => $total,
            'name' => 'huge.bin',
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $bytes),
        ])->assertOk();
    }

    post('/upload/finalize', [
        'uuid' => $uuid,
        'total' => $total,
        'name' => 'huge.bin',
        'expires_in_days' => 1,
    ])->assertStatus(403);

    expect(Share::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles('shares'))->toBeEmpty();
});

test('authed user can upload larger than the guest cap', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    actingAs($user);

    $bigPayload = random_bytes(FinalizeUploadAction::GUEST_MAX_BYTES + 1024);
    $uuid = (string) Str::uuid();
    $chunks = mb_str_split($bigPayload, 5 * 1024 * 1024, '8bit');
    $total = count($chunks);

    foreach ($chunks as $index => $bytes) {
        post('/upload/chunk', [
            'uuid' => $uuid,
            'index' => $index,
            'total' => $total,
            'name' => 'big.bin',
            'chunk' => UploadedFile::fake()->createWithContent('chunk', $bytes),
        ])->assertOk();
    }

    $response = post('/upload/finalize', [
        'uuid' => $uuid,
        'total' => $total,
        'name' => 'big.bin',
        'expires_in_days' => 7,
    ])->assertOk();

    $share = Share::query()->sole();

    expect($share->user_id)->toBe($user->id)
        ->and($share->size)->toBe(mb_strlen($bigPayload, '8bit'));

    $response->assertJson(['id' => $share->id]);
});

test('finalize can create a one-time share', function () {
    Storage::fake('local');
    actingAs(User::factory()->create());

    $uuid = (string) Str::uuid();

    post('/upload/chunk', [
        'uuid' => $uuid,
        'index' => 0,
        'total' => 1,
        'name' => 'x.bin',
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'hello'),
    ])->assertOk();

    post('/upload/finalize', [
        'uuid' => $uuid,
        'total' => 1,
        'name' => 'x.bin',
        'delete_after_first_download' => '1',
    ])->assertOk();

    $share = Share::query()->sole();

    expect($share->delete_after_first_download)->toBeTrue()
        ->and($share->expires_at)->toBeNull();
});

test('finalize refuses to assemble when chunks are missing', function () {
    Storage::fake('local');
    actingAs(User::factory()->create());

    $uuid = (string) Str::uuid();

    post('/upload/chunk', [
        'uuid' => $uuid,
        'index' => 0,
        'total' => 3,
        'name' => 'x.bin',
        'chunk' => UploadedFile::fake()->createWithContent('chunk', 'abc'),
    ])->assertOk();

    post('/upload/finalize', [
        'uuid' => $uuid,
        'total' => 3,
        'name' => 'x.bin',
    ])->assertStatus(422);
});
