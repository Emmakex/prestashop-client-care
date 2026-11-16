<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Durable, database-backed idempotency for customer POST operations.
 * The request key is not an authentication token; ownership and the
 * PrestaShop form CSRF token are checked independently by the controller.
 */
class PsccSubmission
{
    public static function newKey()
    {
        return bin2hex(random_bytes(16));
    }

    public static function validKey($key)
    {
        return is_string($key) && (bool) preg_match('/\A[a-f0-9]{32}\z/D', $key);
    }

    public static function installSchema()
    {
        return Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS \``' . _DB_PREFIX_ . 'pscc_submission\` (
            `request_key` CHAR(32) NOT NULL,
            `id_shop` INT UNSIGNED NOT NULL,
            `id_customer` INT UNSIGNED NOT NULL,
            `action` VARCHAR(8) NOT NULL,
            `id_target` INT UNSIGNED NOT NULL,
            `id_result` INT UNSIGNED NOT NULL DEFAULT 0,
            `date_add` DATETIME NOT NULL,
            PRIMARY KEY (`request_key`),
            KEY `customer_shop` (`id_customer`, `id_shop`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8mb4');
    }

    public static function find($key, $shopId, $customerId, $action, $targetId)
    {
        if (!self::validKey($key) || !in_array($action, array('create', 'reply'), true)) {
            return 0;
        }
        return (int) Db::getInstance()->getValue('SELECT id_result FROM `' . _DB_PREFIX_ . 'pscc_submission`
            WHERE request_key = \'' . pSQL($key) . '\'
                AND id_shop = ' . (int) $shopId . '
                AND id_customer = ' . (int) $customerId . '
                AND action = \'' . pSQL($action) . '\'
                AND id_target = ' . (int) $targetId);
    }

    /** Must be called from within the same transaction as the actual write. */
    public static function claim($db, $key, $shopId, $customerId, $action, $targetId)
    {
        return self::validKey($key) && $db->insert('pscc_submission', array(
            'request_key' => pSQL($key),
            'id_shop' => (int) $shopId,
            'id_customer' => (int) $customerId,
            'action' => pSQL($action),
            'id_target' => (int) $targetId,
            'id_result' => 0,
            'date_add' => date('Y-m-d H:i:s'),
        ));
    }

    public static function finalize($db, $key, $resultId)
    {
        return $resultId > 0 && $db->update('pscc_submission', array('id_result' => (int) $resultId),
            'request_key = \'' . pSQL($key) . '\' AND id_result = 0');
    }
}
