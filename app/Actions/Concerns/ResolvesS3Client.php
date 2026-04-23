<?php

declare(strict_types=1);

namespace App\Actions\Concerns;

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

trait ResolvesS3Client
{
    private function s3Client(): S3Client
    {
        return $this->s3Disk()->getClient();
    }

    private function bucket(): string
    {
        return Config::string('filesystems.disks.'.$this->diskName().'.bucket');
    }

    private function diskName(): string
    {
        return Config::string('filesystems.default');
    }

    private function s3Disk(): AwsS3V3Adapter
    {
        $name = $this->diskName();
        $disk = Storage::disk($name);

        abort_unless(
            $disk instanceof AwsS3V3Adapter,
            503,
            "The default disk '{$name}' is not S3-compatible. Direct-to-S3 uploads require an S3 or R2 bucket — attach one in Laravel Cloud or set an s3 disk locally."
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
