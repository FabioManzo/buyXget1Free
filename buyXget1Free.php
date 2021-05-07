<?php
/**
 * Plugin Name: buyXget1Free
 * Description: Buy X products and get 1 Free. X can be any number you want and the free item is the less expensive. It requires Woocommerce to work
 * Version: 1.0.0
 * Author: Fabio Manzo
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * License: GPL2
 * Requires at least: 5.5
 *
 */



// get the total number of items in cart
function bxg1f_calculate_coupon($cart) {
	$tot = 0;
	$prices = []; // it holds all the prices, repeated for avery item in the cart. If the same items is present twice in the cart, its price gets registeredi in this array twice
	$coupon = 0;

	// Quanti articoli ci sono nel carrello? Ogni 3 prodotti, il più economico è in omaggio, cioè va generato un ammontare fixed che corrisponde al prezzo di questo
	foreach ($cart as $key => $cart_item) {
		$tot += $cart_item['quantity'];

		$product = $cart_item['data'];
		//$product_id = $cart_item['product_id'];
		
		$price = $product->get_price();
		for ($i=0; $i < $cart_item['quantity']; $i++) { 
			
			$prices[] = $price;
		}
		
	}

	// order totals from less expensive 
	sort($prices);

	$free_items_number = bxg1f_get_items_number_to_give_free($tot);

	for ($i=0; $i < $free_items_number; $i++) { 
		$coupon += $prices[$i];
	}

	return $coupon;
}


/* It calculates the number of items to give for free */
function bxg1f_get_items_number_to_give_free($total_items) {


    $X_number = get_option('bxg1f_option_name');

    if (isset($X_number['X_number']) && !empty($X_number['X_number'])) {
        $X_number = $X_number['X_number'];
    } else {
        $X_number = 3;
    }

	return floor($total_items /  $X_number);

}

function get_session_ID_from_cookies() {
	foreach ($_COOKIE as $key => $cookie_val) {
		if (strpos($key, 'wp_woocommerce_session_') !== false) {
			return $cookie_val;
		}
	}
}


// define the woocommerce_update_cart_action_cart_updated callback 
function filter_woocommerce_update_cart_action_cart_updated( $cart_updated ) { 

    $X_number = get_option('bxg1f_option_name');

    if (isset($X_number['X_number']) && !empty($X_number['X_number'])) {
        // setta lo sconto solo se è stato inserito un valore per X

       //$coupon_code = wp_get_session_token();
        $coupon_code = get_session_ID_from_cookies();
        
        $cart = WC()->cart->get_cart();
        
        
        $amount = bxg1f_calculate_coupon($cart); // Amount --> calcolalo in base a questa regola: per ogni 3 prodotti, il terzo più economico è gratis.
        $discount_type = 'fixed_cart'; // Type: fixed_cart, percent, fixed_product, percent_product

        
        // controlla se è stato già creato un coupon in questa sessione
        $coupon_esiste = get_page_by_title($coupon_code, OBJECT, 'shop_coupon');


        if (!empty($coupon_esiste)) {
            //echo "coupon esiste.<br>";
            // esiste. Prendine l'ID
            $coupon_id = $coupon_esiste->ID;
            $coupon_amount = get_post_meta( $coupon_id, 'coupon_amount',true);

            // Corrisponde al totale calcolato ($amount) ?
            // si, non fare niente
            if ($coupon_amount != $amount) {
                // non corrisponde, modifica l'ammontare con il nuovo calcolo
                update_post_meta( $coupon_id, 'coupon_amount', $amount );

            }

            // è associato il coupon al carrrello? 
            if (empty(WC()->cart->applied_coupons)) {
                // non è associato: Asocia di nuovo il coupon al carrello
                WC()->cart->apply_coupon( $coupon_code );
            } 
            

        } else {
            //echo "coupon non esiste.<br>";
            // non esiste. Crealo

            $args_nuovo = array(
                'post_title' => $coupon_code,
                'post_content' => '',
                'post_status' => 'publish',
                'post_type'     => 'shop_coupon',
            );

            $coupon_id = wp_insert_post( $args_nuovo );

            update_post_meta( $coupon_id, 'discount_type', $discount_type );
            update_post_meta( $coupon_id, 'coupon_amount', $amount );
            update_post_meta( $coupon_id, 'individual_use', 'yes' );
            update_post_meta( $coupon_id, 'usage_limit', '1' );
            update_post_meta( $coupon_id, 'usage_limit_per_user', '1' );
            update_post_meta( $coupon_id, 'expiry_date', strtotime('+1 day', time()) ); // tra 24 ore
            update_post_meta( $coupon_id, 'apply_before_tax', 'yes' );
            update_post_meta( $coupon_id, 'free_shipping', 'no' );

            WC()->cart->apply_coupon( $coupon_code );

        }
    } 


    return $cart_updated; 
}; 
         
// add the filter 
add_filter( 'woocommerce_add_to_cart_fragments', 'filter_woocommerce_update_cart_action_cart_updated', 10, 1 ); 


class bxg1fSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'page_init' ) );
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        // This page will be under "Settings"
        add_options_page(
            'Settings Admin', 
            'buyXget1Free', 
            'manage_options', 
            'bxg1f-setting-admin', 
            array( $this, 'create_admin_page' )
        );
    }


    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option( 'bxg1f_option_name' );
        ?>
        <div class="wrap">
            <h1>buyXget1Free Woocommerce Settings</h1>
            <form method="post" action="options.php">
            <?php
                // This prints out all hidden setting fields
                settings_fields( 'bxg1f_option_group' );
                do_settings_sections( 'bxg1f-setting-admin' );
                submit_button();
            ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {        
        register_setting(
            'bxg1f_option_group', // Option group
            'bxg1f_option_name', // Option name
            array( $this, 'sanitize' ) // Sanitize
        );

        add_settings_section(
            'setting_section_id', // ID
            '', // Title
            array( $this, 'print_section_info' ), // Callback
            'bxg1f-setting-admin' // Page
        );  

        add_settings_field(
            'X_number', // ID
            'X (indicate how many items a customer has to buy to get 1 items free). If not set or set to 0, it will not work.', // Title 
            array( $this, 'x_callback' ), // Callback
            'bxg1f-setting-admin', // Page
            'setting_section_id' // Section           
        );      

    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize( $input )
    {
        $new_input = array();
        if( isset( $input['X_number'] ) )
            $new_input['X_number'] = absint( $input['X_number'] );


        return $new_input;
    }

    /** 
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'If you want to create an offer where any 3 items bought 1 is free, insert <strong>X = 3</strong>. With this value (3), this will happen: 
        	<ul>
        		<li>if in the cart there are 3 items, the third less expensive is free.</li> 
        		<li>if in the cart there are 7 items, the 2 less expensive will be free.</li>
                <li>if in the cart there are 9 items, the 3 less expensive will be free.</li>
        	</ul>
        	<div style="color:red;"> The way it works is generating a coupon with the session ID of the cart. </div>
        	';
    }

    /** 
     * Get the settings option array and print one of its values
     */
    public function x_callback()
    {
        printf(
            '<input type="text" id="X_number" name="bxg1f_option_name[X_number]" value="%s" />',
            isset( $this->options['X_number'] ) ? esc_attr( $this->options['X_number']) : ''
        );
    }


}

if( is_admin() )
    $bxg1f_settings_page = new bxg1fSettingsPage();

