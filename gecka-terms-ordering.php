<?php
/*
Plugin Name: Gecka Terms Ordering
Plugin URI: http://gecka-apps.com/wordpress-plugins/terms-ordering/
Description: Order your categories, tags or any other taxonomy of your Wordpress website
Version: 1.1
Author: Gecka
Author URI: http://gecka.nc
Licence: GPL
*/

/*
Copyright 2012  Gecka Apps (email : contact@gecka-apps.com)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$gecka_term_ordering = Gecka_Terms_Ordering::instance();

class Gecka_Terms_Ordering {
	private static $instance;

	private static $taxonomies = array('category');
	private static $plugin_url;
	private static $plugin_path;

	private function __construct () {
		self::$plugin_url 	= plugins_url('', __FILE__);
		self::$plugin_path 	= dirname(__FILE__);
		
		register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );

		add_action( 'plugins_loaded', array($this, 'plugins_loaded'), 5 );
		add_action( 'after_setup_theme', array($this, 'after_setup_theme'), 5 );

		add_action( 'admin_init', array($this, 'admin_init') );
		
		// Default orders
		add_filter( 'get_terms_args', array( $this, 'get_terms_args') , 10, 2 );
		add_filter( 'get_terms_orderby', array( $this, 'get_terms_orderby') , 10, 2 );

		add_action( 'created_term', array($this, 'created_term') , 10, 3 );
	}

	public static function instance () {

		if ( ! isset( self::$instance ) ) {
			$class_name = __CLASS__;
			self::$instance = new $class_name;
		}

		return self::$instance;

	}

	/**
     * Add custom ordering support to one or more taxonomies
     * 
     * @param string|array $taxonomy 
     */
	public static function add_taxonomy_support($taxonomy) {
		$taxonomies = (array)$taxonomy ;
		self::$taxonomies = array_merge(self::$taxonomies, $taxonomies);
	}

	/**
     * Add custom ordering support to one or more taxonomies
     * 
     * @param string|array $taxonomy 
     */
	public static function remove_taxonomy_support($taxonomy) {

		$key = array_search ($taxonomy, self::$taxonomies);
		if( false !== $key ) unset( self::$taxonomies[$key] );

	}

	public static function has_support ($taxonomy) {
		if( in_array($taxonomy, self::$taxonomies) ) return true;
		return false;
	}

	/**
     * Hooks and filters
     */
	public function activation_hook () {
		global $wpdb;

		$result = $wpdb->query("SHOW COLUMNS FROM $wpdb->term_taxonomy LIKE 'order'");
		if ($result == 0) {
			$wpdb->query("ALTER TABLE $wpdb->term_taxonomy ADD `order` INT( 4 ) NULL DEFAULT '0'");
		}
	}

	public function plugins_loaded () {
		self::$taxonomies = apply_filters( 'term-ordering-default-taxonomies', self::$taxonomies );
	}

	public function after_setup_theme () {
		self::$taxonomies = apply_filters( 'term-ordering-taxonomies', self::$taxonomies );
	}

	public function admin_init () {
		// Load needed scripts to order terms
		add_action( 'admin_footer-edit-tags.php', array($this, 'admin_enqueue_scripts'), 10 );
		add_action( 'admin_print_styles-edit-tags.php', array($this, 'admin_css'), 1 );

		// Httpr handler for drag and drop ordering
		add_action( 'wp_ajax_terms-ordering', array($this, 'terms_ordering_httpr') );
		
		// Always check if table have correct column
		$this->activation_hook();
	}

	/**
 	 * Load needed scripts to order categories in admin
 	 */
	public function admin_enqueue_scripts () {
		if( ! isset( $_GET['taxonomy'] ) || ! self::has_support( $_GET['taxonomy'] ) ) return;

		wp_register_script( 'gecka-terms-ordering', self::$plugin_url . '/javascripts/terms-ordering.js', array('jquery-ui-sortable'));
		wp_enqueue_script( 'gecka-terms-ordering' );

		wp_localize_script( 'gecka-terms-ordering', 'terms_order', array( 'taxonomy'=>$_GET['taxonomy'] ) );

		wp_print_scripts('gecka-terms-ordering');
	}

	public function admin_css () {
		if( ! isset( $_GET['taxonomy'] ) || ! self::has_support( $_GET['taxonomy'] ) ) return;
		echo '<style type="text/css">.widefat  .product-cat-placeholder { outline: 1px dotted #21759B; height: 60px; }</style>';
	}

	/**
	 * Httpr handler for categories ordering
	 */
	public function terms_ordering_httpr () {
		global $wpdb;

		$id = (int)$_POST['id'];
		$next_id  = isset($_POST['nextid']) && (int) $_POST['nextid'] ? (int) $_POST['nextid'] : null;
		$taxonomy = isset($_POST['taxonomy']) && $_POST['taxonomy'] ? $_POST['taxonomy'] : null;

		if( ! $id || ! $term = get_term_by('id', $id, $taxonomy) ) die(0);

		$this->place_term ( $term, $taxonomy, $next_id );

		$children = get_terms( $taxonomy, "child_of=$id&menu_order=ASC&hide_empty=0" );

		if( $term && sizeof($children) ) {
			'children';
			die;
		}
	}

	/**
	 * Improve args array with add taxonomies
	 */
	function get_terms_args( $args, $taxonomies ) {
		$args['taxonomies'] = $taxonomies;
		return $args;
	}

	/**
	 * Add term ordering suport to get_terms, set it as default
	 * 
	 * It enables the support a 'order' parameter to get_terms for the configured taxonomy.
	 * 
	 * To disable it, set auto_order to false (or 0)
	 * 
	 */
	function get_terms_orderby( $orderby, $args ) {
		global $wpdb;

		$taxonomies = (array)$args['taxonomies'];
		if( sizeof( $taxonomies === 1 ) ) $taxonomy = array_shift( $taxonomies );
		else return $orderby;

		if( !$this->has_support($taxonomy) ) return $orderby;

		// order
		if( isset($args['auto_order']) && $args['auto_order'] == false ) return $orderby; // auto_order is false whe do not add order clause

		return 'tt.order';
	}
	
	/**
	 * Reorder on term insertion
	 * 
	 * @param int $term_id
	 */
	public function created_term ($term_id, $tt_id, $taxonomy) {
		if( !$this->has_support($taxonomy) ) return;

		$next_id = null;

		$term = get_term($term_id, $taxonomy);

		// gets the sibling terms
		$siblings = get_terms($taxonomy, "parent={$term->parent}&menu_order=ASC&hide_empty=0");

		foreach ($siblings as $sibling) {
			if( $sibling->term_id == $term_id ) continue;
			$next_id =  $sibling->term_id; // first sibling term of the hierachy level
			break;
		}

		// reorder
		$this->place_term ( $term, $taxonomy, $next_id );
	}

	/**
	 * Move a term before the a	given element of its hierachy level
	 *
	 * @param object $the_term
	 * @param int $next_id the id of the next slibling element in save hierachy level
	 * @param int $index
	 * @param int $terms
	 */
	private function place_term ( $the_term, $taxonomy, $next_id, $index=0, $terms=null ) {

		if( ! $terms ) $terms = get_terms($taxonomy, 'menu_order=ASC&hide_empty=0&parent=0');
		if( empty( $terms ) ) return $index;

		$id	= $the_term->term_id;

		$term_in_level = false; // flag: is our term to order in this level of terms

		foreach ( $terms as $term ) {

			if( $term->term_id == $id ) { // our term to order, we skip
				$term_in_level = true;
				continue; // our term to order, we skip
			}
			// the nextid of our term to order, lets move our term here
			if( null !== $next_id && $term->term_id == $next_id ) {
				$index = $this->set_term_order( $id, $taxonomy, $index+1, true );
			}

			// set order
			$index = $this->set_term_order( $term->term_id, $taxonomy, $index+1 );

			// if that term has children we walk thru them
			$children = get_terms($taxonomy, "parent={$term->term_id}&menu_order=ASC&hide_empty=0");
			if( !empty($children) ) {
				$index = $this->place_term( $the_term, $taxonomy, $next_id, $index, $children );
			}
		}

		// no nextid meaning our term is in last position
		if( $term_in_level && null === $next_id )
		$index = $this->set_term_order( $id, $taxonomy, $index+1, true );

		return $index;

	}

	/**
	 * Set the sort order of a term
	 * 
	 * @param int $term_id
	 * @param int $index
	 * @param bool $recursive
	 */
	private function set_term_order ( $term_id, $taxonomy, $index, $recursive=false ) {
		global $wpdb;

		$term_id 	= (int) $term_id;
		$index 		= (int) $index;
		$term 		= get_term($term_id, $taxonomy);

		$wpdb -> update( $wpdb->term_taxonomy, array( 'order' => $index ), array( 'term_taxonomy_id' => $term->term_taxonomy_id ) );

		if( ! $recursive ) return $index;

		$children = get_terms( $taxonomy, "parent=$term_id&menu_order=ASC&hide_empty=0" );

		foreach ( $children as $term ) {
			$index ++;
			$index = $this->set_term_order ( $term->term_id, $index, true );
		}

		return $index;

	}

}

if( ! function_exists('add_term_ordering_support') ) {
	function add_term_ordering_support ($taxonomy) {
		Gecka_Terms_Ordering::add_taxonomy_support($taxonomy);
	}
}

if( ! function_exists('remove_term_ordering_support') ) {
	function remove_term_ordering_support ($taxonomy) {
		Gecka_Terms_Ordering::remove_taxonomy_support($taxonomy);
	}
}

if( ! function_exists('has_term_ordering_support') ) {
	function has_term_ordering_support ($taxonomy) {
		return Gecka_Terms_Ordering::has_support($taxonomy);
	}
}