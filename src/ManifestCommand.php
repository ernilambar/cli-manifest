<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

use WP_CLI;
use WP_CLI_Command;

class ManifestCommand extends WP_CLI_Command {

	private $mkd;

	private $commands;

	/**
	 * Generate manifest.
	 *
	 * @when before_wp_load
	 * @subcommand generate
	 */
	public function generate( $args, $assoc_args ) {
		$this->commands = array();

		$this->mkd = \Parsedown::instance();

		$file = 'manifest.json';

		$wp = WP_CLI::runcommand(
			'cli cmd-dump',
			array(
				'launch' => false,
				'return' => true,
				'parse'  => 'json',
			)
		);

		foreach ( $wp['subcommands'] as $cmd ) {
			$this->gen_cmd_pages( $cmd, array() );
		}

		$keys = array_map( 'strlen', array_keys( $this->commands ) );
		array_multisort( $keys, SORT_ASC, $this->commands );

		$status = file_put_contents( $file, json_encode( $this->commands, JSON_PRETTY_PRINT ) );

		if ( false === $status ) {
			WP_CLI::error( 'Error generating manifest.' );
		}

		WP_CLI::success( 'Manifest generated successfully.' );
	}

	private function gen_cmd_pages( $cmd, $parent = array() ) {
		$parent[] = $cmd['name'];

		$title = implode( ' ', $parent );

		$key = $this->get_clean_key( $title );

		$opt = $this->get_options( $cmd['longdesc'] );

		$this->commands[ $key ] = array(
			'title'         => $title,
			'excerpt'       => $cmd['description'],
			'description'   => $cmd['longdesc'],
			'options'       => $opt['options'],
			'options_extra' => $opt['extra'],
			'examples'      => $this->get_example( $cmd['longdesc'] ),
			'available'     => $this->get_available( $cmd['longdesc'] ),
			'synopsis'      => ( isset( $cmd['synopsis'] ) && 0 !== strlen( $cmd['synopsis'] ) ) ? trim( 'wp ' . $title . ' ' . $cmd['synopsis'] ) : '',
		);

		if ( ! isset( $cmd['subcommands'] ) ) {
			return;
		}

		foreach ( $cmd['subcommands'] as $subcmd ) {
			$this->gen_cmd_pages( $subcmd, $parent );
		}
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
			$lines = explode( "\n", trim( $example ) );
			$lines = array_map( 'trim', $lines );

			$example = implode( "\n", $lines );
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

		$splitted = preg_split( "#\n\s*\n#Uis", $content );

		if ( ! is_array( $splitted ) || 0 === count( $splitted ) ) {
			return $options;
		}

		$fields = array();

		foreach ( $splitted as $it ) {
			$lines = explode( PHP_EOL, $it );

			if ( count( $lines ) > 1 ) {
				$fields[] = $lines;
			}
		}

		if ( ! empty( $fields ) ) {
			$options = '<dl>';

			foreach ( $fields as $field ) {
				if ( count( $field ) < 2 ) {
					continue;
				}

				$options .= '<dt>' . htmlentities( $field[0] ) . '</dt>';

				$new_fields = array_slice( $field, 1 );

				$val = '';

				if ( 1 === count( $new_fields ) ) {
					$val = reset( $new_fields );
					// print_r( $val );
					$val = trim( ltrim( $val, ':' ) );
				} elseif ( isset( $new_fields[1] ) && '---' !== $new_fields[1] ) {
						$val = implode( ' ', $new_fields );
						$val = trim( ltrim( $val, ':' ) );
				} else {
					// --- chha.
					$main_value = reset( $new_fields );

					// Remove first 2 elements.
					array_shift( $new_fields );
					array_shift( $new_fields );

					// Remove last element.
					array_pop( $new_fields );
					// print_r( $new_fields );

					if ( str_starts_with( $new_fields[0], 'default:' ) ) {
						$main_value .= ' [' . $new_fields[0] . ']';
						array_shift( $new_fields );
					}

					$list_values = array();

					if ( isset( $new_fields[0] ) && str_starts_with( $new_fields[0], 'options:' ) ) {
						array_shift( $new_fields );
						$list_values = $new_fields;
					}

					$val = $main_value;

					if ( ! empty( $list_values ) ) {
						$mini_list   = '<div>Options:</div>';
						$mini_list  .= '<ul><li>';
						$list_values = array_map(
							function ( $item ) {
								return trim( str_replace( '-', '', $item ) );
							},
							$list_values
						);
						$mini_list  .= implode( '</li><li>', $list_values );
						$mini_list  .= '</li></ul>';

						$val .= $mini_list;
					}

					$val = trim( ltrim( $val, ':' ) );
				}

				$options .= '<dd>' . $val . '</dd>';
			}

			$options .= '</dl>';
		}

		return $options;
	}
}
