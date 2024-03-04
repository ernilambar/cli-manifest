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

			$opt = $this->get_options( $cv['longdesc'] );

			$commands[ $ck ] = array(
				'title'         => $cv['name'],
				'excerpt'       => $cv['description'],
				'description'   => $cv['longdesc'],
				'options'       => $opt['options'],
				'options_extra' => $opt['extra'],
				'examples'      => $this->get_example( $cv['longdesc'] ),
				'available'     => $this->get_available( $cv['longdesc'] ),
				'synopsis'      => ( isset( $cv['synopsis'] ) && 0 !== strlen( $cv['synopsis'] ) ) ? trim( 'wp ' . $cv['name'] . ' ' . $cv['synopsis'] ) : '',
			);

			if ( isset( $cv['subcommands'] ) ) {
				foreach ( $cv['subcommands'] as $dv ) {
					$dk = $dv['name'];
					$dk = $ck . '/' . $dk;

					$dk = $this->get_clean_key( $dk );

					$title = $cv['name'] . ' ' . $dv['name'];

					$opt = $this->get_options( $dv['longdesc'] );

					$commands[ $dk ] = array(
						'title'         => $title,
						'excerpt'       => $dv['description'],
						'description'   => $dv['longdesc'],
						'options'       => $opt['options'],
						'options_extra' => $opt['extra'],
						'examples'      => $this->get_example( $dv['longdesc'] ),
						'available'     => $this->get_available( $dv['longdesc'] ),
						'synopsis'      => ( isset( $dv['synopsis'] ) && 0 !== strlen( $dv['synopsis'] ) ) ? trim( 'wp ' . $title . ' ' . $dv['synopsis'] ) : '',
					);

					if ( isset( $dv['subcommands'] ) ) {
						foreach ( $dv['subcommands'] as $ev ) {
							$ek = $ev['name'];
							$ek = $dk . '/' . $ek;
							$ek = $this->get_clean_key( $ek );

							$title = $cv['name'] . ' ' . $dv['name'] . ' ' . $ev['name'];

							$opt = $this->get_options( $ev['longdesc'] );

							$commands[ $ek ] = array(
								'title'         => $title,
								'excerpt'       => $ev['description'],
								'description'   => $ev['longdesc'],
								'options'       => $opt['options'],
								'options_extra' => $opt['extra'],
								'examples'      => $this->get_example( $ev['longdesc'] ),
								'available'     => $this->get_available( $ev['longdesc'] ),
								'synopsis'      => ( isset( $ev['synopsis'] ) && 0 !== strlen( $ev['synopsis'] ) ) ? trim( 'wp ' . $title . ' ' . $ev['synopsis'] ) : '',
							);
						}
					}
				}
			}
		}

		$keys = array_map( 'strlen', array_keys( $commands ) );
		array_multisort( $keys, SORT_ASC, $commands );

		$status = file_put_contents( $file, json_encode( $commands, JSON_PRETTY_PRINT ) );

		if ( false === $status ) {
			WP_CLI::error( 'Error generating manifest.' );
		}

		WP_CLI::success( 'Manifest generated successfully.' );
	}

	private function get_clean_key( $title ) {
		return str_replace( array( '/', ' ' ), '-', $title );
	}

	private function get_example( $content ) {
		$example = '';

		$exploded = explode( '## EXAMPLES', $content );

		if ( count( $exploded ) > 1 ) {
			$example = $exploded[1];
		}

		return $example;
	}

	private function get_available( $content ) {
		$available = '';

		$exploded = explode( '## EXAMPLES', $content );

		// Remove examples.
		if ( count( $exploded ) > 1 ) {
			$content = $exploded[0];
		}

		$exploded = explode( '## AVAILABLE FIELDS', $content );

		if ( count( $exploded ) > 1 ) {
			$available = $exploded[1];
		}

		return $available;
	}

	private function get_options( $content ) {
		$options = array(
			'options' => '',
			'extra'   => '',
		);

		$exploded = explode( '## EXAMPLES', $content );

		$content = reset( $exploded );

		$exploded = explode( '## AVAILABLE FIELDS', $content );

		$content = reset( $exploded );

		// Options.
		$exploded = explode( '## OPTIONS', $content );

		if ( 2 === count( $exploded ) ) {
			$options['extra']   = $exploded[0];
			$options['options'] = $exploded[1];
		} else {
			$options['options'] = str_replace( '## OPTIONS', '', $content );
		}

		return $options;
	}
}
