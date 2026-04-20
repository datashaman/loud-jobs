# loud-jobs

[![Latest Version on Packagist](https://img.shields.io/packagist/v/datashaman/loud-jobs.svg?style=flat-square)](https://packagist.org/packages/datashaman/loud-jobs)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/datashaman/loud-jobs/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/datashaman/loud-jobs/actions?query=workflow%3Arun-tests+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/datashaman/loud-jobs.svg?style=flat-square)](https://packagist.org/packages/datashaman/loud-jobs)

Structured progress reporting for Laravel queued jobs. Define weighted phases, tick through per-item work, and emit structured `Progress` events (percent, phase, step, items, elapsed, ETA) to any callback — logs, broadcasting, Horizon metadata, cache, or your own sink.

## Installation

```bash
composer require datashaman/loud-jobs
```

## Usage

`ProgressTracker` is meant to live inside a queued job. You wire it to an emit callback — typically `broadcast()` or `event()` — and it publishes a `Progress` value each time you enter a phase, tick item progress, or attach a note. Method names follow Symfony's `ProgressBar` parlance: `phase()`, `advance()`, `setProgress()`, `setMaxSteps()`, `finish()`.

### Real-time progress in a queued job

This is the intended flow: a queued job reports weighted, real-time progress to a browser via Laravel broadcasting. `loud-jobs` ships a `JobProgressed` event (implements `ShouldBroadcast`) so you don't have to write one.

```php
// app/Jobs/ExportLargeReport.php

namespace App\Jobs;

use Datashaman\LoudJobs\Events\JobProgressed;
use Datashaman\LoudJobs\Support\Progress;
use Datashaman\LoudJobs\Support\ProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExportLargeReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $channel = "reports.user.{$this->userId}";

        $tracker = new ProgressTracker(function (Progress $p) use ($channel) {
            broadcast(new JobProgressed($channel, $p));
        });

        $tracker->defineSteps([
            'Fetching'     => 1,
            'Transforming' => 3,
            'Rendering'    => 5,
            'Uploading'    => 2,
        ]);

        $rows = $this->fetch();
        $tracker->phase('Fetching', max: count($rows));
        foreach ($rows as $row) {
            $this->fetchOne($row);
            $tracker->advance();
        }

        $tracker->phase('Transforming', max: count($rows));
        $tracker->note('cache warmed', ['hit_rate' => 0.92]);
        foreach ($rows as $row) {
            $this->transform($row);
            $tracker->advance();
        }

        $tracker->phase('Rendering');
        $pdf = $this->renderPdf($rows);

        $tracker->phase('Uploading', max: $pdf->size());
        $this->uploadInChunks($pdf, onChunk: fn (int $bytes) => $tracker->advance($bytes));
        $tracker->finish();
    }
}
```

Authorise the channel in `routes/channels.php`:

```php
Broadcast::channel('reports.user.{userId}', function ($user, int $userId) {
    return $user->id === $userId;
});
```

Listen on the frontend with Laravel Echo (Reverb/Pusher/Ably — any Echo-compatible driver):

```js
// resources/js/progress.js
import Echo from 'laravel-echo';

Echo.private(`reports.user.${window.userId}`)
    .listen('.job.progressed', (e) => {
        // e is the broadcastWith() payload
        updateBar(e.percent, e.phase, e.etaMs);
        if (e.message) showToast(e.message, e.meta);
    });
```

Each phase transition, `advance()`, `setProgress()`, `setMaxSteps()`, `finish()`, and `note()` inside the job emits a `JobProgressed` event carrying the full `Progress` snapshot — percent, phase, step, items, elapsed, ETA, message, meta.

### `ProgressTracker` in isolation

The tracker doesn't require broadcasting — any callback works. Useful for log lines, Horizon tags, cache writes, or tests:

```php
$tracker = new ProgressTracker(function (Progress $p) {
    logger()->info('job.progress', (array) $p);
});

$tracker->defineSteps(['Fetching', 'Transforming', 'Uploading']);
$tracker->phase('Fetching', max: count($rows));
foreach ($rows as $row) {
    $tracker->advance();
}
$tracker->phase('Transforming');                 // max unknown — item counts still flow
$tracker->note('cache warmed', ['hit_rate' => 0.92]);
$tracker->phase('Uploading', max: $n);
$tracker->setProgress($n);                       // absolute jump
$tracker->finish();                              // force current phase to 100%
```

### `ProgressTracker` methods

- `defineSteps(array $steps)` — three shapes: `['A', 'B']` (equal weight), `['A' => 1, 'B' => 3]` (keyed), or `[['name' => 'A', 'weight' => 1], ...]` (explicit).
- `phase(string $name, ?int $max = null)` — enter a phase and optionally set its item max. Resets item counters.
- `advance(int $by = 1)` — tick forward by N items.
- `setProgress(int $current)` — jump to an absolute item position within the phase.
- `setMaxSteps(int $max)` — update or set the max mid-phase; recomputes the step fraction.
- `finish()` — force the current phase to 100% of its weight (and snap `itemsProcessed` to `max` if set).
- `note(string $message, array $meta = [])` — emit a message and meta without changing progress.

### `JobProgressed` event

`Datashaman\LoudJobs\Events\JobProgressed` implements `ShouldBroadcast` and carries the full `Progress` on the wire.

```php
new JobProgressed(
    channel: "reports.user.{$userId}",   // channel name (without the "private-" prefix)
    progress: $progress,                  // the Progress snapshot from the tracker
    private: true,                        // false for a public Channel
);
```

Broadcasts as `job.progressed`. The payload (`broadcastWith()`) mirrors the `Progress` fields: `percent`, `phase`, `message`, `step`, `totalSteps`, `itemsProcessed`, `totalItems`, `elapsedMs`, `etaMs`, `meta`.

### Behaviour notes

- Weights are **relative**. `[1, 3, 5]` and `[10, 30, 50]` emit identical percentages.
- `phase()` with an unknown name past the last defined step is clamped — it won't run `step` past `totalSteps`.
- `advance()` / `setProgress()` / `setMaxSteps()` called before any `phase()` is tolerated: `itemsProcessed` / `totalItems` flow through, but `percent`, `phase`, and `step` stay `null` until a phase is named.
- A phase without a `max` still accepts `advance()` calls — item counts flow, but the weighted percent only moves when you transition to the next phase or call `finish()`.
- `etaMs` is computed from weighted elapsed time. It's `null` at 0% and 100%, and only as accurate as your weights.

## Testing

```bash
composer test
```

## Changelog

See [CHANGELOG.md](CHANGELOG.md).

## Security

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Marlin Forbes](https://github.com/datashaman)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
