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

		// Bail if no options have been set
		if ( empty( $this->ranMID ) ) return;

		// Output the conversion script
		add_action( 'woocommerce_thankyou', array( $this, 'output_script' ), 10, 1 );
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
	 * @param [type] $order_id
	 * @return void
	 */
	public function output_script( $order_id ) {

		$ranMID = $this->ranMID;
		$discountType = $this->discountType;
		$taxRate = $this->taxRate;
		$removeTaxFromDiscount = $this->removeTaxFromDiscount;
		
		$order = wc_get_order( $order_id );

		$currency = $order->get_currency(); 
		$customerStatus = $order->get_user() ? 'Existing':'New'; 
		$customerID = $order->get_customer_id(); 
		$discountCode = implode('/',$order->get_coupon_codes());
		$discountAmount = $order->get_discount_total();  
		$taxAmount = $order->get_total_tax();
	
		// This is how to grab line items from the order 
		$line_items = $order->get_items();

		echo "
		<!-- START of Rakuten Marketing Conversion Tag -->
		<script type=\"text/javascript\">
		var rm_trans = {
			affiliateConfig: {ranMID: '$ranMID', discountType: '$discountType', taxRate: $taxRate, removeTaxFromDiscount: $removeTaxFromDiscount},
			
			
			orderid :'$order_id',
			currency: '$currency',
			customerStatus: '$customerStatus',
			conversionType: 'Sale',
			customerID: '$customerID',
			discountCode: '$discountCode',
			discountAmount: $discountAmount,
			taxAmount: $taxAmount,
		";

		// Retrieve and format the line items
		echo 'lineitems : [';
		$data = array();
		foreach ( $line_items as $item ) {
			// Check to make sure the line item is a product
			if ( is_callable( array( $item, 'get_product' ) ) ) {
				$product = $item->get_product();
			} else {
				continue;
			}
			
			$qty = $item['qty']; // This is the qty purchased
			$unitPrice = $order->get_item_subtotal( $item, true, true ); // The unit price is the line item subtotal
			$unitPriceLessTax = $order->get_item_subtotal( $item ); // Price without tax
			$sku = empty($product->get_sku()) ? $product->get_id() : $product->get_sku(); // This is the products SKU
			$productName = addslashes($product->get_name()); // Add slashes for the product name
			// Set up the data for each line item
			$data[] = "
			{
				quantity : $qty,
				unitPrice : $unitPrice,
				unitPriceLessTax: $unitPriceLessTax,
				SKU: '$sku',
				productName: '$productName'
			}
			";
		}
		// Output the data as an array
		echo implode(',',$data);
		echo ']
		};';

		echo '/*Do not edit any information beneath this line*/
		if(!window.DataLayer){window.DataLayer={Sale:{Basket:rm_trans}}}else{DataLayer.Sale=DataLayer.Sale||{Basket:rm_trans};DataLayer.Sale.Basket=DataLayer.Sale.Basket||rm_trans}DataLayer.Sale.Basket.Ready = true; function __readRMCookie(e){for(var a=e+"=",r=document.cookie.split(";"),t=0;t<r.length;t++){for(var n=r[t];" "==n.charAt(0);)n=n.substring(1,n.length);if(0==n.indexOf(a))return n.substring(a.length,n.length)}return""}function __readRMCookiev2(e,a){for(var r=__readRMCookie(a=a||"rmStore");r!==decodeURIComponent(r);)r=decodeURIComponent(r);for(var t=r.split("|"),n=0;n<t.length;n++){var i=t[n].split(":")[0],o=t[n].split(":")[1];if(i===e)return o}return""}function __readParam(e,a,r,t){var n=e||"",i=a||"",o=r||"",s=t||{},d=__readRMCookiev2(n),u=s[i],m=(d=s.ignoreCookie||!1?0:d)||u||o;return m=("string"!=typeof m||"false"!==m.toLowerCase())&&m}function sRAN(){var e=DataLayer&&DataLayer.Sale&&DataLayer.Sale.Basket?DataLayer.Sale.Basket:{},a=e.affiliateConfig||{},r=__readParam("atm","tagType","pixel",a),t=__readParam("adr","discountType","order",a),n=__readParam("acs","includeStatus","false",a),i=__readParam("arto","removeOrderTax","false",a),o=__readParam("artp","removeTaxFromProducts","false",a),s=__readParam("artd","removeTaxFromDiscount","false",a),d=__readParam("atr","taxRate",0,a);d=Number(d);var u=__readParam("ald","land",!1,{})||(a.land&&!0===a.land?__readRMCookie("ranLandDateTime"):a.land)||!1,m=__readParam("atrv","tr",!1,{})||(a.tr&&!0===a.tr?__readRMCookie("ranSiteID"):a.tr)||!1,l=!1,c=__readParam("amid","ranMID","",a)||e.ranMID;if(!c)return!1;if(!(void 0===a.allowCommission||"false"!==a.allowCommission))return!1;var p=e.orderid||"OrderNumberNotAvailable",f="",y="",_="",v="",N=e.currency||"";N=N.toUpperCase();var h=e.taxAmount?Math.abs(Math.round(100*Number(e.taxAmount))):0,g=e.discountAmount?Math.abs(Math.round(100*Number(e.discountAmount))):0;if(s&&d)var C=(100+Number(d))/100,g=Math.round(g/C);var b="pixel"===r?"ep":"mop"===r?"eventnvppixel":"ep",S=e.customerStatus||"",D=document.location.protocol+"//track.linksynergy.com/"+b+"?",w="";null!=S&&""!=S&&(n&&"EXISTING"==S.toUpperCase()||n&&"RETURNING"==S.toUpperCase())&&(w="R_");for(var P=[],x=0,T=0;T<(e.lineitems?e.lineitems.length:0);T++){for(var R=!1,k=window.JSON?JSON.parse(JSON.stringify(e.lineitems[T])):e.lineitems[T],L=0;L<P.length;L++)P[L].SKU===k.SKU&&(R=!0,P[L].quantity=Number(P[L].quantity)+Number(k.quantity));R||P.push(k),x+=Number(k.quantity)*Number(k.unitPriceLessTax||k.unitPrice)*100}for(T=0;T<P.length;T++){var k=P[T],I=encodeURIComponent(k.SKU),M=k.unitPriceLessTax||k.unitPrice,U=k.quantity,A=encodeURIComponent(k.productName)||"",O=Math.round(Number(M)*Number(U)*100);!o||!d||k.unitPriceLessTax&&k.unitPriceLessTax!==k.unitPrice||(O/=C=(100+d)/100),"item"===t.toLowerCase()&&g&&(O-=g*O/x),f+=w+I+"|",y+=U+"|",_+=Math.round(O)+"|",v+=w+A+"|"}f=f.slice(0,-1),y=y.slice(0,-1),_=_.slice(0,-1),v=v.slice(0,-1),g&&"order"===t.toLowerCase()?(f+="|"+w+"DISCOUNT",v+="|"+w+"DISCOUNT",y+="|0",_+="|-"+g):g&&"item"===t.toLowerCase()&&(l=!0),i&&h&&(f+="|"+w+"ORDERTAX",y+="|0",_+="|-"+h,v+="|"+w+"ORDERTAX"),D+="mid="+c+"&ord="+p+"&skulist="+f+"&qlist="+y+"&amtlist="+_+"&cur="+N+"&namelist="+v+"&img=1&",u&&(D+="land="+u+"&"),m&&(D+="tr="+m+"&"),l&&(D+="discount="+g+"&"),"&"===D[D.length-1]&&(D=D.slice(0,-1));var E,B=document.createElement("img");B.setAttribute("src",D),B.setAttribute("height","1px"),B.setAttribute("width","1px"),(E=document.getElementsByTagName("script")[0]).parentNode.insertBefore(B,E)}function sDisplay(){var e=null,a=null,r=null,t=null,n=window.DataLayer&&window.DataLayer.Sale&&window.DataLayer.Sale.Basket?window.DataLayer.Sale.Basket:{},i=n.displayConfig||{},o=n.customerStatus||"",s=n.discountAmount?Math.abs(Number(n.discountAmount)):0,d=null,u=__readParam("dmid","rdMID","",i);if(!u)return!1;var m=__readParam("dtm","tagType","js",i),l="if"===(m="js"===m||"if"===m||"img"===m?m:"js")?"iframe":"img"===m?m:"script",c="//"+__readParam("ddn","domain","tags.rd.linksynergy.com",i)+"/"+m+"/"+u,p=__readParam("dis","includeStatus","false",i),f="";if(null!=o&&""!=o&&(p&&"EXISTING"==o.toUpperCase()||p&&"RETURNING"==o.toUpperCase())&&(f="R_"),!n.orderid||!n.conversionType)return!1;r=0,a=f+n.orderid,e="",t="conv",d=n.currency;for(var y=0;y<(n.lineitems?n.lineitems.length:0);y++)r+=Number(n.lineitems[y].unitPriceLessTax)*Number(n.lineitems[y].quantity)||Number(n.lineitems[y].unitPrice)*Number(n.lineitems[y].quantity),e+=encodeURIComponent(n.lineitems[y].SKU)+",";r=Math.round(100*(r-s))/100,(e=e.slice(0,-1))&&(c=c.indexOf("?")>-1?c+"&prodID="+e:c+"/?prodID="+e),a&&(c=c.indexOf("?")>-1?c+"&orderNumber="+a:c+"/?orderNumber="+a),r&&(c=c.indexOf("?")>-1?c+"&price="+r:c+"/?price="+r),d&&(c=c.indexOf("?")>-1?c+"&cur="+d:c+"/?cur="+d),t&&(c=c.indexOf("?")>-1?c+"&pt="+t:c+"/?pt="+t);var _=document.createElement(l);_.src=c,"script"===l?_.type="text/javascript":"iframe"===l&&_.setAttribute("style","display: none;"),document.getElementsByTagName("body")[0].appendChild(_)}function sSearch(){var e=window.DataLayer&&window.DataLayer.Sale&&window.DataLayer.Sale.Basket?window.DataLayer.Sale.Basket:{},a=e.searchConfig||{},r=__readParam("smid","rsMID","",a);if(!r)return!1;var t=function(){var t=e.discountAmount?Math.abs(Number(e.discountAmount)):0,n=__readParam("sct","conversionType","conv",a),i=0,o="";if(!e.orderid)return!1;i=0,o=e.orderid;for(var s=0;s<(e.lineitems?e.lineitems.length:0);s++)i+=Number(e.lineitems[s].unitPrice)*Number(e.lineitems[s].quantity);i=Math.round(100*(i-t))/100;window.DataLayer.Sale.Basket;var d=[];d[0]="id="+r,d[1]="type="+n,d[2]="val="+i,d[3]="orderId="+o,d[4]="promoCode="+e.discountCode||"",d[5]="valueCurrency="+e.currency||"USD",d[6]="GCID=",d[7]="kw=",d[8]="product=",k_trackevent(d,"113")},n=document.location.protocol.indexOf("s")>-1?"https://":"http://";n+="113.xg4ken.com/media/getpx.php?cid="+r;var i=document.createElement("script");i.type="text/javascript",i.src=n,i.onload=t,i.onreadystatechange=function(){"complete"!=this.readyState&&"loaded"!=this.readyState||t()},document.getElementsByTagName("head")[0].appendChild(i)}sRAN(),sDisplay(),sSearch();</script> 
		<!-- END of Rakuten Marketing Conversion Tag -->';
	}

}

$woocommerce_rakuten_tracking = new IP_Rakuten_Tracking();