<?php

namespace Datashaman\LoudJobs\Commands;

use Illuminate\Console\Command;

class LoudJobsCommand extends Command
{
    public $signature = 'loud-jobs';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
