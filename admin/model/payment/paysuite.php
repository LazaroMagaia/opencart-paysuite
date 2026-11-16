<?php
namespace Opencart\Admin\Model\Extension\Paysuite\Payment;

class Paysuite extends \Opencart\System\Engine\Model {
    public function install(): void {
        $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "paysuite_payment` (
            `paysuite_payment_id` INT(11) NOT NULL AUTO_INCREMENT,
            `order_id` INT(11) NOT NULL,
            `payment_id` VARCHAR(40) NOT NULL,
            `reference` VARCHAR(50) NOT NULL,
            `amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            `status` VARCHAR(16) NOT NULL DEFAULT 'pending',
            `date_added` DATETIME NOT NULL,
            `date_modified` DATETIME NOT NULL,
            PRIMARY KEY (`paysuite_payment_id`),
            UNIQUE KEY `reference` (`reference`),
            KEY `payment_id` (`payment_id`),
            KEY `order_id` (`order_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }
}