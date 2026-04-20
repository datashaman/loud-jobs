<?php

namespace Datashaman\LoudJobs\Support;

final readonly class Progress
{
    public function __construct(
        public ?float $percent = null,
        public ?string $phase = null,
        public ?string $message = null,
        public ?int $step = null,
        public ?int $totalSteps = null,
        public ?int $itemsProcessed = null,
        public ?int $totalItems = null,
        public int $elapsedMs = 0,
        public ?int $etaMs = null,
        public array $meta = [],
    ) {}
}
