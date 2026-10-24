<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/contactpanda-business/contactpanda-business.php' );
$schema = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-schema.php' );
$projects = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-projects.php' );
$updater = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-updater.php' );
foreach ( array( 'projects', 'participants', 'tasks', 'comments', 'attachments', 'events' ) as $table ) if ( false === strpos( $schema, "'{$table}'" ) ) { fwrite( STDERR, "Missing table {$table}.\n" ); exit( 1 ); }
foreach ( array( 'business', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management' ) as $module ) if ( false === strpos( $main, "'{$module}'" ) ) { fwrite( STDERR, "Missing module {$module}.\n" ); exit( 1 ); }
foreach ( array( 'open', 'in_progress', 'review', 'done', 'waiting_customer', 'due_at', 'visibility', 'move_task', 'The task could not be moved.', 'customer_task', 'customer_update_task', 'contactpanda_pro_ticket_sidebar', 'contactpanda_plus_customer_detail', 'customer_projects', 'Assigned projects', 'participant_role', 'access_level', 'Project color', 'Search participants', 'Project permissions', 'Edit project', 'project_participants', 'task_detail_modal' ) as $contract ) if ( false === strpos( $projects . $schema, $contract ) ) { fwrite( STDERR, "Missing project contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( "'product'     => 'contactpanda-business'", "'entitlement' => 'business'" ) as $contract ) if ( false === strpos( $updater, $contract ) ) { fwrite( STDERR, "Missing updater contract {$contract}.\n" ); exit( 1 ); }
if ( false !== strpos( $projects, 'DROP TABLE' ) ) { fwrite( STDERR, "Business must not destructively drop tables.\n" ); exit( 1 ); }
if ( false !== strpos( $projects, 'cpb-list' ) || false !== strpos( $projects, 'data-cpb-view' ) ) { fwrite( STDERR, "The project board must not expose the removed list view.\n" ); exit( 1 ); }
echo "ContactPanda Business foundation checks passed.\n";
