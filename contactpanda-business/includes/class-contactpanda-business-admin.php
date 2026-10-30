<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Business_Admin {
	public static function boot() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ), 37 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	public static function menu() {
		$icon = defined( 'CONTACTPANDA_URL' ) ? CONTACTPANDA_URL . 'assets/images/contactpanda-admin-icon.png' : 'dashicons-portfolio';
		add_menu_page( __( 'ContactPanda Business', 'contactpanda-business' ), __( 'ContactPanda Business', 'contactpanda-business' ), 'contactpanda_business_access', 'contactpanda-business', array( __CLASS__, 'overview' ), $icon, 3.2 );
		add_submenu_page( 'contactpanda-business', __( 'Overview', 'contactpanda-business' ), __( 'Dashboard', 'contactpanda-business' ), 'contactpanda_business_access', 'contactpanda-business', array( __CLASS__, 'overview' ) );
		if ( ! contactpanda_business_module_entitled( 'project_management' ) ) return;
		add_submenu_page( 'contactpanda-business', __( 'Projects', 'contactpanda-business' ), __( 'Projects', 'contactpanda-business' ), 'contactpanda_projects_view', 'contactpanda-business-projects', array( 'ContactPanda_Business_Projects', 'render' ) );
		add_submenu_page( 'contactpanda-business', __( 'Tasks', 'contactpanda-business' ), __( 'Tasks', 'contactpanda-business' ), 'contactpanda_tasks_view', 'contactpanda-business-tasks', array( 'ContactPanda_Business_Workspace', 'render_tasks' ) );
		if ( contactpanda_business_module_entitled( 'support_desk' ) ) {
			add_submenu_page( 'contactpanda-business', __( 'Support Desk', 'contactpanda-business' ), __( 'Support Desk', 'contactpanda-business' ), 'contactpanda_support_view', 'contactpanda-business-support', array( 'ContactPanda_Pro_Helpdesk', 'render' ) );
		}
		add_submenu_page( 'contactpanda-business', __( 'Import tasks', 'contactpanda-business' ), __( 'Import tasks', 'contactpanda-business' ), 'contactpanda_project_imports_manage', 'contactpanda-business-import', array( 'ContactPanda_Business_Imports', 'render' ) );
	}

	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'contactpanda' ) ) return;
		wp_enqueue_style( 'contactpanda-business-admin', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-admin.css', array(), CONTACTPANDA_BUSINESS_VERSION );
		if ( false !== strpos( $hook, 'contactpanda-business-support' ) ) {
			wp_enqueue_style( 'contactpanda-business-support', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-support.css', array( 'contactpanda-business-admin' ), CONTACTPANDA_BUSINESS_VERSION );
			wp_enqueue_script( 'contactpanda-business-support', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-support.js', array(), CONTACTPANDA_BUSINESS_VERSION, true );
		}
		if ( false === strpos( $hook, 'contactpanda-business' ) ) return;
		wp_enqueue_script( 'contactpanda-business-admin', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-admin.js', array(), CONTACTPANDA_BUSINESS_VERSION, true );
		wp_localize_script( 'contactpanda-business-admin', 'contactPandaBusiness', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ), 'nonce' => wp_create_nonce( 'contactpanda_business_move_task' ), 'currentUserId' => get_current_user_id(), 'selectParticipants' => __( 'Select participants', 'contactpanda-business' ), 'selectFiles' => __( 'Select one or more files.', 'contactpanda-business' ), 'moveFailed' => __( 'The task could not be moved.', 'contactpanda-business' ) ) );
	}

	public static function overview() {
		self::guard();
		if ( ! contactpanda_business_module_entitled( 'project_management' ) ) {
			$license_url = admin_url( 'admin.php?page=contactpanda-settings&tab=licenses' );
			echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html__( 'Business overview', 'contactpanda-business' ) . '</h1></div></header><section class="cpb-card cpb-license-required"><h2>' . esc_html__( 'A Business license is required', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Activate a valid ContactPanda Business license to use projects, tasks, imports, reports, and the Business MCP interface.', 'contactpanda-business' ) . '</p><p><a class="button button-primary" href="' . esc_url( $license_url ) . '">' . esc_html__( 'Open license settings', 'contactpanda-business' ) . '</a></p></section></div>';
			return;
		}
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$projects = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t['projects']}` WHERE deleted_at IS NULL" );
		$tasks = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$t['tasks']}` WHERE deleted_at IS NULL AND board_column!='done'" );
		$mcp_url = rest_url( 'contactpanda/v1/mcp' );
		echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html__( 'Overview', 'contactpanda-business' ) . '</h1><p>' . esc_html__( 'Projects, tasks, reviewed imports, and customer reports.', 'contactpanda-business' ) . '</p></div></header><section class="cpb-card cpb-product-visual"><img src="' . esc_url( defined( 'CONTACTPANDA_URL' ) ? CONTACTPANDA_URL . 'assets/images/contactpanda-admin-icon.png' : CONTACTPANDA_BUSINESS_URL . 'assets/images/contactpanda-business-dashboard-simple.png' ) . '" alt=""><div><h2>' . esc_html__( 'Your project workspace', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Track projects, responsibilities, corrections, files, and customer-visible progress without losing the connection to ContactPanda people and companies.', 'contactpanda-business' ) . '</p></div></section><div class="cpb-stat-grid"><section class="cpb-card"><h2>' . esc_html__( 'Projects', 'contactpanda-business' ) . '</h2><strong>' . esc_html( $projects ) . '</strong></section><section class="cpb-card"><h2>' . esc_html__( 'Open tasks', 'contactpanda-business' ) . '</h2><strong>' . esc_html( $tasks ) . '</strong></section></div><p><a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-projects' ) ) . '">' . esc_html__( 'Open projects & corrections', 'contactpanda-business' ) . '</a></p><div class="cpb-dashboard-secondary"><section class="cpb-card"><p class="contactpanda-eyebrow">' . esc_html__( 'New in this version', 'contactpanda-business' ) . '</p><h2>' . esc_html( CONTACTPANDA_BUSINESS_VERSION ) . '</h2><p>' . esc_html__( 'Business follows the coordinated family dependency and protected-update contract.', 'contactpanda-business' ) . '</p></section><section class="cpb-card"><h2>' . esc_html__( 'Business MCP interface', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Use a WordPress application password to read projects and tasks or to create import drafts. MCP never applies a draft without confirmation in WordPress.', 'contactpanda-business' ) . '</p><code>' . esc_html( $mcp_url ) . '</code></section></div></div>';
	}

	private static function guard() { if ( ! ContactPanda_Business_Access::can( 'view', 'business' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); }
}
