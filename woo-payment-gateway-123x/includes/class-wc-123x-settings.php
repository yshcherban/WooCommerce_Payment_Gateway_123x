<?php

defined('ABSPATH') or exit;

/**
 * 123x Payment Gateway Admin settings
 */
class Wc_123x_Settings extends WC_Payment_Gateway
{
    /**
     * @var array
     */
    protected $formFields;

    /**
     * @return self
     */
    static public function create()
    {
        return new self();
    }

    /**
     * Wc_123x_Settings constructor.
     */
    public function __construct()
    {
        $this->formFields = array(
            'enabled' => array(
                'title'       => __('Enable 123x', 'woo-payment-gateway-123x'),
                'label'       => __('Enable 123x payment', 'woo-payment-gateway-123x'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no'
            ),
            'apikey' => array(
                'title'       => __('Api Key', 'woo-payment-gateway-123x'),
                'type'        => 'text',
                'description' => __('123x Api Key', 'woo-payment-gateway-123x'),
                'default'     => __('', 'woo-payment-gateway-123x')
            ),
            'secretkey' => array(
                'title'       => __('Secret Key', 'woo-payment-gateway-123x'),
                'type'        => 'text',
                'description' => __('123x Secret Key', 'woo-payment-gateway-123x'),
                'default'     => __('', 'woo-payment-gateway-123x')
            ),
            'test' => array(
                'title'       => __('Test', 'woo-payment-gateway-123x'),
                'type'        => 'checkbox',
                'label'       => __('Enable test mode', 'woo-payment-gateway-123x'),
                'default'     => 'yes',
                'description' => __('Enable this to accept test payments', 'woo-payment-gateway-123x'),
            ),
        );
    }

    /**
     * @return array
     */
    public function getFormFields()
    {
        return $this->formFields;
    }

    /**
     * @param array $formFields
     *
     * @return self
     */
    public function setFormFields($formFields)
    {
        $this->formFields = $formFields;

        return $this;
    }



}