<?php

defined( 'ABSPATH' ) || exit;

/** Support Desk tools registered on the shared ContactPanda MCP endpoint. */
final class ContactPanda_Pro_MCP {
	const DRAFT_OPTION = 'contactpanda_pro_mcp_reply_drafts';

	public static function boot() {
		if ( ! class_exists( 'ContactPanda_MCP' ) || ! contactpanda_pro_module_entitled( 'support_desk' ) ) return;
		add_filter( 'contactpanda_mcp_tools', array( __CLASS__, 'tools' ) );
		add_filter( 'contactpanda_mcp_tool_call', array( __CLASS__, 'call' ), 10, 3 );
	}

	public static function tools( $tools ) {
		if ( ContactPanda_Pro_Access::can( 'view', 'support_ticket' ) ) {
			$tools[] = array( 'name' => 'contactpanda_support_search', 'description' => __( 'Search support requests without exposing message bodies.', 'contactpanda-pro' ), 'inputSchema' => array( 'type' => 'object', 'properties' => array( 'search' => array( 'type' => 'string' ), 'status' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 100 ) ) ) );
			$tools[] = array( 'name' => 'contactpanda_support_get', 'description' => __( 'Read one support request and its customer-visible conversation.', 'contactpanda-pro' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'ticket_id' ), 'properties' => array( 'ticket_id' => array( 'type' => 'integer' ) ) ) );
		}
		if ( ContactPanda_Pro_Access::can( 'create', 'support_ticket' ) ) {
			$tools[] = array( 'name' => 'contactpanda_support_create', 'description' => __( 'Create a support request and optionally create and link a new person.', 'contactpanda-pro' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'subject', 'message', 'requester_email' ), 'properties' => array( 'contact_id' => array( 'type' => 'integer' ), 'first_name' => array( 'type' => 'string' ), 'last_name' => array( 'type' => 'string' ), 'requester_email' => array( 'type' => 'string' ), 'subject' => array( 'type' => 'string' ), 'message' => array( 'type' => 'string' ), 'request_type' => array( 'type' => 'string' ), 'priority' => array( 'type' => 'string' ), 'related_type' => array( 'type' => 'string' ), 'related_id' => array( 'type' => 'string' ), 'related_label' => array( 'type' => 'string' ), 'license_reference' => array( 'type' => 'string' ), 'product_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ), 'order_ids' => array( 'type' => 'array', 'items' => array( 'type' => 'integer' ) ) ) ) );
		}
		if ( ContactPanda_Pro_Access::can( 'edit', 'support_ticket' ) ) {
			$tools[] = array( 'name' => 'contactpanda_support_reply_draft', 'description' => __( 'Prepare and store a reply draft. This never sends an email.', 'contactpanda-pro' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'ticket_id', 'body' ), 'properties' => array( 'ticket_id' => array( 'type' => 'integer' ), 'body' => array( 'type' => 'string' ), 'internal' => array( 'type' => 'boolean' ) ) ) );
			$tools[] = array( 'name' => 'contactpanda_support_update', 'description' => __( 'Update status, priority, or assignee for a support request.', 'contactpanda-pro' ), 'inputSchema' => array( 'type' => 'object', 'required' => array( 'ticket_id' ), 'properties' => array( 'ticket_id' => array( 'type' => 'integer' ), 'status' => array( 'type' => 'string', 'enum' => array( 'new', 'open', 'waiting_support', 'waiting_customer', 'resolved', 'closed' ) ), 'priority' => array( 'type' => 'string', 'enum' => array( 'low', 'normal', 'high', 'urgent' ) ), 'assigned_user_id' => array( 'type' => 'integer' ) ) ) );
		}
		return $tools;
	}

	public static function call( $result, $name, $args ) {
		if ( null !== $result ) return $result;
		if ( 'contactpanda_support_search' === $name ) return self::search( $args );
		if ( 'contactpanda_support_get' === $name ) return self::get( absint( $args['ticket_id'] ?? 0 ) );
		if ( 'contactpanda_support_create' === $name ) return self::create( $args );
		if ( 'contactpanda_support_reply_draft' === $name ) return self::reply_draft( $args );
		if ( 'contactpanda_support_update' === $name ) return self::update( $args );
		return null;
	}

	private static function search( $args ) {
		if ( ! ContactPanda_Pro_Access::can( 'view', 'support_ticket' ) ) return ContactPanda_Pro_Access::permission_error();
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $where = array( '1=1' ); $values = array(); $status = sanitize_key( $args['status'] ?? '' );
		if ( $status ) { $where[] = 'status=%s'; $values[] = $status; } $search = sanitize_text_field( $args['search'] ?? '' ); if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(subject LIKE %s OR requester_name LIKE %s OR requester_email LIKE %s OR license_reference LIKE %s)'; array_push( $values, $like, $like, $like, $like ); }
		$limit = min( 100, max( 1, absint( $args['limit'] ?? 25 ) ) ); $sql = "SELECT id,subject,requester_name,requester_email,status,priority,assigned_user_id,updated_at FROM `{$tables['support_tickets']}` WHERE " . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT ' . $limit; $rows = $values ? $wpdb->get_results( $wpdb->prepare( $sql, $values ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array( 'tickets' => $rows );
	}

	private static function get( $id ) {
		if ( ! ContactPanda_Pro_Access::can( 'view', 'support_ticket', $id ) ) return ContactPanda_Pro_Access::permission_error();
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tables['support_tickets']}` WHERE id=%d", $id ), ARRAY_A ); if ( ! $ticket ) return new WP_Error( 'ticket_missing', __( 'The support request does not exist.', 'contactpanda-pro' ) );
		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT id,direction,visibility,body,created_at FROM `{$tables['support_messages']}` WHERE ticket_id=%d AND visibility='customer' ORDER BY created_at,id", $id ), ARRAY_A ); $drafts = (array) get_option( self::DRAFT_OPTION, array() );
		return array( 'ticket' => $ticket, 'messages' => $messages, 'reply_draft' => $drafts[ $id ] ?? null, 'admin_url' => admin_url( 'admin.php?page=contactpanda-business-support&ticket=' . $id ) );
	}

	private static function create( $args ) {
		if ( ! ContactPanda_Pro_Access::can( 'create', 'support_ticket' ) ) return ContactPanda_Pro_Access::permission_error();
		$email = sanitize_email( $args['requester_email'] ?? '' ); $subject = sanitize_text_field( $args['subject'] ?? '' ); $message = sanitize_textarea_field( $args['message'] ?? '' ); if ( ! is_email( $email ) || ! $subject || ! $message ) return new WP_Error( 'invalid_ticket', __( 'Requester email, subject, and message are required.', 'contactpanda-pro' ) );
		$contact_id = absint( $args['contact_id'] ?? 0 ); if ( ! $contact_id && method_exists( 'ContactPanda_Plugin', 'extension_contact_by_email' ) ) { $contact = ContactPanda_Plugin::extension_contact_by_email( $email ); if ( $contact ) $contact_id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) ); }
		if ( ! $contact_id && method_exists( 'ContactPanda_Plugin', 'extension_create_contact' ) ) { $created = ContactPanda_Plugin::extension_create_contact( array( 'first_name' => sanitize_text_field( $args['first_name'] ?? '' ), 'last_name' => sanitize_text_field( $args['last_name'] ?? '' ), 'email' => $email ) ); if ( is_wp_error( $created ) ) return $created; $contact_id = absint( $created ); }
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names();
		$source_id = absint( $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$tables['support_sources']}` WHERE base_url=%s ORDER BY id LIMIT 1", home_url( '/' ) ) ) );
		if ( ! $source_id ) $source_id = absint( $wpdb->get_var( "SELECT id FROM `{$tables['support_sources']}` WHERE status='active' ORDER BY id LIMIT 1" ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table name.
		$now = current_time( 'mysql', true ); $uuid = wp_generate_uuid4(); $priorities = array( 'low', 'normal', 'high', 'urgent' ); $priority = sanitize_key( $args['priority'] ?? 'normal' ); if ( ! in_array( $priority, $priorities, true ) ) $priority = 'normal'; $products = array_slice( array_values( array_filter( array_map( 'absint', (array) ( $args['product_ids'] ?? array() ) ) ) ), 0, 50 ); $orders = array_slice( array_values( array_filter( array_map( 'absint', (array) ( $args['order_ids'] ?? array() ) ) ) ), 0, 50 );
		$ok = $wpdb->insert( $tables['support_tickets'], array( 'uuid' => $uuid, 'source_id' => $source_id, 'contact_id' => $contact_id, 'requester_name' => trim( sanitize_text_field( $args['first_name'] ?? '' ) . ' ' . sanitize_text_field( $args['last_name'] ?? '' ) ), 'requester_email' => $email, 'subject' => $subject, 'request_type' => sanitize_key( $args['request_type'] ?? 'general' ), 'related_type' => sanitize_key( $args['related_type'] ?? '' ), 'related_id' => sanitize_text_field( $args['related_id'] ?? '' ), 'related_label' => sanitize_text_field( $args['related_label'] ?? '' ), 'license_reference' => sanitize_text_field( $args['license_reference'] ?? '' ), 'products_json' => wp_json_encode( $products ), 'orders_json' => wp_json_encode( $orders ), 'external_order_id' => $orders ? (string) reset( $orders ) : '', 'status' => 'new', 'priority' => $priority, 'created_at' => $now, 'updated_at' => $now ) ); if ( false === $ok ) return new WP_Error( 'ticket_failed', __( 'The support request could not be created.', 'contactpanda-pro' ) );
		$id = absint( $wpdb->insert_id ); $wpdb->insert( $tables['support_messages'], array( 'ticket_id' => $id, 'visibility' => 'customer', 'direction' => 'inbound', 'author_contact_id' => $contact_id, 'body' => $message, 'external_source' => 'mcp', 'external_id' => 'mcp-' . $uuid, 'created_at' => $now ) ); ContactPanda_Pro_Access::audit( 'created', 'support_ticket', $id, array(), array( 'status' => 'new', 'priority' => $priority, 'contact_id' => $contact_id ), array( 'channel' => 'mcp' ) ); return array( 'ticket_id' => $id, 'contact_id' => $contact_id, 'status' => 'new', 'admin_url' => admin_url( 'admin.php?page=contactpanda-business-support&ticket=' . $id ) );
	}

	private static function reply_draft( $args ) {
		$id = absint( $args['ticket_id'] ?? 0 ); if ( ! ContactPanda_Pro_Access::can( 'edit', 'support_ticket', $id ) ) return ContactPanda_Pro_Access::permission_error(); $body = sanitize_textarea_field( $args['body'] ?? '' ); if ( ! $id || ! $body ) return new WP_Error( 'invalid_draft', __( 'Support request and reply text are required.', 'contactpanda-pro' ) ); $ticket = self::get( $id ); if ( is_wp_error( $ticket ) ) return $ticket; $drafts = (array) get_option( self::DRAFT_OPTION, array() ); $drafts[ $id ] = array( 'body' => $body, 'internal' => ! empty( $args['internal'] ), 'created_by' => get_current_user_id(), 'updated_at' => current_time( 'mysql', true ) ); update_option( self::DRAFT_OPTION, $drafts, false ); ContactPanda_Pro_Access::audit( 'reply_draft_saved', 'support_ticket', $id, array(), array( 'internal' => ! empty( $args['internal'] ) ), array( 'channel' => 'mcp' ) ); return array( 'ticket_id' => $id, 'draft_saved' => true, 'sent' => false, 'admin_url' => admin_url( 'admin.php?page=contactpanda-business-support&ticket=' . $id ) );
	}

	private static function update( $args ) {
		$id = absint( $args['ticket_id'] ?? 0 ); if ( ! ContactPanda_Pro_Access::can( 'edit', 'support_ticket', $id ) ) return ContactPanda_Pro_Access::permission_error(); if ( ! $id ) return new WP_Error( 'ticket_missing', __( 'The support request does not exist.', 'contactpanda-pro' ) ); global $wpdb; $table = ContactPanda_Pro_Schema::table_names()['support_tickets']; $existing = $wpdb->get_row( $wpdb->prepare( "SELECT status,priority,assigned_user_id FROM `{$table}` WHERE id=%d", $id ), ARRAY_A ); if ( ! $existing ) return new WP_Error( 'ticket_missing', __( 'The support request does not exist.', 'contactpanda-pro' ) ); $statuses = array( 'new', 'open', 'waiting_support', 'waiting_customer', 'resolved', 'closed' ); $priorities = array( 'low', 'normal', 'high', 'urgent' ); $status = sanitize_key( $args['status'] ?? $existing['status'] ); $priority = sanitize_key( $args['priority'] ?? $existing['priority'] ); if ( ! in_array( $status, $statuses, true ) || ! in_array( $priority, $priorities, true ) ) return new WP_Error( 'invalid_status', __( 'Status or priority is invalid.', 'contactpanda-pro' ) ); $assigned_user_id = absint( $args['assigned_user_id'] ?? $existing['assigned_user_id'] ); if ( absint( $existing['assigned_user_id'] ) !== $assigned_user_id && ! ContactPanda_Pro_Access::can( 'assign', 'support_ticket', $id ) ) return ContactPanda_Pro_Access::permission_error(); $after = array( 'status' => $status, 'priority' => $priority, 'assigned_user_id' => $assigned_user_id ); $wpdb->update( $table, array_merge( $after, array( 'updated_at' => current_time( 'mysql', true ), 'closed_at' => 'closed' === $status ? current_time( 'mysql', true ) : null ) ), array( 'id' => $id ) ); ContactPanda_Pro_Access::audit( 'updated', 'support_ticket', $id, $existing, $after, array( 'channel' => 'mcp' ) ); return array( 'ticket_id' => $id, 'status' => $status, 'priority' => $priority );
	}
}
