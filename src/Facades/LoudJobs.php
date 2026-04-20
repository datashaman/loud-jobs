<?php

namespace Datashaman\LoudJobs\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Datashaman\LoudJobs\LoudJobs
 */
class LoudJobs extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Datashaman\LoudJobs\LoudJobs::class;
    }
}
