<?php
namespace Opencart\Admin\Controller\Extension\Getepay\Payment;

use Opencart\System\Engine\Controller;

class Getepay extends Controller

// class ControllerPaymentGetepay extends Controller
{
    const PAYMENT_URL      = "marketplace/extension";
    const GETEPAY_LANGUAGE = "extension/getepay/payment/getepay";
	const WEBHOOK_URL    = HTTP_CATALOG . 'index.php?route=extension/getepay/payment/getepay.webhook';
	const CALLBACK_URL    = HTTP_CATALOG . 'index.php?route=extension/getepay/payment/getepay.notify_callback';

    private $error = array();
    private $tableName = DB_PREFIX . 'getepay_transaction';

        /**
     * Setup db table for paygate transactions
     *
     * @return void
     */
    public function install()
    {
        $query = <<<QUERY
        CREATE TABLE IF NOT EXISTS {$this->tableName} (
            getepay_id INT AUTO_INCREMENT PRIMARY KEY,
            customer_id INT NOT NULL,
            order_id INT NOT NULL UNIQUE,
            getepay_transaction_id VARCHAR(255) NOT NULL,
            getepay_payment_status VARCHAR(255) NOT NULL,
            getepay_callback_status TEXT NULL,
            getepay_data TEXT NULL,
            getepay_session TEXT NULL,
            date_created DATETIME NOT NULL,
            date_modified DATETIME NOT NULL
        )
        QUERY;
        
        $this->db->query($query);        

        $this->load->model('setting/setting');

        $this->model_setting_setting->editValue('config', 'config_session_samesite', 'Lax');
    }

    /**
     * Drop table
     *
     * @return void
     */
    public function uninstall()
    {
        $this->db->query("drop table if exists {$this->tableName}");
    }

    public function getToken()
    {
        return $this->session->data['user_token'];
    }

    public function formatPaymentUrl($path)
    {
        $token = $this->getToken();

        return $this->url->link(
            $path,
            'user_token=' . $token . '&type=payment',
            true
        );
    }

    public function formatUrl($path)
    {
        $token = $this->getToken();

        return $this->url->link(
            $path,
            "user_token=$token",
            true
        );
    }

    /**
     * @return string
     */
    private function getNotifyUrl(): string
    {
        $notifyUrl = "";
        if ($this->config->get('payment_getepay_notify_payment') === 1) {
            $notifyUrl = filter_var(
                $this->url->link('extension/getepay/payment/getepay.notify_handler', '', true),
                FILTER_SANITIZE_URL
            );
        }

        return $notifyUrl;
    }

    public function index()
    {
        //$this->language->load('extension/getepay/payment/getepay');
        $this->load->language(self::GETEPAY_LANGUAGE);

        $this->document->setTitle($this->language->get('heading_title'));
        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_getepay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $url                            = $this->formatPaymentUrl(self::PAYMENT_URL);
            $this->response->redirect($url);
        }

        $data['heading_title']                  = $this->language->get('heading_title');
        $data['text_edit']                      = $this->language->get('text_edit');
        $data['text_enabled']                   = $this->language->get('text_enabled');
        $data['text_disabled']                  = $this->language->get('text_disabled');
        $data['text_all_zones']                 = $this->language->get('text_all_zones');
        $data['text_yes']                       = $this->language->get('text_yes');
        $data['text_no']                        = $this->language->get('text_no');
        $data['entry_key_mid']                  = $this->language->get('entry_key_mid');
        $data['entry_key_terminalId']           = $this->language->get('entry_key_terminalId');
        $data['entry_order_status']             = $this->language->get('entry_order_status');
        $data['entry_notify_getepay_payment_chk']   = $this->language->get('entry_notify_getepay_payment_chk');
        $data['entry_status']                   = $this->language->get('entry_status');
        $data['entry_sort_order']               = $this->language->get('entry_sort_order');
        $data['entry_payment_action']           = $this->language->get('entry_payment_action');
        // $data['entry_webhook_secret']           = $this->language->get('entry_webhook_secret');
        // $data['entry_webhook_status']           = $this->language->get('entry_webhook_status');
        // $data['entry_webhook_url']              = $this->language->get('entry_webhook_url');
        $data['button_save']                    = $this->language->get('button_save');
        $data['button_cancel']                  = $this->language->get('button_cancel');

        $data['help_key_mid']                   = $this->language->get('help_key_mid');
        $data['help_order_status']              = $this->language->get('help_order_status');
        // $data['help_webhook_url']               = $this->language->get('help_webhook_url');

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['payment_getepay_key_mid'])) {
            $data['error_key_mid'] = $this->error['payment_getepay_key_mid'];
        } else {
            $data['error_key_mid'] = '';
        }

        if (isset($this->error['payment_getepay_key_terminalId'])) {
            $data['error_key_terminalId'] = $this->error['payment_getepay_key_terminalId'];
        } else {
            $data['error_key_terminalId'] = '';
        }

        if (isset($this->error['payment_getepay_key_key'])) {
            $data['error_key_key'] = $this->error['payment_getepay_key_key'];
        } else {
            $data['error_key_key'] = '';
        }

        if (isset($this->error['payment_getepay_key_iv'])) {
            $data['error_key_iv'] = $this->error['payment_getepay_key_iv'];
        } else {
            $data['error_key_iv'] = '';
        }

        if (isset($this->error['payment_getepay_key_url'])) {
            $data['error_key_url'] = $this->error['payment_getepay_key_url'];
        } else {
            $data['error_key_url'] = '';
        }

        // if (isset($this->error['payment_getepay_webhook_secret'])) {
        //     $data['error_webhook_secret'] = $this->error['payment_getepay_webhook_secret'];
        // } else {
        //     $data['error_webhook_secret'] = '';
        // }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->formatUrl('common/dashboard'),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->formatPaymentUrl(self::PAYMENT_URL),
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->formatUrl(self::GETEPAY_LANGUAGE),
        );

        // $data['action'] = $this->url->link('extension/getepay/payment/getepay', 'user_token=' . $this->session->data['user_token'], true);
        // $data['cancel'] = $this->url->link('extension/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        $data['action'] = $this->formatUrl(self::GETEPAY_LANGUAGE);
        $data['cancel'] = $this->formatPaymentUrl(self::PAYMENT_URL);

        if (isset($this->request->post['payment_getepay_key_mid'])) {
            $data['getepay_key_mid'] = $this->request->post['payment_getepay_key_mid'];
        } else {
            $data['getepay_key_mid'] = $this->config->get('payment_getepay_key_mid');
        }

        if (isset($this->request->post['payment_getepay_key_terminalId'])) {
            $data['getepay_key_terminalId'] = $this->request->post['payment_getepay_key_terminalId'];
        } else {
            $data['getepay_key_terminalId'] = $this->config->get('payment_getepay_key_terminalId');
        }

        if (isset($this->request->post['payment_getepay_key_key'])) {
            $data['getepay_key_key'] = $this->request->post['payment_getepay_key_key'];
        } else {
            $data['getepay_key_key'] = $this->config->get('payment_getepay_key_key');
        }

        if (isset($this->request->post['payment_getepay_key_iv'])) {
            $data['getepay_key_iv'] = $this->request->post['payment_getepay_key_iv'];
        } else {
            $data['getepay_key_iv'] = $this->config->get('payment_getepay_key_iv');
        }

        if (isset($this->request->post['payment_getepay_key_url'])) {
            $data['getepay_key_url'] = $this->request->post['payment_getepay_key_url'];
        } else {
            $data['getepay_key_url'] = $this->config->get('payment_getepay_key_url');
        }

        if (isset($this->request->post['payment_getepay_order_status_id'])) {
            $data['getepay_order_status_id'] = $this->request->post['payment_getepay_order_status_id'];
        } else {
            $data['getepay_order_status_id'] = ($this->config->get('payment_getepay_order_status_id')) ? $this->config->get('payment_getepay_order_status_id') : 2;
        }

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        if (isset($this->request->post['payment_getepay_status'])) {
            $data['getepay_status'] = $this->request->post['payment_getepay_status'];
        } else {
            $data['getepay_status'] = $this->config->get('payment_getepay_status');
        }

        if (isset($this->request->post['payment_getepay_notify_payment'])) {
            $data['url_getepay_notify_payment'] = $this->request->post['payment_getepay_notify_payment'];
        } else {
            $data['url_getepay_notify_payment'] = $this->config->get('payment_getepay_notify_payment');
        }

        if (isset($this->request->post['payment_getepay_key_recheck_url'])) {
            $data['getepay_key_recheck_url'] = $this->request->post['payment_getepay_key_recheck_url'];
        } else {
            $data['getepay_key_recheck_url'] = $this->config->get('payment_getepay_key_recheck_url');
        }

        if (isset($this->request->post['payment_getepay_sort_order'])) {
            $data['getepay_sort_order'] = $this->request->post['payment_getepay_sort_order'];
        } else {
            $data['getepay_sort_order'] = $this->config->get('payment_getepay_sort_order');
        }

        if (isset($this->request->post['payment_getepay_payment_action'])) {
            $data['getepay_payment_action'] = $this->request->post['payment_getepay_payment_action'];
        } else {
            $data['getepay_payment_action'] = $this->config->get('payment_getepay_payment_action');
        }

        if (isset($this->request->post['payment_getepay_max_capture_delay'])) {
            $data['getepay_max_capture_delay'] = $this->request->post['payment_getepay_max_capture_delay'];
        } else {
            $data['getepay_max_capture_delay'] = $this->config->get('payment_getepay_max_capture_delay');
        }

        // if (isset($this->request->post['payment_getepay_webhook_status'])) {
        //     $data['getepay_webhook_status'] = $this->request->post['payment_getepay_webhook_status'];
        // } else {
        //     $data['getepay_webhook_status'] = $this->config->get('payment_getepay_webhook_status');
        // }

        // if (isset($this->request->post['payment_getepay_webhook_secret'])) {
        //     $data['getepay_webhook_secret'] = $this->request->post['payment_getepay_webhook_secret'];
        // } else {
        //     $data['getepay_webhook_secret'] = $this->config->get('payment_getepay_webhook_secret');
        // }
        
        $data['getepay_webhook_url'] = self::WEBHOOK_URL;
        $data['getepay_callback_url'] = self::CALLBACK_URL;

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view(self::GETEPAY_LANGUAGE, $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', self::GETEPAY_LANGUAGE)) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}