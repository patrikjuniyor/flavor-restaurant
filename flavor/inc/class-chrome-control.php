<?php
/**
 * Builder control: order and visibility for one chrome region.
 *
 * Loaded inside the Customizer only. Drag-and-drop is progressive: every row
 * also carries move buttons, so the list stays fully operable with the keyboard
 * and on touch devices, and a reset returns the region to the skin defaults.
 *
 * @package Flavor
 */

namespace Flavor;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chrome_Order_Control
 */
class Chrome_Order_Control extends \WP_Customize_Control {

	/**
	 * Control type.
	 *
	 * @var string
	 */
	public $type = 'flavor-chrome-order';

	/**
	 * Rows to list. Each item: key, label, hint, setting, on, default.
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $flavor_items = array();

	/**
	 * Label of the reset button; empty hides it.
	 *
	 * @var string
	 */
	public $flavor_reset_label = '';

	/**
	 * Send the schema to the script.
	 *
	 * `params` is the free-form bag core forwards to `data.params`, which is
	 * how a custom control gets its own config without touching core filters.
	 */
	public function to_json(): void {
		parent::to_json();

		$order    = array();
		$defaults = array();

		foreach ( (array) $this->flavor_items as $item ) {
			$order[]            = (string) $item['key'];
			$defaults[ (string) $item['key'] ] = (bool) $item['default'];
		}

		$this->json['flavorItems']   = (array) $this->flavor_items;
		$this->json['flavorDefaultOrder'] = $order;
		$this->json['flavorDefaults'] = $defaults;
		$this->json['flavorReset']   = (string) $this->flavor_reset_label;
	}

	/**
	 * Render the list.
	 *
	 * Core prints this markup for the pane, and the script re-binds it to the
	 * settings, so the control is never an empty box.
	 */
	public function render_content(): void {
		$order    = array();
		$defaults = array();

		foreach ( (array) $this->flavor_items as $item ) {
			$order[]                            = (string) $item['key'];
			$defaults[ (string) $item['key'] ] = (bool) $item['default'];
		}
		?>
		<span class="customize-control-title"><?php echo esc_html( $this->label ); ?></span>
		<?php if ( $this->description ) : ?>
			<span class="description customize-control-description"><?php echo esc_html( $this->description ); ?></span>
		<?php endif; ?>

		<ul
			class="flavor-builder-list"
			role="list"
			data-flavor-sortable
			data-flavor-order="<?php echo esc_attr( $this->id ); ?>"
			data-flavor-default-order="<?php echo esc_attr( implode( ',', $order ) ); ?>"
			data-flavor-defaults="<?php echo esc_attr( wp_json_encode( $defaults ) ); ?>"
		>
			<?php foreach ( (array) $this->flavor_items as $item ) : ?>
				<?php $item = (array) $item; ?>
				<li class="flavor-builder-item<?php echo $item['on'] ? ' is-on' : ''; ?>" data-flavor-key="<?php echo esc_attr( (string) $item['key'] ); ?>">
					<span class="flavor-builder-item__handle" aria-hidden="true">&#x2807;</span>

					<label class="flavor-builder-item__label">
						<input
							type="checkbox"
							class="flavor-builder-item__toggle"
							data-flavor-setting="<?php echo esc_attr( (string) $item['setting'] ); ?>"
							<?php checked( ! empty( $item['on'] ) ); ?>
						/>
						<span><?php echo esc_html( (string) $item['label'] ); ?></span>
					</label>

					<span class="flavor-builder-item__buttons">
						<button type="button" class="button-link" data-flavor-move="-1"
							aria-label="<?php /* translators: %s: element label. */ echo esc_attr( sprintf( __( 'انتقال «%s» به بالا', 'flavor' ), (string) $item['label'] ) ); ?>">&#x25B2;</button>
						<button type="button" class="button-link" data-flavor-move="1"
							aria-label="<?php /* translators: %s: element label. */ echo esc_attr( sprintf( __( 'انتقال «%s» به پایین', 'flavor' ), (string) $item['label'] ) ); ?>">&#x25BC;</button>
					</span>

					<?php if ( ! empty( $item['hint'] ) ) : ?>
						<span class="flavor-builder-item__hint"><?php echo esc_html( (string) $item['hint'] ); ?></span>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>
		</ul>

		<?php if ( '' !== (string) $this->flavor_reset_label ) : ?>
			<p>
				<button type="button" class="button button-small" data-flavor-reset>
					<?php echo esc_html( (string) $this->flavor_reset_label ); ?>
				</button>
				<span class="description"><?php esc_html_e( 'چینش و تیک‌ها را به پیش‌فرض پوسته برمی‌گرداند. تا «انتشار» برای هیچ‌کس اعمال نمی‌شود.', 'flavor' ); ?></span>
			</p>
		<?php endif; ?>
		<?php
	}
}
