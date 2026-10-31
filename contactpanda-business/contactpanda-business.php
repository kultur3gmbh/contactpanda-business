<?php
/**
 * Plugin Name: ContactPanda Business
 * Description: Professional CRM, support, project, task, correction, import, and customer workspace for ContactPanda.
 * Version: 0.8.82
 * Author: Kultur3 GmbH
 * Text Domain: contactpanda-business
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: contactpanda, contactpanda-plus
 * Requires ContactPanda: 0.8.82
 * Requires ContactPanda Plus: 0.8.82
 * Update URI: https://github.com/kultur3gmbh/contactpanda-business
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_BUSINESS_VERSION', '0.8.82' );
define( 'CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION', '0.8.82' );
define( 'CONTACTPANDA_BUSINESS_MIN_PLUS_VERSION', '0.8.82' );
define( 'CONTACTPANDA_BUSINESS_FILE', __FILE__ );
define( 'CONTACTPANDA_BUSINESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_BUSINESS_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-mcp.php';
require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-access.php';

// Register Abilities API hooks before secure MCP plugins initialize the registry.
ContactPanda_Business_MCP::register_ability_hooks();

function contactpanda_business_license_modules( $modules ) {
	$modules['business']                = __( 'ContactPanda Business', 'contactpanda-business' );
	$modules['project_management']      = __( 'Project and correction management', 'contactpanda-business' );
	$modules['support_desk']            = __( 'Support Desk', 'contactpanda-business' );
	$modules['customer_management']     = __( 'Customer management', 'contactpanda-business' );
	return $modules;
}
add_filter( 'contactpanda_license_modules', 'contactpanda_business_license_modules' );

function contactpanda_business_license_product( $products ) {
	$products['contactpanda-business'] = array(
		'label'       => __( 'ContactPanda Business', 'contactpanda-business' ),
		'entitlement' => 'business',
		'version'     => CONTACTPANDA_BUSINESS_VERSION,
		'modules'     => array( 'project_management', 'support_desk', 'customer_management' ),
	);
	return $products;
}
add_filter( 'contactpanda_license_products', 'contactpanda_business_license_product' );

function contactpanda_business_module_entitled( $module ) {
	return class_exists( 'ContactPanda_License' )
		&& ( ContactPanda_License::entitled( 'business' ) || ContactPanda_License::entitled( 'pro' ) || ContactPanda_License::entitled( $module ) );
}

/**
 * Preserve the former Pro extension points after every plugin file has loaded.
 *
 * Business is alphabetically loaded before an installed legacy Pro version.
 * Defining these functions at file scope would therefore make the legacy Pro
 * plugin redeclare them and trigger a fatal error before WordPress can finish
 * the coordinated family update.
 */
function contactpanda_business_register_pro_function_compat() {
	if ( ! function_exists( 'contactpanda_pro_module_entitled' ) ) {
		function contactpanda_pro_module_entitled( $module ) { return contactpanda_business_module_entitled( $module ); }
	}
	if ( ! function_exists( 'contactpanda_pro_any_entitled' ) ) {
		function contactpanda_pro_any_entitled() { return contactpanda_business_module_entitled( 'business' ); }
	}
}
add_action( 'plugins_loaded', 'contactpanda_business_register_pro_function_compat', 1 );

