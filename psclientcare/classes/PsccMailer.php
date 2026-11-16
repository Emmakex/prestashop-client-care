<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PsccMailer
{
    private $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    private static function h($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private function languageId($idLang)
    {
        $iso = (string) Language::getIsoById((int) $idLang);
        if (in_array($iso, array('es', 'en', 'ca', 'pt'), true)) {
            return (int) $idLang;
        }
        $fallback = (int) Language::getIdByIso('es');
        if (!$fallback) {
            $fallback = (int) Language::getIdByIso('en');
        }
        return $fallback ?: (int) $idLang;
    }

    private function send($langId, $template, $subject, $vars, $email)
    {
        if (!Validate::isEmail($email)) {
            return false;
        }
        try {
            return (bool) Mail::Send(
                $this->languageId($langId),
                $template,
                $subject,
                $vars,
                $email,
                null,
                null,
                null,
                null,
                null,
                $this->module->getLocalPath() . 'mails/'
            );
        } catch (Exception $e) {
            PrestaShopLogger::addLog('psclientcare: email failed: ' . $template, 2);
            return false;
        }
    }

    public function notifyStaff($ticket, $orderItems, $selectedProducts, $message)
    {
        $allHtml = array();
        $allText = array();
        $selectedIds = array();
        foreach ($selectedProducts as $p) {
            $selectedIds[] = (int) $p['id_order_detail'];
        }
        foreach ($orderItems as $item) {
            $description = (string) $item['product_name'] . ' x' . (int) $item['product_quantity'];
            if (!empty($item['product_reference'])) {
                $description .= ' [' . $item['product_reference'] . ']';
            }
            $isSelected = in_array((int) $item['id_order_detail'], $selectedIds, true);
            $allHtml[] = '<li>' . self::h($description) . ($isSelected ? ' <strong>(afectado)</strong>' : '') . '</li>';
            $allText[] = '- ' . $description . ($isSelected ? ' (afectado)' : '');
        }
        $vars = array(
            '{ticket_id}' => (int) $ticket['id_pscc_ticket'],
            '{order_ref}' => self::h($ticket['reference']),
            '{customer}' => self::h($ticket['firstname'] . ' ' . $ticket['lastname']),
            '{email}' => self::h($ticket['email']),
            '{subject}' => self::h($ticket['subject']),
            '{message_html}' => nl2br(self::h($message)),
            '{message_text}' => (string) $message,
            '{items_html}' => '<ul>' . implode('', $allHtml) . '</ul>',
            '{items_text}' => implode("\n", $allText),
        );
        return $this->send((int) Configuration::get('PS_LANG_DEFAULT'),
            'staff_notice', 'Incidencia #' . (int) $ticket['id_pscc_ticket'] . ' - pedido ' . $ticket['reference'],
            $vars, $this->module->getRecipient());
    }

    public function notifyCustomer($ticket, $message, $url)
    {
        $vars = array(
            '{ticket_id}' => (int) $ticket['id_pscc_ticket'],
            '{order_ref}' => self::h($ticket['reference']),
            '{message_html}' => nl2br(self::h($message)),
            '{message_text}' => (string) $message,
            '{ticket_url}' => self::h($url),
        );
        $order = new Order((int) $ticket['id_order']);
        $lang = Validate::isLoadedObject($order) ? (int) $order->id_lang : (int) Configuration::get('PS_LANG_DEFAULT');
        return $this->send($lang, 'customer_reply',
            'Respuesta a tu incidencia #' . (int) $ticket['id_pscc_ticket'], $vars, $ticket['email']);
    }
}
