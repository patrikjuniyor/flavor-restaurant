<?php
/**
 * Admin experience assets for the theme-owned wp-admin screens.
 *
 * The theme ships five screens of its own — the setup wizard, the launch
 * centre, the demo importer, the changelog and the settings transfer page —
 * and before this class they rendered as bare WordPress markup with a couple
 * of inline `<style>` islands. This loads one stylesheet on exactly those
 * screens so they share the identity in `admin-ui.css`, and nothing at all
 * anywhere else in wp-admin.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Admin_UI
 */
class Admin_UI {

        /**
         * Screen id (without the `appearance_page_` prefix) => hero image slug.
         *
         * @var array<string, string>
         */
        private const SCREENS = array(
                'flavor-setup'     => 'hero-kitchen',
                'flavor-launch'    => 'hero-dining',
                'flavor-demos'     => 'hero-bistro',
                'flavor-changelog' => 'hero-coffee',
                'flavor-transfer'  => 'hero-pizza',
        );

        /**
         * Hooks.
         */
        public static function init(): void {
                add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
        }

        /**
         * Style the screen, when it is one of ours.
         *
         * @param string $hook Current screen hook suffix.
         */
        public static function assets( string $hook ): void {
                if ( ! self::is_screen( $hook ) ) {
                        return;
                }

                $css = '/assets/css/admin-ui.css';
                if ( ! is_readable( FLAVOR_DIR . $css ) ) {
                        return;
                }

                wp_enqueue_style(
                        'flavor-admin-ui',
                        FLAVOR_URI . $css,
                        array(),
                        (string) filemtime( FLAVOR_DIR . $css )
                );
        }

        /**
         * Whether the current screen is one the theme owns.
         *
         * @param string $hook Current screen hook suffix.
         * @return bool
         */
        public static function is_screen( string $hook ): bool {
                if ( '' === $hook ) {
                        return false;
                }

                foreach ( array_keys( self::SCREENS ) as $slug ) {
                        if ( $hook === 'appearance_page_' . $slug ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * URL of the decorative hero photograph for a screen.
         *
         * Returns an empty string when the image is absent, so callers can fall
         * back to the gradient on its own and the page still looks deliberate.
         *
         * @param string $slug Image slug from self::SCREENS.
         * @return string
         */
        public static function hero( string $slug ): string {
                $slug = sanitize_key( $slug );
                if ( ! in_array( $slug, self::SCREENS, true ) ) {
                        return '';
                }

                $rel = '/assets/img/admin/' . $slug . '.jpg';
                if ( ! is_readable( FLAVOR_DIR . $rel ) ) {
                        return '';
                }

                return FLAVOR_URI . $rel;
        }

        /**
         * Inline custom property that paints the hero background, if any.
         *
         * Returned as a whole style attribute so a caller cannot accidentally
         * emit a half-built one; the value goes through esc_url() first.
         *
         * @param string $slug Image slug.
         * @return string
         */
        public static function hero_style( string $slug ): string {
                $url = self::hero( $slug );
                if ( '' === $url ) {
                        return '';
                }

                return sprintf( 'background-image: url(%s);', esc_url( $url ) );
        }
}
