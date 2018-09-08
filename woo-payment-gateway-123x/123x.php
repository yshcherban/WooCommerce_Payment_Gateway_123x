<?php
/*
  Plugin Name: WooCommerce Payment Gateway - 123x
  Plugin URI: https://github.com/yshcherban/WooCommerce_Payment_Gateway_123x
  Text Domain: woo-payment-gateway-123x
  Description: Payment Gateway for 123x.io
  Version: 1.0.0
  Author: Yaroslav Shcherban
  Author URI: https://github.com/yshcherban
  License: GPL version 3 or later - http://www.gnu.org/licenses/gpl-3.0.html

  @package WordPress
  @author Yaroslav Shcherban (https://github.com/yshcherban)
  @since 1.0.0
 */

defined( 'ABSPATH' ) or exit;

if (!in_array(
    'woocommerce/woocommerce.php',
    apply_filters('active_plugins', get_option('active_plugins'))
)) {
    return;
}

add_filter('woocommerce_payment_gateways', 'add_123x_gateway');

/**
 * Add the gateway to WooCommerce
 *
 * @access public
 * @param array $methods
 * @package WooCommerce/Classes/Payment
 *
 * @return array $methods
 */
function add_123x_gateway($methods)
{
    $methods[] = 'WC_Gateway_123x';
    return $methods;
}

add_action('plugins_loaded', 'wc_gateway_123x_load_plugin_textdomain' );

function wc_gateway_123x_load_plugin_textdomain() {
    load_plugin_textdomain(
        'woo-payment-gateway-123x',
        FALSE,
        basename(dirname( __FILE__ )) . '/languages/'
    );
}

add_action('plugins_loaded', 'wc_gateway_123x_init');

/**
 * INIT 123x.io Gateway
 *
 * @access public
 */
