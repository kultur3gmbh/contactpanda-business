<?php
/**
 * Plugin Name: ContactPanda Business
 * Description: Project, correction, customer, contract, subscription, and payment management for ContactPanda.
 * Version: 0.8.3
 * Author: Kultur3 GmbH
 * Text Domain: contactpanda-business
 * Domain Path: /languages
 * Requires at least: 6.4
 * Requires PHP: 7.4
 * Requires Plugins: contactpanda
 * Requires ContactPanda: 0.8.1
 * Update URI: https://github.com/kultur3gmbh/contactpanda-business
 */

defined( 'ABSPATH' ) || exit;

define( 'CONTACTPANDA_BUSINESS_VERSION', '0.8.3' );
define( 'CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION', '0.8.1' );
define( 'CONTACTPANDA_BUSINESS_FILE', __FILE__ );
define( 'CONTACTPANDA_BUSINESS_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONTACTPANDA_BUSINESS_URL', plugin_dir_url( __FILE__ ) );

require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-updater.php';

function contactpanda_business_license_modules( $modules ) {
	$modules['business']                = __( 'ContactPanda Business', 'contactpanda-business' );
	$modules['project_management']      = __( 'Project and correction management', 'contactpanda-business' );
	$modules['customer_management']     = __( 'Customer management', 'contactpanda-business' );
	$modules['contract_management']     = __( 'Contract management', 'contactpanda-business' );
	$modules['subscription_management'] = __( 'Subscription management', 'contactpanda-business' );
	$modules['payment_management']      = __( 'Payment management', 'contactpanda-business' );
	return $modules;
}
add_filter( 'contactpanda_license_modules', 'contactpanda_business_license_modules' );

function contactpanda_business_module_entitled( $module ) {
	return class_exists( 'ContactPanda_License' ) && ( ContactPanda_License::entitled( 'business' ) || ContactPanda_License::entitled( $module ) );
}

function contactpanda_business_activate() {
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-schema.php';
	ContactPanda_Business_Schema::install();
	add_rewrite_endpoint( 'projekte-korrekturen', EP_ROOT | EP_PAGES );
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'contactpanda_business_activate' );
register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );

function contactpanda_business_boot() {
	load_plugin_textdomain( 'contactpanda-business', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	if ( ! defined( 'CONTACTPANDA_VERSION' ) || version_compare( CONTACTPANDA_VERSION, CONTACTPANDA_BUSINESS_MIN_BASIC_VERSION, '<' ) ) {
		add_action( 'admin_notices', 'contactpanda_business_dependency_notice' );
		return;
	}
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-schema.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-projects.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-admin.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-workspace.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-imports.php';
	require_once CONTACTPANDA_BUSINESS_DIR . 'includes/class-contactpanda-business-mcp.php';
	ContactPanda_Business_Schema::maybe_upgrade();
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
add_action( 'plugins_loaded', 'contactpanda_business_boot', 22 );

/**
 * Give stale forms and bookmarked admin-post URLs a useful response when the
 * Business entitlement is missing instead of leaving WordPress on a blank page.
 */
function contactpanda_business_register_locked_actions() {
	$actions = array(
		'contactpanda_business_save_project',
		'contactpanda_business_save_task',
		'contactpanda_business_add_comment',
		'contactpanda_business_trash_task',
		'contactpanda_business_from_support',
		'contactpanda_business_customer_task',
		'contactpanda_business_customer_comment',
		'contactpanda_business_customer_update_task',
		'contactpanda_business_project_action',
		'contactpanda_business_task_action',
		'contactpanda_business_save_area',
		'contactpanda_business_export_report',
		'contactpanda_business_upload_import',
		'contactpanda_business_apply_import',
	);
	foreach ( $actions as $action ) {
		add_action( 'admin_post_' . $action, 'contactpanda_business_license_required' );
	}
	add_action( 'wp_ajax_contactpanda_business_move_task', 'contactpanda_business_license_required' );
}

function contactpanda_business_license_required() {
	if ( ! current_user_can( 'manage_options' ) ) {
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
	if ( function_exists( 'is_account_page' ) && is_account_page() ) wp_enqueue_style( 'contactpanda-business-account', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-account.css', array(), CONTACTPANDA_BUSINESS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'contactpanda_business_frontend_assets' );

function contactpanda_business_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) return;
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ContactPanda Business requires ContactPanda Basic 0.8.1 or newer.', 'contactpanda-business' ) . '</p></div>';
}
