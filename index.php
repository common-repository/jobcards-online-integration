<?php

/**
 * Plugin Name:  Jobcards Online Integration
 * Plugin URI: https://www.jobcardsonline.co.za
 * Description: Provides integration between www.jobcardsonline.co.za and the woocommerce wordpress plugin. This plugin allows invoices, pro-forma invoices and clients to be created on jobcardsonline inside of wordpress. It provides full integration of the jobcardsonline system for use with woocommerce.
 * Author: Jobcards Online
 * Version: 4.9.1
 * Author URI: https://www.jobcardsonline.co.za/
 */


include(plugin_dir_path(__FILE__) . 'assets/classes/io-api.class.php');

function jconline_activate()
{
}

register_activation_hook(__FILE__, 'jconline_activate');

// add_action('woocommerce_created_customer', 'jconline_create_customer');
if (wp_is_block_theme()){
    add_action('woocommerce_store_api_checkout_order_processed', 'jconline_add_io_client', 99, 1);
    add_action('woocommerce_store_api_checkout_update_order_meta', 'jconline_before_checkout');
    // add_action('woocommerce_checkout_order_processed', 'jconline_add_io_client', 99, 1);
    
} else {
    add_action('woocommerce_checkout_order_created', 'jconline_add_io_client', 99, 1);
    add_action('woocommerce_checkout_order_created', 'jconline_before_checkout', 99 , 1);   
    
}




add_action('woocommerce_checkout_order_processed', 'jconline_log_order');
add_action('woocommerce_payment_complete', 'jconline_invoice_table_successfull_payment');

add_action('woocommerce_after_my_account', 'jconline_invoice_table');
add_action('woocommerce_settings_tabs_products', 'jconline_create_vat');
add_action('woocommerce_settings_save_products', 'jconline_save_vat');

