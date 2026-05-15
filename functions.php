<?php
/**
 * Twenty Twenty-Five functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package WordPress
 * @subpackage Twenty_Twenty_Five
 * @since Twenty Twenty-Five 1.0
 */
 
add_filter('style_loader_tag', function ($html, $handle) {
    if ($handle === 'tailwind') {
        return str_replace(
            "media='all'",
            "media='print' onload=\"this.onload=null;this.media='all';\"",
            $html
        );
    }
    return $html;
}, 10, 2);

function enqueue_fullcalendar() {
    if (is_user_logged_in()) {
        wp_enqueue_style('fullcalendar-css', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css');
        wp_enqueue_script('fullcalendar-js', 'https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js', array('jquery'), null, true);
    }
}
add_action('wp_enqueue_scripts', 'enqueue_fullcalendar');


// Adds theme support for post formats.
if ( ! function_exists( 'twentytwentyfive_post_format_setup' ) ) :
	/**
	 * Adds theme support for post formats.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_post_format_setup() {
		add_theme_support( 'post-formats', array( 'aside', 'audio', 'chat', 'gallery', 'image', 'link', 'quote', 'status', 'video' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_post_format_setup' );

// Enqueues editor-style.css in the editors.
if ( ! function_exists( 'twentytwentyfive_editor_style' ) ) :
	/**
	 * Enqueues editor-style.css in the editors.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_editor_style() {
		add_editor_style( get_parent_theme_file_uri( 'assets/css/editor-style.css' ) );
	}
endif;
add_action( 'after_setup_theme', 'twentytwentyfive_editor_style' );

// Enqueues style.css on the front.
if ( ! function_exists( 'twentytwentyfive_enqueue_styles' ) ) :
	/**
	 * Enqueues style.css on the front.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_enqueue_styles() {
		wp_enqueue_style(
			'twentytwentyfive-style',
			get_parent_theme_file_uri( 'style.css' ),
			[],
			'30.40',
			'all'
		);
	}
endif;
add_action( 'wp_enqueue_scripts', 'twentytwentyfive_enqueue_styles' );

// Registers custom block styles.
if ( ! function_exists( 'twentytwentyfive_block_styles' ) ) :
	/**
	 * Registers custom block styles.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_block_styles() {
		register_block_style(
			'core/list',
			array(
				'name'         => 'checkmark-list',
				'label'        => __( 'Checkmark', 'twentytwentyfive' ),
				'inline_style' => '
				ul.is-style-checkmark-list {
					list-style-type: "\2713";
				}

				ul.is-style-checkmark-list li {
					padding-inline-start: 1ch;
				}',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_block_styles' );

// Registers pattern categories.
if ( ! function_exists( 'twentytwentyfive_pattern_categories' ) ) :
	/**
	 * Registers pattern categories.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_pattern_categories() {

		register_block_pattern_category(
			'twentytwentyfive_page',
			array(
				'label'       => __( 'Pages', 'twentytwentyfive' ),
				'description' => __( 'A collection of full page layouts.', 'twentytwentyfive' ),
			)
		);

		register_block_pattern_category(
			'twentytwentyfive_post-format',
			array(
				'label'       => __( 'Post formats', 'twentytwentyfive' ),
				'description' => __( 'A collection of post format patterns.', 'twentytwentyfive' ),
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_pattern_categories' );

// Registers block binding sources.
if ( ! function_exists( 'twentytwentyfive_register_block_bindings' ) ) :
	/**
	 * Registers the post format block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return void
	 */
	function twentytwentyfive_register_block_bindings() {
		register_block_bindings_source(
			'twentytwentyfive/format',
			array(
				'label'              => _x( 'Post format name', 'Label for the block binding placeholder in the editor', 'twentytwentyfive' ),
				'get_value_callback' => 'twentytwentyfive_format_binding',
			)
		);
	}
endif;
add_action( 'init', 'twentytwentyfive_register_block_bindings' );

// Registers block binding callback function for the post format name.
if ( ! function_exists( 'twentytwentyfive_format_binding' ) ) :
	/**
	 * Callback function for the post format name block binding source.
	 *
	 * @since Twenty Twenty-Five 1.0
	 *
	 * @return string|void Post format name, or nothing if the format is 'standard'.
	 */
	function twentytwentyfive_format_binding() {
		$post_format_slug = get_post_format();

		if ( $post_format_slug && 'standard' !== $post_format_slug ) {
			return get_post_format_string( $post_format_slug );
		}
	}
endif;

function fetch_events() {
	$events = array();
	$args = array(
		'post_type'      => 'event',
		'posts_per_page' => -1,
		'post_status'    => 'publish',
		'meta_query'     => array(
			array(
				'key'     => 'start_date',
				'value'   => date('Y-m-d', strtotime('-6 months')),
				'compare' => '>=',
				'type'    => 'DATE'
			)
		)
	);
	$query = new WP_Query($args);
	if ($query->have_posts()) {
		while ($query->have_posts()) {
			$query->the_post();
			$start_date = get_field('start_date');
			$end_date   = get_field('end_date');
			$sign_up_url = get_field('sign_up_url') ?: get_permalink();

			$events[] = array(
				'title' => get_the_title(),
				'start' => date('c', strtotime($start_date)), // Convert to ISO 8601
				'end'   => $end_date ? date('c', strtotime($end_date)) : null,
				'url'   => $sign_up_url,
				'id'    => get_the_ID(),
			);
		}
	}
	wp_reset_postdata();
	wp_send_json($events);
}
add_action('wp_ajax_fetch_events', 'fetch_events');
add_action('wp_ajax_nopriv_fetch_events', 'fetch_events');

// Move Yoast to bottom
function yoasttobottom() {
	return 'low';
}
add_filter( 'wpseo_metabox_prio', 'yoasttobottom');

function register_sponsors_cpt() {
    $labels = array(
        'name'               => 'Sponsors',
        'singular_name'      => 'Sponsor',
        'menu_name'          => 'Sponsors',
        'add_new'            => 'Add New Sponsor',
        'add_new_item'       => 'Add New Sponsor',
        'edit_item'          => 'Edit Sponsor',
        'new_item'           => 'New Sponsor',
        'view_item'          => 'View Sponsor',
        'search_items'       => 'Search Sponsors',
        'not_found'          => 'No sponsors found',
        'not_found_in_trash' => 'No sponsors found in Trash',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'menu_icon'          => 'dashicons-megaphone',
        'supports'           => array('title', 'editor', 'thumbnail'),
        'has_archive'        => true,
        'show_in_rest'       => false, // Not using Gutenberg
    );

    register_post_type('sponsor', $args);
}
add_action('init', 'register_sponsors_cpt');

// suppressing bloat
remove_action('wp_head', 'wp_generator');
remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rest_output_link_wp_head');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_oembed_add_discovery_links');

