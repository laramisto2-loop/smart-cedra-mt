<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $environment = getenv('APP_ENV');
        $connection = getenv('DB_CONNECTION');
        $database = getenv('DB_DATABASE');

        if (
            $environment !== 'testing'
            || $connection !== 'sqlite'
            || $database !== ':memory:'
        ) {
            throw new RuntimeException(
                'Unsafe test environment blocked. '
                .'Run tests through the dedicated Docker test service.'
            );
        }

        parent::setUp();
    }
}