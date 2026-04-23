<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Concerns\ResolvesS3Client;
use App\Models\Share;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class CompleteMultipartUploadAction
{
    use ResolvesS3Client;

    public function handle(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'uploadId' => ['required', 'string', 'max:1024'],
            'key' => ['required', 'string', 'starts_with:shares/', 'max:512'],
            'uuid' => ['required', 'string', 'regex:/^[0-9a-f-]{36}$/i'],
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:255'],
            'parts' => ['required', 'array', 'min:1'],
            'parts.*.partNumber' => ['required', 'integer', 'min:1', 'max:10000'],
            'parts.*.etag' => ['required', 'string', 'max:128'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:30'],
            'delete_after_first_download' => ['nullable', 'boolean'],
        ])->validate();

        $key = $request->string('key')->toString();
        $uploadId = $request->string('uploadId')->toString();
        $name = $request->string('name')->toString();
        $mime = $request->filled('mime') ? $request->string('mime')->toString() : null;
        $expiresInDays = $request->filled('expires_in_days') ? $request->integer('expires_in_days') : null;
        $deleteAfterFirst = $request->boolean('delete_after_first_download');

        /** @var array<int, array{partNumber: int, etag: string}> $parts */
        $parts = $request->array('parts');

        $orderedParts = collect($parts)
            ->sortBy('partNumber')
            ->map(fn (array $p): array => [
                'PartNumber' => $p['partNumber'],
                'ETag' => $p['etag'],
            ])
            ->values()
            ->all();

        $client = $this->s3Client();
        $bucket = $this->bucket();

        $client->completeMultipartUpload([
            'Bucket' => $bucket,
            'Key' => $key,
            'UploadId' => $uploadId,
            'MultipartUpload' => ['Parts' => $orderedParts],
        ]);

        $head = $client->headObject(['Bucket' => $bucket, 'Key' => $key]);
        $contentLength = $head['ContentLength'] ?? 0;
        $finalSize = is_numeric($contentLength) ? (int) $contentLength : 0;
        $user = $request->user();

        if ($user === null && $finalSize > FinalizeUploadAction::GUEST_MAX_BYTES) {
            $client->deleteObject(['Bucket' => $bucket, 'Key' => $key]);
            abort(403, 'Create a free account to send files over 20 MB.');
        }

        $share = Share::query()->create([
            'user_id' => $user?->id,
            'original_name' => $name,
            'disk' => 's3',
            'path' => $key,
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
}
