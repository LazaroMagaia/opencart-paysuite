<?php
namespace Opencart\Catalog\Model\Extension\Paysuite\Payment;

class Paysuite extends \Opencart\System\Engine\Model {
    public function getMethods(array $address = []): array {
        $this->load->language('extension/paysuite/payment/paysuite');

        $geo_zone_id = (int)$this->config->get('payment_paysuite_geo_zone_id');

        if ($this->cart->hasSubscription()) {
            $status = false;
        } elseif (!$geo_zone_id) {
            $status = true;
        } else {
            $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "zone_to_geo_zone` WHERE `geo_zone_id` = '" . $geo_zone_id . "' AND `country_id` = '" . (int)($address['country_id'] ?? 0) . "' AND (`zone_id` = '" . (int)($address['zone_id'] ?? 0) . "' OR `zone_id` = '0')");

            $status = (bool)$query->num_rows;
        }

        $method_data = [];

        if ($status) {
            $option_data['paysuite'] = [
                'code' => 'paysuite.paysuite',
                'name' => $this->language->get('text_title')
            ];

            $method_data = [
                'code'       => 'paysuite',
                'name'       => $this->language->get('text_title'),
                'option'     => $option_data,
                'sort_order' => (int)$this->config->get('payment_paysuite_sort_order')
            ];
        }

        return $method_data;
    }

    public function addPayment(int $order_id, string $payment_id, string $reference, float $amount): void {
        $this->db->query("INSERT INTO `" . DB_PREFIX . "paysuite_payment` SET
            `order_id` = '" . (int)$order_id . "',
            `payment_id` = '" . $this->db->escape($payment_id) . "',
            `reference` = '" . $this->db->escape($reference) . "',
            `amount` = '" . (float)$amount . "',
            `status` = 'pending',
            `date_added` = NOW(),
            `date_modified` = NOW()");
    }

    public function getByReference(string $reference): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "paysuite_payment` WHERE `reference` = '" . $this->db->escape($reference) . "' LIMIT 1");

        return $query->row;
    }

    public function getByPaymentId(string $payment_id): array {
        $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "paysuite_payment` WHERE `payment_id` = '" . $this->db->escape($payment_id) . "' LIMIT 1");

        return $query->row;
    }

    public function transition(int $paysuite_payment_id, string $status): bool {
        $this->db->query("UPDATE `" . DB_PREFIX . "paysuite_payment` SET `status` = '" . $this->db->escape($status) . "', `date_modified` = NOW() WHERE `paysuite_payment_id` = '" . (int)$paysuite_payment_id . "' AND `status` != '" . $this->db->escape($status) . "'");

        return $this->db->countAffected() > 0;
    }
}