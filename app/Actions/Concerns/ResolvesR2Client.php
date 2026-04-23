<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Laravel Cloud's object storage is Cloudflare R2 exposed over the S3-compatible API,
 * which is why the underlying client is still the AWS SDK's S3Client.
 */
trait ResolvesR2Client
{
    private function r2Client(): S3Client
    {
        return $this->r2Disk()->getClient();
    }

    private function bucket(): string
    {
        return Config::string('filesystems.disks.'.$this->diskName().'.bucket');
    }

    private function diskName(): string
    {
        return Config::string('filesystems.default');
    }

    private function r2Disk(): AwsS3V3Adapter
    {
        $name = $this->diskName();
        $disk = Storage::disk($name);

        abort_unless(
            $disk instanceof AwsS3V3Adapter,
            503,
            "The default disk '{$name}' is not an R2 bucket. Attach a Laravel Object Storage bucket in Laravel Cloud (or an S3-compatible disk locally) and redeploy."
        );

        return $disk;
    }

    private function safeName(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return Str::slug($base).($ext !== '' ? '.'.mb_strtolower($ext) : '');
    }
}
