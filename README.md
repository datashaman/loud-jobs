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

`ProgressTracker` takes an emit callback and publishes a `Progress` value each time you enter a phase, tick item progress, or attach a note. The method names follow Symfony's `ProgressBar` parlance: `phase()`, `advance()`, `setProgress()`, `setMaxSteps()`, `finish()`.

```php
use Datashaman\LoudJobs\Support\Progress;
use Datashaman\LoudJobs\Support\ProgressTracker;

$tracker = new ProgressTracker(function (Progress $p) {
    logger()->info('job.progress', (array) $p);
});

// Equal weights — each phase is 1/3 of the run.
$tracker->defineSteps(['Fetching', 'Transforming', 'Uploading']);

// Or weight them — "uploading is 5x as slow as fetching".
$tracker->defineSteps([
    'Fetching'     => 1,
    'Transforming' => 3,
    'Uploading'    => 5,
]);

$tracker->phase('Fetching', max: count($rows));
foreach ($rows as $row) {
    // ...work...
    $tracker->advance();                         // +1 (or $tracker->advance(10))
}

$tracker->phase('Transforming');                 // max unknown — item counts still flow
$tracker->note('cache warmed', ['hit_rate' => 0.92]);

$tracker->phase('Uploading', max: $n);
$tracker->setProgress($n);                       // absolute jump
$tracker->finish();                              // force current phase to 100%
```

### Methods

- `defineSteps(array $steps)` — three shapes: `['A', 'B']` (equal weight), `['A' => 1, 'B' => 3]` (keyed), or `[['name' => 'A', 'weight' => 1], ...]` (explicit).
- `phase(string $name, ?int $max = null)` — enter a phase and optionally set its item max. Resets item counters.
- `advance(int $by = 1)` — tick forward by N items.
- `setProgress(int $current)` — jump to an absolute item position within the phase.
- `setMaxSteps(int $max)` — update or set the max mid-phase; recomputes the step fraction.
- `finish()` — force the current phase to 100% of its weight (and snap `itemsProcessed` to `max` if set).
- `note(string $message, array $meta = [])` — emit a message and meta without changing progress.

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
