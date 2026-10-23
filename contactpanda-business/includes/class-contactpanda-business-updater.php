<?php

defined( 'ABSPATH' ) || exit;

function contactpanda_business_register_updater() {
	if ( ! class_exists( 'ContactPanda_Licensed_Updater' ) ) {
		add_action( 'admin_notices', 'contactpanda_business_updater_notice' );
		return;
	}
	new ContactPanda_Licensed_Updater(
		array(
			'product'     => 'contactpanda-business',
			'entitlement' => 'business',
			'plugin'      => plugin_basename( CONTACTPANDA_BUSINESS_FILE ),
			'slug'        => 'contactpanda-business',
			'name'        => 'ContactPanda Business',
			'version'     => CONTACTPANDA_BUSINESS_VERSION,
			'channel'     => defined( 'CONTACTPANDA_UPDATE_CHANNEL' ) ? CONTACTPANDA_UPDATE_CHANNEL : 'stable',
		)
	);
}
add_action( 'plugins_loaded', 'contactpanda_business_register_updater', 23 );

function contactpanda_business_updater_notice() {
	if ( current_user_can( 'update_plugins' ) ) echo '<div class="notice notice-warning"><p>' . esc_html__( 'Automatic ContactPanda Business updates require a current ContactPanda Basic installation.', 'contactpanda-business' ) . '</p></div>';
}
