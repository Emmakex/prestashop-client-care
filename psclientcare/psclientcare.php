<?php
/**
 * PS Client Care — incidencias y seguimiento de pedidos.
 * License: MIT. Independent PrestaShop module; no core overrides.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once __DIR__ . '/classes/PsccRepository.php';
require_once __DIR__ . '/classes/PsccMailer.php';
require_once __DIR__ . '/classes/PsccAdmin.php';

class Psclientcare extends Module
{
    const CONFIG_RECIPIENT = 'PSCC_RECIPIENT';

    public function __construct()
    {
        $this->name = 'psclientcare';
        $this->tab = 'front_office_features';
        $this->version = '0.1.3';
        $this->author = 'Eduardo Yauri';
        $this->author_uri = 'https://www.eduardoyauriluna.com';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.8.0', 'max' => '9.99.99');
        parent::__construct();
        $this->displayName = $this->l('Seguimiento de Pedido');
        $this->description = $this->l('Incidencias asociadas a pedidos y seguimiento manual, desde la cuenta del cliente.');
        $this->confirmUninstall = $this->l('Las incidencias existentes se conservarán en la base de datos por seguridad.');
    }

    public function install()
    {
        return parent::install()
            && $this->installSchema()
            && $this->registerHook('displayCustomerAccount')
            && Configuration::updateValue(self::CONFIG_RECIPIENT, Configuration::get('PS_SHOP_EMAIL'));
    }

    public function uninstall()
    {
        // Customer service records are deliberately preserved. See README for retention and removal.
        return Configuration::deleteByName(self::CONFIG_RECIPIENT) && parent::uninstall();
    }

    private function installSchema()
    {
        $engine = _MYSQL_ENGINE_;
        $queries = array(
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pscc_ticket` (
                `id_pscc_ticket` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` INT UNSIGNED NOT NULL,
                `id_order` INT UNSIGNED NOT NULL,
                `id_customer` INT UNSIGNED NOT NULL,
                `subject` VARCHAR(200) NOT NULL,
                `status` VARCHAR(20) NOT NULL DEFAULT \'open\',
                `date_add` DATETIME NOT NULL,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_pscc_ticket`),
                KEY `customer_shop` (`id_customer`, `id_shop`),
                KEY `order_key` (`id_order`),
                KEY `shop_status` (`id_shop`, `status`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pscc_message` (
                `id_pscc_message` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_pscc_ticket` INT UNSIGNED NOT NULL,
                `sender` VARCHAR(12) NOT NULL,
                `message` TEXT NOT NULL,
                `email_status` VARCHAR(12) NOT NULL DEFAULT \'pending\',
                `date_add` DATETIME NOT NULL,
                PRIMARY KEY (`id_pscc_message`),
                KEY `ticket_date` (`id_pscc_ticket`, `date_add`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pscc_ticket_product` (
                `id_pscc_ticket` INT UNSIGNED NOT NULL,
                `id_order_detail` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`id_pscc_ticket`, `id_order_detail`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
            'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'pscc_tracking` (
                `id_order` INT UNSIGNED NOT NULL,
                `id_shop` INT UNSIGNED NOT NULL,
                `tracking_number` VARCHAR(160) NOT NULL,
                `tracking_url` VARCHAR(2048) NOT NULL DEFAULT \'\',
                `source` VARCHAR(20) NOT NULL DEFAULT \'manual\',
                `id_employee` INT UNSIGNED NOT NULL DEFAULT 0,
                `date_upd` DATETIME NOT NULL,
                PRIMARY KEY (`id_order`),
                KEY `shop_key` (`id_shop`)
            ) ENGINE=' . $engine . ' DEFAULT CHARSET=utf8mb4',
        );
        foreach ($queries as $query) {
            if (!Db::getInstance()->execute($query)) {
                return false;
            }
        }
        return $this->ensureTrackingUrlSchema();
    }

    /** Also handles a fresh install over tables retained from an older uninstall. */
    public function ensureTrackingUrlSchema()
    {
        $table = _DB_PREFIX_ . 'pscc_tracking';
        $columns = Db::getInstance()->executeS("SHOW COLUMNS FROM `" . $table . "` LIKE 'tracking_url'");
        if ($columns === false) {
            return false;
        }
        if (!empty($columns)) {
            return true;
        }

        return Db::getInstance()->execute("ALTER TABLE `" . $table . "` ADD `tracking_url` VARCHAR(2048) NOT NULL DEFAULT '' AFTER `tracking_number`");
    }

    public function hookDisplayCustomerAccount($params)
    {
        if (!$this->context->customer || !$this->context->customer->isLogged()) {
            return '';
        }
        $this->context->smarty->assign(array(
            'pscc_url' => $this->context->link->getModuleLink($this->name, 'portal', array(), true),
        ));
        return $this->fetch('module:psclientcare/views/templates/hook/account.tpl');
    }

    public function getContent()
    {
        return PsccAdmin::render($this);
    }

    public function getRecipient()
    {
        $recipient = (string) Configuration::get(self::CONFIG_RECIPIENT);
        return Validate::isEmail($recipient) ? $recipient : (string) Configuration::get('PS_SHOP_EMAIL');
    }

    public function getMailer()
    {
        return new PsccMailer($this);
    }
}
