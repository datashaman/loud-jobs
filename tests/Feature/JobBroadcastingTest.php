<?php

use Datashaman\LoudJobs\Events\JobProgressed;
use Datashaman\LoudJobs\Support\Progress;
use Datashaman\LoudJobs\Support\ProgressTracker;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Support\Facades\Event;

/**
 * Stands in for a real queued job — any ShouldQueue class that wires a
 * ProgressTracker to dispatch JobProgressed events inside handle().
 */
final class FakeExportJob
{
    public function __construct(public int $userId) {}

    public function handle(): void
    {
        $channel = "reports.user.{$this->userId}";

        $tracker = new ProgressTracker(function (Progress $p) use ($channel) {
            event(new JobProgressed($channel, $p));
        });

        $tracker->defineSteps([
            'Fetching' => 1,
            'Transforming' => 3,
            'Uploading' => 2,
        ]);

        $rows = range(1, 10);
        $tracker->phase('Fetching', max: count($rows));
        foreach ($rows as $_) {
            $tracker->advance();
        }

        $tracker->phase('Transforming');
        $tracker->note('cache warmed', ['hit_rate' => 0.92]);

        $tracker->phase('Uploading', max: count($rows));
        $tracker->setProgress(count($rows));
        $tracker->finish();
    }
}

it('dispatches JobProgressed events from a queued job\'s handle()', function () {
    Event::fake([JobProgressed::class]);

    (new FakeExportJob(userId: 42))->handle();

    Event::assertDispatched(JobProgressed::class);

    Event::assertDispatched(
        JobProgressed::class,
        fn (JobProgressed $e) => $e->channel === 'reports.user.42'
            && $e->progress->phase === 'Fetching'
            && $e->progress->percent === 0.0
    );

    Event::assertDispatched(
        JobProgressed::class,
        fn (JobProgressed $e) => $e->progress->phase === 'Transforming'
            && $e->progress->message === 'cache warmed'
            && $e->progress->meta === ['hit_rate' => 0.92]
    );

    Event::assertDispatched(
        JobProgressed::class,
        fn (JobProgressed $e) => $e->progress->phase === 'Uploading'
            && $e->progress->percent === 100.0
            && $e->progress->itemsProcessed === 10
    );
});

it('broadcasts on a private channel by default', function () {
    $progress = new Progress(percent: 42.0, phase: 'Fetching');

    $event = new JobProgressed('reports.user.42', $progress);

    expect($event->broadcastOn())->toBeInstanceOf(PrivateChannel::class)
        ->and($event->broadcastAs())->toBe('job.progressed');
});

it('broadcasts on a public channel when private is false', function () {
    $progress = new Progress(percent: 42.0, phase: 'Fetching');

    $event = new JobProgressed('reports.public', $progress, private: false);

    expect($event->broadcastOn())->toBeInstanceOf(Channel::class)
        ->and($event->broadcastOn())->not->toBeInstanceOf(PrivateChannel::class);
});

it('serialises the full Progress payload for the wire', function () {
    $progress = new Progress(
        percent: 55.5,
        phase: 'Transforming',
        message: 'halfway there',
        step: 2,
        totalSteps: 3,
        itemsProcessed: 55,
        totalItems: 100,
        elapsedMs: 1200,
        etaMs: 900,
        meta: ['batch' => 7],
    );

    $payload = (new JobProgressed('ch', $progress))->broadcastWith();

    expect($payload)->toBe([
        'percent' => 55.5,
        'phase' => 'Transforming',
        'message' => 'halfway there',
        'step' => 2,
        'totalSteps' => 3,
        'itemsProcessed' => 55,
        'totalItems' => 100,
        'elapsedMs' => 1200,
        'etaMs' => 900,
        'meta' => ['batch' => 7],
    ]);
});
