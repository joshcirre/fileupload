<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Share;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class DownloadShareAction
{
    public function handle(Share $share): StreamedResponse
    {
        abort_unless($share->isAvailable(), 410, 'This share is no longer available.');

        DB::transaction(function () use ($share): void {
            $locked = Share::query()->whereKey($share->id)->lockForUpdate()->firstOrFail();

            abort_unless($locked->isAvailable(), 410, 'This share is no longer available.');

            $locked->forceFill([
                'download_count' => $locked->download_count + 1,
                'first_downloaded_at' => $locked->first_downloaded_at ?? now(),
            ])->save();

            $share->setRawAttributes($locked->getAttributes(), sync: true);
        });

        return Storage::disk($share->disk)->download(
            $share->path,
            $share->original_name,
            ['Content-Type' => $share->mime_type ?? 'application/octet-stream'],
        );
    }
}
