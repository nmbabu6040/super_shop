<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Feature tests: full HTTP/database tests. The Vite manifest is not needed in tests.
pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
    })
    ->in('Feature');

// Unit tests still boot the framework so config() and url() are available.
pest()->extend(TestCase::class)->in('Unit');
