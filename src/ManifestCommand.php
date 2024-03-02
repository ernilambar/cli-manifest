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
			array(
				'launch' => false,
				'return' => true,
				'parse'  => 'json',
			)
		);

		$subcommands = $cmd_dump['subcommands'];

		$commands = array();

		foreach ( $subcommands as $cv ) {
			$ck = $cv['name'];

			$ck = $this->get_clean_key( $ck );

			$commands[ $ck ] = array(
				'title'   => $cv['name'],
				'excerpt' => $cv['description'],
			);

			if ( isset( $cv['subcommands'] ) ) {
				foreach ( $cv['subcommands'] as $dv ) {
					$dk = $dv['name'];
					$dk = $ck . '/' . $dk;

					$dk = $this->get_clean_key( $dk );

					$commands[ $dk ] = array(
						'title'   => $cv['name'] . ' ' . $dv['name'],
						'excerpt' => $dv['description'],
					);

					if ( isset( $dv['subcommands'] ) ) {
						foreach ( $dv['subcommands'] as $ev ) {
							$ek = $ev['name'];
							$ek = $dk . '/' . $ek;
							$ek = $this->get_clean_key( $ek );

							$commands[ $ek ] = array(
								'title'   => $cv['name'] . ' ' . $dv['name'] . ' ' . $ev['name'],
								'excerpt' => $ev['description'],
							);
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

	private function get_clean_key( $title ) {
		return str_replace( ['/', ' '], '-', $title );
	}
}
