<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Share;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('shares:sweep')]
#[Description('Delete expired shares and consumed one-time shares from storage.')]
final class SweepExpiredShares extends Command
{
    public function handle(): int
    {
        $count = 0;

        Share::query()
            ->reapable()
            ->chunkById(200, function ($shares) use (&$count): void {
                foreach ($shares as $share) {
                    $share->deleteFileAndRecord();
                    $count++;
                }
            });

        $this->info(sprintf('Swept %d share(s).', $count));

        return self::SUCCESS;
    }
}
