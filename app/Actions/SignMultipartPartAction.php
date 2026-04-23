<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Concerns\ResolvesS3Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class SignMultipartPartAction
{
    use ResolvesS3Client;

    public function handle(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'uploadId' => ['required', 'string', 'max:1024'],
            'key' => ['required', 'string', 'starts_with:shares/', 'max:512'],
            'partNumber' => ['required', 'integer', 'min:1', 'max:10000'],
        ])->validate();

        $client = $this->s3Client();

        $command = $client->getCommand('UploadPart', [
            'Bucket' => $this->bucket(),
            'Key' => $request->string('key')->toString(),
            'UploadId' => $request->string('uploadId')->toString(),
            'PartNumber' => $request->integer('partNumber'),
        ]);

        $signed = $client->createPresignedRequest($command, '+30 minutes');

        return response()->json([
            'url' => (string) $signed->getUri(),
        ]);
    }
}
