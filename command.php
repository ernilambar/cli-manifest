<?php

namespace Nilambar\CLI_Manifest;

use WP_CLI;

if ( ! class_exists( 'WP_CLI', false ) ) {
	return;
}

$wpcli_manifest_autoload = __DIR__ . '/vendor/autoload.php';
if ( file_exists( $wpcli_manifest_autoload ) ) {
	require_once $wpcli_manifest_autoload;
}

$dotenv = \Dotenv\Dotenv::createImmutable( __DIR__ );
$dotenv->safeLoad();

WP_CLI::add_command( 'manifest', ManifestCommand::class );
