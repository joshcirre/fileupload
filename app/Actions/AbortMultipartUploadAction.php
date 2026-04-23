<?php

declare(strict_types=1);

namespace App\Actions;

use App\Actions\Concerns\ResolvesR2Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

final class AbortMultipartUploadAction
{
    use ResolvesR2Client;

    public function handle(Request $request): Response
    {
        Validator::make($request->all(), [
            'uploadId' => ['required', 'string', 'max:1024'],
            'key' => ['required', 'string', 'starts_with:shares/', 'max:512'],
        ])->validate();

        $this->r2Client()->abortMultipartUpload([
            'Bucket' => $this->bucket(),
            'Key' => $request->string('key')->toString(),
            'UploadId' => $request->string('uploadId')->toString(),
        ]);

        return response()->noContent();
    }
}
