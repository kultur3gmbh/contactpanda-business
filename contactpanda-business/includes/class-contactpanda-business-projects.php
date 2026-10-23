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
		echo '<section class="cpb-card"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Project', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Customer', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Tasks', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $rows ) echo '<tr><td colspan="6" class="cpb-empty">' . esc_html__( 'No projects have been created yet.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $rows as $row ) {
			$url = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $row['id'] ), admin_url( 'admin.php' ) );
			$user = $row['customer_user_id'] ? get_userdata( (int) $row['customer_user_id'] ) : false;
			echo '<tr><td><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $row['name'] ) . '</strong></a><br><small>' . esc_html( wp_trim_words( $row['description'], 12 ) ) . '</small></td><td>' . esc_html( $user ? $user->display_name : __( 'Not assigned', 'contactpanda-business' ) ) . '</td><td>' . esc_html( sprintf( __( '%1$d open / %2$d total', 'contactpanda-business' ), $row['open_count'], $row['task_count'] ) ) . '</td><td>' . esc_html( self::visibilities()[ $row['visibility'] ] ?? $row['visibility'] ) . '</td><td><a class="button cpb-icon-button" href="' . esc_url( $url ) . '" aria-label="' . esc_attr__( 'View project', 'contactpanda-business' ) . '"><span class="dashicons dashicons-visibility"></span></a></td><td><span class="cpb-status is-' . esc_attr( $row['status'] ) . '">' . esc_html( self::project_statuses()[ $row['status'] ] ?? $row['status'] ) . '</span></td></tr>';
		}
		echo '</tbody></table></section>';
	}

	private static function project_modal() {
		echo '<div id="cpb-new-project" class="cpb-modal" hidden><div class="cpb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpb-project-title"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="cpb-project-title">' . esc_html__( 'Create project', 'contactpanda-business' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_project">'; wp_nonce_field( 'contactpanda_business_save_project' );
		echo '<label><span>' . esc_html__( 'Project name', 'contactpanda-business' ) . '</span><input name="name" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="5"></textarea></label><label><span>' . esc_html__( 'Customer account', 'contactpanda-business' ) . '</span><select name="customer_user_id"><option value="0">' . esc_html__( 'Not assigned', 'contactpanda-business' ) . '</option>';
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name . ' — ' . $user->user_email ) . '</option>';
		echo '</select></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Person ID', 'contactpanda-business' ) . '</span><input type="number" min="0" name="contact_id"></label><label><span>' . esc_html__( 'Company ID', 'contactpanda-business' ) . '</span><input type="number" min="0" name="company_id"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer">' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal">' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><p><button class="button button-primary">' . esc_html__( 'Save project', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function render_board( $project_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ), ARRAY_A );
		if ( ! $project ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'This project does not exist.', 'contactpanda-business' ) . '</p></div>'; return; }
		$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,id", $project_id ), ARRAY_A );
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-projects' ) ) . '">← ' . esc_html__( 'Back to projects', 'contactpanda-business' ) . '</a></p><div class="cpb-project-heading"><div><h2>' . esc_html( $project['name'] ) . '</h2><p>' . esc_html( $project['description'] ) . '</p></div><div><button type="button" class="button button-primary" data-cpb-open="cpb-new-task">' . esc_html__( 'Create task', 'contactpanda-business' ) . '</button> <button type="button" class="button cpb-view-toggle" data-cpb-view="board" aria-pressed="true"><span class="dashicons dashicons-screenoptions"></span></button> <button type="button" class="button cpb-view-toggle" data-cpb-view="list" aria-pressed="false"><span class="dashicons dashicons-list-view"></span></button></div></div>';
		self::task_modal( $project_id );
		echo '<div class="cpb-board" data-cpb-project="' . esc_attr( $project_id ) . '">';
		foreach ( self::columns() as $key => $label ) { echo '<section class="cpb-column" data-cpb-column="' . esc_attr( $key ) . '"><header><h3>' . esc_html( $label ) . '</h3><span>' . esc_html( count( array_filter( $tasks, static function ( $task ) use ( $key ) { return $key === $task['board_column']; } ) ) ) . '</span></header><div class="cpb-column-body">'; foreach ( $tasks as $task ) if ( $key === $task['board_column'] ) self::task_card( $task ); echo '</div></section>'; }
		echo '</div><div class="cpb-list" hidden><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Task', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Column', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Due', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		foreach ( $tasks as $task ) { $url = self::task_url( $task['id'] ); echo '<tr><td><a href="' . esc_url( $url ) . '">' . esc_html( $task['title'] ) . '</a></td><td>' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . '</td><td>' . esc_html( $task['progress'] ) . '%</td><td>' . esc_html( self::due_label( $task['due_at'] ) ) . '</td><td>' . esc_html( self::visibilities()[ $task['visibility']] ?? $task['visibility'] ) . '</td></tr>'; }
		echo '</tbody></table></div>';
	}

	private static function task_modal( $project_id ) {
		echo '<div id="cpb-new-task" class="cpb-modal" hidden><div class="cpb-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="cpb-task-title"><button type="button" class="cpb-modal-close" data-cpb-close aria-label="' . esc_attr__( 'Close', 'contactpanda-business' ) . '">&times;</button><h2 id="cpb-task-title">' . esc_html__( 'Create task', 'contactpanda-business' ) . '</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form"><input type="hidden" name="action" value="contactpanda_business_save_task"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_save_task_' . $project_id );
		echo '<label><span>' . esc_html__( 'Title', 'contactpanda-business' ) . '</span><input name="title" required></label><label><span>' . esc_html__( 'Description', 'contactpanda-business' ) . '</span><textarea name="description" rows="6"></textarea></label><div class="cpb-form-grid"><label><span>' . esc_html__( 'Open status', 'contactpanda-business' ) . '</span><select name="open_status">'; foreach ( self::open_statuses() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Priority', 'contactpanda-business' ) . '</span><select name="priority">'; foreach ( self::priorities() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</option>'; echo '</select></label><label><span>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</span><input type="datetime-local" name="due_at"></label><label><span>' . esc_html__( 'Visibility', 'contactpanda-business' ) . '</span><select name="visibility"><option value="customer">' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal">' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></label></div><p><button class="button button-primary">' . esc_html__( 'Save task', 'contactpanda-business' ) . '</button> <button type="button" class="button" data-cpb-close>' . esc_html__( 'Cancel', 'contactpanda-business' ) . '</button></p></form></div></div>';
	}

	private static function task_card( $task ) {
		$progress = min( 100, absint( $task['progress'] ) );
		echo '<article class="cpb-task" draggable="true" data-cpb-task="' . esc_attr( $task['id'] ) . '"><a href="' . esc_url( self::task_url( $task['id'] ) ) . '"><strong>' . esc_html( $task['title'] ) . '</strong></a><div class="cpb-badges"><span class="cpb-badge is-' . esc_attr( $task['open_status'] ) . '">' . esc_html( self::open_statuses()[ $task['open_status']] ?? $task['open_status'] ) . '</span>' . ( 'internal' === $task['visibility'] ? '<span class="cpb-badge is-internal">' . esc_html__( 'Internal', 'contactpanda-business' ) . '</span>' : '' ) . '</div><div class="cpb-progress is-p' . esc_attr( self::progress_band( $progress ) ) . '"><span style="width:' . esc_attr( $progress ) . '%"></span></div><small>' . esc_html( $progress ) . '% · ' . esc_html( self::due_label( $task['due_at'] ) ) . '</small></article>';
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
		$wpdb->insert( $t['projects'], array( 'uuid' => wp_generate_uuid4(), 'contact_id' => absint( $_POST['contact_id'] ?? 0 ), 'company_id' => absint( $_POST['company_id'] ?? 0 ), 'customer_user_id' => absint( $_POST['customer_user_id'] ?? 0 ), 'name' => $name, 'description' => sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ), 'status' => 'active', 'visibility' => self::clean_visibility( $_POST['visibility'] ?? 'customer' ), 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) );
		self::event( (int) $wpdb->insert_id, 0, 'project_created', array( 'name' => $name ) ); self::redirect( true, __( 'The project was created.', 'contactpanda-business' ), (int) $wpdb->insert_id );
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
		self::event( $project_id, $task_id, 'task_saved', array( 'column' => $column, 'progress' => $data['progress'] ) ); self::redirect( true, __( 'The task was saved.', 'contactpanda-business' ), $project_id, $task_id );
	}

	public static function add_comment() {
		self::guard(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_add_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); if ( ! $task_id || '' === $body ) self::redirect( false, __( 'Please enter a comment.', 'contactpanda-business' ) );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d", $task_id ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => ! empty( $_POST['internal'] ) ? 'internal' : 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( $project_id, $task_id, 'comment_added', array() ); self::redirect( true, __( 'The comment was added.', 'contactpanda-business' ), $project_id, $task_id );
	}

	public static function trash_task() {
		self::guard(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_trash_task_' . $task_id ); global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d", $task_id ) ); $wpdb->update( $t['tasks'], array( 'deleted_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) ); self::event( $project_id, $task_id, 'task_trashed', array() ); self::redirect( true, __( 'The task was moved to the trash.', 'contactpanda-business' ), $project_id );
	}

	public static function move_task() {
		self::guard(); check_ajax_referer( 'contactpanda_business_move_task', 'nonce' ); $task_id = absint( $_POST['task_id'] ?? 0 ); $column = sanitize_key( wp_unslash( $_POST['column'] ?? '' ) ); if ( ! $task_id || ! isset( self::columns()[ $column ] ) ) wp_send_json_error( array( 'message' => __( 'Invalid task movement.', 'contactpanda-business' ) ), 400 );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task = $wpdb->get_row( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $task_id ), ARRAY_A ); if ( ! $task ) wp_send_json_error( array( 'message' => __( 'Task not found.', 'contactpanda-business' ) ), 404 );
		$wpdb->update( $t['tasks'], array( 'board_column' => $column, 'completed_at' => 'done' === $column ? current_time( 'mysql', true ) : null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $task_id ) ); self::event( (int) $task['project_id'], $task_id, 'task_moved', array( 'column' => $column ) ); wp_send_json_success();
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
			$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.*,p.name project_name FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.visibility='customer' AND x.deleted_at IS NULL AND p.customer_user_id=%d AND p.visibility='customer' AND p.deleted_at IS NULL", $task_id, $user_id ), ARRAY_A );
			if ( ! $task ) { echo '<p>' . esc_html__( 'This task is not available.', 'contactpanda-business' ) . '</p>'; return; }
			$comments = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['comments']}` WHERE task_id=%d AND visibility='customer' ORDER BY created_at", $task_id ), ARRAY_A );
			echo '<div class="cpb-account"><p><a href="' . esc_url( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ) . '">← ' . esc_html__( 'Back to projects', 'contactpanda-business' ) . '</a></p><h2>' . esc_html( $task['title'] ) . '</h2><p>' . nl2br( esc_html( $task['description'] ) ) . '</p><p><strong>' . esc_html__( 'Progress', 'contactpanda-business' ) . ':</strong> ' . esc_html( $task['progress'] ) . '% · ' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . '</p>';
			foreach ( $comments as $comment ) { $author = get_userdata( (int) $comment['author_user_id'] ); echo '<article class="cpb-comment"><header><strong>' . esc_html( $author ? $author->display_name : __( 'Customer', 'contactpanda-business' ) ) . '</strong><time>' . esc_html( get_date_from_gmt( $comment['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</time></header><p>' . nl2br( esc_html( $comment['body'] ) ) . '</p></article>'; }
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_comment"><input type="hidden" name="task_id" value="' . esc_attr( $task_id ) . '">'; wp_nonce_field( 'contactpanda_business_customer_comment_' . $task_id ); echo '<p><label>' . esc_html__( 'Comment', 'contactpanda-business' ) . '<textarea name="body" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Add comment', 'contactpanda-business' ) . '</button></form></div>';
			return;
		}
		$projects = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE customer_user_id=%d AND visibility='customer' AND deleted_at IS NULL ORDER BY updated_at DESC", $user_id ), ARRAY_A );
		echo '<div class="cpb-account"><h2>' . esc_html__( 'Projects & corrections', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Submit new tasks and follow customer-visible progress.', 'contactpanda-business' ) . '</p>';
		if ( ! $projects ) { echo '<p>' . esc_html__( 'No projects are assigned to your account.', 'contactpanda-business' ) . '</p></div>'; return; }
		foreach ( $projects as $project ) { echo '<section><h3>' . esc_html( $project['name'] ) . '</h3>'; $tasks = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE project_id=%d AND visibility='customer' AND deleted_at IS NULL ORDER BY updated_at DESC", $project['id'] ), ARRAY_A ); if ( $tasks ) { echo '<ul class="cpb-account-tasks">'; foreach ( $tasks as $task ) { $url = add_query_arg( 'business_task', absint( $task['id'] ), wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); echo '<li><a href="' . esc_url( $url ) . '"><strong>' . esc_html( $task['title'] ) . '</strong></a><span>' . esc_html( self::columns()[ $task['board_column']] ?? $task['board_column'] ) . ' · ' . esc_html( $task['progress'] ) . '%</span></li>'; } echo '</ul>'; } echo '<details><summary>' . esc_html__( 'Submit a new task', 'contactpanda-business' ) . '</summary><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_customer_task"><input type="hidden" name="project_id" value="' . esc_attr( $project['id'] ) . '">'; wp_nonce_field( 'contactpanda_business_customer_task_' . $project['id'] ); echo '<p><label>' . esc_html__( 'Title', 'contactpanda-business' ) . '<input name="title" required></label></p><p><label>' . esc_html__( 'Description', 'contactpanda-business' ) . '<textarea name="description" rows="5" required></textarea></label></p><button class="button">' . esc_html__( 'Submit task', 'contactpanda-business' ) . '</button></form></details></section>'; }
		echo '</div>';
	}

	public static function customer_task() {
		if ( ! is_user_logged_in() ) auth_redirect(); $project_id = absint( $_POST['project_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_task_' . $project_id ); global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $allowed = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND customer_user_id=%d AND visibility='customer' AND deleted_at IS NULL", $project_id, get_current_user_id() ) ); if ( ! $allowed ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); $title = sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ); $description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) ); if ( '' === $title || '' === $description ) { wp_safe_redirect( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); exit; } $now = current_time( 'mysql', true ); $wpdb->insert( $t['tasks'], array( 'uuid' => wp_generate_uuid4(), 'project_id' => $project_id, 'title' => $title, 'description' => $description, 'board_column' => 'open', 'open_status' => 'new', 'priority' => 'normal', 'visibility' => 'customer', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); self::event( $project_id, (int) $wpdb->insert_id, 'customer_task_created', array() ); wp_safe_redirect( wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); exit;
	}

	public static function customer_comment() {
		if ( ! is_user_logged_in() ) auth_redirect(); $task_id = absint( $_POST['task_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_customer_comment_' . $task_id ); $body = sanitize_textarea_field( wp_unslash( $_POST['body'] ?? '' ) ); global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$task = $wpdb->get_row( $wpdb->prepare( "SELECT x.id,x.project_id FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id WHERE x.id=%d AND x.visibility='customer' AND x.deleted_at IS NULL AND p.customer_user_id=%d AND p.visibility='customer' AND p.deleted_at IS NULL", $task_id, get_current_user_id() ), ARRAY_A );
		if ( ! $task || '' === $body ) wp_die( esc_html__( 'This task is not available.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		$wpdb->insert( $t['comments'], array( 'task_id' => $task_id, 'visibility' => 'customer', 'author_user_id' => get_current_user_id(), 'body' => $body, 'created_at' => current_time( 'mysql', true ) ) ); self::event( (int) $task['project_id'], $task_id, 'customer_comment_added', array() ); $url = add_query_arg( 'business_task', $task_id, wc_get_account_endpoint_url( 'projekte-korrekturen' ) ); wp_safe_redirect( $url ); exit;
	}

	private static function event( $project_id, $task_id, $type, $details ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $wpdb->insert( $t['events'], array( 'project_id' => $project_id, 'task_id' => $task_id, 'event_type' => $type, 'actor_user_id' => get_current_user_id(), 'details_json' => wp_json_encode( $details ), 'created_at' => current_time( 'mysql', true ) ) ); }
	private static function redirect( $success, $message, $project_id = 0, $task_id = 0 ) { set_transient( self::NOTICE . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS ); $args = array( 'page' => 'contactpanda-business-projects' ); if ( $project_id ) $args['project'] = $project_id; if ( $task_id ) $args['task'] = $task_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function task_url( $id ) { return add_query_arg( array( 'page' => 'contactpanda-business-projects', 'task' => absint( $id ) ), admin_url( 'admin.php' ) ); }
	private static function due_label( $date ) { if ( ! $date ) return __( 'No due date', 'contactpanda-business' ); $time = strtotime( $date . ' UTC' ); $today = strtotime( gmdate( 'Y-m-d' ) . ' UTC' ); if ( $time < time() ) return sprintf( __( 'Overdue: %s', 'contactpanda-business' ), wp_date( get_option( 'date_format' ), $time ) ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', $today ) ) return __( 'Due today', 'contactpanda-business' ); if ( gmdate( 'Y-m-d', $time ) === gmdate( 'Y-m-d', strtotime( '+1 day', $today ) ) ) return __( 'Due tomorrow', 'contactpanda-business' ); return wp_date( get_option( 'date_format' ), $time ); }
	private static function progress_band( $progress ) { if ( $progress <= 33 ) return 1; if ( $progress <= 66 ) return 2; return 3; }
	private static function clean_visibility( $value ) { return 'internal' === sanitize_key( wp_unslash( $value ) ) ? 'internal' : 'customer'; }
	private static function clean_priority( $value ) { $value = sanitize_key( wp_unslash( $value ) ); return isset( self::priorities()[ $value ] ) ? $value : 'normal'; }
	private static function columns() { return array( 'open' => __( 'Open', 'contactpanda-business' ), 'in_progress' => __( 'In progress', 'contactpanda-business' ), 'review' => __( 'Review', 'contactpanda-business' ), 'done' => __( 'Done', 'contactpanda-business' ) ); }
	private static function open_statuses() { return array( 'new' => __( 'New', 'contactpanda-business' ), 'question' => __( 'Question', 'contactpanda-business' ), 'waiting_customer' => __( 'Waiting for customer', 'contactpanda-business' ), 'planned' => __( 'Planned', 'contactpanda-business' ) ); }
	private static function priorities() { return array( 'low' => __( 'Low', 'contactpanda-business' ), 'normal' => __( 'Normal', 'contactpanda-business' ), 'high' => __( 'High', 'contactpanda-business' ), 'urgent' => __( 'Urgent', 'contactpanda-business' ) ); }
	private static function visibilities() { return array( 'customer' => __( 'Visible to customer', 'contactpanda-business' ), 'internal' => __( 'Internal only', 'contactpanda-business' ) ); }
	private static function project_statuses() { return array( 'active' => __( 'Active', 'contactpanda-business' ), 'on_hold' => __( 'On hold', 'contactpanda-business' ), 'completed' => __( 'Completed', 'contactpanda-business' ), 'archived' => __( 'Archived', 'contactpanda-business' ) ); }
	private static function guard() { if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); }
}
