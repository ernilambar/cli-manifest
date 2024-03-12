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

		unset( $this->commands['manifest'] );

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

		$env_commands = array();

		if ( isset( $_ENV['COMMANDS'] ) && ! empty( $_ENV['COMMANDS'] ) ) {
			$exp = explode( ',', $_ENV['COMMANDS'] );
			$exp = array_map( 'trim', $exp );
			$exp = array_filter( $exp );

			if ( 0 !== $exp ) {
				$env_commands = $exp;
			}
		}

		$is_all = ( count( $env_commands ) ) ? false : true;

		if ( ( ( count( $env_commands ) > 0 ) && ( in_array( $key, $env_commands, true ) ) ) || $is_all ) {
			$parser = new Parser( $cmd['longdesc'] );

			$this->commands[ $key ] = array(
				'title'         => $title,
				'excerpt'       => $cmd['description'],
				'options'       => Formatter::get_formatted_options( $parser->options ),
				'options_extra' => ! empty( $parser->memo ) ? $this->get_html_from_md( $parser->memo ) : '',
				'has_child'     => isset( $cmd['subcommands'] ) ? 1 : 0,
				'examples'      => $parser->examples,
				'available'     => ! empty( $parser->available_fields ) ? $this->get_html_from_md( $parser->available_fields ) : '',
				'synopsis'      => ( isset( $cmd['synopsis'] ) && 0 !== strlen( $cmd['synopsis'] ) ) ? trim( 'wp ' . $title . ' ' . $cmd['synopsis'] ) : '',
			);

		}

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

	private function get_html_from_md( string $content ) {
		return $this->mkd->text( $content );
	}

	private function get_clean_options( string $content, $key ): string {
		$options = '';

		$splitted = preg_split( "#\n\s*\n#Uis", $content );

		if ( ! is_array( $splitted ) || 0 === count( $splitted ) ) {
			return $options;
		}

		$fields = array();

		$i  = 0;
		$fc = 0;

		do {
			if (
					str_starts_with( $splitted[ $i ], '[' )
					|| str_starts_with( $splitted[ $i ], '<' )
					|| str_starts_with( $splitted[ $i ], '-' )
				) {
					++$fc;
			}

			$fields[ $fc ][] = $splitted[ $i ];

		} while ( ++$i < count( $splitted ) );

		$fields = array_filter( $fields );

		$options = '';

		if ( ! empty( $fields ) ) {

			$options .= '<dl>';
			foreach ( $fields as $field ) {
				$main_line = reset( $field );

				$lines = explode( PHP_EOL, $main_line );

				if ( count( $lines ) <= 1 ) {
					continue;
				}

				$options .= '<dt>' . htmlentities( $lines[0] ) . '</dt>';

				$body = '';

				if ( 2 === count( $lines ) ) {
					$body = $lines[1];
					$body = ltrim( $body, ': ' );
				}

				if ( count( $lines ) > 2 ) {
					// Remove parameter.
					array_shift( $lines );

					// Content.
					$param_dec = array_shift( $lines );
					$param_dec = ltrim( $param_dec, ': ' );
					$body     .= $param_dec;

					$dasher_index = array_search( '---', $lines, true );

					$dashed_items = array();

					if ( false !== $dasher_index ) {
						// Dasher chha.
						$dashed_items = array_slice( $lines, $dasher_index );
					}

					$maybe_baki = array_slice( $lines, 0, count( $lines ) - count( $dashed_items ) );

					if ( 0 !== count( $maybe_baki ) ) {
						$body .= ' ' . implode( ' ', $maybe_baki );
					}

					if ( ! empty( $dashed_items ) ) {
						$dashed_content = $this->get_dashed_content( $dashed_items );

						$body .= $dashed_content;
					}
				}

				// Add extra stuff if exists in main field.
				if ( count( $field ) > 1 ) {
					$remaining_stuffs = array_slice( $field, 1 );
					$remaining_stuffs = array_map( 'trim', $remaining_stuffs );
					$remaining_stuffs = array_filter( $remaining_stuffs );

					if ( count( $remaining_stuffs ) ) {
						$body .= ' ' . implode( ' ', $remaining_stuffs );
					}
				}

				$body = $this->get_html_from_md( $body );

				$options .= '<dd>' . $body . '</dd>';
			}

			$options .= '</dl>';
		}

		return $options;
	}

	private function get_dashed_content( $lines ) {
		$content = '';

		if ( empty( $lines ) ) {
			return $content;
		}

		array_shift( $lines );
		array_pop( $lines );

		if ( empty( $lines ) ) {
			return $content;
		}

		if ( str_starts_with( $lines[0], 'default:' ) ) {
			$content .= ' [' . ucfirst( $lines[0] ) . ']';
			// Remove item default: value.
			array_shift( $lines );
		}

		$list_values = array();

		if ( count( $lines ) > 0 ) {
			// There are options also.
			// Remove item options:.
			array_shift( $lines );

			$list_values = $lines;
		}

		if ( ! empty( $list_values ) ) {
			$mini_list   = '<div><span>Options:</span>';
			$mini_list  .= '<ul><li><code>';
			$list_values = array_map(
				function ( $item ) {
					return trim( str_replace( '-', '', $item ) );
				},
				$list_values
			);
			$mini_list  .= implode( '</code></li><li><code>', $list_values );
			$mini_list  .= '</code></li></ul>';

			$mini_list .= '</div>';

			$content .= $mini_list;
		}

		return $content;
	}
}
