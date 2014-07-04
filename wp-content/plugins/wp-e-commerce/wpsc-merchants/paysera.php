<?php

    if (session_id() == "") session_start();

    $nzshpcrt_gateways[$num]['name']             = 'Webtopay.com / Mokejimai.lt';
    $nzshpcrt_gateways[$num]['internalname']     = 'webtopay_certified';
    $nzshpcrt_gateways[$num]['function']         = 'gateway_webtopay_certified';
    $nzshpcrt_gateways[$num]['form']             = "form_webtopay_certified";
    $nzshpcrt_gateways[$num]['submit_function']  = "submit_webtopay_certified";

    require_once('libwebtopay/WebToPay.php');


    function webtopayCallback() {

        global $wpdb;

if($_REQUEST['data']){
        try {
            $response = WebToPay::checkResponse($_REQUEST, array(
                'projectid' 	=> get_option('webtopay_project_id'),
                'sign_password' => get_option('webtopay_certified_sign'),
            ));
        } catch (Exception $e) {
            //exit( get_class($e).': '.$e->getMessage());
        }

        if (isset($response['projectid'])) {

            if ($response['status'] == '1') {

            $Order = $wpdb->get_row("SELECT * FROM " . WPSC_TABLE_PURCHASE_LOGS . " WHERE `sessionid` = ".$wpdb->escape($response['orderid']), OBJECT);
            $currency  = $wpdb->get_var("SELECT `code` FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE `id` = ".get_option( 'currency_type' ));            
            
            if($response['amount'] != ($Order->totalprice*100)) {
                exit('Bad amount: '.$response['amount']);
            }
            if($response['currency'] != $currency) {
                exit('Bad currency: '.$response['currency']);
            }

            
            /* Order status update */
        	$wpdb->query("UPDATE " . WPSC_TABLE_PURCHASE_LOGS . " SET `processed` = '3', `date` = '".time()."' WHERE `sessionid` = ".$wpdb->escape($response['orderid'])." LIMIT 1");

        	exit('OK');
        }} }
    }


    function gateway_webtopay_certified($seperator, $sessionid) {

        global $wpdb, $wpsc_cart;

        $formFields = "SELECT `id`, `unique_name` FROM " . WPSC_TABLE_CHECKOUT_FORMS ." WHERE `active` = 1";
        $result = $wpdb->get_results($formFields);

        $formated = array();

        foreach($result as $item) {;
            $formated[$item->id] = $item->unique_name;
        }

        $userData = array(
        	'country'     => '',
    		'firstname'   => '',
            'lastname'    => '',
            'email'       => '',
            'street'      => '',
            'city'        => '',
            'state'       => '',
            'zip'    	  => '',
            'countrycode' => '',
        );

        foreach($_POST['collected_data'] as $key => $value) {
            ($formated[$key] == 'billingcountry')   ? $userData['country'] = $value[0] : $userData['country'] = $userData['country'];
            ($formated[$key] == 'billingfirstname') ? $userData['firstname'] = $value : $userData['firstname'] = $userData['firstname'];
            ($formated[$key] == 'billinglastname')  ? $userData['lastname'] = $value : $userData['lastname'] = $userData['lastname'];
            ($formated[$key] == 'billingemail')     ? $userData['email'] = $value : $userData['email'] = $userData['email'];
            ($formated[$key] == 'billingaddress')   ? $userData['street'] = $value : $userData['street'] = $userData['street'];
            ($formated[$key] == 'billingcity')      ? $userData['city'] = $value : $userData['city'] = $userData['city'];
            ($formated[$key] == 'billingstate')     ? $userData['state'] = $value : $userData['state'] = $userData['state'];;
            ($formated[$key] == 'billingpostcode')  ? $userData['zip'] = $value : $userData['zip'] = $userData['zip'];
            ($formated[$key] == 'billingcountry')   ? $userData['countrycode'] = $value[0] : $userData['countrycode'] = $userData['countrycode'];
        }

        $_SESSION['webtopayexpresssessionid'] = $sessionid;


        $language  = $wpdb->get_var("SELECT `option_value` FROM $wpdb->options WHERE `option_name` = 'base_country'");
        $currency  = $wpdb->get_var("SELECT `code` FROM " . WPSC_TABLE_CURRENCY_LIST . " WHERE `id` = ".get_option( 'currency_type' ));


        $_GET['sessionid'] = $sessionid;
        $_GET['gateway']   = 'webtopay';

        if(get_option('permalink_structure') != '') {
            $seperator ="?";
        } else {
            $seperator ="&";
        }

        $acceptURL    = get_option('transact_url') . $seperator . "sessionid={$_GET['sessionid']}&gateway={$_GET['gateway']}";
        $cancelURL    = get_option('checkout_url');
        $callbackURL  = get_option('siteurl') . '/';

        $purchase_log_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_PURCHASE_LOGS."` WHERE `sessionid`= %s LIMIT 1", $_SESSION['webtopayexpresssessionid'] );
        $purchase_log = $wpdb->get_results($purchase_log_sql,ARRAY_A) ;

        $cart_sql = "SELECT * FROM `".WPSC_TABLE_CART_CONTENTS."` WHERE `purchaseid`='".$purchase_log[0]['id']."'";
        $cart = $wpdb->get_results($cart_sql,ARRAY_A) ;



