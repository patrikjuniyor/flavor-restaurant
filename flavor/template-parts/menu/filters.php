<?php
/**
 * Dietary / allergen filter chips and sorting for the live menu grid.
 *
 * The data already exists on every dish (the importer stores dietary flags and
 * allergens); this exposes it. Chips are plain buttons, the panel announces its
 * result count, and nothing here decides price or availability.
 *
 * @package Flavor
 */

defined( 'ABSPATH' ) || exit;

if ( 'no' === get_theme_mod( 'flavor_menu_filters_enable', 'yes' ) ) {
	return;
}

$flavor_diets = array(
	'vegetarian'  => __( 'گیاهی', 'flavor' ),
	'vegan'       => __( 'وگان', 'flavor' ),
	'gluten_free' => __( 'بدون گلوتن', 'flavor' ),
	'spicy'       => __( 'تند', 'flavor' ),
	'dairy_free'  => __( 'بدون لبنیات', 'flavor' ),
);

$flavor_avoid = array(
	'gluten' => __( 'بدون گلوتن', 'flavor' ),
	'milk'   => __( 'بدون شیر', 'flavor' ),
	'eggs'   => __( 'بدون تخم‌مرغ', 'flavor' ),
	'nuts'   => __( 'بدون مغزها', 'flavor' ),
);

$flavor_sorts = array(
	'default'      => __( 'چیدمان پیشنهادی', 'flavor' ),
	'price'        => __( 'ارزان‌ترین', 'flavor' ),
	'price-desc'   => __( 'گران‌ترین', 'flavor' ),
	'calories'     => __( 'کم‌کالری‌ترین', 'flavor' ),
	'prep'         => __( 'سریع‌ترین آماده‌سازی', 'flavor' ),
);
?>
<section class="flavor-filters" data-flavor-filters aria-label="<?php esc_attr_e( 'فیلتر و مرتب‌سازی منو', 'flavor' ); ?>">
	<div class="flavor-filters__group" role="group" aria-label="<?php esc_attr_e( 'فیلتر رژیمی', 'flavor' ); ?>">
		<span class="flavor-filters__legend"><?php esc_html_e( 'رژیم:', 'flavor' ); ?></span>
		<?php foreach ( $flavor_diets as $flavor_key => $flavor_label ) : ?>
			<button type="button" class="flavor-filters__chip" data-flavor-filter="diet" data-flavor-value="<?php echo esc_attr( $flavor_key ); ?>" aria-pressed="false"><?php echo esc_html( $flavor_label ); ?></button>
		<?php endforeach; ?>
	</div>

	<div class="flavor-filters__group" role="group" aria-label="<?php esc_attr_e( 'پرهیز از حساسیت‌ها', 'flavor' ); ?>">
		<span class="flavor-filters__legend"><?php esc_html_e( 'پرهیز از:', 'flavor' ); ?></span>
		<?php foreach ( $flavor_avoid as $flavor_key => $flavor_label ) : ?>
			<button type="button" class="flavor-filters__chip" data-flavor-filter="avoid" data-flavor-value="<?php echo esc_attr( $flavor_key ); ?>" aria-pressed="false"><?php echo esc_html( $flavor_label ); ?></button>
		<?php endforeach; ?>
	</div>

	<label class="flavor-filters__sort">
		<?php \Flavor\UI::icon( 'sort', 16 ); ?>
		<span class="screen-reader-text"><?php esc_html_e( 'ترتیب نمایش غذاها', 'flavor' ); ?></span>
		<select data-flavor-sort>
			<?php foreach ( $flavor_sorts as $flavor_key => $flavor_label ) : ?>
				<option value="<?php echo esc_attr( $flavor_key ); ?>"><?php echo esc_html( $flavor_label ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>

	<span class="flavor-filters__count" data-flavor-filter-count role="status" aria-live="polite"></span>
</section>
