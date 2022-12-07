<?php
/**
 * Plugin Name: Rakuten Tracking for WooCommerce
 * Plugin URI : https://www.inverseparadox.com
 * Description: Inserts the Rakuten conversion tracking script to the WooCommerce thankyou page.
 * Version:     1.0.0
 * Author:      Inverse Paradox
 * Author URI:  https://inverseparadox.com
 * Text Domain: woocommerce-rakuten-tracking
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

class IP_Rakuten_Tracking {

	public $plugin_name = 'ip-rakuten-tracking';

	protected $ranMID;
	protected $discountType;
	protected $taxRate;
	protected $removeTaxFromDiscount;
	protected $scriptID;

	public function __construct() {
		// Add settings
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
        add_action( 'woocommerce_settings_tabs_rakuten_tracking', array( $this, 'settings_tab' ) );
        add_action( 'woocommerce_update_options_rakuten_tracking', array( $this, 'update_settings' ) );

		// Get the plugin options
		$this->ranMID = get_option( 'woocommerce_rakuten_ranmid', '' );
		$this->discountType = get_option( 'woocommerce_rakuten_discounttype', 'item' );
		$this->taxRate = get_option( 'woocommerce_rakuten_taxrate', '20' );
		$this->removeTaxFromDiscount = get_option( 'woocommerce_rakuten_removetaxfromdiscount', 'false' );
		$this->scriptID = get_option( 'woocommerce_rakuten_scriptid', '' );

		// Bail if no options have been set
		if ( empty( $this->ranMID ) || empty( $this->scriptID ) ) return;

		// Enqueue the global JS
		wp_enqueue_script( 'rakuten-global-js-spi', plugin_dir_url( __FILE__ ) . 'rakuten-global-js-spi.js', [], '1.0.0', true );
		wp_localize_script( 'rakuten-global-js-spi', 'ipRakuten', array(
			'ranMID' => $this->ranMID,
			'discountType' => $this->discountType,
			'taxRate' => $this->taxRate,
			'removeTaxFromDiscount' => $this->removeTaxFromDiscount,
			'scriptID' => $this->scriptID,
		) );

		// Output the conversion script
		add_action( 'woocommerce_thankyou', array( $this, 'datalayer' ), 10, 1 );
	}

	/**
	 * Adds the settings tab for the plugin to the WooCommerce settings page
	 *
	 * @param array $settings_tabs Settings tabs currently registered
	 * @return array Filtered settings tabs
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['rakuten_tracking'] = __( 'Rakuten Tracking', 'woocommerce-rakuten-tracking' );
        return $settings_tabs;
	}

	/**
	 * Output the fields for the settings tab
	 *
	 * @return void
	 */
	public function settings_tab() {
        woocommerce_admin_fields( $this->get_settings() );
    }

	/**
	 * Update the settings
	 *
	 * @return void
	 */
	public function update_settings() {
        woocommerce_update_options( $this->get_settings() );
    }

	/**
	 * Retrieve the settings for the plugin
	 *
	 * @return void
	 */
	public function get_settings() {

        $settings = array(
            'section_title' => array(
                'name'     => __( 'Rakuten Tracking Settings', 'woocommerce-rakuten-tracking' ),
				'type'     => 'title',
                'desc'     => 'Enter the settings for your Rakuten integration here.',
                'id'       => 'WooCommerce_Rakuten_Tracking_Settings_section_title'
            ),
            'ranMID' => array(
                'name' => __( 'ranMID', 'woocommerce-rakuten-tracking' ),
                'type' => 'text',
				'desc_tip' => __( 'Enter the ranMID code from your Rakuten configuration.', 'woocommerce-rakuten-tracking' ),
                'id'   => 'woocommerce_rakuten_ranmid'
            ), 
			'discountType' => array(
                'name' => __( 'discountType', 'woocommerce-rakuten-tracking' ),
                'type' => 'text',
				'desc_tip' => __( 'Enter the discount type from your Rakuten configuration.', 'woocommerce-rakuten-tracking' ),
                'id'   => 'woocommerce_rakuten_discounttype',
				'default' => 'item',
            ),
			'taxRate' => array(
                'name' => __( 'taxRate', 'woocommerce-rakuten-tracking' ),
                'type' => 'text',
				'desc_tip' => __( 'Enter the tax rate from your Rakuten configuration.', 'woocommerce-rakuten-tracking' ),
                'id'   => 'woocommerce_rakuten_taxrate',
				'default' => '20',
            ),
			'removeTaxFromDiscount' => array(
                'name' => __( 'removeTaxFromDiscount', 'woocommerce-rakuten-tracking' ),
                'type' => 'text',
				'desc_tip' => __( 'Enter true or false to remove tax from discounts according to your Rakuten configuration.', 'woocommerce-rakuten-tracking' ),
                'id'   => 'woocommerce_rakuten_removetaxfromdiscount',
				'default' => 'false',
            ),
			'scriptID' => array(
				'name' => __( 'scriptID', 'woocommerce-rakuten-tracking' ),
				'type' => 'text',
				'desc_tip' => __( 'Enter the script ID from Rakuten, eg the numeric portion of //tag.rmp.rakuten.com/115827.ct.js would be 115827.' ),
				'id' => 'woocommerce_rakuten_scriptid',
			),
            'section_end' => array(
                 'type' => 'sectionend',
                 'id' => 'WooCommerce_Rakuten_Tracking_Settings_section_end'
            )
        );

        return apply_filters( 'woocommerce_rakuten_tracking_settings', $settings );
    }

	/**
	 * Output the conversion tracking script
	 *
	 * @param int  $order_id
	 * @return void
	 */
	public function datalayer( $order_id ) {
		// Set up access to variables
		$taxRate = $this->taxRate;
		$removeTaxFromDiscount = $this->removeTaxFromDiscount;

		// Check WC version
		$woov3_7 = version_compare( WC()->version, '3.7', '>=' );

		// Get the order
		$order = wc_get_order( $order_id );

		// Display console error if order does not exist
		if ( ! $order ) { 
			?>
			<script type="text/javascript">
        		console.warn('Rakuten Advertising Conversion Tag Error: Order does not exist. Order not tracked.');
        	</script>
			<?php
			return false;
		}

		// Bail if the order is failed
		if ( $order->get_status() == 'failed' ) return false;

		$order_number = $order->get_order_number();
		$order_total = $order->get_total();
		$order_subtotal = $order->get_subtotal();
		$order_user_id = $order->get_user_id();
		$order_cur = $order->get_currency();
		$order_discount = $order->get_discount_total();
		$order_discount_tax = $order->get_discount_tax();
		$order_coupons = $woov3_7 ? implode( ',', $order->get_coupon_codes() ) : implode( ',', $order->get_used_coupons() );
		$order_tax = $order->get_total_tax();

		$order_count = wc_get_customer_order_count( $order_user_id );
		$order_user_status = $order_count ? 'EXISTING' : 'NEW';

		$line_items = $order->get_items();

		$items_array = [];

		foreach ( $line_items as $item ) {
			// Check to make sure the line item is a product
			if ( is_callable( array( $item, 'get_product' ) ) ) {
				$product = $item->get_product();
			} else {
				continue;
			}
			
			$sku = $product->get_sku(); // This is the product SKU
            $name = $product->get_name(); // This is the product name
            $qty = $item->get_quantity(); // This is the qty purchased
            $price = $product->get_price(); // This is the product price
            $item_total = $item->get_subtotal();
            $item_total_tax = $item->get_subtotal_tax();
            $item_total_disc = $item->get_total();
            $item_total_tax_disc = $item->get_total_tax();

			$items_array[] = array( 
				'quantity' => $qty,
				'unitPrice' => ( $item_total + $item_total_tax ) / $qty,
				'unitPriceLessTax' => $item_total / $qty,
				'SKU' => $sku,
				'productName' => $name,
			);
		}

		$rm_trans = array(
			'affiliateConfig' => array(
				'ranMID' => $this->ranMID,
				'discountType' => $this->discountType,
				'includeStatus' => 'false',
			),
			'orderid' => $order_number ?? $order_id,
			'currency' => $order_cur,
			'customerStatus' => $order_user_status,
			'conversionType' => 'Sale',
			'customerID' => $order_user_id,
			'discountCode' => $order_coupons,
			'taxAmount' => $order_tax,
			'lineitems' => $items_array
		);

		?>
		
		<!-- START Rakuten Advertising Conversion Datalayer -->
		<script type="text/javascript">
			/* <![CDATA[ */
			var rm_trans = <?php echo json_encode( $rm_trans ); ?>;
			/* ]]> */
		</script>
		<script src="<?php echo plugin_dir_url( __FILE__ ) . 'rakuten-datalayer.js'; ?>">
		<!-- END Rakuten Advertising Conversion Datalayer -->
		<?php
	}

}

$woocommerce_rakuten_tracking = new IP_Rakuten_Tracking();