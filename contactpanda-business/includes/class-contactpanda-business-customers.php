<?php

defined( 'ABSPATH' ) || exit;

/** Creates and updates Business customer links without duplicating identity data. */
final class ContactPanda_Business_Customers {
	public static function ensure_customer( $contact_id, $company_id, $user_id, $source = 'contactpanda_business', $source_id = '' ) {
		global $wpdb;
		$contact_id = absint( $contact_id );
		$company_id = absint( $company_id );
		$user_id    = absint( $user_id );
		$source     = sanitize_key( $source );
		$source_id  = sanitize_text_field( $source_id );

		if ( $contact_id ) {
			$identity_key = 'contact:' . $contact_id;
		} elseif ( $company_id ) {
			$identity_key = 'company:' . $company_id;
		} elseif ( $user_id ) {
			$identity_key = 'user:' . $user_id;
		} elseif ( $source && $source_id ) {
			$identity_key = 'source:' . hash( 'sha256', $source . ':' . $source_id );
		} else {
			return new WP_Error( 'contactpanda_business_customer_identity', __( 'A customer identity is required.', 'contactpanda-business' ) );
		}

		$table = ContactPanda_Business_Service_Schema::table_names()['customers'];
		$id    = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$table}` WHERE identity_key=%s", $identity_key ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Internal prefix-safe table name.
		$now   = current_time( 'mysql', true );
		$data  = array(
			'contact_id' => $contact_id,
			'company_id' => $company_id,
			'user_id'    => $user_id,
			'source'     => $source ?: 'contactpanda_business',
			'source_id'  => $source_id,
			'updated_at' => $now,
		);
		if ( $id ) {
			$wpdb->update( $table, $data, array( 'id' => $id ) );
			return $id;
		}

		$data += array(
			'uuid'         => wp_generate_uuid4(),
			'identity_key' => $identity_key,
			'status'       => 'active',
			'created_at'   => $now,
		);
		if ( false === $wpdb->insert( $table, $data ) ) {
			return new WP_Error( 'contactpanda_business_customer_create', __( 'The customer record could not be created.', 'contactpanda-business' ) );
		}
		return absint( $wpdb->insert_id );
	}
}