function wc_gateway_123x_init()
{

    class WC_Gateway_123x extends WC_Payment_Gateway
    {
        /**
         * Paysera image location
         */
        const P123X_LOGO = 'assets/images/123.png';

        /**
         * Default language
         */
        const DEFAULT_LOCAL = 'en';

        /**
         * Sandbox URL
         */
        const SANDBOX_123X_URL = 'http://77.37.154.184:7777/merchant';

        /**
         * Live URL
         */
        const LIVE_123X_URL = 'https://123x.io/merchant';

        /**
         * @var string
         */
        protected $sandboxUrl;

        /**
         * @var string
         */
        protected $liveUrl;


        /**
         * @var boolean
         */
        protected $test;

        /**
         * @var string
         */
        protected $apiKey;

        /**
         * @var string
         */
        protected $secretKey;

        /**
         * @var string
         */
        protected $locale;

        /**
         * @var object
         */
        protected $pluginSettings;

        /**
         * WC_Gateway_123x constructor.
         */
        public function __construct()
        {
            $this->id = '123x';
            $this->has_fields = true;
            $this->method_title = __('123x', 'woo-payment-gateway-123x');
            $this->method_description = __('About 123x payment gateway', 'woo-payment-gateway-123x');
            $this->icon = apply_filters(
                'woocommerce_123x_icon',
                plugin_dir_url(__FILE__) . $this::P123X_LOGO
            );

            $this->init_form_fields();
            $this->init_settings();

            $this->title = '123x'; // frontend title

            $this->apiKey = $this->get_option('apikey');
            $this->secretKey = $this->get_option('secretkey');
            $this->test = $this->get_option('test') === 'yes';
            $this->locale = $this::DEFAULT_LOCAL;
            $this->sandboxUrl = $this::SANDBOX_123X_URL;
            $this->liveUrl = $this::LIVE_123X_URL;


            add_action('woocommerce_api_wc_gateway_123x', array($this, 'check_callback_request'));


            add_action(
                'woocommerce_update_options_payment_gateways_123x',
                array($this, 'process_admin_options')
            );
        }

        public function init_form_fields()
        {
            if(!class_exists('Wc_123x_Settings')) {
                require_once 'includes/class-wc-123x-settings.php';
            }

            $this->setPluginSettings(Wc_123x_Settings::create());

            $this->form_fields = $this->getPluginSettings()->getFormFields();
        }

        public function admin_options()
        {
            ?>
            <h3><?= __( '123x Settings', 'woo-payment-gateway-123x' ); ?></h3>
            <p><img src="<?= plugins_url( 'woo-payment-gateway-123x/assets/images/123.png' ) ?>"></p>
            <?php
            $this->generate_settings_html();
        }

        public function process_payment($order_id)
        {
            error_log(
                'Order #' . $order_id . ' is redirected to payment.'
                . 'Notify URL: ' . trailingslashit(home_url()) . '?123xListener=123x_callback'
            );

            $order = wc_get_order($order_id);
            $order->add_order_note(__('123x: Order checkout process is started', 'woo-payment-gateway-123x'));
            $this->updateOrderStatus($order, 'wc-on-hold');

            $apiKey = $this->getApiKey();
            $secretKey = $this->getSecretKey();
            $lang = $this->getLocale();
            $parameters = $this->getWooParameters($order);

            $amountToDotSep = str_replace(',', '.', $parameters['amount']);
            $convertedAmountToRubFromEur = floatval(get_option('rc_options')["eur_cb"]) * floatval($amountToDotSep);

            $formattedAmount = number_format($convertedAmountToRubFromEur,2,'.','');

            $data = base64_encode(json_encode(array(
                "api_key" => $apiKey,
                "expiration" => "2014-07-21 12:44",
                "amount" => (string)$formattedAmount,
                "currency" => "RUR",
                "reference" => (string)$parameters['order'],
                "description" => (string)$parameters['order'],
                "handler" => "71010"
            )));

            $signIn = $secretKey . $data;


            $post_data = array(
                'data' => $data,
                'sign' => hash("sha256", $signIn),
                'api_key' => $apiKey,
                'set' => 'lang',
                'lang' => $lang
            );


            /** mail **/
                /*
                $to      = 'yar.shcherban@gmail.com';
                $subject = 'the subject';
                $message = (string)$formattedAmount;
                $headers = 'From: webmaster@example.com' . "\r\n" .
                    'Reply-To: webmaster@example.com' . "\r\n" .
                    'X-Mailer: PHP/' . phpversion();

                mail($to, $subject, $message, $headers);
                */
            /** mail **/


            $siteMode = $this->getTest() ? $this->getSandboxUrl() : $this->getLiveUrl();
            $url = $siteMode . '?' . http_build_query($post_data, null, '&');
            $url = preg_replace('/[\r\n]+/is', '', $url);


            WC()->session->cleanup_sessions();
            WC()->session->set( 'current_order' , $order );

            wc_reduce_stock_levels($order_id);

            return array(
                'result'   => 'success',
                'redirect' => $url, //$url //$this->get_return_url( $order )
            );

        }

        public function check_callback_request()
        {
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $data = '';
                $hash = hash("sha256", $this->getSecretKey() . urldecode($_POST['data']));

                if($hash == $_POST['sign'])
                    $data = json_decode(base64_decode(urldecode($_POST['data'])));

                $order = wc_get_order( $data->{'reference'} );

                switch ($data->{'status_code'}) {
                    case 1026: $this->updateOrderStatus($order, 'wc-pending'); return http_response_code(200);
                    case 1028: $this->updateOrderStatus($order, 'wc-completed'); return http_response_code(200);
                    case 1030: $this->updateOrderStatus($order, 'wc-failed'); return http_response_code(400);
                }
            }

            if ($_SERVER['REQUEST_METHOD'] === 'GET') {
                if (WC()->session->get( 'current_order' )) {
                    return wp_redirect($this->get_return_url(WC()->session->get( 'current_order' )));
                } else {
                    return wp_redirect(home_url());
                }
            }

            die();
        }

        protected function updateOrderStatus($order, $status)
        {
            $orderStatusFiltered = str_replace("wc-", "", $status);
            $order->update_status(
                $orderStatusFiltered,
                __('123x: Status changed to ', 'woo-payment-gateway-123x') . $orderStatusFiltered . '<br />',
                true
            );
        }

        /**
         * Get WooCommerce parameters for request
         *
         * @param object $order
         *
         * @return array
         */
        public function getWooParameters($order)
        {

            $lang = $this->getLocale();

            return array (
                'prebuild'      => true,
                'order'         => $order->get_id(),
                'amount'        => number_format($order->get_total(),2,',',''),
                'currency'      => $order->get_currency(),
                'country'       => $order->get_billing_country(),
                'cancel'        => $order->get_cancel_order_url(),
                'firstname'     => $order->get_billing_first_name(),
                'lastname'      => $order->get_billing_last_name(),
                'email'         => $order->get_billing_email(),
                'street'        => $order->get_billing_address_1(),
                'city'          => $order->get_billing_city(),
                'state'         => $order->get_billing_state(),
                'zip'           => $order->get_billing_postcode(),
                'countrycode'   => $order->get_billing_country(),
                'lang'          => $lang,
                'userid'        => $order->get_user_id()
            );
        }

        protected function getOrderLogMsg($order, $errorMsg, $sendLog = false)
        {
            $fullLog = $errorMsg . ':'
                . ' Order #' . $order->get_id() . ';'
                . ' Amount: ' . $order->get_total() . $order->get_currency();

            if ($sendLog) {
                error_log($fullLog);
                return $sendLog;
            } else {
                return $fullLog;
            }
        }

        /**
         * @return string
         */
        public function getApiKey()
        {
            return $this->apiKey;
        }

        /**
         * @param string $password
         */
        public function setApiKey($apiKey)
        {
            $this->apiKey= $apiKey;
        }

        /**
         * @return string
         */
        public function getSecretKey()
        {
            return $this->secretKey;
        }

        /**
         * @param string $password
         */
        public function setSecretKey($secretKey)
        {
            $this->secretKey= $secretKey;
        }

        /**
         * @return boolean
         */
        public function getTest()
        {
            return $this->test;
        }

        /**
         * @param boolean $test
         */
        public function setTest($test)
        {
            $this->test = $test;
        }

        /**
         * @return string
         */
        public function getLocale()
        {
            return $this->locale;
        }

        /**
         * @param string $locale
         *
         * @return self
         */
        public function setLocale($locale)
        {
            $this->locale = $locale;
            return $this;
        }

        /**
         * @return string
         */
        public function getSandboxUrl()
        {
            return $this->sandboxUrl;
        }

        /**
         * @param string $sandboxUrl
         *
         * @return self
         */
        public function setSandboxUrl($sandboxUrl)
        {
            $this->sandboxUrl = $sandboxUrl;
            return $this;
        }

        /**
         * @return string
         */
        public function getLiveUrl()
        {
            return $this->liveUrl;
        }

        /**
         * @param string $liveUrl
         *
         * @return self
         */
        public function setLiveUrl($liveUrl)
        {
            $this->sandboxUrl = $liveUrl;
            return $this;
        }

        /**
         * @return object
         */
        public function getPluginSettings()
        {
            return $this->pluginSettings;
        }

        /**
         * @param object $pluginSettings
         */
        public function setPluginSettings($pluginSettings)
        {
            $this->pluginSettings = $pluginSettings;
        }



    }




}


