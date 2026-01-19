<?php

namespace Simply_Static;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Simply Static Path Mapping Model
 *
 * Stores original → clean path mappings to ensure consistency across regenerations.
 * This is critical for deterministic static site generation.
 */
class Path_Mapping extends Model {

	/**
	 * Database table name.
	 *
	 * @var string
	 */
	protected static $table_name = 'path_mappings';

	/**
	 * Table columns.
	 *
	 * @var array
	 */
	protected static $columns = array(
		'id'            => 'BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT',
		'original_path' => 'VARCHAR(2048) NOT NULL',
		'clean_path'    => 'VARCHAR(2048) NOT NULL',
		'created_at'    => "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'",
		'updated_at'    => "DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00'"
	);

	/**
	 * Indexes for columns.
	 *
	 * @var array
	 */
	protected static $indexes = array(
		'PRIMARY KEY  (id)',
		'UNIQUE KEY original_path (original_path(191))',
		'KEY clean_path (clean_path(191))'
	);

	/**
	 * Primary key.
	 *
	 * @var string
	 */
	protected static $primary_key = 'id';

	/**
	 * Get clean path for an original path
	 *
	 * @param string $original_path Original WordPress path.
	 * @return string|null Clean path or null if not found.
	 */
	public static function get_clean_path( $original_path ) {
		$mapping = self::query()->find_by( 'original_path', $original_path );

		if ( $mapping ) {
			return $mapping->clean_path;
		}

		return null;
	}

	/**
	 * Save or update a path mapping
	 *
	 * @param string $original_path Original WordPress path.
	 * @param string $clean_path    Clean mapped path.
	 * @return boolean Success status.
	 */
	public static function save_mapping( $original_path, $clean_path ) {
		// Check if mapping already exists
		$existing = self::query()->find_by( 'original_path', $original_path );

		if ( $existing ) {
			// Update existing mapping
			$existing->clean_path = $clean_path;
			return $existing->save();
		} else {
			// Create new mapping
			$mapping = new self();
			$mapping->original_path = $original_path;
			$mapping->clean_path    = $clean_path;
			return $mapping->save();
		}
	}

	/**
	 * Clear all path mappings (useful when starting a fresh export)
	 *
	 * @return void
	 */
	public static function clear_all() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table_name() );
	}

	/**
	 * Get total count of path mappings
	 *
	 * @return int Number of stored mappings.
	 */
	public static function count() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table_name() );
	}
}
