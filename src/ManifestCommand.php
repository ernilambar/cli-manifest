<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

use WP_CLI;
use WP_CLI\Utils;

class ManifestCommand {

	/**
	 * Generate manifest.
	 *
	 * @when before_wp_load
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		WP_CLI::success('Manifest generated successfully.');
	}

	private function create_files( $files_and_contents, $force ) {
		$wrote_files = [];

		foreach ( $files_and_contents as $filename => $contents ) {
			$should_write_file = true;

			if ( ! is_dir( dirname( $filename ) ) ) {
				Process::create( Utils\esc_cmd( 'mkdir -p %s', dirname( $filename ) ) )->run();
			}

			if ( ! file_put_contents( $filename, $contents ) ) {
				WP_CLI::error( "Error creating file: $filename" );
			} elseif ( $should_write_file ) {
				$wrote_files[] = $filename;
			}
		}
		return $wrote_files;
	}
}
