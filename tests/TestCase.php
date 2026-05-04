<?php
/**
 * Base PHPUnit test case for the unit suite. Activates Brain Monkey
 * before each test and tears it down after, so individual test files
 * can stub WordPress functions via {@see \Brain\Monkey\Functions} and
 * {@see \Brain\Monkey\Actions} / {@see \Brain\Monkey\Filters} without
 * having to repeat the lifecycle plumbing.
 *
 * Tests that need to stub WP functions extend this class instead of
 * extending PHPUnit's `TestCase` directly.
 *
 * @package WPMUS\Tests
 */

declare(strict_types=1);

namespace Tests;

use Brain\Monkey;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	// Mockery's PHPUnit integration trait registers a tearDown hook
	// that calls Mockery::close() AND counts every Mockery expectation
	// as a PHPUnit assertion. Without this, tests that use only Mockery
	// `shouldReceive(...)` get flagged as risky by
	// `beStrictAboutTestsThatDoNotTestAnything`.
	use MockeryPHPUnitIntegration;

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}
}
