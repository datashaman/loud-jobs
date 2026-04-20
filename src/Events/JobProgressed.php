<?php

namespace Datashaman\LoudJobs\Events;

use Datashaman\LoudJobs\Support\Progress;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class JobProgressed implements ShouldBroadcast
{
    public function __construct(
        public string $channel,
        public Progress $progress,
        public bool $private = true,
    ) {}

    public function broadcastOn(): Channel
    {
        return $this->private
            ? new PrivateChannel($this->channel)
            : new Channel($this->channel);
    }

    public function broadcastAs(): string
    {
        return 'job.progressed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'percent' => $this->progress->percent,
            'phase' => $this->progress->phase,
            'message' => $this->progress->message,
            'step' => $this->progress->step,
            'totalSteps' => $this->progress->totalSteps,
            'itemsProcessed' => $this->progress->itemsProcessed,
            'totalItems' => $this->progress->totalItems,
            'elapsedMs' => $this->progress->elapsedMs,
            'etaMs' => $this->progress->etaMs,
            'meta' => $this->progress->meta,
        ];
    }
}
