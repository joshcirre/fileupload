<?php

use App\Actions\FinalizeUploadAction;
use Livewire\Attributes\Layout;
use Livewire\Component;

new #[Layout('layouts::app')] class extends Component {};

?>

<div
    class="mx-auto max-w-2xl space-y-8"
    x-data="directUploader({
        initEndpoint: @js(route('direct.init')),
        signEndpoint: @js(route('direct.sign')),
        completeEndpoint: @js(route('direct.complete')),
        abortEndpoint: @js(route('direct.abort')),
        csrfToken: @js(csrf_token()),
        guestMaxBytes: @js(FinalizeUploadAction::GUEST_MAX_BYTES),
        isGuest: @js(! auth()->check()),
    })"
>
    <div class="space-y-2 text-center">
        <flux:badge color="violet" size="sm">Demo: direct-to-S3</flux:badge>
        <flux:heading size="xl" class="!text-3xl sm:!text-4xl">Parts go straight to the bucket.</flux:heading>
        <flux:subheading class="text-base">
            Laravel only signs URLs and records the share — the bytes never hit the container.
        </flux:subheading>
    </div>

    <flux:card class="space-y-5">
        <template x-if="!result">
            <div class="space-y-5">
                <flux:file-upload x-on:change="select($event)" x-bind:disabled="uploading">
                    <flux:file-upload.dropzone
                        heading="Drop a file here or click to browse"
                        text="Each part is PUT straight to S3 via a pre-signed URL — 4 in flight."
                    />
                </flux:file-upload>

                <template x-if="file">
                    <div
                        class="flex items-start gap-3 overflow-hidden rounded-lg border bg-white p-3 shadow-xs dark:bg-white/10"
                        x-bind:class="gated
                            ? 'border-red-500'
                            : 'border-zinc-200 border-b-zinc-300/80 dark:border-white/10'"
                    >
                        <flux:icon.cloud-arrow-up class="mt-0.5 size-5 shrink-0 text-zinc-400" />
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-medium text-zinc-700 dark:text-zinc-200" x-text="file.name"></div>
                            <div class="text-xs text-zinc-500" x-text="formatBytes(file.size)"></div>
                        </div>
                        <flux:button size="sm" variant="ghost" icon="x-mark" x-on:click="clearFile" x-show="!uploading" x-cloak aria-label="Remove file" />
                    </div>
                </template>

                <template x-if="gated">
                    <flux:callout variant="warning" icon="lock-closed" x-cloak>
                        <flux:callout.heading>This file is over 20 MB</flux:callout.heading>
                        <flux:callout.text>
                            Guests can send files up to 20 MB. Create a free account to send anything larger.
                        </flux:callout.text>
                        <x-slot name="actions">
                            <flux:button variant="primary" href="{{ route('register') }}" wire:navigate>Sign up</flux:button>
                            <flux:button variant="ghost" href="{{ route('login') }}" wire:navigate>Login</flux:button>
                        </x-slot>
                    </flux:callout>
                </template>

                <template x-if="file && uploading">
                    <div class="space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-700 dark:bg-zinc-900">
                        <div class="flex items-center justify-between text-xs tabular-nums text-zinc-500">
                            <span>Part <span class="font-medium text-zinc-700 dark:text-zinc-300" x-text="completedParts"></span> / <span x-text="totalParts"></span></span>
                            <span x-text="`${percent}%`"></span>
                        </div>
                        <div class="h-1.5 w-full overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700">
                            <div class="h-full bg-accent transition-[width] duration-150 ease-out" x-bind:style="`width: ${percent}%`"></div>
                        </div>
                        <div class="flex items-center justify-between text-xs tabular-nums text-zinc-500">
                            <span x-text="`${formatBytes(bytesSent)} of ${formatBytes(file.size)}`"></span>
                            <span x-text="`${speedLabel} · ${etaLabel}`"></span>
                        </div>
                    </div>
                </template>

                <template x-if="file && !gated">
                    <flux:fieldset>
                        <flux:legend>Link expiration</flux:legend>
                        <flux:radio.group x-model="expiration" variant="segmented">
                            <flux:radio value="1" label="1 day" />
                            <flux:radio value="7" label="7 days" />
                            <flux:radio value="30" label="30 days" />
                            <flux:radio value="once" label="One-time download" />
                        </flux:radio.group>
                    </flux:fieldset>
                </template>

                <template x-if="error">
                    <flux:callout variant="danger" icon="exclamation-triangle" x-cloak>
                        <flux:callout.heading>Upload failed</flux:callout.heading>
                        <flux:callout.text x-text="error"></flux:callout.text>
                    </flux:callout>
                </template>

                <div class="flex items-center justify-end gap-2" x-show="file && !gated" x-cloak>
                    <flux:button variant="danger" icon="stop" x-on:click="cancel" x-show="uploading" x-cloak>
                        Cancel
                    </flux:button>
                    <flux:button variant="primary" icon="paper-airplane" x-on:click="start" x-bind:disabled="uploading">
                        Create share link
                    </flux:button>
                </div>
            </div>
        </template>

        <template x-if="result">
            <div class="space-y-5" x-cloak>
                <flux:callout variant="success" icon="check-circle">
                    <flux:callout.heading>Ready to share</flux:callout.heading>
                    <flux:callout.text>
                        <span x-text="result.name"></span>
                        <template x-if="result.expires_at">
                            <span> · expires <span x-text="formatExpiry(result.expires_at)"></span></span>
                        </template>
                        <template x-if="result.delete_after_first_download">
                            <span> · one-time download</span>
                        </template>
                    </flux:callout.text>
                </flux:callout>

                <flux:field>
                    <flux:label>Share link</flux:label>
                    <flux:input x-bind:value="result.url" readonly copyable />
                </flux:field>

                <div class="flex justify-end gap-2">
                    <flux:button variant="ghost" icon="arrow-path" x-on:click="reset">Send another</flux:button>
                    @auth
                        <flux:button variant="primary" icon="list-bullet" href="{{ route('shares.index') }}" wire:navigate>
                            My shares
                        </flux:button>
                    @endauth
                </div>
            </div>
        </template>
    </flux:card>

    <div class="grid gap-3 text-sm text-zinc-500 sm:grid-cols-3">
        <div class="flex items-start gap-2">
            <flux:icon.bolt class="mt-0.5 size-4 text-zinc-400" />
            <div><span class="font-medium text-zinc-700 dark:text-zinc-300">Zero proxy.</span> Bytes bypass Laravel — no 413 risk, no bandwidth double-pay.</div>
        </div>
        <div class="flex items-start gap-2">
            <flux:icon.key class="mt-0.5 size-4 text-zinc-400" />
            <div><span class="font-medium text-zinc-700 dark:text-zinc-300">Pre-signed.</span> Server signs each part URL; browser PUTs directly to S3.</div>
        </div>
        <div class="flex items-start gap-2">
            <flux:icon.squares-2x2 class="mt-0.5 size-4 text-zinc-400" />
            <div><span class="font-medium text-zinc-700 dark:text-zinc-300">Native stitching.</span> S3's <code class="text-xs">CompleteMultipartUpload</code> assembles the object.</div>
        </div>
    </div>
