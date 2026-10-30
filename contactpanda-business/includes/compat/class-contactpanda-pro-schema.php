<?php

defined( 'ABSPATH' ) || exit;

/** Owns Pro service data, shared authorization/audit data, and legacy cleanup. */
final class ContactPanda_Pro_Schema {
	const VERSION = '4.2.0';
	const VERSION_OPTION = 'contactpanda_pro_db_version';
	const LEGACY_CLEANUP_OPTION = 'contactpanda_pro_legacy_cleanup_080';

	public static function table_names() {
		global $wpdb;
		return array(
			'support_sources'    => $wpdb->prefix . 'cp_pro_support_sources',
			'support_tickets'    => $wpdb->prefix . 'cp_pro_support_tickets',
			'support_messages'   => $wpdb->prefix . 'cp_pro_support_messages',
			'customers'          => $wpdb->prefix . 'cp_pro_customers',
			'offers'             => $wpdb->prefix . 'cp_pro_offers',
			'tariffs'            => $wpdb->prefix . 'cp_pro_tariffs',
			'contracts'          => $wpdb->prefix . 'cp_pro_contracts',
			'subscriptions'      => $wpdb->prefix . 'cp_pro_subscriptions',
			'payments'           => $wpdb->prefix . 'cp_pro_payments',
			'payment_attempts'   => $wpdb->prefix . 'cp_pro_payment_attempts',
			'invoices'           => $wpdb->prefix . 'cp_pro_invoices',
			'invoice_items'      => $wpdb->prefix . 'cp_pro_invoice_items',
			'commerce_events'    => $wpdb->prefix . 'cp_pro_commerce_events',
			'access_assignments' => $wpdb->prefix . 'cp_pro_access_assignments',
			'audit_events'       => $wpdb->prefix . 'cp_pro_audit_events',
		);
	}

