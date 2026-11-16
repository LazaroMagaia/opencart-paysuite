<?php
namespace Opencart\Catalog\Controller\Extension\Paysuite\Payment;

class Paysuite extends \Opencart\System\Engine\Controller {
    private const DEFAULT_API_URL = 'https://paysuite.tech/api/v1';

    public function index(): string {
        $this->load->language('extension/paysuite/payment/paysuite');

        $data['confirm'] = $this->link('extension/paysuite/payment/paysuite.confirm', 'language=' . $this->config->get('config_language'));

        return $this->load->view('extension/paysuite/payment/paysuite', $data);
    }

    public function confirm(): void {
        $this->load->language('extension/paysuite/payment/paysuite');
        $this->load->model('extension/paysuite/payment/paysuite');
        $this->load->model('checkout/order');

        $json = [];
        $order_info = [];

        if (!isset($this->session->data['order_id'])) {
            $json['error'] = $this->language->get('error_order');
        }

        if (!$json && (!isset($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] != 'paysuite.paysuite')) {
            $json['error'] = $this->language->get('error_payment_method');
        }

        if (!$json) {
            $order_info = $this->model_checkout_order->getOrder((int)$this->session->data['order_id']);

            if (!$order_info) {
                $json['error'] = $this->language->get('error_order');
            }
        }

        $amount = 0.0;

        if (!$json) {
            if (!$this->currency->has('MZN')) {
                $json['error'] = $this->language->get('error_currency');
            } else {
                $amount = round((float)$this->currency->convert($order_info['total'], $this->config->get('config_currency'), 'MZN'), 2);

                if ($amount < 1) {
                    $json['error'] = $this->language->get('error_amount');
                }
            }
        }

        if (!$json) {
            $order_id = (int)$order_info['order_id'];
            $language = $this->config->get('config_language');

            // Só letras e números, máximo 50 caracteres
            $reference = 'OC' . $order_id . 'X' . substr(bin2hex(random_bytes(8)), 0, 12);

            $body = [
                'amount'      => number_format($amount, 2, '.', ''),
                'reference'   => $reference,
                'description' => mb_substr('Pedido #' . $order_id . ' - ' . $this->config->get('config_name'), 0, 125)
            ];

            $return_url = $this->link('extension/paysuite/payment/paysuite.callback', 'language=' . $language . '&reference=' . $reference);
            $webhook_url = $this->link('extension/paysuite/payment/paysuite.webhook');

            if (!$this->isLocal($return_url)) {
                $body['return_url'] = $return_url;
            }

            if (!$this->isLocal($webhook_url)) {
                $body['webhook_url'] = $webhook_url;
            }

            $method = (string)$this->config->get('payment_paysuite_method');

            if (in_array($method, ['mpesa', 'emola', 'credit_card'], true)) {
                $body['method'] = $method;
            }

            // Contacto: procura pelo email; se não existir, cria
            $contact_id = $this->getContactId($order_info);

            if ($contact_id) {
                $body['contact_id'] = $contact_id;
            }

            $response = $this->api('POST', '/payments', $body);

            $payment = $response['data']['data'] ?? [];

            if ($this->isSuccess($response) && !empty($payment['id']) && !empty($payment['checkout_url'])) {
                $this->model_extension_paysuite_payment_paysuite->addPayment($order_id, (string)$payment['id'], $reference, $amount);

                $this->model_checkout_order->addHistory(
                    $order_id,
                    $this->statusId('pending'),
                    sprintf($this->language->get('text_comment_pending'), $reference),
                    false
                );

                $json['redirect'] = str_replace('&amp;', '&', $payment['checkout_url']);
            } else {
                $this->log->write('PaySuite: failed to create payment. HTTP ' . $response['http'] . ' ' . json_encode($response['data']) . ' ' . $response['error']);

                $json['error'] = $this->language->get('error_gateway');
            }
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

        /**
     * O cliente volta do PaySuite (return_url).
     * Não confiamos no URL: só a reference serve para achar o pagamento,
     * o estado real vem da API.
     */
    public function callback(): void {
        $this->load->model('extension/paysuite/payment/paysuite');

        $language = $this->config->get('config_language');
        $reference = (string)($this->request->get['reference'] ?? '');

        $row = $reference ? $this->model_extension_paysuite_payment_paysuite->getByReference($reference) : [];

        if (!$row) {
            $this->response->redirect($this->link('checkout/failure', 'language=' . $language));

            return;
        }

        $status = $this->syncPayment($row);

        if ($status === 'failed') {
            $this->response->redirect($this->link('checkout/failure', 'language=' . $language));
        } else {
            $this->response->redirect($this->link('checkout/success', 'language=' . $language));
        }
    }

    /**
     * Notificação do PaySuite.
     * X-Signature = HMAC-SHA256 do corpo bruto, com o webhook secret.
     */
    public function webhook(): void {
        $payload = file_get_contents('php://input');
        $signature = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
        $secret = (string)$this->config->get('payment_paysuite_webhook_secret');

        if ($secret === '' || $signature === '' || !hash_equals(hash_hmac('sha256', $payload, $secret), $signature)) {
            $this->response->addHeader('HTTP/1.1 401 Unauthorized');
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode(['status' => 'error', 'message' => 'Invalid signature']));

            return;
        }

        $this->load->model('extension/paysuite/payment/paysuite');

        $event = json_decode($payload, true);

        if (is_array($event)) {
            // A documentação não mostra o formato do evento: procuramos o id em vários sítios
            $payment_id = (string)($event['data']['id'] ?? $event['data']['payment_id'] ?? $event['payment_id'] ?? $event['id'] ?? '');
            $reference = (string)($event['data']['reference'] ?? $event['reference'] ?? '');

            $row = [];

            if ($payment_id) {
                $row = $this->model_extension_paysuite_payment_paysuite->getByPaymentId($payment_id);
            }

            if (!$row && $reference) {
                $row = $this->model_extension_paysuite_payment_paysuite->getByReference($reference);
            }

            if ($row) {
                $this->syncPayment($row);
            }
        }

        // Responder depressa e com 200 (o PaySuite espera resposta em 5 segundos)
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode(['status' => 'success']));
    }

