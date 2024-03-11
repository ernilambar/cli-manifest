<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

class Parser {
	public $examples = '';

	public $available_fields = '';

	public $options = array();

	public $memo = '';

	protected $contents = '';

	public function __construct( $string ) {
		if ( empty( $string ) ) {
			return;
		}

		$this->contents = $string;

		$this->do_parse();
	}

	protected function do_parse() {
		$opt = $this->get_options();

		$this->examples         = $this->get_example();
		$this->available_fields = $this->get_available_fields();
		$this->options          = $opt['options'];
		$this->memo             = $opt['memo'];
	}

	private function get_example() {
		$example = '';

		if ( ! str_contains( $this->contents, '## EXAMPLES' ) ) {
			return $example;
		}

		$exploded = explode( '## EXAMPLES', $this->contents );

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

	private function get_available_fields() {
		$available = '';

		if ( ! str_contains( $this->contents, '## AVAILABLE FIELDS' ) ) {
			return $available;
		}

		$content = $this->contents;

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

	private function get_options() {
		$options = array(
			'options' => '',
			'memo'    => '',
		);

		$content = $this->contents;

		$exploded = explode( '## EXAMPLES', $content );

		$content = reset( $exploded );

		$exploded = explode( '## AVAILABLE FIELDS', $content );

		$content = reset( $exploded );

		// Options.
		if ( str_contains( $content, '## OPTIONS' ) ) {
			$exploded = explode( '## OPTIONS', $content );

			if ( 2 === count( $exploded ) ) {
				$options['memo']    = $exploded[0];
				$options['options'] = $this->get_parsed_options( $exploded[1] );
			}
		} else {
			$options['memo'] = $content;
		}

		return $options;
	}

	private function get_parsed_options( $content ) {
		$splitted = preg_split( "#\n\s*\n#Uis", $content );

		if ( ! is_array( $splitted ) || 0 === count( $splitted ) ) {
			return array();
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

		$args = array();

		if ( ! empty( $fields ) ) {

			foreach ( $fields as $field ) {
				$arg_item = array();

				$main_line = reset( $field );

				$lines = explode( PHP_EOL, $main_line );

				if ( count( $lines ) <= 1 ) {
					continue;
				}

				$arg_item['title'] = $lines[0];

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
						$arg_item = array_merge( $arg_item, $this->get_dashed( $dashed_items ) );
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

				$arg_item['body'] = $body;
				$args[]           = $arg_item;
			}
		}

		return $args;
	}

	private function get_dashed( $lines ) {
		$output = array();

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
			$def = str_replace( 'default:', '', $lines[0] );

			$output['default'] = trim( $def );
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
			$list_values = array_map(
				function ( $item ) {
					return trim( str_replace( '-', '', $item ) );
				},
				$list_values
			);

			$output['choices'] = $list_values;
		}

		return $output;
	}
}
