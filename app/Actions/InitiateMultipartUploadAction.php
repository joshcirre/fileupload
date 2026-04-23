<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Concerns\ResolvesS3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

final class InitiateMultipartUploadAction
{
    use ResolvesS3Client;

    public function handle(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'mime' => ['nullable', 'string', 'max:255'],
        ])->validate();

        $name = $request->string('name')->toString();
        $mime = $request->filled('mime') ? $request->string('mime')->toString() : 'application/octet-stream';

        $uuid = Str::orderedUuid()->toString();
        $key = 'shares/'.$uuid.'-'.$this->safeName($name);

        $result = $this->s3Client()->createMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $key,
            'ContentType' => $mime,
            'ContentDisposition' => 'attachment; filename="'.$name.'"',
        ]);

        return response()->json([
            'uploadId' => $result['UploadId'],
            'key' => $key,
            'uuid' => $uuid,
        ]);
    }
}
