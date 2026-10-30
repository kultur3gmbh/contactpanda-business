<?php

defined( 'ABSPATH' ) || exit;

/** First usable, source-aware Support Desk module. */
final class ContactPanda_Pro_Helpdesk {
	const NOTICE_KEY = 'contactpanda_pro_helpdesk_notice_';
	const ACCOUNT_NOTICE_KEY = 'contactpanda_pro_helpdesk_account_notice_';

	public static function boot() {
		add_action( 'init', array( __CLASS__, 'register_account_endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu_items' ) );
		add_action( 'woocommerce_account_hilfe-support_endpoint', array( __CLASS__, 'render_account' ) );
		add_filter( 'contactpanda_customer_portal_tabs', array( __CLASS__, 'customer_portal_tabs' ), 20, 2 );
		add_action( 'admin_post_contactpanda_pro_create_ticket', array( __CLASS__, 'create_ticket' ) );
		add_action( 'admin_post_contactpanda_pro_add_ticket_message', array( __CLASS__, 'add_message' ) );
		add_action( 'admin_post_contactpanda_pro_update_ticket', array( __CLASS__, 'update_ticket' ) );
		add_action( 'admin_post_contactpanda_pro_save_support_source', array( __CLASS__, 'save_source' ) );
		add_action( 'admin_post_contactpanda_pro_customer_create_ticket', array( __CLASS__, 'customer_create_ticket' ) );
		add_action( 'admin_post_contactpanda_pro_customer_reply', array( __CLASS__, 'customer_reply' ) );
		add_action( 'wp_ajax_contactpanda_pro_support_customer_context', array( __CLASS__, 'load_customer_context' ) );
		self::ensure_local_source();
	}

	public static function register_account_endpoint() {
		add_rewrite_endpoint( 'hilfe-support', EP_ROOT | EP_PAGES );
	}

	public static function account_menu_items( $items ) {
		$items['hilfe-support'] = __( 'Help & Support', 'contactpanda-pro' );
		return $items;
	}

	/** Add Support Desk to the provider-neutral ContactPanda customer portal. */
	public static function customer_portal_tabs( $tabs, $identity = array() ) {
		unset( $identity );
		$tabs['support'] = array(
			'label'    => __( 'Help & Support', 'contactpanda-pro' ),
			'callback' => array( __CLASS__, 'render_account' ),
			'priority' => 40,
		);
		return $tabs;
	}

