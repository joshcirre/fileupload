<?php

declare(strict_types=1);

use App\Models\Share;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

/**
 * @param  array<string, mixed>  $attrs
 */
function seedShareWithFile(array $attrs = []): Share
{
    Storage::fake('local');
    $share = Share::factory()->create($attrs);
    Storage::disk($share->disk)->put($share->path, 'file-contents');

    return $share;
}

test('public landing page shows an available share', function () {
    $share = seedShareWithFile();

    get(route('share.show', ['share' => $share->id]))
        ->assertOk()
        ->assertSee($share->original_name)
        ->assertSee('Download');
});

test('download streams the file and increments the counter', function () {
    $share = seedShareWithFile();

    $response = get(route('share.download', ['share' => $share->id]));

    $response->assertOk();
    expect($response->streamedContent())->toBe('file-contents');

    $share->refresh();
    expect($share->download_count)->toBe(1)
        ->and($share->first_downloaded_at)->not->toBeNull();
});

test('expired shares 410', function () {
    $share = seedShareWithFile(['expires_at' => now()->subHour()]);

    get(route('share.download', ['share' => $share->id]))->assertStatus(410);
    get(route('share.show', ['share' => $share->id]))->assertOk()->assertSee('Share expired');
});

test('one-time shares 410 after first download', function () {
    $share = seedShareWithFile([
        'delete_after_first_download' => true,
        'expires_at' => null,
    ]);

    get(route('share.download', ['share' => $share->id]))->assertOk();
    get(route('share.download', ['share' => $share->id]))->assertStatus(410);
});

test('sweep command deletes expired and consumed shares', function () {
    $alive = seedShareWithFile(['expires_at' => now()->addDay()]);
    $expired = seedShareWithFile(['expires_at' => now()->subMinute()]);
    $consumed = seedShareWithFile([
        'delete_after_first_download' => true,
        'expires_at' => null,
        'first_downloaded_at' => now()->subMinute(),
    ]);

    $expiredPath = $expired->path;
    $consumedPath = $consumed->path;

    expect(Artisan::call('shares:sweep'))->toBe(0);

    expect(Share::query()->find($alive->id))->not->toBeNull()
        ->and(Share::query()->find($expired->id))->toBeNull()
        ->and(Share::query()->find($consumed->id))->toBeNull();

    Storage::disk('local')->assertMissing($expiredPath);
    Storage::disk('local')->assertMissing($consumedPath);
});

test('users can delete their own shares from the management page', function () {
    $user = User::factory()->create();
    $share = seedShareWithFile(['user_id' => $user->id]);

    Livewire::actingAs($user);
    Livewire::test('pages::shares')->call('delete', $share->id);

    expect(Share::query()->find($share->id))->toBeNull();
    Storage::disk('local')->assertMissing($share->path);
});
