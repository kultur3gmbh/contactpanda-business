<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Business_Schema {
	const VERSION = '1.3.0';
	const OPTION = 'contactpanda_business_db_version';

	public static function table_names() {
		global $wpdb;
		return array(
			'projects'    => $wpdb->prefix . 'cp_business_projects',
			'participants'=> $wpdb->prefix . 'cp_business_project_participants',
			'areas'       => $wpdb->prefix . 'cp_business_project_areas',
			'tasks'       => $wpdb->prefix . 'cp_business_tasks',
			'comments'    => $wpdb->prefix . 'cp_business_task_comments',
			'attachments' => $wpdb->prefix . 'cp_business_task_attachments',
			'events'      => $wpdb->prefix . 'cp_business_task_events',
			'imports'     => $wpdb->prefix . 'cp_business_imports',
			'import_items'=> $wpdb->prefix . 'cp_business_import_items',
		);
	}

	public static function maybe_upgrade() {
		return self::VERSION === (string) get_option( self::OPTION, '' ) ? true : self::install();
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table_names();
		$c = $wpdb->get_charset_collate();
		$sql = array(
			"CREATE TABLE {$t['projects']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				company_id bigint(20) unsigned NOT NULL DEFAULT 0,
				customer_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(190) NOT NULL,
				description longtext NULL,
				color varchar(7) NOT NULL DEFAULT '#3d5cf5',
				status varchar(30) NOT NULL DEFAULT 'active',
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), UNIQUE KEY uuid (uuid), KEY status (status), KEY customer_user_id (customer_user_id)
			) $c;",
			"CREATE TABLE {$t['participants']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				entity_type varchar(20) NOT NULL,
				entity_id bigint(20) unsigned NOT NULL,
				participant_role varchar(30) NOT NULL DEFAULT 'participant',
				access_level varchar(20) NOT NULL DEFAULT 'read',
				is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), UNIQUE KEY project_entity (project_id,entity_type,entity_id), KEY entity_lookup (entity_type,entity_id), KEY project_role (project_id,participant_role)
			) $c;",
			"CREATE TABLE {$t['areas']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				name varchar(190) NOT NULL,
				description longtext NULL,
				color varchar(7) NOT NULL DEFAULT '#3d5cf5',
				assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				status varchar(30) NOT NULL DEFAULT 'active',
				sort_order int(11) NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), KEY project_status (project_id,status), KEY assigned_user_id (assigned_user_id)
			) $c;",
			"CREATE TABLE {$t['tasks']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				project_id bigint(20) unsigned NOT NULL,
				area_id bigint(20) unsigned NOT NULL DEFAULT 0,
				support_ticket_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL,
				description longtext NULL,
				board_column varchar(30) NOT NULL DEFAULT 'open',
				open_status varchar(30) NOT NULL DEFAULT 'new',
				progress tinyint(3) unsigned NOT NULL DEFAULT 0,
				priority varchar(20) NOT NULL DEFAULT 'normal',
				category varchar(80) NOT NULL DEFAULT '',
				assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				due_at datetime DEFAULT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				sort_order int(11) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				deleted_at datetime DEFAULT NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 1,
				source_hash char(64) NOT NULL DEFAULT '',
				PRIMARY KEY  (id), UNIQUE KEY uuid (uuid), KEY project_board (project_id,board_column,sort_order), KEY area_id (area_id), KEY assigned_user_id (assigned_user_id), KEY due_at (due_at), KEY support_ticket_id (support_ticket_id), KEY source_hash (source_hash)
			) $c;",
			"CREATE TABLE {$t['comments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				task_id bigint(20) unsigned NOT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				body longtext NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), KEY task_created (task_id,created_at)
			) $c;",
			"CREATE TABLE {$t['attachments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				task_id bigint(20) unsigned NOT NULL,
				comment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				attachment_id bigint(20) unsigned NOT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), KEY task_id (task_id)
			) $c;",
			"CREATE TABLE {$t['events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL DEFAULT 0,
				task_id bigint(20) unsigned NOT NULL DEFAULT 0,
				event_type varchar(60) NOT NULL,
				actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				details_json longtext NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), KEY task_created (task_id,created_at), KEY project_created (project_id,created_at)
			) $c;",
			"CREATE TABLE {$t['imports']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				source_type varchar(30) NOT NULL,
				source_name varchar(255) NOT NULL DEFAULT '',
				attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(30) NOT NULL DEFAULT 'review',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id), UNIQUE KEY uuid (uuid), KEY status_created (status,created_at)
			) $c;",
			"CREATE TABLE {$t['import_items']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				import_id bigint(20) unsigned NOT NULL,
				source_key varchar(190) NOT NULL DEFAULT '',
				source_text longtext NULL,
				title varchar(255) NOT NULL,
				description longtext NULL,
				project_id bigint(20) unsigned NOT NULL DEFAULT 0,
				area_id bigint(20) unsigned NOT NULL DEFAULT 0,
				assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				priority varchar(20) NOT NULL DEFAULT 'normal',
				due_at datetime DEFAULT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				match_task_id bigint(20) unsigned NOT NULL DEFAULT 0,
				match_score tinyint(3) unsigned NOT NULL DEFAULT 0,
				import_action varchar(30) NOT NULL DEFAULT 'create',
				selected tinyint(1) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), UNIQUE KEY import_source (import_id,source_key), KEY import_id (import_id), KEY match_task_id (match_task_id)
			) $c;",
		);
		foreach ( $sql as $statement ) dbDelta( $statement );
		foreach ( $t as $table ) if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) return new WP_Error( 'contactpanda_business_schema', __( 'ContactPanda Business could not create all required tables.', 'contactpanda-business' ) );
		self::migrate_legacy_participants( $t );
		update_option( self::OPTION, self::VERSION, false );
		return true;
	}

	private static function migrate_legacy_participants( $tables ) {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$projects = $wpdb->get_results( "SELECT id,contact_id,company_id,customer_user_id FROM `{$tables['projects']}`", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name.
		foreach ( (array) $projects as $project ) {
			$legacy = array(
				array( 'person', absint( $project['contact_id'] ), 'contact', 'read' ),
				array( 'company', absint( $project['company_id'] ), 'customer', 'read' ),
				array( 'user', absint( $project['customer_user_id'] ), 'customer_account', 'write' ),
			);
			foreach ( $legacy as $participant ) {
				if ( ! $participant[1] ) continue;
				$wpdb->query(
					$wpdb->prepare(
						"INSERT IGNORE INTO `{$tables['participants']}` (project_id,entity_type,entity_id,participant_role,access_level,is_primary,created_at) VALUES (%d,%s,%d,%s,%s,1,%s)",
						absint( $project['id'] ), $participant[0], $participant[1], $participant[2], $participant[3], $now
					)
				); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name.
				if ( 'user' === $participant[0] ) {
					$wpdb->update(
						$tables['participants'],
						array( 'access_level' => 'write' ),
						array( 'project_id' => absint( $project['id'] ), 'entity_type' => 'user', 'entity_id' => $participant[1] )
					);
				}
			}
		}
	}
}
