<?php

namespace Opencart\Catalog\Model\Extension\Getepay\Payment;

use Opencart\System\Engine\Model;

class Getepay extends Model

//class ModelExtensionPaymentGetepay extends Model 
{

    //public function getMethod($address, $total) 
    public function getMethods($address, $total = null)
    {
        $this->load->language('extension/getepay/payment/getepay');

        $method_data = array();

        $option_data['getepay'] = [
            'code' => 'getepay.getepay',
            'name' => $this->language->get('text_title'),
        ];
        
        $method_data = array();

        $method_data = array(
            'code'       => 'getepay',
            'name'      => $this->language->get('text_title'),
            'terms'      => '',
            'sort_order' => $this->config->get('payment_getepay_sort_order'),
            'option'     => $option_data,
        );

        return $method_data;
    }

    function pagePay($builder,$config) {
        date_default_timezone_set('Asia/Calcutta');
        $date = date('D M d H:i:s') . ' IST ' . date('Y');
        //$returnUrl = HTTPS_SERVER . "payment_callback/getepay";
        //$callback = HTTPS_SERVER . 'catelog/model/extension/getepay/payment/getepay/callback';
        // print_r($this->url->link('checkout/success'));exit;
        
        $request=array(
            "mid"=>$this->config->get('payment_getepay_key_mid'),
            "amount"=>$builder["total_amount"],
            "merchantTransactionId"=>$builder["order_id"],
            "transactionDate"=>date("Y-m-d H:i:s"),
            "terminalId"=>$this->config->get('payment_getepay_key_terminalId'),
            "udf1"=>$this->config->get('config_telephone'),
            "udf2"=>$this->config->get('config_owner'),
            "udf3"=>$this->config->get('config_email'),
            "udf4"=>"",
            "udf5"=>"",
            "udf6"=>"",
            "udf7"=>"",
            "udf8"=>"",
            "udf9"=>"",
            "udf10"=>"",
            "ru"=>$config["return_url"],
            "callbackUrl"=>"",
            "currency"=>"INR",
            "paymentMode"=>"ALL",
            "bankId"=>"",
            "txnType"=>"single",
            "productType"=>"IPG",
            "txnNote"=>"Getepay transaction",
            "vpa"=>$this->config->get('payment_getepay_key_terminalId'),
        );
       // echo "<pre/>"; print_r($request); exit;
        $json_requset = json_encode($request);

        $key = base64_decode($this->config->get('payment_getepay_key_key'));
        $iv = base64_decode($this->config->get('payment_getepay_key_iv'));

        // Encryption Code //
        $ciphertext_raw = openssl_encrypt($json_requset, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);
        $ciphertext = bin2hex($ciphertext_raw);
        $newCipher = strtoupper($ciphertext);
        //print_r($newCipher);exit;
        $request=array(
            "mid"=>$this->config->get('payment_getepay_key_mid'),
            "terminalId"=>$this->config->get('payment_getepay_key_terminalId'),
            "req"=>$newCipher
        );
        $url = $this->config->get('payment_getepay_key_url');
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            'Content-Type:application/json',
        ));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
        $result = curl_exec($curl);

        curl_close ($curl);

        $jsonDecode = json_decode($result);
        $jsonResult = $jsonDecode->response;
        $ciphertext_raw = hex2bin($jsonResult);
        $original_plaintext = openssl_decrypt($ciphertext_raw,  "AES-256-CBC", $key, $options=OPENSSL_RAW_DATA, $iv);
        $json = json_decode($original_plaintext);
        //echo "<pre/>"; print_r($json); exit;
        // $pgUrl = $json->paymentUrl;
        return $json;
	}

    public function callback() {
        echo "hi";exit;
        /*if (isset($this->request->post['ap_securitycode']) && ($this->request->post['ap_securitycode'] == $this->config->get('payment_payza_security'))) {
            $this->load->model('checkout/order');

            $this->model_checkout_order->addOrderHistory($this->request->post['ap_itemcode'], $this->config->get('payment_payza_order_status_id'));
        }*/
    }

}
