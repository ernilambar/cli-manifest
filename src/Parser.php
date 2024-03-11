<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

class Parser {
	public $examples = '';

	protected $contents = '';

	public function __construct( $string ) {
		if ( empty( $string ) ) {
			return;
		}

		$this->contents = $string;

		$this->do_parse();
	}

	protected function do_parse() {
		$this->examples = $this->get_example();
	}

	private function get_example() {
		$example = '';

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
}
