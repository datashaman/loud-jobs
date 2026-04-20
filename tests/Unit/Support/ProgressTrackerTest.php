<?php

use Datashaman\LoudJobs\Support\Progress;
use Datashaman\LoudJobs\Support\ProgressTracker;

function makeTracker(array &$emitted): ProgressTracker
{
    return new ProgressTracker(function (Progress $p) use (&$emitted) {
        $emitted[] = $p;
    });
}

describe('defineSteps', function () {
    it('accepts positional names with equal weights', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B', 'C']);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->phase('C');
        $tracker->finish();

        expect(array_map(fn (Progress $p) => $p->percent, $emitted))
            ->toBe([0.0, 33.3, 66.7, 100.0]);
    });

    it('accepts keyed weights', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A' => 1, 'B' => 2]);

        $tracker->phase('A');
        $tracker->phase('B');

        expect(end($emitted)->percent)->toBe(33.3);
    });

    it('accepts explicit name/weight entries', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps([
            ['name' => 'A', 'weight' => 1],
            ['name' => 'B', 'weight' => 2],
        ]);

        $tracker->phase('A');
        $tracker->phase('B');

        expect(end($emitted)->percent)->toBe(33.3);
    });

    it('treats keyed and explicit forms as equivalent', function () {
        $keyed = [];
        $k = makeTracker($keyed);
        $k->defineSteps(['A' => 1, 'B' => 2]);
        $k->phase('A');
        $k->phase('B');

        $explicit = [];
        $e = makeTracker($explicit);
        $e->defineSteps([
            ['name' => 'A', 'weight' => 1],
            ['name' => 'B', 'weight' => 2],
        ]);
        $e->phase('A');
        $e->phase('B');

        expect(array_map(fn (Progress $p) => $p->percent, $keyed))
            ->toBe(array_map(fn (Progress $p) => $p->percent, $explicit));
    });

    it('rejects non-positive weights', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A' => 0]);
    })->throws(InvalidArgumentException::class);

    it('rejects malformed entries', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps([['weight' => 1]]);
    })->throws(InvalidArgumentException::class);

    it('ignores weight ratio scale', function () {
        $small = [];
        $s = makeTracker($small);
        $s->defineSteps(['A' => 1, 'B' => 3, 'C' => 5, 'D' => 2]);
        $s->phase('A');
        $s->phase('B');
        $s->phase('C');

        $big = [];
        $b = makeTracker($big);
        $b->defineSteps(['A' => 10, 'B' => 30, 'C' => 50, 'D' => 20]);
        $b->phase('A');
        $b->phase('B');
        $b->phase('C');

        expect(end($small)->percent)->toBe(end($big)->percent);
    });
});

describe('phase()', function () {
    it('enters a named phase and resets item counters', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B']);

        $tracker->phase('A', max: 10);
        $tracker->advance(5);
        $tracker->phase('B');

        $last = end($emitted);
        expect($last->phase)->toBe('B')
            ->and($last->step)->toBe(2)
            ->and($last->itemsProcessed)->toBe(0)
            ->and($last->totalItems)->toBeNull();
    });

    it('accepts an optional max argument', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);

        $tracker->phase('A', max: 50);

        expect(end($emitted)->totalItems)->toBe(50);
    });

    it('clamps currentStep when advancing past the last defined step with an unknown name', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B']);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->phase('Unknown');

        $last = end($emitted);
        expect($last->step)->toBe(2)
            ->and($last->totalSteps)->toBe(2)
            ->and($last->phase)->toBe('Unknown');
    });

    it('honours named phases that jump to earlier steps', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B', 'C']);

        $tracker->phase('C');
        $tracker->phase('A');

        $last = end($emitted);
        expect($last->step)->toBe(1)->and($last->phase)->toBe('A');
    });
});

