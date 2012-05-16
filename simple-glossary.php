<?php
/*
Plugin Name: Simple Glossary
Description: Define domain specific terms and autolink to their definitions.
Author: _FindingSimple
Author URI: http://findingsimple.com/
Version: 1.0

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
 * @package Simple Glossary
 * @version 1.0
 * @author Brent Shepherd <brent@findingsimple.com>
 * @copyright Copyright (c) 2012 Finding Simple
 * @link http://findingsimple.com/
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 */

if ( ! class_exists( 'FS_Simple_Glossary' ) ) {

FS_Simple_Glossary::init();

class FS_Simple_Glossary {

	static $text_domain;

	/**
	 * Hook into WordPress where appropriate.
	 */
	public static function init() {

		self::$text_domain = apply_filters( 'simple_glossary_text_domain', 'Simple_Glossary' );

		add_action( 'init', __CLASS__ . '::register_post_type' );

		add_filter( 'posts_where', __CLASS__ . '::glossary_search_where' );

		add_filter( 'pre_get_posts', __CLASS__ . '::glossary_nopaging' );

		add_action( 'generate_rewrite_rules', __CLASS__ . '::add_glossary_rewrite_rules' );

		add_filter( 'query_vars', __CLASS__ . '::add_glossary_query_vars' );

		add_filter( 'the_content', __CLASS__ . '::autolink_glossary_terms' );

		add_action( 'template_redirect', __CLASS__ . '::circumvent_single_glossary_pages' );

		add_action( 'post_type_link', __CLASS__ . '::individual_glossary_term_uri', 10, 2 );

		add_filter( 'posts_orderby', __CLASS__ . '::order_glossary_terms', 10, 1 );

		add_filter( 'enter_title_here', __CLASS__ . '::change_default_title' );

	}

	/**
	 * Calls @see register_post_type function to create all the custom post types required on the EEO site.
	 *
	 * @author Jason Conroy <jason@findingsimple.com>, Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function register_post_type() {
		register_post_type( 'glossary_term',
			array(
				'description'         => __( 'The glossary post type to store glossary terms.', self::$text_domain ),
				'public'              => true,
				'has_archive'         => true,
				'supports'            => array( 'title', 'editor', 'revisions' ),
				'rewrite'             => array( 'slug' => 'glossary', 'with_front' => false ),
				'show_in_nav_menus'   => false,
				'exclude_from_search' => true,
				'label'               => __( 'Glossary', self::$text_domain ),
				'labels'              => array(
					'name'               => __( 'Glossary', self::$text_domain ),
					'singular_name'      => __( 'Glossary Term', self::$text_domain ),
					'all_items'          => __( 'All Glossary Terms', self::$text_domain ),
					'add_new_item'       => __( 'Add New Term', self::$text_domain ),
					'edit_item'          => __( 'Edit Term', self::$text_domain ),
					'new_item'           => __( 'New Term', self::$text_domain ),
					'view_item'          => __( 'View Term', self::$text_domain ),
					'search_items'       => __( 'Search Terms', self::$text_domain ),
					'not_found'          => __( 'No terms found', self::$text_domain ),
					'not_found_in_trash' => __( 'No terms found in trash', self::$text_domain ),
				),
			)
		);
	}


	/**
	* Boolean function to check whether the current page is a self::glossary_term post archive.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function is_glossary_archive() {
		global $wp_query;

		if( is_post_type_archive() && $wp_query->query_vars['post_type'] == 'glossary_term' )
			return true;
		else
			return false;
	}


	/**
	 * Returns the URI to the Glossary Archive page. 
	 *
	 */
	public static function get_glossary_archive_uri() {
		return site_url( '/glossary/' );
	}


	/**
	 * Returns the glossary filter form.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function glossary_filter_form() {
		global $wp_query;

		$begins_with = ( isset( $wp_query->query_vars['begins-with'] ) ) ? $wp_query->query_vars['begins-with'] : '';
		?>
		<div class="filter">
			<form method="post" class="filter-form" action="<?php echo trailingslashit( self::get_glossary_archive_uri() ); ?>">
			<div>
				<input class="filter-text" type="text" name="begins-with" value="<?php echo $begins_with; ?>" />
				<input class="button" name="glossary-filter" type="submit" value="<?php esc_attr_e( 'Update', self::$text_domain ); ?>" />
			</div>
			</form><!-- .filter-form -->
		</div><!-- .filter -->
	<?php 
	}


	/**
	 * Returns an array containing the first letter of all glossary terms and the number of terms 
	 * that start with that letter.
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function get_glossary_terms_first_letters() {

		$glossary_terms = get_posts( array( 
								'post_type'     => 'glossary_term',
								'numberposts'   => -1,
								'orderby'       => 'title', // Sort alphabetically
								'order'         => 'ASC'
								) 
						);

		$first_letters = array();

		if ( ! empty( $glossary_terms ) ) {

			$curr_letter = '';

			foreach( $glossary_terms as $term ) {

				$first_letter = strtoupper( substr( $term->post_title, 0, 1 ) );

				if ( $first_letter != $curr_letter ) {
					$first_letters[$first_letter] = 1;
					$curr_letter = $first_letter;
				} else {
					$first_letters[$first_letter]++;
				}
			}
		}

		return $first_letters;
	}


	/**
	 * Returns the URI for filtering glossary terms page.
	 * 
	 * @param string $letter the letter to create a filter URI for
	 * @param array $args optional, an array of name value pairs to customise behaviour or the funciton
	 * 			'echo' default true indicates that the URI should be printed as well as returns
	 * 			'remove_filter' default true indicates that if a filter for the given $letter exists, it should be removed
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function the_glossary_filter_uri( $letter, $args = array() ) {
		global $wp_query;

		$defaults = array(
			'echo'          => true,
			'remove_filter' => true
			);

		$args = wp_parse_args( $args, $defaults );

		// If there is already a filter running for this letter, remove it
		if( isset( $wp_query->query_vars['letter'] ) && $letter ==  $wp_query->query_vars['letter'] && $args['remove_filter'] )
			$letter_filter = '';
		else
			$letter_filter = trailingslashit( 'letter/' . $letter );

		$glossary_filter_uri = self::get_glossary_archive_uri() . $letter_filter;

		if( $args['echo'] )
			echo $glossary_filter_uri;

		return $glossary_filter_uri;
	}


	/**
	 * Returns the filter parameters for the current query (if any)
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function get_glossary_filters() {
		global $wp_query;

		if( isset( $wp_query->query_vars['letter'] ) && ! empty( $wp_query->query_vars['letter'] ) )
			return array( 'letter' => "'" . $wp_query->query_vars['letter'] . "'" );
		elseif( isset( $wp_query->query_vars['begins-with'] ) && ! empty( $wp_query->query_vars['begins-with'] ) )
			return array( 'begins-with' => "'" . $wp_query->query_vars['begins-with'] . "'" );
		else
			return array();
	}


	/**
	 * Filters glossary results 
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function glossary_search_where( $where ) {
		global $wpdb, $wp_query;

		if( self::is_glossary_archive() ) {

			$filters = self::get_glossary_filters();

			if( ! empty( $filters['letter'] ) )
				$where .= sprintf( " AND SUBSTR($wpdb->posts.post_title,1,1) = %s", $filters['letter'] );

			if( ! empty( $filters['begins-with'] ) )
				$where .= sprintf( " AND SUBSTR($wpdb->posts.post_title,1,%s) = %s", strlen( $filters['begins-with'] ) - 2, $filters['begins-with'] );

		}

		return $where;
	}


	/**
	 * When filtering results by letter, don't page the glossary terms. This is required
	 * for auto-linking to work without having individual glossary pages. 
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function glossary_nopaging( $query ) {

		if( self::is_glossary_archive() && isset( $query->query_vars['letter'] ) )
			$query->set( 'nopaging', true );

		return $query;
	}


	/**
	 * Creates the rewrite rules for our glossary filters
	 * 
	 * Despite the rewrite rule name, a letter can actually be a number because a term may in fact start with a number.
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function add_glossary_rewrite_rules( $wp_rewrite ) {
		global $wp_rewrite;

		$new_rules = array( 'glossary/letter/(.+)/?$' => 'index.php?post_type=glossary_term&letter=' . $wp_rewrite->preg_index(1) );

		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;
	}


	/**
	 * Adds the filter query parameters to the WP_Query object.
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function add_glossary_query_vars( $query_vars ) {

		$query_vars[] = 'letter';
		$query_vars[] = 'begins-with';

		return $query_vars;
	}


	/**
	 * Echos a selected class for a given taxonomy and term. 
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function glossary_filter_class( $letter ) {
		global $wp_query;

		if( isset( $wp_query->query_vars['letter'] ) && strcasecmp( $wp_query->query_vars['letter'], $letter ) == 0 )
			echo 'selected';
		elseif( isset( $wp_query->query_vars['begins-with'] ) && strcasecmp( substr( $wp_query->query_vars['begins-with'], 0, 1 ), $letter ) == 0 )
			echo 'selected';
	}


	/**
	 * Parses post content and checks for glossary terms. 
	 * 
	 * The first occurrence of each glossary term is linked to the relevant glossary page.
	 * But only if the post content does not already contain a link to that glossary page and
	 * the post content is not the glossary term.
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function autolink_glossary_terms( $content ) {
		global $post;

		if( ! is_object( $post ) )
			return $content;

		$glossary_terms = get_posts( array( 'post_type' => 'glossary_term', 'numberposts' => -1 ) );

		foreach( $glossary_terms as $glossary_term ) {

			// Don't create circular references
			if( $post->post_type == 'glossary_term' && $post->post_title == $glossary_term->post_title )
				continue;

			$glossary_term->post_title = trim( $glossary_term->post_title );

			// Create a URI of the form /glossary/letter/x/#term
			$glossary_term_uri = self::the_glossary_filter_uri( substr( $glossary_term->post_title, 0, 1 ), array( 'echo' => false, 'remove_filter' => false ) ) . '#' . urlencode( $glossary_term->post_title );

			// Don't link when a link to the glossary page already exists (regardless of the links content and whether it is the first term)
			$pattern = '/<a(.*)' . preg_quote( $glossary_term_uri, '/' ) . '(.*)>/i';
			if( preg_match( $pattern, $content ) != 0 )
				continue;

			// Only link whole words, that are not within <> tags or [] shortcode tags & not between header tags <h1-h6>
			$pattern = '/([^a-zA-Z0-9-_>\[])(' . preg_quote( $glossary_term->post_title ) . ')([^a-zA-Z0-9-_<\]])(?!([^<]*)(\>|\<\/a|\]|<\/h\d\>))/i';

			$replacement = sprintf( '$1<a href="%s" title="%s" class="glossary-item">$2</a>$3', $glossary_term_uri, sprintf( __( 'Glossary page for %s', self::$text_domain ), $glossary_term->post_title ) );

			$content = preg_replace( $pattern, $replacement, $content, 1 );
		}

		return $content;
	}


	/**
	 * Redirects any requests for a single glossary term to the index page /glossary/letter/X/#x-term
	 * 
	 * Used to remove individual glossary term pages. 
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function circumvent_single_glossary_pages() {
		global $wp_query;

		if( is_single() && $wp_query->query_vars['post_type'] == 'glossary_term' ) {
			wp_safe_redirect( self::individual_glossary_term_uri( '', $wp_query->post ) );
		}

	}


	/**
	 * Creates a URI for a single glossary term page of the form /glossary/letter/X/#x-term
	 * 
	 * This function is both hooked to hijack the default post_type_link for all posts of type
	 * 'glossary_term' and also used to create links for specific post types in other functions, 
	 * such as @see self::circumvent_single_glossary_pages.
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function individual_glossary_term_uri( $link, $post ) {

		if ( $post->post_type == 'glossary_term') {
			$post->post_title = trim( $post->post_title );
			$link = self::the_glossary_filter_uri( substr( $post->post_title, 0, 1 ), array( 'echo' => false, 'remove_filter' => false ) ) . '#' . urlencode( $post->post_title );
		}

		return $link;
	}

	/**
	 * If a query is for Glossary terms, order them alphabetically. 
	 * 
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function order_glossary_terms( $orderby ){
		global $wpdb;

		if( self::is_glossary_archive() )
			$orderby = " $wpdb->posts.post_title ASC";

		return $orderby;
	}

	/**
	 * Replaces the "Enter title here" text with 
	 *
	 * @author Brent Shepherd <brent@findingsimple.com>
	 * @package Simple Glossary
	 * @since 1.0
	 */
	public static function change_default_title( $title ){
		$screen = get_current_screen();

		if  ( 'eeo_glossary_term' == $screen->post_type )
			$title = __( 'Enter Term', self::$text_domain );

		return $title;
	}

}

}