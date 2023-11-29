<?php
namespace Opencart\Catalog\Controller\Extension\Getepay\Payment;

use Opencart\System\Engine\Controller;

class Getepay extends Controller
{
	const INFORMATION_CONTACT = "information/contact";
    const GETEPAY_CODE = 'getepay.getepay';
    private $tableName = DB_PREFIX . 'getepay_transaction';

    public function index() {
        $this->load->language('extension/getepay/payment/getepay');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['continue']       = $this->language->get('payment_url');

        $this->load->model('checkout/order');

        if (!isset($this->session->data['order_id'])) {
            return false;
        }

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        //echo "<pre/>"; print_r($order_info); exit;

        $returnUrl = filter_var(
            $this->url->link('extension/getepay/payment/getepay|callback', '', true),
            FILTER_SANITIZE_URL
        );

        $config = array (
            'mid'           => $this->config->get('payment_getepay_mid'),
            'terminalId'    => $this->config->get('payment_getepay_terminalId'),
            'key'           => $this->config->get('payment_getepay_key'),
            'iv'            => $this->config->get('payment_getepay_iv'),
            'url'           => $this->config->get('payment_getepay_url'),
            'return_url'    => $returnUrl
        );

        $out_trade_no = trim($order_info['order_id']);
        $subject = trim($this->config->get('config_name'));
		//$total_amount = trim($this->currency->format($order_info['total'], 'INR', '', false));

		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($order_info['order_id']);
		$this->model_checkout_order->addHistory(
			$order_info['order_id'],
			1,
			'User Redirect on Getepay for Payment.',
			true
		);

        $total_amount = $order_info['total'];
		$formatted_amount = number_format($total_amount, 2, '.', '');

        $payRequestBuilder = array(
            'total_amount' => $formatted_amount,
            'order_id'     => $out_trade_no
        );

        $this->load->model('extension/getepay/payment/getepay');
        $response = $this->model_extension_getepay_payment_getepay->pagePay($payRequestBuilder, $config);
        $pgUrl = $response->paymentUrl;
        $paymentId = $response->paymentId;
        //echo "<pre/>"; print_r($paymentId); exit;
        if ($order_info['payment_method']['code'] === self::GETEPAY_CODE) {
            // Save transaction data for return
            $getepayData    = serialize($order_info);
            $getepaySession = [
                'customer' => $this->customer,
                'customerId' => $order_info['customer_id'],
            ];
            $getepaySession = base64_encode(serialize($getepaySession));
            $createDate     = date('Y-m-d H:i:s');
            $query          = <<<QUERY
insert into {$this->tableName} (customer_id, order_id, getepay_transaction_id, getepay_payment_status, getepay_data, getepay_session, date_created,
date_modified)
values (
        '{$order_info['customer_id']}',
        '{$order_info['order_id']}',
        '{$paymentId}',
        'PENDING',
        '{$getepayData}',
        '{$getepaySession}',
        '{$createDate}',
        '{$createDate}'
        )
QUERY;
            $this->db->query($query);

            $this->cart->clear();
        }

        $data['action'] = $pgUrl;
        $data['form_params'] = [];

        return $this->load->view('extension/getepay/payment/getepay', $data);
    }

