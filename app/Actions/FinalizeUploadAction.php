<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

final readonly class FinalizeUploadAction
{
    public const GUEST_MAX_BYTES = 20 * 1024 * 1024;

    public function handle(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'uuid' => ['required', 'string', 'regex:/^[0-9a-f-]{36}$/i'],
            'total' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:255'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'delete_after_first_download' => ['nullable', 'boolean'],
        ])->validate();

        $uuid = $request->string('uuid')->toString();
        $total = $request->integer('total');
        $name = $request->string('name')->toString();
        $mime = $request->filled('mime') ? $request->string('mime')->toString() : null;
        $expiresInDays = $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null;
        $deleteAfterFirst = $request->boolean('delete_after_first_download');

        $diskName = Config::string('filesystems.default');
        $disk = Storage::disk($diskName);
        $chunkDir = 'chunks/'.$uuid;

        abort_unless(
            count($disk->files($chunkDir)) === $total,
            422,
            'Missing chunks — re-upload any gaps before finalizing.'
        );

        $safeName = $this->safeName($name);
        $finalPath = 'shares/'.$uuid.'-'.$safeName;

        $temp = tmpfile();
        if ($temp === false) {
            throw new FileException('Could not create temp file for assembly.');
        }

        try {
            for ($i = 0; $i < $total; $i++) {
                $in = $disk->readStream($chunkDir.'/'.$i);
                if ($in === null) {
                    throw new FileException(sprintf('Missing chunk %d.', $i));
                }

                stream_copy_to_stream($in, $temp);
                fclose($in);
            }

            rewind($temp);
            $disk->writeStream($finalPath, $temp);
        } finally {
            fclose($temp);
        }

        $disk->deleteDirectory($chunkDir);

        $finalSize = $disk->size($finalPath);
        $user = $request->user();

        if ($user === null && $finalSize > self::GUEST_MAX_BYTES) {
            $disk->delete($finalPath);
            abort(403, 'Create a free account to send files over 20 MB.');
        }

        $share = Share::query()->create([
            'user_id' => $user?->id,
            'original_name' => $name,
            'disk' => $diskName,
            'path' => $finalPath,
            'size' => $finalSize,
            'mime_type' => $mime,
            'expires_at' => $expiresInDays !== null ? now()->addDays($expiresInDays) : null,
            'delete_after_first_download' => $deleteAfterFirst,
        ]);

        return response()->json([
            'id' => $share->id,
            'url' => route('share.show', ['share' => $share->id]),
            'download_url' => route('share.download', ['share' => $share->id]),
            'name' => $share->original_name,
            'size' => $share->size,
            'expires_at' => $share->expires_at?->toIso8601String(),
            'delete_after_first_download' => $share->delete_after_first_download,
        ]);
    }

    private function safeName(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return Str::slug($base).($ext !== '' ? '.'.mb_strtolower($ext) : '');
    }
}
