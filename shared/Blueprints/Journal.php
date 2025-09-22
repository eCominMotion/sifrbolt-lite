<?php
// phpcs:ignoreFile WordPress.Files.FileName.NotHyphenatedLowercase,WordPress.Files.FileName.InvalidClassFileName

/**
 * Lightweight blueprint journal placeholder for Spark tier.
 *
 * @package SifrBolt\Shared\Blueprints
 */
declare(strict_types=1);

namespace SifrBolt\Shared\Blueprints;

if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stores blueprint activity locally for Spark tier dashboards.
 */
final class Journal {

	private const OPTION = 'sifrbolt_blueprint_journal';

	/**
	 * Returns recent blueprint events recorded locally.
	 *
	 * @param int $limit Maximum number of events to return.
	 *
	 * @return array<int, array<string, mixed>>
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
	 *
	 * @param string $event_id Event identifier that triggered the rollback.
	 * @param string $operator Operator requesting the rollback.
	 *
	 * @return array<string, mixed>|null
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
	 *
	 * @return array<int, array<string, mixed>>
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
	 *
	 * @param array<int, array<string, mixed>> $events Events to persist.
	 */
	private function save_events( array $events ): void {
		update_option( self::OPTION, array_values( $events ), false );
	}
}