function contactpanda_business_activate() {
	$dependency = contactpanda_business_dependency_error();
	if ( is_wp_error( $dependency ) ) {
		wp_die( esc_html( $dependency->get_error_message() ), esc_html__( 'ContactPanda dependencies required', 'contactpanda-business' ), array( 'back_link' => true ) );
	}
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-schema.php';
	if ( ! class_exists( 'ContactPanda_Pro_Schema' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-schema.php';
	if ( ! class_exists( 'ContactPanda_Pro_Access' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-access.php';
	ContactPanda_Pro_Schema::install();
	ContactPanda_Pro_Access::register_capabilities();
	ContactPanda_Business_Schema::install();
	ContactPanda_Business_Access::register_capabilities();
	add_rewrite_endpoint( 'projekte-korrekturen', EP_ROOT | EP_PAGES );
	add_rewrite_endpoint( 'hilfe-support', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'contactpanda_business_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

/** Return a dependency error before activation mutates any Business-owned data. */
function contactpanda_business_dependency_error() {
	if ( ! defined( 'CONTACTPANDA_VERSION' ) || version_compare( CONTACTPANDA_VERSION, CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION, '<' ) || ! class_exists( 'ContactPanda_Plugin' ) ) {
		return new WP_Error(
			'contactpanda_business_basic_required',
			sprintf( __( 'ContactPanda Business requires the active ContactPanda Basic version %s or newer.', 'contactpanda-business' ), CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION )
		);
	}
	if ( ! defined( 'CONTACTPANDA_PLUS_VERSION' ) || version_compare( CONTACTPANDA_PLUS_VERSION, CONTACTPANDA_BUSINESS_MIN_PLUS_VERSION, '<' ) ) {
		return new WP_Error(
			'contactpanda_business_plus_required',
			sprintf( __( 'ContactPanda Business requires the active ContactPanda Plus version %s or newer.', 'contactpanda-business' ), CONTACTPANDA_BUSINESS_MIN_PLUS_VERSION )
		);
	}
	return true;
}

function contactpanda_business_boot() {
	load_plugin_textdomain( 'contactpanda-business', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	load_plugin_textdomain( 'contactpanda-pro', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	if ( ! defined( 'CONTACTPANDA_VERSION' ) || version_compare( CONTACTPANDA_VERSION, CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION, '<' ) || ! defined( 'CONTACTPANDA_PLUS_VERSION' ) || version_compare( CONTACTPANDA_PLUS_VERSION, CONTACTPANDA_BUSINESS_MIN_PLUS_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'contactpanda_business_dependency_notice' );
		return;
	}
	if ( ! class_exists( 'ContactPanda_Pro_Schema' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-schema.php';
	if ( ! class_exists( 'ContactPanda_Pro_Access' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-access.php';
	if ( ! class_exists( 'ContactPanda_Pro_Helpdesk' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-helpdesk.php';
	if ( ! class_exists( 'ContactPanda_Pro_MCP' ) ) require_once CONTACTPANDA_BUSINESS_DIR . 'includes/compat/class-contactpanda-pro-mcp.php';
	ContactPanda_Pro_Schema::maybe_upgrade();
	ContactPanda_Pro_Access::boot();
	ContactPanda_Business_Access::boot();
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-schema.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-projects.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-admin.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-workspace.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-imports.php';
	ContactPanda_Business_Schema::maybe_upgrade();
	ContactPanda_Business_Schema::link_pro_customers();
	if ( contactpanda_business_module_entitled( 'support_desk' ) ) ContactPanda_Pro_Helpdesk::boot();
	ContactPanda_Pro_MCP::boot();
	if ( contactpanda_business_module_entitled( 'project_management' ) ) {
		ContactPanda_Business_Projects::boot();
		ContactPanda_Business_Workspace::boot();
		ContactPanda_Business_Imports::boot();
		ContactPanda_Business_MCP::boot();
	} else {
		contactpanda_business_register_locked_actions();
	}
	ContactPanda_Business_Admin::boot();
}
add_action( 'plugins_loaded', 'contactpanda_business_boot', 50 );

/**
 * Give stale forms and bookmarked admin-post URLs a useful response when the
 * Business entitlement is missing instead of leaving WordPress on a blank page.
 */
function contactpanda_business_register_locked_actions() {
	$actions = array(
		'contactpanda_business_save_project',
		'contactpanda_business_save_project_field',
		'contactpanda_business_save_task',
		'contactpanda_business_add_comment',
		'contactpanda_business_upload_task_files',
		'contactpanda_business_remove_task_file',
		'contactpanda_business_add_checklist_item',
		'contactpanda_business_toggle_checklist_item',
		'contactpanda_business_trash_task',
		'contactpanda_business_from_support',
		'contactpanda_business_customer_task',
		'contactpanda_business_customer_comment',
		'contactpanda_business_customer_update_task',
		'contactpanda_business_project_action',
		'contactpanda_business_task_action',
		'contactpanda_business_save_area',
		'contactpanda_business_export_report',
		'contactpanda_business_save_project_step',
		'contactpanda_business_save_project_payment',
		'contactpanda_business_upload_project_documents',
		'contactpanda_business_remove_project_document',
		'contactpanda_business_upload_import',
		'contactpanda_business_apply_import',
	);
	foreach ( $actions as $action ) {
		add_action( 'admin_post_' . $action, 'contactpanda_business_license_required' );
	}
	add_action( 'wp_ajax_contactpanda_business_move_task', 'contactpanda_business_license_required' );
}

function contactpanda_business_license_required() {
	if ( ! ContactPanda_Business_Access::can( 'view', 'business' ) ) {
		wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
	}
	wp_die(
		esc_html__( 'A valid ContactPanda Business license is required. Activate the license in the ContactPanda settings and try again.', 'contactpanda-business' ),
		esc_html__( 'Business license required', 'contactpanda-business' ),
		array(
			'response'  => 403,
			'back_link' => true,
		)
	);
}

function contactpanda_business_frontend_assets() {
	$is_account = function_exists( 'is_account_page' ) && is_account_page();
	$is_portal  = class_exists( 'ContactPanda_Customer_Portal' ) && ContactPanda_Customer_Portal::is_portal_page();
	if ( $is_account || $is_portal ) {
		wp_enqueue_style( 'contactpanda-business-account', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-account.css', array(), CONTACTPANDA_BUSINESS_VERSION );
		wp_enqueue_style( 'contactpanda-business-support-account', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-support-account.css', array( 'contactpanda-business-account' ), CONTACTPANDA_BUSINESS_VERSION );
	}
}
add_action( 'wp_enqueue_scripts', 'contactpanda_business_frontend_assets' );

function contactpanda_business_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) return;
	echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'ContactPanda Business requires ContactPanda Basic %1$s and ContactPanda Plus %2$s or newer.', 'contactpanda-business' ), CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION, CONTACTPANDA_BUSINESS_MIN_PLUS_VERSION ) ) . '</p></div>';
}
