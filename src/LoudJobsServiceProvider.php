<?php

namespace Datashaman\LoudJobs;

use Datashaman\LoudJobs\Commands\LoudJobsCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LoudJobsServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('loud-jobs')
            ->hasConfigFile()
            ->hasViews()
            ->hasMigration('create_loud_jobs_table')
            ->hasCommand(LoudJobsCommand::class);
    }
}
