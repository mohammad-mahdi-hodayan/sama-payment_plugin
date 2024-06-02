<?php

if (!defined('ABSPATH')) {
    exit;
}
add_filter('woocommerce_checkout_fields', 'add_custom_checkout_field');
function add_custom_checkout_field($fields)
{
    $fields['billing']['sama_payment_type_f'] = array(
        'type' => 'text',
        'label' => __('sama_payment_type_label'),
        'required' => true,
        'class' => array('form-row-wide'),
    );
    return $fields;
}
add_action('woocommerce_checkout_update_order_meta', 'save_custom_checkout_field');
function save_custom_checkout_field($order_id)
{
    if (!empty($_POST['sama_payment_type_f'])) {
        update_post_meta($order_id, 'sama_payment_type_label', $_POST['sama_payment_type_f']);
    }
}
function Load_Sama_Gateway()
{

    if (!function_exists('Woocommerce_Add_Sama_Gateway') && class_exists('WC_Payment_Gateway') && !class_exists('WC_Samaplugin')) {


        add_filter('woocommerce_payment_gateways', 'Woocommerce_Add_Sama_Gateway');

        function Woocommerce_Add_Sama_Gateway($methods)
        {
            $methods[] = 'WC_Samaplugin';
            return $methods;
        }

        add_filter('woocommerce_currencies', 'add_IR_currency');

        function add_IR_currency($currencies)
        {
            $currencies['IRR'] = __('ریال', 'woocommerce');
            $currencies['IRT'] = __('تومان', 'woocommerce');
            $currencies['IRHR'] = __('هزار ریال', 'woocommerce');
            $currencies['IRHT'] = __('هزار تومان', 'woocommerce');

            return $currencies;
        }

        add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);

        function add_IR_currency_symbol($currency_symbol, $currency)
        {
            switch ($currency) {
                case 'IRR':
                    $currency_symbol = 'ریال';
                    break;
                case 'IRT':
                    $currency_symbol = 'تومان';
                    break;
                case 'IRHR':
                    $currency_symbol = 'هزار ریال';
                    break;
                case 'IRHT':
                    $currency_symbol = 'هزار تومان';
                    break;
            }
            return $currency_symbol;
        }

        class WC_Samaplugin extends WC_Payment_Gateway
        {


            private $api_key;
            private $failedMassage;
            private $successMassage;

            public function __construct()
            {

                $this->id = 'WC_Samaplugin';
                $this->method_title = __('درگاه پرداخت سما', 'woocommerce');
                $this->method_description = __('تنظیمات درگاه پرداخت سما برای افزونه فروشگاه ساز ووکامرس', 'woocommerce');
                $this->icon = apply_filters('WC_Samaplugin_logo', WP_PLUGIN_URL . '/' . plugin_basename(__DIR__) . '/assets/images/logo.png');
                $this->has_fields = false;

                $this->init_form_fields();
                $this->init_settings();

                $this->title = $this->settings['title'];
                $this->description = $this->settings['description'];

                $this->api_key = $this->settings['api_key'];

                $this->successMassage = $this->settings['success_massage'];
                $this->failedMassage = $this->settings['failed_massage'];

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id . '', array($this, 'Send_to_Sama_Gateway'));
                add_action('woocommerce_api_' . strtolower(get_class($this)) . '', array($this, 'Return_from_Sama_Gateway'));
            }

            public function init_form_fields()
            {
                $this->form_fields = apply_filters(
                    'WC_Samaplugin_Config',
                    array(
                        'base_config' => array(
                            'title' => __('تنظیمات پایه ای', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'enabled' => array(
                            'title' => __('فعالسازی/غیرفعالسازی', 'woocommerce'),
                            'type' => 'checkbox',
                            'label' => __('فعالسازی درگاه سما', 'woocommerce'),
                            'description' => __('برای فعالسازی درگاه پرداخت سما باید چک باکس را تیک بزنید', 'woocommerce'),
                            'default' => 'yes',
                            'desc_tip' => true,
                        ),
                        'title' => array(
                            'title' => __('عنوان درگاه', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('عنوان درگاه که در طی خرید به مشتری نمایش داده میشود', 'woocommerce'),
                            'default' => __('درگاه پرداخت سما', 'woocommerce'),
                            'desc_tip' => true,
                        ),
                        'description' => array(
                            'title' => __('توضیحات درگاه', 'woocommerce'),
                            'type' => 'text',
                            'desc_tip' => true,
                            'description' => __('توضیحاتی که در طی عملیات پرداخت برای درگاه نمایش داده خواهد شد', 'woocommerce'),
                            'default' => __('پرداخت امن به وسیله کلیه کارت های عضو شتاب از طریق درگاه سما', 'woocommerce')
                        ),
                        'account_config' => array(
                            'title' => __('تنظیمات حساب سما', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'api_key' => array(
                            'title' => __('کلید API', 'woocommerce'),
                            'type' => 'text',
                            'description' => __('مرچنت کد درگاه سما', 'woocommerce'),
                            'default' => '',
                            'desc_tip' => true
                        ),
                        'payment_config' => array(
                            'title' => __('تنظیمات عملیات پرداخت', 'woocommerce'),
                            'type' => 'title',
                            'description' => '',
                        ),
                        'success_massage' => array(
                            'title' => __('پیام پرداخت موفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {transaction_id} برای نمایش کد رهگیری (توکن) سما استفاده نمایید .', 'woocommerce'),
                            'default' => __('با تشکر از شما . سفارش شما با موفقیت پرداخت شد .', 'woocommerce'),
                        ),
                        'failed_massage' => array(
                            'title' => __('پیام پرداخت ناموفق', 'woocommerce'),
                            'type' => 'textarea',
                            'description' => __('متن پیامی که میخواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد نمایید . همچنین می توانید از شورت کد {fault} برای نمایش دلیل خطای رخ داده استفاده نمایید . این دلیل خطا از سایت سما ارسال میگردد .', 'woocommerce'),
                            'default' => __('پرداخت شما ناموفق بوده است . لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید .', 'woocommerce'),
                        ),
                    )
                );
            }

            public function process_payment($order_id)
            {
                $order = new WC_Order($order_id);
                return array(
                    'result' => 'success',
                    'redirect' => $order->get_checkout_payment_url(true)
                );
            }

            /**
             * @param $action (PaymentRequest, )
             * @param $params string
             *
             * @return mixed
             */
            public function SendRequestToSama($action, $params)
            {
                try {
                    /*
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                        'price' => $amount,
                        'client_id' => $client_id,
                        'buyer_phone' => empty($order->get_shipping_phone())
                            ? $order->get_billing_phone()
                            : $order->get_shipping_phone(),
                        'callback_url' => add_query_arg(
                            'wc_order',
                            $order_id,
                            WC()->api_request_url('wc_gsama')
                        ),
                    ]), );*/
                    $ch = curl_init('https://stage.app.sama.ir/api/stores/services/deposits/' . $action . '/');
                    curl_setopt($ch, CURLOPT_USERAGENT, 'Sama Rest Api v1');
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt(
                        $ch,
                        CURLOPT_HTTPHEADER,
                        array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen($params),
                            'Authorization: Api-Key ' . $this->api_key
                        )
                    );
                    $result = curl_exec($ch);
                    return json_decode($result, true);
                } catch (Exception $ex) {
                    return false;
                }
            }

            public function Send_to_Sama_Gateway($order_id)
            {


                global $woocommerce;
                $woocommerce->session->order_id_Sama = $order_id;
                $order = new WC_Order($order_id);
                $currency = $order->get_currency();
                $currency = apply_filters('WC_Samaplugin_Currency', $currency, $order_id);


                $form = '<form action="" method="POST" class="Sama-checkout-form" id="Sama-checkout-form">
						<input type="submit" name="Sama_submit" class="button alt" id="Sama-payment-button" value="' . __('پرداخت', 'woocommerce') . '"/>
						<a class="button cancel" href="' . wc_get_checkout_url() . '">' . __('بازگشت', 'woocommerce') . '</a>
					 </form><br/>';
                $form = apply_filters('WC_Samaplugin_Form', $form, $order_id, $woocommerce);

                do_action('WC_Samaplugin_Gateway_Before_Form', $order_id, $woocommerce);
                echo wp_kses(
                    $form,
                    array(
                        'form' => array('action', 'method', 'class', 'id'),
                        'input' => array('type', 'name', 'class', 'id', 'value'),
                        'a' => array('class', 'href')

                    )
                );
                do_action('WC_Samaplugin_Gateway_After_Form', $order_id, $woocommerce);


                $Amount = intval($order->get_total());
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                $strToLowerCurrency = strtolower($currency);

                if (strtolower($currency) === strtolower('IRHT')) {
                    $Amount *= 1000;
                } else if (strtolower($currency) === strtolower('IRHR')) {
                    $Amount *= 100;
                }
                /*else if (strtolower($currency) === strtolower('IRR')) {
                   $Amount /= 10;
               }*/


                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_after_check_currency', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_irt', $Amount, $currency);
                $Amount = apply_filters('woocommerce_order_amount_total_Sama_gateway', $Amount, $currency);

                $CallbackUrl = add_query_arg('wc_order', $order_id, WC()->api_request_url('WC_Samaplugin'));

                $products = array();
                $order_items = $order->get_items();
                foreach ($order_items as $product) {
                    $products[] = $product['name'] . ' (' . $product['qty'] . ') ';
                }
                $products = implode(' - ', $products);
                $client_id = sha1(
                    $order->get_customer_id() .
                    '_' .
                    $order_id .
                    '_' .
                    $Amount .
                    '_' .
                    time()
                );
                $Description = 'خرید به شماره سفارش : ' . $order->get_order_number() . ' | خریدار : ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $Mobile = $order->get_billing_phone();
                $Email = $order->get_billing_email();
                $Payer = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
                $ResNumber = (int) $order->get_order_number();

                //Hooks for iranian developer
                $Description = apply_filters('WC_Samaplugin_Description', $Description, $order_id);
                $Mobile = apply_filters('WC_Samaplugin_Mobile', $Mobile, $order_id);

                $Email = apply_filters('WC_Samaplugin_Email', $Email, $order_id);
                $Payer = apply_filters('WC_Samaplugin_Paymenter', $Payer, $order_id);
                $ResNumber = apply_filters('WC_Samaplugin_ResNumber', $ResNumber, $order_id);
                do_action('WC_Samaplugin_Gateway_Payment', $order_id, $Description, $Mobile);
                $Email = !filter_var($Email, FILTER_VALIDATE_EMAIL) === false ? $Email : '';
                $sama_payment_type_f_value = get_post_meta($order_id, 'sama_payment_type_label', true);
                // if == 1 it means that user selected پرداخت اقساطی and else will return nothing, we will use it in curl data.
                echo "input data is : " . $sama_payment_type_f_value . "<br>";

                if (preg_match('/^(\+989|989|\+9809|9809)([0-9]{9})$/i', $Mobile, $matches)) {
                    $Mobile = '09' . $matches[2];
                } elseif (preg_match('/^9[0-7]{1}[0-9]{8}$/i', $Mobile)) {
                    $Mobile = preg_replace('/^9/', '0$0', $Mobile);
                } else {
                    $Mobile = preg_match('/^09[0-7]{1}[0-9]{8}$/i', $Mobile) ? $Mobile : '';
                }

                if (strtolower($currency) === strtolower('IRR')) {

                    $data = array(
                        'price' => $Amount,
                        'client_id' => $client_id,
                        'callback_url' => $CallbackUrl."&client_id=".$client_id,
                        'description' => $Description,
                        "currency" => "IRR",
                        "metadata" => ["order_id" => "سفارش شماره $order_id"]
                    );
                    if ($sama_payment_type_f_value == "pardakht_etebari") {
                        $data['payment_type'] = "installment";
                    }
                    if ($Mobile) {
                        $data['buyer_phone'] = $Mobile;
                    }
                    if ($Email) {
                        $data['metadata']["email"] = $Email;
                    }
                } else if (
                    ($strToLowerCurrency === strtolower('IRT')) ||
                    ($strToLowerCurrency === strtolower('TOMAN')) ||
                    $strToLowerCurrency === strtolower('Iran TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                    $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                    $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                    $strToLowerCurrency === strtolower('IRHT') ||
                    $strToLowerCurrency === strtolower('تومان') ||
                    $strToLowerCurrency === strtolower('IRHR') ||
                    $strToLowerCurrency === strtolower(
                        'تومان ایران'
                    )
                ) {
                    $data = array(
                        'price' => $Amount,
                        'client_id' => $client_id,
                        'callback_url' => $CallbackUrl."&client_id=".$client_id,
                        'description' => $Description,
                        "currency" => "IRT",
                        "metadata" => ["order_id" => "سفارش شماره $order_id"]
                    );
                    if ($sama_payment_type_f_value == "pardakht_etebari") {
                        $data['payment_type'] = "installment";
                    }
                    if ($Mobile) {
                        $data['buyer_phone'] = $Mobile;
                    }
                    if ($Email) {
                        $data['metadata']["email"] = $Email;
                    }
                }
                $result = $this->SendRequestToSama('guaranteed', json_encode($data));
                //$result = false;
                /*if($sama_payment_type_f_value == "pardakht_zemanati"){
                    echo "پرداخت قسطی انتخاب شده است";
                }else{
                    echo "پرداخت قسطی انتخاب نشده است";
                }*/
                // echo "posted info are : ";
                // print_r($_POST);
                // echo "call back is : ";
                // echo $CallbackUrl;

                if ($result === false) {
                    echo esc_html('cURL Error #:');
                } else if (isset($result['uid'])) {
                    header('Location: ' . $result['web_view_link']);
                    exit;
                } else {

                    $Message = ' تراکنش ناموفق بود- کد خطا : ' . $result['errors']['code'];

                    $Fault = '';
                }

                if (!empty($Message) && $Message) {

                    $Note = sprintf(__('خطا در هنگام ارسال به بانک : %s', 'woocommerce'), $Message);
                    $Note = apply_filters('WC_Samaplugin_Send_to_Gateway_Failed_Note', $Note, $order_id, $Fault);
                    $order->add_order_note($Note);


                    $Notice = sprintf(__('در هنگام اتصال به بانک خطای زیر رخ داده است : <br/>%s', 'woocommerce'), $Message);
                    $Notice = apply_filters('WC_Samaplugin_Send_to_Gateway_Failed_Notice', $Notice, $order_id, $Fault);
                    if ($Notice) {
                        wc_add_notice($Notice, 'error');
                    }

                    do_action('WC_Samaplugin_Send_to_Gateway_Failed', $order_id, $Fault);
                }
            }


            public function Return_from_Sama_Gateway()
            {

                $client_id = $_GET["client_id"];

                global $woocommerce;


                if (isset($_GET['wc_order'])) {
                    $order_id = sanitize_text_field($_GET['wc_order']);
                }

                if ($order_id) {

                    $order = new WC_Order($order_id);
                    $currency = $order->get_currency();
                    $currency = apply_filters('WC_Samaplugin_Currency', $currency, $order_id);

                    if ($order->status !== 'completed') {

                        $api_key = $this->api_key;

                        if (isset($client_id)) {

                            $MerchantID = $this->api_key;
                            $Amount = intval($order->get_total());
                            $Amount = apply_filters('woocommerce_order_amount_total_IRANIAN_gateways_before_check_currency', $Amount, $currency);
                            $strToLowerCurrency = strtolower($currency);
                            if (
                                ($strToLowerCurrency === strtolower('IRT')) ||
                                ($strToLowerCurrency === strtolower('TOMAN')) ||
                                $strToLowerCurrency === strtolower('Iran TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian-TOMAN') ||
                                $strToLowerCurrency === strtolower('Iran_TOMAN') ||
                                $strToLowerCurrency === strtolower('Iranian_TOMAN') ||
                                $strToLowerCurrency === strtolower('IRHT') ||
                                $strToLowerCurrency === strtolower('تومان') ||
                                $strToLowerCurrency === strtolower('IRHR') ||
                                $strToLowerCurrency === strtolower(
                                    'تومان ایران'
                                )
                            ) if (strtolower($currency) === strtolower('IRHT')) {
                                $Amount *= 1000;
                            } else if (strtolower($currency) === strtolower('IRHR')) {
                                $Amount *= 100;
                            }
                            //else if (strtolower($currency) === strtolower('IRR')) {
                            //$Amount /= 10;
                            //}

                            $Authority = sanitize_text_field($_GET['Authority']);

                            $request_id = $_GET['request_id'];
                            $data = array("client_id" => $client_id, "request_id" => $request_id);
                            $result = $this->SendRequestToSama('guaranteed/payment/verify', json_encode($data));

                            if ($result['payment']['is_failed'] == false && $result['is_paid'] == true && $result['payment']['result_code'] == 0) {
                                $Status = 'completed';
                                $Transaction_ID = $result['payment']['id'];
                                $Fault = '';
                                $Message = '';
                                
                            } elseif ($result['code'] == 101) {
                                // این حلقه اتفاق نخواهد افتاد و در حال حاضر قابلیت تایید درخواست تکراری توسط ای پی آی وجود ندارد اما برای آپدیت های بعدی قابل استفاده است
                                $Message = 'این تراکنش قلا تایید شده است';
                                $Transaction_ID = $result['payment']['id'];
                                $Notice = wpautop(wptexturize($Message));
                                wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                                exit;
                            } else {
                                $Status = 'failed';
                                $Fault = $result['errors']['code'];
                                $Message = 'تراکنش ناموفق بود';
                            }
                        } else {
                            $Status = 'failed';
                            $Fault = '';
                            $Message = 'تراکنش انجام نشد .';
                        }
                        if ($Status === 'completed' && isset($Transaction_ID) && $Transaction_ID !== 0) {
                            update_post_meta($order_id, '_transaction_id', $Transaction_ID);
                            $order->update_status( 'completed' );


                            $order->payment_complete($Transaction_ID);
                            $woocommerce->cart->empty_cart();

                            $Note = sprintf(__('پرداخت موفقیت آمیز بود .<br/> کد رهگیری : %s', 'woocommerce'), $Transaction_ID);
                            $Note = apply_filters('WC_Samaplugin_Return_from_Gateway_Success_Note', $Note, $order_id, $Transaction_ID);
                            if ($Note)
                                $order->add_order_note($Note, 1);


                            $Notice = wpautop(wptexturize($this->successMassage));

                            $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                            $Notice = apply_filters('WC_Samaplugin_Return_from_Gateway_Success_Notice', $Notice, $order_id, $Transaction_ID);
                            if ($Notice)
                                wc_add_notice($Notice, 'success');

                            do_action('WC_Samaplugin_Return_from_Gateway_Success', $order_id, $Transaction_ID);

                            wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                            exit;
                        }
                        if (($Transaction_ID && ($Transaction_ID != 0))) {
                            $tr_id = ('<br/>توکن : ' . $Transaction_ID);
                        } else {
                            $tr_id = '';
                        }

                        $Note = sprintf(__('خطا در هنگام بازگشت از بانک : %s %s', 'woocommerce'), $Message, $tr_id);

                        $Note = apply_filters('WC_Samaplugin_Return_from_Gateway_Failed_Note', $Note, $order_id, $Transaction_ID, $Fault);
                        if ($Note) {
                            $order->add_order_note($Note, 1);
                        }

                        $Notice = wpautop(wptexturize($this->failedMassage));

                        $Notice = str_replace(array('{transaction_id}', '{fault}'), array($Transaction_ID, $Message), $Notice);
                        $Notice = apply_filters('WC_Samaplugin_Return_from_Gateway_Failed_Notice', $Notice, $order_id, $Transaction_ID, $Fault);
                        if ($Notice) {
                            wc_add_notice($Notice, 'error');
                        }

                        do_action('WC_Samaplugin_Return_from_Gateway_Failed', $order_id, $Transaction_ID, $Fault);

                        wp_redirect(wc_get_checkout_url());
                        exit;
                    }

                    $Transaction_ID = get_post_meta($order_id, '_transaction_id', true);

                    $Notice = wpautop(wptexturize($this->successMassage));

                    $Notice = str_replace('{transaction_id}', $Transaction_ID, $Notice);

                    $Notice = apply_filters('WC_Samaplugin_Return_from_Gateway_ReSuccess_Notice', $Notice, $order_id, $Transaction_ID);
                    if ($Notice) {
                        wc_add_notice($Notice, 'success');
                    }

                    do_action('WC_Samaplugin_Return_from_Gateway_ReSuccess', $order_id, $Transaction_ID);

                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $Fault = __('شماره سفارش وجود ندارد .', 'woocommerce');
                $Notice = wpautop(wptexturize($this->failedMassage));
                $Notice = str_replace('{fault}', $Fault, $Notice);
                $Notice = apply_filters('WC_Samaplugin_Return_from_Gateway_No_Order_ID_Notice', $Notice, $order_id, $Fault);
                if ($Notice) {
                    wc_add_notice($Notice, 'error');
                }

                do_action('WC_Samaplugin_Return_from_Gateway_No_Order_ID', $order_id, '0', $Fault);

                
                
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        }
    }
}

add_action('init', 'Load_Sama_Gateway', 0);