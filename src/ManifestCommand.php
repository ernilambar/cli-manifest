<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

use WP_CLI;
use WP_CLI_Command;

class ManifestCommand extends WP_CLI_Command {

	private $mkd;

	/**
	 * Generate manifest.
	 *
	 * @when before_wp_load
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$this->mkd = \Parsedown::instance();

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

			var_dump( $cv['name'] );
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

					var_dump( $title );
					// if ( ! str_contains( $title, 'user create') ) {
					// 	continue;
					// }
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

							var_dump( $title );
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

		if ( ! empty( $example ) ) {
			$lines = explode("\n", trim( $example ) );
			$lines = array_map( 'trim', $lines );

			$example = implode("\n", $lines );
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

		$available = $this->get_html_from_md( $available );

		return $available;
	}

	private function get_html_from_md( string $content ) {
		return $this->mkd->text( $content );
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
		if ( str_contains( $content, '## OPTIONS' ) ) {
			$exploded = explode( '## OPTIONS', $content );

			if ( 2 === count( $exploded ) ) {
				$options['extra']   = $this->get_html_from_md( $exploded[0] );
				$options['options'] = $this->get_clean_options( $exploded[1] );
			}
		} else {
			$options['extra'] = $this->get_html_from_md( $content );
		}

		return $options;
	}

	private function get_clean_options( string $content ): string {
		$options = '';

		// var_dump( '----' );


		$splitted  = preg_split("#\n\s*\n#Uis", $content);

		if ( ! is_array( $splitted ) || 0 === count( $splitted ) ) {
			return $options;
		}

		$fields = [];




		foreach( $splitted as $it ) {
			$lines = explode( PHP_EOL, $it );

			if ( count( $lines ) > 1 ) {
				$fields[] = $lines;
			}
		}

		if ( ! empty( $fields ) ) {
			$options = "<dl>";

			foreach ( $fields as $field ) {
				if ( count( $field ) < 2 ) {
					continue;
				}

				$options .= '<dt>' . htmlentities( $field[0] ) . "</dt>";

				$new_fields = array_slice( $field, 1 );

				$val = '';

				if ( 1 === count( $new_fields ) ) {
					$val = reset( $new_fields );
					// print_r( $val );
					$val = trim( ltrim( $val, ':' ) );
				} else {
					if ( isset( $new_fields[1] ) && '---' !== $new_fields[1] ) {
						$val = implode( ' ', $new_fields );
						$val = trim( ltrim( $val, ':' ) );
					} else {
						// --- chha.
						$main_value = reset( $new_fields );


						// Remove first 2 elements.
						array_shift($new_fields);
						array_shift($new_fields);

						// Remove last element.
						array_pop($new_fields);
						// print_r( $new_fields );

						if ( str_starts_with( $new_fields[0], 'default:' ) ) {
							$main_value .= ' [' . $new_fields[0] . ']';
							array_shift($new_fields);
						}

						$list_values = [];

						if ( isset( $new_fields[0] ) && str_starts_with( $new_fields[0], 'options:' ) ) {
							array_shift($new_fields);
							$list_values = $new_fields;
						}

						$val = $main_value;

						if ( ! empty( $list_values ) ) {
							$mini_list = '<div>Options:</div>';
							$mini_list .= '<ul><li>';
							$list_values = array_map(function ($item ){
								return trim( str_replace('-','', $item ) );
							}, $list_values);
							$mini_list .= implode( '</li><li>', $list_values );
							$mini_list .= '</li></ul>';

							$val .= $mini_list;
						}

						$val = trim( ltrim( $val, ':' ) );
					}
				}

				$options .= '<dd>' . $val . "</dd>";
			}


			$options .= "</dl>";
		}

		return $options;
	}
}
