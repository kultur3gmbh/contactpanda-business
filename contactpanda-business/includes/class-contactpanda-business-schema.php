<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Business_Schema {
	const VERSION = '1.7.0';
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
			'checklist'   => $wpdb->prefix . 'cp_business_task_checklist',
			'events'      => $wpdb->prefix . 'cp_business_task_events',
			'imports'     => $wpdb->prefix . 'cp_business_imports',
			'import_items'=> $wpdb->prefix . 'cp_business_import_items',
			'project_steps'=> $wpdb->prefix . 'cp_business_project_steps',
			'project_documents'=> $wpdb->prefix . 'cp_business_project_documents',
			'payments'    => $wpdb->prefix . 'cp_business_project_payments',
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
				pro_customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				name varchar(190) NOT NULL,
				description longtext NULL,
				color varchar(7) NOT NULL DEFAULT '#3d5cf5',
				status varchar(30) NOT NULL DEFAULT 'active',
				progress tinyint(3) unsigned NOT NULL DEFAULT 0,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				start_date date DEFAULT NULL,
				target_end_date date DEFAULT NULL,
				budget_total decimal(18,2) NOT NULL DEFAULT 0.00,
				currency char(3) NOT NULL DEFAULT 'EUR',
				financing_notes longtext NULL,
				notes longtext NULL,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), UNIQUE KEY uuid (uuid), KEY status (status), KEY customer_user_id (customer_user_id), KEY pro_customer_id (pro_customer_id)
			) $c;",
			"CREATE TABLE {$t['participants']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				entity_type varchar(20) NOT NULL,
				entity_id bigint(20) unsigned NOT NULL,
				pro_customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
				participant_role varchar(30) NOT NULL DEFAULT 'participant',
				access_level varchar(20) NOT NULL DEFAULT 'read',
				is_primary tinyint(1) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id), UNIQUE KEY project_entity (project_id,entity_type,entity_id), KEY entity_lookup (entity_type,entity_id), KEY pro_customer_id (pro_customer_id), KEY project_role (project_id,participant_role)
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
			"CREATE TABLE {$t['checklist']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				task_id bigint(20) unsigned NOT NULL,
				title varchar(255) NOT NULL,
				is_complete tinyint(1) unsigned NOT NULL DEFAULT 0,
				sort_order int(11) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				completed_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), KEY task_order (task_id,sort_order,id)
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
				first_applied_at datetime DEFAULT NULL,
				applied_at datetime DEFAULT NULL,
				apply_count int(11) unsigned NOT NULL DEFAULT 0,
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
			"CREATE TABLE {$t['project_steps']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				area_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL,
				description longtext NULL,
				step_type varchar(40) NOT NULL DEFAULT 'phase',
				status varchar(30) NOT NULL DEFAULT 'planned',
				due_at date DEFAULT NULL,
				completed_at datetime DEFAULT NULL,
				amount decimal(18,2) NOT NULL DEFAULT 0.00,
				currency char(3) NOT NULL DEFAULT 'EUR',
				sort_order int(11) NOT NULL DEFAULT 0,
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), KEY project_order (project_id,sort_order,id), KEY area_id (area_id), KEY status_due (status,due_at)
			) $c;",
			"CREATE TABLE {$t['project_documents']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				step_id bigint(20) unsigned NOT NULL DEFAULT 0,
				pro_payment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				pro_invoice_id bigint(20) unsigned NOT NULL DEFAULT 0,
				attachment_id bigint(20) unsigned NOT NULL,
				document_type varchar(40) NOT NULL DEFAULT 'other',
				title varchar(255) NOT NULL DEFAULT '',
				notes longtext NULL,
				document_date date DEFAULT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'internal',
				uploaded_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), KEY project_created (project_id,created_at), KEY step_id (step_id), KEY attachment_id (attachment_id)
			) $c;",
			"CREATE TABLE {$t['payments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				project_id bigint(20) unsigned NOT NULL,
				area_id bigint(20) unsigned NOT NULL DEFAULT 0,
				step_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(255) NOT NULL,
				amount decimal(18,2) NOT NULL DEFAULT 0.00,
				paid_amount decimal(18,2) NOT NULL DEFAULT 0.00,
				currency char(3) NOT NULL DEFAULT 'EUR',
				due_at date DEFAULT NULL,
				paid_at datetime DEFAULT NULL,
				status varchar(30) NOT NULL DEFAULT 'planned',
				reference varchar(190) NOT NULL DEFAULT '',
				notes longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id), KEY project_due (project_id,due_at), KEY status (status), KEY step_id (step_id), KEY pro_payment_id (pro_payment_id), KEY pro_invoice_id (pro_invoice_id)
			) $c;",
		);
		foreach ( $sql as $statement ) dbDelta( $statement );
		foreach ( $t as $table ) if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) return new WP_Error( 'contactpanda_business_schema', __( 'ContactPanda Business could not create all required tables.', 'contactpanda-business' ) );
		self::migrate_legacy_participants( $t );
		self::link_pro_customers();
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

	/** Link customer-facing project participants to Pro without copying identity data. */
	public static function link_pro_customers() {
		if ( ! class_exists( 'ContactPanda_Pro_Commercial' ) ) return;
		global $wpdb;
		$tables = self::table_names();
		$participants = $wpdb->get_results( "SELECT id,project_id,entity_type,entity_id,participant_role,is_primary FROM `{$tables['participants']}` WHERE pro_customer_id=0 AND participant_role IN ('customer','customer_account','agency')", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name.
		foreach ( (array) $participants as $participant ) {
			$contact_id = 'person' === $participant['entity_type'] ? absint( $participant['entity_id'] ) : 0;
			$company_id = 'company' === $participant['entity_type'] ? absint( $participant['entity_id'] ) : 0;
			$user_id = 'user' === $participant['entity_type'] ? absint( $participant['entity_id'] ) : 0;
			if ( $user_id && class_exists( 'ContactPanda_Identity' ) ) {
				$identity = ContactPanda_Identity::resolve_user( $user_id, true );
				if ( ! is_wp_error( $identity ) ) $contact_id = absint( $identity['id'] ?? 0 );
			}
			$customer_id = ContactPanda_Pro_Commercial::ensure_customer( $contact_id, $company_id, $user_id, 'contactpanda_business', 'participant:' . absint( $participant['id'] ) );
			if ( is_wp_error( $customer_id ) ) continue;
			$wpdb->update( $tables['participants'], array( 'pro_customer_id' => absint( $customer_id ) ), array( 'id' => absint( $participant['id'] ) ) );
			if ( ! empty( $participant['is_primary'] ) ) {
				$wpdb->query( $wpdb->prepare( "UPDATE `{$tables['projects']}` SET pro_customer_id=%d WHERE id=%d AND pro_customer_id=0", absint( $customer_id ), absint( $participant['project_id'] ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name.
			}
		}
	}
}
