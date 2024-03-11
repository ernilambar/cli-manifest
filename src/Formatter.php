<?php

namespace Nilambar\CLI_Manifest\ManifestCommand;

class Formatter {

	public static function get_formatted_options( $data ) {
		$options = '';

		if ( empty( $data ) ) {
			return $options;
		}

		$mkd = \Parsedown::instance();

		$options .= '<dl>';

		foreach ( $data as $item ) {
			$options .= '<dt>' . htmlentities( $item['title'] ) . '</dt>';

			$body = $item['body'];

			if ( isset( $item['default'] ) ) {
				$body .= ' [Default: ' . $item['default'] . ']';
			}

			if ( isset( $item['choices'] ) && ! empty( $item['choices'] ) ) {

				$list_values = $item['choices'];

				$mini_list  = '<div><span>Options:</span>';
				$mini_list .= '<ul><li><code>';

				$list_values = array_map(
					function ( $item ) {
						return trim( str_replace( '-', '', $item ) );
					},
					$list_values
				);

				$mini_list .= implode( '</code></li><li><code>', $list_values );
				$mini_list .= '</code></li></ul>';

				$mini_list .= '</div>';

				$body .= $mini_list;
			}

			$options .= '<dd>' . $mkd->text( $body ) . '</dd>';
		}

		$options .= '</dl>';

		return $options;
	}
}
