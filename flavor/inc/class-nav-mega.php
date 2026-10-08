<?php
/**
 * Mega menu: a multi-column panel for navigation branches with many children.
 *
 * The panel is pure CSS (multi-column layout + grid), so it costs no extra
 * JavaScript and stays keyboard accessible; the script only maintains
 * `aria-expanded` and closes the panel on Escape.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Nav_Mega
 */
class Nav_Mega {

	/**
	 * Children per parent, cached per menu.
	 *
	 * @var array<int, int>
	 */
	private static $children = array();

	/**
	 * Hooks.
	 */
	public static function init(): void {
		add_filter( 'nav_menu_css_class', array( self::class, 'classes' ), 10, 4 );
		add_filter( 'nav_menu_link_attributes', array( self::class, 'link_attributes' ), 10, 4 );
		add_filter( 'walker_nav_menu_start_el', array( self::class, 'description' ), 10, 4 );
	}

	/**
	 * Is the feature on?
	 */
	public static function enabled(): bool {
		return 'no' !== get_theme_mod( 'flavor_mega_enable', 'yes' );
	}

	/**
	 * Menu locations allowed to use the panel.
	 *
	 * @return array<int, string>
	 */
	public static function locations(): array {
		$locations = (array) apply_filters( 'flavor_mega_locations', array( 'primary' ) );
		return array_map( 'strval', $locations );
	}

	/**
	 * Number of columns, clamped to something a phone can still read.
	 */
	public static function columns(): int {
		$columns = absint( get_theme_mod( 'flavor_mega_columns', 3 ) );
		return max( 2, min( 4, $columns ? $columns : 3 ) );
	}

	/**
	 * How many children turn a plain dropdown into a panel.
	 */
	public static function threshold(): int {
		$threshold = absint( get_theme_mod( 'flavor_mega_threshold', 5 ) );
		return max( 2, min( 20, $threshold ? $threshold : 5 ) );
	}

	/**
	 * Children count per parent item id for a menu.
	 *
	 * @param mixed $menu Menu id, slug or object.
	 * @return array<int, int>
	 */
	public static function children_map( $menu ): array {
		$menu = wp_get_nav_menu_object( $menu );

		if ( ! $menu || empty( $menu->term_id ) ) {
			return array();
		}

		$key = (int) $menu->term_id;

		if ( isset( self::$children[ $key ] ) ) {
			return self::$children[ $key ];
		}

		$map   = array();
		$items = wp_get_nav_menu_items( $key );

		if ( is_array( $items ) ) {
			foreach ( $items as $item ) {
				$parent = (int) $item->menu_item_parent;
				if ( $parent > 0 ) {
					$map[ $parent ] = ( $map[ $parent ] ?? 0 ) + 1;
				}
			}
		}

		self::$children[ $key ] = $map;

		return $map;
	}

	/**
	 * Does this item qualify for a panel?
	 *
	 * @param mixed $item Menu item.
	 * @param mixed $args Menu args.
	 * @return bool
	 */
	public static function is_mega( $item, $args = null ): bool {
		if ( ! is_object( $item ) || empty( $item->ID ) ) {
			return false;
		}

		$forced = array();

		if ( ! empty( $item->classes ) && is_array( $item->classes ) ) {
			$forced = $item->classes;
		}

		// An explicit class always wins: `mega-menu` is the convention other
		// commercial themes use, so a migrated menu keeps behaving.
		if ( array_intersect( array( 'mega-menu', 'flavor-mega', 'mega' ), $forced ) ) {
			return true;
		}

		$menu  = is_object( $args ) && isset( $args->menu ) ? $args->menu : 0;
		$count = self::children_map( $menu )[ (int) $item->ID ] ?? 0;

		return $count >= self::threshold();
	}

	/**
	 * Add classes to the menu item.
	 *
	 * @param array<int, string> $classes Classes.
	 * @param mixed              $item    Menu item.
	 * @param mixed              $args    Args.
	 * @param int                $depth   Depth.
	 * @return array<int, string>
	 */
	public static function classes( $classes, $item, $args, $depth = 0 ): array {
		$classes = is_array( $classes ) ? $classes : array();

		if ( ! self::enabled() || ! self::in_location( $args ) || 0 !== (int) $depth ) {
			return $classes;
		}

		if ( ! self::is_mega( $item, $args ) ) {
			return $classes;
		}

		$classes[] = 'flavor-mega';
		$classes[] = 'flavor-mega--cols-' . self::columns();

		return array_values( array_unique( $classes ) );
	}

	/**
	 * ARIA for branches, so the panel is reachable without a mouse.
	 *
	 * @param array<string, string> $atts  Link attributes.
	 * @param mixed                 $item  Menu item.
	 * @param mixed                 $args  Args.
	 * @param int                   $depth Depth.
	 * @return array<string, string>
	 */
	public static function link_attributes( $atts, $item, $args, $depth = 0 ): array {
		$atts = is_array( $atts ) ? $atts : array();

		if ( ! self::enabled() || ! self::in_location( $args ) || ! is_object( $item ) ) {
			return $atts;
		}

		$menu  = is_object( $args ) && isset( $args->menu ) ? $args->menu : 0;
		$count = self::children_map( $menu )[ (int) $item->ID ] ?? 0;

		if ( $count > 0 ) {
			$atts['aria-haspopup']  = 'true';
			$atts['aria-expanded']  = 'false';
			$atts['data-flavor-nav'] = 'branch';
		}

		unset( $depth );

		return $atts;
	}

	/**
	 * Menu descriptions become the panel's muted second line. WordPress does
	 * not print descriptions in list menus, which is why paid themes add a
	 * custom walker for it.
	 *
	 * @param string $output Item markup.
	 * @param mixed  $item   Menu item.
	 * @param int    $depth  Depth.
	 * @param mixed  $args   Args.
	 * @return string
	 */
	public static function description( $output, $item, $depth = 0, $args = null ): string {
		if ( ! self::enabled() || ! self::in_location( $args ) || ! is_object( $item ) ) {
			return (string) $output;
		}

		$description = trim( (string) ( $item->description ?? '' ) );

		if ( '' === $description ) {
			return (string) $output;
		}

		$output = (string) $output;
		$close  = strrpos( $output, '</a>' );

		if ( false === $close ) {
			return $output;
		}

		$markup = '<span class="flavor-mega__desc">' . esc_html( $description ) . '</span>';

		unset( $depth );

		return substr( $output, 0, $close ) . $markup . substr( $output, $close );
	}

	/**
	 * Is this menu being rendered in one of the allowed locations?
	 *
	 * @param mixed $args Menu args.
	 * @return bool
	 */
	public static function in_location( $args ): bool {
		if ( ! is_object( $args ) || empty( $args->theme_location ) ) {
			return false;
		}

		return in_array( (string) $args->theme_location, self::locations(), true );
	}
}
