# Changelog

All notable changes to `loud-jobs` will be documented in this file.

## Unreleased

- Initial `ProgressTracker` and `Progress` value object under `Datashaman\LoudJobs\Support`.
- Weighted phase progress with three input shapes (positional, keyed, explicit).
- `advance()` clamps out-of-range phase names to the last defined step.
- `tick()` before any `advance()` emits item counts with `percent`/`phase`/`step` left `null`.
- Pest test suite covering weighting, clamping, ETA behaviour, and shape equivalence.