</div>

@script
<script>
    const PART_SIZE = 5 * 1024 * 1024;

    Alpine.data('directUploader', ({ initEndpoint, signEndpoint, completeEndpoint, abortEndpoint, csrfToken, guestMaxBytes, isGuest }) => ({
        file: null,
        uploading: false,
        error: null,
        result: null,
        expiration: '7',
        totalParts: 0,
        completedParts: 0,
        bytesSent: 0,
        speed: 0,
        lastTickAt: 0,
        lastTickBytes: 0,
        abortController: null,
        uploadId: null,
        key: null,
        uuid: null,

        get gated() {
            return isGuest && this.file && this.file.size > guestMaxBytes;
        },
        get percent() {
            if (!this.file || this.file.size === 0) return 0;
            return Math.min(100, Math.round((this.bytesSent / this.file.size) * 100));
        },
        get speedLabel() {
            return this.speed > 0 ? `${this.formatBytes(this.speed)}/s` : '—';
        },
        get etaLabel() {
            if (!this.uploading || this.speed <= 0) return '—';
            const remaining = Math.max(0, this.file.size - this.bytesSent);
            return this.formatDuration(remaining / this.speed);
        },

        select(event) {
            const f = event.target?.files?.[0];
            if (!f) return;
            this.file = f;
            this.totalParts = Math.max(1, Math.ceil(f.size / PART_SIZE));
            this.error = null;
        },

        clearFile() {
            if (this.uploading) return;
            this.file = null;
            this.totalParts = 0;
            this.error = null;
            const input = this.$el.querySelector('input[type=file]');
            if (input) input.value = '';
        },

        reset() {
            this.abortController?.abort();
            this.abortController = null;
            this.file = null;
            this.uploading = false;
            this.error = null;
            this.result = null;
            this.totalParts = 0;
            this.completedParts = 0;
            this.bytesSent = 0;
            this.speed = 0;
            this.uploadId = null;
            this.key = null;
            this.uuid = null;
            const input = this.$el.querySelector('input[type=file]');
            if (input) input.value = '';
        },

        async cancel() {
            this.abortController?.abort();
            if (this.uploadId && this.key) {
                try {
                    await fetch(abortEndpoint, {
                        method: 'POST',
                        body: JSON.stringify({ uploadId: this.uploadId, key: this.key }),
                        credentials: 'same-origin',
                        headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' },
                    });
                } catch {
                    // best-effort cleanup
                }
            }
            this.uploading = false;
            this.error = 'Upload cancelled.';
        },

        async start() {
            if (!this.file || this.uploading || this.gated) return;

            this.uploading = true;
            this.error = null;
            this.bytesSent = 0;
            this.completedParts = 0;
            this.abortController = new AbortController();
            this.lastTickAt = performance.now();
            this.lastTickBytes = 0;

            try {
                const init = await this.postJson(initEndpoint, {
                    name: this.file.name,
                    mime: this.file.type || undefined,
                });

                this.uploadId = init.uploadId;
                this.key = init.key;
                this.uuid = init.uuid;

                const etags = new Array(this.totalParts);
                let nextIndex = 0;
                let failure = null;

                const uploadOne = async () => {
                    while (failure === null && !this.abortController.signal.aborted) {
                        const i = nextIndex++;
                        if (i >= this.totalParts) return;

                        const partNumber = i + 1;
                        const start = i * PART_SIZE;
                        const end = Math.min(this.file.size, start + PART_SIZE);
                        const blob = this.file.slice(start, end);

                        for (let attempt = 1; attempt <= 3; attempt++) {
                            try {
                                const signed = await this.postJson(signEndpoint, {
                                    uploadId: this.uploadId,
                                    key: this.key,
                                    partNumber,
                                });

                                const res = await fetch(signed.url, {
                                    method: 'PUT',
                                    body: blob,
                                    signal: this.abortController.signal,
                                });

                                if (!res.ok) throw new Error(`HTTP ${res.status}`);
                                const etag = (res.headers.get('ETag') || '').replace(/"/g, '');
                                if (!etag) throw new Error('Missing ETag from S3');
                                etags[i] = etag;
                                break;
                            } catch (e) {
                                if (e.name === 'AbortError') return;
                                if (attempt === 3) {
                                    failure = new Error(`Part ${partNumber} failed after 3 attempts: ${e.message}`);
                                    this.abortController.abort();
                                    return;
                                }
                                await new Promise(r => setTimeout(r, 400 * attempt));
                            }
                        }

                        this.completedParts += 1;
                        this.bytesSent += blob.size;
                        this.updateSpeed();
                    }
                };

                const concurrency = Math.min(4, this.totalParts);
                await Promise.all(Array.from({ length: concurrency }, uploadOne));
                if (failure) throw failure;

                const parts = etags.map((etag, i) => ({ partNumber: i + 1, etag }));

                const complete = await this.postJson(completeEndpoint, {
                    uploadId: this.uploadId,
                    key: this.key,
                    uuid: this.uuid,
                    name: this.file.name,
                    mime: this.file.type || undefined,
                    parts,
                    ...(this.expiration === 'once'
                        ? { delete_after_first_download: true }
                        : { expires_in_days: Number(this.expiration) }),
                });

                this.result = complete;
            } catch (e) {
                if (e.name !== 'AbortError') {
                    this.error = e.message || String(e);
                    // best-effort abort on the server
                    if (this.uploadId && this.key) {
                        fetch(abortEndpoint, {
                            method: 'POST',
                            body: JSON.stringify({ uploadId: this.uploadId, key: this.key }),
                            credentials: 'same-origin',
                            headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' },
                        }).catch(() => {});
                    }
                }
            } finally {
                this.uploading = false;
                this.abortController = null;
            }
        },

        async postJson(url, body) {
            const res = await fetch(url, {
                method: 'POST',
                body: JSON.stringify(body),
                credentials: 'same-origin',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/json', Accept: 'application/json' },
                signal: this.abortController.signal,
            });

            if (!res.ok) {
                const payload = await res.json().catch(() => null);
                throw new Error(payload?.message || `Request failed (HTTP ${res.status}).`);
            }

            return res.json();
        },

        updateSpeed() {
            const now = performance.now();
            const dt = (now - this.lastTickAt) / 1000;
            if (dt >= 0.25) {
                this.speed = (this.bytesSent - this.lastTickBytes) / dt;
                this.lastTickAt = now;
                this.lastTickBytes = this.bytesSent;
            }
        },

        formatBytes(bytes) {
            if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
            const units = ['B', 'KB', 'MB', 'GB'];
            const i = Math.min(units.length - 1, Math.floor(Math.log(bytes) / Math.log(1024)));
            return `${(bytes / Math.pow(1024, i)).toFixed(i === 0 ? 0 : 1)} ${units[i]}`;
        },

        formatDuration(seconds) {
            if (!Number.isFinite(seconds) || seconds <= 0) return '—';
            if (seconds < 60) return `${Math.ceil(seconds)}s`;
            const m = Math.floor(seconds / 60);
            const s = Math.ceil(seconds % 60);
            return `${m}m ${s}s`;
        },

        formatExpiry(iso) {
            try {
                return new Date(iso).toLocaleString();
            } catch {
                return iso;
            }
        },
    }));
</script>
@endscript
