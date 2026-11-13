<?php
/**
 * Tests for the address part used in the rate limits.
 *
 * @package Formwerk
 */

declare( strict_types=1 );

namespace Formwerk\Tests\Unit\Submissions;

use Formwerk\Submissions\Handler;
use PHPUnit\Framework\TestCase;

/**
 * Handler::network_of().
 */
final class NetworkTest extends TestCase {

	public function test_ipv4_stays_whole(): void {
		$this->assertSame( '203.0.113.7', Handler::network_of( '203.0.113.7' ) );
	}

	public function test_ipv6_addresses_of_one_network_count_together(): void {
		$this->assertSame( '2001:db8:1:2::/64', Handler::network_of( '2001:db8:1:2:aaaa:bbbb:cccc:dddd' ) );
		$this->assertSame( Handler::network_of( '2001:db8:1:2::1' ), Handler::network_of( '2001:db8:1:2:ffff::9' ) );
		$this->assertNotSame( Handler::network_of( '2001:db8:1:2::1' ), Handler::network_of( '2001:db8:1:3::1' ) );
	}

	public function test_anything_else_is_kept(): void {
		$this->assertSame( 'proxy-said-this', Handler::network_of( 'proxy-said-this' ) );
	}
}
