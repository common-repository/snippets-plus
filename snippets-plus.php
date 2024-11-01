<?php
/**
 * Plugin Name: Snippets Plus
 * Plugin URI: http://wordpress.org/plugins/snippets-plus
 * Description: This widget allows you to create dynamic "regions" in widget areas that their content can be specified on a per-post basis.
 * Version: 0.1.2
 * Author: Hassan Derakhshandeh
 * Author URI: http://shazdeh.me/
 */

class Snippets_Plus_Widget extends WP_Widget {

	function __construct() {
		$widget_ops = array( 'classname' => 'snippet', 'description' => __( 'Create a snippet region.', 'snippets-plus' ) );
		parent::__construct( 'snippets-plus', __( 'Snippets Plus', 'snippets-plus' ), $widget_ops, null );
		$this->setup_hooks();
	}

	public function setup_hooks() {
		add_action( 'wp_loaded', array( $this, 'load_plugin_integration' ) );
		if( is_admin() ) {
			add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
		} else {
			/* Apply filters to the snippet content. */
			add_filter( 'snippets_plus_content', 'wptexturize' );
			add_filter( 'snippets_plus_content', 'convert_smilies' );
			add_filter( 'snippets_plus_content', 'convert_chars' );
			add_filter( 'snippets_plus_content', 'wpautop' );
			add_filter( 'snippets_plus_content', 'shortcode_unautop' );
			add_filter( 'snippets_plus_content', 'do_shortcode' );
		}
	}

	function widget( $args, $instance ) {
		extract( $args );
		$instance = wp_parse_args( $instance, $this->get_defaults( $instance ) );
		if( ! is_singular() || $instance['post_type'] !== get_post_type() )
			return '';

		$id = $this->_get_id( $instance );
		$post_id = get_queried_object_id();
		$title = get_post_meta( $post_id, "_snippet_{$id}_title", true );
		$content = get_post_meta( $post_id, "_snippet_{$id}_content", true );

		if( empty( $content ) )
			return '';

		echo $before_widget;

		if( $title )
			echo $before_title . apply_filters( 'widget_title', $title, $instance, $this->id_base ) . $after_title;

		echo apply_filters( 'snippets_plus_content', $content );

		echo $after_widget;
	}

	function update( $new_instance, $old_instance ) {
		return $new_instance;
	}