function jconline_log($txt)
{
    if (is_array($txt)) {
        $txt = json_encode($txt);
    }
    file_put_contents(__DIR__ . '/assets/logs/logs.txt', $txt . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function jconline_invoice_table()
{

    $table = '<h2>Invoices</h2>';

    $io = new InvoicesOnlineAPI();
    $io->username = get_option('io_api_username');
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');

    $user_ID = get_current_user_id();
    $ioID = get_transient('ClientID');//get_user_meta($user_ID, 'invoices_online_id', true);
    $document = json_decode(wp_remote_retrieve_body($io->GetAllDocumentsByType('invoices', $ioID)),true);

    // echo '<pre>';
    // // var_dump($ioID);
    // print_r($document);
    // // print_r(wp_remote_retrieve_body($document));
    // echo '</pre>';

    if (is_array($document) && count($document) > 0) {

        $table .= '<table class="shop_table my_account_orders">
                <thead>
                    <tr>
                        <th class="order-number"><span class="nobr">Order</span></th>
                        <th class="order-date"><span class="nobr">Date</span></th>
                        <th class="order-total"><span class="nobr">Total</span></th>
                        <th class="order-actions">&nbsp;</th>
                    </tr>
                </thead>
                <tbody>';
        foreach ($document as $doc) {
            foreach ($doc as $dc) {
                $table .= '<tr class="order">
                        <td class="order-number"><a href="' . $dc['link'] . '" target="_blank">' . $dc['invoice_nr'] . '</a></td>
                        <td class="order-date">' . $dc['invoice_date'] . '</td>
                        <td class="order-total"><span class="amount">' . $dc['amount_formatted'] . '</span></td>
                        <td class="order-actions"><a href="' . $dc['link'] . '" target="_blank" class="button view">View</a></td>
                    </tr>';
            }
        }

        $table .= '</tbody></table>';
        echo wp_kses($table, [
            'h2' => [],
            'table' => [
                'class' => []
            ],
            'thead' => [],
            'tr' => [],
            'th' => [
                'class' => []
            ],
            'td' => [
                'class' => []
            ],
            'span' => [
                'class' => []
            ],
            'a' => [
                'href' => [],
                'target' => [],
                'class' => [],
            ]
        ]);
    }
}

add_action('woocommerce_email_order_meta', 'jconline_invoice_table_add_order_notes_to_email');

function jconline_invoice_table_add_order_notes_to_email()
{
    $message = '<h2 style="color:#505050; display:block; font-family:Arial; font-size:30px; font-weight:bold; margin-top:10px; margin-right:0; margin-bottom:10px; margin-left:0; text-align:left; line-height:150%">Invoice</h2>';
    $message .= 'Click here to view your Invoice : <a href="' . WC()->session->ioinvoiceurl . '"> View Invoice</a>';
    echo wp_kses($message, [
        'h2' => [
            'style' => []
        ],
        'a' => [
            'href' => []
        ]
    ]);
}

function jconline_log_order($order)
{
    $order_io_inv = get_post_meta($order, 'io_proforma_invoice', true);
    WC()->session->ioOrder = $order_io_inv;
}

function jconline_invoice_table_successfull_payment($order_id)
{

    $autoinvoice = get_option('autoinvoice');

    $order = wc_get_order($order_id);
    $order_data = $order->get_data();

    $clientID = get_post_meta($order_id, 'ClientID', true);
    update_post_meta($order_id, 'io_client_id', $clientID);

    update_post_meta($order_id, 'iostatus', 'Invoice not generated');

    if ($autoinvoice == 'yes') {
        $io = new InvoicesOnlineAPI();

        $order_state = ($order_data['shipping']['state'] == '') ? $order_data['billing']['state'] : $order_data['shipping']['state'];
        $regionalCompanies = get_option('IORegionalCompanies', '[]');
        $regions = json_decode($regionalCompanies, true);
        $customerRegion = $order_state;
        $setRegions = array_keys($regions);
        if (in_array($customerRegion, $setRegions)) {
            $io->username = $regions[$customerRegion]['username'];
            $io->password = $regions[$customerRegion]['password'];
            $io->BusinessID = $regions[$customerRegion]['code'];
        } else {
            $io->username = get_option('io_api_username');
            $io->password = get_option('io_api_password');
            $io->BusinessID = get_option('io_business_id');
        }

        $proFormaInvNr = get_post_meta($order_id, 'io_proforma_invoice', true);

        $createInvoice = $io->ConvertProformaToInvoice(get_option('io_business_id'), $proFormaInvNr);
        $createInvoice = json_decode($createInvoice);
        WC()->session->ioinvoiceurl = $createInvoice[2]->url;

        update_post_meta($order_id, 'iostatus', 'Generated');


        $paymentopts['payment_method'] = $order_data['payment_method_title'];
        $paymentopts['Description'] = '';
        $paymentopts['PaymentAmount'] = get_post_meta($order_id, 'io_cart_total', true);

        $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientID, $paymentopts);
    }

    return;
}

function jconline_create_customer($data)
{

    $io = new InvoicesOnlineAPI();

    $io->username = get_option('io_api_username');
    $io->password = get_option('io_api_password');
    $io->BusinessID = get_option('io_business_id');

    // $addioid = update_user_meta($data, 'invoices_online_id', get_transient('ClientID'));
}

function jconline_add_io_client($order_id)
{

    delete_transient('ClientID');

    $order = wc_get_order($order_id);

    $io = new InvoicesOnlineAPI();

    // $cinfo['billing_state'] = sanitize_text_field($_REQUEST['billing_state']);
    // $cinfo['billing_company'] = sanitize_text_field($_REQUEST['billing_company']);
    // $cinfo['billing_first_name'] = sanitize_text_field($_REQUEST['billing_first_name']);
    // $cinfo['billing_last_name'] = sanitize_text_field($_REQUEST['billing_last_name']);
    // $cinfo['billing_phone'] = sanitize_text_field($_REQUEST['billing_phone']);
    // $cinfo['billing_email'] = sanitize_email($_REQUEST['billing_email']);
    // $cinfo['billing_address_1'] = sanitize_text_field($_REQUEST['billing_address_1']);
    // $cinfo['billing_address_2'] = sanitize_text_field($_REQUEST['billing_address_2']);
    // $cinfo['billing_city'] = sanitize_text_field($_REQUEST['billing_city']);
    // $cinfo['billing_postcode'] = sanitize_text_field($_REQUEST['billing_postcode']);

    $cinfo['billing_state'] = $order->get_billing_state();
    $cinfo['billing_company'] = $order->get_billing_company();
    $cinfo['billing_first_name'] = $order->get_billing_first_name();
    $cinfo['billing_last_name'] = $order->get_billing_last_name();
    $cinfo['billing_phone'] = $order->get_billing_phone();
    $cinfo['billing_email'] = $order->get_billing_email();
    $cinfo['billing_address_1'] = $order->get_billing_address_1();
    $cinfo['billing_address_2'] = $order->get_billing_address_2();
    $cinfo['billing_city'] = $order->get_billing_city();
    $cinfo['billing_postcode'] = $order->get_billing_postcode();

    $regionalCompanies = get_option('IORegionalCompanies', '[]');
    $regions = json_decode($regionalCompanies, true);
    $customerRegion = $cinfo['billing_state'];
    $setRegions = array_keys($regions);
    if (in_array($customerRegion, $setRegions)) {
        $io->username = $regions[$customerRegion]['username'];
        $io->password = $regions[$customerRegion]['password'];
        $io->BusinessID = $regions[$customerRegion]['code'];
    } else {
        $io->username = get_option('io_api_username');
        $io->password = get_option('io_api_password');
        $io->BusinessID = get_option('io_business_id');
    }

    if ($cinfo['billing_company'] == '') {
        $ClientParams['client_invoice_name'] = $cinfo['billing_first_name'] . ' ' . $cinfo['billing_last_name'];
    } else {
        $ClientParams['client_invoice_name'] = $cinfo['billing_first_name'] . ' ' . $cinfo['billing_last_name'] . ' ( ' . $cinfo['billing_company'] . ' )';
    }
    $ClientParams['client_phone_nr'] = $cinfo['billing_phone'];
    $ClientParams['client_phone_nr2'] = '';
    $ClientParams['client_mobile_nr'] = '';
    $ClientParams['client_email'] = $cinfo['billing_email'];
    $ClientParams['client_vat_nr'] = '';
    $ClientParams['client_fax_nr'] = '';
    $ClientParams['contact_name'] = $cinfo['billing_first_name'];
    $ClientParams['contact_surname'] = $cinfo['billing_last_name'];
    $ClientParams['client_postal_address1'] = $cinfo['billing_address_1'];
    $ClientParams['client_postal_address2'] = $cinfo['billing_address_2'];
    $ClientParams['client_postal_address3'] = $cinfo['billing_city'] . ', ' . $cinfo['billing_state'];
    $ClientParams['client_postal_address4'] = $cinfo['billing_postcode'];
    $ClientParams['client_physical_address1'] = $cinfo['shipping_address_1'];
    $ClientParams['client_physical_address2'] = $cinfo['shipping_address_2'];
    $ClientParams['client_physical_address3'] = $cinfo['shipping_city'] . ', ' . $cinfo['shipping_state'];
    $ClientParams['client_physical_address4'] = $cinfo['shipping_postcode'];

    $ClientID = $io->CreateNewClient($ClientParams);

    set_transient('ClientID', $ClientID);
    
}

function jconline_before_checkout($order_id)
{


    $order = wc_get_order($order_id);


    $vatapplies = get_option('vatapplies');
    $amountsincludevat = get_option('amountsincludevat');
    $autoinvoice = get_option('autoinvoice');

    $io = new InvoicesOnlineAPI();

    // $address_details['billing_state'] = sanitize_text_field($_POST['billing_state']);
    $address_details['billing_state'] = $order->get_billing_state();
    $regionalCompanies = get_option('IORegionalCompanies', '[]');
    $regions = json_decode($regionalCompanies, true);
    $customerRegion = $address_details['billing_state'];
    $setRegions = array_keys($regions);
    if (in_array($customerRegion, $setRegions)) {
        $io->username = $regions[$customerRegion]['username'];
        $io->password = $regions[$customerRegion]['password'];
        $io->BusinessID = $regions[$customerRegion]['code'];
    } else {
        $io->username = get_option('io_api_username');
        $io->password = get_option('io_api_password');
        $io->BusinessID = get_option('io_business_id');
    }

    $woocart = WC()->cart->get_cart();
    $wctax = new WC_Tax();
    $wctavr = $wctax->get_rates();

    $currency = get_woocommerce_currency();
    switch ($currency) {
        case 'AED':
        case 'AUD':
        case 'BWP':
        case 'CAD':
        case 'CNY':
        case 'EUR':
        case 'GBP':
        case 'NAD':
        case 'USD':
        case 'ZMW':

            break;
        default:
            $currency = 'ZAR';
            break;
    }

    $lines = array();
    $i = 0;
    $shipping = WC()->cart->shipping_total;

    foreach ($woocart as $item) {

        $product_info = wc_get_product($item['product_id']);

        $price = get_post_meta($item['product_id'], '_price', true);

        $lines[$i][0] = $item['product_id'];
        $lines[$i][1] = $item['quantity'];
        $lines[$i][2] = $item['data']->post->post_title;
        $lines[$i][3] = $price;
        $lines[$i][4] = $currency;
        if ($amountsincludevat == 'yes') {
            $lines[$i][5] = 1;
        } else {
            $lines[$i][5] = 0;
        };
        if (empty($wctavr)) {
            $lines[$i][6] = '0';
        } else {
            $lines[$i][6] = $wctavr[1]['rate'];
        }
        if ($vatapplies == 'yes') {
            $lines[$i][7] = 1;
        } else {
            $lines[$i][7] = 0;
        }
        $lines[$i][8] = sanitize_text_field(@$_REQUEST['order_comments']);
        $i++;
    }

    $vouchertag = get_option('voucherchecktag');
    foreach ($lines as $lkey => $line) {
        if (has_term($vouchertag, 'product_tag', $line[0])) {
            unset($lines[$lkey]);
        }
    }

    if (count($lines) > 0) {

        if ($shipping > 0) {
            $lines[$i][0] = 'shipping';
            $lines[$i][1] = 1;
            $lines[$i][2] = 'Shipping';
            $lines[$i][3] = $shipping;
            $lines[$i][4] = $currency;
            if ($amountsincludevat == 'yes') {
                $lines[$i][5] = 1;
            } else {
                $lines[$i][5] = 0;
            };
            if (empty($wctavr)) {
                $lines[$i][6] = '0';
            } else {
                $lines[$i][6] = $wctavr[1]['rate'];
            }
            if ($vatapplies == 'yes') {
                $lines[$i][7] = 1;
            } else {
                $lines[$i][7] = 0;
            }
            $lines[$i][8] = '';
            $i++;
        }

        $OrderNR = $order->get_id();

        WC()->session->iolines = $lines;
        WC()->session->iocarttotal = WC()->cart->total;
        // WC()->session->ioclientid = $_SESSION['ClientID'];
        
        // var_dump('ClientID: '.get_transient('ClientID'));

        update_post_meta($order->get_id(), 'io_cart_total', WC()->cart->total);
        if (!is_numeric(get_transient('ClientID'))){
            jconline_add_io_client($order_id);
        }

        // var_dump('ClientID: '.get_transient('ClientID'));

        WC()->session->ioclientid = get_transient('ClientID');
        // $addproforma = $io->CreateNewProformaInvoice($io->BusinessID, $_SESSION['ClientID'], $OrderNR, $lines);
        $addproforma = $io->CreateNewProformaInvoice($io->BusinessID, get_transient('ClientID'), $OrderNR, $lines);
        // var_dump($addproforma);
        $responseData = json_decode(wp_remote_retrieve_body($addproforma),true);
        
        update_post_meta($order->get_id(), 'iostatus', 'Pro-forma Invoice generated');
        // update_post_meta($order->get_id(), 'ClientID', $_SESSION['ClientID']);
        update_post_meta($order->get_id(), 'ClientID', get_transient('ClientID'));
        

        if (is_array($responseData)) {
            foreach ($responseData as $val){
                if (isset($val['invoice_nr'])){
                    update_post_meta($order->get_id(), 'io_proforma_invoice', $val['invoice_nr']);
                }
            
        
            }
        }

        
    }
}

add_action('admin_menu', 'jconline_create_menu');

function jconline_create_menu()
{

    add_menu_page('Jobcards Online Plugin Settings', 'Jobcards Online', 'administrator', __FILE__, 'jconline_settings_page', plugins_url('/assets/images/favicon-16x16.png', __FILE__));

    add_action('admin_init', 'jconline_register_io_settings');
}

function jconline_register_io_settings()
{

    register_setting('io-settings-group', 'io_api_username');
    register_setting('io-settings-group', 'io_api_password');
    register_setting('io-settings-group', 'io_business_id');
    register_setting('io-settings-group', 'vatapplies');
    register_setting('io-settings-group', 'amountsincludevat');
    register_setting('io-settings-group', 'autoinvoice');
    register_setting('io-settings-group', 'checkcredits');
    register_setting('io-settings-group', 'vouchercheck');
    register_setting('io-settings-group', 'voucherchecktag');
    register_setting('io-settings-group', 'regionalCompanies');
}

function jconline_settings_page()
{
    /* load all settings */
    $io_api_username = esc_attr(get_option('io_api_username'), '');
    $io_api_password = esc_attr(get_option('io_api_password'), '');
    $io_business_id = esc_attr(get_option('io_business_id'), '');
    $vatapplies = get_option('vatapplies', 'no');
    $amountsincludevat = get_option('amountsincludevat', 'no');
    $autoinvoice = get_option('autoinvoice', 'no');
    $checkcredits = get_option('checkcredits', 'no');
    $vouchercheck = get_option('vouchercheck', 'no');
    $voucherchecktag = get_option('voucherchecktag', 'no');
    $regionalCompanies = get_option('regionalCompanies', 'no');

    $select_options = array('yes', 'no');

?>
    <div class="wrap">
        <h2>Jobcards Online Settings</h2>
        <?php if (isset($_GET['settings-updated'])) : ?>
            <div id="message" class="updated notice is-dismissible"><p><?php _e('Settings saved.', 'text-domain'); ?></p></div>
        <?php endif; ?>
        <img class="io-logo" src="<?php echo plugins_url('/assets/images/logo.png', __FILE__); ?>">
        <form method="post" action="options.php">
            <?php settings_fields('io-settings-group'); ?>
            <?php do_settings_sections('io-settings-group'); ?>
            <div class="io-tab-menu subsubsub">
                <div class="io-tab active" id="io-settings"><a href="#">Settings</a></div>
                <?php if ($regionalCompanies == 'yes') { ?>
                    <div class="io-tab" id="io-companies"><a href="#">Companies</a></div>
                <?php } ?>
                <div class="io-tab" id="io-faq"><a href="#">Support & FAQ</a></div>
                <div class="io_link"><a href="https://www.jobcardsonline.co.za/" target="_blank">Jobcards Online Website</a></div>
            </div>
            <div class="io-tab-holder active" id="io-settings-tab">
                <table class="wp-list-table widefat plugins">
                    <tr>
                        <td colspan="3">
                            API Information is available on <a href="https://www.jobcardsonline.co.za" target="_blank">www.jobcardsonline.co.za</a> -> Settings -> API Access
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Username</th>
                        <td><input type="text" name="io_api_username" value="<?php echo esc_html($io_api_username); ?>" /></td>
                        <td class="io-side-note"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">API Password</th>
                        <td><input type="text" name="io_api_password" value="<?php echo esc_html($io_api_password); ?>" /></td>
                        <td class="io-side-note"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Business ID</th>
                        <td><input type="text" name="io_business_id" value="<?php echo esc_html($io_business_id); ?>" /></td>
                        <td class="io-side-note"></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Woocommerce Prices Include VAT?</th>
                        <td>
                            <select class="select2" name="vatapplies">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($vatapplies == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Must Jobcards Online Apply vat to prices?</th>
                        <td>
                            <select class="select2" name="amountsincludevat">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($amountsincludevat == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Allow Clients to buy on Credit if they have credit in Jobcards Online</th>
                        <td>
                            <select class="select2" name="checkcredits">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($checkcredits == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Skip invoice creation and only process payment for products below?</th>
                        <td>
                            <select class="select2" name="vouchercheck">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($vouchercheck == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Select Tag to look for, to skip the invoice creation as mentioned in previous setting.</th>
                        <td>
                            <?php
                            $tags = get_terms(array('taxonomy' => 'product_tag', 'post_type' => 'product', 'hide_empty' => false));
                            if (!empty($tags)) {
                            ?>
                                <select class="select2" name="voucherchecktag">
                                    <option value=""> Select Tag </option>
                                    <?php
                                    foreach ($tags as $tag) {
                                        echo '<option value="' . esc_html($tag->slug) . '" ' . ($voucherchecktag == $tag->slug ? 'selected="selected"' : '') . '>' . esc_html($tag->slug) . '</option>';
                                    }
                                    ?>
                                </select>
                            <?php
                            } else {
                                echo esc_html('No Tags Created Yet, Create a tag to use this.');
                            }
                            ?>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Automatically convert pro-forma invoice to invoice?<br /><sub> ( If this is disabled then you will have to manually generate invoice from the orders screen )</sub></th>
                        <td>
                            <select class="select2" name="autoinvoice">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($autoinvoice == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Uses different companies for different regions:<br /><sub> Once activated it can be setup in the new tab that will be available.</sub></th>
                        <td>
                            <select class="select2" name="regionalCompanies">
                                <?php
                                foreach ($select_options as $option) {
                                    echo '<option value="' . esc_html($option) . '" ' . ($regionalCompanies == $option ? 'selected="selected"' : '') . '>' . esc_html($option) . '</option>';
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </div>
            <div class="io-tab-holder" id="io-companies-tab">
                <?php
                global $woocommerce;
                $countries_obj   = new WC_Countries();
                $default_country = $countries_obj->get_base_country();
                $default_county_states = $countries_obj->get_states($default_country);
                ?>
                <div class="regionalCompanyForm">
                    <div>
                        <label>Company Regions</label>
                        <select id="companyRegions" class="">
                            <option></option>
                            <?php
                            foreach ($default_county_states as $statekey => $state) {
                                echo '<option value="' . esc_html($statekey) . '">' . esc_html($state) . '</option>';
                            }
                            ?>
                        </select>
                    </div>
                    <div>
                        <label>Business ID</label><input id="companyCode" type="text" />
                    </div>
                    <div>
                        <label>Username</label><input id="io_username" type="text" />
                    </div>
                    <div>
                        <label>Password</label><input id="io_password" type="text" />
                    </div>
                    <div>
                        <span class="button blue" id="add-regional-company">Add / Update Regional Company</span>
                        <img class="io-loader" style="width:25px" src="<?php echo plugin_dir_url(__FILE__) . 'assets/images/spinner-1.gif' ?>" />
                    </div>
                </div>
                <table class="widefat" id="companyRegionsTable">
                    <thead>
                        <tr>
                            <th>Region/s</th>
                            <th>Business ID</th>
                            <th>Business Username</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>

                    </tbody>
                </table>
            </div>
            <div class="io-tab-holder" id="io-faq-tab">
                <table class="wp-list-table widefat plugins">
                    <tr valign="top">
                        <th scope="row">Support Email:</th>
                        <td><a href="mailto:info@jobcardsonline.co.za">info@jobcardsonline.co.za</a></td>
                    </tr>
                    <tr valign="top">
                        <th scope="row">Website:</th>
                        <td><a href="https://www.jobcardsonline.co.za">www.jobcardsonline.co.za</a></td>
                    </tr>
                </table>
            </div>
            <?php submit_button(); ?>

        </form>
    </div>
<?php
}

add_action('admin_head', 'jconline_head');

function jconline_head()
{
?>
    <style type="text/css">
        .io-tab-menu {
            display: flex;
            width: 100%;
            background: #4c8ab7;
            border: 1px solid #e5e5e5;
            -webkit-box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            margin-bottom: 10px;
        }

        .io-tab-menu div {
            padding: 5px 10px;
            border-right: solid 1px #ffffff;
        }

        .io-tab-menu div:hover {
            background: #222222;
        }

        .io-tab-menu div a {
            color: #ffffff;
        }

        .io-tab-menu div:last-child {
            border-right: none;
        }

        .subsubsub span {
            line-height: 2;
            padding: .2em;
            text-decoration: none;
        }

        .io-tab-holder {
            display: none;
            padding: 10px 0px;
            margin-top: 35px;
        }

        .io-tab-holder.active {
            display: block;
        }

        .io-tab.active {
            color: #000000;
        }

        .io-logo {
            position: absolute;
            right: 40px;
            top: 20px;
        }

        .io-table-form {}

        .io-table-form tr {}

        .io-table-form tr th {
            padding: 9px;
        }

        .io-table-form tr td {
            padding: 9px;
        }

        .io-table-form tr td input[type="text"] {
            border: solid 1px #efefef;
            background: #ffffff;
            box-shadow: none;
            font-size: 13px;
            color: #777777;
        }

        .regionalCompanyForm {
            display: flex;
            align-content: stretch;
            padding: 10px 10px;
            background: #ffffff;
            margin-bottom: 10px;
        }

        .regionalCompanyForm div {
            display: flex;
            align-items: center;
            margin-right: 20px;
        }

        .regionalCompanyForm label {
            padding-right: 15px;
        }

        .io-loader {
            display: none;
        }

        .wc-action-button-view-parcial::after {
            font-family: woocommerce !important;
            content: "\e000" !important;
        }

        .io_link {
            border: 1px solid #00B4FF;
            color: white !important;
            text-shadow: none;
            background: #00B4FF;
            background-color: rgba(0, 180, 255, 1);
        }

        #toplevel_page_invoicesonline-index.current a {
            background: #ffffff !important;
            color: #111111 !important;
        }
    </style>
<?php
}

function admin_enqueue_scripts_callback_jconline()
{
    wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
    wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', 'jquery', '4.1.0-rc.0');
    wp_enqueue_script('io-script', plugins_url('/assets/js/io.js', __FILE__), array('jquery'), null, true);
}
add_action('admin_enqueue_scripts', 'admin_enqueue_scripts_callback_jconline');

            add_action('wp_ajax_add_company_region', 'add_company_region');

            function add_company_region()
            {

                $currentRegions = get_option('IORegionalCompanies', '[]');
                $regions = json_decode($currentRegions, true);
                $companyRegions[] = sanitize_text_field($_POST['companyRegions']);
                $companyCode = sanitize_text_field($_POST['companyCode']);
                $io_username = sanitize_text_field($_POST['io_username']);
                $io_password = sanitize_text_field($_POST['io_password']);

                foreach ($companyRegions as $region) {
                    $regions[$region] = ['code' => $companyCode, 'username' => $io_username, 'password' => $io_password];
                }

                update_option('IORegionalCompanies', json_encode($regions));

                die();
            }

            add_action('wp_ajax_refresh_company_regions', 'refresh_company_regions');

            function refresh_company_regions()
            {

                $currentRegions = get_option('IORegionalCompanies', '[]');
                $regions = json_decode($currentRegions, true);
                $countries_obj   = new WC_Countries();
                $default_country = $countries_obj->get_base_country();

                $regionsBody = "";
                foreach ($regions as $regionkey => $companyCode) {
                    $stateName = WC()->countries->get_states($default_country)[$regionkey];
                    $regionsBody .= '<tr><td>' . $stateName . '</td><td>' . $companyCode['code'] . '</td><td>' . $companyCode['username'] . '</td><td><span id="' . $regionkey . '" class="removeRegion button grey">Remove Region</span></td></tr>';
                }
                echo wp_kses($regionsBody, [
                    'tr' => [],
                    'td' => [],
                    'span' => [
                        'id' => [],
                        'class' => []
                    ]
                ]);

                die();
            }

            add_action('wp_ajax_remove_company_region', 'remove_company_region');

            function remove_company_region()
            {

                $currentRegions = get_option('IORegionalCompanies', '[]');
                $regions = json_decode($currentRegions, true);
                $regionCode = sanitize_text_field($_POST['companyRegion']);

                unset($regions[$regionCode]);

                update_option('IORegionalCompanies', json_encode($regions));

                die();
            }

            add_action('admin_notices', 'jconline_admin_notice');

            function jconline_admin_notice()
            {
                global $current_user;
                $user_id = $current_user->ID;
                if (!get_user_meta($user_id, 'io_ignore_notice')) {
                    $message = '<div class="error"><p>';
                    $message .= 'Please set your tax settings in the Jobcards Online Settings page | <a href="?io_nag_ignore=0">Hide Notice</a>';
                    // printf(__('Please set your tax settings in the Jobcards Online Settings page | <a href="%1$s">Hide Notice</a>'), '?io_nag_ignore=0');
                    $message .= "</p></div>";
                    echo wp_kses($message, [
                        'div' => [
                            'class' => []
                        ],
                        'p' => [],
                        'a' => [
                            'href' => []
                        ]
                    ]);
                }
            }

            add_action('admin_init', 'jconline_nag_ignore');

            function jconline_nag_ignore()
            {
                global $current_user;
                $user_id = $current_user->ID;
                if (isset($_GET['io_nag_ignore']) && '0' == sanitize_text_field($_GET['io_nag_ignore'])) {
                    add_user_meta($user_id, 'io_ignore_notice', 'true', true);
                }
            }

            // Add your custom order status action button (for orders with "processing" status)
            add_filter('woocommerce_admin_order_actions', 'add_custom_order_status_actions_button', 100, 2);

            function add_custom_order_status_actions_button($actions, $order)
            {
                // Display the button for all orders that have a 'processing' status
                $order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
                $iostatus = get_post_meta($order_id, 'iostatus', true);
                if ($iostatus != 'Generated') {;
                    // Set the action button
                    $actions['parcial'] = array(
                        'url' => wp_nonce_url(admin_url('admin-ajax.php?action=io_generate_invoice&order_id=' . $order->get_id()), 'woocommerce-mark-order-status'),
                        'name' => __('Generate Invoice and Proof of Payment', 'woocommerce'),
                        'action' => "view-parcial", // keep "view" class for a clean button CSS
                    );
                }
                return $actions;
            }

            add_action('admin_head', 'add_custom_order_status_actions_button_css');

            function add_custom_order_status_actions_button_css()
            {
                $style = '<style>.view.parcial::after { font-family: woocommerce; content: "\e006" !important; }</style>';
                echo wp_kses($style, [
                    'style' => []
                ]);
            }

            add_action('wp_ajax_io_generate_invoice', 'generate_io_invoice');

            function generate_io_invoice()
            {

                $orderid = sanitize_text_field($_GET['order_id']);
                $order = new WC_Order($orderid);
                $clientId = get_post_meta($orderid, 'ClientID', true);

                $order = wc_get_order($orderid);
                $order_data = $order->get_data();
                $vouchertag = get_option('voucherchecktag');

                $order_total = $order->get_total();
                $payment_total = 0;

                $io = new InvoicesOnlineAPI();

                $order_state = ($order_data['shipping']['state'] == '') ? $order_data['billing']['state'] : $order_data['shipping']['state'];
                $regionalCompanies = get_option('IORegionalCompanies', '[]');
                $regions = json_decode($regionalCompanies, true);
                $customerRegion = $order_state;
                $setRegions = array_keys($regions);
                if (in_array($customerRegion, $setRegions)) {
                    $io->username = $regions[$customerRegion]['username'];
                    $io->password = $regions[$customerRegion]['password'];
                    $io->BusinessID = $regions[$customerRegion]['code'];
                } else {
                    $io->username = get_option('io_api_username');
                    $io->password = get_option('io_api_password');
                    $io->BusinessID = get_option('io_business_id');
                }

                // $io->username = get_option('io_api_username');
                // $io->password = get_option('io_api_password');
                // $io->BusinessID = get_option('io_business_id');

                $vouchetcheck = get_option('vouchercheck');
                if ($vouchetcheck) {
                    foreach ($order->get_items() as $line) {
                        if (has_term($vouchertag, 'product_tag', $line->get_product_id())) {
                            $paymentopts = array();
                            $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                            $paymentopts['Description'] = '';
                            $paymentopts['PaymentAmount'] = $line->get_total();
                            $paymentopts['reference_number'] = 'Online Purchase';
                            $payment_total = $payment_total + $line->get_total();
                            $addPayment = $io->CreateNewPayment($io->BusinessID, $clientId, $paymentopts);
                        }
                    }

                    $order_total = $order_total - $payment_total;
                }

                $creditOption = get_option('checkcredits');

                $proFormaInvNr = get_post_meta($orderid, 'io_proforma_invoice', true);

                $createInvoice = $io->ConvertProformaToInvoice(get_option('io_business_id'), $proFormaInvNr);
                $responseData = json_decode(wp_remote_retrieve_body($createInvoice), true);
                // $createInvoice = json_decode($createInvoice);

                update_post_meta($orderid, 'iostatus', 'Generated');

                if ($creditOption == 'yes') {
                    $balance = $io->GetClientBalance($clientId);
                    if ($balance['balance'] >= $order_total) {
                        // do Nothing
                    } else {
                        $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                        $paymentopts['Description'] = '';
                        $paymentopts['PaymentAmount'] = $order_total;
                        $paymentopts['reference_number'] = $createInvoice[3]->invoice_nr;
                        $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientId, $paymentopts);
                    }
                } else {
                    $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                    $paymentopts['Description'] = '';
                    $paymentopts['PaymentAmount'] = $order_total;
                    $paymentopts['reference_number'] = $createInvoice[3]->invoice_nr;
                    $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientId, $paymentopts);
                }

                $url = wp_get_referer() ? wp_get_referer() : admin_url('edit.php?post_type=shop_order');
                header("location:" . $url);

                //wp_die(); // this is required to terminate immediately and return a proper response
            }

            function generate_auto_io_invoice($orderid)
            {

                $io = new InvoicesOnlineAPI();

                $order = wc_get_order($orderid);
                $order_data = $order->get_data();
                $order_state = ($order_data['shipping']['state'] == '') ? $order_data['billing']['state'] : $order_data['shipping']['state'];
                $regionalCompanies = get_option('IORegionalCompanies', '[]');
                $regions = json_decode($regionalCompanies, true);
                $customerRegion = $order_state;
                $setRegions = array_keys($regions);
                if (in_array($customerRegion, $setRegions)) {
                    $io->username = $regions[$customerRegion]['username'];
                    $io->password = $regions[$customerRegion]['password'];
                    $io->BusinessID = $regions[$customerRegion]['code'];
                } else {
                    $io->username = get_option('io_api_username');
                    $io->password = get_option('io_api_password');
                    $io->BusinessID = get_option('io_business_id');
                }

                $proFormaInvNr = get_post_meta($orderid, 'io_proforma_invoice', true);

                $createInvoice = $io->ConvertProformaToInvoice(get_option('io_business_id'), $proFormaInvNr);

                $createInvoicefr = json_decode($createInvoice);

                return $createInvoice;
            }

            function jconline_new_order_column($columns)
            {
                $columns['IO'] = 'IO';
                return $columns;
            }

            add_filter('manage_edit-shop_order_columns', 'jconline_new_order_column');

            function jconline_sv_wc_cogs_add_order_profit_column_content($column)
            {
                global $post;

                if ('IO' === $column) {
                    $iostatus = get_post_meta($post->ID, 'iostatus', true);
                    if ($iostatus == 'Generated') {
                        $iostatus = "Invoice Generated";
                    }
                    echo esc_html($iostatus);
                }
            }

            add_action('manage_shop_order_posts_custom_column', 'jconline_sv_wc_cogs_add_order_profit_column_content');

            function rfvc_update_order_status($order_id)
            {

                $orderid = $order_id;
                $order = new WC_Order($orderid);
                $clientId = get_post_meta($orderid, 'ClientID', true);
                $gstatus = get_post_meta($orderid, 'iostatus', true);

                $order = wc_get_order($orderid);
                $vouchertag = get_option('voucherchecktag');

                $order_total = $order->get_total();
                $payment_total = 0;

                $io = new InvoicesOnlineAPI();
                $io->username = get_option('io_api_username');
                $io->password = get_option('io_api_password');
                $io->BusinessID = get_option('io_business_id');

                $vouchetcheck = get_option('vouchercheck');
                if ($vouchetcheck) {
                    foreach ($order->get_items() as $line) {
                        if (has_term($vouchertag, 'product_tag', $line->get_product_id())) {
                            $paymentopts = array();
                            $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                            $paymentopts['Description'] = '';
                            $paymentopts['PaymentAmount'] = $line->get_total();
                            $paymentopts['reference_number'] = 'Online Purchase';
                            $payment_total = $payment_total + $line->get_total();
                            $addPayment = $io->CreateNewPayment($io->BusinessID, $clientId, $paymentopts);
                        }
                    }

                    $order_total = $order_total - $payment_total;
                }

                $creditOption = get_option('checkcredits');

                $gstatus = get_post_meta($order_id, 'iostatus', true);

                if ($gstatus != 'Generated') {

                    $createInvoice = generate_auto_io_invoice($order_id);
                }

                if ($creditOption == 'yes') {
                    $balance = $io->GetClientBalance($clientId);
                    if ($balance['balance'] >= $order_total) {
                        // do Nothing, Client has Credits
                    } else {
                        $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                        $paymentopts['Description'] = '';
                        $paymentopts['PaymentAmount'] = $order_total;
                        $paymentopts['reference_number'] = $createInvoice[3]->invoice_nr;
                        $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientId, $paymentopts);
                    }
                } else {
                    $paymentopts['payment_method'] = get_post_meta($orderid, '_payment_method', true);
                    $paymentopts['Description'] = '';
                    $paymentopts['PaymentAmount'] = $order_total;
                    $paymentopts['reference_number'] = $createInvoice[3]->invoice_nr;
                    $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientId, $paymentopts);
                }

                return;
            }

            function rfvc_update_order_status_org($order_id)
            {

                $io = new InvoicesOnlineAPI();

                $io->username = get_option('io_api_username');
                $io->password = get_option('io_api_password');
                $io->BusinessID = get_option('io_business_id');

                $order = new WC_Order($order_id);

                $clientId = get_post_meta($order_id, 'ClientID', true);

                $gstatus = get_post_meta($order_id, 'iostatus', true);

                if ($gstatus != 'Generated') {

                    generate_auto_io_invoice($order_id);
                }

                $paymentopts['payment_method'] = $order->get_payment_method();

                $paymentopts['Description'] = '';
                $paymentopts['PaymentAmount'] = $order->get_total();
                $paymentopts['reference_number'] = $order->ID;

                $addPayment = $io->CreateNewPayment(get_option('io_business_id'), $clientId, $paymentopts);

                return $order_status;
            }

            function jconline_create_vat()
            {
                $select_options = array('yes', 'no');
                ?>
    <h2>VAT</h2>
    <table class="form-table">
        <tbody>
            <tr valign="top">
                <th scope="row">Woocommerce Prices Include VAT?</th>
                <td>
                    <?php $vatapplies = get_option('vatapplies'); ?>
                    <select name="vatapplies" style="padding:0px">
                        <?php
                        foreach ($select_options as $ar) {
                            if ($vatapplies == $ar) {
                                echo '<option value="' . esc_html($ar) . '" selected="selected">' . esc_html($ar) . '</option>';
                            } else {
                                echo '<option value="' . esc_html($ar) . '">' . esc_html($ar) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
            <tr valign="top">
                <th scope="row">Must Jobcards Online Apply vat to prices?</th>
                <td>
                    <?php $amountsincludevat = get_option('amountsincludevat'); ?>
                    <select name="amountsincludevat" style="padding:0px">
                        <?php
                        foreach ($select_options as $ar2) {
                            if ($amountsincludevat == $ar2) {
                                echo '<option value="' . esc_html($ar2) . '" selected="selected">' . esc_html($ar2) . '</option>';
                            } else {
                                echo '<option value="' . esc_html($ar2) . '">' . esc_html($ar2) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </td>
            </tr>
        </tbody>
    </table>
<?php
            }

            function jconline_save_vat()
            {
                update_option('vatapplies', sanitize_text_field($_POST['vatapplies']));
                update_option('amountsincludevat', sanitize_text_field($_POST['amountsincludevat']));
            }
?>