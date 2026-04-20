<?php

use Datashaman\LoudJobs\Support\Progress;
use Datashaman\LoudJobs\Support\ProgressTracker;

function makeTracker(array &$emitted): ProgressTracker
{
    return new ProgressTracker(function (Progress $p) use (&$emitted) {
        $emitted[] = $p;
    });
}

it('reports equal-weight progress linearly', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B', 'C']);

    $tracker->advance('A');
    $tracker->advance('B');
    $tracker->advance('C');
    $tracker->tick(1, 1);

    $percents = array_map(fn (Progress $p) => $p->percent, $emitted);

    expect($percents)->toBe([0.0, 33.3, 66.7, 100.0]);
});

it('weights progress by step weight', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A' => 1, 'B' => 3, 'C' => 5, 'D' => 2]);

    $tracker->advance('A');
    $tracker->advance('B');
    $tracker->advance('C');
    $tracker->tick(5, 10);

    $last = end($emitted);

    expect($last->percent)->toBe(round(((1 + 3 + 2.5) / 11) * 100, 1));
});

it('clamps currentStep when advancing past the last defined step with an unknown phase', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B']);

    $tracker->advance('A');
    $tracker->advance('B');
    $tracker->advance('Unknown');

    $last = end($emitted);

    expect($last->step)->toBe(2)
        ->and($last->totalSteps)->toBe(2)
        ->and($last->phase)->toBe('Unknown');
});

it('still honours named phases that point to earlier steps', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B', 'C']);

    $tracker->advance('C');
    $tracker->advance('A');

    $last = end($emitted);

    expect($last->step)->toBe(1)
        ->and($last->phase)->toBe('A');
});

it('tolerates tick() called before any advance()', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B']);

    $tracker->tick(5, 10);

    $last = end($emitted);

    expect($last->percent)->toBeNull()
        ->and($last->phase)->toBeNull()
        ->and($last->step)->toBeNull()
        ->and($last->itemsProcessed)->toBe(5)
        ->and($last->totalItems)->toBe(10);
});

it('leaves ETA null at 0% and 100%', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B']);

    $tracker->advance('A');
    $tracker->advance('B');
    $tracker->tick(1, 1);

    expect($emitted[0]->etaMs)->toBeNull()
        ->and(end($emitted)->etaMs)->toBeNull();
});

it('produces a finite ETA mid-run', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A', 'B', 'C', 'D']);

    $tracker->advance('A');
    usleep(10_000);
    $tracker->advance('B');

    $last = end($emitted);

    expect($last->etaMs)->toBeInt()->toBeGreaterThan(0);
});

it('treats keyed weights and explicit weights equivalently', function () {
    $keyedEmitted = [];
    $keyed = makeTracker($keyedEmitted);
    $keyed->defineSteps(['A' => 1, 'B' => 2]);
    $keyed->advance('A');
    $keyed->advance('B');

    $explicitEmitted = [];
    $explicit = makeTracker($explicitEmitted);
    $explicit->defineSteps([
        ['name' => 'A', 'weight' => 1],
        ['name' => 'B', 'weight' => 2],
    ]);
    $explicit->advance('A');
    $explicit->advance('B');

    $keyedPercents = array_map(fn (Progress $p) => $p->percent, $keyedEmitted);
    $explicitPercents = array_map(fn (Progress $p) => $p->percent, $explicitEmitted);

    expect($keyedPercents)->toBe($explicitPercents);
});

it('rejects non-positive weights', function () {
    $emitted = [];
    $tracker = makeTracker($emitted);
    $tracker->defineSteps(['A' => 0]);
})->throws(InvalidArgumentException::class);

it('ignores weight ratio scale', function () {
    $smallEmitted = [];
    $small = makeTracker($smallEmitted);
    $small->defineSteps(['A' => 1, 'B' => 3, 'C' => 5, 'D' => 2]);
    $small->advance('A');
    $small->advance('B');
    $small->advance('C');

    $bigEmitted = [];
    $big = makeTracker($bigEmitted);
    $big->defineSteps(['A' => 10, 'B' => 30, 'C' => 50, 'D' => 20]);
    $big->advance('A');
    $big->advance('B');
    $big->advance('C');

    $smallLast = end($smallEmitted);
    $bigLast = end($bigEmitted);

    expect($smallLast->percent)->toBe($bigLast->percent);
});
