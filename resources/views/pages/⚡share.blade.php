<?php

use App\Models\Share;
use Illuminate\Support\Number;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::auth')] class extends Component {
    public Share $share;

    public function mount(Share $share): void
    {
        $this->share = $share;
    }
};

?>

<div class="space-y-6 p-6">
    <flux:card class="space-y-5">
        <div class="space-y-1">
            <flux:heading size="lg">Someone shared a file with you</flux:heading>
            <flux:subheading>Click download to grab it.</flux:subheading>
        </div>

        @if (! $share->isAvailable())
            <flux:callout variant="danger" icon="no-symbol">
                <flux:callout.heading>
                    @if ($share->isConsumed())
                        Already downloaded
                    @else
                        Share expired
                    @endif
                </flux:callout.heading>
                <flux:callout.text>
                    @if ($share->isConsumed())
                        This share was set to delete itself after the first download.
                    @else
                        This link expired {{ $share->expires_at?->diffForHumans() }}.
                    @endif
                </flux:callout.text>
            </flux:callout>
        @else
            <div class="flex items-center gap-4 rounded-lg border border-zinc-200 bg-zinc-50 p-4 dark:border-zinc-700 dark:bg-zinc-900">
                <flux:icon.document-arrow-down class="size-10 text-zinc-400" />
                <div class="min-w-0 flex-1">
                    <div class="truncate font-medium">{{ $share->original_name }}</div>
                    <flux:text size="sm" class="text-zinc-500">
                        {{ Number::fileSize($share->size, precision: 1) }}
                        @if ($share->expires_at)
                            · expires {{ $share->expires_at->diffForHumans() }}
                        @endif

                        @if ($share->delete_after_first_download)
                            · one-time download
                        @endif
                    </flux:text>
                </div>
            </div>

            <flux:button variant="primary" icon="arrow-down-tray" href="{{ route('share.download', ['share' => $share->id]) }}" class="w-full">
                Download
            </flux:button>
        @endif
    </flux:card>
</div>
