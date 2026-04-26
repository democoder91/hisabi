<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Ai\Embeddings;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Avoid hitting real embedding providers during tests; the fake gateway
        // produces deterministic-shape (random direction) unit vectors so the
        // hybrid semantic search hybrid path remains exercisable in test runs.
        Embeddings::fake();
    }
}

