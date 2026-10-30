<?php

defined( 'ABSPATH' ) || exit;

/**
 * Shared authorization and audit foundation for Pro and its extensions.
 *
 * WordPress capabilities remain the coarse entry point. Optional resource-level
 * assignments can then narrow or extend access without changing source records.
 */
final class ContactPanda_Pro_Access {
	const CAPABILITY_VERSION = '1.0.0';
	const CAPABILITY_OPTION  = 'contactpanda_pro_capability_version';

	/** Register the initial capabilities without changing existing non-admin roles. */
	public static function boot() {
		add_filter( 'contactpanda_mcp_allowed', array( __CLASS__, 'allow_mcp' ), 10, 2 );
		add_filter( 'contactpanda_role_capability_dependencies', array( __CLASS__, 'role_capability_dependencies' ) );
		if ( self::CAPABILITY_VERSION !== (string) get_option( self::CAPABILITY_OPTION, '' ) ) {
			self::register_capabilities();
		}
	}

	public static function role_capability_dependencies( $map ) {
		$map['contactpanda_manage_support'] = array_values( array_filter( self::capabilities(), static function ( $capability ) { return 0 === strpos( $capability, 'contactpanda_support_' ) || 'contactpanda_pro_access' === $capability; } ) );
		$map['contactpanda_manage_contacts'] = array_values( array_filter( self::capabilities(), static function ( $capability ) { return 0 === strpos( $capability, 'contactpanda_customers_' ); } ) );
		return $map;
	}

	public static function allow_mcp( $allowed, $user = null ) {
		if ( $allowed ) return true;
		$user_id = $user instanceof WP_User ? $user->ID : 0;
		foreach ( array( 'view', 'create', 'edit' ) as $action ) {
			if ( self::can( $action, 'support_ticket', 0, array(), $user_id ) ) return true;
		}
		return false;
	}

	public static function capabilities() {
		return array(
			'contactpanda_pro_access',
			'contactpanda_support_view',
			'contactpanda_support_create',
			'contactpanda_support_edit',
			'contactpanda_support_delete',
			'contactpanda_support_export',
			'contactpanda_support_assign',
			'contactpanda_support_manage_sources',
			'contactpanda_customers_view',
			'contactpanda_customers_create',
			'contactpanda_customers_edit',
			'contactpanda_customers_delete',
			'contactpanda_customers_export',
			'contactpanda_customers_merge',
			'contactpanda_customers_view_sensitive',
			'contactpanda_contracts_view',
			'contactpanda_contracts_manage',
			'contactpanda_subscriptions_view',
			'contactpanda_subscriptions_manage',
			'contactpanda_payments_view',
			'contactpanda_payments_manage',
			'contactpanda_invoices_view',
			'contactpanda_invoices_manage',
			'contactpanda_portal_manage',
			'contactpanda_audit_view',
		);
	}

	public static function register_capabilities() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( self::capabilities() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
		update_option( self::CAPABILITY_OPTION, self::CAPABILITY_VERSION, false );
	}

	/**
	 * Determine whether the current or supplied user may perform an action.
	 *
	 * Explicit resource-level denies win over allows. Administrators retain the
	 * historical manage_options access and cannot accidentally lock themselves out.
	 */
	public static function can( $action, $resource_type = 'pro', $resource_id = 0, $context = array(), $user_id = 0 ) {
		$user = $user_id ? get_user_by( 'id', absint( $user_id ) ) : wp_get_current_user();
		if ( ! $user || ! $user->exists() ) return false;
		if ( user_can( $user, 'manage_options' ) ) return true;

		$action        = sanitize_key( $action );
		$resource_type = sanitize_key( $resource_type );
		$resource_id   = absint( $resource_id );
		$capability    = self::capability_for( $action, $resource_type );
		$decision      = $capability && user_can( $user, $capability );
		$assignment    = self::assignment_decision( $user, $action, $resource_type, $resource_id, $context );

		if ( false === $assignment ) $decision = false;
		elseif ( true === $assignment ) $decision = true;

		return (bool) apply_filters( 'contactpanda_pro_access_decision', $decision, $action, $resource_type, $resource_id, $context, $user );
	}

	public static function permission_error() {
		return new WP_Error( 'contactpanda_pro_forbidden', __( 'You do not have permission.', 'contactpanda-pro' ), array( 'status' => 403 ) );
	}

