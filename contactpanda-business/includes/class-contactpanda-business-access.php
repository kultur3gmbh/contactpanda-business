<?php

defined( 'ABSPATH' ) || exit;

/** Business authorization bridge. Uses Pro's resource rules when available. */
final class ContactPanda_Business_Access {
	const CAPABILITY_VERSION = '1.0.0';
	const CAPABILITY_OPTION  = 'contactpanda_business_capability_version';

	public static function boot() {
		add_filter( 'contactpanda_pro_capability_map', array( __CLASS__, 'extend_pro_map' ) );
		add_filter( 'contactpanda_mcp_allowed', array( __CLASS__, 'allow_mcp' ), 20, 2 );
		if ( self::CAPABILITY_VERSION !== (string) get_option( self::CAPABILITY_OPTION, '' ) ) self::register_capabilities();
	}

	public static function allow_mcp( $allowed, $user = null ) {
		if ( $allowed ) return true;
		$user_id = $user instanceof WP_User ? $user->ID : 0;
		if ( class_exists( 'ContactPanda_Pro_Access' ) ) {
			return ContactPanda_Pro_Access::can( 'view', 'project', 0, array(), $user_id ) || ContactPanda_Pro_Access::can( 'view', 'task', 0, array(), $user_id ) || ContactPanda_Pro_Access::can( 'manage', 'project_import', 0, array(), $user_id );
		}
		return current_user_can( 'contactpanda_business_access' );
	}

	public static function capabilities() {
		return array(
			'contactpanda_business_access',
			'contactpanda_projects_view',
			'contactpanda_projects_create',
			'contactpanda_projects_edit',
			'contactpanda_projects_delete',
			'contactpanda_projects_export',
			'contactpanda_tasks_view',
			'contactpanda_tasks_create',
			'contactpanda_tasks_edit',
			'contactpanda_tasks_delete',
			'contactpanda_tasks_assign',
			'contactpanda_tasks_comment',
			'contactpanda_project_imports_manage',
		);
	}

	public static function register_capabilities() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) foreach ( self::capabilities() as $capability ) $administrator->add_cap( $capability );
		update_option( self::CAPABILITY_OPTION, self::CAPABILITY_VERSION, false );
	}

	public static function extend_pro_map( $map ) {
		$map['business'] = array( 'view' => 'contactpanda_business_access', 'manage' => 'contactpanda_business_access' );
		$map['project'] = array( 'view' => 'contactpanda_projects_view', 'create' => 'contactpanda_projects_create', 'edit' => 'contactpanda_projects_edit', 'delete' => 'contactpanda_projects_delete', 'export' => 'contactpanda_projects_export' );
		$map['task'] = array( 'view' => 'contactpanda_tasks_view', 'create' => 'contactpanda_tasks_create', 'edit' => 'contactpanda_tasks_edit', 'delete' => 'contactpanda_tasks_delete', 'assign' => 'contactpanda_tasks_assign', 'comment' => 'contactpanda_tasks_comment' );
		$map['project_import'] = array( 'view' => 'contactpanda_project_imports_manage', 'manage' => 'contactpanda_project_imports_manage' );
		return $map;
	}

	public static function can( $action, $resource_type = 'business', $resource_id = 0, $context = array() ) {
		if ( ! $context && $resource_id ) $context = self::resource_context( $resource_type, $resource_id );
		if ( class_exists( 'ContactPanda_Pro_Access' ) ) return ContactPanda_Pro_Access::can( $action, $resource_type, $resource_id, $context );
		if ( current_user_can( 'manage_options' ) ) return true;
		$map = self::extend_pro_map( array() );
		return ! empty( $map[ $resource_type ][ $action ] ) && current_user_can( $map[ $resource_type ][ $action ] );
	}

	public static function audit( $action, $resource_type, $resource_id = 0, $before = array(), $after = array(), $context = array() ) {
		if ( class_exists( 'ContactPanda_Pro_Access' ) ) return ContactPanda_Pro_Access::audit( $action, $resource_type, $resource_id, $before, $after, $context );
		return false;
	}

	/** Supply ownership and assignment context for Pro's own/assigned scopes. */
	private static function resource_context( $resource_type, $resource_id ) {
		if ( ! in_array( $resource_type, array( 'project', 'task' ), true ) ) return array();
		global $wpdb;
		$tables = ContactPanda_Business_Schema::table_names();
		if ( 'task' === $resource_type ) {
			$row = $wpdb->get_row( $wpdb->prepare( "SELECT created_by,assigned_user_id,project_id FROM `{$tables['tasks']}` WHERE id=%d", $resource_id ), ARRAY_A );
			return $row ? array( 'owner_user_id' => absint( $row['created_by'] ), 'assigned_user_id' => absint( $row['assigned_user_id'] ), 'project_id' => absint( $row['project_id'] ) ) : array();
		}
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT created_by FROM `{$tables['projects']}` WHERE id=%d", $resource_id ), ARRAY_A );
		return $row ? array( 'owner_user_id' => absint( $row['created_by'] ) ) : array();
	}
}
