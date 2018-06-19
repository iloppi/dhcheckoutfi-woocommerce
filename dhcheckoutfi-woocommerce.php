<?php
/*
	Plugin Name: Checkout-verkkomaksutoiminto
	Plugin URI: https://www.laskuhari.fi/checkout-fi-woocommerce-verkkomaksu
	Description: Lisää Checkoutin verkkomaksutoiminnot WooCommercen kassasivulle
	Version: 0.9.6
	Author: Datahari Solutions
	Author URI: https://www.datahari.fi
	License:  GPL-2.0+
 	License URI:  http://www.gnu.org/licenses/gpl-2.0.txt

 	Modified from Paga Woocommerce E-Pay plugin
 	(https://wordpress.org/plugins/paga-woocommerce/)
 	Original author: Pagatech Limited
 	Modification date: 2016/05/10
*/
if ( ! defined( 'ABSPATH' ) )
	exit;

add_action('plugins_loaded', 'tbz_wc_dhcheckoutfi_init', 0);

function tbz_wc_dhcheckoutfi_init() {

	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	/**
 	 * Gateway class
 	 */
	class WC_Tbz_DHCheckoutFi_Gateway extends WC_Payment_Gateway {

		public function __construct(){

			$this->order_button_text  = __( 'Siirry maksamaan', 'woocommerce-gateway-dhcheckoutfi' );
			$this->id 					= 'tbz_dhcheckoutfi_gateway';
			$this->has_fields 			= true;
        	$this->method_title     	= 'Checkout.fi';
        	$this->method_description  	= 'Checkout Finland Oy:n maksunvälityspalvelu';

			// Load the form fields.
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();

			// Define user set variables
			$this->title              = $this->get_option( 'title' );
			$this->description        = $this->get_option( 'description' );
			$this->instructions       = $this->get_option( 'instructions' );
			$this->testmode  		  = $this->get_option( 'testmode' );
			$this->show_banner  	  = $this->get_option( 'show_banner' );
			$this->banner_url   	  = $this->get_option( 'banner_url' );
			$this->show_logo  		  = $this->get_option( 'show_logo' );

			if($this->testmode == 'yes') {
				$this->merchant_id = 375917;
				$this->merchant_secret = "SAIPPUAKAUPPIAS";
			} else {
				$this->merchant_id        = trim(rtrim(html_entity_decode(htmlspecialchars_decode($this->get_option( 'merchant_id' )))));
				$this->merchant_secret    = trim(rtrim(html_entity_decode(htmlspecialchars_decode($this->get_option( 'merchant_secret' )))));
			}

			if($this->show_logo == 'yes') {
    			$this->icon 				= apply_filters('woocommerce_dhcheckoutfi_icon', plugins_url( 'assets/checkout-logo.png' , __FILE__ ) );
			}

			//Actions
			add_action('woocommerce_receipt_tbz_dhcheckoutfi_gateway', array($this, 'receipt_page'));
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// Payment listener/API hook
			add_action( 'woocommerce_api_wc_tbz_dhcheckoutfi_gateway', array( $this, 'check_dhcheckoutfi_response' ) );
		}

        /**
         * Admin Panel Options
         **/
        public function admin_options(){
        	echo '<p><a href="https://www.laskuhari.fi/?ref=checkout_woocommerce" target="_blank" id="dh-checkout-ad-link"><img src="https://www.laskuhari.fi/dh-checkout-ad.png" id="dh-checkout-ad-img" /></a></p>
        	<script src="https://www.laskuhari.fi/dh-checkout-ad.js" type="text/javascript"></script>';
            echo '<h3>Checkout.fi-integraation asetukset</h3>';
            echo '<p>Tässä voit määrittää asetukset Checkout.fi-integraatiota varten.</p>';
            echo '<table class="form-table">';
            $this->generate_settings_html();
            echo '</table>';
        }


	    /**
	     * Initialise Gateway Settings Form Fields
	    **/

		function init_form_fields(){

			$this->form_fields = array(
				'enabled' => array(
					'title'       => __( 'Käytössä', 'woocommerce' ),
					'label'       => __( 'Ota käyttöön Checkout.fi-maksutapa', 'woocommerce' ),
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => __( 'Otsikko', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Tämä näkyy maksutavan nimenä asiakkaalle', 'woocommerce' ),
					'default'     => __( 'Verkkomaksu', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Kuvaus', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Kuvaus, joka näytetään maksutavan yhteydessä', 'woocommerce' ),
					'default'     => __( 'Maksa tilauksesi verkkopankissa tai luottokortilla', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'show_banner' => array(
					'title'       		=> 'Banneri',
					'type'        		=> 'checkbox',
					'label'       		=> 'Näytä maksutapabanneri',
					'default'     		=> 'no',
					'description' 		=> 'Näytä maksutapabanneri maksutavan valinnan yhteydessä',
				),
				'banner_url' => array(
					'title'       => __( 'Bannerin URL', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Maksutapavalinnan yhteydessä näytettävän bannerin URL', 'woocommerce' ),
					'default'     => __( '', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'show_logo' => array(
					'title'       		=> 'Logo',
					'type'        		=> 'checkbox',
					'label'       		=> 'Näytä Checkout-logo',
					'default'     		=> 'yes',
					'description' 		=> 'Näytä Checkoutin logo maksutavan valinnan yhteydessä',
				),
				'merchant_id' => array(
					'title'       => __( 'Kauppiastunnus', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Kauppiastunnus Checkout.fi-palveluun', 'woocommerce' ),
					'default'     => __( '', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'merchant_secret' => array(
					'title'       => __( 'Turva-avain', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Turva-avain Checkout.fi-palveluun', 'woocommerce' ),
					'default'     => __( '', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Ohjeet', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'Ohjeet, jotka näkyvät maksutavan valintasivulla', 'woocommerce' ),
					'default'     => __( 'Ole hyvä ja valitse haluamasi maksutapa', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'testmode' => array(
					'title'       		=> 'Testaustila',
					'type'        		=> 'checkbox',
					'label'       		=> 'Testaustila päällä',
					'default'     		=> 'no',
					'description' 		=> 'Testaustilassa voit testata verkkomaksua ilman kauppiastunnuksia. Otathan testaustilan pois käytöstä tuotantoympäristössä.',
				)
			);
		}


	    /**
		 * Generate the DHCheckoutFi Payment button link
	    **/
	    function generate_dhcheckoutfi_form( $order_id ) {

	    	// enqueue the payment button styles
	    	wp_enqueue_style( "dhcheckout-style", plugins_url( 'assets/dhcheckout-style.css' , __FILE__ ) );

			$order 					= wc_get_order( $order_id );

			if($order->get_order_currency() != "EUR") {
				wc_add_notice( "Verkkomaksua ei voi käyttää, mikäli valuuttana ei ole Euro" , 'error' );
				return;
			}

			$customer 				= $order->get_address('billing');
			$description       		= "Maksu tilaukselle ".$order->get_order_number()." (". get_bloginfo('name').")";
			$return_url 			= WC()->api_request_url( 'WC_Tbz_DHCheckoutFi_Gateway' );
			$order_total			= $order->get_total();

			// merchantID and securitykey (normally about 80 chars)
			$co = new Checkout($this->merchant_id, $this->merchant_secret); 

			// Order information
			$coData						= array();
			$coData["stamp"]			= time(); // unique timestamp
			$coData["reference"]		= $order->get_id();
			$coData["message"]			= $description;
			$coData["return"]			= $return_url;
			$coData["delayed"]			= $return_url;
			$coData["amount"]			= ceil($order_total*100); // price in cents
			$coData["delivery_date"]	= date("Ymd");
			$coData["firstname"]		= $customer['first_name'];
			$coData["familyname"]		= $customer['last_name'];
			$coData["address"]			= $customer['address_1']." ".$customer['address_2'];
			$coData["postcode"]			= $customer['postcode'];
			$coData["postoffice"]		= $customer['city'];
			$coData["email"]			= $customer['email'];
			$coData["phone"]			= $customer['phone'];

			// change stamp for xml method
			$coData['stamp'] = time() + 1;
			$response =	$co->getCheckoutXML($coData); // get payment button data
			$xml = simplexml_load_string($response);

			if($xml === false) {
				wc_add_notice( "XML-rajapinnan käyttö epäonnistui. Ota yhteys verkkokaupan asiakaspalveluun." , 'error' );
			} else {
				// paymentURL link is used if a payer somehow manages to fail paying. You can
				// save it to the webstore and later (if needed) send it by email.
				$link = $xml->paymentURL;
			}

			$return = '<p>'.$this->instructions.'</p>';

	foreach($xml->payments->payment->banks as $bankX) {
		foreach($bankX as $bank) {
			$return .= "<div class='C1'>
		<form action='".$bank['url']."' method='post'>";
			foreach($bank as $key => $value) {
				$return .= "<input type='hidden' name='".$key."' value='".htmlspecialchars($value)."'>";
			}
			$return .= "<span><input type='image' src='".$bank['icon']."'> </span>
			<div>".$bank['name']."</div>";
			$return .= "</form>
		</div>";
		}
	}
	$return .= "<hr style='clear: both;'>";
	$return .= '<a class="button cancel" href="'.esc_url_raw( $order->get_cancel_order_url() ).'">'.__('Peruuta tilaus ja palaa ostoskoriin', 'woocommerce').'</a>'
				;
				return $return;
		}


	    /**
	     * Process the payment and return the result
	    **/
		function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

	        return array(
	        	'result' 	=> 'success',
				'redirect'	=> $order->get_checkout_payment_url( true )
	        );

		}

		public function payment_fields(){
			echo $this->description;
			if( $this->show_banner == 'yes' ) {
				if( empty( $this->banner_url ) ) {
					$banner = plugins_url( 'assets/checkout-banner.png' , __FILE__ );
				} else {
					$banner = esc_url_raw( $this->banner_url );
				}
				echo '<br /><img src="'.$banner.'" style="max-height: 300px; width: 100%; height: auto; max-width: 300px; float: none;" />';
			}
		}

	    /**
	     * Output for the order received page.
	    **/
		function receipt_page( $order ) {
			echo $this->generate_dhcheckoutfi_form( $order );
		}


		/**
		 * Process Payment!
		**/
		function check_dhcheckoutfi_response( $posted ){
			//print_r($_GET);

			$order_id 		= (int)$_GET['REFERENCE'];
            $order 			= wc_get_order($order_id);

            //after payment hook
            do_action('tbz_dhcheckoutfi_woo_after_payment', $_POST, $order );

			// merchantID and securitykey (normally about 80 chars)
			$co = new Checkout($this->merchant_id, $this->merchant_secret); 

			// if we are returning from payment
			if(isset($_GET['MAC'])) 
			{ 
				if($co->validateCheckout($_GET))
				{
					if($co->isPaid($_GET['STATUS'])) 
					{
						//echo "OK";
                        //$order->update_status( 'processing', 'Maksu vahvistettu' );

                        $order->add_order_note( 'Maksettu verkkomaksuna');

                        // Let Woocommerce handle status update and stock levels
                        $order->payment_complete();
                        
						// Reduce stock levels
						//$order->reduce_order_stock();

						// Empty cart
						WC()->cart->empty_cart();
					} 
					else 
					{
						$maksu_error = array(
							"-10" => "Virhe: Maksu hyvitetty maksajalle. Ota yhteys asiakaspalveluun",
							"-4" => "Virhe: Maksutapahtumaa ei löydy",
							"-3" => "Virhe: Maksutapahtuma aikakatkaistiin",
							"-2" => "Virhe: Järjestelmä peruutti maksun",
							"-1" => "Virhe: Käyttäjä perui maksun",
							"1" => "Virhe: Maksutapahtuma kesken. Ota yhteys asiakaspalveluun.",
							"3" => "Maksu viivästyi. Ota yhteys asiakaspalveluun."
						);
						if(isset($maksu_error[$_GET['STATUS']])) {
							$virhe = $maksu_error[$_GET['STATUS']];
						} else {
							$virhe = "Maksu epäonnistui tai peruttiin.";
						}
	                    $order->add_order_note( 'Asiakas sai virheen: '.$virhe );
						$order->update_status('failed', 'Maksu epäonnistui tai peruttiin');
						wc_add_notice( $virhe, 'error' );
					}
				} 
				else 
				{
					$order->update_status('failed', 'Maksu epäonnistui');
	                $order->add_order_note( 'Asiakas sai virheen: Virhe maksutapahtuman käsittelyssä' );
					wc_add_notice( 'Virhe maksutapahtuman käsittelyssä', 'error' );
				}
			}
			else 
			{
				$order->update_status('failed', 'Maksu epäonnistui');
	            $order->add_order_note( 'Asiakas sai virheen: MAC-parametri puuttuu' );
				wc_add_notice( 'Virhe: MAC-parametri puuttuu', 'error' );
			}

			$redirect_url = $order->get_checkout_order_received_url();
		    wp_redirect( $redirect_url );

		    exit;
        }
	}
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function tbz_wc_add_dhcheckoutfi_gateway($methods) {
		$methods[] = 'WC_Tbz_DHCheckoutFi_Gateway';
		return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'tbz_wc_add_dhcheckoutfi_gateway' );


	/**
	* Add Settings link to the plugin entry in the plugins menu for WC below 2.1
	**/
	if ( version_compare( WOOCOMMERCE_VERSION, "2.1" ) <= 0 ) {

		add_filter('plugin_action_links', 'tbz_dhcheckoutfi_plugin_action_links', 10, 2);

		function tbz_dhcheckoutfi_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Tbz_DHCheckoutFi_Gateway">Asetukset</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}
	/**
	* Add Settings link to the plugin entry in the plugins menu for WC 2.1 and above
	**/
	else{
		add_filter('plugin_action_links', 'tbz_dhcheckoutfi_plugin_action_links', 10, 2);

		function tbz_dhcheckoutfi_plugin_action_links($links, $file) {
		    static $this_plugin;

		    if (!$this_plugin) {
		        $this_plugin = plugin_basename(__FILE__);
		    }

		    if ($file == $this_plugin) {
		        $settings_link = '<a href="' . get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_tbz_dhcheckoutfi_gateway">Asetukset</a>';
		        array_unshift($links, $settings_link);
		    }
		    return $links;
		}
	}

	/**
 	* Display the testmode notice
 	**/
	function tbz_wc_dhcheckoutfi_testmode_notice(){
		$tbz_dhcheckoutfi_settings = get_option( 'woocommerce_tbz_dhcheckoutfi_gateway_settings' );

		$dhcheckoutfi_test_mode = $tbz_dhcheckoutfi_settings['testmode'];

		if ( 'yes' == $dhcheckoutfi_test_mode ) {

		$settings_link = get_bloginfo('wpurl') . '/wp-admin/admin.php?page=wc-settings&tab=checkout&section=wc_tbz_dhcheckoutfi_gateway';

	    ?>
		    <div class="update-nag">
		        Verkkomaksutoiminto on testaustilassa. Poista testaustila käytöstä <a href="<?php echo $settings_link; ?>">täällä</a>
		    </div>
	    <?php
		}
	}
	add_action( 'admin_notices', 'tbz_wc_dhcheckoutfi_testmode_notice' );
}


/* Checkout class from http://www.checkout.fi/materiaalit/tekninen-materiaali/ */
class Checkout 
{
	private $version		= "0001";
	private $language		= "FI";
	private $country		= "FIN";
	private $currency		= "EUR";
	private $device			= "1";
	private $content		= "1";
	private $type			= "0";
	private $algorithm		= "3";
	private $merchant		= "";
	private $password		= "";
	private $stamp			= 0;
	private $amount			= 0;
	private $reference		= "";
	private $message		= "";
	private $return			= "";
	private $cancel			= "";
	private $reject			= "";
	private $delayed		= "";
	private $delivery_date	= "";
	private $firstname		= "";
	private $familyname		= "";
	private $address		= "";
	private $postcode		= "";
	private $postoffice		= "";
	private $status			= "";
	private $email			= "";
	
	public function __construct($merchant, $password) 
	{
		$this->merchant	= $merchant; // merchant id
		$this->password	= $password; // security key (about 80 chars)
	}

	/*
 	 * generates MAC and prepares values for creating payment
	 */	
	public function getCheckoutObject($data) 
	{
		// overwrite default values
		foreach($data as $key => $value) 
		{
			$this->{$key} = $value;
		}

		$mac = 
strtoupper(md5("{$this->version}+{$this->stamp}+{$this->amount}+{$this->reference}+{$this->message}+{$this->language}+{$this->merchant}+{$this->return}+{$this->cancel}+{$this->reject}+{$this->delayed}+{$this->country}+{$this->currency}+{$this->device}+{$this->content}+{$this->type}+{$this->algorithm}+{$this->delivery_date}+{$this->firstname}+{$this->familyname}+{$this->address}+{$this->postcode}+{$this->postoffice}+{$this->password}"));
		$post['VERSION']		= $this->version;
		$post['STAMP']			= $this->stamp;
		$post['AMOUNT']			= $this->amount;
		$post['REFERENCE']		= $this->reference;
		$post['MESSAGE']		= $this->message;
		$post['LANGUAGE']		= $this->language;
		$post['MERCHANT']		= $this->merchant;
		$post['RETURN']			= $this->return;
		$post['CANCEL']			= $this->cancel;
		$post['REJECT']			= $this->reject;
		$post['DELAYED']		= $this->delayed;
		$post['COUNTRY']		= $this->country;
		$post['CURRENCY']		= $this->currency;
		$post['DEVICE']			= $this->device;
		$post['CONTENT']		= $this->content;
		$post['TYPE']			= $this->type;
		$post['ALGORITHM']		= $this->algorithm;
		$post['DELIVERY_DATE']	= $this->delivery_date;
		$post['FIRSTNAME']		= $this->firstname;
		$post['FAMILYNAME']		= $this->familyname;
		$post['ADDRESS']		= $this->address;
		$post['POSTCODE']		= $this->postcode;
		$post['POSTOFFICE']		= $this->postoffice;
		$post['MAC']			= $mac;

		$post['EMAIL']			= $this->email;
		$post['PHONE']			= $this->phone;

		return $post;
	}
	
	/*
	 * returns payment information in XML
	 */
	public function getCheckoutXML($data) 
	{
		$this->device = "10";
		return $this->sendPost($this->getCheckoutObject($data));
	}
	
	private function sendPost($post) {
		$options = array(
				CURLOPT_POST 		=> 1,
				CURLOPT_HEADER 		=> 0,
				CURLOPT_URL 		=> 'https://payment.checkout.fi',
				CURLOPT_FRESH_CONNECT 	=> 1,
				CURLOPT_RETURNTRANSFER 	=> 1,
				CURLOPT_FORBID_REUSE 	=> 1,
				CURLOPT_TIMEOUT 	=> 20,
				CURLOPT_POSTFIELDS 	=> http_build_query($post)
		);
		
		$ch = curl_init();
		curl_setopt_array($ch, $options);
		$result = curl_exec($ch);
	    curl_close($ch);

	    return $result; 
	}
	
	public function validateCheckout($data) 
	{
		$generatedMac =  strtoupper(hash_hmac("sha256","{$data['VERSION']}&{$data['STAMP']}&{$data['REFERENCE']}&{$data['PAYMENT']}&{$data['STATUS']}&{$data['ALGORITHM']}",$this->password));
		
		if($data['MAC'] === $generatedMac) 
			return true;
		else
			return false;
	}
	
	public function isPaid($status)
	{
		if(in_array($status, array(2, 4, 5, 6, 7, 8, 9, 10))) 
			return true;
		else
			return false;
	}
}  // class Checkout