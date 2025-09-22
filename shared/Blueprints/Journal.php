<?php
/**
 * Lightweight blueprint journal placeholder for Spark tier.
 */

declare(strict_types=1);

namespace SifrBolt\Shared\Blueprints;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

final class Journal {

	private const OPTION = 'sifrbolt_blueprint_journal';

	/**
	 * Returns recent blueprint events recorded locally.
	 */
	public function recent( int $limit = 25 ): array {
		$events = $this->load_events();
		$events = array_reverse( $events );
		if ( $limit > 0 ) {
			$events = array_slice( $events, 0, $limit );
		}
		return $events;
	}

	/**
	 * Records a rollback event referencing a prior entry.
	 */
	public function record_rollback( string $event_id, string $operator ): ?array {
		$events = $this->load_events();
		$found  = null;
		foreach ( $events as $entry ) {
			if ( isset( $entry['id'] ) && (string) $entry['id'] === (string) $event_id ) {
				$found = $entry;
				break;
			}
		}

		if ( null === $found ) {
			return null;
		}

		$rollback = array(
			'id'        => uniqid( 'rollback_', true ),
			'mode'      => 'rollback',
			'reference' => $event_id,
			'operator'  => $operator,
			'timestamp' => time(),
		);

		$events[] = $rollback;
		$this->save_events( $events );

		return $rollback;
	}

	/**
	 * Loads stored events from WordPress options.
	 */
	private function load_events(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			return array();
		}
		return $stored;
	}

	/**
	 * Persists events to WordPress options.
	 */
	private function save_events( array $events ): void {
		update_option( self::OPTION, array_values( $events ), false );
	}
}
