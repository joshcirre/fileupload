<?php

declare(strict_types=1);

namespace App\Actions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

final readonly class StoreUploadChunkAction
{
    public function handle(Request $request): JsonResponse
    {
        Validator::make($request->all(), [
            'uuid' => ['required', 'string', 'regex:/^[0-9a-f-]{36}$/i'],
            'index' => ['required', 'integer', 'min:0'],
            'total' => ['required', 'integer', 'min:1'],
            'name' => ['required', 'string', 'max:255'],
            'chunk' => ['required', 'file', 'max:6144'],
        ])->validate();

        $uuid = $request->string('uuid')->toString();
        $index = $request->integer('index');
        $total = $request->integer('total');

        $diskName = Config::string('filesystems.default');
        $disk = Storage::disk($diskName);
        $dir = 'chunks/'.$uuid;
        $disk->makeDirectory($dir);

        $request->file('chunk')->storeAs(
            $dir,
            (string) $index,
            ['disk' => $diskName]
        );

        return response()->json([
            'uuid' => $uuid,
            'received' => count($disk->files($dir)),
            'total' => $total,
        ]);
    }
}