$i = 1;
        foreach($cart as $item)
        {
            $product_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `" . $wpdb->posts . "` WHERE `id`= %d LIMIT 1", $item['prodid'] ), ARRAY_A );
            $product_data = $product_data[0];
            $variation_count = count($product_variations);

            //Does this even still work in 3.8? We're not using this table.
            $variation_sql = $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_CART_ITEM_VARIATIONS."` WHERE `cart_id` = %d", $item['id'] );
            $variation_data = $wpdb->get_results( $variation_sql, ARRAY_A );
            $variation_count = count($variation_data);

            if($variation_count >= 1)
            {
                $variation_list = " (";
                $j = 0;

                foreach($variation_data as $variation)
                {
                    if($j > 0)
                    {
                        $variation_list .= ", ";
                    }
                    $value_id = $variation['venue_id'];
                    $value_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM `".WPSC_TABLE_VARIATION_VALUES."` WHERE `id`= %d LIMIT 1", $value_id ), ARRAY_A);
                    $variation_list .= $value_data[0]['name'];
                    $j++;
                }
                $variation_list .= ")";
            }
            else
            {
                $variation_list = '';
            }

            $local_currency_productprice = $item['price'];

            $local_currency_shipping = $item['pnp'];


            $chronopay_currency_productprice = $local_currency_productprice;
            $chronopay_currency_shipping = $local_currency_shipping;

            $data['item_name_'.$i] = $product_data['name'].$variation_list;
            $data['amount_'.$i] = number_format(sprintf("%01.2f", $chronopay_currency_productprice),$decimal_places,'.','');
            $data['quantity_'.$i] = $item['quantity'];
            $data['item_number_'.$i] = $product_data['id'];

            if($item['donation'] !=1)
            {
                $all_donations = false;
                $data['shipping_'.$i] = number_format($chronopay_currency_shipping,$decimal_places,'.','');
                $data['shipping2_'.$i] = number_format($chronopay_currency_shipping,$decimal_places,'.','');
            }
            else
            {
                $data['shipping_'.$i] = number_format(0,$decimal_places,'.','');
                $data['shipping2_'.$i] = number_format(0,$decimal_places,'.','');
            }

            if($product_data['no_shipping'] != 1) {
                $all_no_shipping = false;
            }


            $total_price = $total_price + ($data['amount_'.$i] * $data['quantity_'.$i]);

            if( $all_no_shipping != false )
                $total_price = $total_price + $data['shipping_'.$i] + $data['shipping2_'.$i];

            $i++;
        }
        $base_shipping = $purchase_log[0]['base_shipping'];
        if(($base_shipping > 0) && ($all_donations == false) && ($all_no_shipping == false))
        {
            $data['handling_cart'] = number_format($base_shipping,$decimal_places,'.','');
            $total_price += number_format($base_shipping,$decimal_places,'.','');
        }

        $total_price = $wpsc_cart->total_price;



        $lng = array('LT'=>'LIT', 'LV'=>'LAV', 'EE'=>'EST', 'RU'=>'RUS', 'DE'=>'GER', 'PL'=>'POL');
        $amount = $cart['price']*$cart['quantity'] + $cart->base_shipping;

        $dat = array(
            'projectid'	    => get_option('webtopay_project_id'),
            'sign_password' => get_option('webtopay_certified_sign'),
            'orderid'       => $sessionid,
            'amount'        => round($total_price * 100),

            'currency'      => $currency,
            'lang'          => (isset($lng[$language->language])?$lng[$language->language]:'ENG'),

            'accepturl'	    => $acceptURL,
            'cancelurl'	    => $cancelURL,
            'callbackurl'   => $callbackURL,

            'country'       => $userData['country'],
            'p_firstname'   => $userData['firstname'],
            'p_lastname'    => $userData['lastname'],
            'p_email'       => $userData['email'],
            'p_street'      => $userData['street'],
            'p_city'        => $userData['city'],
            'p_state'       => $userData['state'],
            'p_zip'    	    => $userData['zip'],
            'p_countrycode' => $userData['countrycode'],
            'test'          => get_option('webtopay_certified_test'),
        );
        try {
            $request = WebToPay::buildRequest($dat);

        } catch (WebToPayException $e) {
            exit( $e->getMessage() );
        }


        $form = '';

        foreach ($request as $key => $value) {
            $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '" />';
        }

        $output = '<html>
            <head>
                <title></title>
            </head>
            <body onload=\'document.getElementById("webtopay_form").submit();\'>
                <form action="' . WebToPay::PAY_URL . '" id="webtopay_form" method="post">'.$form.'</form>
            </body>
        </html>';

        print($output);
	die();
    }

    function submit_webtopay_certified() {

        if($_POST['webtopay_project_id'] != null) {
            update_option('webtopay_project_id', $_POST['webtopay_project_id']);
        }

        if($_POST['webtopay_certified_sign'] != null) {
            update_option('webtopay_certified_sign', $_POST['webtopay_certified_sign']);
        }

        if($_POST['webtopay_certified_test'] != null) {
            update_option('webtopay_certified_test', $_POST['webtopay_certified_test']);
        }

        return true;
    }

    function form_webtopay_certified() {


        $selectOptions = '';

        if(get_option('webtopay_certified_test') == 1) {
            $selectOptions = '<option selected="true" value="1" >Enabled</option><option value="0" >Disabled</option>';
        } else {
            $selectOptions = '<option value="1" >Enabled</option><option value="0" selected="true" >Disabled</option>';
        }

        $output = '
        <tr>
			<td nowrap>'.__('Project ID:', 'wpsc').'</td>
          	<td>
              	<input type="text" size="10" value="'.get_option('webtopay_project_id').'" name="webtopay_project_id" />
              	<br>
            	<span class="small description">'.__('Your webtopay.com project ID:', 'wpsc').'</span>
          	</td>
        </tr>
        <tr>
            <td nowrap>'.__('Project password:', 'wpsc').'</td>
            <td>
                <input type="text" size="10" value="'.get_option('webtopay_certified_sign').'" name="webtopay_certified_sign" />
                <br>
                <span class="small description">'.__('Your webtopay.com project password:', 'wpsc').'</span>
            </td>
        </tr>
        <tr>
            <td nowrap>'.__('Test mode:', 'wpsc').'</td>
            <td>
                <select name="webtopay_certified_test">'.$selectOptions.'</select>
                <br>
                <span class="small description">'.__('Toggle test payments', 'wpsc').'</span>
            </td>
        </tr>';

        return $output;
    }

    add_action('init', 'webtopayCallback');
?>

