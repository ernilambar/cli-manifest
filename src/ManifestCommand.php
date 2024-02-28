<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

use WP_CLI;
use WP_CLI_Command;

class ManifestCommand extends WP_CLI_Command {

	/**
	 * Generate manifest.
	 *
	 * @when before_wp_load
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$file = 'manifest.json';

		$cmd_dump = WP_CLI::runcommand(
			'cli cmd-dump',
			[
				'launch' => false,
				'return' => true,
				'parse'  => 'json',
			]
		);

		$subcommands = $cmd_dump['subcommands'];

		$commands = array();

		foreach ( $subcommands as $cv ) {
			$ck = str_replace( ' ', '/', $cv['name'] );

			$commands[ $ck ] = [ 'title' => $cv['name'] ];

			if ( isset( $cv['subcommands'] ) ) {
				foreach ( $cv['subcommands'] as $dv ) {
					$dk = str_replace( ' ', '/', $dv['name'] );
					$dk = $ck . '/' . $dk;

					$commands[ $dk ] = [ 'title' => $cv['name'] . ' ' . $dv['name'] ];

					if ( isset( $dv['subcommands'] ) ) {
						foreach ( $dv['subcommands'] as $ev ) {
							$ek = str_replace( ' ', '/', $ev['name'] );
							$ek = $dk . '/' . $ek;

							$commands[ $ek ] = [ 'title' => $cv['name'] . ' ' . $dv['name'] . ' ' . $ev['name'] ];
						}
					}
				}
			}
		}

		$status = file_put_contents( $file, json_encode( $commands, JSON_PRETTY_PRINT ) );

		if ( false === $status ) {
			WP_CLI::error( 'Error generating manifest.' );
		}

		WP_CLI::success( 'Manifest generated successfully.' );
	}
}
