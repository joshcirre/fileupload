# Upchunk

A WeTransfer-style file share built on Laravel 12 + Livewire 4 + Flux Pro. **Two upload paths, same UX** — so you can see the difference between "chunks through your app" and "direct-to-R2" side by side.

> **The problem it solves.** Every host puts a cap on request body size (Laravel Cloud's is around 100 MB). A 120 MB upload through the usual `<input type="file">` returns a 413. Upchunk shows two ways around it.

## How it works

### Path 1 — Chunking through Laravel (`/`)

Slice the file in the browser into 5 MB chunks and POST them one at a time (4 in flight). Each request is tiny, so nothing trips the proxy. When all chunks are up, one finalize call reassembles them server-side.

```
browser ──(5 MB chunks × N, 4 in flight)──▶ Laravel ──▶ default disk
                                                │
                                                ▼
browser ◀────────── share URL ──────── Laravel ─── finalize: stream
                                                    chunks → stitch →
                                                    create Share row
```

**Bytes route:** `browser → Laravel → storage`. On Cloud you pay bandwidth twice during finalize (read chunks back, write final). Works on any filesystem — `local` in dev, R2 on Cloud.

### Path 2 — Direct-to-R2 multipart (`/direct`)

Laravel signs URLs; R2 does the rest. The browser uploads each part directly to the bucket via pre-signed PUT URLs. Laravel only sees small JSON round-trips.

```
browser ──▶ Laravel: "initiate"           ─── R2 ─▶ uploadId
browser ──▶ Laravel: "sign part N"        ─── R2 ─▶ signed PUT URL
browser ──(part N, direct)────────────────────▶ R2   (captures ETag)
   ... 4 parts in flight ...
browser ──▶ Laravel: "complete" + ETags   ─── R2 ─▶ stitched object
browser ◀── share URL ──────── Laravel ─── HEAD for size → Share row
```

**Bytes route:** `browser → R2`. The file never hits the Laravel container.

### Same everything else

Both paths create the same `Share` row, use the same download proxy (`GET /s/{uuid}/download` enforces expiration + one-time rules), and share the same UI patterns. Only the *upload* differs.

## File map

If you're reading or demoing the code, here's what to open.

**Path 1 — chunking**
| File | Role |
|---|---|
| `resources/views/pages/⚡home.blade.php` | Alpine `chunkUploader` — slice + worker pool |
| `app/Actions/StoreUploadChunkAction.php` | Receive one chunk → `storeAs` |
| `app/Actions/FinalizeUploadAction.php` | Stream chunks → stitch → create `Share` |

**Path 2 — direct-to-R2**
| File | Role |
|---|---|
| `resources/views/pages/⚡direct.blade.php` | Alpine `directUploader` — init → sign → PUT → complete |
| `app/Actions/InitiateMultipartUploadAction.php` | `CreateMultipartUpload` |
| `app/Actions/SignMultipartPartAction.php` | Pre-signed PUT URL per part (30 min) |
| `app/Actions/CompleteMultipartUploadAction.php` | `CompleteMultipartUpload` → `HeadObject` → `Share` |
| `app/Actions/AbortMultipartUploadAction.php` | Cleanup on cancel |
| `app/Actions/Concerns/ResolvesR2Client.php` | Grabs the `S3Client` off the default disk |

**Shared**
| File | Role |
|---|---|
| `app/Models/Share.php` | Share record + `reapable` scope + availability helpers |
| `app/Actions/DownloadShareAction.php` | Streamed download + `lockForUpdate` counter |
| `app/Console/Commands/SweepExpiredShares.php` | `php artisan shares:sweep` |
| `app/Policies/SharePolicy.php` | `delete` authorization for the `/shares` page |
| `resources/views/pages/⚡shares.blade.php` | Your shares, statuses, copy link, delete |
| `resources/views/pages/⚡share.blade.php` | Public `/s/{uuid}` landing |
| `routes/web.php` | Route layout — best single file for comparing the two paths |

## Guest vs. signed-in

| | Guest | Signed in |
|---|---|---|
| Max file size | 20 MB | no app-level cap |
| Owner on share row | `user_id = null` | `user_id = auth()->id()` |
| See your shares later | no | `/shares` |
| Authorization | N/A | `SharePolicy@delete` |

The 20 MB cap is checked server-side in both upload paths (`FinalizeUploadAction::GUEST_MAX_BYTES`). If a guest busts it, the assembled file is deleted and the endpoint returns `403`.

## Expiration options

Set on the upload form:

- **1 / 7 / 30 days** — `expires_at` stamped; `SweepExpiredShares` deletes the file + row past that.
- **One-time download** — `delete_after_first_download = true`; first `GET /s/{uuid}/download` marks it consumed (via `lockForUpdate` transaction), subsequent hits return `410`. Sweep deletes the file.

## Setup

```bash
composer setup
```

That runs the Fission installer: deps, `.env`, app key, SQLite, migrations, Bun install, Vite build. Then:

```bash
composer dev   # server + queue + logs + vite
```

Head to `http://localhost:8000`.

**Local note on `/direct`:** the direct-to-R2 page will return `503` locally until you either (a) point the default disk at a real R2/S3 bucket, or (b) deploy to Laravel Cloud. That's intentional — there's no way to pre-sign a URL for a local filesystem. The chunking page on `/` works locally on the `local` disk.

## Deployment (Laravel Cloud)

1. **Attach a Laravel Object Storage bucket** to your environment. Cloud provisions an R2 bucket and injects `LARAVEL_CLOUD_DISK_CONFIG`, which registers it as a Laravel disk with whatever name you pick (e.g. `private`). Mark it the default so `Storage::disk()` resolves to R2.
2. **Deploy.** Both upload paths work immediately — chunking writes `chunks/{uuid}/*` to R2 and assembles the final object via `writeStream()`; direct-to-R2 uses the SDK directly against the same bucket.
3. **CORS — this is the part that bites people.** On the bucket's settings page:
   - **Expose headers: `ETag`** (non-negotiable — the browser reads it off each direct-PUT response)
   - **Allowed methods: `PUT`** (plus whatever else Cloud pre-selects)
   - **Allowed origins:** Laravel Cloud auto-adds your env's domain; only add custom origins if you need local dev against the real bucket
4. **Schedule the sweep.** `routes/console.php` already wires `shares:sweep` every 5 minutes; make sure the scheduler is running on Cloud.

If `/direct` returns "The default disk '…' is not an R2 bucket," the default disk isn't an S3-compatible adapter — check `config('filesystems.default')` and the `LARAVEL_CLOUD_DISK_CONFIG` env var.

## Tests & quality

```bash
composer test      # typos + pest + pint --test + phpstan + rector dry-run
composer fix       # phpstan + rector + pint + prettier
```

Pest covers: public home, guest upload ≤ 20 MB, guest blocked > 20 MB, authed large upload, one-time share, missing-chunks 422, download happy path, expired 410, consumed 410, sweep command, delete-from-management, and `/direct` validation + 503 when no R2 is attached.

## Stack

- PHP 8.4 · Laravel 12 · Livewire 4 (SFC with `⚡` prefix) · Livewire Flux Pro · Tailwind CSS v4
- Pest 4 · PHPStan + Larastan · Rector · Pint
- AWS SDK for PHP + `league/flysystem-aws-s3-v3` (for R2 on Cloud)

## License

MIT.
