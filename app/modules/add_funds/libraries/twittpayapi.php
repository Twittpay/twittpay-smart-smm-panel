<?php
defined('BASEPATH') or exit('No direct script access allowed');

/**
 * TwittPay - Smart SMM Panel v4 API client
 *
 * @version 1.0.0
 */
class Twittpayapi
{
    private $apiKey;
    private $baseUrl;

    public function __construct($apiKey = null, $apiUrl = null)
    {
        $this->apiKey  = trim((string) $apiKey);
        $this->baseUrl = $this->normalizeBaseUrl($apiUrl);
    }

    /** Create the payment and hand back the checkout URL. */
    public function createPayment($requestData)
    {
        $response = $this->sendRequest('/api/payment/create', $requestData);

        if (empty($response['status']) || empty($response['payment_url'])) {
            // The API message is not passed on - an error string can carry the key
            // back out to the customer.
            throw new Exception('The payment could not be started. Please try again.');
        }

        return $response['payment_url'];
    }

    /** Ask the gateway what really happened to a transaction. */
    public function verifyPayment($transactionId)
    {
        if (empty($transactionId)) {
            return [];
        }

        return $this->sendRequest('/api/payment/verify', ['transaction_id' => $transactionId]);
    }

    /**
     * The verify status: PENDING, COMPLETED or ERROR when the transaction is real,
     * and an empty string when it is not - a miss answers a number, not text.
     */
    public function readStatus($verified)
    {
        if (!is_array($verified) || !isset($verified['status']) || !is_string($verified['status'])) {
            return '';
        }

        return strtoupper(trim($verified['status']));
    }

    /** metadata comes back from verify as a JSON string. */
    public function metadata($verified)
    {
        if (!is_array($verified) || !isset($verified['metadata'])) {
            return [];
        }

        $meta = $verified['metadata'];

        if (is_array($meta)) {
            return $meta;
        }

        if (is_object($meta)) {
            return (array) $meta;
        }

        if (is_string($meta) && $meta !== '') {
            $decoded = json_decode($meta, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    /** The transaction id, from the URL, a form body, or a JSON body. */
    public function transactionIdFromRequest()
    {
        foreach ([$_GET, $_POST] as $bag) {
            foreach (['transactionId', 'transaction_id'] as $key) {
                if (!empty($bag[$key])) {
                    return trim((string) $bag[$key]);
                }
            }
        }

        $raw = file_get_contents('php://input');

        if (!empty($raw)) {
            $body = json_decode($raw, true);

            if (is_array($body)) {
                foreach (['transactionId', 'transaction_id'] as $key) {
                    if (!empty($body[$key])) {
                        return trim((string) $body[$key]);
                    }
                }
            }
        }

        return '';
    }

    /**
     * Scheme and host of the configured endpoint. Pasting the whole endpoint or a
     * trailing /api still works.
     */
    private function normalizeBaseUrl($apiUrl)
    {
        $raw    = rtrim(trim((string) $apiUrl), '/');
        $scheme = parse_url($raw, PHP_URL_SCHEME);
        $host   = parse_url($raw, PHP_URL_HOST);

        if (empty($host)) {
            $host = strtok(ltrim(preg_replace('#^[a-z]+://#i', '', $raw), '/'), '/');
        }

        if (empty($scheme)) {
            $scheme = 'https';
        }

        return $scheme . '://' . $host;
    }

    /** One POST to the API. JSON in, array out. */
    private function sendRequest($endpoint, $data)
    {
        // metadata has to arrive as a JSON object; a PHP list would encode as an
        // array and be rejected.
        if (isset($data['metadata'])) {
            $data['metadata'] = (object) $data['metadata'];
        }

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->baseUrl . $endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Accept: application/json',
                'Content-Type: application/json',
                'API-KEY: ' . $this->apiKey,
            ],
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : [];
    }
}
