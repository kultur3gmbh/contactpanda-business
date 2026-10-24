<?php

defined( 'ABSPATH' ) || exit;

/** Staged task imports shared by file upload and MCP. */
final class ContactPanda_Business_Imports {
	const NOTICE = 'contactpanda_business_import_notice_';

	public static function boot() {
		add_action( 'admin_post_contactpanda_business_upload_import', array( __CLASS__, 'upload' ) );
		add_action( 'admin_post_contactpanda_business_apply_import', array( __CLASS__, 'apply' ) );
	}

	public static function render() {
		self::guard(); $import_id = absint( $_GET['import_id'] ?? 0 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		self::header();
		if ( $import_id ) self::render_review( $import_id ); else self::render_upload();
		echo '</div>';
	}

	private static function render_upload() {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $imports = $wpdb->get_results( "SELECT * FROM `{$t['imports']}` ORDER BY created_at DESC LIMIT 50", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<section class="cpb-card"><h2>' . esc_html__( 'Upload corrections or tasks', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'CSV, Excel, Word, text, and MCP imports always open a review list before anything is added to the live task board.', 'contactpanda-business' ) . '</p><form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_upload_import">'; wp_nonce_field( 'contactpanda_business_upload_import' ); echo '<input type="file" name="import_file" accept=".csv,.xlsx,.docx,.txt" required> <button class="button button-primary">' . esc_html__( 'Upload and review', 'contactpanda-business' ) . '</button></form></section>';
		echo '<section class="cpb-card"><h2>' . esc_html__( 'Import drafts', 'contactpanda-business' ) . '</h2><table class="widefat striped"><thead><tr><th>' . esc_html__( 'Source', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Status', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Created', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Actions', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		if ( ! $imports ) echo '<tr><td colspan="4">' . esc_html__( 'No import drafts are available.', 'contactpanda-business' ) . '</td></tr>';
		foreach ( $imports as $import ) echo '<tr><td>' . esc_html( $import['source_name'] ?: $import['source_type'] ) . '</td><td>' . esc_html( $import['status'] ) . '</td><td>' . esc_html( get_date_from_gmt( $import['created_at'], get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ) . '</td><td><a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'contactpanda-business-import', 'import_id' => $import['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Open review', 'contactpanda-business' ) . '</a></td></tr>';
		echo '</tbody></table></section>';
	}

	private static function render_review( $import_id ) {
		global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $import = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['imports']}` WHERE id=%d", $import_id ), ARRAY_A );
		if ( ! $import ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'The import draft does not exist.', 'contactpanda-business' ) . '</p></div>'; return; }
		$items = $wpdb->get_results( $wpdb->prepare( "SELECT i.*,x.title match_title,x.board_column match_status FROM `{$t['import_items']}` i LEFT JOIN `{$t['tasks']}` x ON x.id=i.match_task_id WHERE i.import_id=%d ORDER BY i.id", $import_id ), ARRAY_A );
		$projects = $wpdb->get_results( "SELECT id,name FROM `{$t['projects']}` WHERE deleted_at IS NULL ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$areas = $wpdb->get_results( "SELECT id,project_id,name FROM `{$t['areas']}` WHERE deleted_at IS NULL ORDER BY name", ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=contactpanda-business-import' ) ) . '">← ' . esc_html__( 'Back to imports', 'contactpanda-business' ) . '</a></p><section class="cpb-card"><h2>' . esc_html__( 'Review imported tasks', 'contactpanda-business' ) . '</h2><p>' . esc_html__( 'Nothing is written to the task board until you confirm this list.', 'contactpanda-business' ) . '</p><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="contactpanda_business_apply_import"><input type="hidden" name="import_id" value="' . esc_attr( $import_id ) . '">'; wp_nonce_field( 'contactpanda_business_apply_import_' . $import_id );
		echo '<div class="cpb-import-scroll"><table class="widefat striped cpb-import-table"><thead><tr><th>' . esc_html__( 'Use', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Task', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Project and area', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Assignee and due date', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Existing task', 'contactpanda-business' ) . '</th><th>' . esc_html__( 'Import action', 'contactpanda-business' ) . '</th></tr></thead><tbody>';
		foreach ( $items as $item ) {
			echo '<tr><td><input type="checkbox" name="items[' . esc_attr( $item['id'] ) . '][selected]" value="1"' . checked( $item['selected'], 1, false ) . '></td><td><input class="widefat" name="items[' . esc_attr( $item['id'] ) . '][title]" value="' . esc_attr( $item['title'] ) . '" required><textarea class="widefat" rows="4" name="items[' . esc_attr( $item['id'] ) . '][description]">' . esc_textarea( $item['description'] ) . '</textarea><details><summary>' . esc_html__( 'Original source text', 'contactpanda-business' ) . '</summary><pre>' . esc_html( $item['source_text'] ) . '</pre></details></td><td><select name="items[' . esc_attr( $item['id'] ) . '][project_id]" data-cpb-import-project><option value="0">' . esc_html__( 'Select project', 'contactpanda-business' ) . '</option>';
			foreach ( $projects as $project ) echo '<option value="' . esc_attr( $project['id'] ) . '"' . selected( $item['project_id'], $project['id'], false ) . '>' . esc_html( $project['name'] ) . '</option>';
			echo '</select><select name="items[' . esc_attr( $item['id'] ) . '][area_id]" data-cpb-import-area><option value="0">' . esc_html__( 'No area', 'contactpanda-business' ) . '</option>';
			foreach ( $areas as $area ) echo '<option value="' . esc_attr( $area['id'] ) . '" data-project="' . esc_attr( $area['project_id'] ) . '"' . selected( $item['area_id'], $area['id'], false ) . '>' . esc_html( $area['name'] ) . '</option>';
			echo '</select></td><td><select name="items[' . esc_attr( $item['id'] ) . '][assigned_user_id]"><option value="0">' . esc_html__( 'Unassigned', 'contactpanda-business' ) . '</option>';
			foreach ( get_users( array( 'orderby' => 'display_name' ) ) as $user ) echo '<option value="' . esc_attr( $user->ID ) . '"' . selected( $item['assigned_user_id'], $user->ID, false ) . '>' . esc_html( $user->display_name ) . '</option>';
			echo '</select><input type="datetime-local" name="items[' . esc_attr( $item['id'] ) . '][due_at]" value="' . esc_attr( $item['due_at'] ? gmdate( 'Y-m-d\TH:i', strtotime( $item['due_at'] ) ) : '' ) . '"><select name="items[' . esc_attr( $item['id'] ) . '][priority]"><option value="low"' . selected( $item['priority'], 'low', false ) . '>' . esc_html__( 'Low', 'contactpanda-business' ) . '</option><option value="normal"' . selected( $item['priority'], 'normal', false ) . '>' . esc_html__( 'Normal', 'contactpanda-business' ) . '</option><option value="high"' . selected( $item['priority'], 'high', false ) . '>' . esc_html__( 'High', 'contactpanda-business' ) . '</option><option value="urgent"' . selected( $item['priority'], 'urgent', false ) . '>' . esc_html__( 'Urgent', 'contactpanda-business' ) . '</option></select><select name="items[' . esc_attr( $item['id'] ) . '][visibility]"><option value="customer"' . selected( $item['visibility'], 'customer', false ) . '>' . esc_html__( 'Visible to customer', 'contactpanda-business' ) . '</option><option value="internal"' . selected( $item['visibility'], 'internal', false ) . '>' . esc_html__( 'Internal only', 'contactpanda-business' ) . '</option></select></td><td>';
			if ( $item['match_task_id'] ) echo '<strong>' . esc_html( $item['match_title'] ) . '</strong><br><small>' . esc_html( sprintf( __( '%d%% match', 'contactpanda-business' ), $item['match_score'] ) ) . '</small><br><a href="' . esc_url( add_query_arg( array( 'page' => 'contactpanda-business-projects', 'task' => $item['match_task_id'] ), admin_url( 'admin.php' ) ) ) . '" target="_blank">' . esc_html__( 'Open existing task', 'contactpanda-business' ) . '</a>';
			else echo '<span class="cpb-status is-active">' . esc_html__( 'New', 'contactpanda-business' ) . '</span>';
			echo '</td><td><select name="items[' . esc_attr( $item['id'] ) . '][import_action]"><option value="create"' . selected( $item['import_action'], 'create', false ) . '>' . esc_html__( 'Create new task', 'contactpanda-business' ) . '</option>';
			if ( $item['match_task_id'] ) { echo '<option value="comment"' . selected( $item['import_action'], 'comment', false ) . '>' . esc_html__( 'Add as comment', 'contactpanda-business' ) . '</option><option value="update"' . selected( $item['import_action'], 'update', false ) . '>' . esc_html__( 'Update existing task', 'contactpanda-business' ) . '</option>'; }
			echo '<option value="skip"' . selected( $item['import_action'], 'skip', false ) . '>' . esc_html__( 'Skip', 'contactpanda-business' ) . '</option></select><label><input type="checkbox" name="items[' . esc_attr( $item['id'] ) . '][internal]" value="1"> ' . esc_html__( 'Internal comment', 'contactpanda-business' ) . '</label>';
			if ( $item['match_task_id'] ) echo '<label><input type="checkbox" name="items[' . esc_attr( $item['id'] ) . '][reopen]" value="1"> ' . esc_html__( 'Reopen completed task', 'contactpanda-business' ) . '</label>';
			echo '</td></tr>';
		}
		echo '</tbody></table></div><p><button class="button button-primary">' . esc_html__( 'Apply selected tasks', 'contactpanda-business' ) . '</button></p></form></section>';
	}

	public static function upload() {
		self::guard(); check_admin_referer( 'contactpanda_business_upload_import' );
		if ( empty( $_FILES['import_file']['tmp_name'] ) || ! is_uploaded_file( $_FILES['import_file']['tmp_name'] ) ) self::redirect( false, __( 'Select a file to import.', 'contactpanda-business' ) );
		$name = sanitize_file_name( wp_unslash( $_FILES['import_file']['name'] ) ); $ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ); $allowed = array( 'csv', 'xlsx', 'docx', 'txt' );
		if ( ! in_array( $ext, $allowed, true ) ) self::redirect( false, __( 'This file type is not supported for direct import.', 'contactpanda-business' ) );
		$rows = self::parse_file( $_FILES['import_file']['tmp_name'], $ext ); if ( is_wp_error( $rows ) ) self::redirect( false, $rows->get_error_message() ); if ( ! $rows ) self::redirect( false, __( 'No importable tasks were found in the file.', 'contactpanda-business' ) );
		$import_id = self::stage_rows( $rows, $ext, $name ); if ( is_wp_error( $import_id ) ) self::redirect( false, $import_id->get_error_message() );
		$attachment_id = self::archive_source( $_FILES['import_file']['tmp_name'], $name );
		if ( $attachment_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $wpdb->update( $t['imports'], array( 'attachment_id' => $attachment_id ), array( 'id' => $import_id ) ); }
		self::redirect( true, __( 'The file was parsed. Review the tasks before importing them.', 'contactpanda-business' ), $import_id );
	}

	public static function stage_rows( $rows, $source_type = 'mcp', $source_name = '' ) {
		self::guard(); global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $now = current_time( 'mysql', true );
		$wpdb->insert( $t['imports'], array( 'uuid' => wp_generate_uuid4(), 'source_type' => sanitize_key( $source_type ), 'source_name' => sanitize_text_field( $source_name ), 'status' => 'review', 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now ) ); $import_id = absint( $wpdb->insert_id );
		if ( ! $import_id ) return new WP_Error( 'import_failed', __( 'The import draft could not be created.', 'contactpanda-business' ) );
		foreach ( array_slice( (array) $rows, 0, 1000 ) as $index => $row ) {
			$title = sanitize_text_field( $row['title'] ?? '' ); $description = sanitize_textarea_field( $row['description'] ?? $row['text'] ?? '' ); if ( ! $title ) $title = sanitize_text_field( wp_trim_words( $description, 12, '' ) ); if ( ! $title ) continue;
			$project_id = absint( $row['project_id'] ?? 0 ); $provided_match = absint( $row['match_task_id'] ?? 0 );
			if ( $provided_match ) { $matched_project = absint( $wpdb->get_var( $wpdb->prepare( "SELECT project_id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $provided_match ) ) ); if ( ! $matched_project ) $provided_match = 0; elseif ( ! $project_id ) $project_id = $matched_project; }
			$match = $provided_match ? array( 'id' => $provided_match, 'score' => 100 ) : self::find_match( $title, $description, $project_id );
			$wpdb->insert( $t['import_items'], array( 'import_id' => $import_id, 'source_key' => sanitize_text_field( $row['source_key'] ?? (string) $index ), 'source_text' => sanitize_textarea_field( $row['source_text'] ?? $description ), 'title' => $title, 'description' => $description, 'project_id' => $project_id, 'area_id' => absint( $row['area_id'] ?? 0 ), 'assigned_user_id' => absint( $row['assigned_user_id'] ?? 0 ), 'priority' => self::priority( $row['priority'] ?? 'normal' ), 'due_at' => self::date_value( $row['due_at'] ?? '' ), 'visibility' => 'internal' === sanitize_key( $row['visibility'] ?? '' ) ? 'internal' : 'customer', 'match_task_id' => absint( $match['id'] ?? 0 ), 'match_score' => absint( $match['score'] ?? 0 ), 'import_action' => ! empty( $match['id'] ) ? 'comment' : 'create', 'selected' => 1, 'created_at' => $now ) );
		}
		return $import_id;
	}

	public static function apply() {
		self::guard(); $import_id = absint( $_POST['import_id'] ?? 0 ); check_admin_referer( 'contactpanda_business_apply_import_' . $import_id ); global $wpdb; $t = ContactPanda_Business_Schema::table_names();
		$posted = isset( $_POST['items'] ) && is_array( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : array(); $created = 0; $comments = 0; $updated = 0;
		foreach ( $posted as $item_id => $values ) {
			$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM `{$t['import_items']}` WHERE id=%d AND import_id=%d", absint( $item_id ), $import_id ), ARRAY_A ); if ( ! $item ) continue;
			$selected = ! empty( $values['selected'] ); $action = sanitize_key( $values['import_action'] ?? 'skip' ); $title = sanitize_text_field( $values['title'] ?? $item['title'] ); $description = sanitize_textarea_field( $values['description'] ?? $item['description'] ); $project_id = absint( $values['project_id'] ?? 0 ); $area_id = absint( $values['area_id'] ?? 0 ); $assigned = absint( $values['assigned_user_id'] ?? 0 ); $due = self::date_value( $values['due_at'] ?? '' ); $priority = self::priority( $values['priority'] ?? $item['priority'] ); $visibility = 'internal' === sanitize_key( $values['visibility'] ?? $item['visibility'] ) ? 'internal' : 'customer';
			if ( $area_id && ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['areas']}` WHERE id=%d AND project_id=%d AND deleted_at IS NULL", $area_id, $project_id ) ) ) $area_id = 0;
			$wpdb->update( $t['import_items'], array( 'selected' => $selected ? 1 : 0, 'title' => $title, 'description' => $description, 'project_id' => $project_id, 'area_id' => $area_id, 'assigned_user_id' => $assigned, 'priority' => $priority, 'due_at' => $due, 'visibility' => $visibility, 'import_action' => $action ), array( 'id' => absint( $item_id ) ) );
			if ( ! $selected || 'skip' === $action ) continue; $now = current_time( 'mysql', true );
			$reopen = ! empty( $values['reopen'] );
			$match_task_id = absint( $item['match_task_id'] ); $match_exists = $match_task_id && $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['tasks']}` WHERE id=%d AND deleted_at IS NULL", $match_task_id ) );
			if ( in_array( $action, array( 'comment', 'update' ), true ) && ! $match_exists ) continue;
			if ( 'comment' === $action && $match_exists ) { $wpdb->insert( $t['comments'], array( 'task_id' => $match_task_id, 'visibility' => ! empty( $values['internal'] ) ? 'internal' : 'customer', 'author_user_id' => get_current_user_id(), 'body' => $description ?: $title, 'created_at' => $now ) ); if ( $reopen ) $wpdb->update( $t['tasks'], array( 'board_column' => 'open', 'open_status' => 'question', 'completed_at' => null, 'updated_at' => $now ), array( 'id' => $match_task_id ) ); $comments++; continue; }
			if ( 'update' === $action && $match_exists ) { $task_data = array( 'title' => $title, 'description' => $description, 'area_id' => $area_id, 'assigned_user_id' => $assigned, 'priority' => $priority, 'due_at' => $due, 'visibility' => $visibility, 'revision' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT revision FROM `{$t['tasks']}` WHERE id=%d", $match_task_id ) ) + 1, 'updated_at' => $now ); if ( $reopen ) { $task_data['board_column'] = 'open'; $task_data['open_status'] = 'question'; $task_data['completed_at'] = null; } $wpdb->update( $t['tasks'], $task_data, array( 'id' => $match_task_id ) ); $updated++; continue; }
			if ( ! $project_id || ! $wpdb->get_var( $wpdb->prepare( "SELECT id FROM `{$t['projects']}` WHERE id=%d AND deleted_at IS NULL", $project_id ) ) ) continue;
			$wpdb->insert( $t['tasks'], array( 'uuid' => wp_generate_uuid4(), 'project_id' => $project_id, 'area_id' => $area_id, 'title' => $title, 'description' => $description, 'board_column' => 'open', 'open_status' => 'new', 'progress' => 0, 'priority' => $priority, 'assigned_user_id' => $assigned, 'due_at' => $due, 'visibility' => $visibility, 'created_by' => get_current_user_id(), 'created_at' => $now, 'updated_at' => $now, 'source_hash' => hash( 'sha256', strtolower( $project_id . '|' . $title . '|' . $description ) ) ) ); if ( $wpdb->insert_id ) $created++;
		}
		$wpdb->update( $t['imports'], array( 'status' => 'applied', 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => $import_id ) ); self::redirect( true, sprintf( __( 'Import complete: %1$d tasks created, %2$d comments added, %3$d tasks updated.', 'contactpanda-business' ), $created, $comments, $updated ), $import_id );
	}

	private static function parse_file( $path, $ext ) {
		if ( 'csv' === $ext ) return self::parse_csv( $path ); if ( 'txt' === $ext ) return self::parse_text( file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( ! class_exists( 'ZipArchive' ) ) return new WP_Error( 'zip_missing', __( 'The PHP ZIP extension is required to import Word or Excel files.', 'contactpanda-business' ) );
		return 'docx' === $ext ? self::parse_docx( $path ) : self::parse_xlsx( $path );
	}

	/** Keep the original test/customer document with the import record, not in the plugin repository. */
	private static function archive_source( $path, $name ) {
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) return 0;
		$upload = wp_upload_bits( $name, null, $contents );
		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) ) return 0;
		$filetype = wp_check_filetype( $upload['file'] );
		return absint( wp_insert_attachment( array( 'post_mime_type' => $filetype['type'] ?: 'application/octet-stream', 'post_title' => sanitize_text_field( pathinfo( $name, PATHINFO_FILENAME ) ), 'post_status' => 'private' ), $upload['file'] ) );
	}

	private static function parse_csv( $path ) { $handle = fopen( $path, 'r' ); if ( ! $handle ) return new WP_Error( 'csv_failed', __( 'The CSV file could not be read.', 'contactpanda-business' ) ); $rows = array(); $headers = fgetcsv( $handle, 0, self::csv_delimiter( $path ) ); while ( ( $data = fgetcsv( $handle, 0, self::csv_delimiter( $path ) ) ) !== false ) { $mapped = array(); foreach ( (array) $headers as $index => $header ) $mapped[ sanitize_key( $header ) ] = $data[ $index ] ?? ''; $text = $mapped['description'] ?? $mapped['beschreibung'] ?? $mapped['text'] ?? ''; $title = $mapped['title'] ?? $mapped['titel'] ?? $mapped['aufgabe'] ?? ''; $rows[] = array( 'title' => $title, 'description' => $text, 'source_text' => implode( ' | ', $data ), 'due_at' => $mapped['due_at'] ?? $mapped['falligkeit'] ?? '', 'priority' => $mapped['priority'] ?? $mapped['prioritat'] ?? 'normal' ); } fclose( $handle ); return $rows; }
	private static function csv_delimiter( $path ) { $sample = (string) file_get_contents( $path, false, null, 0, 4096 ); return substr_count( $sample, ';' ) > substr_count( $sample, ',' ) ? ';' : ','; } // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	private static function parse_text( $text ) { $rows = array(); foreach ( preg_split( '/\R+/u', (string) $text ) as $index => $line ) { $line = trim( preg_replace( '/^[\s\-\x{2022}\d\.\)]+/u', '', $line ) ); if ( $line ) $rows[] = array( 'title' => wp_trim_words( $line, 12, '' ), 'description' => $line, 'source_text' => $line, 'source_key' => (string) $index ); } return $rows; }
	private static function parse_docx( $path ) {
		$zip = new ZipArchive(); if ( true !== $zip->open( $path ) ) return new WP_Error( 'docx_failed', __( 'The Word document could not be opened.', 'contactpanda-business' ) ); $xml = $zip->getFromName( 'word/document.xml' ); $comments_xml = $zip->getFromName( 'word/comments.xml' ); $zip->close(); if ( ! $xml ) return new WP_Error( 'docx_failed', __( 'The Word document contains no readable text.', 'contactpanda-business' ) );
		$comments = array(); if ( $comments_xml && preg_match_all( '/<w:comment[^>]*w:id="(\d+)"[^>]*>(.*?)<\/w:comment>/s', $comments_xml, $matches, PREG_SET_ORDER ) ) foreach ( $matches as $match ) { preg_match_all( '/<w:t[^>]*>(.*?)<\/w:t>/s', $match[2], $texts ); $comments[ (int) $match[1] ] = html_entity_decode( implode( '', $texts[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ); }
		$paragraphs = array(); if ( preg_match_all( '/<w:p(?:\s[^>]*)?>(.*?)<\/w:p>/s', $xml, $matches ) ) foreach ( $matches[1] as $paragraph ) { preg_match_all( '/<w:t[^>]*>(.*?)<\/w:t>/s', $paragraph, $texts ); preg_match_all( '/w:comment(?:RangeStart|Reference)[^>]*w:id="(\d+)"/s', $paragraph, $ids ); $paragraphs[] = array( 'text' => html_entity_decode( implode( '', $texts[1] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ), 'comments' => array_map( 'intval', $ids[1] ?? array() ) ); }
		$has_ids = false; foreach ( $paragraphs as $paragraph ) if ( preg_match( '/\[CP-TASK:(\d+)\]/', $paragraph['text'] ) ) { $has_ids = true; break; } if ( ! $has_ids ) return self::parse_text( implode( "\n", wp_list_pluck( $paragraphs, 'text' ) ) );
		$rows = array(); $current = null; $response = false;
		foreach ( $paragraphs as $paragraph ) {
			$text = trim( $paragraph['text'] ); if ( preg_match( '/\[CP-TASK:(\d+)\]/', $text, $marker ) ) { if ( $current && trim( $current['description'] ) ) $rows[] = $current; $current = array( 'match_task_id' => absint( $marker[1] ), 'title' => '', 'description' => '', 'source_text' => '', 'source_key' => 'task-' . absint( $marker[1] ) ); $response = false; continue; }
			if ( ! $current ) continue; if ( ! $current['title'] && $text ) { $current['title'] = $text; continue; } if ( 0 === stripos( $text, __( 'Customer response', 'contactpanda-business' ) ) || 0 === stripos( $text, 'Customer response' ) || 0 === stripos( $text, 'Kundenrückmeldung' ) ) { $response = true; continue; }
			if ( $response && $text && ! preg_match( '/^_+$/', $text ) ) $current['description'] .= ( $current['description'] ? "\n" : '' ) . $text;
			foreach ( array_unique( $paragraph['comments'] ) as $comment_id ) if ( ! empty( $comments[ $comment_id ] ) ) $current['description'] .= ( $current['description'] ? "\n" : '' ) . $comments[ $comment_id ];
			$current['source_text'] .= ( $text ? $text . "\n" : '' );
		}
		if ( $current && trim( $current['description'] ) ) $rows[] = $current; return $rows;
	}
	private static function parse_xlsx( $path ) { $zip = new ZipArchive(); if ( true !== $zip->open( $path ) ) return new WP_Error( 'xlsx_failed', __( 'The Excel workbook could not be opened.', 'contactpanda-business' ) ); $shared = array(); $shared_xml = $zip->getFromName( 'xl/sharedStrings.xml' ); if ( $shared_xml && preg_match_all( '/<si>(.*?)<\/si>/s', $shared_xml, $matches ) ) foreach ( $matches[1] as $match ) $shared[] = html_entity_decode( wp_strip_all_tags( $match ), ENT_QUOTES | ENT_XML1, 'UTF-8' ); $sheet = $zip->getFromName( 'xl/worksheets/sheet1.xml' ); $zip->close(); if ( ! $sheet ) return new WP_Error( 'xlsx_failed', __( 'The first Excel sheet could not be read.', 'contactpanda-business' ) ); $lines = array(); if ( preg_match_all( '/<row[^>]*>(.*?)<\/row>/s', $sheet, $rows ) ) foreach ( $rows[1] as $row ) { $values = array(); if ( preg_match_all( '/<c([^>]*)>(.*?)<\/c>/s', $row, $cells, PREG_SET_ORDER ) ) foreach ( $cells as $cell ) { preg_match( '/<v>(.*?)<\/v>/s', $cell[2], $value ); $raw = html_entity_decode( $value[1] ?? wp_strip_all_tags( $cell[2] ), ENT_QUOTES | ENT_XML1, 'UTF-8' ); $values[] = false !== strpos( $cell[1], 't="s"' ) ? ( $shared[ (int) $raw ] ?? '' ) : $raw; } if ( array_filter( $values, 'strlen' ) ) $lines[] = implode( ' | ', $values ); } return self::parse_text( implode( "\n", $lines ) ); }

	private static function find_match( $title, $description, $project_id ) { global $wpdb; $t = ContactPanda_Business_Schema::table_names(); $where = 'deleted_at IS NULL'; $args = array(); if ( $project_id ) { $where .= ' AND project_id=%d'; $args[] = $project_id; } $rows = $args ? $wpdb->get_results( $wpdb->prepare( "SELECT id,title,description FROM `{$t['tasks']}` WHERE $where ORDER BY updated_at DESC LIMIT 300", $args ), ARRAY_A ) : $wpdb->get_results( "SELECT id,title,description FROM `{$t['tasks']}` WHERE $where ORDER BY updated_at DESC LIMIT 300", ARRAY_A ); $needle = strtolower( remove_accents( trim( $title . ' ' . $description ) ) ); $best = array( 'id' => 0, 'score' => 0 ); foreach ( $rows as $row ) { $haystack = strtolower( remove_accents( trim( $row['title'] . ' ' . $row['description'] ) ) ); similar_text( $needle, $haystack, $score ); if ( $score > $best['score'] ) $best = array( 'id' => absint( $row['id'] ), 'score' => min( 100, (int) round( $score ) ) ); } return $best['score'] >= 60 ? $best : array( 'id' => 0, 'score' => 0 ); }
	private static function priority( $value ) { $value = sanitize_key( $value ); return in_array( $value, array( 'low', 'normal', 'high', 'urgent' ), true ) ? $value : 'normal'; }
	private static function date_value( $value ) { $value = sanitize_text_field( $value ); if ( ! $value ) return null; $timestamp = strtotime( $value ); return $timestamp ? gmdate( 'Y-m-d H:i:s', $timestamp ) : null; }
	private static function header() { echo '<div class="wrap contactpanda-wrap contactpanda-business-wrap"><header class="contactpanda-heading"><div><p class="contactpanda-eyebrow">CONTACTPANDA BUSINESS</p><h1>' . esc_html__( 'Import tasks', 'contactpanda-business' ) . '</h1><p>' . esc_html__( 'Review corrections from documents, spreadsheets, or MCP before adding them to projects.', 'contactpanda-business' ) . '</p></div></header>'; $notice = get_transient( self::NOTICE . get_current_user_id() ); if ( is_array( $notice ) ) { delete_transient( self::NOTICE . get_current_user_id() ); echo '<div class="notice notice-' . ( $notice['success'] ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $notice['message'] ) . '</p></div>'; } }
	private static function redirect( $success, $message, $import_id = 0 ) { set_transient( self::NOTICE . get_current_user_id(), array( 'success' => $success, 'message' => $message ), MINUTE_IN_SECONDS ); $args = array( 'page' => 'contactpanda-business-import' ); if ( $import_id ) $args['import_id'] = $import_id; wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) ); exit; }
	private static function guard() { if ( ! contactpanda_business_module_entitled( 'project_management' ) ) wp_die( esc_html__( 'A valid ContactPanda Business license is required.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); if ( ! current_user_can( 'manage_options' ) ) wp_die( esc_html__( 'You do not have permission.', 'contactpanda-business' ), '', array( 'response' => 403 ) ); return true; }
}
