<?php

defined( 'ABSPATH' ) || exit;

/** Authenticated MCP tools for projects, tasks, staged imports, and report preparation. */
final class ContactPanda_Business_MCP {
	const ROUTE = 'contactpanda-business/v1';
	const AUDIT_OPTION = 'contactpanda_business_mcp_audit';

	public static function boot() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_route' ) );
		add_action( 'wp_abilities_api_categories_init', array( __CLASS__, 'register_ability_category' ) );
		add_action( 'wp_abilities_api_init', array( __CLASS__, 'register_abilities' ) );
		if ( class_exists( 'ContactPanda_Plus_MCP' ) ) { add_filter( 'contactpanda_plus_mcp_tools', array( __CLASS__, 'extend_tools' ) ); add_filter( 'contactpanda_plus_mcp_tool_call', array( __CLASS__, 'extend_call' ), 10, 3 ); }
	}

	public static function register_route() { register_rest_route( self::ROUTE, '/mcp', array( 'methods' => WP_REST_Server::CREATABLE, 'callback' => array( __CLASS__, 'handle' ), 'permission_callback' => array( __CLASS__, 'allowed' ) ) ); }
	public static function allowed( $input = null ) { return contactpanda_business_module_entitled( 'project_management' ) && current_user_can( 'manage_options' ); }

	/**
	 * Expose Business tools through the WordPress Abilities API used by secure
	 * MCP connectors. Older WordPress versions continue to use the REST route.
	 */
	public static function register_ability_category() {
		if ( ! function_exists( 'wp_register_ability_category' ) ) return;
		wp_register_ability_category(
			'contactpanda-business',
			array(
				'label'       => __( 'ContactPanda Business', 'contactpanda-business' ),
				'description' => __( 'Projects, tasks, reviewed imports, and customer reports.', 'contactpanda-business' ),
			)
		);
	}

	public static function register_abilities() {
		if ( ! function_exists( 'wp_register_ability' ) ) return;

		$read_meta = array(
			'public'       => true,
			'show_in_rest' => true,
			'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
		);
		$stage_meta = array(
			'public'       => true,
			'show_in_rest' => true,
			'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
		);
		$output_schema = array( 'type' => 'object', 'additionalProperties' => true );

		wp_register_ability(
			'contactpanda-business/projects-list',
			array(
				'label'               => __( 'List Business projects', 'contactpanda-business' ),
				'description'         => __( 'List Business projects and open task counts.', 'contactpanda-business' ),
				'category'            => 'contactpanda-business',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search' => array( 'type' => 'string', 'description' => __( 'Optional project search term.', 'contactpanda-business' ) ),
						'limit'  => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'default' => 50 ),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => $output_schema,
				'execute_callback'    => array( __CLASS__, 'ability_projects_list' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
				'meta'                => $read_meta,
			)
		);

		wp_register_ability(
			'contactpanda-business/project-get',
			array(
				'label'               => __( 'Read Business project', 'contactpanda-business' ),
				'description'         => __( 'Read one project with areas and tasks.', 'contactpanda-business' ),
				'category'            => 'contactpanda-business',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'project_id' ),
					'properties'           => array(
						'project_id'  => array( 'type' => 'integer', 'minimum' => 1 ),
						'include_done' => array( 'type' => 'boolean', 'default' => false ),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => $output_schema,
				'execute_callback'    => array( __CLASS__, 'ability_project_get' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
				'meta'                => $read_meta,
			)
		);

		wp_register_ability(
			'contactpanda-business/tasks-search',
			array(
				'label'               => __( 'Search Business tasks', 'contactpanda-business' ),
				'description'         => __( 'Search tasks by project, area, assignee, or status.', 'contactpanda-business' ),
				'category'            => 'contactpanda-business',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'           => array( 'type' => 'string' ),
						'project_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
						'area_id'          => array( 'type' => 'integer', 'minimum' => 1 ),
						'assigned_user_id' => array( 'type' => 'integer', 'minimum' => 1 ),
						'status'           => array( 'type' => 'string' ),
						'limit'            => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200, 'default' => 50 ),
					),
					'additionalProperties' => false,
					'default'              => array(),
				),
				'output_schema'       => $output_schema,
				'execute_callback'    => array( __CLASS__, 'ability_tasks_search' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
				'meta'                => $read_meta,
			)
		);

		wp_register_ability(
			'contactpanda-business/import-stage',
			array(
				'label'               => __( 'Prepare a Business import draft', 'contactpanda-business' ),
				'description'         => __( 'Create a review draft from extracted document items. This does not create or modify live tasks.', 'contactpanda-business' ),
				'category'            => 'contactpanda-business',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'items' ),
					'properties'           => array(
						'source_name' => array( 'type' => 'string' ),
						'items'       => array(
							'type'     => 'array',
							'minItems' => 1,
							'maxItems' => 1000,
							'items'    => array(
								'type'                 => 'object',
								'required'             => array( 'title' ),
								'properties'           => array(
									'title'            => array( 'type' => 'string', 'minLength' => 1 ),
									'description'      => array( 'type' => 'string' ),
									'source_text'      => array( 'type' => 'string' ),
									'project_id'       => array( 'type' => 'integer', 'minimum' => 1 ),
									'area_id'          => array( 'type' => 'integer', 'minimum' => 1 ),
									'assigned_user_id' => array( 'type' => 'integer', 'minimum' => 1 ),
									'priority'         => array( 'type' => 'string' ),
									'due_at'           => array( 'type' => 'string' ),
									'visibility'       => array( 'type' => 'string' ),
								),
								'additionalProperties' => false,
							),
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => $output_schema,
				'execute_callback'    => array( __CLASS__, 'ability_import_stage' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
				'meta'                => $stage_meta,
			)
		);

		wp_register_ability(
			'contactpanda-business/report-prepare',
			array(
				'label'               => __( 'Prepare a Business project report', 'contactpanda-business' ),
				'description'         => __( 'Prepare links for a customer-visible HTML/PDF print view and editable Word report.', 'contactpanda-business' ),
				'category'            => 'contactpanda-business',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'project_id' ),
					'properties'           => array( 'project_id' => array( 'type' => 'integer', 'minimum' => 1 ) ),
					'additionalProperties' => false,
				),
				'output_schema'       => $output_schema,
				'execute_callback'    => array( __CLASS__, 'ability_report_prepare' ),
				'permission_callback' => array( __CLASS__, 'allowed' ),
				'meta'                => $read_meta,
			)
		);
	}

	public static function ability_projects_list( $input = array() ) { return self::ability_call( 'contactpanda_business_projects_list', $input ); }
	public static function ability_project_get( $input = array() ) { return self::ability_call( 'contactpanda_business_project_get', $input ); }
	public static function ability_tasks_search( $input = array() ) { return self::ability_call( 'contactpanda_business_tasks_search', $input ); }
	public static function ability_import_stage( $input = array() ) { return self::ability_call( 'contactpanda_business_import_stage', $input ); }
	public static function ability_report_prepare( $input = array() ) { return self::ability_call( 'contactpanda_business_report_prepare', $input ); }

	private static function ability_call( $name, $input ) {
		$result = self::call( $name, is_array( $input ) ? $input : array() );
		self::audit( $name, ! is_wp_error( $result ) );
		return $result;
	}

	public static function handle( WP_REST_Request $request ) {
		$payload = $request->get_json_params(); if ( ! is_array( $payload ) ) return self::error( null, -32700, 'Parse error' ); $id = $payload['id'] ?? null; $method = sanitize_text_field( $payload['method'] ?? '' );
		if ( 'initialize' === $method ) return self::result( $id, array( 'protocolVersion' => '2025-03-26', 'capabilities' => array( 'tools' => (object) array( 'listChanged' => false ) ), 'serverInfo' => array( 'name' => 'ContactPanda Business', 'version' => CONTACTPANDA_BUSINESS_VERSION ), 'instructions' => __( 'Read project data and prepare reviewed import drafts. Changes are applied only after manual confirmation in WordPress.', 'contactpanda-business' ) ) );
		if ( 'ping' === $method ) return self::result( $id, (object) array() ); if ( 'notifications/initialized' === $method ) return new WP_REST_Response( null, 202 ); if ( 'tools/list' === $method ) return self::result( $id, array( 'tools' => self::tools() ) ); if ( 'tools/call' !== $method ) return self::error( $id, -32601, 'Method not found' );
		$params = is_array( $payload['params'] ?? null ) ? $payload['params'] : array(); $name = sanitize_key( $params['name'] ?? '' ); $arguments = is_array( $params['arguments'] ?? null ) ? $params['arguments'] : array(); $outcome = self::call( $name, $arguments );
		if ( null === $outcome ) return self::error( $id, -32602, 'Unknown tool' ); $success = ! is_wp_error( $outcome ); self::audit( $name, $success ); if ( is_wp_error( $outcome ) ) return self::result( $id, array( 'isError' => true, 'content' => array( array( 'type' => 'text', 'text' => $outcome->get_error_message() ) ) ) ); return self::result( $id, array( 'content' => array( array( 'type' => 'text', 'text' => wp_json_encode( $outcome, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) ), 'structuredContent' => $outcome ) );
	}

	public static function extend_tools( $tools ) { return array_merge( $tools, self::tools() ); }
	public static function extend_call( $result, $name, $args ) { if ( null !== $result ) return $result; $result = self::call( $name, $args ); if ( null !== $result ) self::audit( $name, ! is_wp_error( $result ) ); return $result; }

	private static function tools() {
		return array(
			array(
				'name'        => 'contactpanda_business_projects_list',
				'description' => __( 'List Business projects and open task counts.', 'contactpanda-business' ),
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ) ) ),
			),
			array(
				'name'        => 'contactpanda_business_project_get',
				'description' => __( 'Read one project with areas and tasks.', 'contactpanda-business' ),
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'project_id' ), 'properties' => array( 'project_id' => array( 'type' => 'integer' ), 'include_done' => array( 'type' => 'boolean' ) ) ),
			),
			array(
				'name'        => 'contactpanda_business_tasks_search',
				'description' => __( 'Search tasks by project, area, assignee, or status.', 'contactpanda-business' ),
				'inputSchema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'project_id' => array( 'type' => 'integer' ), 'area_id' => array( 'type' => 'integer' ), 'assigned_user_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 200 ) ) ),
			),
			array(
				'name'        => 'contactpanda_business_import_stage',
				'description' => __( 'Create a review draft from extracted document items. This does not create or modify live tasks.', 'contactpanda-business' ),
				'inputSchema' => array(
					'type'       => 'object',
					'required'   => array( 'items' ),
					'properties' => array(
						'source_name' => array( 'type' => 'string' ),
						'items'       => array(
							'type'     => 'array',
							'maxItems' => 1000,
							'items'    => array(
								'type'       => 'object',
								'required'   => array( 'title' ),
								'properties' => array(
									'title'            => array( 'type' => 'string' ),
									'description'      => array( 'type' => 'string' ),
									'source_text'      => array( 'type' => 'string' ),
									'project_id'       => array( 'type' => 'integer' ),
									'area_id'          => array( 'type' => 'integer' ),
									'assigned_user_id' => array( 'type' => 'integer' ),
									'priority'         => array( 'type' => 'string' ),
									'due_at'           => array( 'type' => 'string' ),
									'visibility'       => array( 'type' => 'string' ),
								),
							),
						),
					),
				),
			),
			array(
				'name'        => 'contactpanda_business_report_prepare',
				'description' => __( 'Prepare links for a customer-visible HTML/PDF print view and editable Word report.', 'contactpanda-business' ),
				'inputSchema' => array( 'type' => 'object', 'required' => array( 'project_id' ), 'properties' => array( 'project_id' => array( 'type' => 'integer' ) ) ),
			),
		);
	}

	private static function call( $name, $args ) { if ( 'contactpanda_business_projects_list' === $name ) return self::projects( $args ); if ( 'contactpanda_business_project_get' === $name ) return self::project( $args ); if ( 'contactpanda_business_tasks_search' === $name ) return self::tasks( $args ); if ( 'contactpanda_business_import_stage' === $name ) return self::stage( $args ); if ( 'contactpanda_business_report_prepare' === $name ) return self::report( absint( $args['project_id'] ?? 0 ) ); return null; }

	private static function projects( $args ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $where = 'p.deleted_at IS NULL'; $values = array(); $search = sanitize_text_field( $args['search'] ?? '' ); if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where .= ' AND (p.name LIKE %s OR p.description LIKE %s)'; array_push( $values, $like, $like ); } $limit = min( 100, max( 1, absint( $args['limit'] ?? 50 ) ) ); $sql = "SELECT p.id,p.uuid,p.name,p.description,p.color,p.status,p.visibility,p.updated_at,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL AND x.board_column!='done') open_tasks,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL) total_tasks FROM `{$t['projects']}` p WHERE $where ORDER BY p.updated_at DESC LIMIT $limit"; $rows = $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); return array( 'projects' => $rows ); }
	private static function project( $args ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $id = absint( $args['project_id'] ?? 0 ); $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $id ), ARRAY_A ); if ( ! $project ) return new WP_Error( 'project_missing', __( 'The project does not exist.', 'contactpanda-business' ) ); $areas = $wpdb->get_results( $wpdb->prepare( "SELECT id,name,description,color,assigned_user_id,visibility,status FROM `{$t['areas']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,name", $id ), ARRAY_A ); $task_where = empty( $args['include_done'] ) ? " AND board_column!='done'" : ''; $tasks = $wpdb->get_results( $wpdb->prepare( "SELECT id,uuid,area_id,title,description,board_column,open_status,progress,priority,assigned_user_id,due_at,visibility,revision,updated_at FROM `{$t['tasks']}` WHERE project_id=%d AND deleted_at IS NULL$task_where ORDER BY sort_order,id", $id ), ARRAY_A ); return array( 'project' => $project, 'areas' => $areas, 'tasks' => $tasks, 'admin_url' => admin_url( 'admin.php?page=contactpanda-business-projects&project=' . $id ) ); }
	private static function tasks( $args ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $where = array( 'x.deleted_at IS NULL', 'p.deleted_at IS NULL' ); $values = array(); foreach ( array( 'project_id', 'area_id', 'assigned_user_id' ) as $key ) if ( isset( $args[ $key ] ) && '' !== $args[ $key ] ) { $where[] = 'x.' . $key . '=%d'; $values[] = absint( $args[ $key ] ); } $status = sanitize_key( $args['status'] ?? '' ); if ( $status ) { $where[] = 'x.board_column=%s'; $values[] = $status; } $search = sanitize_text_field( $args['search'] ?? '' ); if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(x.title LIKE %s OR x.description LIKE %s)'; array_push( $values, $like, $like ); } $limit = min( 200, max( 1, absint( $args['limit'] ?? 50 ) ) ); $sql = "SELECT x.id,x.uuid,x.project_id,p.name project_name,x.area_id,a.name area_name,x.title,x.description,x.board_column,x.open_status,x.progress,x.priority,x.assigned_user_id,x.due_at,x.visibility,x.revision,x.updated_at FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id LEFT JOIN `{$t['areas']}` a ON a.id=x.area_id WHERE " . implode( ' AND ', $where ) . " ORDER BY x.updated_at DESC LIMIT $limit"; $rows = $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); return array( 'tasks' => $rows ); }
	private static function stage( $args ) { $rows = is_array( $args['items'] ?? null ) ? $args['items'] : array(); if ( ! $rows ) return new WP_Error( 'items_missing', __( 'Provide at least one task for review.', 'contactpanda-business' ) ); $id = ContactPanda_Business_Imports::stage_rows( $rows, 'mcp', sanitize_text_field( $args['source_name'] ?? 'MCP' ) ); if ( is_wp_error( $id ) ) return $id; return array( 'import_id' => $id, 'status' => 'review', 'live_tasks_changed' => false, 'review_url' => admin_url( 'admin.php?page=contactpanda-business-import&import_id=' . $id ) ); }
	private static function report( $project_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); if ( ! $project_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ) ) ) return new WP_Error( 'project_missing', __( 'The project does not exist.', 'contactpanda-business' ) ); $base = admin_url( 'admin-post.php?action=contactpanda_business_export_report&project_id=' . $project_id ); return array( 'project_id' => $project_id, 'html_pdf_preview_url' => wp_nonce_url( $base . '&format=html', 'contactpanda_business_export_report_' . $project_id ), 'word_download_url' => wp_nonce_url( $base . '&format=docx', 'contactpanda_business_export_report_' . $project_id ), 'internal_items_included' => false ); }
	private static function audit( $tool, $success ) { $log = (array) get_option( self::AUDIT_OPTION, array() ); $log[] = array( 'time' => current_time( 'mysql', true ), 'user_id' => get_current_user_id(), 'tool' => sanitize_key( $tool ), 'success' => (bool) $success ); update_option( self::AUDIT_OPTION, array_slice( $log, -200 ), false ); }
	private static function result( $id, $result ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'result' => $result ) ); }
	private static function error( $id, $code, $message ) { return rest_ensure_response( array( 'jsonrpc' => '2.0', 'id' => $id, 'error' => array( 'code' => $code, 'message' => $message ) ) ); }
}
