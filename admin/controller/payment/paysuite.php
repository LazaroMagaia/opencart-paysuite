<?php
namespace Opencart\Admin\Controller\Extension\Paysuite\Payment;

class Paysuite extends \Opencart\System\Engine\Controller {
    public function index(): void {
        $this->load->language('extension/paysuite/payment/paysuite');

        $this->document->setTitle($this->language->get('heading_title'));

        $token = 'user_token=' . $this->session->data['user_token'];

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', $token)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'type=payment&' . $token)
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/paysuite/payment/paysuite', $token)
        ];

        $data['save'] = $this->url->link('extension/paysuite/payment/paysuite.save', $token);
        $data['back'] = $this->url->link('marketplace/extension', 'type=payment&' . $token);

        $fields = [
            'status'                    => 0,
            'token'                     => '',
            'webhook_secret'            => '',
            'method'                    => '',
            'api_url'                   => 'https://paysuite.tech/api/v1',
            'geo_zone_id'               => 0,
            'sort_order'                => '',
            'order_status_pending_id'   => 1,
            'order_status_completed_id' => 2,
            'order_status_failed_id'    => 10
        ];

        foreach ($fields as $key => $default) {
            $value = $this->config->get('payment_paysuite_' . $key);
            $data['payment_paysuite_' . $key] = ($value !== null && $value !== '') ? $value : $default;
        }

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $this->load->model('localisation/geo_zone');
        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        $data['webhook_url'] = HTTP_CATALOG . 'index.php?route=extension/paysuite/payment/paysuite.webhook';

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/paysuite/payment/paysuite', $data));
    }

    public function save(): void {
        $this->load->language('extension/paysuite/payment/paysuite');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/paysuite/payment/paysuite')) {
            $json['error']['warning'] = $this->language->get('error_permission');
        }

        if (!$json) {
            if (!empty($this->request->post['payment_paysuite_status'])) {
                if (empty(trim((string)$this->request->post['payment_paysuite_token']))) {
                    $json['error']['token'] = $this->language->get('error_token');
                }

                if (empty(trim((string)$this->request->post['payment_paysuite_webhook_secret']))) {
                    $json['error']['webhook_secret'] = $this->language->get('error_webhook_secret');
                }
            }
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('payment_paysuite', $this->request->post);

            $json['success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void {
        if ($this->user->hasPermission('modify', 'extension/payment')) {
            $this->load->model('extension/paysuite/payment/paysuite');

            $this->model_extension_paysuite_payment_paysuite->install();
        }
    }
}