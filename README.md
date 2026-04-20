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

`ProgressTracker` takes an emit callback and publishes a `Progress` value each time you advance a phase, tick an item counter, or attach a note.

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

$tracker->advance('Fetching');
foreach ($rows as $i => $row) {
    // ...work...
    $tracker->tick($i + 1, count($rows));
}

$tracker->advance('Transforming');
$tracker->note('cache warmed', ['hit_rate' => 0.92]);

$tracker->advance('Uploading');
$tracker->tick(count($rows), count($rows));
```

### Behaviour notes

- Weights are **relative**. `[1, 3, 5]` and `[10, 30, 50]` emit identical percentages.
- `advance()` with an unknown phase name is clamped to the last defined step — it won't run `step` past `totalSteps`.
- `tick()` called before the first `advance()` is tolerated: `itemsProcessed` / `totalItems` flow through, but `percent`, `phase`, and `step` stay `null` until a phase is named.
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
