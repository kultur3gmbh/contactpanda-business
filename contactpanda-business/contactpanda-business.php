<?php
/**
 * Plugin Name: ContactPanda Business
 * Description: Project, correction, customer, contract, subscription, and payment management for ContactPanda.
 * Version: 0.8.1
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

define( 'CONTACTPANDA_BUSINESS_VERSION', '0.8.1' );
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
	ContactPanda_Business_Schema::maybe_upgrade();
	if ( contactpanda_business_module_entitled( 'project_management' ) ) ContactPanda_Business_Projects::boot();
	ContactPanda_Business_Admin::boot();
}
add_action( 'plugins_loaded', 'contactpanda_business_boot', 22 );

function contactpanda_business_frontend_assets() {
	if ( function_exists( 'is_account_page' ) && is_account_page() ) wp_enqueue_style( 'contactpanda-business-account', CONTACTPANDA_BUSINESS_URL . 'assets/contactpanda-business-account.css', array(), CONTACTPANDA_BUSINESS_VERSION );
}
add_action( 'wp_enqueue_scripts', 'contactpanda_business_frontend_assets' );

function contactpanda_business_dependency_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) return;
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ContactPanda Business requires ContactPanda Basic 0.8.1 or newer.', 'contactpanda-business' ) . '</p></div>';
}