	public static function maybe_upgrade() {
		return self::VERSION === (string) get_option( self::VERSION_OPTION, '' ) ? true : self::install();
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t = self::table_names();
		$charset = $wpdb->get_charset_collate();
		$sql = array(
			"CREATE TABLE {$t['support_sources']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				name varchar(190) NOT NULL,
				source_type varchar(40) NOT NULL DEFAULT 'local',
				base_url varchar(255) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				token_hash char(64) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$t['support_tickets']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				source_id bigint(20) unsigned NOT NULL DEFAULT 0,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				company_id bigint(20) unsigned NOT NULL DEFAULT 0,
				requester_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				external_customer_id varchar(190) NOT NULL DEFAULT '',
				external_order_id varchar(190) NOT NULL DEFAULT '',
				request_type varchar(30) NOT NULL DEFAULT 'general',
				related_type varchar(40) NOT NULL DEFAULT '',
				related_id varchar(190) NOT NULL DEFAULT '',
				related_label varchar(255) NOT NULL DEFAULT '',
				license_reference varchar(190) NOT NULL DEFAULT '',
				products_json longtext NULL,
				orders_json longtext NULL,
				requester_name varchar(190) NOT NULL DEFAULT '',
				requester_email varchar(190) NOT NULL DEFAULT '',
				subject varchar(255) NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'new',
				priority varchar(20) NOT NULL DEFAULT 'normal',
				assigned_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				closed_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				KEY status_updated (status,updated_at),
				KEY contact_id (contact_id),
				KEY requester_user_id (requester_user_id),
				KEY source_id (source_id),
				KEY related_record (related_type,related_id)
			) $charset;",
			"CREATE TABLE {$t['support_messages']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				ticket_id bigint(20) unsigned NOT NULL,
				visibility varchar(20) NOT NULL DEFAULT 'customer',
				direction varchar(20) NOT NULL DEFAULT 'inbound',
				author_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				author_contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				body longtext NULL,
				attachments_json longtext NULL,
				external_source varchar(60) NOT NULL DEFAULT '',
				external_id varchar(190) DEFAULT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY source_message (external_source,external_id),
				KEY ticket_created (ticket_id,created_at)
			) $charset;",
			"CREATE TABLE {$t['customers']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				identity_key varchar(190) NOT NULL,
				contact_id bigint(20) unsigned NOT NULL DEFAULT 0,
				company_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				classification varchar(100) NOT NULL DEFAULT '',
				status varchar(30) NOT NULL DEFAULT 'active',
				source varchar(60) NOT NULL DEFAULT 'contactpanda',
				source_id varchar(190) NOT NULL DEFAULT '',
				customer_since datetime DEFAULT NULL,
				metadata_json longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY identity_key (identity_key),
				KEY contact_id (contact_id),
				KEY company_id (company_id),
				KEY user_id (user_id),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$t['offers']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				offer_key varchar(100) NOT NULL,
				name varchar(190) NOT NULL,
				description text NULL,
				offer_type varchar(30) NOT NULL DEFAULT 'service',
				internal_number varchar(100) NOT NULL DEFAULT '',
				status varchar(20) NOT NULL DEFAULT 'active',
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY offer_key (offer_key),
				KEY type_status (offer_type,status)
			) $charset;",
			"CREATE TABLE {$t['tariffs']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				offer_id bigint(20) unsigned NOT NULL,
				tariff_key varchar(100) NOT NULL,
				name varchar(190) NOT NULL,
				description text NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				billing_interval varchar(20) NOT NULL DEFAULT 'once',
				interval_count smallint unsigned NOT NULL DEFAULT 1,
				price decimal(18,6) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'EUR',
				price_source varchar(30) NOT NULL DEFAULT 'contactpanda',
				external_product_id varchar(190) NOT NULL DEFAULT '',
				external_variation_id varchar(190) NOT NULL DEFAULT '',
				upgrade_group varchar(100) NOT NULL DEFAULT '',
				metadata_json longtext NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY tariff_key (tariff_key),
				KEY offer_status (offer_id,status),
				KEY external_product (price_source,external_product_id,external_variation_id),
				KEY upgrade_group (upgrade_group)
			) $charset;",
			"CREATE TABLE {$t['contracts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				contract_number varchar(100) NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				tariff_id bigint(20) unsigned NOT NULL DEFAULT 0,
				title varchar(190) NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'draft',
				renewal_mode varchar(20) NOT NULL DEFAULT 'manual',
				price decimal(18,6) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'EUR',
				starts_at datetime DEFAULT NULL,
				ends_at datetime DEFAULT NULL,
				cancel_at datetime DEFAULT NULL,
				ended_at datetime DEFAULT NULL,
				source varchar(60) NOT NULL DEFAULT 'contactpanda',
				source_id varchar(190) NOT NULL DEFAULT '',
				terms_json longtext NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY contract_number (contract_number),
				KEY customer_status (customer_id,status),
				KEY tariff_id (tariff_id),
				KEY source_object (source,source_id)
			) $charset;",
			"CREATE TABLE {$t['subscriptions']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				contract_id bigint(20) unsigned NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				tariff_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(30) NOT NULL DEFAULT 'pending',
				renewal_mode varchar(20) NOT NULL DEFAULT 'manual',
				billing_interval varchar(20) NOT NULL DEFAULT 'month',
				interval_count smallint unsigned NOT NULL DEFAULT 1,
				amount decimal(18,6) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'EUR',
				current_period_starts_at datetime DEFAULT NULL,
				current_period_ends_at datetime DEFAULT NULL,
				next_renewal_at datetime DEFAULT NULL,
				cancel_at datetime DEFAULT NULL,
				ended_at datetime DEFAULT NULL,
				source varchar(60) NOT NULL DEFAULT 'contactpanda',
				source_id varchar(190) NOT NULL DEFAULT '',
				gateway_customer_reference varchar(190) NOT NULL DEFAULT '',
				gateway_payment_reference varchar(190) NOT NULL DEFAULT '',
				metadata_json longtext NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				deleted_at datetime DEFAULT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				KEY customer_status (customer_id,status),
				KEY contract_id (contract_id),
				KEY renewal_queue (status,next_renewal_at),
				KEY source_object (source,source_id)
			) $charset;",
			"CREATE TABLE {$t['payments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				customer_id bigint(20) unsigned NOT NULL,
				contract_id bigint(20) unsigned NOT NULL DEFAULT 0,
				subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
				payment_type varchar(30) NOT NULL DEFAULT 'one_time',
				status varchar(30) NOT NULL DEFAULT 'pending',
				amount decimal(18,6) NOT NULL DEFAULT 0,
				refunded_amount decimal(18,6) NOT NULL DEFAULT 0,
				currency char(3) NOT NULL DEFAULT 'EUR',
				source varchar(60) NOT NULL DEFAULT 'contactpanda',
				source_id varchar(190) NOT NULL DEFAULT '',
				gateway_reference varchar(190) NOT NULL DEFAULT '',
				due_at datetime DEFAULT NULL,
				paid_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				KEY customer_status (customer_id,status),
				KEY contract_id (contract_id),
				KEY subscription_id (subscription_id),
				KEY source_object (source,source_id)
			) $charset;",
			"CREATE TABLE {$t['payment_attempts']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				payment_id bigint(20) unsigned NOT NULL,
				gateway varchar(60) NOT NULL DEFAULT '',
				gateway_reference varchar(190) NOT NULL DEFAULT '',
				status varchar(30) NOT NULL DEFAULT 'pending',
				error_code varchar(100) NOT NULL DEFAULT '',
				error_message text NULL,
				attempted_at datetime NOT NULL,
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY payment_attempt (payment_id,attempted_at),
				KEY gateway_reference (gateway,gateway_reference)
			) $charset;",
			"CREATE TABLE {$t['invoices']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				invoice_number varchar(100) DEFAULT NULL,
				invoice_type varchar(30) NOT NULL DEFAULT 'invoice',
				customer_id bigint(20) unsigned NOT NULL,
				contract_id bigint(20) unsigned NOT NULL DEFAULT 0,
				subscription_id bigint(20) unsigned NOT NULL DEFAULT 0,
				project_reference varchar(190) NOT NULL DEFAULT '',
				status varchar(30) NOT NULL DEFAULT 'draft',
				currency char(3) NOT NULL DEFAULT 'EUR',
				net_total decimal(18,6) NOT NULL DEFAULT 0,
				tax_total decimal(18,6) NOT NULL DEFAULT 0,
				gross_total decimal(18,6) NOT NULL DEFAULT 0,
				issued_at datetime DEFAULT NULL,
				due_at datetime DEFAULT NULL,
				service_starts_at datetime DEFAULT NULL,
				service_ends_at datetime DEFAULT NULL,
				source varchar(60) NOT NULL DEFAULT 'contactpanda',
				source_id varchar(190) NOT NULL DEFAULT '',
				pdf_attachment_id bigint(20) unsigned NOT NULL DEFAULT 0,
				metadata_json longtext NULL,
				revision bigint(20) unsigned NOT NULL DEFAULT 1,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY invoice_number (invoice_number),
				KEY customer_status (customer_id,status),
				KEY contract_id (contract_id),
				KEY project_reference (project_reference),
				KEY source_object (source,source_id)
			) $charset;",
			"CREATE TABLE {$t['invoice_items']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				invoice_id bigint(20) unsigned NOT NULL,
				position int(11) unsigned NOT NULL DEFAULT 1,
				description text NOT NULL,
				quantity decimal(18,6) NOT NULL DEFAULT 1,
				unit_price decimal(18,6) NOT NULL DEFAULT 0,
				tax_rate decimal(8,4) NOT NULL DEFAULT 0,
				net_total decimal(18,6) NOT NULL DEFAULT 0,
				tax_total decimal(18,6) NOT NULL DEFAULT 0,
				gross_total decimal(18,6) NOT NULL DEFAULT 0,
				metadata_json longtext NULL,
				PRIMARY KEY  (id),
				KEY invoice_position (invoice_id,position)
			) $charset;",
			"CREATE TABLE {$t['commerce_events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				uuid char(36) NOT NULL,
				source varchar(60) NOT NULL,
				event_key varchar(190) NOT NULL,
				event_type varchar(80) NOT NULL,
				object_type varchar(60) NOT NULL DEFAULT '',
				object_id bigint(20) unsigned NOT NULL DEFAULT 0,
				payload_hash char(64) NOT NULL,
				payload_json longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'pending',
				attempts smallint unsigned NOT NULL DEFAULT 0,
				last_error text NULL,
				occurred_at datetime NOT NULL,
				processed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY uuid (uuid),
				UNIQUE KEY source_event (source,event_key),
				KEY status_created (status,created_at)
			) $charset;",
			"CREATE TABLE {$t['access_assignments']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				principal_type varchar(20) NOT NULL DEFAULT 'user',
				principal_id bigint(20) unsigned NOT NULL DEFAULT 0,
				principal_key varchar(100) NOT NULL DEFAULT '',
				permission_key varchar(100) NOT NULL,
				resource_type varchar(60) NOT NULL DEFAULT '*',
				resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
				scope varchar(30) NOT NULL DEFAULT 'explicit',
				effect varchar(10) NOT NULL DEFAULT 'allow',
				constraints_json longtext NULL,
				status varchar(20) NOT NULL DEFAULT 'active',
				created_by bigint(20) unsigned NOT NULL DEFAULT 0,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				KEY principal (principal_type,principal_id,principal_key),
				KEY permission_resource (permission_key,resource_type,resource_id),
				KEY status (status)
			) $charset;",
			"CREATE TABLE {$t['audit_events']} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				event_uuid char(36) NOT NULL,
				actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				action varchar(100) NOT NULL,
				resource_type varchar(60) NOT NULL,
				resource_id bigint(20) unsigned NOT NULL DEFAULT 0,
				before_json longtext NULL,
				after_json longtext NULL,
				context_json longtext NULL,
				ip_hash char(64) NOT NULL DEFAULT '',
				created_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY event_uuid (event_uuid),
				KEY resource_created (resource_type,resource_id,created_at),
				KEY actor_created (actor_user_id,created_at)
			) $charset;",
		);
		foreach ( $sql as $statement ) dbDelta( $statement );
		foreach ( $t as $table ) {
			if ( ! self::table_exists( $table ) ) return new WP_Error( 'contactpanda_pro_schema_incomplete', __( 'ContactPanda Pro could not create all required tables.', 'contactpanda-pro' ) );
		}
		self::remove_legacy_test_tables();
		update_option( self::VERSION_OPTION, self::VERSION, false );
		return true;
	}

	/**
	 * Remove only the fixed allowlist of obsolete tables from the pre-0.8 Pro prototype.
	 *
	 * ContactPanda people, companies, Support Desk data and the new Business tables use
	 * different names and can therefore never be affected by this migration.
	 */
	private static function remove_legacy_test_tables() {
		if ( get_option( self::LEGACY_CLEANUP_OPTION, false ) ) return;
		global $wpdb;
		$suffixes = array(
			'membership_plans',
			'memberships',
			'products',
			'plans',
			'product_mappings',
			'external_identities',
			'contract_events',
			'entitlement_grants',
		);
		$dropped = array();
		foreach ( $suffixes as $suffix ) {
			$table = $wpdb->prefix . 'cp_pro_' . $suffix;
			if ( self::table_exists( $table ) ) {
				$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- fixed prefix-qualified internal table allowlist.
				if ( ! self::table_exists( $table ) ) $dropped[] = $table;
			}
		}
		update_option(
			self::LEGACY_CLEANUP_OPTION,
			array(
				'completed_at' => current_time( 'mysql', true ),
				'dropped'      => $dropped,
			),
			false
		);
	}

	private static function table_exists( $table ) {
		global $wpdb;
		return $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
	}

	public static function table_exists_public( $table ) {
		return self::table_exists( $table );
	}
}