    public function callback() {
		header('Set-Cookie: ' . $this->config->get('session_name') . '=' . $this->session->getId() . '; SameSite=None; Secure ; HttpOnly');

        $this->log->write('POST' . var_export($this->request->post, true));

        $this->load->model('extension/getepay/payment/getepay');
		$this->load->model('checkout/order');
        $this->load->language('extension/getepay/payment/getepay');
        $this->load->language('extension/getepay/checkout/getepay');


        $post = $this->request->post;

        $response = $post["response"];

        $key = base64_decode($this->config->get('payment_getepay_key_key'));
        $iv = base64_decode($this->config->get('payment_getepay_key_iv'));

        $ciphertext_raw = hex2bin($response);
        $original_plaintext = openssl_decrypt($ciphertext_raw, "AES-256-CBC", $key, OPENSSL_RAW_DATA, $iv);
        $json = json_decode($original_plaintext, true);

		//echo '<pre>'; print_r($json); exit;
		// Decode JSON data
		$data = json_decode($json, true);

		// Retrieve the value of "txnStatus"
		$txnStatus = $data['txnStatus'];
		$order_id = $data["merchantOrderNo"];
		$getepayTxnId = $data["getepayTxnId"];

        // Update Getepay Payment Status transaction record
        $query = <<<QUERY
        UPDATE {$this->tableName}
        SET getepay_payment_status = '{$txnStatus}'
        WHERE getepay_transaction_id = '{$getepayTxnId}';
        QUERY;
        $this->db->query($query);


        $returnUrl = $this->url->link('checkout/checkout', '', true);

        if ($txnStatus == "SUCCESS") {
            $this->load->model('checkout/order');
			$statusDesc     = 'approved';

            $order_info = $this->model_checkout_order->getOrder($order_id);
			$products = $this->model_checkout_order->getProducts($order_id);

			$this->model_checkout_order->addHistory(
                $order_id,
                $this->config->get('payment_getepay_order_status_id'),
                "Transaction is completed successfully With Getepay, Transaction ID: $getepayTxnId",
                true
            );

			$this->setHeadingValues($data, $txnStatus, $order_id, $statusDesc);
			
			//$this->cart->clear();

            $returnUrl = $this->url->link('checkout/success', '', true);
        }
		elseif ($txnStatus == "FAILED") {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($order_id);
			$products = $this->model_checkout_order->getProducts($order_id);

			$statusDesc = "failed";

			$this->model_checkout_order->addHistory(
                $order_id,
                10,
                "Transaction Failed by Getepay, Transaction ID: $getepayTxnId",
                true
            );

			//$this->restoreCart($products, $statusDesc, $order_id);
            $returnUrl = $this->url->link('checkout/failure', '', true);
        }
		elseif ($txnStatus == "PENDING") {
            $this->load->model('checkout/order');

            $order_info = $this->model_checkout_order->getOrder($order_id);
			$products = $this->model_checkout_order->getProducts($order_id);

			$statusDesc = "pending";

			$this->model_checkout_order->addHistory(
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

	public function setHeadingValues($data, $txnStatus, $order_id, $statusDesc)
    {

		$order_id = $data["merchantOrderNo"];
		$order_info = $this->model_checkout_order->getOrder($order_id);
		$products = $this->model_checkout_order->getProducts($order_id);
		$customer_id = $order_info["customer_id"];		
        $customerId = (int)isset($customer_id) ? $customer_id : 0;

		
		//$customerId = (int) $customer_id;
		//echo '<pre>'; print_r($customerId); exit;

        // if ($customerId > 0) {
        //     // Load the customer model
		// 	$this->load->model('account/customer');
			
		// 	// Log in the customer by customer ID
		// 	$customer_info = $this->model_account_customer->getCustomer($customerId);

		// 	// Debug: Print customer_info to check its contents
		// 	// echo '<pre>';
		// 	// print_r($customer_info);
		// 	// echo '</pre>';
			
		// 	if ($customer_info) {

		// 		$this->customer->login($customer_info['email'], '', true);
		// 		// Set customer_token in the session
		// 		$this->session->data['token'] = $customer_token;
		// 		$this->session->data['customer_token'] = $customer_token;
				
		// 		// Debug: Print the session data to check if customer_token is set
		// 		// echo '<pre>';
		// 		// print_r($this->session->data);
		// 		// echo '</pre>';
		// 		// exit;
		// 	}

        // }


    }

	public function confirm()
    {
        if ( $this->session->data['payment_method']['code'] == 'getepay.getepay' ) {
            $this->load->model( 'checkout/order' );
            $comment = 'Redirected to PayGate';
            $this->model_checkout_order->addHistory( $this->session->data['order_id'],1 , $comment, true );
        }
		
    }

	public function restoreCart($products, $statusDesc, $order_id)
    {
        if ($statusDesc !== 'approved' && is_array($products)) {
            // Restore the cart which has already been cleared
            foreach ($products as $product) {
                $options = $this->model_checkout_order->getOptions($order_id, $product['order_product_id']);
                $option  = [];
                if (is_array($options) && count($options) > 0) {
                    $option = $options;
                }
                $this->cart->add($product['product_id'], $product['quantity'], $option);
            }
        }
    }
}