    // ------------------------------------------------------------------
    // Contactos
    // ------------------------------------------------------------------

    /**
     * Devolve o id do contacto PaySuite do cliente do pedido.
     * 1) procura pelo email  2) se não existir, cria.
     * Devolve '' se não for possível (o pagamento segue sem contacto).
     */
    private function getContactId(array $order_info): string {
        $email = trim((string)$order_info['email']);
        $name = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
        $phone = $this->normalizePhone((string)$order_info['telephone']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = '';
        }

        if ($email) {
            $contact_id = $this->findContactByEmail($email);

            // null = erro na API: não criamos, para não gerar duplicados
            if ($contact_id === null) {
                return '';
            }

            if ($contact_id !== '') {
                return $contact_id;
            }
        }

        if (!$email && !$phone) {
            return '';
        }

        return $this->createContact($name, $email, $phone);
    }

    /**
     * GET /contacts?email=...
     * Devolve o id se existir um contacto com este email, '' se não existir,
     * ou null se a chamada à API falhar.
     */
    private function findContactByEmail(string $email): ?string {
        $response = $this->api('GET', '/contacts', [], ['email' => $email, 'limit' => 100]);

        if (!$this->isSuccess($response)) {
            $this->log->write('PaySuite: could not search contact. HTTP ' . $response['http'] . ' ' . json_encode($response['data']) . ' ' . $response['error']);

            return null;
        }

        foreach (($response['data']['data'] ?? []) as $contact) {
            if (isset($contact['id'], $contact['email']) && strcasecmp($contact['email'], $email) === 0) {
                return (string)$contact['id'];
            }
        }

        return '';
    }

    /**
     * POST /contacts. Se falhar com telefone, repete só com o email.
     */
    private function createContact(string $name, string $email, string $phone): string {
        $body = ['name' => mb_substr($name ?: 'Cliente', 0, 100)];

        if ($email) {
            $body['email'] = mb_substr($email, 0, 100);
        }

        if ($phone) {
            $body['phone'] = $phone;
        }

        $response = $this->api('POST', '/contacts', $body);

        if ($this->isSuccess($response) && !empty($response['data']['data']['id'])) {
            return (string)$response['data']['data']['id'];
        }

        $this->log->write('PaySuite: could not create contact. HTTP ' . $response['http'] . ' ' . json_encode($response['data']) . ' ' . $response['error']);

        if ($phone && $email) {
            unset($body['phone']);

            $response = $this->api('POST', '/contacts', $body);

            if ($this->isSuccess($response) && !empty($response['data']['data']['id'])) {
                return (string)$response['data']['data']['id'];
            }

            $this->log->write('PaySuite: could not create contact without phone. HTTP ' . $response['http'] . ' ' . json_encode($response['data']));
        }

        return '';
    }