	function form( $instance ) {
		$instance = wp_parse_args( $instance, $this->get_defaults( $instance ) ); ?>
		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'snippets-plus' ); ?> *</label>
			<input type="text" class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo esc_attr( $instance['title'] ); ?>" />
			<span class="description"><?php _e( 'Title must be unique for each snippet.', 'snippets-plus' ); ?></span>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id( 'post_type' ); ?>"><?php _e( 'Post type', 'snippets-plus' ); ?></label>
			<select class="widefat" id="<?php echo $this->get_field_id('post_type'); ?>" name="<?php echo $this->get_field_name( 'post_type' ); ?>">
				<?php
				$post_types = get_post_types( array( 'public' => true ), 'objects' );
				unset( $post_types['attachment'] );
				foreach( $post_types as $key => $type ) : ?>
				<option value="<?php echo $key; ?>" <?php selected( $key, $instance['post_type'] ) ?>> <?php echo $type->name; ?> </option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php _e( 'Show the snippets admin screen only for the selected post type.', 'snippets-plus' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Default widget options
	 *
	 * @return array
	 */
	function get_defaults( $instance ) {
		return array(
			'title' => __( 'My Snippet', 'snippets-plus' ),
			'post_type' => 'page',
		);
	}

	public function get_regions( $post_type = null ) {
		$regions = get_option( 'widget_snippets-plus', array() );
		unset( $regions['_multiwidget'] );
		if( $post_type ) {
			foreach( $regions as $key => $value ) {
				if( $value['post_type'] != $post_type ) {
					unset( $regions[$key] );
				}
			}
		}

		return apply_filters( 'snippets_plus_regions', $regions );
	}

	/**
	 * Adds the meta box.
	 *
	 * @since  0.2.0
	 * @access public
	 * @return void
	 */
	public function add_meta_boxes( $post_type ) {
		$post_type_object = get_post_type_object( $post_type );
		if ( 'page' !== $post_type && false === $post_type_object->publicly_queryable )
			return;

		foreach( $this->get_regions() as $id => $options ) {
			add_meta_box(
				'snippets-plus',
				__( 'Snippets', 'snippets-plus' ),
				array( $this, 'snippet_meta_box' ),
				$options['post_type'],
				'normal',
				'low'
			);
		}
	}

	public function snippet_meta_box( $post, $box ) {
		wp_nonce_field( basename( __FILE__ ), 'snippets_plus_meta_nonce' );
		foreach( $this->get_regions( $post->post_type ) as $options ) {
			$id = $this->_get_id( $options );
			$title = get_post_meta( $post->ID, "_snippet_{$id}_title", true ); 
			$content = get_post_meta( $post->ID, "_snippet_{$id}_content", true ); ?>
			<p>
				<label for="snippet-<?php echo $id; ?>-title"><?php printf( __( '%s Title', 'snippets-plus' ), $options['title'] ); ?></label> 
				<input class="widefat" type="text" name="snippet-<?php echo $id; ?>-title" id="snippet-<?php echo $id; ?>-title" value="<?php echo esc_attr( $title ); ?>" />
			</p>
			<p>
				<label for="snippet-<?php echo $id; ?>-content"><?php printf( __( '%s Content', 'snippets-plus' ), $options['title'] ); ?></label>
				<textarea class="widefat" name="snippet-<?php echo $id; ?>-content" id="snippet-<?php echo $id; ?>-content" cols="60" rows="4"><?php echo esc_textarea( $content ); ?></textarea>
			</p><?php
		}
	}

	public function save_post( $post_id, $post ) {
		/* Verify the nonce. */
		if ( !isset( $_POST['snippets_plus_meta_nonce'] ) || !wp_verify_nonce( $_POST['snippets_plus_meta_nonce'], basename( __FILE__ ) ) )
			return;
		/* Get the post type object. */
		$post_type = get_post_type_object( $post->post_type );
		/* Check if the current user has permission to edit the post. */
		if ( !current_user_can( $post_type->cap->edit_post, $post_id ) )
			return $post_id;
		/* Don't save if the post is only a revision. */
		if ( 'revision' == $post->post_type )
			return;

		foreach( $this->get_regions( $post->post_type ) as $options ) {
			$id = $this->_get_id( $options );
			$this->save_meta( "_snippet_{$id}_title", $_POST["snippet-{$id}-title"], $post_id );
			$this->save_meta( "_snippet_{$id}_content", $_POST["snippet-{$id}-content"], $post_id );
		}
	}

	public function save_meta( $meta_key, $new_meta_value, $post_id = null ) {
		global $post;

		if( ! $post_id )
			$post_id = $post->ID;
		$meta_value = get_post_meta( $post_id, $meta_key, true );
		if ( $new_meta_value && '' == $meta_value )
			add_post_meta( $post_id, $meta_key, $new_meta_value, true );
		elseif ( $new_meta_value && $new_meta_value != $meta_value )
			update_post_meta( $post_id, $meta_key, $new_meta_value );
		elseif ( '' == $new_meta_value && $meta_value )
			delete_post_meta( $post_id, $meta_key, $meta_value );
	}

	public function _get_id( $widget ) {
		return sanitize_title_with_dashes( $widget['title'] );
	}

	public static function register() {
		register_widget( __CLASS__ );
	}

	public function load_plugin_integration() {
		if( class_exists( 'Polylang' ) ) {
			require_once( dirname( __FILE__ ) . '/includes/polylang-compat.php' );
		}
	}
}
add_action( 'widgets_init', array( 'Snippets_Plus_Widget', 'register' ) );