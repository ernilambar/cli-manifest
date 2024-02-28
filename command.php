<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

use WP_CLI;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_manifest_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_manifest_autoload ) ) {
	require_once $wpcli_manifest_autoload;
}

WP_CLI::add_command( 'manifest', array( ManifestCommand::class, 'generate' ) );
