<?php
/*
 * Plugin Name: WooCommerce FBL Payment Gateway
 * Plugin URI: N/A
 * Description: Take credit card payments on FBL store.
 * Author: Lambda Solutions S.A. de C.V.
 * Author URI: http://lambda.com.sv
 * Version: 1.0.1
 *
 * 
 

 /*
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */


if( file_exists( dirname(__FILE__) . '/vendor/autoload.php')) {
	require_once dirname(__FILE__) . '/vendor/autoload.php';
}

use Emarref\Jwt\Claim;
use LVR\CreditCard\Factory as CardFactory;
use LVR\CreditCard\Exceptions\CreditCardException;
use LVR\CreditCard\ExpirationDateValidator;

add_filter( 'woocommerce_payment_gateways', 'fbl_add_gateway_class' );
function fbl_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_fbl_Gateway'; // your class name is here
	return $gateways;
}
 
/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'fbl_init_gateway_class' );
function fbl_init_gateway_class() {
 
	class WC_fbl_Gateway extends WC_Payment_Gateway {
 
 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {

            $this->id = 'fbl'; // payment gateway plugin ID
            $this->icon = ''; // URL of the icon that will be displayed on checkout page near your gateway name
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = 'fbl Gateway';
            $this->method_description = 'Description of fbl payment gateway'; // will be displayed on the options page
         
            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products',
                'tokenization'
            );
         
            // Method with all the options fields
            $this->init_form_fields();
         
            // Load the settings.
            $this->init_settings();
            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->testmode = 'yes' === $this->get_option( 'testmode' );
            $this->secret_key =  $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
			$this->gateway_url =  $this->testmode ? $this->get_option( 'test_gateway_url' ) : $this->get_option( 'gateway_url' );
            
			// This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
         
            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
 
 		}
 
		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
 
		    $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable fbl Gateway',
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'This controls the title which the user sees during checkout.',
                    'default'     => 'Tarjeta de Crédito / Débito',
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'This controls the description which the user sees during checkout.',
                    'default'     => 'Paga de forma segura con tus tarjetas de crédito o débito',
                ),
                'gateway_url' => array(
                    'title'       => 'Payment gateway url',
                    'type'        => 'text',
                    'description' => 'Url for payment gateway url',
                    'desc_tip'    => true,
                ),				
                'test_gateway_url' => array(
                    'title'       => 'Test payment gateway url',
                    'type'        => 'text',
                    'description' => 'Test url for payment gateway url',
                    'desc_tip'    => true,
                ),				
                'testmode' => array(
                    'title'       => 'Test mode',
                    'label'       => 'Enable Test Mode',
                    'type'        => 'checkbox',
                    'description' => 'Place the payment gateway in test mode using test API keys.',
                    'default'     => 'yes',
                    'desc_tip'    => true,
                ),
                'secret_key' => array(
                    'title'       => 'Secret Key',
                    'type'        => 'password'
                ),
                'test_secret_key' => array(
                    'title'       => 'Test secret Key',
                    'type'        => 'password'
                )				
            );
 
	 	}
 
		/**
		 * You will need it if you want your custom credit card form, Step 4 is about it
		 */
		public function payment_fields() {
 
		    // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( $this->testmode ) {
                    $this->description .= ' MODO TEST HABILITADO.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }
        
			echo wpautop( wp_kses_post( 'Solamente se aceptan tarjetas visa y mastercard' ) );
			
            echo '				
				<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
				';
        
            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_credit_card_form_start', $this->id );
        
            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            
            echo '		
				<input id="fbl_type" name="fbl_type" type="hidden" >
				<input id="fbl_month"  name="fbl_month" type="hidden" >
				<input id="fbl_year" name="fbl_year" type="hidden" >				
				<p class="form-row form-row-wide validate-required woocommerce-validated" id="fbl_name_field">
					<label for="fbl_name">Titular de la tarjeta&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
					<span class="woocommerce-input-wrapper">
						<input type="text" class="input-text" name="fbl_name" id="fbl_name" placeholder="Nombre completo" autocomplete="off">
					</span>
				</p>								
				<p class="form-row form-row-wide validate-required woocommerce-validated" id="fbl_number_field">
					<label for="fbl_number">Número de la tarjeta&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
					<span class="woocommerce-input-wrapper inputWithIcon">						
						<input type="text" class="input-text" name="fbl_number" id="fbl_number" 
							onfocus="fbl_formatCardElem(this, \'Number\')" 
							onblur="fbl_onBlurCardNumber(this)"
							placeholder="•••• •••• •••• ••••" autocomplete="off">
						<i class="icon-credit-card-alt" aria-hidden="true"></i>					
					</span>
				</p>								
				<p class="form-row form-row-first validate-required woocommerce-validated" id="fbl_expiry_field">
					<label for="fbl_expiry">Fecha de expiración&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
					<span class="woocommerce-input-wrapper">
						<input type="text" class="input-text"  id="fbl_expiry" 
							onfocus="fbl_formatCardElem(this, \'Expiry\')" placeholder="MM/AAAA" autocomplete="off">

					</span>
				</p>								
				<p class="form-row form-row-last validate-required woocommerce-validated" id="fbl_cvc_field">
					<label for="fbl_cvc">CVC&nbsp;<abbr class="required" title="obligatorio">*</abbr></label>
					<span class="woocommerce-input-wrapper">
						<input  class="input-text"  id="fbl_cvc" name="fbl_cvc" 
							onfocus="fbl_formatCardElem(this, \'CVC\')" type="text" autocomplete="off" placeholder="••••">
					</span>
				</p>
                <div class="clear"></div>
				';
        
            do_action( 'woocommerce_credit_card_form_end', $this->id );
        
            echo '<div class="clear"></div></fieldset>';
 
		}
 
		/*
		 * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
		 */
	 	public function payment_scripts() {
 
		    // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }
        
            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }
        
            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->private_key ) || empty( $this->publishable_key ) ) {
                //return;
            }
        
            // do not work with card detailes without SSL unless your website is in a test mode
            if ( ! $this->testmode && ! is_ssl() ) {
                //return;
            }
             
            wp_register_style('woocommerce_fbl_css', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), '1.0.1', 'all');			
            wp_enqueue_style('woocommerce_fbl_css'); 
			
			wp_register_script('woocommerce_fbl_js_payform', plugin_dir_url( __FILE__ ) . 'assets/js/jquery.payform.min.js', array( 'jquery'), '1.4.0', true );
			wp_register_script('woocommerce_fbl_js_payment', plugin_dir_url( __FILE__ ) . 'assets/js/payment.js', array('woocommerce_fbl_js_payform' ), '1.0.4', true);
			
			wp_enqueue_script('woocommerce_fbl_js_payform');
			wp_enqueue_script('woocommerce_fbl_js_payment');
	 	}
 
		/*
 		 * Fields validation, more in Step 5
		 */
		public function validate_fields() {
 
			if( empty( $_POST[ 'fbl_name' ]) ) {
                wc_add_notice( 'El titular de la tarjeta es requerido', 'error' );
                return false;
            }
			if( empty( $_POST[ 'fbl_number' ]) ) {
                wc_add_notice( 'El número de la tarjeta es requerido', 'error' );
                return false;
            }
			
		    try{
				$cards = array("visa","mastercard");
				$card = CardFactory::makeFromNumber($_POST[ 'fbl_number' ]);
				
				if(!(in_array($card->name(), $cards))){
					wc_add_notice( 'Solo se permiten tarjetas Visa y Mastercard', 'error' );
					return false;					
				}				
				if(!(ExpirationDateValidator::validate($_POST['fbl_year'], $_POST['fbl_month']))){
					wc_add_notice( 'Por favor ingrese un mes y año válido', 'error' );
					return false;					
				}				
				if(!($card->isValidCvc($_POST['fbl_cvc']))){
					wc_add_notice( 'Por favor ingrese un código de verificación válido', 'error' );
					return false;					
				}
				
			}
			catch(CreditCardException $exc){
                wc_add_notice( 'El número de la tarjeta es inválido', 'error' );
                return false;				
			}
			
            return true;
		}
 
		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {
 
		    global $woocommerce;

			$cards = array("visa" => 1 ,"mastercard" => 2);

            // we need it to get any order detailes
            $order = wc_get_order( $order_id );
            $amount = $order->get_total();
            $currency = get_woocommerce_currency();
			$current_user = wp_get_current_user();
            $order_number = $order->get_order_number();
			$order_items = $order->get_items();

            $token = $this->create_auth_token();

			$items = array();
			foreach( $order_items as $order_item ) {
				$elem = array();
				$elem['description'] = $order_item->get_name();
				$elem['amount'] = floatval($order_item->get_total());
				$elem['quantity'] = $order_item->get_quantity();
				$elem['unitAmount'] = $elem['amount'] / $elem['quantity'];				
				
				array_push($items, $elem);
			}

			$payload = array(
				'platformId' =>  'futbolitico',
				'clientName' => $current_user->user_email,
				'cardNumber' => str_replace(' ', '', $_POST['fbl_number']),
				'cardName'  => $_POST['fbl_name'],
				'cardExpirationYear' => intval($_POST['fbl_year']),
				'cardExpirationMonth' => intval($_POST['fbl_month']),
				'cardVerificationValue' => $_POST['fbl_cvc'],
				'cardType' => $cards[$_POST['fbl_type']],
				'amount' => floatval($amount),
				'items' => $items,
				'metadata' => array(
					'wpOrderId' => $order_id,
					'wpOrderNumber' => $order_number
				)
			);
			
			$response = wp_remote_post("{$this->gateway_url}/api/virtualpos", array(
				'timeout'     => 3000,
				'httpversion' => '1.0',
				'body'    => wp_json_encode($payload),
				'headers' => array(
					'Content-Type' => 'application/json',
					'Authorization' => "Bearer {$token}",
				)
			));			
			
			if ( is_wp_error( $response ) ) {
				wc_add_notice("Error de conexión al procesar el pago", 'error' );
				return;
			} else {				
				$status = $response['response']['code'];
				$responseMessage = $response['response']['message'];
				$body = json_decode($response['body'], true);
				if($status == 200){
					$order->payment_complete();
					$order->add_order_note( 'Tu orden ha sido pagada con éxito! Gracias!', true );
					$woocommerce->cart->empty_cart();
					return array(
						'result' => 'success',
						'redirect' => $this->get_return_url( $order )
					);
				}
				else{
					$error_message = "Su transacción no puede ser procesada";
					if($status == 400 && isset($body->gatewayResponse) && $body->gatewayResponse->errorType === 'warning') {
						$error_message = $body->gatewayResponse->errorMessage;
					}
					wc_add_notice($error_message, 'error' );
					return;
				}
			}			
	 	}
 
		/*
		 * In case you need a webhook, like PayPal IPN etc
		 */
		public function webhook() {
			/*
		    $order = wc_get_order( $_GET['id'] );
            $order->payment_complete();
            $order->reduce_order_stock();

            update_option('webhook_debug', $_GET);
			*/
	 	}
		
		private function create_auth_token(){
			$token = new Emarref\Jwt\Token();
			$token->addClaim(new Claim\Expiration(new \DateTime('30 seconds')));
			$token->addClaim(new Claim\IssuedAt(new \DateTime('now')));
			$token->addClaim(new Claim\NotBefore(new \DateTime('now')));
			$token->addClaim(new Claim\PublicClaim('unique_name', 'root'));
			$jwt = new Emarref\Jwt\Jwt();
			$algorithm = new Emarref\Jwt\Algorithm\Hs256($this->secret_key);
			$encryption = Emarref\Jwt\Encryption\Factory::create($algorithm);
			return $jwt->serialize($token, $encryption);		
		}
		
 	}
}