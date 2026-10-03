<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TwittPay - Smart SMM Panel v4 deposit method
 * ---------------------------------------------------------------------------
 * create_payment()  creates the payment and hands the checkout URL to the panel
 * complete()        answers both the returning user and the gateway's webhook
 *
 * The deposit is only credited after the transaction has been verified against
 * the API, and only while its own log row is still unpaid - so the webhook and the
 * user's return cannot credit the same deposit twice.
 *
 * @version 1.0.0
 */
class twittpay extends MX_Controller
{
    public $tb_users;
    public $tb_transaction_logs;
    public $tb_payments;
    public $tb_payments_bonuses;
    public $payment_type;
    public $payment_id;
    public $payment_lib;
    public $api_key;
    public $api_url;
    public $currency_rate;
    public $currency_code;
    public $take_fee_from_user;

    public function __construct($payment = "")
    {
        parent::__construct();
        $this->load->model('add_funds_model', 'model');

        $this->tb_users            = USERS;
        $this->payment_type        = 'twittpay';
        $this->tb_transaction_logs = TRANSACTION_LOGS;
        $this->tb_payments         = PAYMENTS_METHOD;
        $this->tb_payments_bonuses = PAYMENTS_BONUSES;
        $this->currency_code       = get_option("currency_code", "USD");

        if (!$payment) {
            $payment = $this->model->get('id, type, name, params', $this->tb_payments, ['type' => $this->payment_type]);
        }

        $this->payment_id = $payment->id;
        $params           = $payment->params;
        $option           = get_value($params, 'option');

        $this->take_fee_from_user = get_value($params, 'take_fee_from_user');

        // Gateway settings
        $this->api_key       = get_value($option, 'api_key');
        $this->api_url       = get_value($option, 'api_url');
        $this->currency_rate = get_value($option, 'currency_rate');

        $this->load->library("twittpayapi");
        $this->payment_lib = new Twittpayapi($this->api_key, $this->api_url);
    }

    public function index()
    {
        redirect(cn("add_funds"));
    }

    public function create_payment($data_payment = "")
    {
        _is_ajax($data_payment['module']);
        $amount = $data_payment['amount'];

        if (!$amount || $amount <= 0) {
            _validation('error', lang('invalid_amount'));
        }

        if (!$this->api_key || !$this->api_url) {
            _validation('error', lang('payment_gateway_not_configured'));
        }

        $user = $this->model->get('*', $this->tb_users, ['id' => session('uid')]);

        if (!$user) {
            _validation('error', lang('user_not_found'));
        }

        $unique_id = uniqid();

        $data = [
            "cus_name"    => trim($user->first_name . ' ' . $user->last_name),
            "cus_email"   => !empty($user->email) ? $user->email : 'default@gmail.com',
            "amount"      => number_format($this->to_bdt($amount), 2, '.', ''),
            "success_url" => cn("add_funds/twittpay/complete"),
            "cancel_url"  => cn("add_funds/unsuccess"),
            "webhook_url" => cn("add_funds/twittpay/complete"),
            "metadata"    => [
                "invoice_id"       => $unique_id,
                "user_id"          => (string) $user->id,
                "description"      => lang('deposit_to') . get_option('website_name'),
                "deposit_amount"   => (string) $amount,
                "deposit_currency" => $this->currency_code,
                "source"           => 'smart-smm-panel-v4',
            ],
        ];

        try {
            $transaction_data = [
                "ids"            => ids(),
                "uid"            => $user->id,
                "type"           => $this->payment_type,
                "transaction_id" => $unique_id,
                "amount"         => $amount,
                "status"         => 0,
                "created"        => NOW,
            ];

            $this->db->insert($this->tb_transaction_logs, $transaction_data);
            $transaction_id = $this->db->insert_id();
            set_session("transaction_id", $transaction_id);

            $paymentUrl = $this->payment_lib->createPayment($data);

            ms([
                'status'       => 'success',
                'redirect_url' => $paymentUrl,
            ]);
        } catch (Exception $e) {
            _validation('error', $e->getMessage());
        }
    }

    /**
     * Both the returning user and the gateway's webhook land here. The webhook
     * posts a form; the user arrives with a GET.
     */
    public function complete()
    {
        $is_webhook     = (isset($_SERVER['REQUEST_METHOD']) && strtoupper($_SERVER['REQUEST_METHOD']) === 'POST');
        $transaction_id = $this->payment_lib->transactionIdFromRequest();

        if (empty($transaction_id)) {
            $this->finish($is_webhook, 'unknown');
        }

        $result = $this->payment_lib->verifyPayment($transaction_id);
        $status = $this->payment_lib->readStatus($result);

        if ($status === '') {
            $this->finish($is_webhook, 'unknown');
        }

        $meta = $this->payment_lib->metadata($result);

        if (empty($meta['invoice_id'])) {
            $this->finish($is_webhook, 'unknown');
        }

        if ($status === 'PENDING') {
            // Sent, not approved by the merchant yet. The gateway calls this URL
            // again with the answer, so the log row is left unpaid.
            $this->finish($is_webhook, 'pending');
        }

        if ($status !== 'COMPLETED') {
            $this->finish($is_webhook, 'failed');
        }

        // status 0 means not yet credited, so this can only match once.
        $transaction = $this->model->get('*', $this->tb_transaction_logs, [
            'transaction_id' => $meta['invoice_id'],
            'status'         => 0,
        ]);

        if ($transaction) {
            $amount = isset($meta['deposit_amount']) ? $meta['deposit_amount'] : $transaction->amount;

            $update_data = [
                "transaction_id" => isset($result['transaction_id']) ? $result['transaction_id'] : $transaction_id,
                "amount"         => $amount,
                "txn_fee"        => 0,
                "payer_email"    => isset($result['cus_email']) ? $result['cus_email'] : '',
                "status"         => 1,
            ];

            $this->db->update($this->tb_transaction_logs, $update_data, ['id' => $transaction->id]);

            $credited = $this->model->get('*', $this->tb_transaction_logs, ['id' => $transaction->id]);
            $this->model->add_funds_bonus_email($credited, $this->payment_id);
        }

        $this->finish($is_webhook, 'success');
    }

    /**
     * The webhook gets a bare 200, the user gets sent back into the panel.
     */
    private function finish($is_webhook, $outcome)
    {
        if ($is_webhook) {
            http_response_code(200);
            echo json_encode(['status' => true, 'message' => $outcome]);
            exit;
        }

        if ($outcome === 'success') {
            redirect(cn("add_funds/success"));
        }

        if ($outcome === 'pending') {
            // Not a failure - the money has been sent and is being checked.
            redirect(cn("add_funds"));
        }

        redirect(cn("add_funds/unsuccess"));
    }

    /** The gateway charges BDT. Any other panel currency is converted. */
    private function to_bdt($amount)
    {
        if (strtoupper((string) $this->currency_code) === 'BDT') {
            return (float) $amount;
        }

        $rate = (float) $this->currency_rate;

        if ($rate <= 0) {
            $rate = 1;
        }

        return (float) $amount * $rate;
    }
}
