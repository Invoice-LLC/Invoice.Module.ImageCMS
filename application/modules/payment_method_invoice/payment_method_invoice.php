<?php

(defined('BASEPATH')) or exit('No direct script access allowed');

require "InvoiceSDK/RestClient.php";
require "InvoiceSDK/common/SETTINGS.php";
require "InvoiceSDK/common/ORDER.php";
require "InvoiceSDK/CREATE_TERMINAL.php";
require "InvoiceSDK/CREATE_PAYMENT.php";
require "InvoiceSDK/GET_TERMINAL.php";

class Payment_method_invoice extends MY_Controller
{
    public $moduleName = 'payment_method_invoice';

    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
    }

    private function getPaymentSettings($key)
    {
        $ci = &get_instance();
        $id = '';
        if (!$key) {
            $id = $ci->db->where('payment_system_name', $this->moduleName)
                ->get('shop_payment_methods')->row()->value;

            $key = $id . '_' . $this->moduleName;
        }

        $value = $ci->db->where('name', $key)->get('shop_settings');
        if ($value) {
            $value = $value->row()->value;
        } else {
            show_error($ci->db->_error_message());
        }

        $settings = unserialize($value);

        $this->login = $settings['login'];
        $this->default_terminal_name = $settings['default_terminal_name'];
        $this->api_key = $settings['api_key'];

        return $settings;
    }

    public function getAdminForm($id, $payName = null)
    {
        if (!$this->dx_auth->is_admin()) {
            redirect('/');
            exit;
        }

        $nameMethod = $payName ? $payName : $this->paymentMethod->getPaymentSystemName();
        $key = $id . '_' . $nameMethod;
        $data = $this->getPaymentSettings($key);

        $codeTpl = \CMSFactory\assetManager::create()
            ->setData('data', $data)
            ->fetchTemplate('adminForm');

        return $codeTpl;
    }

    public function getForm($param)
    {

        $payment_method_id = $param->getPaymentMethod();
        $key = $payment_method_id . '_' . $this->moduleName;
        $tid = $this->getTerminal();
        $this->getPaymentSettings($key);

        $amount = $param->getDeliveryPrice() ? ($param->getTotalPrice() + $param->getDeliveryPrice()) : $param->getTotalPrice();
        $id = $param->id;

        $request = new CREATE_PAYMENT();
        $request->order = $this->getOrder($amount, $id);
        $request->settings = $this->getSettings($tid);
        $request->receipt = $this->getReceipt();

        $response = (new RestClient($this->login, $this->api_key))->CreatePayment($request);

        if ($response == null or isset($response->error)) throw new Exception('Payment error');

        $payment_url = $response->payment_url;

        $data = array(
            'payment_url' => $payment_url,
        );

        $codeTpl = \CMSFactory\assetManager::create()
            ->setData('data', $data)
            ->fetchTemplate('form');

        return $codeTpl;
    }

    /**
     * @return INVOICE_ORDER
     */
    function getOrder($amount, $id)
    {
        $order = new INVOICE_ORDER();
        $order->amount = $amount;
        $order->id = "$id" . "-" . bin2hex(random_bytes(5));
        $order->currency = "RUB";

        return $order;
    }

    /**
     * @return SETTINGS
     */
    function getSettings($terminal)
    {
        $url = ((!empty($_SERVER['HTTPS'])) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];

        $settings = new SETTINGS();
        $settings->terminal_id = $terminal;
        $settings->success_url = $url;
        $settings->fail_url = $url;

        return $settings;
    }

    /**
     * @return ITEM
     */
    function getReceipt()
    {
        $receipt = array();

        return $receipt;
    }

    public function callback()
    {
        $postData = file_get_contents('php://input');
        $notification = json_decode($postData, true);

        $type = $notification["notification_type"];
        $id = strstr($notification["order"]["id"], "-", true);

        $signature = $notification["signature"];

        if ($signature != $this->getSignature($notification["id"], $notification["status"], $this->apiKey)) {
            die("Wrong signature");
        }

        if ($type == "pay") {

            if ($notification["status"] == "successful") {
                $this->pay($id, $notification['order']['amount']);
                die("payment successful");
            }
            if ($notification["status"] == "error") {
                die("payment failed");
            }
        }

        die("null");
    }

    public function pay($orderId, $amount)
    {
        $ci = &get_instance();

        \CMSFactory\Events::create()->registerEvent(['system' => __CLASS__, 'order_id' => $orderId], 'PaymentSystem:successPaid');
        \CMSFactory\Events::runFactory();

        $userOrder = $ci->db->where('id', $orderId)
            ->get('shop_orders');

        $ci->db->where('id', $orderId)
            ->update('shop_orders', ['paid' => '1', 'date_updated' => time()]);

        $ci->db
            ->where('id', $userOrder->user_id)
            ->limit(1)
            ->update(
                'users',
                [
                    'amout' => str_replace(',', '.', $amount),
                ]
            );
    }

    public function error()
    {
    }

    public function getTerminal()
    {
        if (!file_exists('invoice_tid')) file_put_contents('invoice_tid', '');
        $tid = file_get_contents('invoice_tid');
        $terminal = new GET_TERMINAL();
        $terminal->alias =  $tid;
        $info = (new RestClient($this->login, $this->api_key))->GetTerminal($terminal);

        if ($tid == null or empty($tid) || $info->id == null || $info->id != $terminal->alias) {
            $request = new CREATE_TERMINAL();
            $request->name = $this->default_terminal_name;
            $request->type = "dynamical";
            $request->description = "ImageCMS Terminal";
            $request->defaultPrice = 0;
            $response = (new RestClient($this->login, $this->api_key))->CreateTerminal($request);

            if ($response == null or isset($response->error)) throw new Exception('terminal error');

            $tid = $response->id;
            file_put_contents('invoice_tid', $tid);
        }

        return $tid;
    }

    public function saveSettings(SPaymentMethods $paymentMethod)
    {
        $saveKey = $paymentMethod->getId() . '_' . $this->moduleName;
        \ShopCore::app()->SSettings->set($saveKey, serialize($_POST['payment_method_invoice']));

        return true;
    }

    public function getSignature($id, $status, $key)
    {
        return md5($id . $status . $key);
    }

    public function autoload()
    {
    }

    public function _install()
    {
        $ci = &get_instance();

        $result = $ci->db->where('name', $this->moduleName)
            ->update('components', ['enabled' => '1']);
        if ($ci->db->_error_message()) {
            show_error($ci->db->_error_message());
        }
    }

    public function _deinstall()
    {
        $ci = &get_instance();

        $result = $ci->db->where('payment_system_name', $this->moduleName)
            ->update(
                'shop_payment_methods',
                [
                    'active'              => '0',
                    'payment_system_name' => '0',
                ]
            );
        if ($ci->db->_error_message()) {
            show_error($ci->db->_error_message());
        }

        $result = $ci->db->like('name', $this->moduleName)
            ->delete('shop_settings');
        if ($ci->db->_error_message()) {
            show_error($ci->db->_error_message());
        }
    }
}