describe('advance()', function () {
    it('defaults to ticking by 1', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);
        $tracker->phase('A', max: 4);

        $tracker->advance();
        $tracker->advance();

        expect(end($emitted)->itemsProcessed)->toBe(2)
            ->and(end($emitted)->percent)->toBe(50.0);
    });

    it('accepts a custom step size', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);
        $tracker->phase('A', max: 10);

        $tracker->advance(3);
        $tracker->advance(7);

        expect(end($emitted)->itemsProcessed)->toBe(10)
            ->and(end($emitted)->percent)->toBe(100.0);
    });

    it('without a max still increments itemsProcessed but leaves stepFraction at 0', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A' => 1, 'B' => 1]);

        $tracker->phase('A');
        $tracker->advance(5);

        $last = end($emitted);
        expect($last->itemsProcessed)->toBe(5)
            ->and($last->totalItems)->toBeNull()
            ->and($last->percent)->toBe(0.0);
    });

    it('called before any phase() emits item counts with null phase/percent/step', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B']);

        $tracker->setMaxSteps(10);
        $tracker->advance(5);

        $last = end($emitted);
        expect($last->phase)->toBeNull()
            ->and($last->step)->toBeNull()
            ->and($last->percent)->toBeNull()
            ->and($last->itemsProcessed)->toBe(5)
            ->and($last->totalItems)->toBe(10);
    });
});

describe('setProgress()', function () {
    it('jumps to an absolute item position within the phase', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A' => 1, 'B' => 3, 'C' => 5, 'D' => 2]);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->phase('C', max: 10);
        $tracker->setProgress(5);

        expect(end($emitted)->percent)
            ->toBe(round(((1 + 3 + 2.5) / 11) * 100, 1));
    });

    it('clamps stepFraction at 1.0 when asked to exceed max', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);
        $tracker->phase('A', max: 10);

        $tracker->setProgress(1000);

        expect(end($emitted)->percent)->toBe(100.0);
    });
});

describe('setMaxSteps()', function () {
    it('updates the max mid-phase and recomputes stepFraction', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);

        $tracker->phase('A');
        $tracker->advance(5);
        expect(end($emitted)->percent)->toBe(0.0);

        $tracker->setMaxSteps(10);
        expect(end($emitted)->percent)->toBe(50.0);
    });
});

describe('finish()', function () {
    it('forces the current phase to 100% of its weight', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B']);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->finish();

        expect(end($emitted)->percent)->toBe(100.0);
    });

    it('snaps itemsProcessed to max when a max is set', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);
        $tracker->phase('A', max: 42);

        $tracker->finish();

        expect(end($emitted)->itemsProcessed)->toBe(42)
            ->and(end($emitted)->totalItems)->toBe(42);
    });

    it('is a no-op when called before any phase()', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);

        $tracker->finish();

        expect($emitted)->toBe([]);
    });
});

describe('note()', function () {
    it('emits a message and meta without changing progress', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A']);

        $tracker->phase('A', max: 10);
        $tracker->advance(3);
        $beforePercent = end($emitted)->percent;

        $tracker->note('cache warmed', ['hit_rate' => 0.92]);

        $last = end($emitted);
        expect($last->message)->toBe('cache warmed')
            ->and($last->meta)->toBe(['hit_rate' => 0.92])
            ->and($last->percent)->toBe($beforePercent);
    });
});

describe('ETA', function () {
    it('is null at 0% and at 100%', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B']);

        $tracker->phase('A');
        expect($emitted[0]->etaMs)->toBeNull();

        $tracker->phase('B');
        $tracker->finish();
        expect(end($emitted)->etaMs)->toBeNull();
    });

    it('is a positive integer mid-run', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B', 'C', 'D']);

        $tracker->phase('A');
        usleep(10_000);
        $tracker->phase('B');

        expect(end($emitted)->etaMs)->toBeInt()->toBeGreaterThan(0);
    });
});

describe('weighted progress', function () {
    it('progresses linearly with equal weights', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A', 'B', 'C']);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->phase('C');
        $tracker->finish();

        expect(array_map(fn (Progress $p) => $p->percent, $emitted))
            ->toBe([0.0, 33.3, 66.7, 100.0]);
    });

    it('weights percent by step weight when ticking within a phase', function () {
        $emitted = [];
        $tracker = makeTracker($emitted);
        $tracker->defineSteps(['A' => 1, 'B' => 3, 'C' => 5, 'D' => 2]);

        $tracker->phase('A');
        $tracker->phase('B');
        $tracker->phase('C', max: 10);
        $tracker->advance(5);

        expect(end($emitted)->percent)
            ->toBe(round(((1 + 3 + 2.5) / 11) * 100, 1));
    });
});
