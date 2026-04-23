<?php

use App\Models\Share;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {
    public function delete(Share $share): void
    {
        Gate::authorize('delete', $share);

        $share->deleteFileAndRecord();
    }

    /** @return Collection<int, Share> */
    #[Computed]
    public function shares(): Collection
    {
        return Share::query()
            ->whereBelongsTo(auth()->user())
            ->latest()
            ->get();
    }
};

?>

<div class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-start justify-between">
        <div class="space-y-1">
            <flux:heading size="xl">Your shares</flux:heading>
            <flux:subheading>Links you've created, their status, and the download counts.</flux:subheading>
        </div>
        <flux:button variant="primary" icon="arrow-up-tray" href="{{ route('home') }}" wire:navigate>New share</flux:button>
    </div>

    @if ($this->shares->isEmpty())
        <flux:card class="text-center">
            <flux:icon.inbox class="mx-auto size-10 text-zinc-400" />
            <flux:heading size="lg" class="mt-2">No shares yet</flux:heading>
            <flux:subheading>Upload a file to get your first share link.</flux:subheading>
        </flux:card>
    @else
        <flux:card>
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>File</flux:table.column>
                    <flux:table.column>Status</flux:table.column>
                    <flux:table.column>Downloads</flux:table.column>
                    <flux:table.column>Created</flux:table.column>
                    <flux:table.column class="w-24"></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($this->shares as $share)
                        <flux:table.row :key="$share->id">
                            <flux:table.cell>
                                <div class="min-w-0">
                                    <div class="truncate font-medium">{{ $share->original_name }}</div>
                                    <flux:text size="sm" class="text-zinc-500">
                                        {{ Number::fileSize($share->size, precision: 1) }}
                                    </flux:text>
                                </div>
                            </flux:table.cell>
                            <flux:table.cell>
                                @if ($share->isConsumed())
                                    <flux:badge color="zinc" size="sm">Downloaded</flux:badge>
                                @elseif ($share->isExpired())
                                    <flux:badge color="red" size="sm">Expired</flux:badge>
                                @elseif ($share->delete_after_first_download)
                                    <flux:badge color="amber" size="sm">One-time</flux:badge>
                                @elseif ($share->expires_at)
                                    <flux:badge color="emerald" size="sm">Expires {{ $share->expires_at->diffForHumans() }}</flux:badge>
                                @else
                                    <flux:badge color="emerald" size="sm">Active</flux:badge>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>
                                <span class="tabular-nums">{{ $share->download_count }}</span>
                            </flux:table.cell>
                            <flux:table.cell class="text-zinc-500">
                                {{ $share->created_at?->diffForHumans() }}
                            </flux:table.cell>
                            <flux:table.cell>
                                <div class="flex justify-end gap-1">
                                    @if ($share->isAvailable())
                                        <flux:tooltip content="Copy link">
                                            <flux:button
                                                size="sm"
                                                variant="ghost"
                                                icon="link"
                                                x-on:click="
                                                    navigator.clipboard.writeText(@js(route('share.show', ['share' => $share->id])))
                                                    $flux.toast({ text: 'Link copied', variant: 'success' })
                                                "
                                            />
                                        </flux:tooltip>
                                    @endif

                                    <flux:tooltip content="Delete">
                                        <flux:button
                                            size="sm"
                                            variant="ghost"
                                            icon="trash"
                                            wire:click="delete('{{ $share->id }}')"
                                            wire:confirm="Delete this share? The file will be removed immediately."
                                        />
                                    </flux:tooltip>
                                </div>
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </flux:card>
    @endif
</div>
