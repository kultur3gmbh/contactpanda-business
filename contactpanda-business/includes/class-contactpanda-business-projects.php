<?php

defined( 'ABSPATH' ) || exit;

final class ContactPanda_Business_Projects {
	const NOTICE = 'contactpanda_business_notice_';

	public static function boot() {
		add_action( 'admin_post_contactpanda_business_save_project', array( __CLASS__, 'save_project' ) );
		add_action( 'admin_post_contactpanda_business_save_task', array( __CLASS__, 'save_task' ) );
		add_action( 'admin_post_contactpanda_business_add_comment', array( __CLASS__, 'add_comment' ) );
		add_action( 'admin_post_contactpanda_business_trash_task', array( __CLASS__, 'trash_task' ) );
		add_action( 'wp_ajax_contactpanda_business_move_task', array( __CLASS__, 'move_task' ) );
		add_action( 'contactpanda_pro_ticket_sidebar', array( __CLASS__, 'support_ticket_box' ) );
		add_action( 'admin_post_contactpanda_business_from_support', array( __CLASS__, 'from_support' ) );
		add_action( 'init', array( __CLASS__, 'endpoint' ) );
		add_filter( 'woocommerce_account_menu_items', array( __CLASS__, 'account_menu' ) );
		add_action( 'woocommerce_account_projekte-korrekturen_endpoint', array( __CLASS__, 'account_page' ) );
		add_action( 'admin_post_contactpanda_business_customer_task', array( __CLASS__, 'customer_task' ) );
		add_action( 'admin_post_contactpanda_business_customer_comment', array( __CLASS__, 'customer_comment' ) );
		add_action( 'admin_post_contactpanda_business_customer_update_task', array( __CLASS__, 'customer_update_task' ) );
		add_action( 'contactpanda_plus_customer_detail', array( __CLASS__, 'customer_projects' ), 10, 5 );
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
		$items['projekte-korrekturen'] = __( 'Projects & corrections', 'contactpanda-business' );
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
		echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html__( 'Projects & corrections', 'contactpanda-business' ) . '</h1><p>' . esc_html__( 'Plan work on a board and decide separately which tasks customers may see.', 'contactpanda-business' ) . '</p></div></header>';
		$notice = get_transient( self::NOTICE . get_current_user_id() );
		if ( is_array( $notice ) ) { delete_transient( self::NOTICE . get_current_user_id() ); echo '<div class="notice notice-' . ( $notice['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; }
	}

	private static function render_projects() {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$status = sanitize_key( wp_unslash( $_GET['project_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter.
		$where = array( 'p.deleted_at IS NULL' ); $args = array();
		if ( '' !== $search ) { $where[] = '(p.name LIKE %s OR p.description LIKE %s)'; $like = '%' . $wpdb->esc_like( $search ) . '%'; $args[] = $like; $args[] = $like; }
		if ( isset( self::project_statuses()[ $status ] ) ) { $where[] = 'p.status=%s'; $args[] = $status; }
		$sql = "SELECT p.*,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL) task_count,(SELECT COUNT(*) FROM `{$t['tasks']}` x WHERE x.project_id=p.id AND x.deleted_at IS NULL AND x.board_column!='done') open_count FROM `{$t['projects']}` p WHERE " . implode( ' AND ', $where ) . ' ORDER BY p.updated_at DESC';
		$rows = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared values and internal tables.
		echo '<div class="cpb-toolbar"><form method="get"><input type="hidden" name="page" value="contactpanda-business-projects"><label class="screen-reader-text" for="cpb-project-search">' . esc_html__( 'Search projects', 'contactpanda-business' ) . '</label><input id="cpb-project-search" type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search projects', 'contactpanda-business' ) . '"><select name="project_status"><option value="">' . esc_html__( 'All', 'contactpanda-business' ) . '</option>';
		foreach ( self::project_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select><button class="button" aria-label="' . esc_attr__( 'Search', 'contactpanda-business' ) . '"><span class="dashicons dashicons-search" aria-hidden="true"></span></button></form><button type="button" class="button button-primary" data-cpb-open="cpb-new-project">' . esc_html__( 'Create project', 'contactpanda-business' ) . '</button></div>';
		self::project_modal();
		echo '<section class="cpb-card"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Project', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Project participants', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Tasks', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="6" class="cpb-empty">' . esc_html__( 'No projects have been created yet.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $row['id'] ), admin_url( 'admin.php' ) );
			$participants = self::participant_summary( (int) $row['id'] );
			$color = self::clean_color( $row['color'] ?? '' );
			echo '<tr><td><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( $color ) . '"></span><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $row['name'] ) . '</strong></a><br><small>' . esc_html( wp_trim_words( $row['description'], 12 ) ) . '</small></td><td>' . esc_html( $participants ?: __( 'Not assigned', 'contactpanda-business' ) ) . '</td><td>' . esc_html( sprintf( __( '%1$d open / %2$d total', 'contactpanda-business' ), $row['open_count'], $row['task_count'] ) ) . '</td><td>' . esc_html( self::visibilities()[ $row['visibility'] ] ?? $row['visibility'] ) . '</td><td><a class="button cpb-icon-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'View project', 'contactpanda-business' ) . '"><span class="dashicons dashicons-visibility"></span></a></td><td><span class="cpb-status is-' . esc_attr( $row['status'] ) . '">' . esc_html( self::project_statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></td></tr>';
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
		echo '<label><span>' . esc_html__( 'Project name', 'contactpanda-business' ) . '</span><input name="name" value="' . esc_attr( $project['name'] ?? '' ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $project['description'] ?? '' ) . '</textarea></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Project color', 'contactpanda-business' ) . '</span><input type="color" name="color" value="' . esc_attr( self::clean_color( $project['color'] ?? '' ) ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $project['visibility'] ?? 'customer', 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $project['visibility'] ?? 'customer', 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><h3>' . esc_html__( 'Project participants', 'contactpanda-business' ) . '</h3><p class="description">' . esc_html__( 'Assign one or more customer accounts, people or companies and define their role in the project.', 'contactpanda-business' ) . '</p>';
		self::participant_picker( 'user', __( 'Customer accounts with portal access', 'contactpanda-business' ), self::user_options(), $selected['user'] ?? array() );
		self::participant_picker( 'person', __( 'People', 'contactpanda-business' ), self::person_options(), $selected['person'] ?? array() );
		self::participant_picker( 'company', __( 'Companies', 'contactpanda-business' ), self::company_options(), $selected['company'] ?? array() );
		echo '<p><button class="button button-primary">' . esc_html__( 'Save project', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function render_board( $project_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ), ARRAY_A );
		if ( ! $project ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'This project does not exist.', 'contactpanda-business' ) . '</p></div>'; return; }
		$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,id", $project_id ), ARRAY_A );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-projects' ) ) . '">← ' . esc_html__( 'Back to projects', 'contactpanda-business' ) . '</a></p><div class="cpb-project-heading" style="--cpb-project-color:' . esc_attr( self::clean_color( $project['color'] ?? '' ) ) . '"><div><h2>' . esc_html( $project['name'] ) . '</h2><p>' . esc_html( $project['description'] ) . '</p><p class="cpb-project-participants">' . esc_html( self::participant_summary( $project_id ) ) . '</p></div><div><button type="button" class="button" data-cpb-open="cpb-edit-project">' . esc_html__( 'Edit project', 'contactpanda-business' ) . '</button> <button type="button" class="button button-primary" data-cpb-open="cpb-new-task">' . esc_html__( 'Create task', 'contactpanda-business' ) . '</button></div></div>';
		self::project_modal( $project );
		self::new_task_modal( $project_id );
		echo '<div class="cpb-board" data-cpb-project="' . esc_attr( $project_id ) . '">';
		foreach ( self::columns() as $key => $label ) { echo '<section class="cpb-column" data-cpb-column="' . esc_attr( $key ) . '"><header><h3>' . esc_html( $label ) . '</h3><span>' . esc_html( count( array_filter( $tasks, static function ( $task ) use ( $key ) { return $key === $task['board_column']; } ) ) ) . '</span></header><div class="cpb-column-body">'; foreach ( $tasks as $task ) if ( $key === $task['board_column'] ) self::task_card( $task ); echo '</div></section>'; }
		echo '</div>';
		foreach ( $tasks as $task ) self::task_detail_modal( $task );
	}

	private static function new_task_modal( $project_id ) {
		echo '<div id="cpb-new-task" class="cpb-modal" hidden><div class="cpb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpb-task-title"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="cpb-task-title">' . esc_html__( 'Create task', 'contactpanda-business' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_save_task_' . $project_id );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="6"></textarea></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Priority', 'contactpanda-business' ) . '</span><select name="priority">'; foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer">' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal">' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><p><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function task_card( $task ) {
		$progress = min( 100, absint( $task['progress'] ) );
		echo '<article class="cpb-task" draggable="true" tabindex="0" role="button" data-cpb-task="' . esc_attr( $task['id'] ) . '" data-cpb-task-card data-cpb-open="cpb-task-' . esc_attr( $task['id'] ) . '" aria-label="' . esc_attr( sprintf( __( 'Open task: %s', 'contactpanda-business' ), $task['title'] ) ) . '"><header><strong>' . esc_html( $task['title'] ) . '</strong><span class="cpb-badge is-' . esc_attr( $task['open_status'] ) . '">' . esc_html( self::open_statuses()[ $task['open_status']] ?? $task['open_status'] ) . '</span></header>' . ( 'internal' === $task['visibility'] ? '<div class="cpb-badges"><span class="cpb-badge is-internal">' . esc_html__( 'Internal', 'contactpanda-business' ) . '</span></div>' : '' ) . '<div class="cpb-progress is-p' . esc_attr( self::progress_band( $progress ) ) . '"><span style="width:' . esc_attr( $progress ) . '%"></span></div><small>' . esc_html( $progress ) . '% · ' . esc_html( self::due_label( $task['due_at'] ) ) . '</small></article>';
	}

	private static function task_detail_modal( $task ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task_id = absint( $task['id'] );
		$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['comments']}` WHERE task_id=%d ORDER BY created_at", $task_id ), ARRAY_A );
		echo '<div id="cpb-task-' . esc_attr( $task_id ) . '" class="cpb-modal" hidden><div class="cpb-modal-dialog cpb-task-modal" role="dialog" aria-modal="true" aria-labelledby="cpb-task-modal-title-' . esc_attr( $task_id ) . '"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="cpb-task-modal-title-' . esc_attr( $task_id ) . '">' . esc_html( $task['title'] ) . '</h2><div class="cpb-task-modal-grid"><main><p>' . nl2br( esc_html( $task['description'] ) ) . '</p><h3>' . esc_html__( 'Comments', 'contactpanda-business' ) . '</h3>';
		if ( ! $comments ) echo '<p class="cpb-empty">' . esc_html__( 'No comments yet.', 'contactpanda-business' ) . '</p>';
		foreach ( $comments as $comment ) { $author = get_userdata( (int) $comment['author_user_id'] ); echo '<article class="cpb-comment ' . ( 'internal' === $comment['visibility'] ? 'is-internal' : '' ) . '"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>'; }
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_add_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="return_to_board" value="1">'; wp_nonce_field( 'contactpanda_business_add_comment_' . $task_id ); echo '<textarea class="widefat" rows="4" name="body" required></textarea><p><label><input type="checkbox" name="internal" value="1"> ' . esc_html__( 'Internal comment', 'contactpanda-business' ) . '</label></p><button class="button button-primary">' . esc_html__( 'Add comment', 'contactpanda-business' ) . '</button></form></main><aside><h3>' . esc_html__( 'Task details', 'contactpanda-business' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '"><input type="hidden" name="project_id" value="' . esc_attr( $task['project_id'] ) . '"><input type="hidden" name="return_to_board" value="1">'; wp_nonce_field( 'contactpanda_business_save_task_' . $task['project_id'] );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" value="' . esc_attr( $task['title'] ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $task['description'] ) . '</textarea></label><label><span>' . esc_html__( 'Column', 'contactpanda-business' ) . '</span><select name="board_column">'; foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</span><input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at" value="' . esc_attr( $task['due_at'] ? gmdate( 'Y-m-d\TH:i', strtotime( $task['due_at'] ) ) : '' ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $task['visibility'], 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $task['visibility'], 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button></form><p><a href="' . esc_url( self::task_url( $task_id ) ) . '">' . esc_html__( 'Open full task view', 'contactpanda-business' ) . '</a></p></aside></div></div></div>';
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
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" value="' . esc_attr( $task['title'] ) . '" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5">' . esc_textarea( $task['description'] ) . '</textarea></label><label><span>' . esc_html__( 'Column', 'contactpanda-business' ) . '</span><select name="board_column">'; foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</span><input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at" value="' . esc_attr( $task['due_at'] ? gmdate( 'Y-m-d\TH:i', strtotime( $task['due_at'] ) ) : '' ) . '"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer"' . selected( $task['visibility'], 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $task['visibility'], 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button></form><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return window.confirm(' . esc_attr( wp_json_encode( __( 'Move this task to the trash?', 'contactpanda-business' ) ) ) . ');"><input type="hidden" name="action" value="contactpanda_business_trash_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_trash_task_' . $task_id ); echo '<p><button class="button cpb-danger">' . esc_html__( 'Move to trash', 'contactpanda-business' ) . '</button></p></form></aside></div>';
	}

	public static function save_project() {
		self::guard(); check_admin_referer( 'contactpanda_business_save_project' );
		$name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) ); if ( '' === $name ) self::redirect( false, __( 'Please enter a project name.', 'contactpanda-business' ) );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true );
		$project_id = absint( $_POST['project_id'] ?? 0 );
		$selected = self::posted_participants();
		$data = array( 'contact_id' => self::first_participant_id( $selected, 'person' ), 'company_id' => self::first_participant_id( $selected, 'company' ), 'customer_user_id' => self::first_participant_id( $selected, 'user' ), 'name' => $name, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'color' => self::clean_color( $_POST['color'] ?? '' ), 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'customer' ), 'updated_at' => $now );
		if ( $project_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ) );
			if ( ! $exists || false === $wpdb->update( $t['projects'], $data, array( 'id' => $project_id ) ) ) self::redirect( false, __( 'The project could not be updated.', 'contactpanda-business' ), $project_id );
			self::save_participants( $project_id, $selected, $now, true );
			self::event( $project_id, 0, 'project_updated', array( 'name' => $name, 'participant_count' => count( $selected ) ) ); self::redirect( true, __( 'The project was updated.', 'contactpanda-business' ), $project_id );
		}
		$data += array( 'uuid' => wp_generate_uuid4(), 'status' => 'active', 'created_by' => get_current_user_id(), 'created_at' => $now );
		$wpdb->insert( $t['projects'], $data ); $project_id = (int) $wpdb->insert_id;
		if ( ! $project_id ) self::redirect( false, __( 'The project could not be created.', 'contactpanda-business' ) );
		self::save_participants( $project_id, $selected, $now ); self::event( $project_id, 0, 'project_created', array( 'name' => $name, 'participant_count' => count( $selected ) ) ); self::redirect( true, __( 'The project was created.', 'contactpanda-business' ), $project_id );
	}

	public static function save_task() {
		self::guard(); $project_id = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_save_task_' . $project_id );
		$task_id = absint( $_POST['task_id'] ?? 0 ); $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); if ( ! $project_id || '' === $title ) self::redirect( false, __( 'Please enter a task title.', 'contactpanda-business' ), $project_id );
		$column = sanitize_key( wp_unslash( $_POST['board_column'] ?? 'open' ) ); if ( ! isset( self::columns()[ $column ] ) ) $column = 'open';
		$open = sanitize_key( wp_unslash( $_POST['open_status'] ?? 'new' ) ); if ( ! isset( self::open_statuses()[ $open ] ) ) $open = 'new';
		$due = sanitize_text_field( wp_unslash( $_POST['due_at'] ?? '' ) ); $due = $due ? gmdate( 'Y-m-d H:i:s', strtotime( $due ) ) : null;
		$data = array( 'project_id' => $project_id, 'title' => $title, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'board_column' => $column, 'open_status' => $open, 'progress' => min( 100, absint( $_POST['progress'] ?? 0 ) ), 'priority' => self::clean_priority( $_POST['priority'] ?? 'normal' ), 'due_at' => $due, 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'customer' ), 'updated_at' => current_time( 'mysql', true ), 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		if ( $task_id ) $wpdb->update( $t['tasks'], $data, array( 'id' => $task_id, 'project_id' => $project_id ) ); else { $data['uuid'] = wp_generate_uuid4(); $data['created_by'] = get_current_user_id(); $data['created_at'] = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], $data ); $task_id = (int) $wpdb->insert_id; }
		self::event( $project_id, $task_id, 'task_saved', array( 'column' => $column, 'progress' => $data['progress'] ) ); self::redirect( true, __( 'The task was saved.', 'contactpanda-business' ), $project_id, empty( $_POST['return_to_board'] ) ? $task_id : 0 );
	}

	public static function add_comment() {
		self::guard(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_add_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); if ( ! $task_id || '' === $body ) self::redirect( false, __( 'Please enter a comment.', 'contactpanda-business' ) );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d", $task_id ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => ! empty( $_POST['internal'] ) ? 'internal' : 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( $project_id, $task_id, 'comment_added', array() ); self::redirect( true, __( 'The comment was added.', 'contactpanda-business' ), $project_id, empty( $_POST['return_to_board'] ) ? $task_id : 0 );
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
			echo '<div class="cpb-account"><p><a href="' . esc_url( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ) . '">← ' . esc_html__( 'Back to projects', 'contactpanda-business' ) . '</a></p><h2>' . esc_html( $task['title'] ) . '</h2><p>' . nl2br( esc_html( $task['description'] ) ) . '</p><p><strong>' . esc_html__( 'Progress', 'contactpanda-business' ) . ':</strong> ' . esc_html( $task['progress'] ) . '% · ' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . '</p>';
			foreach ( $comments as $comment ) { $author = get_userdata( (int) $comment['author_user_id'] ); echo '<article class="cpb-comment"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>'; }
			if ( in_array( $access, array( 'write', 'manage' ), true ) ) { echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_customer_comment_' . $task_id ); echo '<p><label>' . esc_html__( 'Comment', 'contactpanda-business' ) . '<textarea name="body" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Add comment', 'contactpanda-business' ) . '</button></form>'; }
			if ( 'manage' === $access ) { echo '<h3>' . esc_html__( 'Manage task', 'contactpanda-business' ) . '</h3><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_update_task"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_customer_update_task_' . $task_id ); echo '<p><label>' . esc_html__( 'Column', 'contactpanda-business' ) . '<select name="board_column">'; foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['board_column'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label></p><p><label>' . esc_html__( 'Open status', 'contactpanda-business' ) . '<select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $task['open_status'], $key, false ) . '>' . esc_html( $label ) . '</option>'; echo '</select></label></p><p><label>' . esc_html__( 'Progress', 'contactpanda-business' ) . '<input type="number" min="0" max="100" name="progress" value="' . esc_attr( $task['progress'] ) . '"></label></p><button class="button">' . esc_html__( 'Update task', 'contactpanda-business' ) . '</button></form>'; }
			echo '</div>';
			return;
		}
		$projects = $wpdb->get_results( "SELECT * FROM `{$t['projects']}` p WHERE p.visibility='customer' AND p.deleted_at IS NULL ORDER BY p.updated_at DESC", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Internal prefix-safe table name without user input.
		$projects = array_values( array_filter( (array) $projects, static function ( $project ) use ( $user_id ) { return self::customer_can( (int) $project['id'], $user_id, 'read' ); } ) );
		echo '<div class="cpb-account"><h2>' . esc_html__( 'Projects & corrections', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Submit new tasks and follow customer-visible progress.', 'contactpanda-business' ) . '</p>';
		if ( ! $projects ) { echo '<p>' . esc_html__( 'No projects are assigned to your account.', 'contactpanda-business' ) . '</p></div>'; return; }
		foreach ( $projects as $project ) {
			$access = self::customer_access_level( (int) $project['id'], $user_id ); echo '<section><h3>' . esc_html( $project['name'] ) . '</h3><p><small>' . esc_html( self::access_levels()[ $access ] ?? '' ) . '</small></p>';
			$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE project_id=%d AND visibility='customer' AND deleted_at IS NULL ORDER BY updated_at DESC", $project['id'] ), ARRAY_A );
			if ( $tasks ) { echo '<ul class="cpb-account-tasks">'; foreach ( $tasks as $task ) { $url = add_query_arg( 'business_task', absint( $task['id'] ), wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); echo '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $task['title'] ) . '</strong></a><span>' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . ' · ' . esc_html( $task['progress'] ) . '%</span></li>'; } echo '</ul>'; }
			if ( in_array( $access, array( 'write', 'manage' ), true ) ) { echo '<details><summary>' . esc_html__( 'Submit a new task', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_task"><input type="hidden" name="project_id" value="' . esc_attr( $project['id'] ) . '">'; wp_nonce_field( 'contactpanda_business_customer_task_' . $project['id'] ); echo '<p><label>' . esc_html__( 'Title', 'contactpanda-business' ) . '<input name="title" required></label></p><p><label>' . esc_html__( 'Description', 'contactpanda-business' ) . '<textarea name="description" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Submit task', 'contactpanda-business' ) . '</button></form></details>'; }
			echo '</section>';
		}
		echo '</div>';
	}

	public static function customer_task() {
		if ( ! is_user_logged_in() ) auth_redirect(); $project_id = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_task_' . $project_id );
		if ( ! self::customer_can( $project_id, get_current_user_id(), 'write' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ); if ( '' === $title || '' === $description ) { wp_safe_redirect( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); exit; }
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], array( 'uuid' => wp_generate_uuid4(), 'project_id' => $project_id, 'title' => $title, 'description' => $description, 'board_column' => 'open', 'open_status' => 'new', 'priority' => 'normal', 'visibility' => 'customer', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); self::event( $project_id, (int) $wpdb->insert_id, 'customer_task_created', array() ); wp_safe_redirect( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); exit;
	}

	public static function customer_comment() {
		if ( ! is_user_logged_in() ) auth_redirect(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.id,x.project_id FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.visibility='customer' AND x.deleted_at IS NULL AND p.visibility='customer' AND p.deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task || '' === $body || ! self::customer_can( (int) $task['project_id'], get_current_user_id(), 'write' ) ) wp_die( esc_html__( 'This task is not available.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( (int) $task['project_id'], $task_id, 'customer_comment_added', array() ); $url = add_query_arg( 'business_task', $task_id, wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); wp_safe_redirect( $url ); exit;
	}

	public static function customer_update_task() {
		if ( ! is_user_logged_in() ) auth_redirect(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_update_task_' . $task_id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task = $wpdb->get_row( $wpdb->prepare( "SELECT id,project_id FROM `{$t['tasks']}` WHERE id=%d AND visibility='customer' AND deleted_at IS NULL", $task_id ), ARRAY_A );
		if ( ! $task || ! self::customer_can( (int) $task['project_id'], get_current_user_id(), 'manage' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$column = sanitize_key( wp_unslash( $_POST['board_column'] ?? '' ) ); if ( ! isset( self::columns()[ $column ] ) ) $column = 'open';
		$open = sanitize_key( wp_unslash( $_POST['open_status'] ?? '' ) ); if ( ! isset( self::open_statuses()[ $open ] ) ) $open = 'new';
		$progress = min( 100, absint( $_POST['progress'] ?? 0 ) );
		$wpdb->update( $t['tasks'], array( 'board_column' => $column, 'open_status' => $open, 'progress' => $progress, 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) );
		self::event( (int) $task['project_id'], $task_id, 'customer_task_updated', array( 'column' => $column, 'progress' => $progress ) ); $url = add_query_arg( 'business_task', $task_id, wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); wp_safe_redirect( $url ); exit;
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
	private static function redirect( $success, $message, $project_id = 0, $task_id = 0 ) { set_transient( self::NOTICE . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS ); $args = array( 'page' => 'contactpanda-business-projects' ); if ( $project_id ) $args['project'] = $project_id; if ( $task_id ) $args['task'] = $task_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function task_url( $id ) { return add_query_arg( array( 'page' => 'contactpanda-business-projects', 'task' => absint( $id ) ), admin_url( 'admin.php' ) ); }
	private static function due_label( $date ) { if ( ! $date ) return __( 'No due date', 'contactpanda-business' ); $time = strtotime( $date . ' UTC' ); $today = strtotime( gmdate( 'Y-m-d' ) . ' UTC' ); if ( $time < time() ) return sprintf( __( 'Overdue: %s', 'contactpanda-business' ), wp_date( get_option( 'date_format' ), $time ) ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', $today ) ) return __( 'Due today', 'contactpanda-business' ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', strtotime( '+1 day', $today ) ) ) return __( 'Due tomorrow', 'contactpanda-business' ); return wp_date( get_option( 'date_format' ), $time ); }
	private static function progress_band( $progress ) { if ( $progress <= 33 ) return 1; if ( $progress <= 66 ) return 2; return 3; }
	private static function clean_color( $color ) { $color = is_string( $color ) ? sanitize_hex_color( wp_unslash( $color ) ) : ''; return $color ?: '#3d5cf5'; }
	private static function clean_participant_role( $role ) { $role = is_string( $role ) ? sanitize_key( $role ) : ''; return isset( self::participant_roles()[ $role ] ) ? $role : 'participant'; }
	private static function clean_access_level( $level ) { $level = is_string( $level ) ? sanitize_key( $level ) : ''; return isset( self::access_levels()[ $level ] ) ? $level : 'read'; }
	private static function clean_visibility( $value ) { return 'internal' === sanitize_key( wp_unslash( $value ) ) ? 'internal' : 'customer'; }
	private static function clean_priority( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::priorities()[ $value ] ) ? $value : 'normal'; }
	private static function columns() { return array( 'open' => __( 'Open', 'contactpanda-business' ), 'in_progress' => __( 'In progress', 'contactpanda-business' ), 'review' => __( 'Review', 'contactpanda-business' ), 'done' => __( 'Done', 'contactpanda-business' ) ); }
	private static function open_statuses() { return array( 'new' => __( 'New', 'contactpanda-business' ), 'question' => __( 'Question', 'contactpanda-business' ), 'waiting_customer' => __( 'Waiting for customer', 'contactpanda-business' ), 'planned' => __( 'Planned', 'contactpanda-business' ) ); }
	private static function priorities() { return array( 'low' => __( 'Low', 'contactpanda-business' ), 'normal' => __( 'Normal', 'contactpanda-business' ), 'high' => __( 'High', 'contactpanda-business' ), 'urgent' => __( 'Urgent', 'contactpanda-business' ) ); }
	private static function participant_roles() { return array( 'customer_account' => __( 'Customer account', 'contactpanda-business' ), 'customer' => __( 'Customer', 'contactpanda-business' ), 'agency' => __( 'Agency', 'contactpanda-business' ), 'contact' => __( 'Contact person', 'contactpanda-business' ), 'participant' => __( 'Other participant', 'contactpanda-business' ) ); }
	private static function access_levels() { return array( 'read' => __( 'Read only', 'contactpanda-business' ), 'write' => __( 'Read and write', 'contactpanda-business' ), 'manage' => __( 'Manage and move tasks', 'contactpanda-business' ) ); }
	private static function visibilities() { return array( 'customer' => __( 'Visible to customer', 'contactpanda-business' ), 'internal' => __( 'Internal only', 'contactpanda-business' ) ); }
	private static function project_statuses() { return array( 'active' => __( 'Active', 'contactpanda-business' ), 'on_hold' => __( 'On hold', 'contactpanda-business' ), 'completed' => __( 'Completed', 'contactpanda-business' ), 'archived' => __( 'Archived', 'contactpanda-business' ) ); }
	private static function guard() { if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); }
}
