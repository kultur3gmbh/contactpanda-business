<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Business_Projects {
	const NOTICE = 'contactpanda_business_notice_';

	public static function boot() {
		add_action( 'admin_post_contactpanda_business_save_project', array( __CLASS__, 'save_project' ) );
		add_action( 'admin_post_contactpanda_business_save_project_field', array( __CLASS__, 'save_project_field' ) );
		add_action( 'admin_post_contactpanda_business_save_task', array( __CLASS__, 'save_task' ) );
		add_action( 'admin_post_contactpanda_business_add_comment', array( __CLASS__, 'add_comment' ) );
		add_action( 'admin_post_contactpanda_business_upload_task_files', array( __CLASS__, 'upload_task_files' ) );
		add_action( 'admin_post_contactpanda_business_remove_task_file', array( __CLASS__, 'remove_task_file' ) );
		add_action( 'admin_post_contactpanda_business_add_checklist_item', array( __CLASS__, 'add_checklist_item' ) );
		add_action( 'admin_post_contactpanda_business_toggle_checklist_item', array( __CLASS__, 'toggle_checklist_item' ) );
		add_action( 'admin_post_contactpanda_business_save_project_step', array( __CLASS__, 'save_project_step' ) );
		add_action( 'admin_post_contactpanda_business_save_project_payment', array( __CLASS__, 'save_project_payment' ) );
		add_action( 'admin_post_contactpanda_business_upload_project_documents', array( __CLASS__, 'upload_project_documents' ) );
		add_action( 'admin_post_contactpanda_business_remove_project_document', array( __CLASS__, 'remove_project_document' ) );
		add_action( 'admin_post_contactpanda_business_trash_task', array( __CLASS__, 'trash_task' ) );
		add_action( 'wp_ajax_contactpanda_business_move_task', array( __CLASS__, 'move_task' ) );
		add_action( 'contactpanda_pro_ticket_sidebar', array( __CLASS__, 'support_ticket_box' ) );
		add_action( 'admin_post_contactpanda_business_from_support', array( __CLASS__, 'from_support' ) );
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_action( 'woocommerce_account_projekte-korrekturen_endpoint', array( __CLASS__, 'account_page' ) );
		add_filter( 'contactpanda_customer_portal_tabs', array( __CLASS__, 'customer_portal_tabs' ), 30, 2 );
		add_action( 'admin_post_contactpanda_business_customer_task', array( __CLASS__, 'customer_task' ) );
		add_action( 'admin_post_contactpanda_business_customer_comment', array( __CLASS__, 'customer_comment' ) );
		add_action( 'admin_post_contactpanda_business_customer_update_task', array( __CLASS__, 'customer_update_task' ) );
		add_action( 'contactpanda_plus_customer_detail', array( __CLASS__, 'customer_projects' ), 10, 5 );
	}

	/** Add customer-visible projects to the shared, WooCommerce-independent portal. */
	public static function customer_portal_tabs( $tabs, $identity = array() ) {
		unset( $identity );
		$tabs['projects'] = array(
			'label'    => __( 'Projects', 'contactpanda-business' ),
			'callback' => array( __CLASS__, 'account_page' ),
			'priority' => 50,
		);
		return $tabs;
	}

	public static function customer_projects( $contact_id, $customer, $contact, $editable ) {
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$contact_id = absint( $contact_id );
		$user_id = absint( $customer['user_id'] ?? 0 );
		$company_id = absint( $customer['company_id'] ?? 0 );
		$participant_conditions = array( "(x.entity_type='person' AND x.entity_id=%d)" );
		$participant_args = array( $contact_id );
		$legacy_conditions = array( 'p.contact_id=%d' );
		$legacy_args = array( $contact_id );
		if ( $user_id ) {
			$participant_conditions[] = "(x.entity_type='user' AND x.entity_id=%d)";
			$participant_args[] = $user_id;
			$legacy_conditions[] = 'p.customer_user_id=%d';
			$legacy_args[] = $user_id;
		}
		if ( $company_id ) {
			$participant_conditions[] = "(x.entity_type='company' AND x.entity_id=%d)";
			$participant_args[] = $company_id;
			$legacy_conditions[] = 'p.company_id=%d';
			$legacy_args[] = $company_id;
		}
		$sql = "SELECT p.*,(SELECT COUNT(*) FROM `{$t['tasks']}` q WHERE q.project_id=p.id AND q.deleted_at IS NULL) task_count,(SELECT COUNT(*) FROM `{$t['tasks']}` q WHERE q.project_id=p.id AND q.deleted_at IS NULL AND q.board_column!='done') open_count FROM `{$t['projects']}` p WHERE p.deleted_at IS NULL AND ((" . implode( ' OR ', $legacy_conditions ) . ") OR EXISTS (SELECT 1 FROM `{$t['participants']}` x WHERE x.project_id=p.id AND (" . implode( ' OR ', $participant_conditions ) . '))) ORDER BY p.updated_at DESC';
		$projects = $wpdb->get_results( $wpdb->prepare( $sql, array_merge( $legacy_args, $participant_args ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe tables and prepared values.
		echo '<div class="contactpanda-toolbar contactpanda-customer-project-heading"><h2>' . esc_html__( 'Assigned projects', 'contactpanda-business' ) . '</h2><span class="contactpanda-count">' . esc_html( count( $projects ) ) . '</span></div>';
		if ( ! $projects ) {
			echo '<p class="contactpanda-muted">' . esc_html__( 'No project is currently assigned to this customer.', 'contactpanda-business' ) . '</p>';
			return;
		}
		echo '<div class="contactpanda-table-scroll"><table class="widefat striped contactpanda-table"><thead><tr><th>' . esc_html__( 'Project', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Tasks', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th><th><span class="screen-reader-text">' . esc_html__( 'Actions', 'contactpanda-business' ) . '</span></th></tr></thead><tbody>';
		foreach ( $projects as $project ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => absint( $project['id'] ) ), admin_url( 'admin.php' ) );
			$color = self::clean_color( $project['color'] ?? '' );
			echo '<tr><td><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( $color ) . '"></span><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $project['name'] ) . '</strong></a></td><td>' . esc_html( sprintf( __( '%1$d open / %2$d total', 'contactpanda-business' ), $project['open_count'], $project['task_count'] ) ) . '</td><td><span class="cpb-status is-' . esc_attr( $project['status'] ) . '">' . esc_html( self::project_statuses()[ $project['status'] ] ?? $project['status'] ) . '</span></td><td><a class="button cpb-icon-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'View project', 'contactpanda-business' ) . '"><span class="dashicons dashicons-visibility" aria-hidden="true"></span></a></td></tr>';
		}
		echo '</tbody></table></div>';
	}

	public static function endpoint() { add_rewrite_endpoint( 'projekte-korrekturen', EP_ROOT | EP_PAGES ); }

	public static function account_menu( $items ) {
		$logout = $items['customer-logout'] ?? null; unset( $items['customer-logout'] );
		$items['projekte-korrekturen'] = __( 'Projects', 'contactpanda-business' );
		if ( null !== $logout ) $items['customer-logout'] = $logout;
		return $items;
	}

	public static function render() {
		self::guard();
		$project_id = absint( $_GET['project'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route.
		$task_id = absint( $_GET['task'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only route.
		self::header();
		if ( $task_id ) self::render_task( $task_id );
		elseif ( $project_id ) self::render_board( $project_id );
		else self::render_projects();
		echo '</div>';
	}

	private static function header() {
		echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html__( 'Projects', 'contactpanda-business' ) . '</h1><p>' . esc_html__( 'Keep project information, documents, finances, tasks, and customer-visible progress together.', 'contactpanda-business' ) . '</p></div></header>';
		$notice = get_transient( self::NOTICE . get_current_user_id() );
		if ( is_array( $notice ) ) { delete_transient( self::NOTICE . get_current_user_id() ); echo '<div class="notice notice-' . ( $notice['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
	}

	private static function render_projects() {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$status = sanitize_key( wp_unslash( $_GET['project_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$trash = ! empty( $_GET['trash'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$where = array( $trash ? 'p.deleted_at IS NOT NULL' : 'p.deleted_at IS NULL' ); $args = array();
		if ( '' !== $search ) { $where[] = '(p.name LIKE %s OR p.description LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; $args[] = $like; $args[] = $like; }
		if ( isset( self::project_statuses()[ $status ] ) ) { $where[] = 'p.status=%s'; $args[] = $status; }
		$sql = "SELECT p.*,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL) task_count,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL AND x.board_column!='done') open_count FROM `{$t['projects']}` p WHERE " . implode( ' AND ', $where ) . ' ORDER BY p.updated_at DESC';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared values and internal tables.
		echo '<div class="cpb-toolbar"><form method="get"><input type="hidden" name="page" value="contactpanda-business-projects">' . ( $trash ? '<input type="hidden" name="trash" value="1">' : '' ) . '<label class="screen-reader-text" for="cpb-project-search">' . esc_html__( 'Search projects', 'contactpanda-business' ) . '</label><input id="cpb-project-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search projects', 'contactpanda-business' ) . '"><select name="project_status"><option value="">' . esc_html__( 'All', 'contactpanda-business' ) . '</option>';
		foreach ( self::project_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select><button class="button" aria-label="' . esc_attr__( 'Search', 'contactpanda-business' ) . '"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></form><div><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'contactpanda-business-projects', 'trash' => $trash ? 0 : 1 ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $trash ? __( 'Back to projects', 'contactpanda-business' ) : __( 'Trash', 'contactpanda-business' ) ) . '</a> ' . ( $trash ? '' : '<button type="button" class="button button-primary" data-cpb-open="cpb-new-project">' . esc_html__( 'Create project', 'contactpanda-business' ) . '</button>' ) . '</div></div>';
		self::project_modal();
		echo '<section class="cpb-card"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Project', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Project participants', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Tasks', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="6" class="cpb-empty">' . esc_html__( 'No projects have been created yet.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $row['id'] ), admin_url( 'admin.php' ) );
			$participants = self::participant_summary( (int) $row['id'] );
			$color = self::clean_color( $row['color'] ?? '' );
			echo '<tr><td><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( $color ) . '"></span>' . ( $trash ? '<strong>' . esc_html( $row['name'] ) . '</strong>' : '<a href="' . esc_url( $url ) . '"><strong>' . esc_html( $row['name'] ) . '</strong></a>' ) . '<br><small>' . esc_html( wp_trim_words( $row['description'], 12 ) ) . '</small></td><td>' . esc_html( $participants ?: __( 'Not assigned', 'contactpanda-business' ) ) . '</td><td>' . esc_html( sprintf( __( '%1$d open / %2$d total', 'contactpanda-business' ), $row['open_count'], $row['task_count'] ) ) . '</td><td>' . esc_html( self::visibilities()[ $row['visibility'] ] ?? $row['visibility'] ) . '</td><td class="cpb-actions">';
			if ( ! $trash ) echo '<a class="button cpb-icon-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'View project', 'contactpanda-business' ) . '"><span class="dashicons dashicons-visibility"></span></a>';
			ContactPanda_Business_Workspace::project_action_buttons( $row['id'], $trash );
			echo '</td><td><span class="cpb-status is-' . esc_attr( $row['status'] ) . '">' . esc_html( self::project_statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function project_modal( $project = null ) {
		$is_edit = is_array( $project );
		$project_id = $is_edit ? absint( $project['id'] ) : 0;
		$modal_id = $is_edit ? 'cpb-edit-project' : 'cpb-new-project';
		$title = $is_edit ? __( 'Edit project', 'contactpanda-business' ) : __( 'Create project', 'contactpanda-business' );
		$selected = $is_edit ? self::project_participants( $project_id ) : array();
		echo '<div id="' . esc_attr( $modal_id ) . '" class="cpb-modal" hidden><div class="cpb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="' . esc_attr( $modal_id . '-title' ) . '"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="' . esc_attr( $modal_id . '-title' ) . '">' . esc_html( $title ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_project"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_save_project' );
		echo '<label><span>' . esc_html__( 'Project name', 'contactpanda-business' ) . '</span><input name="name" value="' . esc_attr( $project['name'] ?? '' ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $project['description'] ?? '' ) . '</textarea></label>';
		echo '<div class="cpb-form-grid"><label><span>' . esc_html__( 'Project color', 'contactpanda-business' ) . '</span><input type="color" name="color" value="' . esc_attr( self::clean_color( $project['color'] ?? '' ) ) . '"></label><label><span>' . esc_html__( 'Status', 'contactpanda-business' ) . '</span><select name="status">'; foreach ( self::project_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $project['status'] ?? 'active', $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</span><input type="number" min="0" max="100" name="progress" value="' . esc_attr( $project['progress'] ?? 0 ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $project['visibility'] ?? 'customer', 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $project['visibility'] ?? 'customer', 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label><label><span>' . esc_html__( 'Project start', 'contactpanda-business' ) . '</span><input type="date" name="start_date" value="' . esc_attr( $project['start_date'] ?? '' ) . '"></label><label><span>' . esc_html__( 'Target completion', 'contactpanda-business' ) . '</span><input type="date" name="target_end_date" value="' . esc_attr( $project['target_end_date'] ?? '' ) . '"></label><label><span>' . esc_html__( 'Total budget', 'contactpanda-business' ) . '</span><input type="number" min="0" step="0.01" name="budget_total" value="' . esc_attr( $project['budget_total'] ?? '0.00' ) . '"></label><label><span>' . esc_html__( 'Currency', 'contactpanda-business' ) . '</span><input name="currency" maxlength="3" value="' . esc_attr( $project['currency'] ?? 'EUR' ) . '"></label></div>';
		echo '<label><span>' . esc_html__( 'Financing notes', 'contactpanda-business' ) . '</span><textarea name="financing_notes" rows="4">' . esc_textarea( $project['financing_notes'] ?? '' ) . '</textarea></label><label><span>' . esc_html__( 'Notes and comments', 'contactpanda-business' ) . '</span><textarea name="notes" rows="4">' . esc_textarea( $project['notes'] ?? '' ) . '</textarea></label><h3>' . esc_html__( 'Project participants', 'contactpanda-business' ) . '</h3><p class="description">' . esc_html__( 'Assign one or more customer accounts, people or companies and define their role in the project.', 'contactpanda-business' ) . '</p>';
		self::participant_picker( 'user', __( 'Customer accounts with portal access', 'contactpanda-business' ), self::user_options(), $selected['user'] ?? array() );
		self::participant_picker( 'person', __( 'People', 'contactpanda-business' ), self::person_options(), $selected['person'] ?? array() );
		self::participant_picker( 'company', __( 'Companies', 'contactpanda-business' ), self::company_options(), $selected['company'] ?? array() );
		echo '<p><button class="button button-primary">' . esc_html__( 'Save project', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function render_board( $project_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ), ARRAY_A );
		if ( ! $project ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'This project does not exist.', 'contactpanda-business' ) . '</p></div>'; return; }
		$report_base = admin_url( 'admin-post.php?action=contactpanda_business_export_report&project_id=' . $project_id );
		$tab = sanitize_key( wp_unslash( $_GET['project_tab'] ?? 'general' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab.
		if ( ! in_array( $tab, array( 'general', 'tasks' ), true ) ) $tab = 'general';
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-projects' ) ) . '">← ' . esc_html__( 'Back to project list', 'contactpanda-business' ) . '</a></p><div class="cpb-project-heading" style="--cpb-project-color:' . esc_attr( self::clean_color( $project['color'] ?? '' ) ) . '"><div class="cpb-project-title-wrap"><button type="button" class="cpb-project-editable" data-cpb-inline-edit-open="cpb-project-name-editor"><h2>' . esc_html( $project['name'] ) . '</h2></button>';
		self::project_field_form( $project_id, 'name', $project['name'], 'text', 'cpb-project-name-editor' );
		echo '<p class="cpb-project-participants">' . esc_html( self::participant_summary( $project_id ) ) . '</p><button type="button" class="button button-small" data-cpb-open="cpb-edit-project">' . esc_html__( 'Project settings', 'contactpanda-business' ) . '</button></div><div><a class="button" target="_blank" href="' . esc_url( wp_nonce_url( $report_base . '&format=html', 'contactpanda_business_export_report_' . $project_id ) ) . '">' . esc_html__( 'PDF / print report', 'contactpanda-business' ) . '</a> <a class="button" href="' . esc_url( wp_nonce_url( $report_base . '&format=docx', 'contactpanda_business_export_report_' . $project_id ) ) . '">' . esc_html__( 'Word report', 'contactpanda-business' ) . '</a></div></div>';
		self::project_modal( $project );
		$base = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $project_id ), admin_url( 'admin.php' ) );
		echo '<nav class="nav-tab-wrapper cpb-project-tabs"><a class="nav-tab ' . ( 'general' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'project_tab', 'general', $base ) ) . '">' . esc_html__( 'General information', 'contactpanda-business' ) . '</a><a class="nav-tab ' . ( 'tasks' === $tab ? 'nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'project_tab', 'tasks', $base ) ) . '">' . esc_html__( 'Tasks and corrections', 'contactpanda-business' ) . '</a></nav>';
		if ( 'general' === $tab ) { self::render_project_general( $project ); return; }
		$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT x.*,(SELECT COUNT(*) FROM `{$t['comments']}` c WHERE c.task_id=x.id) comment_count,(SELECT COUNT(*) FROM `{$t['attachments']}` f WHERE f.task_id=x.id) attachment_count,(SELECT COUNT(*) FROM `{$t['checklist']}` k WHERE k.task_id=x.id) checklist_count,(SELECT COUNT(*) FROM `{$t['checklist']}` k WHERE k.task_id=x.id AND k.is_complete=1) checklist_done FROM `{$t['tasks']}` x WHERE x.project_id=%d AND x.deleted_at IS NULL ORDER BY x.sort_order,x.id", $project_id ), ARRAY_A );
		ContactPanda_Business_Workspace::render_board_controls( $project_id, $tasks );
		echo '<div class="cpb-board" data-cpb-project="' . esc_attr( $project_id ) . '">';
		foreach ( self::columns() as $key => $label ) {
			echo '<section class="cpb-column" data-cpb-column="' . esc_attr( $key ) . '"><header><h3>' . esc_html( $label ) . '</h3><span>' . esc_html( count( array_filter( $tasks, static function ( $task ) use ( $key ) { return $key === $task['board_column']; } ) ) ) . '</span></header><div class="cpb-column-body">';
			foreach ( $tasks as $task ) if ( $key === $task['board_column'] ) self::task_card( $task );
			echo '</div>';
			if ( in_array( $key, array( 'open', 'in_progress' ), true ) ) self::inline_task_form( $project_id, $key );
			echo '</section>';
		}
		echo '</div>';
		foreach ( $tasks as $task ) self::task_detail_modal( $task );
	}

	private static function render_project_general( $project ) {
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$project_id = absint( $project['id'] );
		$areas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['areas']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,name", $project_id ), ARRAY_A );
		$steps = $wpdb->get_results( $wpdb->prepare( "SELECT s.*,a.name area_name FROM `{$t['project_steps']}` s LEFT JOIN `{$t['areas']}` a ON a.id=s.area_id WHERE s.project_id=%d AND s.deleted_at IS NULL ORDER BY s.sort_order,s.due_at,s.id", $project_id ), ARRAY_A );
		$documents = $wpdb->get_results( $wpdb->prepare( "SELECT d.*,s.title step_title FROM `{$t['project_documents']}` d LEFT JOIN `{$t['project_steps']}` s ON s.id=d.step_id WHERE d.project_id=%d AND d.deleted_at IS NULL ORDER BY d.document_date DESC,d.created_at DESC", $project_id ), ARRAY_A );
		$payments = $wpdb->get_results( $wpdb->prepare( "SELECT p.*,a.name area_name,s.title step_title FROM `{$t['payments']}` p LEFT JOIN `{$t['areas']}` a ON a.id=p.area_id LEFT JOIN `{$t['project_steps']}` s ON s.id=p.step_id WHERE p.project_id=%d AND p.deleted_at IS NULL ORDER BY p.due_at,p.id", $project_id ), ARRAY_A );
		$events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['events']}` WHERE project_id=%d AND task_id=0 ORDER BY created_at DESC LIMIT 25", $project_id ), ARRAY_A );
		$currency = self::clean_currency( $project['currency'] ?? 'EUR' );
		echo '<div class="cpb-project-overview">';
		echo '<section class="cpb-card cpb-project-summary"><div class="cpb-section-heading"><h2>' . esc_html__( 'Project overview', 'contactpanda-business' ) . '</h2></div><div class="cpb-summary-grid">';
		self::project_field_card( $project, 'start_date', __( 'Project start', 'contactpanda-business' ), self::short_date( $project['start_date'] ?? '' ), 'date' );
		self::project_field_card( $project, 'target_end_date', __( 'Project end', 'contactpanda-business' ), self::short_date( $project['target_end_date'] ?? '' ), 'date' );
		self::project_field_card( $project, 'status', __( 'Status', 'contactpanda-business' ), self::project_statuses()[ $project['status'] ] ?? $project['status'], 'status' );
		self::project_field_card( $project, 'progress', __( 'Progress', 'contactpanda-business' ), absint( $project['progress'] ?? 0 ) . '%', 'number' );
		self::project_field_card( $project, 'budget_total', __( 'Total budget', 'contactpanda-business' ), self::money( $project['budget_total'] ?? 0, $currency ), 'money' );
		echo '</div></section><div class="cpb-project-section-grid">';
		self::project_text_section( $project, 'description', __( 'Description', 'contactpanda-business' ), __( 'Click to add a project description.', 'contactpanda-business' ), 'cpb-project-description' );

		echo '<section class="cpb-card cpb-project-parts"><div class="cpb-section-heading"><div><h2>' . esc_html__( 'Project parts', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Split the project into independently responsible areas.', 'contactpanda-business' ) . '</p></div></div><div class="cpb-area-list">';
		if ( ! $areas ) echo '<span class="cpb-empty">' . esc_html__( 'No project parts have been created yet.', 'contactpanda-business' ) . '</span>';
		foreach ( $areas as $area ) { $user = $area['assigned_user_id'] ? get_userdata( (int) $area['assigned_user_id'] ) : null; echo '<span class="cpb-area-chip"><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( self::clean_color( $area['color'] ) ) . '"></span><strong>' . esc_html( $area['name'] ) . '</strong>' . ( $user ? ' · ' . esc_html( $user->display_name ) : '' ) . '</span>'; }
		echo '</div><details class="cpb-add-panel"><summary><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add project part', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form-grid"><input type="hidden" name="action" value="contactpanda_business_save_area"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_save_area_' . $project_id );
		echo '<label><span>' . esc_html__( 'Area name', 'contactpanda-business' ) . '</span><input name="name" required></label><label><span>' . esc_html__( 'Color', 'contactpanda-business' ) . '</span><input type="color" name="color" value="#3d5cf5"></label><label><span>' . esc_html__( 'Responsible person', 'contactpanda-business' ) . '</span><select name="assigned_user_id">' . ContactPanda_Business_Workspace::assignee_options() . '</select></label><p><button class="button button-primary">' . esc_html__( 'Add area', 'contactpanda-business' ) . '</button></p></form></details></section>';

		echo '<section class="cpb-card cpb-project-steps"><div class="cpb-section-heading"><div><h2>' . esc_html__( 'Project steps', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Track offers, orders, phases, acceptance, and other milestones chronologically.', 'contactpanda-business' ) . '</p></div></div><div class="cpb-timeline">';
		if ( ! $steps ) echo '<p class="cpb-empty">' . esc_html__( 'No project steps have been created yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $steps as $step ) echo '<article class="cpb-timeline-item is-' . esc_attr( $step['status'] ) . '"><span></span><div><header><strong>' . esc_html( $step['title'] ) . '</strong><small>' . esc_html( self::step_types()[ $step['step_type'] ] ?? $step['step_type'] ) . '</small></header><p>' . esc_html( $step['description'] ) . '</p><footer>' . esc_html( self::short_date( $step['due_at'] ) ) . ( $step['area_name'] ? ' · ' . esc_html( $step['area_name'] ) : '' ) . ( (float) $step['amount'] > 0 ? ' · ' . esc_html( self::money( $step['amount'], $step['currency'] ) ) : '' ) . ' · <span class="cpb-status is-' . esc_attr( $step['status'] ) . '">' . esc_html( self::step_statuses()[ $step['status'] ] ?? $step['status'] ) . '</span></footer></div></article>';
		echo '</div><details class="cpb-add-panel"><summary><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add project step', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_project_step"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '"><input type="hidden" name="currency" value="' . esc_attr( $currency ) . '">'; wp_nonce_field( 'contactpanda_business_save_project_step_' . $project_id );
		echo '<div class="cpb-form-grid"><label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" required></label><label><span>' . esc_html__( 'Step type', 'contactpanda-business' ) . '</span><select name="step_type">'; foreach ( self::step_types() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Status', 'contactpanda-business' ) . '</span><select name="status">'; foreach ( self::step_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Project part', 'contactpanda-business' ) . '</span><select name="area_id">' . ContactPanda_Business_Workspace::area_options( $project_id ) . '</select></label><label><span>' . esc_html__( 'Due date', 'contactpanda-business' ) . '</span><input type="date" name="due_at"></label><label><span>' . esc_html__( 'Amount', 'contactpanda-business' ) . '</span><input type="number" min="0" step="0.01" name="amount"></label></div><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="3"></textarea></label><button class="button button-primary">' . esc_html__( 'Save project step', 'contactpanda-business' ) . '</button></form></details></section>';

		echo '<section class="cpb-card cpb-project-documents"><div class="cpb-section-heading"><div><h2>' . esc_html__( 'Project documents', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Keep offers, contracts, order confirmations, invoices, and other files with their context.', 'contactpanda-business' ) . '</p></div></div><div class="cpb-document-list">';
		if ( ! $documents ) echo '<p class="cpb-empty">' . esc_html__( 'No project documents have been added yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $documents as $document ) self::project_document( $document );
		echo '</div><details class="cpb-add-panel"><summary><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add project documents', 'contactpanda-business' ) . '</summary><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_upload_project_documents"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_upload_project_documents_' . $project_id );
		echo '<div class="cpb-form-grid"><label><span>' . esc_html__( 'Document type', 'contactpanda-business' ) . '</span><select name="document_type">'; foreach ( self::document_types() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Document date', 'contactpanda-business' ) . '</span><input type="date" name="document_date"></label><label><span>' . esc_html__( 'Related project step', 'contactpanda-business' ) . '</span><select name="step_id"><option value="0">' . esc_html__( 'No project step', 'contactpanda-business' ) . '</option>'; foreach ( $steps as $step ) echo '<option value="' . esc_attr( $step['id'] ) . '">' . esc_html( $step['title'] ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="internal">' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option><option value="customer">' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option></select></label></div><label><span>' . esc_html__( 'Files', 'contactpanda-business' ) . '</span><input type="file" name="project_files[]" multiple required></label><label><span>' . esc_html__( 'Notes', 'contactpanda-business' ) . '</span><textarea name="notes" rows="3"></textarea></label><button class="button button-primary">' . esc_html__( 'Upload documents', 'contactpanda-business' ) . '</button></form></details></section>';

		echo '<section class="cpb-card cpb-project-financing"><div class="cpb-section-heading"><div><h2>' . esc_html__( 'Financing and payment plan', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Plan partial and final payments and record when they were paid.', 'contactpanda-business' ) . '</p></div></div>';
		self::project_inline_text( $project, 'financing_notes', __( 'Financing notes', 'contactpanda-business' ), __( 'Click to add financing details.', 'contactpanda-business' ) );
		echo '<div class="cpb-table-scroll"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Payment', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Project part / step', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Due date', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Amount', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Paid', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $payments ) echo '<tr><td colspan="6" class="cpb-empty">' . esc_html__( 'No payments have been planned yet.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $payments as $payment ) echo '<tr><td><strong>' . esc_html( $payment['title'] ) . '</strong><br><small>' . esc_html( $payment['reference'] ) . '</small></td><td>' . esc_html( $payment['area_name'] ?: $payment['step_title'] ?: '—' ) . '</td><td>' . esc_html( self::short_date( $payment['due_at'] ) ) . '</td><td>' . esc_html( self::money( $payment['amount'], $payment['currency'] ) ) . '</td><td>' . esc_html( self::money( $payment['paid_amount'], $payment['currency'] ) ) . ( $payment['paid_at'] ? '<br><small>' . esc_html( self::short_date( $payment['paid_at'] ) ) . '</small>' : '' ) . '</td><td><span class="cpb-status is-' . esc_attr( $payment['status'] ) . '">' . esc_html( self::payment_statuses()[ $payment['status'] ] ?? $payment['status'] ) . '</span></td></tr>';
		echo '</tbody></table></div><details class="cpb-add-panel"><summary><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>' . esc_html__( 'Add payment', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_project_payment"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '"><input type="hidden" name="currency" value="' . esc_attr( $currency ) . '">'; wp_nonce_field( 'contactpanda_business_save_project_payment_' . $project_id );
		echo '<div class="cpb-form-grid"><label><span>' . esc_html__( 'Payment title', 'contactpanda-business' ) . '</span><input name="title" required></label><label><span>' . esc_html__( 'Amount', 'contactpanda-business' ) . '</span><input type="number" min="0" step="0.01" name="amount" required></label><label><span>' . esc_html__( 'Due date', 'contactpanda-business' ) . '</span><input type="date" name="due_at"></label><label><span>' . esc_html__( 'Status', 'contactpanda-business' ) . '</span><select name="status">'; foreach ( self::payment_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Paid amount', 'contactpanda-business' ) . '</span><input type="number" min="0" step="0.01" name="paid_amount"></label><label><span>' . esc_html__( 'Paid date', 'contactpanda-business' ) . '</span><input type="date" name="paid_at"></label><label><span>' . esc_html__( 'Project part', 'contactpanda-business' ) . '</span><select name="area_id">' . ContactPanda_Business_Workspace::area_options( $project_id ) . '</select></label><label><span>' . esc_html__( 'Related project step', 'contactpanda-business' ) . '</span><select name="step_id"><option value="0">' . esc_html__( 'No project step', 'contactpanda-business' ) . '</option>'; foreach ( $steps as $step ) echo '<option value="' . esc_attr( $step['id'] ) . '">' . esc_html( $step['title'] ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Reference', 'contactpanda-business' ) . '</span><input name="reference"></label></div><label><span>' . esc_html__( 'Notes', 'contactpanda-business' ) . '</span><textarea name="notes" rows="3"></textarea></label><button class="button button-primary">' . esc_html__( 'Save payment', 'contactpanda-business' ) . '</button></form></details></section>';

		echo '<section class="cpb-card cpb-project-history-card"><div class="cpb-section-heading"><h2>' . esc_html__( 'Project history', 'contactpanda-business' ) . '</h2></div><ol class="cpb-activity-list cpb-project-history">';
		if ( ! $events ) echo '<li class="cpb-empty">' . esc_html__( 'No project activity has been recorded yet.', 'contactpanda-business' ) . '</li>';
		foreach ( $events as $event ) { $actor = $event['actor_user_id'] ? get_userdata( (int) $event['actor_user_id'] ) : null; echo '<li><span class="cpb-activity-avatar">' . esc_html( strtoupper( substr( $actor ? $actor->display_name : 'CP', 0, 1 ) ) ) . '</span><div><strong>' . esc_html( $actor ? $actor->display_name : 'ContactPanda' ) . '</strong> ' . esc_html( self::event_label( $event['event_type'], $event['details_json'] ) ) . '<time>' . esc_html( get_date_from_gmt( $event['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></div></li>'; }
		echo '</ol></section>';
		self::project_text_section( $project, 'notes', __( 'Notes and comments', 'contactpanda-business' ), __( 'Click to add notes or comments.', 'contactpanda-business' ), 'cpb-project-notes' );
		echo '</div></div>';
	}

	private static function new_task_modal( $project_id ) {
		echo '<div id="cpb-new-task" class="cpb-modal" hidden><div class="cpb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpb-task-title"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="cpb-task-title">' . esc_html__( 'Create task', 'contactpanda-business' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '"><input type="hidden" name="return_to_board" value="1">'; wp_nonce_field( 'contactpanda_business_save_task_' . $project_id );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="6"></textarea></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Area', 'contactpanda-business' ) . '</span><select name="area_id">' . ContactPanda_Business_Workspace::area_options( $project_id ) . '</select></label><label><span>' . esc_html__( 'Assignee', 'contactpanda-business' ) . '</span><select name="assigned_user_id">' . ContactPanda_Business_Workspace::assignee_options() . '</select></label><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Priority', 'contactpanda-business' ) . '</span><select name="priority">'; foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer">' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal">' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><p><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function inline_task_form( $project_id, $column ) {
		$form_id = 'cpb-inline-task-' . sanitize_html_class( $column );
		echo '<div class="cpb-inline-task-add"><button type="button" class="button-link cpb-inline-task-trigger" data-cpb-inline-task-open="' . esc_attr( $form_id ) . '"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add task', 'contactpanda-business' ) . '</button><form id="' . esc_attr( $form_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-inline-task-form" hidden><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '"><input type="hidden" name="board_column" value="' . esc_attr( $column ) . '"><input type="hidden" name="return_to_board" value="1">';
		wp_nonce_field( 'contactpanda_business_save_task_' . $project_id );
		echo '<label class="screen-reader-text" for="' . esc_attr( $form_id . '-title' ) . '">' . esc_html__( 'Task title', 'contactpanda-business' ) . '</label><textarea id="' . esc_attr( $form_id . '-title' ) . '" name="title" rows="3" placeholder="' . esc_attr__( 'Enter a task title …', 'contactpanda-business' ) . '" required></textarea><div><button class="button button-primary">' . esc_html__( 'Add task', 'contactpanda-business' ) . '</button><button type="button" class="button-link" data-cpb-inline-task-cancel>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></div></form></div>';
	}

	private static function task_card( $task ) {
		$progress = min( 100, absint( $task['progress'] ) );
		echo '<article class="cpb-task" draggable="true" tabindex="0" role="button" data-cpb-task="' . esc_attr( $task['id'] ) . '" data-cpb-area="' . esc_attr( absint( $task['area_id'] ?? 0 ) ) . '" data-cpb-assignee="' . esc_attr( absint( $task['assigned_user_id'] ?? 0 ) ) . '" data-cpb-task-card data-cpb-open="cpb-task-' . esc_attr( $task['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Open task: %s', 'contactpanda-business' ), $task['title'] ) ) . '">';
		echo '<span class="cpb-task-status cpb-badge is-' . esc_attr( $task['open_status'] ) . '">' . esc_html( self::open_statuses()[ $task['open_status']] ?? $task['open_status'] ) . '</span><header><strong>' . esc_html( $task['title'] ) . '</strong></header>';
		if ( 'internal' === $task['visibility'] ) echo '<div class="cpb-badges"><span class="cpb-badge is-internal">' . esc_html__( 'Internal', 'contactpanda-business' ) . '</span></div>';
		echo '<div class="cpb-progress is-p' . esc_attr( self::progress_band( $progress ) ) . '"><span style="width:' . esc_attr( $progress ) . '%"></span></div><div class="cpb-task-card-meta"><small>' . esc_html( $progress ) . '% · ' . esc_html( self::due_label( $task['due_at'] ) ) . '</small><span>';
		if ( ! empty( $task['attachment_count'] ) ) echo '<span title="' . esc_attr__( 'Files', 'contactpanda-business' ) . '"><span class="dashicons dashicons-paperclip" aria-hidden="true"></span>' . esc_html( absint( $task['attachment_count'] ) ) . '</span>';
		if ( ! empty( $task['comment_count'] ) ) echo '<span title="' . esc_attr__( 'Comments', 'contactpanda-business' ) . '"><span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>' . esc_html( absint( $task['comment_count'] ) ) . '</span>';
		if ( ! empty( $task['checklist_count'] ) ) echo '<span title="' . esc_attr__( 'Checklist', 'contactpanda-business' ) . '"><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html( absint( $task['checklist_done'] ) . '/' . absint( $task['checklist_count'] ) ) . '</span>';
		echo '</span>';
		ContactPanda_Business_Workspace::task_card_actions( $task['id'], $task['project_id'] );
		echo '</div></article>';
	}

	private static function task_detail_modal( $task ) {
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$task_id = absint( $task['id'] );
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT name,color FROM `{$t['projects']}` WHERE id=%d", $task['project_id'] ), ARRAY_A );
		$area = ! empty( $task['area_id'] ) ? $wpdb->get_var( $wpdb->prepare( "SELECT name FROM `{$t['areas']}` WHERE id=%d", $task['area_id'] ) ) : '';
		$assignee = ! empty( $task['assigned_user_id'] ) ? get_userdata( (int) $task['assigned_user_id'] ) : null;
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['comments']}` WHERE task_id=%d ORDER BY created_at DESC", $task_id ), ARRAY_A );
		$attachments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['attachments']}` WHERE task_id=%d ORDER BY created_at DESC", $task_id ), ARRAY_A );
		$checklist = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['checklist']}` WHERE task_id=%d ORDER BY sort_order,id", $task_id ), ARRAY_A );
		$events = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['events']}` WHERE task_id=%d ORDER BY created_at DESC LIMIT 20", $task_id ), ARRAY_A );
		$done = count( array_filter( $checklist, static function ( $item ) { return ! empty( $item['is_complete'] ); } ) );
		$project_color = self::clean_color( $project['color'] ?? '' );

		echo '<div id="cpb-task-' . esc_attr( $task_id ) . '" class="cpb-modal" hidden><div class="cpb-modal-dialog cpb-task-modal" role="dialog" aria-modal="true" aria-labelledby="cpb-task-modal-title-' . esc_attr( $task_id ) . '"><header class="cpb-task-modal-header"><div><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( $project_color ) . '"></span><strong>' . esc_html( $project['name'] ?? __( 'Project', 'contactpanda-business' ) ) . '</strong><span class="cpb-badge is-' . esc_attr( $task['board_column'] ) . '">' . esc_html( self::columns()[ $task['board_column'] ] ?? $task['board_column'] ) . '</span></div><div class="cpb-task-modal-actions">';
		ContactPanda_Business_Workspace::task_card_actions( $task_id, $task['project_id'] );
		echo '<button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button></div></header><div class="cpb-task-modal-grid"><main class="cpb-task-modal-main"><button type="button" class="cpb-inline-edit-title" data-cpb-inline-edit-open="cpb-task-editor-' . esc_attr( $task_id ) . '"><h2 id="cpb-task-modal-title-' . esc_attr( $task_id ) . '">' . esc_html( $task['title'] ) . '</h2></button><div class="cpb-task-meta" data-cpb-inline-edit-open="cpb-task-editor-' . esc_attr( $task_id ) . '" role="button" tabindex="0">';
		self::task_meta_item( __( 'Project', 'contactpanda-business' ), $project['name'] ?? '—', 'portfolio' );
		self::task_meta_item( __( 'Area', 'contactpanda-business' ), $area ?: __( 'No area', 'contactpanda-business' ), 'category' );
		self::task_meta_item( __( 'Assignee', 'contactpanda-business' ), $assignee ? $assignee->display_name : __( 'Unassigned', 'contactpanda-business' ), 'admin-users' );
		self::task_meta_item( __( 'Due by', 'contactpanda-business' ), self::due_label( $task['due_at'] ), 'calendar-alt' );
		self::task_meta_item( __( 'Priority', 'contactpanda-business' ), self::priorities()[ $task['priority'] ] ?? $task['priority'], 'flag' );
		self::task_meta_item( __( 'Visibility', 'contactpanda-business' ), self::visibilities()[ $task['visibility'] ] ?? $task['visibility'], 'visibility' );
		echo '</div><section class="cpb-task-section"><div class="cpb-task-section-heading"><h3><span class="dashicons dashicons-editor-alignleft" aria-hidden="true"></span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</h3></div><button type="button" class="cpb-task-description" data-cpb-inline-edit-open="cpb-task-editor-' . esc_attr( $task_id ) . '">' . ( $task['description'] ? nl2br( esc_html( $task['description'] ) ) : '<span class="cpb-empty">' . esc_html__( 'Click to add a description.', 'contactpanda-business' ) . '</span>' ) . '</button></section>';

		echo '<section class="cpb-task-section"><div class="cpb-task-section-heading"><h3><span class="dashicons dashicons-paperclip" aria-hidden="true"></span>' . esc_html__( 'Files', 'contactpanda-business' ) . '</h3><span>' . esc_html( count( $attachments ) ) . '</span></div><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-file-upload"><input type="hidden" name="action" value="contactpanda_business_upload_task_files"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">';
		wp_nonce_field( 'contactpanda_business_upload_task_files_' . $task_id );
		echo '<label><span class="button"><span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span> ' . esc_html__( 'Add files', 'contactpanda-business' ) . '</span><input type="file" name="task_files[]" multiple required data-cpb-task-files></label><span class="cpb-file-selection" data-cpb-file-selection>' . esc_html__( 'Select one or more files or drag them here.', 'contactpanda-business' ) . '</span><button class="button button-primary" disabled data-cpb-file-submit>' . esc_html__( 'Upload files', 'contactpanda-business' ) . '</button></form><div class="cpb-attachment-list">';
		if ( ! $attachments ) echo '<p class="cpb-empty">' . esc_html__( 'No files have been attached yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $attachments as $attachment ) self::task_attachment( $attachment, $task_id );
		echo '</div></section>';

		$total = count( $checklist );
		echo '<section class="cpb-task-section"><div class="cpb-task-section-heading"><h3><span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>' . esc_html__( 'Checklist', 'contactpanda-business' ) . '</h3><span>' . esc_html( sprintf( __( '%1$d of %2$d', 'contactpanda-business' ), $done, $total ) ) . '</span></div>';
		if ( $total ) echo '<div class="cpb-checklist-progress"><span style="width:' . esc_attr( (string) round( ( $done / $total ) * 100 ) ) . '%"></span></div>';
		if ( ! $checklist ) echo '<p class="cpb-empty">' . esc_html__( 'No checklist items have been added yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $checklist as $item ) self::checklist_item( $item, $task_id );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-checklist-add"><input type="hidden" name="action" value="contactpanda_business_add_checklist_item"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">';
		wp_nonce_field( 'contactpanda_business_add_checklist_item_' . $task_id );
		echo '<input name="title" placeholder="' . esc_attr__( 'Add checklist item …', 'contactpanda-business' ) . '" required><button class="button">' . esc_html__( 'Add', 'contactpanda-business' ) . '</button></form></section>';

		echo '<div id="cpb-task-editor-' . esc_attr( $task_id ) . '" class="cpb-task-edit-panel" hidden><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="project_id" value="' . esc_attr( $task['project_id'] ) . '"><input type="hidden" name="return_to_board" value="1"><input type="hidden" name="open_modal" value="1">';
		wp_nonce_field( 'contactpanda_business_save_task_' . $task['project_id'] );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" value="' . esc_attr( $task['title'] ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $task['description'] ) . '</textarea></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Area', 'contactpanda-business' ) . '</span><select name="area_id">' . ContactPanda_Business_Workspace::area_options( $task['project_id'], $task['area_id'] ?? 0 ) . '</select></label><label><span>' . esc_html__( 'Assignee', 'contactpanda-business' ) . '</span><select name="assigned_user_id">' . ContactPanda_Business_Workspace::assignee_options( $task['assigned_user_id'] ?? 0 ) . '</select></label><label><span>' . esc_html__( 'Column', 'contactpanda-business' ) . '</span><select name="board_column">';
		foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">';
		foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label><span>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</span><input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label><label><span>' . esc_html__( 'Priority', 'contactpanda-business' ) . '</span><select name="priority">';
		foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['priority'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at" value="' . esc_attr( $task['due_at'] ? gmdate( 'Y-m-d\TH:i', strtotime( $task['due_at'] ) ) : '' ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $task['visibility'], 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $task['visibility'], 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><p><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-inline-edit-cancel="cpb-task-editor-' . esc_attr( $task_id ) . '">' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></main>';

		echo '<aside class="cpb-task-activity"><h3><span class="dashicons dashicons-admin-comments" aria-hidden="true"></span>' . esc_html__( 'Comments & activity', 'contactpanda-business' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-comment-form"><input type="hidden" name="action" value="contactpanda_business_add_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="return_to_board" value="1">';
		wp_nonce_field( 'contactpanda_business_add_comment_' . $task_id );
		echo '<textarea rows="3" name="body" placeholder="' . esc_attr__( 'Write a comment …', 'contactpanda-business' ) . '" required></textarea><div><label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Internal comment', 'contactpanda-business' ) . '</label><button class="button button-primary">' . esc_html__( 'Comment', 'contactpanda-business' ) . '</button></div></form><div class="cpb-comment-list">';
		if ( ! $comments ) echo '<p class="cpb-empty">' . esc_html__( 'No comments yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $comments as $comment ) {
			$author = get_userdata( (int) $comment['author_user_id'] );
			echo '<article class="cpb-comment ' . ( 'internal' === $comment['visibility'] ? 'is-internal' : '' ) . '"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>';
		}
		echo '</div><h3 class="cpb-activity-heading"><span class="dashicons dashicons-chart-line" aria-hidden="true"></span>' . esc_html__( 'Activity', 'contactpanda-business' ) . '</h3><ol class="cpb-activity-list">';
		if ( ! $events ) echo '<li class="cpb-empty">' . esc_html__( 'No activity has been recorded yet.', 'contactpanda-business' ) . '</li>';
		foreach ( $events as $event ) {
			$actor = $event['actor_user_id'] ? get_userdata( (int) $event['actor_user_id'] ) : null;
			echo '<li><span class="cpb-activity-avatar">' . esc_html( strtoupper( substr( $actor ? $actor->display_name : 'CP', 0, 1 ) ) ) . '</span><div><strong>' . esc_html( $actor ? $actor->display_name : 'ContactPanda' ) . '</strong> ' . esc_html( self::event_label( $event['event_type'], $event['details_json'] ) ) . '<time>' . esc_html( get_date_from_gmt( $event['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></div></li>';
		}
		echo '</ol></aside></div><footer class="cpb-task-modal-footer"><button type="button" class="button" data-cpb-close>' . esc_html__( 'Close', 'contactpanda-business' ) . '</button></footer></div></div>';
	}

	private static function task_meta_item( $label, $value, $icon ) {
		echo '<div class="cpb-task-meta-item"><span class="dashicons dashicons-' . esc_attr( $icon ) . '" aria-hidden="true"></span><span><small>' . esc_html( $label ) . '</small><strong>' . esc_html( $value ) . '</strong></span></div>';
	}

	private static function summary_item( $label, $value ) {
		echo '<div><small>' . esc_html( $label ) . '</small><strong>' . esc_html( $value ?: '—' ) . '</strong></div>';
	}

	private static function project_field_card( $project, $field, $label, $display, $type ) {
		$editor_id = 'cpb-project-field-' . absint( $project['id'] ) . '-' . sanitize_html_class( $field );
		echo '<div class="cpb-project-field"><button type="button" class="cpb-project-field-value" data-cpb-inline-edit-open="' . esc_attr( $editor_id ) . '"><small>' . esc_html( $label ) . '</small><strong>' . esc_html( $display ?: '—' ) . '</strong></button>';
		self::project_field_form( absint( $project['id'] ), $field, $project[ $field ] ?? '', $type, $editor_id, $project['currency'] ?? 'EUR' );
		echo '</div>';
	}

	private static function project_text_section( $project, $field, $label, $empty_text, $class_name ) {
		echo '<section class="cpb-card ' . esc_attr( $class_name ) . '"><div class="cpb-section-heading"><h2>' . esc_html( $label ) . '</h2></div>';
		self::project_inline_text( $project, $field, $label, $empty_text );
		echo '</section>';
	}

	private static function project_inline_text( $project, $field, $label, $empty_text ) {
		$editor_id = 'cpb-project-field-' . absint( $project['id'] ) . '-' . sanitize_html_class( $field );
		echo '<button type="button" class="cpb-project-text-value" data-cpb-inline-edit-open="' . esc_attr( $editor_id ) . '">' . nl2br( esc_html( $project[ $field ] ?? '' ) ) . ( empty( $project[ $field ] ) ? '<span class="cpb-empty">' . esc_html( $empty_text ) . '</span>' : '' ) . '</button>';
		self::project_field_form( absint( $project['id'] ), $field, $project[ $field ] ?? '', 'textarea', $editor_id );
	}

	private static function project_field_form( $project_id, $field, $value, $type, $editor_id, $currency = 'EUR' ) {
		echo '<form id="' . esc_attr( $editor_id ) . '" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-project-field-editor" hidden><input type="hidden" name="action" value="contactpanda_business_save_project_field"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '"><input type="hidden" name="field" value="' . esc_attr( $field ) . '">';
		wp_nonce_field( 'contactpanda_business_save_project_field_' . $project_id . '_' . $field );
		if ( 'textarea' === $type ) {
			echo '<textarea name="value" rows="6" aria-label="' . esc_attr__( 'Edit value', 'contactpanda-business' ) . '">' . esc_textarea( $value ) . '</textarea>';
		} elseif ( 'status' === $type ) {
			echo '<select name="value" aria-label="' . esc_attr__( 'Edit value', 'contactpanda-business' ) . '">';
			foreach ( self::project_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $value, $key, false ) . '>' . esc_html( $label ) . '</option>';
			echo '</select>';
		} elseif ( 'money' === $type ) {
			echo '<div class="cpb-project-money-editor"><input type="number" min="0" step="0.01" name="value" value="' . esc_attr( $value ) . '"><input name="currency" maxlength="3" value="' . esc_attr( self::clean_currency( $currency ) ) . '" aria-label="' . esc_attr__( 'Currency', 'contactpanda-business' ) . '"></div>';
		} else {
			$input_type = in_array( $type, array( 'date', 'number', 'text' ), true ) ? $type : 'text';
			$extra = 'number' === $input_type ? ' min="0" max="100"' : '';
			echo '<input type="' . esc_attr( $input_type ) . '" name="value" value="' . esc_attr( $value ) . '"' . $extra . ' aria-label="' . esc_attr__( 'Edit value', 'contactpanda-business' ) . '">';
		}
		echo '<div class="cpb-project-field-actions"><button class="button button-primary">' . esc_html__( 'Save', 'contactpanda-business' ) . '</button><button type="button" class="button" data-cpb-inline-edit-cancel="' . esc_attr( $editor_id ) . '">' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></div></form>';
	}

	private static function project_document( $document ) {
		$attachment_id = absint( $document['attachment_id'] ); $url = wp_get_attachment_url( $attachment_id ); if ( ! $url ) return;
		$title = $document['title'] ?: get_the_title( $attachment_id );
		echo '<article class="cpb-project-document"><span class="dashicons dashicons-media-document" aria-hidden="true"></span><div><strong>' . esc_html( $title ) . '</strong><small>' . esc_html( self::document_types()[ $document['document_type'] ] ?? $document['document_type'] ) . ( $document['document_date'] ? ' · ' . esc_html( self::short_date( $document['document_date'] ) ) : '' ) . ( $document['step_title'] ? ' · ' . esc_html( $document['step_title'] ) : '' ) . '</small>' . ( $document['notes'] ? '<p>' . esc_html( $document['notes'] ) . '</p>' : '' ) . '</div><a class="button cpb-icon-button" href="' . esc_url( $url ) . '" download aria-label="' . esc_attr__( 'Download file', 'contactpanda-business' ) . '"><span class="dashicons dashicons-download" aria-hidden="true"></span></a><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-inline-form" data-cpb-confirm="' . esc_attr__( 'Remove this document from the project? The file remains available in the Media Library.', 'contactpanda-business' ) . '"><input type="hidden" name="action" value="contactpanda_business_remove_project_document"><input type="hidden" name="document_id" value="' . esc_attr( $document['id'] ) . '">';
		wp_nonce_field( 'contactpanda_business_remove_project_document_' . absint( $document['id'] ) );
		echo '<button class="button cpb-icon-button" aria-label="' . esc_attr__( 'Remove document from project', 'contactpanda-business' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></form></article>';
	}

	private static function task_attachment( $attachment, $task_id ) {
		$attachment_id = absint( $attachment['attachment_id'] );
		$url = wp_get_attachment_url( $attachment_id );
		if ( ! $url ) return;
		$file = get_attached_file( $attachment_id );
		$title = get_the_title( $attachment_id ) ?: basename( $file ?: $url );
		$size = $file && file_exists( $file ) ? size_format( filesize( $file ) ) : '';
		echo '<article class="cpb-attachment"><span class="dashicons dashicons-media-default" aria-hidden="true"></span><div><strong>' . esc_html( $title ) . '</strong>' . ( $size ? '<small>' . esc_html( $size ) . '</small>' : '' ) . '</div><a class="button cpb-icon-button" href="' . esc_url( $url ) . '" download aria-label="' . esc_attr__( 'Download file', 'contactpanda-business' ) . '"><span class="dashicons dashicons-download" aria-hidden="true"></span></a><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-inline-form" data-cpb-confirm="' . esc_attr__( 'Remove this file from the task? The file remains available in the Media Library.', 'contactpanda-business' ) . '"><input type="hidden" name="action" value="contactpanda_business_remove_task_file"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="attachment_row_id" value="' . esc_attr( $attachment['id'] ) . '">';
		wp_nonce_field( 'contactpanda_business_remove_task_file_' . absint( $attachment['id'] ) );
		echo '<button class="button cpb-icon-button" aria-label="' . esc_attr__( 'Remove file from task', 'contactpanda-business' ) . '"><span class="dashicons dashicons-trash" aria-hidden="true"></span></button></form></article>';
	}

	private static function checklist_item( $item, $task_id ) {
		$item_id = absint( $item['id'] );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-checklist-item' . ( ! empty( $item['is_complete'] ) ? ' is-complete' : '' ) . '"><input type="hidden" name="action" value="contactpanda_business_toggle_checklist_item"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="item_id" value="' . esc_attr( $item_id ) . '"><input type="hidden" name="is_complete" value="' . esc_attr( empty( $item['is_complete'] ) ? 1 : 0 ) . '">';
		wp_nonce_field( 'contactpanda_business_toggle_checklist_item_' . $item_id );
		echo '<button class="cpb-checklist-toggle" aria-label="' . esc_attr( empty( $item['is_complete'] ) ? __( 'Mark checklist item complete', 'contactpanda-business' ) : __( 'Mark checklist item incomplete', 'contactpanda-business' ) ) . '"><span class="dashicons dashicons-' . ( ! empty( $item['is_complete'] ) ? 'yes-alt' : 'marker' ) . '" aria-hidden="true"></span><span>' . esc_html( $item['title'] ) . '</span></button></form>';
	}

	private static function event_label( $type, $details_json ) {
		$details = json_decode( (string) $details_json, true );
		$details = is_array( $details ) ? $details : array();
		$labels = array(
			'task_created'       => __( 'created the task.', 'contactpanda-business' ),
			'task_saved'         => __( 'updated the task.', 'contactpanda-business' ),
			'task_moved'         => __( 'moved the task.', 'contactpanda-business' ),
			'comment_added'      => __( 'added a comment.', 'contactpanda-business' ),
			'attachment_added'   => __( 'added files.', 'contactpanda-business' ),
			'attachment_removed' => __( 'removed a file from the task.', 'contactpanda-business' ),
			'checklist_added'    => __( 'added a checklist item.', 'contactpanda-business' ),
			'checklist_toggled'  => __( 'updated the checklist.', 'contactpanda-business' ),
			'project_created' => __( 'created the project.', 'contactpanda-business' ),
			'project_updated' => __( 'updated the project information.', 'contactpanda-business' ),
			'project_step_added' => __( 'added a project step.', 'contactpanda-business' ),
			'project_payment_added' => __( 'added a payment to the plan.', 'contactpanda-business' ),
			'project_documents_added' => __( 'added project documents.', 'contactpanda-business' ),
			'project_document_removed' => __( 'removed a document from the project.', 'contactpanda-business' ),
		);
		if ( 'task_moved' === $type && ! empty( $details['column'] ) ) return sprintf( __( 'moved the task to “%s”.', 'contactpanda-business' ), self::columns()[ $details['column'] ] ?? $details['column'] );
		return $labels[ $type ] ?? __( 'updated the task.', 'contactpanda-business' );
	}

	private static function render_task( $task_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.*,p.name project_name FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'This task does not exist.', 'contactpanda-business' ) . '</p></div>'; return; }
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['comments']}` WHERE task_id=%d ORDER BY created_at", $task_id ), ARRAY_A );
		$back = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $task['project_id'] ), admin_url( 'admin.php' ) );
		echo '<p><a href="' . esc_url( $back ) . '">← ' . esc_html__( 'Back to board', 'contactpanda-business' ) . '</a></p><div class="cpb-task-detail"><main class="cpb-card"><h2>' . esc_html( $task['title'] ) . '</h2><p>' . nl2br( esc_html( $task['description'] ) ) . '</p><h3>' . esc_html__( 'Comments', 'contactpanda-business' ) . '</h3>';
		if ( ! $comments ) echo '<p class="cpb-empty">' . esc_html__( 'No comments yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $comments as $comment ) { $author = get_userdata( (int) $comment['author_user_id'] ); echo '<article class="cpb-comment ' . ( 'internal' === $comment['visibility'] ? 'is-internal' : '' ) . '"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_add_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_add_comment_' . $task_id ); echo '<textarea class="widefat" rows="5" name="body" required></textarea><p><label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Internal comment', 'contactpanda-business' ) . '</label></p><button class="button button-primary">' . esc_html__( 'Add comment', 'contactpanda-business' ) . '</button></form></main><aside class="cpb-card"><h3>' . esc_html__( 'Task details', 'contactpanda-business' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="project_id" value="' . esc_attr( $task['project_id'] ) . '">'; wp_nonce_field( 'contactpanda_business_save_task_' . $task['project_id'] );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" value="' . esc_attr( $task['title'] ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $task['description'] ) . '</textarea></label><label><span>' . esc_html__( 'Area', 'contactpanda-business' ) . '</span><select name="area_id">' . ContactPanda_Business_Workspace::area_options( $task['project_id'], $task['area_id'] ?? 0 ) . '</select></label><label><span>' . esc_html__( 'Assignee', 'contactpanda-business' ) . '</span><select name="assigned_user_id">' . ContactPanda_Business_Workspace::assignee_options( $task['assigned_user_id'] ?? 0 ) . '</select></label><label><span>' . esc_html__( 'Column', 'contactpanda-business' ) . '</span><select name="board_column">'; foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</span><input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label><label><span>' . esc_html__( 'Priority', 'contactpanda-business' ) . '</span><select name="priority">'; foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['priority'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at" value="' . esc_attr( $task['due_at'] ? gmdate( 'Y-m-d\TH:i', strtotime( $task['due_at'] ) ) : '' ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $task['visibility'], 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $task['visibility'], 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button></form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Move this task to the trash?', 'contactpanda-business' ) ) ) . ');"><input type="hidden" name="action" value="contactpanda_business_trash_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_trash_task_' . $task_id ); echo '<p><button class="button cpb-danger">' . esc_html__( 'Move to trash', 'contactpanda-business' ) . '</button></p></form></aside></div>';
	}

	public static function save_project_step() {
		self::guard();
		$project_id = absint( $_POST['project_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_save_project_step_' . $project_id );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( ! $project_id || '' === $title ) self::redirect( false, __( 'Please enter a project step title.', 'contactpanda-business' ), $project_id );
		if ( ! self::project_exists( $project_id ) ) self::redirect( false, __( 'The project does not exist.', 'contactpanda-business' ) );
		$type = sanitize_key( wp_unslash( $_POST['step_type'] ?? 'phase' ) ); if ( ! isset( self::step_types()[ $type ] ) ) $type = 'phase';
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'planned' ) ); if ( ! isset( self::step_statuses()[ $status ] ) ) $status = 'planned';
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true );
		$inserted = $wpdb->insert( $t['project_steps'], array( 'project_id' => $project_id, 'area_id' => self::project_area_id( $project_id, $_POST['area_id'] ?? 0 ), 'title' => $title, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'step_type' => $type, 'status' => $status, 'due_at' => self::clean_date( $_POST['due_at'] ?? '' ), 'completed_at' => 'completed' === $status ? $now : null, 'amount' => self::decimal( $_POST['amount'] ?? 0 ), 'currency' => self::clean_currency( $_POST['currency'] ?? 'EUR' ), 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) );
		if ( $inserted ) self::event( $project_id, 0, 'project_step_added', array( 'title' => $title, 'type' => $type ) );
		self::redirect( (bool) $inserted, $inserted ? __( 'The project step was added.', 'contactpanda-business' ) : __( 'The project step could not be added.', 'contactpanda-business' ), $project_id );
	}

	public static function save_project_payment() {
		self::guard();
		$project_id = absint( $_POST['project_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_save_project_payment_' . $project_id );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		if ( ! $project_id || '' === $title ) self::redirect( false, __( 'Please enter a payment title.', 'contactpanda-business' ), $project_id );
		if ( ! self::project_exists( $project_id ) ) self::redirect( false, __( 'The project does not exist.', 'contactpanda-business' ) );
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'planned' ) ); if ( ! isset( self::payment_statuses()[ $status ] ) ) $status = 'planned';
		$paid_at = self::clean_date( $_POST['paid_at'] ?? '' ); if ( $paid_at ) $paid_at .= ' 00:00:00';
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true );
		$inserted = $wpdb->insert( $t['payments'], array( 'project_id' => $project_id, 'area_id' => self::project_area_id( $project_id, $_POST['area_id'] ?? 0 ), 'step_id' => self::project_step_id( $project_id, $_POST['step_id'] ?? 0 ), 'title' => $title, 'amount' => self::decimal( $_POST['amount'] ?? 0 ), 'paid_amount' => self::decimal( $_POST['paid_amount'] ?? 0 ), 'currency' => self::clean_currency( $_POST['currency'] ?? 'EUR' ), 'due_at' => self::clean_date( $_POST['due_at'] ?? '' ), 'paid_at' => $paid_at, 'status' => $status, 'reference' => sanitize_text_field( wp_unslash( $_POST['reference'] ?? '' ) ), 'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ), 'created_at' => $now, 'updated_at' => $now ) );
		if ( $inserted ) self::event( $project_id, 0, 'project_payment_added', array( 'title' => $title, 'amount' => self::decimal( $_POST['amount'] ?? 0 ) ) );
		self::redirect( (bool) $inserted, $inserted ? __( 'The payment was added.', 'contactpanda-business' ) : __( 'The payment could not be added.', 'contactpanda-business' ), $project_id );
	}

	public static function upload_project_documents() {
		self::guard();
		$project_id = absint( $_POST['project_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_upload_project_documents_' . $project_id );
		if ( ! self::project_exists( $project_id ) ) self::redirect( false, __( 'The project does not exist.', 'contactpanda-business' ) );
		$files = $_FILES['project_files'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress validates each media upload.
		if ( ! $project_id || empty( $files['name'] ) || ! is_array( $files['name'] ) ) self::redirect( false, __( 'Select at least one project document.', 'contactpanda-business' ), $project_id );
		require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $added = 0; $errors = 0; $type = sanitize_key( wp_unslash( $_POST['document_type'] ?? 'other' ) ); if ( ! isset( self::document_types()[ $type ] ) ) $type = 'other';
		$limit = min( 20, count( $files['name'] ) );
		for ( $index = 0; $index < $limit; $index++ ) {
			if ( UPLOAD_ERR_NO_FILE === (int) ( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ) ) continue;
			$_FILES['contactpanda_business_project_file'] = array( 'name' => $files['name'][ $index ] ?? '', 'type' => $files['type'][ $index ] ?? '', 'tmp_name' => $files['tmp_name'][ $index ] ?? '', 'error' => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE, 'size' => $files['size'][ $index ] ?? 0 );
			$attachment_id = media_handle_upload( 'contactpanda_business_project_file', 0 );
			if ( is_wp_error( $attachment_id ) ) { $errors++; continue; }
			$ok = $wpdb->insert( $t['project_documents'], array( 'project_id' => $project_id, 'step_id' => self::project_step_id( $project_id, $_POST['step_id'] ?? 0 ), 'attachment_id' => absint( $attachment_id ), 'document_type' => $type, 'title' => sanitize_text_field( $files['name'][ $index ] ?? '' ), 'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ), 'document_date' => self::clean_date( $_POST['document_date'] ?? '' ), 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'internal' ), 'uploaded_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
			$ok ? $added++ : $errors++;
		}
		unset( $_FILES['contactpanda_business_project_file'] );
		if ( $added ) self::event( $project_id, 0, 'project_documents_added', array( 'count' => $added, 'type' => $type ) );
		$message = sprintf( __( '%d project documents were added.', 'contactpanda-business' ), $added ); if ( $errors ) $message .= ' ' . sprintf( __( '%d files could not be uploaded.', 'contactpanda-business' ), $errors );
		self::redirect( $added > 0, $message, $project_id );
	}

	public static function remove_project_document() {
		self::guard();
		$row_id = absint( $_POST['document_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_remove_project_document_' . $row_id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['project_documents']}` WHERE id=%d AND deleted_at IS NULL", $row_id ) );
		if ( ! $project_id ) self::redirect( false, __( 'The project document does not exist.', 'contactpanda-business' ) );
		$updated = $wpdb->update( $t['project_documents'], array( 'deleted_at' => current_time( 'mysql', true ) ), array( 'id' => $row_id ) );
		if ( $updated ) self::event( $project_id, 0, 'project_document_removed', array( 'document_id' => $row_id ) );
		self::redirect( false !== $updated, false !== $updated ? __( 'The document was removed from the project. The media file was retained.', 'contactpanda-business' ) : __( 'The project document could not be removed.', 'contactpanda-business' ), $project_id );
	}

	public static function save_project() {
		self::guard(); check_admin_referer( 'contactpanda_business_save_project' );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); if ( '' === $name ) self::redirect( false, __( 'Please enter a project name.', 'contactpanda-business' ) );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true );
		$project_id = absint( $_POST['project_id'] ?? 0 );
		$selected = self::posted_participants();
		$status = sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ); if ( ! isset( self::project_statuses()[ $status ] ) ) $status = 'active';
		$data = array( 'contact_id' => self::first_participant_id( $selected, 'person' ), 'company_id' => self::first_participant_id( $selected, 'company' ), 'customer_user_id' => self::first_participant_id( $selected, 'user' ), 'name' => $name, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'color' => self::clean_color( $_POST['color'] ?? '' ), 'status' => $status, 'progress' => min( 100, absint( $_POST['progress'] ?? 0 ) ), 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'customer' ), 'start_date' => self::clean_date( $_POST['start_date'] ?? '' ), 'target_end_date' => self::clean_date( $_POST['target_end_date'] ?? '' ), 'budget_total' => self::decimal( $_POST['budget_total'] ?? 0 ), 'currency' => self::clean_currency( $_POST['currency'] ?? 'EUR' ), 'financing_notes' => sanitize_textarea_field( wp_unslash( $_POST['financing_notes'] ?? '' ) ), 'notes' => sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) ), 'updated_at' => $now );
		if ( $project_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ) );
			if ( ! $exists || false === $wpdb->update( $t['projects'], $data, array( 'id' => $project_id ) ) ) self::redirect( false, __( 'The project could not be updated.', 'contactpanda-business' ), $project_id );
			self::save_participants( $project_id, $selected, $now, true );
			self::event( $project_id, 0, 'project_updated', array( 'name' => $name, 'participant_count' => count( $selected ) ) ); self::redirect( true, __( 'The project was updated.', 'contactpanda-business' ), $project_id );
		}
		$data += array( 'uuid' => wp_generate_uuid4(), 'created_by' => get_current_user_id(), 'created_at' => $now );
		$wpdb->insert( $t['projects'], $data ); $project_id = (int) $wpdb->insert_id;
		if ( ! $project_id ) self::redirect( false, __( 'The project could not be created.', 'contactpanda-business' ) );
		self::save_participants( $project_id, $selected, $now ); self::event( $project_id, 0, 'project_created', array( 'name' => $name, 'participant_count' => count( $selected ) ) ); self::redirect( true, __( 'The project was created.', 'contactpanda-business' ), $project_id );
	}

	public static function save_project_field() {
		self::guard();
		$project_id = absint( $_POST['project_id'] ?? 0 );
		$field = sanitize_key( wp_unslash( $_POST['field'] ?? '' ) );
		check_admin_referer( 'contactpanda_business_save_project_field_' . $project_id . '_' . $field );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		if ( ! $project_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ) ) ) self::redirect( false, __( 'The project does not exist.', 'contactpanda-business' ), $project_id );
		$raw = wp_unslash( $_POST['value'] ?? '' );
		$data = array();
		switch ( $field ) {
			case 'name':
				$value = sanitize_text_field( $raw );
				if ( '' === $value ) self::redirect( false, __( 'Please enter a project name.', 'contactpanda-business' ), $project_id );
				$data['name'] = $value;
				break;
			case 'description':
			case 'financing_notes':
			case 'notes':
				$data[ $field ] = sanitize_textarea_field( $raw );
				break;
			case 'status':
				$value = sanitize_key( $raw );
				if ( ! isset( self::project_statuses()[ $value ] ) ) self::redirect( false, __( 'The selected project status is invalid.', 'contactpanda-business' ), $project_id );
				$data['status'] = $value;
				break;
			case 'progress':
				$data['progress'] = min( 100, absint( $raw ) );
				break;
			case 'start_date':
			case 'target_end_date':
				$data[ $field ] = self::clean_date( $raw );
				break;
			case 'budget_total':
				$data['budget_total'] = self::decimal( $raw );
				$data['currency'] = self::clean_currency( $_POST['currency'] ?? 'EUR' );
				break;
			default:
				self::redirect( false, __( 'This project field cannot be edited here.', 'contactpanda-business' ), $project_id );
		}
		$data['updated_at'] = current_time( 'mysql', true );
		$updated = $wpdb->update( $t['projects'], $data, array( 'id' => $project_id ) );
		if ( false !== $updated ) self::event( $project_id, 0, 'project_updated', array( 'field' => $field ) );
		self::redirect( false !== $updated, false !== $updated ? __( 'The project field was saved.', 'contactpanda-business' ) : __( 'The project field could not be saved.', 'contactpanda-business' ), $project_id );
	}

	public static function save_task() {
		self::guard(); $project_id = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_save_task_' . $project_id );
		$task_id = absint( $_POST['task_id'] ?? 0 ); $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); if ( ! $project_id || '' === $title ) self::redirect( false, __( 'Please enter a task title.', 'contactpanda-business' ), $project_id );
		$column = sanitize_key( wp_unslash( $_POST['board_column'] ?? 'open' ) ); if ( ! isset( self::columns()[ $column ] ) ) $column = 'open';
		$open = sanitize_key( wp_unslash( $_POST['open_status'] ?? 'new' ) ); if ( ! isset( self::open_statuses()[ $open ] ) ) $open = 'new';
		$due = sanitize_text_field( wp_unslash( $_POST['due_at'] ?? '' ) ); $due = $due ? gmdate( 'Y-m-d H:i:s', strtotime( $due ) ) : null;
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$area_id = absint( $_POST['area_id'] ?? 0 );
		if ( $area_id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['areas']}` WHERE id=%d AND project_id=%d AND deleted_at IS NULL", $area_id, $project_id ) ) ) $area_id = 0;
		$data = array( 'project_id' => $project_id, 'area_id' => $area_id, 'assigned_user_id' => absint( $_POST['assigned_user_id'] ?? 0 ), 'title' => $title, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'board_column' => $column, 'open_status' => $open, 'progress' => min( 100, absint( $_POST['progress'] ?? 0 ) ), 'priority' => self::clean_priority( $_POST['priority'] ?? 'normal' ), 'due_at' => $due, 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'customer' ), 'updated_at' => current_time( 'mysql', true ), 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null );
		$is_new = ! $task_id;
		if ( $task_id ) { $data['revision'] = (int) $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM `{$t['tasks']}` WHERE id=%d", $task_id ) ) + 1; $wpdb->update( $t['tasks'], $data, array( 'id' => $task_id, 'project_id' => $project_id ) ); } else { $data['uuid'] = wp_generate_uuid4(); $data['created_by'] = get_current_user_id(); $data['created_at'] = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], $data ); $task_id = (int) $wpdb->insert_id; }
		self::event( $project_id, $task_id, $is_new ? 'task_created' : 'task_saved', array( 'column' => $column, 'progress' => $data['progress'] ) ); self::redirect( true, __( 'The task was saved.', 'contactpanda-business' ), $project_id, empty( $_POST['return_to_board'] ) ? $task_id : 0, ! empty( $_POST['open_modal'] ) ? $task_id : 0 );
	}

	public static function add_comment() {
		self::guard(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_add_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); if ( ! $task_id || '' === $body ) self::redirect( false, __( 'Please enter a comment.', 'contactpanda-business' ) );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d", $task_id ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => ! empty( $_POST['internal'] ) ? 'internal' : 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( $project_id, $task_id, 'comment_added', array() ); self::redirect( true, __( 'The comment was added.', 'contactpanda-business' ), $project_id, empty( $_POST['return_to_board'] ) ? $task_id : 0, ! empty( $_POST['return_to_board'] ) ? $task_id : 0 );
	}

	public static function upload_task_files() {
		self::guard();
		$task_id = absint( $_POST['task_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_upload_task_files_' . $task_id );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT project_id,visibility FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task ) self::redirect( false, __( 'The task does not exist.', 'contactpanda-business' ) );
		$files = $_FILES['task_files'] ?? array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- WordPress media handling validates each upload.
		if ( empty( $files['name'] ) || ! is_array( $files['name'] ) ) self::redirect( false, __( 'Select at least one file.', 'contactpanda-business' ), $task['project_id'] );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$added = 0;
		$errors = 0;
		$limit = min( 20, count( $files['name'] ) );
		for ( $index = 0; $index < $limit; $index++ ) {
			if ( UPLOAD_ERR_NO_FILE === (int) ( $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE ) ) continue;
			$_FILES['contactpanda_business_task_file'] = array(
				'name'     => $files['name'][ $index ] ?? '',
				'type'     => $files['type'][ $index ] ?? '',
				'tmp_name' => $files['tmp_name'][ $index ] ?? '',
				'error'    => $files['error'][ $index ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $files['size'][ $index ] ?? 0,
			);
			$attachment_id = media_handle_upload( 'contactpanda_business_task_file', 0 );
			if ( is_wp_error( $attachment_id ) ) { $errors++; continue; }
			$inserted = $wpdb->insert( $t['attachments'], array( 'task_id' => $task_id, 'attachment_id' => absint( $attachment_id ), 'visibility' => self::clean_visibility( $task['visibility'] ), 'uploaded_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
			if ( $inserted ) $added++; else $errors++;
		}
		unset( $_FILES['contactpanda_business_task_file'] );
		if ( $added ) self::event( (int) $task['project_id'], $task_id, 'attachment_added', array( 'count' => $added ) );
		$message = 1 === $added ? __( 'One file was added.', 'contactpanda-business' ) : sprintf( __( '%d files were added.', 'contactpanda-business' ), $added );
		if ( $errors ) $message .= ' ' . ( 1 === $errors ? __( 'One file could not be uploaded.', 'contactpanda-business' ) : sprintf( __( '%d files could not be uploaded.', 'contactpanda-business' ), $errors ) );
		self::redirect( $added > 0, $message, $task['project_id'], 0, $task_id );
	}

	public static function remove_task_file() {
		self::guard();
		$task_id = absint( $_POST['task_id'] ?? 0 );
		$row_id = absint( $_POST['attachment_row_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_remove_task_file_' . $row_id );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ) );
		if ( ! $project_id ) self::redirect( false, __( 'The task does not exist.', 'contactpanda-business' ) );
		$deleted = $wpdb->delete( $t['attachments'], array( 'id' => $row_id, 'task_id' => $task_id ) );
		if ( $deleted ) self::event( $project_id, $task_id, 'attachment_removed', array( 'attachment_row_id' => $row_id ) );
		self::redirect( (bool) $deleted, $deleted ? __( 'The file was removed from the task. It remains in the Media Library.', 'contactpanda-business' ) : __( 'The file link could not be removed.', 'contactpanda-business' ), $project_id, 0, $task_id );
	}

	public static function add_checklist_item() {
		self::guard();
		$task_id = absint( $_POST['task_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_add_checklist_item_' . $task_id );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ) );
		if ( ! $project_id || '' === $title ) self::redirect( false, __( 'Enter a checklist item.', 'contactpanda-business' ), $project_id );
		$sort_order = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(MAX(sort_order),0)+1 FROM `{$t['checklist']}` WHERE task_id=%d", $task_id ) );
		$inserted = $wpdb->insert( $t['checklist'], array( 'task_id' => $task_id, 'title' => $title, 'sort_order' => $sort_order, 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
		if ( $inserted ) self::event( $project_id, $task_id, 'checklist_added', array( 'item_id' => (int) $wpdb->insert_id ) );
		self::redirect( (bool) $inserted, $inserted ? __( 'The checklist item was added.', 'contactpanda-business' ) : __( 'The checklist item could not be added.', 'contactpanda-business' ), $project_id, 0, $task_id );
	}

	public static function toggle_checklist_item() {
		self::guard();
		$task_id = absint( $_POST['task_id'] ?? 0 );
		$item_id = absint( $_POST['item_id'] ?? 0 );
		check_admin_referer( 'contactpanda_business_toggle_checklist_item_' . $item_id );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ) );
		$complete = empty( $_POST['is_complete'] ) ? 0 : 1;
		$updated = $project_id ? $wpdb->update( $t['checklist'], array( 'is_complete' => $complete, 'completed_at' => $complete ? current_time( 'mysql', true ) : null ), array( 'id' => $item_id, 'task_id' => $task_id ) ) : false;
		if ( false !== $updated && $project_id ) self::event( $project_id, $task_id, 'checklist_toggled', array( 'item_id' => $item_id, 'complete' => $complete ) );
		self::redirect( false !== $updated, false !== $updated ? __( 'The checklist was updated.', 'contactpanda-business' ) : __( 'The checklist could not be updated.', 'contactpanda-business' ), $project_id, 0, $task_id );
	}

	public static function trash_task() {
		self::guard(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_trash_task_' . $task_id ); global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d", $task_id ) ); $wpdb->update( $t['tasks'], array( 'deleted_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) ); self::event( $project_id, $task_id, 'task_trashed', array() ); self::redirect( true, __( 'The task was moved to the trash.', 'contactpanda-business' ), $project_id );
	}

	public static function move_task() {
		self::guard(); check_ajax_referer( 'contactpanda_business_move_task', 'nonce' ); $task_id = absint( $_POST['task_id'] ?? 0 ); $column = sanitize_key( wp_unslash( $_POST['column'] ?? '' ) ); if ( ! $task_id || ! isset( self::columns()[ $column ] ) ) wp_send_json_error( array( 'message' => __( 'Invalid task movement.', 'contactpanda-business' ) ), 400 );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task = $wpdb->get_row( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ), ARRAY_A ); if ( ! $task ) wp_send_json_error( array( 'message' => __( 'Task not found.', 'contactpanda-business' ) ), 404 );
		$updated = $wpdb->update( $t['tasks'], array( 'board_column' => $column, 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) );
		if ( false === $updated ) wp_send_json_error( array( 'message' => __( 'The task could not be moved.', 'contactpanda-business' ) ), 500 );
		self::event( (int) $task['project_id'], $task_id, 'task_moved', array( 'column' => $column ) ); wp_send_json_success();
	}

	public static function support_ticket_box( $ticket ) {
		if ( ! current_user_can( 'manage_options' ) ) return; global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $projects = $wpdb->get_results( "SELECT id,name FROM `{$t['projects']}` WHERE deleted_at IS NULL AND status='active' ORDER BY name", ARRAY_A );
		echo '<section class="contactpanda-card"><h2>' . esc_html__( 'Business task', 'contactpanda-business' ) . '</h2>';
		if ( ! $projects ) { echo '<p>' . esc_html__( 'Create a Business project before converting this support request into a task.', 'contactpanda-business' ) . '</p></section>'; return; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_from_support"><input type="hidden" name="ticket_id" value="' . esc_attr( $ticket['id'] ) . '">'; wp_nonce_field( 'contactpanda_business_from_support_' . $ticket['id'] ); echo '<label><span>' . esc_html__( 'Project', 'contactpanda-business' ) . '</span><select name="project_id">'; foreach ( $projects as $project ) echo '<option value="' . esc_attr( $project['id'] ) . '">' . esc_html( $project['name'] ) . '</option>'; echo '</select></label><p><button class="button">' . esc_html__( 'Create task from request', 'contactpanda-business' ) . '</button></p></form></section>';
	}

	public static function from_support() {
		self::guard(); $ticket_id = absint( $_POST['ticket_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_from_support_' . $ticket_id ); $project_id = absint( $_POST['project_id'] ?? 0 ); if ( ! $ticket_id || ! $project_id || ! class_exists( 'ContactPanda_Pro_Schema' ) ) self::redirect( false, __( 'The support request could not be converted.', 'contactpanda-business' ), $project_id );
		global $wpdb; $pt = ContactPanda_Pro_Schema::table_names(); $ticket = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$pt['support_tickets']}` WHERE id=%d", $ticket_id ), ARRAY_A ); if ( ! $ticket ) self::redirect( false, __( 'The support request could not be found.', 'contactpanda-business' ), $project_id );
		$t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], array( 'uuid' => wp_generate_uuid4(), 'project_id' => $project_id, 'support_ticket_id' => $ticket_id, 'title' => $ticket['subject'], 'description' => sprintf( __( 'Created from Support Desk request #%d.', 'contactpanda-business' ), $ticket_id ), 'board_column' => 'open', 'open_status' => 'new', 'priority' => self::clean_priority( $ticket['priority'] ), 'visibility' => 'customer', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); self::event( $project_id, (int) $wpdb->insert_id, 'task_created_from_support', array( 'ticket_id' => $ticket_id ) ); self::redirect( true, __( 'A Business task was created from the support request.', 'contactpanda-business' ), $project_id, (int) $wpdb->insert_id );
	}

	public static function account_page() {
		if ( ! is_user_logged_in() ) return; $user_id = get_current_user_id(); global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$task_id = absint( $_GET['business_task'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only customer route.
		if ( $task_id ) {
			$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.*,p.name project_name FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.visibility='customer' AND x.deleted_at IS NULL AND p.visibility='customer' AND p.deleted_at IS NULL", $task_id ), ARRAY_A );
			if ( ! $task || ! self::customer_can( (int) $task['project_id'], $user_id, 'read' ) ) { echo '<p>' . esc_html__( 'This task is not available.', 'contactpanda-business' ) . '</p>'; return; }
			$access = self::customer_access_level( (int) $task['project_id'], $user_id );
			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['comments']}` WHERE task_id=%d AND visibility='customer' ORDER BY created_at", $task_id ), ARRAY_A );
			echo '<div class="cpb-account"><p><a href="' . esc_url( self::account_url() ) . '">← ' . esc_html__( 'Back to projects', 'contactpanda-business' ) . '</a></p><h2>' . esc_html( $task['title'] ) . '</h2><p>' . nl2br( esc_html( $task['description'] ) ) . '</p><p><strong>' . esc_html__( 'Progress', 'contactpanda-business' ) . ':</strong> ' . esc_html( $task['progress'] ) . '% · ' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . '</p>';
			foreach ( $comments as $comment ) { $author = get_userdata( (int) $comment['author_user_id'] ); echo '<article class="cpb-comment"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>'; }
			if ( in_array( $access, array( 'write', 'manage' ), true ) ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_customer_comment_' . $task_id ); echo '<p><label>' . esc_html__( 'Comment', 'contactpanda-business' ) . '<textarea name="body" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Add comment', 'contactpanda-business' ) . '</button></form>'; }
			if ( 'manage' === $access ) { echo '<h3>' . esc_html__( 'Manage task', 'contactpanda-business' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_update_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_customer_update_task_' . $task_id ); echo '<p><label>' . esc_html__( 'Column', 'contactpanda-business' ) . '<select name="board_column">'; foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label></p><p><label>' . esc_html__( 'Open status', 'contactpanda-business' ) . '<select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label></p><p><label>' . esc_html__( 'Progress', 'contactpanda-business' ) . '<input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label></p><button class="button">' . esc_html__( 'Update task', 'contactpanda-business' ) . '</button></form>'; }
			echo '</div>';
			return;
		}
		$projects = $wpdb->get_results( "SELECT * FROM `{$t['projects']}` p WHERE p.visibility='customer' AND p.deleted_at IS NULL ORDER BY p.updated_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name without user input.
		$projects = array_values( array_filter( (array) $projects, static function ( $project ) use ( $user_id ) { return self::customer_can( (int) $project['id'], $user_id, 'read' ); } ) );
		echo '<div class="cpb-account"><h2>' . esc_html__( 'Projects', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Submit new tasks and follow customer-visible progress.', 'contactpanda-business' ) . '</p>';
		if ( ! $projects ) { echo '<p>' . esc_html__( 'No projects are assigned to your account.', 'contactpanda-business' ) . '</p></div>'; return; }
		foreach ( $projects as $project ) {
			$access = self::customer_access_level( (int) $project['id'], $user_id ); echo '<section><h3>' . esc_html( $project['name'] ) . '</h3><p><small>' . esc_html( self::access_levels()[ $access ] ?? '' ) . '</small></p>';
			$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE project_id=%d AND visibility='customer' AND deleted_at IS NULL ORDER BY updated_at DESC", $project['id'] ), ARRAY_A );
			if ( $tasks ) { echo '<ul class="cpb-account-tasks">'; foreach ( $tasks as $task ) { $url = self::account_url( absint( $task['id'] ) ); echo '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $task['title'] ) . '</strong></a><span>' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . ' · ' . esc_html( $task['progress'] ) . '%</span></li>'; } echo '</ul>'; }
			if ( in_array( $access, array( 'write', 'manage' ), true ) ) { echo '<details><summary>' . esc_html__( 'Submit a new task', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_task"><input type="hidden" name="project_id" value="' . esc_attr( $project['id'] ) . '">'; wp_nonce_field( 'contactpanda_business_customer_task_' . $project['id'] ); echo '<p><label>' . esc_html__( 'Title', 'contactpanda-business' ) . '<input name="title" required></label></p><p><label>' . esc_html__( 'Description', 'contactpanda-business' ) . '<textarea name="description" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Submit task', 'contactpanda-business' ) . '</button></form></details>'; }
			echo '</section>';
		}
		echo '</div>';
	}

	public static function customer_task() {
		if ( ! is_user_logged_in() ) auth_redirect(); $project_id = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_task_' . $project_id );
		if ( ! self::customer_can( $project_id, get_current_user_id(), 'write' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ); if ( '' === $title || '' === $description ) { wp_safe_redirect( self::account_url() ); exit; }
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], array( 'uuid' => wp_generate_uuid4(), 'project_id' => $project_id, 'title' => $title, 'description' => $description, 'board_column' => 'open', 'open_status' => 'new', 'priority' => 'normal', 'visibility' => 'customer', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); self::event( $project_id, (int) $wpdb->insert_id, 'customer_task_created', array() ); wp_safe_redirect( self::account_url() ); exit;
	}

	public static function customer_comment() {
		if ( ! is_user_logged_in() ) auth_redirect(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.id,x.project_id FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.visibility='customer' AND x.deleted_at IS NULL AND p.visibility='customer' AND p.deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task || '' === $body || ! self::customer_can( (int) $task['project_id'], get_current_user_id(), 'write' ) ) wp_die( esc_html__( 'This task is not available.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( (int) $task['project_id'], $task_id, 'customer_comment_added', array() ); wp_safe_redirect( self::account_url( $task_id ) ); exit;
	}

	public static function customer_update_task() {
		if ( ! is_user_logged_in() ) auth_redirect(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_update_task_' . $task_id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task = $wpdb->get_row( $wpdb->prepare( "SELECT id,project_id FROM `{$t['tasks']}` WHERE id=%d AND visibility='customer' AND deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task || ! self::customer_can( (int) $task['project_id'], get_current_user_id(), 'manage' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$column = sanitize_key( wp_unslash( $_POST['board_column'] ?? '' ) ); if ( ! isset( self::columns()[ $column ] ) ) $column = 'open';
		$open = sanitize_key( wp_unslash( $_POST['open_status'] ?? '' ) ); if ( ! isset( self::open_statuses()[ $open ] ) ) $open = 'new';
		$progress = min( 100, absint( $_POST['progress'] ?? 0 ) );
		$wpdb->update( $t['tasks'], array( 'board_column' => $column, 'open_status' => $open, 'progress' => $progress, 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) );
		self::event( (int) $task['project_id'], $task_id, 'customer_task_updated', array( 'column' => $column, 'progress' => $progress ) ); wp_safe_redirect( self::account_url( $task_id ) ); exit;
	}

	private static function account_url( $task_id = 0 ) {
		if ( class_exists( 'ContactPanda_Customer_Portal' ) && ContactPanda_Customer_Portal::is_configured() ) {
			return ContactPanda_Customer_Portal::tab_url( 'projects', $task_id ? array( 'business_task' => absint( $task_id ) ) : array() );
		}
		$url = function_exists( 'wc_get_account_endpoint_url' ) ? wc_get_account_endpoint_url( 'projekte-korrekturen' ) : home_url( '/' );
		return $task_id ? add_query_arg( 'business_task', absint( $task_id ), $url ) : $url;
	}

	private static function participant_picker( $type, $label, $options, $selected = array() ) {
		$id = 'cpb-participant-' . sanitize_key( $type );
		echo '<div class="cpb-participant-picker" data-cpb-participant-picker><span class="cpb-participant-label">' . esc_html( $label ) . '</span><details><summary><span data-cpb-participant-summary>' . esc_html__( 'Select participants', 'contactpanda-business' ) . '</span><span class="dashicons dashicons-arrow-down-alt2" aria-hidden="true"></span></summary><div class="cpb-participant-dropdown"><label class="screen-reader-text" for="' . esc_attr( $id ) . '">' . esc_html__( 'Search participants', 'contactpanda-business' ) . '</label><input id="' . esc_attr( $id ) . '" type="search" placeholder="' . esc_attr__( 'Search participants', 'contactpanda-business' ) . '" data-cpb-participant-search>';
		if ( ! $options ) echo '<p class="cpb-empty">' . esc_html__( 'No matching records are available.', 'contactpanda-business' ) . '</p>';
		foreach ( $options as $option ) {
			$entity_id = absint( $option['id'] ?? 0 ); if ( ! $entity_id ) continue;
			$default_role = 'user' === $type ? 'customer_account' : ( 'company' === $type ? 'customer' : 'contact' );
			$current_role = self::clean_participant_role( $selected[ $entity_id ]['role'] ?? $default_role );
			$current_access = self::clean_access_level( $selected[ $entity_id ]['access'] ?? 'read' );
			echo '<div class="cpb-participant-option" data-cpb-participant-option data-search="' . esc_attr( strtolower( remove_accents( $option['label'] ) ) ) . '"><label><input type="checkbox" name="participants[' . esc_attr( $type ) . '][]" value="' . esc_attr( $entity_id ) . '"' . checked( isset( $selected[ $entity_id ] ), true, false ) . '> <span>' . esc_html( $option['label'] ) . '</span></label><select name="participant_roles[' . esc_attr( $type ) . '][' . esc_attr( $entity_id ) . ']" aria-label="' . esc_attr__( 'Role in project', 'contactpanda-business' ) . '">';
			foreach ( self::participant_roles() as $role => $role_label ) echo '<option value="' . esc_attr( $role ) . '"' . selected( $current_role, $role, false ) . '>' . esc_html( $role_label ) . '</option>';
			echo '</select><select name="participant_access[' . esc_attr( $type ) . '][' . esc_attr( $entity_id ) . ']" aria-label="' . esc_attr__( 'Project permissions', 'contactpanda-business' ) . '">';
			foreach ( self::access_levels() as $access => $access_label ) echo '<option value="' . esc_attr( $access ) . '"' . selected( $current_access, $access, false ) . '>' . esc_html( $access_label ) . '</option>';
			echo '</select></div>';
		}
		echo '</div></details></div>';
	}

	private static function user_options() {
		$options = array();
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) $options[] = array( 'id' => $user->ID, 'label' => trim( $user->display_name . ' — ' . $user->user_email ) );
		return $options;
	}

	private static function person_options() {
		if ( ! class_exists( 'ContactPanda_Plugin' ) || ! method_exists( 'ContactPanda_Plugin', 'extension_contacts' ) ) return array();
		$options = array();
		foreach ( (array) ContactPanda_Plugin::extension_contacts() as $person ) {
			$id = absint( ContactPanda_Plugin::extension_value( $person, array( 'id', 'ID', 'get_id' ) ) );
			$name = trim( ContactPanda_Plugin::extension_value( $person, array( 'first_name', 'get_first_name' ) ) . ' ' . ContactPanda_Plugin::extension_value( $person, array( 'last_name', 'get_last_name' ) ) );
			$email = ContactPanda_Plugin::extension_value( $person, array( 'email', 'get_email' ) );
			if ( $id ) $options[] = array( 'id' => $id, 'label' => trim( ( $name ?: __( 'Unnamed person', 'contactpanda-business' ) ) . ( $email ? ' — ' . $email : '' ) ) );
		}
		return $options;
	}

	private static function company_options() {
		if ( ! class_exists( 'ContactPanda_Plugin' ) || ! method_exists( 'ContactPanda_Plugin', 'extension_companies' ) ) return array();
		$options = array();
		foreach ( (array) ContactPanda_Plugin::extension_companies() as $company ) {
			$id = absint( ContactPanda_Plugin::extension_value( $company, array( 'id', 'ID', 'get_id' ) ) );
			$name = ContactPanda_Plugin::extension_value( $company, array( 'name', 'company_name', 'get_name' ) );
			if ( $id ) $options[] = array( 'id' => $id, 'label' => $name ?: __( 'Unnamed company', 'contactpanda-business' ) );
		}
		return $options;
	}

	private static function posted_participants() {
		$posted = isset( $_POST['participants'] ) && is_array( $_POST['participants'] ) ? wp_unslash( $_POST['participants'] ) : array();
		$posted_roles = isset( $_POST['participant_roles'] ) && is_array( $_POST['participant_roles'] ) ? wp_unslash( $_POST['participant_roles'] ) : array();
		$posted_access = isset( $_POST['participant_access'] ) && is_array( $_POST['participant_access'] ) ? wp_unslash( $_POST['participant_access'] ) : array();
		$result = array();
		foreach ( array( 'user', 'person', 'company' ) as $type ) {
			$allowed_ids = self::participant_ids( $type );
			$ids = isset( $posted[ $type ] ) && is_array( $posted[ $type ] ) ? array_unique( array_filter( array_map( 'absint', $posted[ $type ] ) ) ) : array();
			foreach ( $ids as $id ) {
				if ( ! isset( $allowed_ids[ $id ] ) ) continue;
				$role = self::clean_participant_role( $posted_roles[ $type ][ $id ] ?? 'participant' );
				$access = self::clean_access_level( $posted_access[ $type ][ $id ] ?? 'read' );
				$result[] = array( 'type' => $type, 'id' => $id, 'role' => $role, 'access' => $access );
			}
		}
		return $result;
	}

	private static function participant_ids( $type ) {
		$options = 'user' === $type ? self::user_options() : ( 'person' === $type ? self::person_options() : self::company_options() );
		$ids = array();
		foreach ( $options as $option ) {
			$id = absint( $option['id'] ?? 0 );
			if ( $id ) $ids[ $id ] = true;
		}
		return $ids;
	}

	private static function save_participants( $project_id, $participants, $created_at, $replace = false ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $primary = array();
		if ( $replace ) $wpdb->delete( $t['participants'], array( 'project_id' => absint( $project_id ) ) );
		foreach ( $participants as $participant ) {
			$type = $participant['type']; $is_primary = empty( $primary[ $type ] ) ? 1 : 0; $primary[ $type ] = true;
			$wpdb->insert( $t['participants'], array( 'project_id' => absint( $project_id ), 'entity_type' => $type, 'entity_id' => absint( $participant['id'] ), 'participant_role' => $participant['role'], 'access_level' => $participant['access'], 'is_primary' => $is_primary, 'created_at' => $created_at ) );
		}
	}

	private static function project_participants( $project_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $selected = array( 'user' => array(), 'person' => array(), 'company' => array() );
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT entity_type,entity_id,participant_role,access_level FROM `{$t['participants']}` WHERE project_id=%d", $project_id ), ARRAY_A );
		foreach ( (array) $rows as $row ) if ( isset( $selected[ $row['entity_type'] ] ) ) $selected[ $row['entity_type'] ][ absint( $row['entity_id'] ) ] = array( 'role' => self::clean_participant_role( $row['participant_role'] ), 'access' => self::clean_access_level( $row['access_level'] ) );
		return $selected;
	}

	private static function first_participant_id( $participants, $type ) {
		foreach ( $participants as $participant ) if ( $type === $participant['type'] ) return absint( $participant['id'] );
		return 0;
	}

	private static function participant_summary( $project_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT entity_type,entity_id,participant_role,access_level FROM `{$t['participants']}` WHERE project_id=%d ORDER BY is_primary DESC,id ASC", $project_id ), ARRAY_A );
		$labels = array();
		foreach ( (array) $rows as $row ) {
			$entity = self::participant_entity_label( $row['entity_type'], absint( $row['entity_id'] ) );
			if ( $entity ) $labels[] = sprintf( '%1$s (%2$s · %3$s)', $entity, self::participant_roles()[ $row['participant_role'] ] ?? $row['participant_role'], self::access_levels()[ $row['access_level'] ] ?? $row['access_level'] );
		}
		return implode( ', ', $labels );
	}

	private static function participant_entity_label( $type, $id ) {
		static $maps = array();
		if ( 'user' === $type ) { $user = get_userdata( $id ); return $user ? $user->display_name : ''; }
		if ( ! isset( $maps[ $type ] ) ) {
			$maps[ $type ] = array(); $options = 'person' === $type ? self::person_options() : self::company_options();
			foreach ( $options as $option ) $maps[ $type ][ absint( $option['id'] ) ] = $option['label'];
		}
		return $maps[ $type ][ $id ] ?? '';
	}

	private static function customer_access_level( $project_id, $user_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$level = $wpdb->get_var( $wpdb->prepare( "SELECT access_level FROM `{$t['participants']}` WHERE project_id=%d AND entity_type='user' AND entity_id=%d", $project_id, $user_id ) );
		if ( $level ) return self::clean_access_level( $level );
		$person_id = self::person_id_for_user( $user_id );
		if ( $person_id ) {
			$level = $wpdb->get_var( $wpdb->prepare( "SELECT access_level FROM `{$t['participants']}` WHERE project_id=%d AND entity_type='person' AND entity_id=%d", $project_id, $person_id ) );
			if ( $level ) return self::clean_access_level( $level );
		}
		$legacy = $wpdb->get_var( $wpdb->prepare( "SELECT customer_user_id FROM `{$t['projects']}` WHERE id=%d AND customer_user_id=%d", $project_id, $user_id ) );
		return $legacy ? 'write' : '';
	}

	private static function person_id_for_user( $user_id ) {
		static $cache = array(); if ( isset( $cache[ $user_id ] ) ) return $cache[ $user_id ];
		$user = get_userdata( $user_id ); if ( ! $user || ! class_exists( 'ContactPanda_Plugin' ) || ! method_exists( 'ContactPanda_Plugin', 'extension_contacts' ) ) return $cache[ $user_id ] = 0;
		foreach ( (array) ContactPanda_Plugin::extension_contacts() as $person ) {
			$email = ContactPanda_Plugin::extension_value( $person, array( 'email', 'get_email' ) );
			if ( $email && 0 === strcasecmp( $email, $user->user_email ) ) return $cache[ $user_id ] = absint( ContactPanda_Plugin::extension_value( $person, array( 'id', 'ID', 'get_id' ) ) );
		}
		return $cache[ $user_id ] = 0;
	}

	private static function customer_can( $project_id, $user_id, $required ) {
		$weights = array( 'read' => 1, 'write' => 2, 'manage' => 3 ); $current = self::customer_access_level( $project_id, $user_id );
		return isset( $weights[ $current ], $weights[ $required ] ) && $weights[ $current ] >= $weights[ $required ];
	}

	private static function event( $project_id, $task_id, $type, $details ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $wpdb->insert( $t['events'], array( 'project_id' => $project_id, 'task_id' => $task_id, 'event_type' => $type, 'actor_user_id' => get_current_user_id(), 'details_json' => wp_json_encode( $details ), 'created_at' => current_time( 'mysql', true ) ) ); }
	private static function redirect( $success, $message, $project_id = 0, $task_id = 0, $open_task_id = 0 ) { set_transient( self::NOTICE . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS ); $args = array( 'page' => 'contactpanda-business-projects' ); if ( $project_id ) { $args['project'] = $project_id; $action = sanitize_key( wp_unslash( $_REQUEST['action'] ?? '' ) ); if ( $task_id || $open_task_id || preg_match( '/(task|comment|checklist)/', $action ) ) $args['project_tab'] = 'tasks'; } if ( $task_id ) $args['task'] = $task_id; if ( $open_task_id ) $args['open_task'] = $open_task_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function task_url( $id ) { return add_query_arg( array( 'page' => 'contactpanda-business-projects', 'task' => absint( $id ) ), admin_url( 'admin.php' ) ); }
	private static function due_label( $date ) { if ( ! $date ) return __( 'No due date', 'contactpanda-business' ); $time = strtotime( $date . ' UTC' ); $today = strtotime( gmdate( 'Y-m-d' ) . ' UTC' ); if ( $time < time() ) return sprintf( __( 'Overdue: %s', 'contactpanda-business' ), wp_date( get_option( 'date_format' ), $time ) ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', $today ) ) return __( 'Due today', 'contactpanda-business' ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', strtotime( '+1 day', $today ) ) ) return __( 'Due tomorrow', 'contactpanda-business' ); return wp_date( get_option( 'date_format' ), $time ); }
	private static function progress_band( $progress ) { if ( $progress <= 33 ) return 1; if ( $progress <= 66 ) return 2; return 3; }
	private static function clean_color( $color ) { $color = is_string( $color ) ? sanitize_hex_color( wp_unslash( $color ) ) : ''; return $color ?: '#3d5cf5'; }
	private static function clean_participant_role( $role ) { $role = is_string( $role ) ? sanitize_key( $role ) : ''; return isset( self::participant_roles()[ $role ] ) ? $role : 'participant'; }
	private static function clean_access_level( $level ) { $level = is_string( $level ) ? sanitize_key( $level ) : ''; return isset( self::access_levels()[ $level ] ) ? $level : 'read'; }
	private static function clean_visibility( $value ) { return 'internal' === sanitize_key( wp_unslash( $value ) ) ? 'internal' : 'customer'; }
	private static function clean_priority( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::priorities()[ $value ] ) ? $value : 'normal'; }
	private static function clean_date( $value ) { $value = sanitize_text_field( wp_unslash( (string) $value ) ); return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ? $value : null; }
	private static function decimal( $value ) { return number_format( max( 0, (float) str_replace( ',', '.', (string) $value ) ), 2, '.', '' ); }
	private static function clean_currency( $value ) { $value = strtoupper( preg_replace( '/[^A-Za-z]/', '', (string) $value ) ); return 3 === strlen( $value ) ? $value : 'EUR'; }
	private static function project_exists( $project_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); return (bool) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", absint( $project_id ) ) ); }
	private static function project_area_id( $project_id, $area_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $area_id = absint( $area_id ); return $area_id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['areas']}` WHERE id=%d AND project_id=%d AND deleted_at IS NULL", $area_id, absint( $project_id ) ) ) ? $area_id : 0; }
	private static function project_step_id( $project_id, $step_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $step_id = absint( $step_id ); return $step_id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['project_steps']}` WHERE id=%d AND project_id=%d AND deleted_at IS NULL", $step_id, absint( $project_id ) ) ) ? $step_id : 0; }
	private static function money( $amount, $currency ) { return number_format_i18n( (float) $amount, 2 ) . ' ' . self::clean_currency( $currency ); }
	private static function short_date( $date ) { if ( ! $date ) return '—'; $time = strtotime( (string) $date ); return $time ? wp_date( get_option( 'date_format' ), $time ) : '—'; }
	private static function columns() { return array( 'open' => __( 'Open', 'contactpanda-business' ), 'in_progress' => __( 'In progress', 'contactpanda-business' ), 'review' => __( 'Review', 'contactpanda-business' ), 'done' => __( 'Done', 'contactpanda-business' ) ); }
	private static function open_statuses() { return array( 'new' => __( 'New', 'contactpanda-business' ), 'question' => __( 'Question', 'contactpanda-business' ), 'waiting_customer' => __( 'Waiting for customer', 'contactpanda-business' ), 'planned' => __( 'Planned', 'contactpanda-business' ) ); }
	private static function priorities() { return array( 'low' => __( 'Low', 'contactpanda-business' ), 'normal' => __( 'Normal', 'contactpanda-business' ), 'high' => __( 'High', 'contactpanda-business' ), 'urgent' => __( 'Urgent', 'contactpanda-business' ) ); }
	private static function participant_roles() { return array( 'customer_account' => __( 'Customer account', 'contactpanda-business' ), 'customer' => __( 'Customer', 'contactpanda-business' ), 'agency' => __( 'Agency', 'contactpanda-business' ), 'contact' => __( 'Contact person', 'contactpanda-business' ), 'participant' => __( 'Other participant', 'contactpanda-business' ) ); }
	private static function access_levels() { return array( 'read' => __( 'Read only', 'contactpanda-business' ), 'write' => __( 'Read and write', 'contactpanda-business' ), 'manage' => __( 'Manage and move tasks', 'contactpanda-business' ) ); }
	private static function visibilities() { return array( 'customer' => __( 'Visible to customer', 'contactpanda-business' ), 'internal' => __( 'Internal only', 'contactpanda-business' ) ); }
	private static function project_statuses() { return array( 'active' => __( 'Active', 'contactpanda-business' ), 'on_hold' => __( 'On hold', 'contactpanda-business' ), 'completed' => __( 'Completed', 'contactpanda-business' ), 'archived' => __( 'Archived', 'contactpanda-business' ) ); }
	private static function step_types() { return array( 'offer' => __( 'Offer', 'contactpanda-business' ), 'revised_offer' => __( 'Revised offer', 'contactpanda-business' ), 'order' => __( 'Order awarded', 'contactpanda-business' ), 'order_confirmation' => __( 'Order confirmation', 'contactpanda-business' ), 'project_start' => __( 'Project start', 'contactpanda-business' ), 'phase' => __( 'Project phase', 'contactpanda-business' ), 'acceptance' => __( 'Acceptance', 'contactpanda-business' ), 'partial_payment' => __( 'Partial payment', 'contactpanda-business' ), 'final_payment' => __( 'Final payment', 'contactpanda-business' ), 'other' => __( 'Other milestone', 'contactpanda-business' ) ); }
	private static function step_statuses() { return array( 'planned' => __( 'Planned', 'contactpanda-business' ), 'in_progress' => __( 'In progress', 'contactpanda-business' ), 'completed' => __( 'Completed', 'contactpanda-business' ), 'cancelled' => __( 'Cancelled', 'contactpanda-business' ) ); }
	private static function payment_statuses() { return array( 'planned' => __( 'Planned', 'contactpanda-business' ), 'due' => __( 'Due', 'contactpanda-business' ), 'partial' => __( 'Partially paid', 'contactpanda-business' ), 'paid' => __( 'Paid', 'contactpanda-business' ), 'overdue' => __( 'Overdue', 'contactpanda-business' ), 'cancelled' => __( 'Cancelled', 'contactpanda-business' ) ); }
	private static function document_types() { return array( 'offer' => __( 'Offer', 'contactpanda-business' ), 'contract' => __( 'Contract', 'contactpanda-business' ), 'order_confirmation' => __( 'Order confirmation', 'contactpanda-business' ), 'invoice' => __( 'Invoice', 'contactpanda-business' ), 'acceptance' => __( 'Acceptance document', 'contactpanda-business' ), 'briefing' => __( 'Briefing', 'contactpanda-business' ), 'correspondence' => __( 'Correspondence', 'contactpanda-business' ), 'other' => __( 'Other document', 'contactpanda-business' ) ); }
	private static function guard() { if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); }
}