	public static function render() {
		$ticket_id = absint( $_GET['ticket'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		$is_new    = ! empty( $_GET['new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		self::authorize( 'view', 'support_ticket', $ticket_id );
		self::render_header();
		if ( $ticket_id ) self::render_ticket( $ticket_id );
		elseif ( $is_new ) self::render_new_ticket_page();
		else self::render_inbox();
		echo '</div>';
	}

	private static function render_header() {
		echo '<div class="wrap contactpanda-wrap contactpanda-pro-wrap contactpanda-helpdesk"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA PRO</p><h1>' . esc_html__( 'Support Desk', 'contactpanda-pro' ) . '</h1><p>' . esc_html__( 'Manage requests from this website and connected external shops in one source-aware inbox.', 'contactpanda-pro' ) . '</p></div></header>';
		$notice = get_transient( self::NOTICE_KEY . get_current_user_id() );
		if ( is_array( $notice ) ) {
			delete_transient( self::NOTICE_KEY . get_current_user_id() );
			echo '<div class="notice notice-' . ( ! empty( $notice['success'] ) ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ?? '' ) . '</p></div>';
		}
	}

	private static function render_inbox() {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		$status = sanitize_key( wp_unslash( $_GET['ticket_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$where = array( '1=1' );
		$args = array();
		if ( isset( self::statuses()[ $status ] ) ) { $where[] = 't.status=%s'; $args[] = $status; }
		if ( '' !== $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(t.subject LIKE %s OR t.requester_name LIKE %s OR t.requester_email LIKE %s OR t.external_order_id LIKE %s)'; array_push( $args, $like, $like, $like, $like ); }
		$sql = "SELECT t.*,s.name AS source_name FROM `{$tables['support_tickets']}` t LEFT JOIN `{$tables['support_sources']}` s ON s.id=t.source_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY t.updated_at DESC LIMIT 100';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- internal table names and prepared values.

		echo '<div class="contactpanda-helpdesk-toolbar"><form method="get"><input type="hidden" name="page" value="contactpanda-business-support"><label class="screen-reader-text" for="contactpanda-helpdesk-search">' . esc_html__( 'Search support requests', 'contactpanda-pro' ) . '</label><input id="contactpanda-helpdesk-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search subject, requester, email, or order', 'contactpanda-pro' ) . '"><select name="ticket_status"><option value="">' . esc_html__( 'All statuses', 'contactpanda-pro' ) . '</option>';
		foreach ( self::statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		$new_url = add_query_arg( array( 'page' => 'contactpanda-business-support', 'new' => 1 ), admin_url( 'admin.php' ) );
		echo '</select><button class="button">' . esc_html__( 'Filter', 'contactpanda-pro' ) . '</button></form><a class="button button-primary" href="' . esc_url( $new_url ) . '">' . esc_html__( 'New request', 'contactpanda-pro' ) . '</a></div>';

		// The inbox must never depend on the considerably heavier creation form.
		// Render the table first and load the form only when the modal is opened.
		echo '<section class="contactpanda-card contactpanda-helpdesk-inbox"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Request', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Requester', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Source', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Priority', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Assigned to', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Updated', 'contactpanda-pro' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="7" class="contactpanda-empty">' . esc_html__( 'No support requests match this view.', 'contactpanda-pro' ) . '</td></tr>';
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-business-support', 'ticket' => absint( $row['id'] ) ), admin_url( 'admin.php' ) );
			$assignee = $row['assigned_user_id'] ? get_userdata( absint( $row['assigned_user_id'] ) ) : false;
			echo '<tr><td><a href="' . esc_url( $url ) . '"><strong>#' . esc_html( $row['id'] ) . ' ' . esc_html( $row['subject'] ) . '</strong></a><br><small>' . esc_html( self::ticket_relation_label( $row ) ) . '</small></td><td>' . esc_html( trim( $row['requester_name'] ) ?: $row['requester_email'] ) . '<br><small>' . esc_html( $row['requester_email'] ) . '</small></td><td>' . esc_html( $row['source_name'] ?: __( 'Local website', 'contactpanda-pro' ) ) . '</td><td>' . self::status_badge( $row['status'] ) . '</td><td>' . esc_html( self::priorities()[ $row['priority'] ] ?? $row['priority'] ) . '</td><td>' . esc_html( $assignee ? $assignee->display_name : __( 'Unassigned', 'contactpanda-pro' ) ) . '</td><td>' . esc_html( self::display_date( $row['updated_at'] ) ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
		if ( ContactPanda_Pro_Access::can( 'manage', 'support_source' ) ) self::render_sources();
	}

	private static function render_new_ticket_page() {
		self::authorize( 'create', 'support_ticket' );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-support' ) ) . '">← ' . esc_html__( 'Back to support requests', 'contactpanda-pro' ) . '</a></p>';
		echo '<section class="contactpanda-card contactpanda-helpdesk-create-page"><div class="contactpanda-pro-section-heading"><div><h2>' . esc_html__( 'Create support request', 'contactpanda-pro' ) . '</h2><p>' . esc_html__( 'Select an existing person or create a new one. Matching customer records become available automatically.', 'contactpanda-pro' ) . '</p></div></div>';
		self::render_new_ticket_form();
		echo '</section>';
	}

	/** Load only records that belong to the selected person. */
	public static function load_customer_context() {
		check_ajax_referer( 'contactpanda_pro_support_customer_context', 'nonce' );
		self::authorize( 'create', 'support_ticket' );
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		$contact = self::contact_option( $contact_id );
		if ( ! $contact ) wp_send_json_error( array( 'message' => __( 'The selected person could not be found.', 'contactpanda-pro' ) ), 404 );
		$relations = self::customer_relations( $contact['email'], self::user_id_for_email( $contact['email'] ), $contact_id );
		wp_send_json_success(
			array(
				'relations' => $relations,
				'counts'    => self::relation_counts( $relations ),
				'type_labels' => self::relation_type_labels(),
				'summary'   => self::customer_summary( $contact['email'], $relations ),
			)
		);
	}

	private static function render_new_ticket_form() {
		$prefill_subject = sanitize_text_field( wp_unslash( $_GET['subject'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- optional display prefill.
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-helpdesk-form" data-contactpanda-helpdesk-create data-context-url="' . esc_url( admin_url( 'admin-ajax.php' ) ) . '" data-context-nonce="' . esc_attr( wp_create_nonce( 'contactpanda_pro_support_customer_context' ) ) . '" data-name-required="' . esc_attr__( 'Please enter a name.', 'contactpanda-pro' ) . '" data-context-loading="' . esc_attr__( 'Loading customer records…', 'contactpanda-pro' ) . '" data-context-error="' . esc_attr__( 'The customer records could not be loaded.', 'contactpanda-pro' ) . '"><input type="hidden" name="action" value="contactpanda_pro_create_ticket">';
		wp_nonce_field( 'contactpanda_pro_create_ticket' );
		echo '<div class="contactpanda-helpdesk-form-grid"><section class="contactpanda-helpdesk-form-column is-primary"><h3>' . esc_html__( 'Request details', 'contactpanda-pro' ) . '</h3>';
		self::source_select( 0 );
		echo '<label><span>' . esc_html__( 'Request type', 'contactpanda-pro' ) . '</span><select name="request_type">';
		foreach ( self::request_types() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		echo '</select></label><label class="contactpanda-helpdesk-person"><span>' . esc_html__( 'Person', 'contactpanda-pro' ) . '</span><select name="contact_id" class="contactpanda-pro-search-select" data-contactpanda-helpdesk-contact><option value="0">' . esc_html__( 'Select a person or type to search', 'contactpanda-pro' ) . '</option>';
		foreach ( self::contact_options() as $contact ) echo '<option value="' . esc_attr( $contact['id'] ) . '" data-name="' . esc_attr( $contact['name'] ) . '" data-email="' . esc_attr( $contact['email'] ) . '">' . esc_html( $contact['label'] ) . '</option>';
		echo '</select><button type="button" class="button" data-contactpanda-helpdesk-toggle="new-person">' . esc_html__( 'Create a new person', 'contactpanda-pro' ) . '</button><small data-contactpanda-helpdesk-person-summary data-default-label="' . esc_attr__( 'No person selected yet.', 'contactpanda-pro' ) . '">' . esc_html__( 'No person selected yet.', 'contactpanda-pro' ) . '</small></label>';
		echo '<fieldset id="contactpanda-new-person" class="contactpanda-helpdesk-new-person" hidden><legend>' . esc_html__( 'New person', 'contactpanda-pro' ) . '</legend><p>' . esc_html__( 'The person is linked immediately and no marketing consent is granted.', 'contactpanda-pro' ) . '</p><label><span>' . esc_html__( 'First name', 'contactpanda-pro' ) . '</span><input name="new_first_name"></label><label><span>' . esc_html__( 'Last name', 'contactpanda-pro' ) . '</span><input name="new_last_name"></label><label><span>' . esc_html__( 'Email address', 'contactpanda-pro' ) . '</span><input type="email" name="new_email"></label></fieldset>';
		echo '<label><span>' . esc_html__( 'Priority', 'contactpanda-pro' ) . '</span><select name="priority">';
		foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>';
		echo '</select></label></section><section class="contactpanda-helpdesk-form-column is-context"><h3>' . esc_html__( 'Customer context', 'contactpanda-pro' ) . '</h3>';
		$context_prompt = __( 'Select a person to load their orders, purchased products, licenses, events, and projects.', 'contactpanda-pro' );
		echo '<div class="contactpanda-helpdesk-context" data-contactpanda-helpdesk-context><div><p data-contactpanda-helpdesk-context-summary data-default-label="' . esc_attr( $context_prompt ) . '">' . esc_html( $context_prompt ) . '</p><div class="contactpanda-helpdesk-counts" data-contactpanda-helpdesk-counts></div></div><label><span>' . esc_html__( 'Record type', 'contactpanda-pro' ) . '</span><select data-contactpanda-helpdesk-related-type data-all-label="' . esc_attr__( 'All related records', 'contactpanda-pro' ) . '" disabled><option value="">' . esc_html__( 'All related records', 'contactpanda-pro' ) . '</option></select></label><label><span>' . esc_html__( 'Related record', 'contactpanda-pro' ) . '</span><span class="contactpanda-helpdesk-related-control"><select name="related_record" data-contactpanda-helpdesk-related disabled><option value="">' . esc_html__( 'General request without a related record', 'contactpanda-pro' ) . '</option></select><button type="button" class="contactpanda-pro-icon-button" data-contactpanda-helpdesk-preview disabled aria-label="' . esc_attr__( 'Show details', 'contactpanda-pro' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></button></span></label><div class="contactpanda-helpdesk-relation-preview" data-contactpanda-helpdesk-relation-preview hidden></div></div></section></div>';
		echo '<div class="contactpanda-helpdesk-points" data-contactpanda-support-points><h3>' . esc_html__( 'Request points', 'contactpanda-pro' ) . '</h3><p>' . esc_html__( 'Each point becomes a separate support request and can be processed independently.', 'contactpanda-pro' ) . '</p><article data-contactpanda-support-point><label><span>' . esc_html__( 'Point title', 'contactpanda-pro' ) . '</span><input name="point_subject[]" value="' . esc_attr( $prefill_subject ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-pro' ) . '</span><textarea name="point_message[]" rows="6" required></textarea></label></article><button type="button" class="button" data-contactpanda-add-support-point><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add another point', 'contactpanda-pro' ) . '</button></div><div class="contactpanda-helpdesk-form-bottom"><label class="contactpanda-helpdesk-message"><span>' . esc_html__( 'Shared attachments', 'contactpanda-pro' ) . '</span><input type="file" name="attachments[]" multiple><small>' . esc_html__( 'These files are attached to every point in this request.', 'contactpanda-pro' ) . '</small></label></div><footer><a class="button" href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-support' ) ) . '">' . esc_html__( 'Cancel', 'contactpanda-pro' ) . '</a><button type="submit" class="button button-primary">' . esc_html__( 'Create requests', 'contactpanda-pro' ) . '</button></footer></form>';
	}

	private static function render_ticket( $ticket_id ) {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT t.*,s.name AS source_name FROM `{$tables['support_tickets']}` t LEFT JOIN `{$tables['support_sources']}` s ON s.id=t.source_id WHERE t.id=%d", $ticket_id ), ARRAY_A );
		if ( ! $ticket ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'This support request no longer exists.', 'contactpanda-pro' ) . '</p></div>'; return; }
		$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tables['support_messages']}` WHERE ticket_id=%d ORDER BY created_at ASC,id ASC", $ticket_id ), ARRAY_A );
		$reply_drafts = (array) get_option( ContactPanda_Pro_MCP::DRAFT_OPTION, array() );
		$reply_draft = isset( $reply_drafts[ $ticket_id ] ) && is_array( $reply_drafts[ $ticket_id ] ) ? $reply_drafts[ $ticket_id ] : array();
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-support' ) ) . '">← ' . esc_html__( 'Back to the Support Desk overview', 'contactpanda-pro' ) . '</a></p><div class="contactpanda-helpdesk-detail"><main><section class="contactpanda-card"><div class="contactpanda-toolbar"><h2>#' . esc_html( $ticket['id'] ) . ' ' . esc_html( $ticket['subject'] ) . '</h2>' . self::status_badge( $ticket['status'] ) . '</div><p class="contactpanda-muted">' . esc_html( sprintf( __( 'From %1$s <%2$s> via %3$s', 'contactpanda-pro' ), $ticket['requester_name'], $ticket['requester_email'], $ticket['source_name'] ?: __( 'Local website', 'contactpanda-pro' ) ) ) . '</p>' . self::related_record_card( $ticket ) . '<div class="contactpanda-helpdesk-thread">';
		foreach ( $messages as $message ) {
			$internal = 'internal' === $message['visibility'];
			echo '<article class="contactpanda-helpdesk-message-card ' . ( $internal ? 'is-internal' : ( 'outbound' === $message['direction'] ? 'is-outbound' : 'is-inbound' ) ) . '"><header><strong>' . esc_html( $internal ? __( 'Internal note', 'contactpanda-pro' ) : ( 'outbound' === $message['direction'] ? __( 'Support reply', 'contactpanda-pro' ) : __( 'Customer message', 'contactpanda-pro' ) ) ) . '</strong><time>' . esc_html( self::display_date( $message['created_at'] ) ) . '</time></header><div>' . wpautop( esc_html( $message['body'] ) ) . '</div>' . self::attachment_links( $message['attachments_json'] ?? '' ) . '</article>';
		}
		echo '</div></section><section class="contactpanda-card"><h2>' . esc_html__( 'Reply or add an internal note', 'contactpanda-pro' ) . '</h2>';
		if ( $reply_draft ) echo '<div class="notice notice-info inline"><p>' . esc_html__( 'An MCP reply draft is ready. Please review it before sending.', 'contactpanda-pro' ) . '</p></div>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_pro_add_ticket_message"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '">'; wp_nonce_field( 'contactpanda_pro_add_ticket_message_' . $ticket_id ); echo '<label class="contactpanda-helpdesk-reply-type"><input type="radio" name="visibility" value="customer"' . checked( empty( $reply_draft['internal'] ), true, false ) . '> ' . esc_html__( 'Reply to customer', 'contactpanda-pro' ) . '</label> <label class="contactpanda-helpdesk-reply-type"><input type="radio" name="visibility" value="internal"' . checked( ! empty( $reply_draft['internal'] ), true, false ) . '> ' . esc_html__( 'Internal note', 'contactpanda-pro' ) . '</label><textarea class="widefat" rows="7" name="body" required>' . esc_textarea( $reply_draft['body'] ?? '' ) . '</textarea><label><span>' . esc_html__( 'Attachments', 'contactpanda-pro' ) . '</span><input type="file" name="attachments[]" multiple></label><p><button class="button button-primary">' . esc_html__( 'Send or add to conversation', 'contactpanda-pro' ) . '</button></p><p class="description">' . esc_html__( 'Customer replies are sent through WordPress email delivery and are recorded only after successful handoff. Internal notes remain visible only to support staff.', 'contactpanda-pro' ) . '</p></form></section></main><aside><section class="contactpanda-card"><h2>' . esc_html__( 'Request details', 'contactpanda-pro' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-helpdesk-sidebar-form"><input type="hidden" name="action" value="contactpanda_pro_update_ticket"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '">';
		wp_nonce_field( 'contactpanda_pro_update_ticket_' . $ticket_id );
		echo '<label><span>' . esc_html__( 'Status', 'contactpanda-pro' ) . '</span><select name="status">'; foreach ( self::statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $ticket['status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Priority', 'contactpanda-pro' ) . '</span><select name="priority">'; foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $ticket['priority'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Assigned to', 'contactpanda-pro' ) . '</span><select name="assigned_user_id"><option value="0">' . esc_html__( 'Unassigned', 'contactpanda-pro' ) . '</option>'; foreach ( get_users( array( 'role__in' => array( 'administrator', 'editor', 'shop_manager' ), 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $ticket['assigned_user_id'], $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>'; echo '</select></label><button class="button button-primary">' . esc_html__( 'Save details', 'contactpanda-pro' ) . '</button></form><dl class="contactpanda-helpdesk-meta"><dt>' . esc_html__( 'Request type', 'contactpanda-pro' ) . '</dt><dd>' . esc_html( self::request_types()[ $ticket['request_type'] ] ?? $ticket['request_type'] ) . '</dd><dt>' . esc_html__( 'Person', 'contactpanda-pro' ) . '</dt><dd>' . esc_html( $ticket['requester_name'] ?: $ticket['requester_email'] ) . '</dd><dt>' . esc_html__( 'Related record', 'contactpanda-pro' ) . '</dt><dd>' . esc_html( self::ticket_relation_label( $ticket ) ) . '</dd></dl></section>';
		do_action( 'contactpanda_pro_ticket_sidebar', $ticket );
		echo '</aside></div>';
	}

	private static function render_sources() {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		$sources = $wpdb->get_results( "SELECT id,name,source_type,base_url,status FROM `{$tables['support_sources']}` ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		echo '<details class="contactpanda-card contactpanda-helpdesk-sources"><summary><strong>' . esc_html__( 'Sources and external shops', 'contactpanda-pro' ) . '</strong></summary><p>' . esc_html__( 'Use sources to distinguish this website from external shops, forms, or other systems. No shop data is changed.', 'contactpanda-pro' ) . '</p><ul>';
		foreach ( $sources as $source ) echo '<li><strong>' . esc_html( $source['name'] ) . '</strong> — ' . esc_html( $source['source_type'] ) . ( $source['base_url'] ? ' · ' . esc_html( $source['base_url'] ) : '' ) . '</li>';
		echo '</ul><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-helpdesk-source-form"><input type="hidden" name="action" value="contactpanda_pro_save_support_source">'; wp_nonce_field( 'contactpanda_pro_save_support_source' ); echo '<label><span>' . esc_html__( 'Source name', 'contactpanda-pro' ) . '</span><input name="name" required placeholder="' . esc_attr__( 'External shop', 'contactpanda-pro' ) . '"></label><label><span>' . esc_html__( 'Source type', 'contactpanda-pro' ) . '</span><select name="source_type"><option value="external_shop">' . esc_html__( 'External shop', 'contactpanda-pro' ) . '</option><option value="website">' . esc_html__( 'Website or form', 'contactpanda-pro' ) . '</option><option value="email">' . esc_html__( 'Email inbox', 'contactpanda-pro' ) . '</option><option value="other">' . esc_html__( 'Other system', 'contactpanda-pro' ) . '</option></select></label><label><span>' . esc_html__( 'Website URL (optional)', 'contactpanda-pro' ) . '</span><input type="url" name="base_url"></label><p><button class="button">' . esc_html__( 'Add source', 'contactpanda-pro' ) . '</button></p></form></details>';
	}

	private static function source_select( $selected ) {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		$sources = $wpdb->get_results( "SELECT id,name FROM `{$tables['support_sources']}` WHERE status='active' ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		echo '<label><span>' . esc_html__( 'Source', 'contactpanda-pro' ) . '</span><select name="source_id">';
		foreach ( $sources as $source ) echo '<option value="' . esc_attr( $source['id'] ) . '"' . selected( $selected, $source['id'], false ) . '>' . esc_html( $source['name'] ) . '</option>';
		echo '</select></label>';
	}

	public static function create_ticket() {
		self::authorize( 'create', 'support_ticket' ); check_admin_referer( 'contactpanda_pro_create_ticket' );
		$schema = self::ensure_support_schema(); if ( is_wp_error( $schema ) ) self::redirect_notice( false, $schema->get_error_message() ); self::ensure_local_source();
		$contact_id = absint( $_POST['contact_id'] ?? 0 );
		$new_email = sanitize_email( wp_unslash( $_POST['new_email'] ?? '' ) );
		if ( ! $contact_id && $new_email && method_exists( 'ContactPanda_Plugin', 'extension_create_contact' ) ) {
			$created = ContactPanda_Plugin::extension_create_contact( array( 'first_name' => wp_unslash( $_POST['new_first_name'] ?? '' ), 'last_name' => wp_unslash( $_POST['new_last_name'] ?? '' ), 'email' => wp_unslash( $_POST['new_email'] ?? '' ) ) );
			if ( is_wp_error( $created ) ) self::redirect_notice( false, $created->get_error_message() );
			$contact_id = absint( $created );
		}
		$requester = self::resolve_requester( $contact_id, array( 'first_name' => $_POST['new_first_name'] ?? '', 'last_name' => $_POST['new_last_name'] ?? '', 'email' => $new_email ) );
		if ( is_wp_error( $requester ) ) self::redirect_notice( false, $requester->get_error_message() );
		$email = $requester['email'];
		$name = $requester['name'];
		$subjects = array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['point_subject'] ?? array() ) ); $bodies = array_map( 'sanitize_textarea_field', (array) wp_unslash( $_POST['point_message'] ?? array() ) );
		$points = array(); foreach ( $subjects as $index => $subject ) { $body = $bodies[ $index ] ?? ''; if ( '' !== $subject && '' !== $body ) $points[] = array( $subject, $body ); }
		if ( ! $email || ! $points ) self::redirect_notice( false, __( 'Please provide a title and description for at least one point.', 'contactpanda-pro' ) );
		$batch_uuid = function_exists( 'wp_generate_uuid4' ) ? wp_generate_uuid4() : uniqid( 'ticket-batch-', true );
		$requester_user_id = self::user_id_for_email( $email );
		$relation = self::resolve_relation( $_POST['related_record'] ?? '', self::customer_relations( $email, $requester_user_id, $contact_id ) );
		if ( is_wp_error( $relation ) ) self::redirect_notice( false, $relation->get_error_message() );
		$legacy = self::relation_legacy_fields( $relation );
		$source_id = absint( $_POST['source_id'] ?? 0 ) ?: self::local_source_id();
		$shared_attachments = self::uploaded_attachments(); unset( $_FILES['attachments'] );
		$first_ticket_id = 0; foreach ( $points as $point_index => $point ) { list( $subject, $body ) = $point; $uuid = $batch_uuid . '-' . ( $point_index + 1 );
		$ticket_id = self::persist_ticket(
			array(
				'uuid'               => $uuid,
				'source_id'          => $source_id,
				'contact_id'         => $contact_id,
				'company_id'         => 0,
				'requester_user_id'  => $requester_user_id,
				'external_order_id'  => $legacy['external_order_id'],
				'request_type'       => self::clean_request_type( $_POST['request_type'] ?? 'general' ),
				'related_type'       => $relation['type'],
				'related_id'         => $relation['id'],
				'related_label'      => $relation['label'],
				'license_reference'  => $legacy['license_reference'],
				'products_json'      => wp_json_encode( $legacy['products'] ),
				'orders_json'        => wp_json_encode( $legacy['orders'] ),
				'requester_name'     => $name,
				'requester_email'    => $email,
				'subject'            => $subject,
				'status'             => 'new',
				'priority'           => self::clean_priority( $_POST['priority'] ?? 'normal' ),
			),
			array(
				'author_user_id'   => get_current_user_id(),
				'author_contact_id' => $contact_id,
				'body'             => $body,
				'attachments_json' => wp_json_encode( $shared_attachments ),
				'external_source'  => 'manual',
				'external_id'      => 'manual-' . $uuid,
			)
		);
		if ( is_wp_error( $ticket_id ) ) self::redirect_notice( false, $ticket_id->get_error_message() );
		if ( ! $first_ticket_id ) $first_ticket_id = $ticket_id;
		ContactPanda_Pro_Access::audit( 'created', 'support_ticket', $ticket_id, array(), array( 'status' => 'new', 'priority' => self::clean_priority( $_POST['priority'] ?? 'normal' ), 'contact_id' => $contact_id ), array( 'channel' => 'admin' ) );
		self::notify_support_team( $ticket_id, $subject, $name ?: $email, false );
		}
		self::redirect_notice( true, sprintf( _n( 'The support request was created.', '%d separate support requests were created.', count( $points ), 'contactpanda-pro' ), count( $points ) ), $first_ticket_id );
	}

	public static function add_message() {
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 ); self::authorize( 'edit', 'support_ticket', $ticket_id ); check_admin_referer( 'contactpanda_pro_add_ticket_message_' . $ticket_id );
		$body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); if ( ! $ticket_id || '' === $body ) self::redirect_notice( false, __( 'Please enter a message.', 'contactpanda-pro' ), $ticket_id );
		$visibility = 'internal' === sanitize_key( wp_unslash( $_POST['visibility'] ?? '' ) ) ? 'internal' : 'customer';
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $now = current_time( 'mysql', true );
		if ( 'customer' === $visibility ) {
			$ticket = $wpdb->get_row( $wpdb->prepare( "SELECT subject,requester_email FROM `{$tables['support_tickets']}` WHERE id=%d", $ticket_id ), ARRAY_A );
			$sent = $ticket && is_email( $ticket['requester_email'] ) ? ( class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $ticket['requester_email'], sprintf( __( 'Re: %s', 'contactpanda-pro' ), $ticket['subject'] ), wpautop( wp_kses_post( $body ) ) ) : wp_mail( $ticket['requester_email'], sprintf( __( 'Re: %s', 'contactpanda-pro' ), $ticket['subject'] ), $body ) ) : false;
			if ( ! $sent ) {
				self::redirect_notice( false, __( 'The reply could not be sent. Nothing was added to the customer conversation.', 'contactpanda-pro' ), $ticket_id );
			}
		}
		$wpdb->insert( $tables['support_messages'], array( 'ticket_id' => $ticket_id, 'visibility' => $visibility, 'direction' => 'internal' === $visibility ? 'internal' : 'outbound', 'author_user_id' => get_current_user_id(), 'body' => $body, 'attachments_json' => wp_json_encode( self::uploaded_attachments() ), 'external_source' => 'internal' === $visibility ? 'contactpanda' : 'wp_mail', 'external_id' => wp_generate_uuid4(), 'created_at' => $now ) );
		$wpdb->update( $tables['support_tickets'], array( 'status' => 'internal' === $visibility ? sanitize_key( self::ticket_value( $ticket_id, 'status' ) ) : 'answered', 'updated_at' => $now ), array( 'id' => $ticket_id ) );
		$reply_drafts = (array) get_option( ContactPanda_Pro_MCP::DRAFT_OPTION, array() );
		if ( isset( $reply_drafts[ $ticket_id ] ) ) { unset( $reply_drafts[ $ticket_id ] ); update_option( ContactPanda_Pro_MCP::DRAFT_OPTION, $reply_drafts, false ); }
		ContactPanda_Pro_Access::audit( 'message_added', 'support_ticket', $ticket_id, array(), array( 'visibility' => $visibility, 'status' => 'internal' === $visibility ? self::ticket_value( $ticket_id, 'status' ) : 'answered' ), array( 'channel' => 'admin' ) );
		self::redirect_notice( true, 'internal' === $visibility ? __( 'The internal note was added.', 'contactpanda-pro' ) : __( 'The reply was added to the conversation.', 'contactpanda-pro' ), $ticket_id );
	}

	public static function update_ticket() {
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 ); self::authorize( 'edit', 'support_ticket', $ticket_id ); check_admin_referer( 'contactpanda_pro_update_ticket_' . $ticket_id );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? '' ) ); if ( ! isset( self::statuses()[ $status ] ) ) $status = 'open';
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names();
		$before = $wpdb->get_row( $wpdb->prepare( "SELECT status,priority,assigned_user_id FROM `{$tables['support_tickets']}` WHERE id=%d", $ticket_id ), ARRAY_A );
		$assigned_user_id = absint( $_POST['assigned_user_id'] ?? 0 );
		if ( $before && absint( $before['assigned_user_id'] ) !== $assigned_user_id ) self::authorize( 'assign', 'support_ticket', $ticket_id );
		$after = array( 'status' => $status, 'priority' => self::clean_priority( $_POST['priority'] ?? 'normal' ), 'assigned_user_id' => $assigned_user_id );
		$wpdb->update( $tables['support_tickets'], array_merge( $after, array( 'updated_at' => current_time( 'mysql', true ), 'closed_at' => 'closed' === $status ? current_time( 'mysql', true ) : null ) ), array( 'id' => $ticket_id ) );
		ContactPanda_Pro_Access::audit( 'updated', 'support_ticket', $ticket_id, $before ?: array(), $after, array( 'channel' => 'admin' ) );
		self::redirect_notice( true, __( 'The request details were saved.', 'contactpanda-pro' ), $ticket_id );
	}

	public static function save_source() {
		self::authorize( 'manage', 'support_source' ); check_admin_referer( 'contactpanda_pro_save_support_source' );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); if ( '' === $name ) self::redirect_notice( false, __( 'Please enter a source name.', 'contactpanda-pro' ) );
		$type = sanitize_key( wp_unslash( $_POST['source_type'] ?? 'other' ) ); if ( ! in_array( $type, array( 'external_shop', 'website', 'email', 'other' ), true ) ) $type = 'other';
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $now = current_time( 'mysql', true );
		$wpdb->insert( $tables['support_sources'], array( 'name' => $name, 'source_type' => $type, 'base_url' => esc_url_raw( wp_unslash( $_POST['base_url'] ?? '' ) ), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
		ContactPanda_Pro_Access::audit( 'created', 'support_source', absint( $wpdb->insert_id ), array(), array( 'name' => $name, 'source_type' => $type, 'status' => 'active' ), array( 'channel' => 'admin' ) );
		self::redirect_notice( true, __( 'The support source was added.', 'contactpanda-pro' ) );
	}

	public static function render_account() {
		if ( ! is_user_logged_in() ) return;
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		$user = wp_get_current_user();
		$view = sanitize_key( wp_unslash( $_GET['support_view'] ?? 'requests' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only customer routing.
		if ( ! in_array( $view, array( 'new', 'requests' ), true ) ) $view = 'requests';
		$notice = get_transient( self::ACCOUNT_NOTICE_KEY . $user->ID );
		if ( is_array( $notice ) ) {
			delete_transient( self::ACCOUNT_NOTICE_KEY . $user->ID );
			echo '<div class="woocommerce-' . ( ! empty( $notice['success'] ) ? 'message' : 'error' ) . '" role="alert">' . esc_html( $notice['message'] ?? '' ) . '</div>';
		}
		$ticket_id = absint( $_GET['support_ticket'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only customer routing.
		echo '<div class="contactpanda-support-account"><header class="contactpanda-support-account-heading"><h2>' . esc_html__( 'Help & Support', 'contactpanda-pro' ) . '</h2><p>' . esc_html__( 'Create a request and keep the complete conversation in your customer account.', 'contactpanda-pro' ) . '</p></header>';
		self::render_account_tabs( $ticket_id ? 'requests' : $view );
		if ( $ticket_id ) {
			$ticket = self::customer_ticket( $ticket_id, $user );
			if ( ! $ticket ) { echo '<p>' . esc_html__( 'This support request is not available.', 'contactpanda-pro' ) . '</p></div>'; return; }
			$messages = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tables['support_messages']}` WHERE ticket_id=%d AND visibility='customer' ORDER BY created_at ASC,id ASC", $ticket_id ), ARRAY_A );
			echo '<section class="contactpanda-support-ticket"><p><a href="' . esc_url( self::account_url( 0, 'requests' ) ) . '">← ' . esc_html__( 'Back to my support requests', 'contactpanda-pro' ) . '</a></p><div class="contactpanda-support-ticket-heading"><h3>#' . esc_html( $ticket['id'] ) . ' ' . esc_html( $ticket['subject'] ) . '</h3>' . self::status_badge( $ticket['status'] ) . '</div><p class="contactpanda-support-relation"><strong>' . esc_html__( 'Related record', 'contactpanda-pro' ) . ':</strong> ' . esc_html( self::ticket_relation_label( $ticket ) ) . '</p><div class="contactpanda-support-thread">';
			foreach ( $messages as $message ) echo '<article class="' . ( 'outbound' === $message['direction'] ? 'is-support' : 'is-customer' ) . '"><strong>' . esc_html( 'outbound' === $message['direction'] ? __( 'Support', 'contactpanda-pro' ) : __( 'You', 'contactpanda-pro' ) ) . '</strong><time>' . esc_html( self::display_date( $message['created_at'] ) ) . '</time><div>' . wpautop( esc_html( $message['body'] ) ) . '</div>' . self::attachment_links( $message['attachments_json'] ?? '' ) . '</article>';
			echo '</div><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="contactpanda-support-reply"><input type="hidden" name="action" value="contactpanda_pro_customer_reply"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket_id ) . '">';
			wp_nonce_field( 'contactpanda_pro_customer_reply_' . $ticket_id );
			echo '<label><span>' . esc_html__( 'Your reply', 'contactpanda-pro' ) . '</span><textarea name="body" rows="6" required></textarea></label><label><span>' . esc_html__( 'Attachments', 'contactpanda-pro' ) . '</span><input type="file" name="attachments[]" multiple></label><button class="button">' . esc_html__( 'Send reply', 'contactpanda-pro' ) . '</button></form></section></div>';
			return;
		}
		if ( 'new' === $view ) {
			self::render_account_form( $user );
			echo '</div>';
			return;
		}
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$tables['support_tickets']}` WHERE requester_user_id=%d OR (requester_user_id=0 AND requester_email=%s) ORDER BY updated_at DESC", $user->ID, $user->user_email ), ARRAY_A );
		if ( $rows ) {
			echo '<table class="shop_table shop_table_responsive contactpanda-support-request-list"><thead><tr><th>' . esc_html__( 'Request', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-pro' ) . '</th><th>' . esc_html__( 'Updated', 'contactpanda-pro' ) . '</th></tr></thead><tbody>';
			foreach ( $rows as $row ) {
				$url = self::account_url( absint( $row['id'] ), 'requests' );
				echo '<tr><td data-title="' . esc_attr__( 'Request', 'contactpanda-pro' ) . '"><a href="' . esc_url( $url ) . '">#' . esc_html( $row['id'] ) . ' ' . esc_html( $row['subject'] ) . '</a></td><td data-title="' . esc_attr__( 'Status', 'contactpanda-pro' ) . '">' . self::status_badge( $row['status'] ) . '</td><td data-title="' . esc_attr__( 'Updated', 'contactpanda-pro' ) . '">' . esc_html( self::display_date( $row['updated_at'] ) ) . '</td></tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<div class="contactpanda-support-empty"><p>' . esc_html__( 'You have not submitted any support requests yet.', 'contactpanda-pro' ) . '</p><a class="button" href="' . esc_url( self::account_url( 0, 'new' ) ) . '">' . esc_html__( 'Create a support request', 'contactpanda-pro' ) . '</a></div>';
		}
		echo '</div>';
	}

	private static function render_account_tabs( $active ) {
		echo '<nav class="contactpanda-support-tabs" aria-label="' . esc_attr__( 'Support navigation', 'contactpanda-pro' ) . '"><a href="' . esc_url( self::account_url( 0, 'new' ) ) . '" class="' . ( 'new' === $active ? 'is-active' : '' ) . '"' . ( 'new' === $active ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'New support request', 'contactpanda-pro' ) . '</a><a href="' . esc_url( self::account_url( 0, 'requests' ) ) . '" class="' . ( 'requests' === $active ? 'is-active' : '' ) . '"' . ( 'requests' === $active ? ' aria-current="page"' : '' ) . '>' . esc_html__( 'My support requests', 'contactpanda-pro' ) . '</a></nav>';
	}

	private static function render_account_form( $user ) {
		echo '<section class="contactpanda-support-new"><h3>' . esc_html__( 'New support request', 'contactpanda-pro' ) . '</h3><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_pro_customer_create_ticket">';
		wp_nonce_field( 'contactpanda_pro_customer_create_ticket' );
		$contact_id = self::contact_id_for_email( $user->user_email );
		$relations = self::customer_relations( $user->user_email, $user->ID, $contact_id );
		echo '<label><span>' . esc_html__( 'Request type', 'contactpanda-pro' ) . '</span><select name="request_type">'; foreach ( self::request_types() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Subject', 'contactpanda-pro' ) . '</span><input name="subject" required></label>';
		self::render_relation_select( $relations );
		echo '<label class="contactpanda-support-message"><span>' . esc_html__( 'Message', 'contactpanda-pro' ) . '</span><textarea name="message" rows="7" required></textarea></label><label class="contactpanda-support-message"><span>' . esc_html__( 'Attachments', 'contactpanda-pro' ) . '</span><input type="file" name="attachments[]" multiple></label><button class="button">' . esc_html__( 'Submit request', 'contactpanda-pro' ) . '</button></form></section>';
	}

	public static function customer_create_ticket() {
		if ( ! is_user_logged_in() ) auth_redirect();
		check_admin_referer( 'contactpanda_pro_customer_create_ticket' );
		$schema = self::ensure_support_schema();
		if ( is_wp_error( $schema ) ) self::account_redirect( 0, false, $schema->get_error_message() );
		self::ensure_local_source();
		$user = wp_get_current_user();
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body = sanitize_textarea_field( wp_unslash( $_POST['message'] ?? '' ) );
		if ( ! $subject || ! $body ) self::account_redirect( 0, false, __( 'Please enter a subject and message.', 'contactpanda-pro' ) );
		$contact = method_exists( 'ContactPanda_Plugin', 'extension_contact_by_email' ) ? ContactPanda_Plugin::extension_contact_by_email( $user->user_email ) : false;
		$contact_id = $contact ? absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) ) : 0;
		if ( ! $contact_id && method_exists( 'ContactPanda_Plugin', 'extension_create_contact' ) ) {
			$created = ContactPanda_Plugin::extension_create_contact( array( 'first_name' => get_user_meta( $user->ID, 'first_name', true ) ?: $user->display_name, 'last_name' => get_user_meta( $user->ID, 'last_name', true ), 'email' => $user->user_email ) );
			if ( ! is_wp_error( $created ) ) $contact_id = absint( $created );
		}
		$relation = self::resolve_relation( $_POST['related_record'] ?? '', self::customer_relations( $user->user_email, $user->ID, $contact_id ) );
		if ( is_wp_error( $relation ) ) self::account_redirect( 0, false, $relation->get_error_message() );
		$legacy = self::relation_legacy_fields( $relation );
		$uuid = wp_generate_uuid4();
		$ticket_id = self::persist_ticket(
			array(
				'uuid'               => $uuid,
				'source_id'          => self::local_source_id(),
				'contact_id'         => $contact_id,
				'requester_user_id'  => $user->ID,
				'external_order_id'  => $legacy['external_order_id'],
				'request_type'       => self::clean_request_type( $_POST['request_type'] ?? 'general' ),
				'related_type'       => $relation['type'],
				'related_id'         => $relation['id'],
				'related_label'      => $relation['label'],
				'license_reference'  => $legacy['license_reference'],
				'products_json'      => wp_json_encode( $legacy['products'] ),
				'orders_json'        => wp_json_encode( $legacy['orders'] ),
				'requester_name'     => $user->display_name,
				'requester_email'    => $user->user_email,
				'subject'            => $subject,
				'status'             => 'new',
				'priority'           => 'normal',
			),
			array(
				'author_user_id'    => $user->ID,
				'author_contact_id' => $contact_id,
				'body'              => $body,
				'external_source'   => 'customer_account',
				'external_id'       => 'account-' . $uuid,
			)
		);
		if ( is_wp_error( $ticket_id ) ) self::account_redirect( 0, false, $ticket_id->get_error_message() );
		ContactPanda_Pro_Access::audit( 'created', 'support_ticket', $ticket_id, array(), array( 'status' => 'new', 'contact_id' => $contact_id, 'requester_user_id' => $user->ID ), array( 'channel' => 'customer_portal' ) );
		self::notify_support_team( $ticket_id, $subject, $user->display_name, false );
		self::account_redirect( $ticket_id, true, __( 'Your support request was created.', 'contactpanda-pro' ) );
	}

	public static function customer_reply() {
		if ( ! is_user_logged_in() ) auth_redirect();
		$ticket_id = absint( $_POST['ticket_id'] ?? 0 );
		check_admin_referer( 'contactpanda_pro_customer_reply_' . $ticket_id );
		$user = wp_get_current_user(); $ticket = self::customer_ticket( $ticket_id, $user );
		$body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) );
		if ( ! $ticket || ! $body ) self::account_redirect();
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $now = current_time( 'mysql', true );
		$wpdb->insert( $tables['support_messages'], array( 'ticket_id' => $ticket_id, 'visibility' => 'customer', 'direction' => 'inbound', 'author_user_id' => $user->ID, 'author_contact_id' => absint( $ticket['contact_id'] ), 'body' => $body, 'attachments_json' => wp_json_encode( self::uploaded_attachments() ), 'external_source' => 'customer_account', 'external_id' => wp_generate_uuid4(), 'created_at' => $now ) );
		$wpdb->update( $tables['support_tickets'], array( 'status' => 'waiting_support', 'updated_at' => $now ), array( 'id' => $ticket_id ) );
		ContactPanda_Pro_Access::audit( 'message_added', 'support_ticket', $ticket_id, array( 'status' => $ticket['status'] ), array( 'status' => 'waiting_support', 'visibility' => 'customer' ), array( 'channel' => 'customer_portal' ) );
		self::notify_support_team( $ticket_id, $ticket['subject'], $user->display_name, true );
		self::account_redirect( $ticket_id );
	}

	private static function customer_ticket( $ticket_id, $user ) {
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$tables['support_tickets']}` WHERE id=%d AND (requester_user_id=%d OR (requester_user_id=0 AND requester_email=%s))", $ticket_id, $user->ID, $user->user_email ), ARRAY_A );
	}

	private static function account_redirect( $ticket_id = 0, $success = null, $message = '' ) {
		if ( null !== $success && is_user_logged_in() ) set_transient( self::ACCOUNT_NOTICE_KEY . get_current_user_id(), array( 'success' => (bool) $success, 'message' => $message ), MINUTE_IN_SECONDS );
		wp_safe_redirect( self::account_url( $ticket_id ) ); exit;
	}

	private static function account_url( $ticket_id = 0, $view = '' ) {
		$args = array();
		if ( $ticket_id ) $args['support_ticket'] = absint( $ticket_id );
		if ( in_array( $view, array( 'new', 'requests' ), true ) ) $args['support_view'] = $view;
		if ( class_exists( 'ContactPanda_Customer_Portal' ) && ContactPanda_Customer_Portal::is_configured() ) {
			return ContactPanda_Customer_Portal::tab_url( 'support', $args );
		}
		$url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'hilfe-support' ) : home_url( '/' );
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	private static function contact_options() {
		$options = array();
		if ( ! method_exists( 'ContactPanda_Plugin', 'extension_contacts' ) || ! method_exists( 'ContactPanda_Plugin', 'extension_value' ) ) return $options;
		try {
			foreach ( (array) ContactPanda_Plugin::extension_contacts() as $contact ) {
				$id = absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) );
				$email = sanitize_email( ContactPanda_Plugin::extension_value( $contact, array( 'email', 'get_email' ) ) );
				$name = trim( ContactPanda_Plugin::extension_value( $contact, array( 'first_name', 'get_first_name' ) ) . ' ' . ContactPanda_Plugin::extension_value( $contact, array( 'last_name', 'get_last_name' ) ) );
				if ( $id ) $options[] = array( 'id' => $id, 'email' => $email, 'name' => $name, 'label' => trim( $name . ( $email ? ' — ' . $email : '' ) ) ?: '#' . $id );
			}
		} catch ( Throwable $error ) {
			self::report_optional_form_error( 'contacts', $error );
			return array();
		}
		usort( $options, static function ( $a, $b ) { return strcasecmp( $a['label'], $b['label'] ); } );
		return $options;
	}

	private static function contact_option( $id ) { foreach ( self::contact_options() as $option ) if ( (int) $option['id'] === (int) $id ) return $option; return false; }

	private static function contact_id_for_email( $email ) {
		if ( ! is_email( $email ) || ! method_exists( 'ContactPanda_Plugin', 'extension_contact_by_email' ) || ! method_exists( 'ContactPanda_Plugin', 'extension_value' ) ) return 0;
		try {
			$contact = ContactPanda_Plugin::extension_contact_by_email( $email );
			return $contact ? absint( ContactPanda_Plugin::extension_value( $contact, array( 'id', 'ID', 'get_id' ) ) ) : 0;
		} catch ( Throwable $error ) {
			self::report_optional_form_error( 'contact', $error );
			return 0;
		}
	}

	private static function user_id_for_email( $email ) {
		$user = is_email( $email ) ? get_user_by( 'email', $email ) : false;
		return $user ? absint( $user->ID ) : 0;
	}

	/**
	 * Return support relations owned by one customer. Other ContactPanda modules may
	 * append licenses, events, projects, or their own record types through the filter.
	 */
	private static function customer_relations( $email, $user_id, $contact_id ) {
		$relations = array();
		$orders = array();
		if ( function_exists( 'wc_get_orders' ) ) {
			try {
				$queries = array();
				if ( $user_id ) $queries[] = array( 'customer_id' => absint( $user_id ) );
				if ( is_email( $email ) ) $queries[] = array( 'billing_email' => $email );
				foreach ( $queries as $identity ) {
					$found = wc_get_orders( array_merge( array( 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC' ), $identity ) );
					foreach ( (array) $found as $order ) {
						if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_id' ) ) ) continue;
						$orders[ absint( $order->get_id() ) ] = $order;
					}
				}
			} catch ( Throwable $error ) {
				self::report_optional_form_error( 'customer_orders', $error );
			}
		}

		$products = array();
		foreach ( $orders as $order ) {
			$order_id = absint( $order->get_id() );
			$order_number = is_callable( array( $order, 'get_order_number' ) ) ? $order->get_order_number() : $order_id;
			$order_total = is_callable( array( $order, 'get_formatted_order_total' ) ) ? wp_strip_all_tags( $order->get_formatted_order_total() ) : '';
			$relations[] = array(
				'type'  => 'order',
				'id'    => (string) $order_id,
				'label' => trim( sprintf( __( 'Order #%s', 'contactpanda-pro' ), $order_number ) . ( $order_total ? ' · ' . $order_total : '' ) ),
				'details' => trim( sprintf( __( 'Order #%1$s · %2$s', 'contactpanda-pro' ), $order_number, $order_total ?: '—' ) ),
			);
			if ( ! is_callable( array( $order, 'get_items' ) ) ) continue;
			foreach ( (array) $order->get_items() as $item ) {
				$product_id = is_object( $item ) && is_callable( array( $item, 'get_product_id' ) ) ? absint( $item->get_product_id() ) : 0;
				if ( ! $product_id ) continue;
				$name = is_callable( array( $item, 'get_name' ) ) ? sanitize_text_field( $item->get_name() ) : '';
				$products[ $product_id ] = array(
					'type'  => 'product',
					'id'    => (string) $product_id,
					'label' => sprintf( __( 'Purchased product: %1$s (#%2$d)', 'contactpanda-pro' ), $name ?: __( 'Product', 'contactpanda-pro' ), $product_id ),
					'details' => sprintf( __( 'Purchased product: %1$s · Product ID %2$d', 'contactpanda-pro' ), $name ?: __( 'Product', 'contactpanda-pro' ), $product_id ),
				);
			}
		}
		$relations = array_merge( $relations, array_values( $products ) );
		$context = array( 'email' => sanitize_email( $email ), 'user_id' => absint( $user_id ), 'contact_id' => absint( $contact_id ), 'pro_customer_id' => 0, 'company_id' => 0 );
		global $wpdb;
		$customer_table = ContactPanda_Pro_Schema::table_names()['customers'];
		if ( ContactPanda_Pro_Schema::table_exists_public( $customer_table ) ) {
			$matches = array();
			$args = array();
			if ( $contact_id ) { $matches[] = 'contact_id=%d'; $args[] = absint( $contact_id ); }
			if ( $user_id ) { $matches[] = 'user_id=%d'; $args[] = absint( $user_id ); }
			if ( $matches ) {
				$customer = $wpdb->get_row( $wpdb->prepare( "SELECT id,company_id FROM `{$customer_table}` WHERE deleted_at IS NULL AND (" . implode( ' OR ', $matches ) . ') ORDER BY updated_at DESC LIMIT 1', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table and prepared identity values.
				if ( $customer ) {
					$context['pro_customer_id'] = absint( $customer['id'] );
					$context['company_id'] = absint( $customer['company_id'] );
				}
			}
		}
		$relations = apply_filters( 'contactpanda_pro_support_customer_relations', $relations, $context );

		$clean = array();
		foreach ( (array) $relations as $relation ) {
			$type = sanitize_key( $relation['type'] ?? '' );
			$id = sanitize_text_field( (string) ( $relation['id'] ?? '' ) );
			$label = sanitize_text_field( $relation['label'] ?? '' );
			if ( ! $type || '' === $id || ! $label ) continue;
			$clean[ $type . '|' . $id ] = array( 'type' => $type, 'id' => $id, 'label' => $label, 'details' => sanitize_textarea_field( $relation['details'] ?? $label ) );
		}
		return array_values( $clean );
	}

	private static function customer_summary( $email, $relations ) {
		$counts = self::relation_counts( $relations );
		$labels = array( 'order' => __( 'orders', 'contactpanda-pro' ), 'product' => __( 'products', 'contactpanda-pro' ), 'event' => __( 'events', 'contactpanda-pro' ), 'license' => __( 'licenses', 'contactpanda-pro' ), 'project' => __( 'projects', 'contactpanda-pro' ) );
		$parts = array();
		foreach ( $counts as $type => $count ) $parts[] = absint( $count ) . ' ' . ( $labels[ $type ] ?? $type );
		return trim( sanitize_email( $email ) . ' · ' . ( $parts ? implode( ' · ', $parts ) : __( 'no linked records', 'contactpanda-pro' ) ), ' ·' );
	}

	private static function relation_counts( $relations ) {
		$counts = array();
		foreach ( (array) $relations as $relation ) { $type = sanitize_key( $relation['type'] ?? '' ); if ( $type ) $counts[ $type ] = ( $counts[ $type ] ?? 0 ) + 1; }
		return $counts;
	}

	private static function relation_type_labels() {
		return array( 'order' => __( 'Orders', 'contactpanda-pro' ), 'product' => __( 'Products', 'contactpanda-pro' ), 'event' => __( 'Events', 'contactpanda-pro' ), 'license' => __( 'Licenses', 'contactpanda-pro' ), 'project' => __( 'Projects', 'contactpanda-pro' ) );
	}

	private static function relation_value( $relation ) {
		return sanitize_key( $relation['type'] ?? '' ) . '|' . rawurlencode( sanitize_text_field( (string) ( $relation['id'] ?? '' ) ) );
	}

	private static function resolve_relation( $value, $relations ) {
		$value = sanitize_text_field( wp_unslash( $value ) );
		if ( '' === $value ) return array( 'type' => '', 'id' => '', 'label' => '' );
		foreach ( $relations as $relation ) if ( hash_equals( self::relation_value( $relation ), $value ) ) return $relation;
		return new WP_Error( 'contactpanda_pro_support_relation_invalid', __( 'The selected record does not belong to this customer. Please select it again.', 'contactpanda-pro' ) );
	}

	private static function render_relation_select( $relations ) {
		echo '<label class="contactpanda-support-related"><span>' . esc_html__( 'Related record', 'contactpanda-pro' ) . '</span><select name="related_record"><option value="">' . esc_html__( 'General request without a related record', 'contactpanda-pro' ) . '</option>';
		foreach ( $relations as $relation ) echo '<option value="' . esc_attr( self::relation_value( $relation ) ) . '">' . esc_html( $relation['label'] ) . '</option>';
		echo '</select><small>' . esc_html__( 'Only your own orders, products, licenses, events, and projects are shown.', 'contactpanda-pro' ) . '</small></label>';
	}

	private static function relation_legacy_fields( $relation ) {
		$type = $relation['type'] ?? '';
		$id = $relation['id'] ?? '';
		return array(
			'external_order_id' => 'order' === $type ? $id : '',
			'license_reference' => 'license' === $type ? $id : '',
			'products'          => 'product' === $type ? array( absint( $id ) ) : array(),
			'orders'            => 'order' === $type ? array( absint( $id ) ) : array(),
		);
	}

	private static function ticket_relation_label( $ticket ) {
		if ( ! empty( $ticket['related_label'] ) ) return $ticket['related_label'];
		if ( ! empty( $ticket['external_order_id'] ) ) return sprintf( __( 'Order %s', 'contactpanda-pro' ), $ticket['external_order_id'] );
		if ( ! empty( $ticket['license_reference'] ) ) return sprintf( __( 'License %s', 'contactpanda-pro' ), $ticket['license_reference'] );
		return __( 'General request', 'contactpanda-pro' );
	}

	private static function related_record_card( $ticket ) {
		return '<div class="contactpanda-helpdesk-related-card"><span class="dashicons dashicons-admin-links" aria-hidden="true"></span><div><strong>' . esc_html__( 'Related record', 'contactpanda-pro' ) . '</strong><p>' . esc_html( self::ticket_relation_label( $ticket ) ) . '</p></div></div>';
	}
	private static function render_product_select() {
		if ( ! function_exists( 'wc_get_products' ) ) return;
		try {
			$products = wc_get_products( array( 'limit' => 100, 'status' => array( 'publish', 'private' ), 'orderby' => 'name', 'order' => 'ASC' ) );
			echo '<label><span>' . esc_html__( 'Products (multiple selection allowed)', 'contactpanda-pro' ) . '</span><select name="product_ids[]" multiple size="5">';
			foreach ( (array) $products as $product ) {
				if ( ! is_object( $product ) || ! is_callable( array( $product, 'get_id' ) ) || ! is_callable( array( $product, 'get_name' ) ) ) continue;
				echo '<option value="' . esc_attr( $product->get_id() ) . '">' . esc_html( $product->get_name() . ' (#' . $product->get_id() . ')' ) . '</option>';
			}
			echo '</select></label>';
		} catch ( Throwable $error ) {
			self::report_optional_form_error( 'products', $error );
		}
	}

	private static function render_order_select( $user_id ) {
		if ( ! function_exists( 'wc_get_orders' ) ) return;
		try {
			$args = array( 'limit' => 100, 'orderby' => 'date', 'order' => 'DESC' );
			if ( $user_id ) $args['customer_id'] = absint( $user_id );
			$orders = wc_get_orders( $args );
			echo '<label><span>' . esc_html__( 'Orders (multiple selection allowed)', 'contactpanda-pro' ) . '</span><select name="order_ids[]" multiple size="5">';
			foreach ( (array) $orders as $order ) {
				if ( ! is_object( $order ) || ! is_callable( array( $order, 'get_id' ) ) ) continue;
				$order_number = is_callable( array( $order, 'get_order_number' ) ) ? $order->get_order_number() : $order->get_id();
				$billing_name = is_callable( array( $order, 'get_formatted_billing_full_name' ) ) ? $order->get_formatted_billing_full_name() : '';
				$order_total  = is_callable( array( $order, 'get_formatted_order_total' ) ) ? wp_strip_all_tags( $order->get_formatted_order_total() ) : '';
				echo '<option value="' . esc_attr( $order->get_id() ) . '">' . esc_html( trim( '#' . $order_number . ' — ' . $billing_name . ' — ' . $order_total, ' —' ) ) . '</option>';
			}
			echo '</select></label>';
		} catch ( Throwable $error ) {
			self::report_optional_form_error( 'orders', $error );
		}
	}

	private static function report_optional_form_error( $component, $error ) {
		$reference = substr( hash( 'sha256', sanitize_key( $component ) . '|' . get_class( $error ) . '|' . $error->getMessage() ), 0, 12 );
		do_action( 'contactpanda_pro_support_form_optional_error', sanitize_key( $component ), sanitize_text_field( $error->getMessage() ), $reference, $error );
	}
	private static function allowed_customer_orders( $ids, $user ) { $allowed = array(); if ( ! function_exists( 'wc_get_order' ) ) return $allowed; foreach ( $ids as $id ) { $order = wc_get_order( $id ); if ( $order && ( (int) $order->get_user_id() === (int) $user->ID || 0 === strcasecmp( $order->get_billing_email(), $user->user_email ) ) ) $allowed[] = $id; } return $allowed; }
	private static function clean_id_list( $values ) { return array_values( array_unique( array_filter( array_map( 'absint', (array) wp_unslash( $values ) ) ) ) ); }
	private static function id_list_label( $json ) { $ids = json_decode( (string) $json, true ); return $ids ? implode( ', ', array_map( static function ( $id ) { return '#' . absint( $id ); }, $ids ) ) : '—'; }
	private static function uploaded_attachments() {
		$files = $_FILES['attachments'] ?? array(); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- caller verifies its action nonce.
		if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) return array();
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$allowed = array_intersect_key( get_allowed_mime_types(), array_flip( array( 'jpg|jpeg|jpe', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt' ) ) );
		$uploaded = array();
		foreach ( array_keys( $files['name'] ) as $index ) {
			if ( UPLOAD_ERR_NO_FILE === (int) ( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ) ) continue;
			$file = array( 'name' => sanitize_file_name( $files['name'][ $index ] ?? '' ), 'type' => sanitize_mime_type( $files['type'][ $index ] ?? '' ), 'tmp_name' => $files['tmp_name'][ $index ] ?? '', 'error' => absint( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ), 'size' => absint( $files['size'][ $index ] ?? 0 ) );
			if ( ! $file['name'] || $file['size'] > 10 * MB_IN_BYTES ) continue;
			$result = wp_handle_upload( $file, array( 'test_form' => false, 'mimes' => $allowed ) );
			if ( empty( $result['error'] ) && ! empty( $result['url'] ) ) $uploaded[] = array( 'name' => $file['name'], 'url' => esc_url_raw( $result['url'] ), 'type' => sanitize_mime_type( $result['type'] ?? '' ), 'size' => $file['size'] );
		}
		return $uploaded;
	}
	private static function attachment_links( $json ) {
		$items = json_decode( (string) $json, true ); if ( ! is_array( $items ) || ! $items ) return '';
		$html = '<ul class="contactpanda-helpdesk-attachments">'; foreach ( $items as $item ) if ( ! empty( $item['url'] ) ) $html .= '<li><a href="' . esc_url( $item['url'] ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $item['name'] ?? basename( $item['url'] ) ) . '</a></li>'; return $html . '</ul>';
	}
	private static function local_source_id() { global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); return absint( $wpdb->get_var( "SELECT id FROM `{$tables['support_sources']}` WHERE source_type='local' AND status='active' ORDER BY id ASC LIMIT 1" ) ); }

	/**
	 * Store a ticket and its first message as one logical operation.
	 *
	 * A failed first message removes the just-created ticket so the inbox can never
	 * contain an unusable half-record. Existing records are never changed.
	 */
	private static function persist_ticket( $ticket, $message ) {
		global $wpdb;
		$tables = ContactPanda_Pro_Schema::table_names();
		foreach ( array( 'support_sources', 'support_tickets', 'support_messages' ) as $table_key ) {
			if ( ! ContactPanda_Pro_Schema::table_exists_public( $tables[ $table_key ] ) ) return new WP_Error( 'contactpanda_pro_support_schema_missing', __( 'The Support Desk database is not ready. Please run the plugin update and try again.', 'contactpanda-pro' ) );
		}
		if ( empty( $ticket['source_id'] ) || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$tables['support_sources']}` WHERE id=%d AND status='active'", absint( $ticket['source_id'] ) ) ) ) {
			return new WP_Error( 'contactpanda_pro_support_source_missing', __( 'No active support source is available. Please reload the page and try again.', 'contactpanda-pro' ) );
		}
		$now = current_time( 'mysql', true );
		$ticket['created_at'] = $now;
		$ticket['updated_at'] = $now;
		$transaction = false !== $wpdb->query( 'START TRANSACTION' );
		$inserted = $wpdb->insert( $tables['support_tickets'], $ticket );
		if ( false === $inserted || ! $wpdb->insert_id ) {
			if ( $transaction ) $wpdb->query( 'ROLLBACK' );
			do_action( 'contactpanda_pro_support_storage_error', 'ticket_insert', sanitize_text_field( $wpdb->last_error ) );
			return new WP_Error( 'contactpanda_pro_support_ticket_insert_failed', __( 'The support request could not be saved. Please reload the page and try again.', 'contactpanda-pro' ) );
		}
		$ticket_id = absint( $wpdb->insert_id );
		$message = array_merge(
			array(
				'ticket_id'        => $ticket_id,
				'visibility'       => 'customer',
				'direction'        => 'inbound',
				'attachments_json' => wp_json_encode( array() ),
				'created_at'       => $now,
			),
			$message
		);
		if ( false === $wpdb->insert( $tables['support_messages'], $message ) ) {
			if ( $transaction ) $wpdb->query( 'ROLLBACK' );
			else $wpdb->delete( $tables['support_tickets'], array( 'id' => $ticket_id ), array( '%d' ) );
			do_action( 'contactpanda_pro_support_storage_error', 'message_insert', sanitize_text_field( $wpdb->last_error ) );
			return new WP_Error( 'contactpanda_pro_support_message_insert_failed', __( 'The first message could not be saved, so no incomplete request was created. Please try again.', 'contactpanda-pro' ) );
		}
		$message_id = absint( $wpdb->insert_id );
		$attachments = self::uploaded_attachments();
		if ( $attachments ) $wpdb->update( $tables['support_messages'], array( 'attachments_json' => wp_json_encode( $attachments ) ), array( 'id' => $message_id ), array( '%s' ), array( '%d' ) );
		if ( $transaction ) $wpdb->query( 'COMMIT' );
		do_action( 'contactpanda_pro_support_ticket_created', $ticket_id, array_merge( $ticket, array( 'id' => $ticket_id ) ) );
		return $ticket_id;
	}

	/** Repair a missing Support Desk schema even when an older version option says it is current. */
	private static function ensure_support_schema() {
		$tables = ContactPanda_Pro_Schema::table_names();
		foreach ( array( 'support_sources', 'support_tickets', 'support_messages' ) as $table_key ) {
			if ( ContactPanda_Pro_Schema::table_exists_public( $tables[ $table_key ] ) ) continue;
			$installed = ContactPanda_Pro_Schema::install();
			if ( is_wp_error( $installed ) ) return $installed;
			break;
		}
		foreach ( array( 'support_sources', 'support_tickets', 'support_messages' ) as $table_key ) {
			if ( ! ContactPanda_Pro_Schema::table_exists_public( $tables[ $table_key ] ) ) return new WP_Error( 'contactpanda_pro_support_schema_missing', __( 'The Support Desk database could not be prepared.', 'contactpanda-pro' ) );
		}
		return true;
	}

	/** Resolve the authoritative requester from the linked ContactPanda person. */
	private static function resolve_requester( $contact_id, $fallback = array() ) {
		if ( ! $contact_id ) return new WP_Error( 'contactpanda_pro_support_person_missing', __( 'Please select a person or create a new one.', 'contactpanda-pro' ) );
		$selected = self::contact_option( $contact_id );
		$name = $selected ? sanitize_text_field( $selected['name'] ) : trim( sanitize_text_field( $fallback['first_name'] ?? '' ) . ' ' . sanitize_text_field( $fallback['last_name'] ?? '' ) );
		$email = $selected ? sanitize_email( $selected['email'] ) : sanitize_email( $fallback['email'] ?? '' );
		if ( ! is_email( $email ) ) return new WP_Error( 'contactpanda_pro_support_email_missing', __( 'The linked person needs a valid email address.', 'contactpanda-pro' ) );
		if ( '' === $name ) $name = $email;
		return array( 'contact_id' => absint( $contact_id ), 'name' => $name, 'email' => $email );
	}
	private static function request_types() { return array( 'general' => __( 'General request', 'contactpanda-pro' ), 'feature_request' => __( 'Feature request', 'contactpanda-pro' ), 'software' => __( 'Software and license', 'contactpanda-pro' ), 'product' => __( 'Product question', 'contactpanda-pro' ), 'order' => __( 'Order question', 'contactpanda-pro' ), 'event' => __( 'Event question', 'contactpanda-pro' ), 'project' => __( 'Project question', 'contactpanda-pro' ) ); }
	private static function clean_request_type( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::request_types()[ $value ] ) ? $value : 'general'; }

	private static function ensure_local_source() {
		global $wpdb; $tables = ContactPanda_Pro_Schema::table_names();
		if ( ! ContactPanda_Pro_Schema::table_exists_public( $tables['support_sources'] ) ) return;
		$local = $wpdb->get_row( "SELECT id,status FROM `{$tables['support_sources']}` WHERE source_type='local' ORDER BY id ASC LIMIT 1", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- internal table name.
		if ( $local ) {
			if ( 'active' !== $local['status'] ) $wpdb->update( $tables['support_sources'], array( 'status' => 'active', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => absint( $local['id'] ) ) );
			return;
		}
		$now = current_time( 'mysql', true ); $wpdb->insert( $tables['support_sources'], array( 'name' => __( 'Local website', 'contactpanda-pro' ), 'source_type' => 'local', 'base_url' => home_url( '/' ), 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
	}

	private static function ticket_value( $ticket_id, $column ) { global $wpdb; $tables = ContactPanda_Pro_Schema::table_names(); $allowed = array( 'status' ); if ( ! in_array( $column, $allowed, true ) ) return ''; return (string) $wpdb->get_var( $wpdb->prepare( "SELECT {$column} FROM `{$tables['support_tickets']}` WHERE id=%d", $ticket_id ) ); }
	private static function notify_support_team( $ticket_id, $subject, $requester, $reply ) { $email = sanitize_email( get_option( 'admin_email' ) ); if ( ! $email ) return; $title = $reply ? sprintf( __( 'New reply to support request #%1$d: %2$s', 'contactpanda-pro' ), $ticket_id, $subject ) : sprintf( __( 'New support request #%1$d: %2$s', 'contactpanda-pro' ), $ticket_id, $subject ); $url = admin_url( 'admin.php?page=contactpanda-business-support&ticket=' . absint( $ticket_id ) ); $body = sprintf( __( '%1$s has submitted a support request or reply. Open the request: %2$s', 'contactpanda-pro' ), sanitize_text_field( $requester ), esc_url_raw( $url ) ); class_exists( 'ContactPanda_System_Emails' ) ? ContactPanda_System_Emails::send( $email, $title, wpautop( esc_html( $body ) ) ) : wp_mail( $email, $title, $body ); }
	private static function statuses() { return array( 'new' => __( 'New', 'contactpanda-pro' ), 'open' => __( 'Open', 'contactpanda-pro' ), 'in_progress' => __( 'In progress', 'contactpanda-pro' ), 'waiting_support' => __( 'Waiting for support', 'contactpanda-pro' ), 'waiting_customer' => __( 'Waiting for customer', 'contactpanda-pro' ), 'answered' => __( 'Answered', 'contactpanda-pro' ), 'resolved' => __( 'Resolved', 'contactpanda-pro' ), 'closed' => __( 'Closed', 'contactpanda-pro' ) ); }
	private static function priorities() { return array( 'low' => __( 'Low', 'contactpanda-pro' ), 'normal' => __( 'Normal', 'contactpanda-pro' ), 'high' => __( 'High', 'contactpanda-pro' ), 'urgent' => __( 'Urgent', 'contactpanda-pro' ) ); }
	private static function clean_priority( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::priorities()[ $value ] ) ? $value : 'normal'; }
	private static function status_badge( $status ) { return '<span class="contactpanda-pro-status contactpanda-ticket-status is-' . esc_attr( sanitize_html_class( $status ) ) . '">' . esc_html( self::statuses()[ $status ] ?? $status ) . '</span>'; }
	private static function display_date( $date ) { return $date ? mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $date . ' +0000' ) : '—'; }
	private static function redirect_notice( $success, $message, $ticket_id = 0 ) { set_transient( self::NOTICE_KEY . get_current_user_id(), array( 'success' => (bool) $success, 'message' => $message ), MINUTE_IN_SECONDS ); $args = array( 'page' => 'contactpanda-business-support' ); if ( $ticket_id ) $args['ticket'] = $ticket_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function authorize( $action, $resource_type, $resource_id = 0, $context = array() ) { if ( ! ContactPanda_Pro_Access::can( $action, $resource_type, $resource_id, $context ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-pro' ), '', array( 'response' => 403 ) ); }
}