function disable_wp_embed() {
    wp_deregister_script('wp-embed');
}
add_action('wp_footer', 'disable_wp_embed');

function dequeue_unused_scripts() {
    wp_dequeue_script('twentytwentyfive-navigation'); // or actual handle
    wp_dequeue_script('twentytwentyfive-style');      // only if it's JS
}
add_action('wp_enqueue_scripts', 'dequeue_unused_scripts', 20);

function dequeue_jquery_migrate( $scripts ) {
    if (!is_admin() && isset($scripts->registered['jquery'])) {
        $script = $scripts->registered['jquery'];
        if ($script->deps) { 
            $script->deps = array_diff($script->deps, ['jquery-migrate']);
        }
    }
}
add_action('wp_default_scripts', 'dequeue_jquery_migrate');

add_filter('wp_generate_attachment_metadata', function($metadata, $attachment_id) {
    $upload_dir = wp_upload_dir();
    $file = get_attached_file($attachment_id);
    $info = pathinfo($file);
    $ext = strtolower($info['extension']);

    if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
        $webp_path = $info['dirname'] . '/' . $info['filename'] . '.webp';
        if (!file_exists($webp_path)) {
            $image = imagecreatefromstring(file_get_contents($file));
            if ($image && function_exists('imagewebp')) {
                imagewebp($image, $webp_path, 80); // 80% quality
                imagedestroy($image);
            }
        }
    }

    return $metadata;
}, 10, 2);

remove_action('wp_head', 'print_emoji_detection_script', 7);
remove_action('wp_print_styles', 'print_emoji_styles');