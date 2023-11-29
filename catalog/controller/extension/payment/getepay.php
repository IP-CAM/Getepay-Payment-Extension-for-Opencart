<?php
class ControllerExtensionPaymentGetepay extends Controller {
	public function index() {
		$data['button_confirm'] = $this->language->get('button_confirm');

		$this->load->model('checkout/order');

		if(!isset($this->session->data['order_id'])) {
			return false;
		}

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$config = array (
			'mid'               => $this->config->get('payment_getepay_mid'),
            'terminalId'        => $this->config->get('payment_getepay_terminalId'),
            'key'               => $this->config->get('payment_getepay_key'),
            'iv'               	=> $this->config->get('payment_getepay_iv'),
            'url'               => $this->config->get('payment_getepay_url'),
			'return_url'        => $this->url->link('extension/payment/getepay/callback')
		);
		$out_trade_no = trim($order_info['order_id']);
		$subject = trim($this->config->get('config_name'));
		$total_amount = trim($this->currency->format($order_info['total'], 'INR', '', false));

		// $order_info = $this->model_checkout_order->getOrder($order_info['order_id']);
		// $this->model_checkout_order->addOrderHistory(
		// 	$order_info['order_id'],
		// 	1,
		// 	'User Redirect to Getepay for Payment.',
		// 	true
		// );

		$payRequestBuilder = array(
			'total_amount' => $total_amount,
			'order_id' => $out_trade_no
		);

		$this->load->model('extension/payment/getepay');

		$response = $this->model_extension_payment_getepay->pagePay($payRequestBuilder,$config);
		$data['action'] = $response;
		$data['form_params'] = [];

		return $this->load->view('extension/payment/getepay', $data);
	}

	public function callback() {
		//header('Set-Cookie: ' . $this->config->get('session_name') . '=' . $this->session->getId() . '; SameSite=None; Secure ; HttpOnly');
        $this->log->write('POST' . var_export($_POST,true));

        $this->load->model('extension/payment/getepay');
        $this->load->language('extension/payment/getepay');

        $post = $_POST;

        $response = $post["response"];

        $key = base64_decode($this->config->get('payment_getepay_key_key'));
        $iv = base64_decode($this->config->get('payment_getepay_key_iv'));

        // Encryption Code //
        $ciphertext_raw = $ciphertext_raw = hex2bin($response);
        $original_plaintext = openssl_decrypt($ciphertext_raw,  "AES-256-CBC", $key, $options=OPENSSL_RAW_DATA, $iv);
       	$json = json_decode(json_decode($original_plaintext,true),true);
        //$json = $original_plaintext;
		$order_id = $json["merchantOrderNo"];
		$getepayTxnId = $json["getepayTxnId"];
        $returnUrl =  $this->url->link('checkout/checkout', '', true);
        if($json["paymentStatus"] == "SUCCESS"){
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($order_id);
			if (!empty($order_info['customer_id'])) {
				$this->load->model('account/customer');
				$customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);
				$this->customer->login($customer_info['email'], '', true);
			}
            //$getepay_order_id = $this->model_extension_payment_globalpay->addOrder($order_info, $this->request->post['PASREF'], $this->request->post['AUTHCODE'], $this->request->post['ACCOUNT'], $this->request->post['ORDER_ID']);
            //$this->model_checkout_order->addOrderHistory($order_id, $this->config->get('payment_getepay_order_status_id'));
			$this->model_checkout_order->addOrderHistory(
                $order_id,
                $this->config->get('payment_getepay_order_status_id'),
                "Transaction is completed successfully With Getepay, Transaction ID: $getepayTxnId",
                true
            );
			// Set order session for clearing the cart
			$this->session->data['order_id'] = $order_id;
			// echo '<pre>';
			// print_r($this->session->data); exit;
			// Clear the cart after successful checkout
            $this->cart->clear();
            $returnUrl = $this->url->link('checkout/success', '', true);
        }
		elseif ($json["paymentStatus"] == "FAILED") {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($order_id);
			if (!empty($order_info['customer_id'])) {
				$this->load->model('account/customer');
				$customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);
				$this->customer->login($customer_info['email'], '', true);
			}
			$statusDesc = "failed";

			$this->model_checkout_order->addOrderHistory(
                $order_id,
                10,
                "Transaction Failed by Getepay, Transaction ID: $getepayTxnId",
                true
            );

			//$this->restoreCart($products, $statusDesc, $order_id);
            $returnUrl = $this->url->link('checkout/failure', '', true);
        }
		elseif ($json["paymentStatus"] == "PENDING") {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($order_id);
			if (!empty($order_info['customer_id'])) {
				$this->load->model('account/customer');
				$customer_info = $this->model_account_customer->getCustomer($order_info['customer_id']);
				$this->customer->login($customer_info['email'], '', true);
			}
			$statusDesc = "pending";

			$this->model_checkout_order->addOrderHistory(
                $order_id,
                1,
                "Getepay Transaction status is Pending, Transaction ID: $getepayTxnId",
                true
            );

			//$this->restoreCart($products, $statusDesc, $order_id);
            $returnUrl = $this->url->link('checkout/failure', '', true);
        }

        $this->response->redirect($returnUrl);
	}
}