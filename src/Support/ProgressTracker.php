<?php

namespace Datashaman\LoudJobs\Support;

use Closure;
use InvalidArgumentException;

class ProgressTracker
{
    /** @var array<int, array{name: string, weight: float}> */
    private array $steps = [];

    private int $currentStep = 0;

    private float $stepFraction = 0.0;

    private ?string $phase = null;

    private ?int $maxSteps = null;

    private int $currentItems = 0;

    private float $startedAt;

    public function __construct(private Closure $emit)
    {
        $this->startedAt = microtime(true);
    }

    /**
     * Define steps with optional weights.
     *
     * Three accepted shapes:
     *   ['Fetching', 'Transforming', 'Uploading']                 // equal weights
     *   ['Fetching' => 1, 'Transforming' => 5, 'Uploading' => 2]  // named weights
     *   [['name' => 'Fetching', 'weight' => 1], ...]              // explicit
     */
    public function defineSteps(array $steps): void
    {
        $normalized = [];

        foreach ($steps as $key => $value) {
            if (is_int($key) && is_string($value)) {
                $normalized[] = ['name' => $value, 'weight' => 1.0];
            } elseif (is_string($key) && is_numeric($value)) {
                if ($value <= 0) {
                    throw new InvalidArgumentException("Step '{$key}' must have a positive weight.");
                }
                $normalized[] = ['name' => $key, 'weight' => (float) $value];
            } elseif (is_array($value) && isset($value['name'])) {
                $normalized[] = [
                    'name' => $value['name'],
                    'weight' => (float) ($value['weight'] ?? 1.0),
                ];
            } else {
                throw new InvalidArgumentException('Invalid step definition.');
            }
        }

        $this->steps = $normalized;
    }

    public function phase(string $name, ?int $max = null): void
    {
        $idx = $this->findStep($name);

        if ($idx !== null) {
            $this->currentStep = $idx;
        } elseif ($this->steps === [] || $this->currentStep < count($this->steps) - 1) {
            $this->currentStep++;
        }

        $this->phase = $name;
        $this->maxSteps = $max;
        $this->currentItems = 0;
        $this->stepFraction = 0.0;
        $this->report();
    }

    public function advance(int $by = 1): void
    {
        $this->currentItems += $by;
        $this->recomputeFraction();
        $this->report();
    }

    public function setProgress(int $current): void
    {
        $this->currentItems = $current;
        $this->recomputeFraction();
        $this->report();
    }

    public function setMaxSteps(int $max): void
    {
        $this->maxSteps = $max;
        $this->recomputeFraction();
        $this->report();
    }

    public function finish(): void
    {
        if ($this->phase === null) {
            return;
        }

        if ($this->maxSteps !== null) {
            $this->currentItems = $this->maxSteps;
        }
        $this->stepFraction = 1.0;
        $this->report();
    }

    public function note(string $message, array $meta = []): void
    {
        $this->report(message: $message, meta: $meta);
    }

    private function recomputeFraction(): void
    {
        if ($this->maxSteps !== null && $this->maxSteps > 0) {
            $this->stepFraction = min($this->currentItems / $this->maxSteps, 1.0);
        }
    }

    private function findStep(string $phase): ?int
    {
        foreach ($this->steps as $i => $step) {
            if ($step['name'] === $phase) {
                return $i;
            }
        }

        return null;
    }

    private function report(?string $message = null, array $meta = []): void
    {
        $totalSteps = count($this->steps) ?: null;
        $hasPhase = $this->phase !== null;
        $percent = $totalSteps && $hasPhase ? $this->calculatePercent() : null;

        $elapsedMs = (int) ((microtime(true) - $this->startedAt) * 1000);
        $etaMs = $this->calculateEta($elapsedMs, $percent);

        ($this->emit)(new Progress(
            percent: $percent,
            phase: $this->phase,
            message: $message,
            step: $totalSteps && $hasPhase ? $this->currentStep + 1 : null,
            totalSteps: $totalSteps,
            itemsProcessed: $hasPhase || $this->currentItems > 0 ? $this->currentItems : null,
            totalItems: $this->maxSteps,
            elapsedMs: $elapsedMs,
            etaMs: $etaMs,
            meta: $meta,
        ));
    }

    private function calculatePercent(): float
    {
        $totalWeight = array_sum(array_column($this->steps, 'weight'));
        if ($totalWeight <= 0) {
            return 0.0;
        }

        $completedWeight = 0.0;
        for ($i = 0; $i < $this->currentStep; $i++) {
            $completedWeight += $this->steps[$i]['weight'];
        }

        $currentWeight = $this->steps[$this->currentStep]['weight'] ?? 0.0;
        $weightedProgress = $completedWeight + ($currentWeight * $this->stepFraction);

        return round(min(($weightedProgress / $totalWeight) * 100, 100), 1);
    }

    private function calculateEta(int $elapsedMs, ?float $percent): ?int
    {
        if ($percent === null || $percent <= 0 || $percent >= 100) {
            return null;
        }

        return (int) (($elapsedMs / $percent) * (100 - $percent));
    }
}
