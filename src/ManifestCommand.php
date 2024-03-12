<?php

namespace Nilambar\CLI_Manifest;

use WP_CLI;
use WP_CLI_Command;

class ManifestCommand extends WP_CLI_Command {

	private $mkd;

	private $commands;

	/**
	 * Generate manifest.
	 *
	 * @when before_wp_load
	 */
	public function __invoke( $args, $assoc_args ) {
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

	private function get_clean_key( $title ) {
		return str_replace( array( '/', ' ' ), '-', $title );
	}

	private function get_html_from_md( string $content ) {
		return $this->mkd->text( $content );
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
}
