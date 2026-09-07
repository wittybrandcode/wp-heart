<?php
/**
 * Modular health engine framework (WH-070–WH-073).
 *
 * @package WP_Heart
 */

namespace WPHeart\Diagnostics;

use WPHeart\Diagnostics\Checks\AutoloadSizeDiagnostic;
use WPHeart\Diagnostics\Checks\CharsetDiagnostic;
use WPHeart\Diagnostics\Checks\CronHealthDiagnostic;
use WPHeart\Diagnostics\Checks\EngineMixDiagnostic;
use WPHeart\Diagnostics\Checks\ExpiredTransientsDiagnostic;
use WPHeart\Diagnostics\Checks\LargeTableDiagnostic;
use WPHeart\Diagnostics\Checks\MissingCoreTableDiagnostic;
use WPHeart\Diagnostics\Checks\MissingPrimaryKeyDiagnostic;
use WPHeart\Diagnostics\Checks\OrphanCandidateDiagnostic;
use WPHeart\Diagnostics\Checks\RedundantIndexDiagnostic;
use WPHeart\Diagnostics\Checks\TopAutoloadOptionsDiagnostic;
use WPHeart\Diagnostics\Checks\UnindexedReferenceDiagnostic;
use WPHeart\Domain\HealthIssue;
use WPHeart\Intelligence\CoreTableRecognizer;
use WPHeart\Intelligence\WpContextService;
use WPHeart\Logging\Logger;
use WPHeart\Schema\IndexInspector;
use WPHeart\Support\Severity;

/**
 * Modular health engine framework (WH-070–WH-073).
 */
class HealthEngine {
	/** @var DiagnosticInterface[] */
	private $diagnostics = array();

	/**
	 * @param DiagnosticInterface $diagnostic Diagnostic to register.
	 */
	public function register( DiagnosticInterface $diagnostic ) {
		$this->diagnostics[ $diagnostic->id() ] = $diagnostic;
	}

	/**
	 * Default engine with the Release 1.0 diagnostic set.
	 *
	 * @param IndexInspector|null      $indexes Index inspector (nullable for unit tests).
	 * @param CoreTableRecognizer|null $core Core recognizer.
	 * @param WpContextService|null    $context WP context.
	 * @return HealthEngine
	 */
	public static function with_defaults( $indexes = null, $core = null, $context = null ) {
		$engine = new self();
		$engine->register( new MissingPrimaryKeyDiagnostic() );
		$engine->register( new MissingCoreTableDiagnostic( $core, $context ) );
		$engine->register( new EngineMixDiagnostic() );
		$engine->register( new CharsetDiagnostic() );
		$engine->register( new OrphanCandidateDiagnostic() );
		$engine->register( new LargeTableDiagnostic() );
		$engine->register( new RedundantIndexDiagnostic() );
		$engine->register( new AutoloadSizeDiagnostic() );
		$engine->register( new TopAutoloadOptionsDiagnostic() );
		$engine->register( new ExpiredTransientsDiagnostic() );
		$engine->register( new CronHealthDiagnostic() );
		$engine->register( new UnindexedReferenceDiagnostic() );
		return $engine;
	}

	/**
	 * Run all registered diagnostics. One failing diagnostic never blocks others.
	 *
	 * @param array $context Diagnostic context.
	 * @return array {issues: HealthIssue[], ran: string[], generated_at: int}
	 */
	public function run( array $context ) {
		$issues = array();
		$ran    = array();
		foreach ( $this->diagnostics as $id => $diagnostic ) {
			try {
				$found = $diagnostic->check( $context );
				foreach ( (array) $found as $issue ) {
					if ( $issue instanceof HealthIssue ) {
						$issues[] = $issue;
					}
				}
				$ran[] = $id;
			} catch ( \Exception $e ) {
				Logger::log( Logger::CHANNEL_DIAGNOSTICS, 'diagnostic failed', array( 'id' => $id ) );
			}
		}

		usort(
			$issues,
			static function ( HealthIssue $a, HealthIssue $b ) {
				$ra = Severity::rank( $a->severity() );
				$rb = Severity::rank( $b->severity() );
				if ( $ra === $rb ) {
					return 0;
				}
				return $ra < $rb ? 1 : -1;
			}
		);

		return array(
			'issues'       => $issues,
			'ran'          => $ran,
			'generated_at' => time(),
		);
	}

	/**
	 * @return DiagnosticInterface[]
	 */
	public function registered() {
		return $this->diagnostics;
	}
}
