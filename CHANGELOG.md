# Changelog

All notable changes to `loud-jobs` will be documented in this file.

## Unreleased

- Initial `ProgressTracker` and `Progress` value object under `Datashaman\LoudJobs\Support`.
- Weighted phase progress with three input shapes for `defineSteps` (positional, keyed, explicit).
- Symfony-style API: `phase()`, `advance()`, `setProgress()`, `setMaxSteps()`, `finish()`, `note()`.
- `phase()` clamps out-of-range names to the last defined step.
- `advance()` / `setProgress()` / `setMaxSteps()` before any `phase()` emit item counts with `percent`/`phase`/`step` left `null`.
- Pest suite covering every option: input shapes, weighting, clamping, item-tick variants, max-update recomputation, finish snapping, note emission, and ETA extremes.
