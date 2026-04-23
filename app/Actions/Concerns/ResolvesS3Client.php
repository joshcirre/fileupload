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
        $this->ensureS3Configured();

        $disk = Storage::disk('s3');

        abort_unless(
            $disk instanceof AwsS3V3Adapter,
            503,
            'The default S3 disk driver is not the AWS S3 v3 adapter.'
        );

        return $disk->getClient();
    }

    private function bucket(): string
    {
        $this->ensureS3Configured();

        return Config::string('filesystems.disks.s3.bucket');
    }

    private function ensureS3Configured(): void
    {
        abort_unless(
            filled(config('filesystems.disks.s3.bucket'))
            && filled(config('filesystems.disks.s3.key'))
            && filled(config('filesystems.disks.s3.secret')),
            503,
            'S3 is not configured. Set AWS_BUCKET, AWS_ACCESS_KEY_ID, and AWS_SECRET_ACCESS_KEY in your environment.'
        );
    }

    private function safeName(string $name): string
    {
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $base = pathinfo($name, PATHINFO_FILENAME);

        return Str::slug($base).($ext !== '' ? '.'.mb_strtolower($ext) : '');
    }
}