    /**
     * Números de Moçambique para E.164 (+258XXXXXXXXX). '' se não for possível.
     */
    private function normalizePhone(string $phone): string {
        $digits = preg_replace('/\D+/', '', $phone);

        if (strlen($digits) === 9 && $digits[0] === '8') {
            return '+258' . $digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '258')) {
            return '+' . $digits;
        }

        return '';
    }

    // ------------------------------------------------------------------
    // Auxiliares
    // ------------------------------------------------------------------

    private function statusId(string $type): int {
        $defaults = ['pending' => 1, 'completed' => 2, 'failed' => 10];

        return (int)$this->config->get('payment_paysuite_order_status_' . $type . '_id') ?: $defaults[$type];
    }

    private function isLocal(string $url): bool {
        return (bool)preg_match('~//(localhost|127\.0\.0\.1|[^/:]*\.local)(:|/|$)~i', $url);
    }

    private function isSuccess(array $response): bool {
        return $response['http'] >= 200 && $response['http'] < 300 && ($response['data']['status'] ?? '') === 'success';
    }

    private function api(string $method, string $endpoint, array $body = [], array $query = []): array {
        $base = rtrim((string)($this->config->get('payment_paysuite_api_url') ?: self::DEFAULT_API_URL), '/');
        $url = $base . $endpoint;

        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $curl = curl_init($url);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->config->get('payment_paysuite_token'),
            'Content-Type: application/json',
            'Accept: application/json'
        ]);

        if ($body) {
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $result = curl_exec($curl);
        $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);

        $data = is_string($result) ? json_decode($result, true) : null;

        return [
            'http'  => $http,
            'data'  => is_array($data) ? $data : [],
            'error' => $error
        ];
    }

    private function link(string $route, string $args = ''): string {
        return str_replace('&amp;', '&', $this->url->link($route, $args));
    }

        /**
     * Vai buscar o pagamento ao PaySuite e actualiza a nossa tabela e o pedido.
     * Idempotente. Devolve o estado local: pending | completed | failed
     */
    private function syncPayment(array $row): string {
        if ($row['status'] === 'completed') {
            return 'completed';
        }

        $response = $this->api('GET', '/payments/' . rawurlencode($row['payment_id']));

        if (!$this->isSuccess($response)) {
            $this->log->write('PaySuite: could not fetch payment ' . $row['payment_id'] . '. HTTP ' . $response['http'] . ' ' . $response['error']);

            return $row['status'];
        }

        $remote = $response['data']['data'] ?? [];
        $remote_status = strtolower((string)($remote['status'] ?? ''));

        if ($remote_status === 'completed') {
            $new = 'completed';
        } elseif (in_array($remote_status, ['failed', 'cancelled', 'canceled', 'expired', 'declined'], true)) {
            $new = 'failed';
        } else {
            return $row['status'];
        }

        // O valor confirmado tem de bater com o que enviámos
        if ($new === 'completed' && isset($remote['amount']) && abs((float)$remote['amount'] - (float)$row['amount']) > 0.01) {
            $this->log->write('PaySuite: amount mismatch on payment ' . $row['payment_id'] . ' (expected ' . $row['amount'] . ', got ' . $remote['amount'] . ')');

            return $row['status'];
        }

        // Só quem conseguir mudar o estado regista o histórico (evita duplicados)
        if ($this->model_extension_paysuite_payment_paysuite->transition((int)$row['paysuite_payment_id'], $new)) {
            $this->load->language('extension/paysuite/payment/paysuite');
            $this->load->model('checkout/order');

            $comment = sprintf($this->language->get($new === 'completed' ? 'text_comment_paid' : 'text_comment_failed'), $row['reference']);

            $this->model_checkout_order->addHistory((int)$row['order_id'], $this->statusId($new), $comment, $new === 'completed');
        }

        return $new;
    }
}