<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/contactpanda-business/contactpanda-business.php' );
$schema = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-schema.php' );
$projects = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-projects.php' );
$workspace = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-workspace.php' );
$imports = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-imports.php' );
$mcp = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-mcp.php' );
$updater = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-updater.php' );
foreach ( array( 'projects', 'participants', 'areas', 'tasks', 'comments', 'attachments', 'events', 'imports', 'import_items' ) as $table ) if ( false === strpos( $schema, "'{$table}'" ) ) { fwrite( STDERR, "Missing table {$table}.\n" ); exit( 1 ); }
foreach ( array( 'business', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management' ) as $module ) if ( false === strpos( $main, "'{$module}'" ) ) { fwrite( STDERR, "Missing module {$module}.\n" ); exit( 1 ); }
foreach ( array( 'open', 'in_progress', 'review', 'done', 'waiting_customer', 'due_at', 'visibility', 'move_task', 'The task could not be moved.', 'customer_task', 'customer_update_task', 'contactpanda_pro_ticket_sidebar', 'contactpanda_plus_customer_detail', 'customer_projects', 'Assigned projects', 'participant_role', 'access_level', 'Project color', 'Search participants', 'Project permissions', 'Edit project', 'project_participants', 'task_detail_modal' ) as $contract ) if ( false === strpos( $projects . $schema, $contract ) ) { fwrite( STDERR, "Missing project contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( "'product'     => 'contactpanda-business'", "'entitlement' => 'business'" ) as $contract ) if ( false === strpos( $updater, $contract ) ) { fwrite( STDERR, "Missing updater contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( 'project_action', 'task_action', 'save_area', 'export_report', 'Move project to trash', 'Duplicate task', 'Word report' ) as $contract ) if ( false === strpos( $workspace, $contract ) ) { fwrite( STDERR, "Missing workspace contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( 'stage_rows', 'Review imported tasks', 'match_task_id', 'Add as comment', 'Reopen completed task', 'archive_source', 'Bulk edit selected tasks', 'data-cpb-import-bulk', 'data-cpb-import-select-all' ) as $contract ) if ( false === strpos( $imports, $contract ) ) { fwrite( STDERR, "Missing import contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( 'contactpanda-business/v1', 'contactpanda_business_projects_list', 'contactpanda_business_tasks_search', 'contactpanda_business_import_stage', 'live_tasks_changed' ) as $contract ) if ( false === strpos( $mcp, $contract ) ) { fwrite( STDERR, "Missing MCP contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( 'register_ability_hooks', 'wp_abilities_api_categories_init', 'wp_abilities_api_init', 'wp_register_ability_category', 'wp_register_ability', 'contactpanda-business/projects-list', 'contactpanda-business/project-get', 'contactpanda-business/tasks-search', 'contactpanda-business/import-stage', 'contactpanda-business/report-prepare' ) as $contract ) if ( false === strpos( $mcp, $contract ) ) { fwrite( STDERR, "Missing WordPress ability contract {$contract}.\n" ); exit( 1 ); }
if ( false === strpos( $main, 'ContactPanda_Business_MCP::register_ability_hooks();' ) ) { fwrite( STDERR, "Business must register ability hooks during plugin loading.\n" ); exit( 1 ); }
if ( false !== strpos( $projects, 'DROP TABLE' ) ) { fwrite( STDERR, "Business must not destructively drop tables.\n" ); exit( 1 ); }
if ( false !== strpos( $schema, 'DROP TABLE' ) || false !== strpos( $imports, 'DROP TABLE' ) ) { fwrite( STDERR, "Business upgrades and imports must remain non-destructive.\n" ); exit( 1 ); }
if ( false !== strpos( $projects, 'cpb-list' ) || false !== strpos( $projects, 'data-cpb-view' ) ) { fwrite( STDERR, "The project board must not expose the removed list view.\n" ); exit( 1 ); }
echo "ContactPanda Business foundation checks passed.\n";
