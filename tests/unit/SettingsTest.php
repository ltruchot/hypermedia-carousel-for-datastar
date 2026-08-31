<?php
/**
 * Unit tests for the one setting the plugin has.
 *
 * @package HypermediaCarouselForDatastar
 */

declare(strict_types=1);

namespace HCFD\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use HCFD\Settings;
use PHPUnit\Framework\TestCase;

/**
 * @covers \HCFD\Settings
 */
final class SettingsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();
		Functions\when( 'absint' )->alias( static fn( $v ) => abs( (int) $v ) );
		Functions\when( '__' )->returnArg( 1 );
		Functions\when( 'add_settings_error' )->justReturn( null );
		Functions\when( 'wp_parse_args' )->alias(
			static fn( $args, $defaults ) => array_merge( $defaults, array_filter( (array) $args, static fn( $v ) => null !== $v ) )
		);
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * @dataProvider provide_submitted_values
	 */
	public function test_the_stored_interval_is_always_within_range( $submitted, int $expected ): void {
		$this->assertSame( $expected, Settings::sanitize( array( 'interval' => $submitted ) )['interval'] );
	}

	public static function provide_submitted_values(): array {
		return array(
			'valeur normale'     => array( 5, 5 ),
			'le plancher'        => array( 3, 3 ),
			'le plafond'         => array( 60, 60 ),
			'sous le plancher'   => array( 1, 3 ),
			'zero'               => array( 0, 3 ),
			// Refuse, ne reflete pas : absint() ferait de -10 dix secondes.
			'negatif'            => array( -10, 3 ),
			'au-dessus'          => array( 3600, 60 ),
			// Pas un nombre : on revient au defaut livre, pas au plancher.
			'du texte'           => array( 'abc', 5 ),
			'du texte numerique' => array( '12', 12 ),
			'un flottant'        => array( 7.9, 7 ),
			'un tableau'         => array( array( 1, 2 ), 5 ),
			'null'               => array( null, 5 ),
		);
	}

	public function test_a_submission_shaped_like_nothing_expected_falls_back_to_the_defaults(): void {
		foreach ( array( 'not an array', array(), array( 'autre' => 9 ) ) as $nonsense ) {
			$this->assertSame( Settings::DEFAULTS, Settings::sanitize( $nonsense ) );
		}
	}

	public function test_the_sanitiser_never_returns_a_key_it_did_not_declare(): void {
		// A settings callback that let an extra key through would store it, and
		// whatever wrote it would be read back later as if the plugin had put
		// it there.
		$out = Settings::sanitize(
			array(
				'interval'   => 5,
				'transition' => 'fade',
				'evil'       => '<script>',
				'x'          => array(),
			)
		);

		$this->assertSame( array_keys( Settings::DEFAULTS ), array_keys( $out ) );
		$this->assertIsInt( $out['interval'] );
		$this->assertIsString( $out['transition'] );
	}

	/**
	 * @dataProvider provide_stored_rows
	 */
	public function test_a_corrupt_stored_row_still_yields_a_usable_interval( $stored, int $expected ): void {
		// The option can have been written by an older version, by WP-CLI, or
		// by hand. Reading it through get_option() alone would put whatever it
		// holds straight into an HTML attribute and into a timer.
		Functions\when( 'get_option' )->justReturn( $stored );

		$this->assertSame( $expected, Settings::interval() );
	}

	public static function provide_stored_rows(): array {
		return array(
			'absente'        => array( array(), 5 ),
			'correcte'       => array( array( 'interval' => 9 ), 9 ),
			'chaine'         => array( array( 'interval' => '9' ), 9 ),
			'enorme'         => array( array( 'interval' => 999999 ), 60 ),
			'negative'       => array( array( 'interval' => -1 ), 3 ),
			'flottante'      => array( array( 'interval' => 8.7 ), 8 ),
			'du texte'       => array( array( 'interval' => 'drop table' ), 5 ),
			'pas un tableau' => array( 'corrompu', 5 ),
			'cle inattendue' => array( array( 'autre' => 1 ), 5 ),
		);
	}

	/**
	 * @dataProvider provide_submitted_transitions
	 */
	public function test_only_a_transition_this_version_can_play_is_stored( $submitted, string $expected ): void {
		$this->assertSame( $expected, Settings::sanitize( array( 'transition' => $submitted ) )['transition'] );
	}

	public static function provide_submitted_transitions(): array {
		return array(
			'fondu'             => array( 'fade', 'fade' ),
			'aucune'            => array( 'none', 'none' ),
			// Un nom inconnu poserait un view-transition-name que personne n'a
			// defini, et le navigateur ferait un fondu de la page entiere.
			'un nom invente'    => array( 'slide', 'fade' ),
			'une injection'     => array( 'fade;--x', 'fade' ),
			'la mauvaise casse' => array( 'FADE', 'fade' ),
			'un nombre'         => array( 1, 'fade' ),
			'un tableau'        => array( array( 'fade' ), 'fade' ),
			'null'              => array( null, 'fade' ),
		);
	}

	/**
	 * @dataProvider provide_stored_transitions
	 */
	public function test_a_corrupt_stored_transition_falls_back_to_the_default( $stored, string $expected ): void {
		Functions\when( 'get_option' )->justReturn( $stored );

		$this->assertSame( $expected, Settings::transition() );
	}

	public static function provide_stored_transitions(): array {
		return array(
			'absente'        => array( array(), 'fade' ),
			'aucune'         => array( array( 'transition' => 'none' ), 'none' ),
			'inconnue'       => array( array( 'transition' => 'zoom' ), 'fade' ),
			'pas un tableau' => array( 'corrompu', 'fade' ),
		);
	}

	public function test_the_default_transition_is_one_the_plugin_offers(): void {
		// The select is built from TRANSITIONS; a default outside it would show
		// no option selected and change on the first save.
		$this->assertContains( Settings::DEFAULTS['transition'], Settings::TRANSITIONS );
		$this->assertSame( array( 'fade', 'none' ), Settings::TRANSITIONS );
	}

	public function test_the_bounds_are_the_ones_the_form_advertises(): void {
		// The number input carries min and max; server-side clamping has to
		// agree with them, or the field promises something the server refuses.
		$this->assertSame( 3, Settings::MIN_INTERVAL );
		$this->assertSame( 60, Settings::MAX_INTERVAL );
		$this->assertGreaterThanOrEqual( Settings::MIN_INTERVAL, Settings::DEFAULTS['interval'] );
		$this->assertLessThanOrEqual( Settings::MAX_INTERVAL, Settings::DEFAULTS['interval'] );
	}
}