	public static function capability_for( $action, $resource_type ) {
		$map = array(
			'pro' => array(
				'view'   => 'contactpanda_pro_access',
				'manage' => 'contactpanda_pro_access',
			),
			'support_ticket' => array(
				'view'   => 'contactpanda_support_view',
				'create' => 'contactpanda_support_create',
				'edit'   => 'contactpanda_support_edit',
				'delete' => 'contactpanda_support_delete',
				'export' => 'contactpanda_support_export',
				'assign' => 'contactpanda_support_assign',
			),
			'support_source' => array(
				'view'   => 'contactpanda_support_view',
				'manage' => 'contactpanda_support_manage_sources',
			),
			'customer' => array(
				'view'           => 'contactpanda_customers_view',
				'create'         => 'contactpanda_customers_create',
				'edit'           => 'contactpanda_customers_edit',
				'delete'         => 'contactpanda_customers_delete',
				'export'         => 'contactpanda_customers_export',
				'merge'          => 'contactpanda_customers_merge',
				'view_sensitive' => 'contactpanda_customers_view_sensitive',
			),
			'contract' => array( 'view' => 'contactpanda_contracts_view', 'manage' => 'contactpanda_contracts_manage' ),
			'subscription' => array( 'view' => 'contactpanda_subscriptions_view', 'manage' => 'contactpanda_subscriptions_manage' ),
			'payment' => array( 'view' => 'contactpanda_payments_view', 'manage' => 'contactpanda_payments_manage' ),
			'invoice' => array( 'view' => 'contactpanda_invoices_view', 'manage' => 'contactpanda_invoices_manage' ),
			'offer' => array( 'view' => 'contactpanda_contracts_view', 'manage' => 'contactpanda_contracts_manage' ),
			'tariff' => array( 'view' => 'contactpanda_contracts_view', 'manage' => 'contactpanda_contracts_manage' ),
			'portal' => array( 'manage' => 'contactpanda_portal_manage' ),
			'audit' => array( 'view' => 'contactpanda_audit_view' ),
		);
		$map = apply_filters( 'contactpanda_pro_capability_map', $map );
		return isset( $map[ $resource_type ][ $action ] ) ? $map[ $resource_type ][ $action ] : '';
	}

	/** Store a metadata-only audit event. Callers should not pass message bodies or secrets. */
	public static function audit( $action, $resource_type, $resource_id = 0, $before = array(), $after = array(), $context = array() ) {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		if ( empty( $tables['audit_events'] ) || ! ContactPanda_Pro_Schema::table_exists_public( $tables['audit_events'] ) ) return false;

		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip_hash = $ip ? hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) ) : '';
		$result = $wpdb->insert(
			$tables['audit_events'],
			array(
				'event_uuid'    => wp_generate_uuid4(),
				'actor_user_id' => get_current_user_id(),
				'action'        => sanitize_key( $action ),
				'resource_type' => sanitize_key( $resource_type ),
				'resource_id'   => absint( $resource_id ),
				'before_json'   => wp_json_encode( self::redact( $before ) ),
				'after_json'    => wp_json_encode( self::redact( $after ) ),
				'context_json'  => wp_json_encode( self::redact( $context ) ),
				'ip_hash'       => $ip_hash,
				'created_at'    => current_time( 'mysql', true ),
			)
		);
		return false !== $result;
	}

	private static function assignment_decision( $user, $action, $resource_type, $resource_id, $context ) {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		if ( empty( $tables['access_assignments'] ) || ! ContactPanda_Pro_Schema::table_exists_public( $tables['access_assignments'] ) ) return null;

		$roles = array_values( array_filter( array_map( 'sanitize_key', (array) $user->roles ) ) );
		$conditions = array( '(principal_type=%s AND principal_id=%d)' );
		$values = array( 'user', absint( $user->ID ) );
		foreach ( $roles as $role ) {
			$conditions[] = '(principal_type=%s AND principal_key=%s)';
			$values[] = 'role';
			$values[] = $role;
		}
		$sql = "SELECT permission_key,resource_type,resource_id,scope,effect,constraints_json FROM `{$tables['access_assignments']}` WHERE status='active' AND (" . implode( ' OR ', $conditions ) . ')';
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name and prepared values.
		$allow = false;
		foreach ( $rows as $row ) {
			if ( ! in_array( $row['permission_key'], array( '*', $action, $resource_type . '.' . $action ), true ) ) continue;
			if ( ! in_array( $row['resource_type'], array( '*', $resource_type ), true ) ) continue;
			if ( $row['resource_id'] && absint( $row['resource_id'] ) !== $resource_id ) continue;
			if ( ! self::scope_matches( $row['scope'], $user, $context ) ) continue;
			if ( 'deny' === $row['effect'] ) return false;
			$allow = true;
		}
		return $allow ? true : null;
	}

	private static function scope_matches( $scope, $user, $context ) {
		if ( in_array( $scope, array( '', 'all', 'explicit' ), true ) ) return true;
		if ( 'own' === $scope ) return absint( $context['owner_user_id'] ?? 0 ) === absint( $user->ID );
		if ( 'assigned' === $scope ) return absint( $context['assigned_user_id'] ?? 0 ) === absint( $user->ID );
		return (bool) apply_filters( 'contactpanda_pro_access_scope_matches', false, $scope, $user, $context );
	}

	private static function redact( $value, $key = '' ) {
		if ( preg_match( '/(?:password|secret|token|authorization|card|body|message|content)/i', (string) $key ) ) return '[redacted]';
		if ( ! is_array( $value ) ) return is_scalar( $value ) || null === $value ? $value : (string) $value;
		$clean = array();
		foreach ( $value as $item_key => $item ) $clean[ $item_key ] = self::redact( $item, $item_key );
		return $clean;
	}
}
