<?php

defined( 'ABSPATH' ) || exit;

/** Cross-project task list, project areas, trash handling, duplication, and reports. */
final class ContactPanda_Business_Workspace {
	const NOTICE = 'contactpanda_business_notice_';

	public static function boot() {
		add_action( 'admin_post_contactpanda_business_project_action', array( __CLASS__, 'project_action' ) );
		add_action( 'admin_post_contactpanda_business_task_action', array( __CLASS__, 'task_action' ) );
		add_action( 'admin_post_contactpanda_business_save_area', array( __CLASS__, 'save_area' ) );
		add_action( 'admin_post_contactpanda_business_export_report', array( __CLASS__, 'export_report' ) );
	}

	public static function render_tasks() {
		self::guard( 'view', 'task' );
		global $wpdb;
		$t = ContactPanda_Business_Schema::table_names();
		$search = sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$project_id = absint( $_GET['project_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$column = sanitize_key( wp_unslash( $_GET['task_status'] ?? '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$assigned = isset( $_GET['assigned_user_id'] ) ? absint( $_GET['assigned_user_id'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$trash = ! empty( $_GET['trash'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$where = array( $trash ? 'x.deleted_at IS NOT NULL' : 'x.deleted_at IS NULL', 'p.deleted_at IS NULL' );
		$args = array();
		if ( $search ) { $like = '%' . $wpdb->esc_like( $search ) . '%'; $where[] = '(x.title LIKE %s OR x.description LIKE %s OR p.name LIKE %s)'; array_push( $args, $like, $like, $like ); }
		if ( $project_id ) { $where[] = 'x.project_id=%d'; $args[] = $project_id; }
		if ( $column && in_array( $column, array( 'open', 'in_progress', 'review', 'done' ), true ) ) { $where[] = 'x.board_column=%s'; $args[] = $column; }
		if ( $assigned >= 0 ) { $where[] = 'x.assigned_user_id=%d'; $args[] = $assigned; }
		$sql = "SELECT x.*,p.name project_name,a.name area_name FROM `{$t['tasks']}` x JOIN `{$t['projects']}` p ON p.id=x.project_id LEFT JOIN `{$t['areas']}` a ON a.id=x.area_id WHERE " . implode( ' AND ', $where ) . ' ORDER BY x.updated_at DESC,x.id DESC';
		$tasks = $args ? $wpdb->get_results( $wpdb->prepare( $sql, $args ), ARRAY_A ) : $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$projects = $wpdb->get_results( "SELECT id,name FROM `{$t['projects']}` WHERE deleted_at IS NULL ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		self::header( __( 'Tasks', 'contactpanda-business' ), __( 'Search and manage tasks across all projects.', 'contactpanda-business' ) );
		echo '<div class="cpb-toolbar"><form method="get"><input type="hidden" name="page" value="contactpanda-business-tasks"><input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search tasks', 'contactpanda-business' ) . '"><select name="project_id"><option value="0">' . esc_html__( 'All projects', 'contactpanda-business' ) . '</option>';
		foreach ( $projects as $project ) echo '<option value="' . esc_attr( $project['id'] ) . '"' . selected( $project_id, $project['id'], false ) . '>' . esc_html( $project['name'] ) . '</option>';
		echo '</select><select name="task_status"><option value="">' . esc_html__( 'All statuses', 'contactpanda-business' ) . '</option>';
		foreach ( self::columns() as $key => $label ) echo '<option value="' . esc_attr( $key ) . '"' . selected( $column, $key, false ) . '>' . esc_html( $label ) . '</option>';
		echo '</select><select name="assigned_user_id"><option value="-1">' . esc_html__( 'All assignees', 'contactpanda-business' ) . '</option><option value="0"' . selected( $assigned, 0, false ) . '>' . esc_html__( 'Unassigned', 'contactpanda-business' ) . '</option>';
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $assigned, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		echo '</select><button class="button cpb-icon-button" aria-label="' . esc_attr__( 'Search', 'contactpanda-business' ) . '"><span class="dashicons dashicons-search"></span></button></form><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'contactpanda-business-tasks', 'trash' => $trash ? 0 : 1 ), admin_url( 'admin.php' ) ) ) . '">' . esc_html( $trash ? __( 'Back to tasks', 'contactpanda-business' ) : __( 'Trash', 'contactpanda-business' ) ) . '</a></div>';
		echo '<section class="cpb-card"><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Task', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Project', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Area', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Assignee', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Due by', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Progress', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $tasks ) echo '<tr><td colspan="8" class="cpb-empty">' . esc_html__( 'No tasks were found.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $tasks as $task ) {
			$user = $task['assigned_user_id'] ? get_userdata( (int) $task['assigned_user_id'] ) : null;
			$project_url = add_query_arg( array( 'page' => 'contactpanda-business-projects', 'project' => $task['project_id'] ), admin_url( 'admin.php' ) );
			echo '<tr><td><strong>' . esc_html( $task['title'] ) . '</strong><br><small>' . esc_html( wp_trim_words( $task['description'], 15 ) ) . '</small></td><td><a href="' . esc_url( $project_url ) . '">' . esc_html( $task['project_name'] ) . '</a></td><td>' . esc_html( $task['area_name'] ?: '—' ) . '</td><td>' . esc_html( $user ? $user->display_name : __( 'Unassigned', 'contactpanda-business' ) ) . '</td><td>' . esc_html( self::date_label( $task['due_at'] ) ) . '</td><td>' . esc_html( absint( $task['progress'] ) ) . '%</td><td>' . esc_html( self::columns()[ $task['board_column'] ] ?? $task['board_column'] ) . '</td><td class="cpb-actions">';
			if ( $trash ) { self::action_form( 'task', 'restore', $task['id'], __( 'Restore', 'contactpanda-business' ), 'controls-repeat', false ); self::action_form( 'task', 'delete', $task['id'], __( 'Delete permanently', 'contactpanda-business' ), 'trash', true ); }
			else { echo '<a class="button cpb-icon-button" href="' . esc_url( add_query_arg( array( 'page' => 'contactpanda-business-projects', 'task' => $task['id'] ), admin_url( 'admin.php' ) ) ) . '" aria-label="' . esc_attr__( 'View task', 'contactpanda-business' ) . '"><span class="dashicons dashicons-visibility"></span></a>'; self::action_form( 'task', 'duplicate', $task['id'], __( 'Duplicate task', 'contactpanda-business' ), 'admin-page', false ); self::action_form( 'task', 'trash', $task['id'], __( 'Move to trash', 'contactpanda-business' ), 'trash', true ); }
			echo '</td></tr>';
		}
		echo '</tbody></table></section></div>';
	}

	public static function render_board_controls( $project_id, $tasks ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$areas = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `{$t['areas']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,name", $project_id ), ARRAY_A );
		echo '<section class="cpb-card cpb-board-tools"><div class="cpb-toolbar"><div><strong>' . esc_html__( 'Board filters', 'contactpanda-business' ) . '</strong><div class="cpb-filter-options"><label><input type="checkbox" data-cpb-filter-area value="0" checked> ' . esc_html__( 'Without area', 'contactpanda-business' ) . '</label>';
		foreach ( $areas as $area ) echo '<label><input type="checkbox" data-cpb-filter-area value="' . esc_attr( $area['id'] ) . '" checked> <span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( self::clean_color( $area['color'] ) ) . '"></span>' . esc_html( $area['name'] ) . '</label>';
		echo '</div></div><div><label><span class="screen-reader-text">' . esc_html__( 'Assignee', 'contactpanda-business' ) . '</span><select data-cpb-filter-assignee><option value="all">' . esc_html__( 'All assignees', 'contactpanda-business' ) . '</option><option value="me">' . esc_html__( 'My tasks', 'contactpanda-business' ) . '</option><option value="0">' . esc_html__( 'Unassigned', 'contactpanda-business' ) . '</option>';
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name ) . '</option>';
		echo '</select></label> <button type="button" class="button" data-cpb-reset-filters>' . esc_html__( 'Reset filters', 'contactpanda-business' ) . '</button></div></div><details><summary>' . esc_html__( 'Manage project areas', 'contactpanda-business' ) . '</summary><div class="cpb-area-list">';
		foreach ( $areas as $area ) echo '<span class="cpb-area-chip"><span class="cpb-project-color" style="--cpb-project-color:' . esc_attr( self::clean_color( $area['color'] ) ) . '"></span>' . esc_html( $area['name'] ) . '</span>';
		echo '</div><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-form-grid"><input type="hidden" name="action" value="contactpanda_business_save_area"><input type="hidden" name="project_id" value="' . esc_attr( $project_id ) . '">'; wp_nonce_field( 'contactpanda_business_save_area_' . $project_id );
		echo '<label><span>' . esc_html__( 'Area name', 'contactpanda-business' ) . '</span><input name="name" required></label><label><span>' . esc_html__( 'Color', 'contactpanda-business' ) . '</span><input type="color" name="color" value="#3d5cf5"></label><label><span>' . esc_html__( 'Responsible person', 'contactpanda-business' ) . '</span><select name="assigned_user_id"><option value="0">' . esc_html__( 'Unassigned', 'contactpanda-business' ) . '</option>';
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '">' . esc_html( $user->display_name ) . '</option>';
		echo '</select></label><p><button class="button">' . esc_html__( 'Add area', 'contactpanda-business' ) . '</button></p></form></details></section>';
	}

	public static function area_options( $project_id, $selected = 0 ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$areas = $wpdb->get_results( $wpdb->prepare( "SELECT id,name FROM `{$t['areas']}` WHERE project_id=%d AND deleted_at IS NULL ORDER BY sort_order,name", $project_id ), ARRAY_A );
		$html = '<option value="0">' . esc_html__( 'No area', 'contactpanda-business' ) . '</option>';
		foreach ( $areas as $area ) $html .= '<option value="' . esc_attr( $area['id'] ) . '"' . selected( $selected, $area['id'], false ) . '>' . esc_html( $area['name'] ) . '</option>';
		return $html;
	}

	public static function assignee_options( $selected = 0 ) {
		$html = '<option value="0">' . esc_html__( 'Unassigned', 'contactpanda-business' ) . '</option>';
		foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) $html .= '<option value="' . esc_attr( $user->ID ) . '"' . selected( $selected, $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
		return $html;
	}

	public static function task_card_actions( $task_id, $project_id = 0 ) {
		echo '<span class="cpb-card-actions">';
		self::action_form( 'task', 'duplicate', absint( $task_id ), __( 'Duplicate task', 'contactpanda-business' ), 'admin-page', false, absint( $project_id ) );
		self::action_form( 'task', 'trash', absint( $task_id ), __( 'Move task to trash', 'contactpanda-business' ), 'trash', true, absint( $project_id ) );
		echo '</span>';
	}

	public static function project_action_buttons( $project_id, $trash = false ) {
		if ( $trash ) { self::action_form( 'project', 'restore', absint( $project_id ), __( 'Restore project', 'contactpanda-business' ), 'controls-repeat', false ); self::action_form( 'project', 'delete', absint( $project_id ), __( 'Delete project permanently', 'contactpanda-business' ), 'trash', true ); return; }
		self::action_form( 'project', 'trash', absint( $project_id ), __( 'Move project to trash', 'contactpanda-business' ), 'trash', true );
	}

	public static function project_action() {
		$id = absint( $_POST['object_id'] ?? 0 ); $action = sanitize_key( wp_unslash( $_POST['object_action'] ?? '' ) ); check_admin_referer( 'contactpanda_business_project_action_' . $id );
		self::guard( 'delete' === $action || 'trash' === $action ? 'delete' : 'edit', 'project', $id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project = $wpdb->get_row( $wpdb->prepare( "SELECT id,name FROM `{$t['projects']}` WHERE id=%d", $id ), ARRAY_A );
		if ( ! $project ) self::redirect( false, __( 'The project does not exist.', 'contactpanda-business' ), 'projects' );
		if ( 'trash' === $action ) $wpdb->update( $t['projects'], array( 'deleted_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		elseif ( 'restore' === $action ) $wpdb->update( $t['projects'], array( 'deleted_at' => null ), array( 'id' => $id ) );
		elseif ( 'delete' === $action ) {
			$task_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM `{$t['tasks']}` WHERE project_id=%d", $id ) );
			if ( $task_ids ) { $ids = implode( ',', array_map( 'absint', $task_ids ) ); $wpdb->query( "DELETE FROM `{$t['comments']}` WHERE task_id IN ($ids)" ); $wpdb->query( "DELETE FROM `{$t['attachments']}` WHERE task_id IN ($ids)" ); $wpdb->query( "DELETE FROM `{$t['checklist']}` WHERE task_id IN ($ids)" ); } // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- integer-only identifiers.
			$wpdb->delete( $t['events'], array( 'project_id' => $id ) ); $wpdb->delete( $t['participants'], array( 'project_id' => $id ) ); $wpdb->delete( $t['project_documents'], array( 'project_id' => $id ) ); $wpdb->delete( $t['payments'], array( 'project_id' => $id ) ); $wpdb->delete( $t['project_steps'], array( 'project_id' => $id ) ); $wpdb->delete( $t['areas'], array( 'project_id' => $id ) ); $wpdb->delete( $t['tasks'], array( 'project_id' => $id ) ); $wpdb->delete( $t['projects'], array( 'id' => $id ) );
		}
		else self::redirect( false, __( 'The requested project action is invalid.', 'contactpanda-business' ), 'projects' );
		ContactPanda_Business_Access::audit( 'project_' . $action, 'project', $id, array(), array( 'name' => $project['name'] ) );
		self::redirect( true, 'restore' === $action ? __( 'The project was restored.', 'contactpanda-business' ) : ( 'delete' === $action ? __( 'The project and its related records were deleted permanently.', 'contactpanda-business' ) : __( 'The project was moved to the trash.', 'contactpanda-business' ) ), 'projects' );
	}

	public static function task_action() {
		$id = absint( $_POST['object_id'] ?? 0 ); $action = sanitize_key( wp_unslash( $_POST['object_action'] ?? '' ) ); check_admin_referer( 'contactpanda_business_task_action_' . $id );
		$required_action = 'duplicate' === $action ? 'create' : ( in_array( $action, array( 'trash', 'delete' ), true ) ? 'delete' : 'edit' );
		self::guard( $required_action, 'task', 'duplicate' === $action ? 0 : $id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $task = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['tasks']}` WHERE id=%d", $id ), ARRAY_A );
		if ( ! $task ) self::redirect( false, __( 'The task does not exist.', 'contactpanda-business' ), 'tasks' );
		if ( 'trash' === $action ) $wpdb->update( $t['tasks'], array( 'deleted_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		elseif ( 'restore' === $action ) $wpdb->update( $t['tasks'], array( 'deleted_at' => null, 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $id ) );
		elseif ( 'duplicate' === $action ) {
			$source_id = $id; unset( $task['id'], $task['completed_at'], $task['deleted_at'] ); $task['uuid'] = wp_generate_uuid4(); $task['title'] = sprintf( __( 'Copy of %s', 'contactpanda-business' ), $task['title'] ); $task['created_by'] = get_current_user_id(); $task['created_at'] = current_time( 'mysql', true ); $task['updated_at'] = $task['created_at']; $task['revision'] = 1; $task['source_hash'] = '';
			$wpdb->insert( $t['tasks'], $task ); $new_task_id = (int) $wpdb->insert_id;
			if ( $new_task_id ) {
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT attachment_id,visibility FROM `{$t['attachments']}` WHERE task_id=%d", $source_id ), ARRAY_A ) as $attachment ) $wpdb->insert( $t['attachments'], array( 'task_id' => $new_task_id, 'attachment_id' => absint( $attachment['attachment_id'] ), 'visibility' => $attachment['visibility'], 'uploaded_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
				foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT title,sort_order FROM `{$t['checklist']}` WHERE task_id=%d ORDER BY sort_order,id", $source_id ), ARRAY_A ) as $item ) $wpdb->insert( $t['checklist'], array( 'task_id' => $new_task_id, 'title' => $item['title'], 'sort_order' => (int) $item['sort_order'], 'created_by' => get_current_user_id(), 'created_at' => current_time( 'mysql', true ) ) );
			}
		} elseif ( 'delete' === $action ) { $wpdb->delete( $t['comments'], array( 'task_id' => $id ) ); $wpdb->delete( $t['attachments'], array( 'task_id' => $id ) ); $wpdb->delete( $t['checklist'], array( 'task_id' => $id ) ); $wpdb->delete( $t['events'], array( 'task_id' => $id ) ); $wpdb->delete( $t['tasks'], array( 'id' => $id ) ); }
		else self::redirect( false, __( 'The requested task action is invalid.', 'contactpanda-business' ), 'tasks' );
		ContactPanda_Business_Access::audit( 'task_' . $action, 'task', 'delete' === $action ? 0 : $id, array(), array( 'project_id' => absint( $task['project_id'] ) ) );
		$return_project_id = absint( $_POST['return_project_id'] ?? 0 );
		self::redirect( true, 'duplicate' === $action ? __( 'The task was duplicated.', 'contactpanda-business' ) : ( 'restore' === $action ? __( 'The task was restored.', 'contactpanda-business' ) : ( 'delete' === $action ? __( 'The task was deleted permanently.', 'contactpanda-business' ) : __( 'The task was moved to the trash.', 'contactpanda-business' ) ) ), $return_project_id ? 'projects' : 'tasks', $return_project_id );
	}

	public static function save_area() {
		$project_id = absint( $_POST['project_id'] ?? 0 ); self::guard( 'edit', 'project', $project_id ); check_admin_referer( 'contactpanda_business_save_area_' . $project_id ); $name = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		if ( ! $project_id || ! $name ) self::redirect( false, __( 'Enter an area name.', 'contactpanda-business' ), 'projects', $project_id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true ); $wpdb->insert( $t['areas'], array( 'project_id' => $project_id, 'name' => $name, 'color' => self::clean_color( wp_unslash( $_POST['color'] ?? '' ) ), 'assigned_user_id' => absint( $_POST['assigned_user_id'] ?? 0 ), 'visibility' => 'customer', 'status' => 'active', 'created_at' => $now, 'updated_at' => $now ) );
		if ( $wpdb->insert_id ) ContactPanda_Business_Access::audit( 'project_area_created', 'project', $project_id, array(), array( 'area_id' => absint( $wpdb->insert_id ) ) );
		self::redirect( (bool) $wpdb->insert_id, $wpdb->insert_id ? __( 'The project area was added.', 'contactpanda-business' ) : __( 'The project area could not be added.', 'contactpanda-business' ), 'projects', $project_id );
	}

	public static function export_report() {
		$project_id = absint( $_GET['project_id'] ?? 0 ); self::guard( 'export', 'project', $project_id ); $format = sanitize_key( $_GET['format'] ?? 'html' ); check_admin_referer( 'contactpanda_business_export_report_' . $project_id );
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $project = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ), ARRAY_A );
		if ( ! $project ) wp_die( esc_html__( 'The project does not exist.', 'contactpanda-business' ) );
		$tasks = $wpdb->get_results( $wpdb->prepare( "SELECT x.*,a.name area_name FROM `{$t['tasks']}` x LEFT JOIN `{$t['areas']}` a ON a.id=x.area_id WHERE x.project_id=%d AND x.deleted_at IS NULL AND x.visibility='customer' ORDER BY a.name,x.board_column,x.sort_order,x.id", $project_id ), ARRAY_A );
		$title = sprintf( __( 'Project report: %s', 'contactpanda-business' ), $project['name'] ); $html = self::report_html( $title, $project, $tasks );
		if ( 'docx' === $format ) self::send_docx( $project_id, $title, $project, $tasks );
		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fully escaped report markup.
		exit;
	}

	private static function send_docx( $project_id, $title, $project, $tasks ) {
		if ( ! class_exists( 'ZipArchive' ) ) wp_die( esc_html__( 'The PHP ZIP extension is required to create a Word report.', 'contactpanda-business' ) );
		$file = wp_tempnam( 'contactpanda-business-report-' . $project_id . '.docx' ); $zip = new ZipArchive();
		if ( ! $file || true !== $zip->open( $file, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) wp_die( esc_html__( 'The Word report could not be created.', 'contactpanda-business' ) );
		$zip->addFromString( '[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>' );
		$zip->addFromString( '_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>' );
		$paragraphs = array( $title, $project['description'], sprintf( __( 'Created: %s', 'contactpanda-business' ), wp_date( get_option( 'date_format' ) ) ) );
		foreach ( $tasks as $task ) { $paragraphs[] = '[CP-TASK:' . absint( $task['id'] ) . ']'; $paragraphs[] = $task['title']; $paragraphs[] = ( $task['area_name'] ?: __( 'No area', 'contactpanda-business' ) ) . ' · ' . ( self::columns()[ $task['board_column'] ] ?? $task['board_column'] ) . ' · ' . absint( $task['progress'] ) . '%'; $paragraphs[] = $task['description']; $paragraphs[] = __( 'Customer response', 'contactpanda-business' ) . ':'; $paragraphs[] = '________________________________________________________________'; }
		$body = ''; foreach ( $paragraphs as $paragraph ) $body .= '<w:p><w:r><w:t xml:space="preserve">' . self::xml( $paragraph ) . '</w:t></w:r></w:p>';
		$document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr></w:body></w:document>';
		$zip->addFromString( 'word/document.xml', $document ); $zip->close(); nocache_headers(); header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' ); header( 'Content-Disposition: attachment; filename="project-report-' . absint( $project_id ) . '.docx"' ); header( 'Content-Length: ' . filesize( $file ) ); readfile( $file ); wp_delete_file( $file ); exit; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_readfile
	}

	private static function report_html( $title, $project, $tasks ) {
		$color = self::clean_color( $project['color'] );
		$out = '<!doctype html><html><head><meta charset="utf-8"><title>' . esc_html( $title ) . '</title><style>@page{size:A4 landscape;margin:12mm}body{font-family:Arial,sans-serif;color:#273548;margin:0}h1{border-bottom:5px solid ' . esc_attr( $color ) . ';padding-bottom:10px}.meta{color:#667085;font-size:11px}.no-print{margin-bottom:16px}table{width:100%;border-collapse:collapse;table-layout:fixed}th,td{border:1px solid #aeb8c4;padding:8px;vertical-align:top;text-align:left;overflow-wrap:anywhere}th{background:#eef2f7}.col-number{width:5%}.col-task{width:43%}.col-status{width:20%}.col-comment{width:32%}.task-row{page-break-inside:avoid}.customer-comment{height:72px}.task-description{margin-top:6px;white-space:pre-wrap}.task-marker{display:block;margin-top:8px;color:#7a8593;font-size:9px}@media print{.no-print{display:none}body{font-size:10pt}}</style></head><body><div class="no-print"><button onclick="window.print()">' . esc_html__( 'Print or save as PDF', 'contactpanda-business' ) . '</button></div><h1>' . esc_html( $title ) . '</h1><p>' . nl2br( esc_html( $project['description'] ) ) . '</p><p class="meta">' . esc_html( wp_date( get_option( 'date_format' ) ) ) . '</p><table><thead><tr><th class="col-number">' . esc_html__( 'No.', 'contactpanda-business' ) . '</th><th class="col-task">' . esc_html__( 'To-do / correction', 'contactpanda-business' ) . '</th><th class="col-status">' . esc_html__( 'Status / solution', 'contactpanda-business' ) . '</th><th class="col-comment">' . esc_html__( 'Customer comments', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		foreach ( $tasks as $index => $task ) {
			$status = ( self::columns()[ $task['board_column'] ] ?? $task['board_column'] ) . ' · ' . absint( $task['progress'] ) . '%';
			$out .= '<tr class="task-row"><td>' . esc_html( $index + 1 ) . '</td><td><strong>' . esc_html( $task['title'] ) . '</strong>' . ( $task['description'] ? '<div class="task-description">' . nl2br( esc_html( $task['description'] ) ) . '</div>' : '' ) . '<span class="task-marker">[CP-TASK:' . esc_html( $task['id'] ) . ']</span></td><td><strong>' . esc_html( $status ) . '</strong><br><span class="meta">' . esc_html( $task['area_name'] ?: __( 'No area', 'contactpanda-business' ) ) . '</span></td><td class="customer-comment">&nbsp;</td></tr>';
		}
		if ( ! $tasks ) $out .= '<tr><td colspan="4">' . esc_html__( 'No customer-visible tasks are available.', 'contactpanda-business' ) . '</td></tr>';
		return $out . '</tbody></table></body></html>';
	}
	private static function xml( $value ) { return htmlspecialchars( (string) $value, ENT_QUOTES | ENT_XML1, 'UTF-8' ); }

	private static function action_form( $type, $action, $id, $label, $icon, $confirm, $return_project_id = 0 ) {
		$permission = 'edit';
		$permission_id = absint( $id );
		if ( in_array( $action, array( 'trash', 'delete' ), true ) ) $permission = 'delete';
		elseif ( 'duplicate' === $action ) { $permission = 'create'; $permission_id = 0; }
		if ( ! ContactPanda_Business_Access::can( $permission, $type, $permission_id ) ) return;
		$handler = 'project' === $type ? 'contactpanda_business_project_action' : 'contactpanda_business_task_action'; $nonce = 'contactpanda_business_' . $type . '_action_' . $id;
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="cpb-inline-form"' . ( $confirm ? ' data-cpb-confirm="' . esc_attr__( 'This action also affects related records. Do you want to continue?', 'contactpanda-business' ) . '"' : '' ) . '><input type="hidden" name="action" value="' . esc_attr( $handler ) . '"><input type="hidden" name="object_action" value="' . esc_attr( $action ) . '"><input type="hidden" name="object_id" value="' . esc_attr( $id ) . '">'; if ( $return_project_id ) echo '<input type="hidden" name="return_project_id" value="' . esc_attr( $return_project_id ) . '">'; wp_nonce_field( $nonce ); echo '<button class="button cpb-icon-button" aria-label="' . esc_attr( $label ) . '"><span class="dashicons dashicons-' . esc_attr( $icon ) . '"></span></button></form>';
	}

	private static function header( $title, $description ) { echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html( $title ) . '</h1><p>' . esc_html( $description ) . '</p></div></header>'; $notice = get_transient( self::NOTICE . get_current_user_id() ); if ( is_array( $notice ) ) { delete_transient( self::NOTICE . get_current_user_id() ); echo '<div class="notice notice-' . ( $notice['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; } }
	private static function redirect( $success, $message, $target, $project_id = 0 ) { set_transient( self::NOTICE . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS ); $page = 'tasks' === $target ? 'contactpanda-business-tasks' : 'contactpanda-business-projects'; $args = array( 'page' => $page ); if ( $project_id ) $args['project'] = $project_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function columns() { return array( 'open' => __( 'Open', 'contactpanda-business' ), 'in_progress' => __( 'In progress', 'contactpanda-business' ), 'review' => __( 'Review', 'contactpanda-business' ), 'done' => __( 'Done', 'contactpanda-business' ) ); }
	private static function date_label( $date ) { return $date ? wp_date( get_option( 'date_format' ), strtotime( $date . ' UTC' ) ) : '—'; }
	private static function clean_color( $value ) { return function_exists( 'contactpanda_sanitize_css_color' ) ? contactpanda_sanitize_css_color( $value, '#3d5cf5' ) : ( sanitize_hex_color( $value ) ?: '#3d5cf5' ); }
	private static function guard( $action = 'view', $resource_type = 'business', $resource_id = 0 ) {
		if ( ! contactpanda_business_module_entitled( 'project_management' ) ) wp_die( esc_html__( 'A valid ContactPanda Business license is required.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
		if ( ! ContactPanda_Business_Access::can( $action, $resource_type, $resource_id ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) );
	}
}
