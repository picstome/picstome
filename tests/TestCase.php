<?php

namespace Tests;

use App\Services\StorageUsageService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // StorageUsageService memoises the team total for the duration of a
        // real request. Tests share one PHP process for several requests, so
        // each test starts with an empty memo.
        StorageUsageService::flushRequestMemo();
    }
}
