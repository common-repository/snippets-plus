<?php

class Snippets_Plus_Polylang_Compat {

	function __construct() {
		add_filter( 'snippets_plus_regions', array( $this, 'snippets_plus_regions' ) );
	}

	function snippets_plus_regions( $regions ) {
		if( ! empty( $regions ) ) {
			$lang = $this->get_post_lang();
			foreach( $regions as $key => $region ) {
				// check if widget is specific to a certain language
				if( $this->get_widget_lang( "snippets-plus-{$key}" ) ) {
					// check if the post language matches the language assigned to the widget
					if( $this->get_widget_lang( "snippets-plus-{$key}" ) != $lang ) {
						unset( $regions[$key] );
					}
				}
			}
		}

		return $regions;
	}

	public function get_widget_lang( $id ) {
		global $polylang;

		if( isset( $polylang->options['widgets'][$id] ) ) {
			return $polylang->options['widgets'][$id];
		}

		return 0;
	}

	function get_post_lang() {
		global $polylang;
		$lang = $polylang->model->get_post_language( get_the_ID() );

		return $lang->slug;
	}
}
new Snippets_Plus_Polylang_Compat;