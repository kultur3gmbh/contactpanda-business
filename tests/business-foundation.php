<?php
$root = dirname( __DIR__ );
$main = file_get_contents( $root . '/contactpanda-business/contactpanda-business.php' );
$schema = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-schema.php' );
$projects = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-projects.php' );
$updater = file_get_contents( $root . '/contactpanda-business/includes/class-contactpanda-business-updater.php' );
foreach ( array( 'projects', 'tasks', 'comments', 'attachments', 'events' ) as $table ) if ( false === strpos( $schema, "'{$table}'" ) ) { fwrite( STDERR, "Missing table {$table}.\n" ); exit( 1 ); }
foreach ( array( 'business', 'project_management', 'customer_management', 'contract_management', 'subscription_management', 'payment_management' ) as $module ) if ( false === strpos( $main, "'{$module}'" ) ) { fwrite( STDERR, "Missing module {$module}.\n" ); exit( 1 ); }
foreach ( array( 'open', 'in_progress', 'review', 'done', 'waiting_customer', 'due_at', 'visibility', 'move_task', 'customer_task', 'contactpanda_pro_ticket_sidebar' ) as $contract ) if ( false === strpos( $projects . $schema, $contract ) ) { fwrite( STDERR, "Missing project contract {$contract}.\n" ); exit( 1 ); }
foreach ( array( "'product'     => 'contactpanda-business'", "'entitlement' => 'business'" ) as $contract ) if ( false === strpos( $updater, $contract ) ) { fwrite( STDERR, "Missing updater contract {$contract}.\n" ); exit( 1 ); }
if ( false !== strpos( $projects, 'DROP TABLE' ) ) { fwrite( STDERR, "Business must not destructively drop tables.\n" ); exit( 1 ); }
echo "ContactPanda Business foundation checks passed.\n";
