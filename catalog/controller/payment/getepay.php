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
            'mid'           => $this->config->get('payment_getepay_key_mid'),
            'terminalId'    => $this->config->get('payment_getepay_key_terminalId'),
            'key'           => $this->config->get('payment_getepay_key_key'),
            'iv'            => $this->config->get('payment_getepay_key_iv'),
            'url'           => $this->config->get('payment_getepay_key_url'),
            'return_url'    => $returnUrl
        );
        $out_trade_no = trim($order_info['order_id']);
        $subject = trim($this->config->get('config_name'));
		//$total_amount = trim($this->currency->format($order_info['total'], 'INR', '', false));
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
            $query = <<<QUERY
INSERT INTO {$this->tableName} (customer_id, order_id, getepay_transaction_id, getepay_payment_status, getepay_callback_status, getepay_data, getepay_session, date_created, date_modified)
VALUES (
    '{$order_info['customer_id']}',
    '{$order_info['order_id']}',
    '{$paymentId}',
    'PENDING',
    'NO',
    '{$getepayData}',
    '{$getepaySession}',
    '{$createDate}',
    '{$createDate}'
)
ON DUPLICATE KEY UPDATE
    customer_id = '{$order_info['customer_id']}',
    getepay_transaction_id = '{$paymentId}',
    getepay_payment_status = 'PENDING',
    getepay_callback_status = 'NO',
    getepay_data = '{$getepayData}',
    getepay_session = '{$getepaySession}',
    date_modified = '{$createDate}';
QUERY;

$this->db->query($query);

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
        $createDate     = date('Y-m-d H:i:s');
        // Update Getepay Payment Status transaction record
        $query = <<<QUERY
        UPDATE {$this->tableName}
        SET getepay_payment_status = '{$txnStatus}', date_modified = '{$createDate}'
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
            $returnUrl = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
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
            $this->session->data['error'] = ' Payment Failed! Check Getepay dashboard for details of Payment Id:' . $getepayTxnId;
            $returnUrl = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
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
            $this->session->data['error'] = ' Payment Pending! Check Getepay dashboard for details of Payment Id:' . $getepayTxnId;
            $returnUrl = $this->url->link('checkout/failure', $this->config->get('config_language'), true);
        }
        $this->response->redirect($returnUrl);
    }
	public function setHeadingValues($data, $txnStatus, $order_id, $statusDesc)
    {
		// $order_id = $data["merchantOrderNo"];
		// $order_info = $this->model_checkout_order->getOrder($order_id);
		// $products = $this->model_checkout_order->getProducts($order_id);
    }

    /**
     * @return string
     */
    private function getNotifyUrl(): string
    {
        $notifyUrl = "";
        if ($this->config->get('payment_getepay_notify_payment') === 1) {
            $notifyUrl = filter_var(
                $this->url->link('extension/getepay/payment/getepay.notify_callback', '', true),
                FILTER_SANITIZE_URL
            );
        }

        return $notifyUrl;
    }

    public function get_pending_order_ids_to_check()
    {
        $ordersTable = $this->tableName;
        $currentTimestamp = time();
        $twoDaysAgoTimestamp = $currentTimestamp - 172800;

        $query = <<<QUERY
        SELECT getepay_transaction_id
        FROM $ordersTable
        WHERE getepay_payment_status = 'PENDING'
        AND date_created >= FROM_UNIXTIME($twoDaysAgoTimestamp)
        AND getepay_transaction_id IS NOT NULL
        AND getepay_transaction_id != ''
        QUERY;
        
        $results = $this->db->query($query);
        if ($results->num_rows) {
            $orderIds = array();
            foreach ($results->rows as $row) {
                $orderIds[] = $row['getepay_transaction_id'];
            }
            return $orderIds;
        } else {
            return array();
        }
    }

    /**
     * Handles notify response from Paygate
     * Controlled by Redirect/Notify setting in config
     */
    public function notify_callback()
    {
        // Shouldn't be able to get here in redirect as notify url is not set in redirect mode
        if ($this->config->get('payment_getepay_notify_payment') === '1' ) {
            // Notify Paygate that information has been received
            echo 'OK';
            $mid            = $this->config->get('payment_getepay_key_mid');
            $terminalId     = $this->config->get('payment_getepay_key_terminalId');
            $keyy           = $this->config->get('payment_getepay_key_key');
            $ivv            = $this->config->get('payment_getepay_key_iv');
            $url            = $this->config->get('payment_getepay_key_recheck_url');
            //$url            = 'https://pay1.getepay.in:8443/getepayPortal/pg/invoiceStatus';
            $key            = base64_decode($keyy);
            $iv             = base64_decode($ivv);
            // Loop through each order Ids
            foreach ($this->get_pending_order_ids_to_check() as $paymentId) {
                echo $paymentId;
                //GetePay Callback
                $requestt = array(
                    "mid" => $mid,
                    "paymentId" => $paymentId,
                    "referenceNo" => "",
                    "status" => "",
                    "terminalId" => $terminalId,
                );
                $json_requset = json_encode($requestt);
                $ciphertext_raw = openssl_encrypt($json_requset, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);
                $ciphertext = bin2hex($ciphertext_raw);
                $newCipher = strtoupper($ciphertext);
                $request = array(
                    "mid" => $mid,
                    "terminalId" => $terminalId,
                    "req" => $newCipher
                );
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLINFO_HEADER_OUT, true);
                curl_setopt(
                    $curl,
                    CURLOPT_HTTPHEADER,
                    array(
                        'Content-Type:application/json',
                    )
                );
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($curl, CURLOPT_POST, true);
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($request));
                $result = curl_exec($curl);
                curl_close($curl);
                $jsonDecode = json_decode($result);
                $jsonResult = $jsonDecode->response;
                $ciphertext_raw = hex2bin($jsonResult);
                $original_plaintext = openssl_decrypt($ciphertext_raw, "AES-256-CBC", $key, $options = OPENSSL_RAW_DATA, $iv);
                $json = json_decode($original_plaintext);
                // print_r($json->txnStatus); exit;
                $txnStatus = $json->txnStatus;
                $order_id = $json->merchantOrderNo;
                $getepayTxnId = $json->getepayTxnId;
                $createDate   = date('Y-m-d H:i:s');
                // Update Getepay Payment Status transaction record
                $query = <<<QUERY
                UPDATE {$this->tableName}
                SET getepay_payment_status = '{$txnStatus}', getepay_callback_status= 'YES', date_modified = '{$createDate}'
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
                    $this->setHeadingValues($json, $txnStatus, $order_id, $statusDesc);
                    //$this->cart->clear();
                    //$returnUrl = $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true);
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
                    $this->session->data['error'] = ' Payment Failed! Check Getepay dashboard for details of Payment Id:' . $getepayTxnId;
                    //$returnUrl = $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
                }
                
            }
        }
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
