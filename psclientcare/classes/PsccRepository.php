<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

/** All order reads are shop-scoped; customer reads also require id_customer ownership. */
class PsccRepository
{
    public static function getCustomerOrders($customerId, $shopId, $limit = 80)
    {
        return Db::getInstance()->executeS('SELECT o.id_order, o.reference, o.date_add,
            osl.name AS state_name, mt.tracking_number AS manual_tracking, mt.tracking_url AS manual_tracking_url,
            (SELECT oc.tracking_number FROM `' . _DB_PREFIX_ . 'order_carrier` oc
                WHERE oc.id_order = o.id_order AND oc.tracking_number IS NOT NULL AND oc.tracking_number != \'\'
                ORDER BY oc.id_order_carrier DESC LIMIT 1) AS native_tracking
            FROM `' . _DB_PREFIX_ . 'orders` o
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                ON osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) Context::getContext()->language->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'pscc_tracking` mt ON mt.id_order = o.id_order AND mt.id_shop = o.id_shop
            WHERE o.id_customer = ' . (int) $customerId . ' AND o.id_shop = ' . (int) $shopId . '
            ORDER BY o.date_add DESC LIMIT ' . (int) $limit) ?: array();
    }

    public static function getCustomerOrder($orderId, $customerId, $shopId)
    {
        return Db::getInstance()->getRow('SELECT o.id_order, o.id_customer, o.id_shop, o.reference, o.date_add,
            osl.name AS state_name, mt.tracking_number AS manual_tracking, mt.tracking_url AS manual_tracking_url,
            (SELECT oc.tracking_number FROM `' . _DB_PREFIX_ . 'order_carrier` oc
                WHERE oc.id_order = o.id_order AND oc.tracking_number IS NOT NULL AND oc.tracking_number != \'\'
                ORDER BY oc.id_order_carrier DESC LIMIT 1) AS native_tracking
            FROM `' . _DB_PREFIX_ . 'orders` o
            LEFT JOIN `' . _DB_PREFIX_ . 'order_state_lang` osl
                ON osl.id_order_state = o.current_state AND osl.id_lang = ' . (int) Context::getContext()->language->id . '
            LEFT JOIN `' . _DB_PREFIX_ . 'pscc_tracking` mt ON mt.id_order = o.id_order AND mt.id_shop = o.id_shop
            WHERE o.id_order = ' . (int) $orderId . ' AND o.id_customer = ' . (int) $customerId . '
              AND o.id_shop = ' . (int) $shopId);
    }

    public static function getAdminOrder($orderId, $shopId)
    {
        return Db::getInstance()->getRow('SELECT o.id_order, o.id_customer, o.id_shop, o.reference, o.date_add,
            c.firstname, c.lastname, c.email
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
            WHERE o.id_order = ' . (int) $orderId . ' AND o.id_shop = ' . (int) $shopId);
    }

    public static function getOrderItems($orderId)
    {
        return Db::getInstance()->executeS('SELECT od.id_order_detail, od.product_name, od.product_reference,
            od.product_quantity, od.unit_price_tax_incl
            FROM `' . _DB_PREFIX_ . 'order_detail` od
            WHERE od.id_order = ' . (int) $orderId . '
            ORDER BY od.id_order_detail ASC') ?: array();
    }

    public static function getCustomerTickets($customerId, $shopId)
    {
        return Db::getInstance()->executeS('SELECT t.*, o.reference FROM `' . _DB_PREFIX_ . 'pscc_ticket` t
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = t.id_order
            WHERE t.id_customer = ' . (int) $customerId . ' AND t.id_shop = ' . (int) $shopId . '
              AND o.id_customer = t.id_customer AND o.id_shop = t.id_shop
            ORDER BY t.date_upd DESC LIMIT 100') ?: array();
    }

    public static function getTicket($ticketId, $shopId, $customerId = null)
    {
        $whereCustomer = $customerId === null ? '' : ' AND t.id_customer = ' . (int) $customerId;
        return Db::getInstance()->getRow('SELECT t.*, o.reference, c.email, c.firstname, c.lastname
            FROM `' . _DB_PREFIX_ . 'pscc_ticket` t
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = t.id_order AND o.id_customer = t.id_customer AND o.id_shop = t.id_shop
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = t.id_customer
            WHERE t.id_pscc_ticket = ' . (int) $ticketId . ' AND t.id_shop = ' . (int) $shopId . $whereCustomer);
    }

    public static function getTicketMessages($ticketId)
    {
        return Db::getInstance()->executeS('SELECT * FROM `' . _DB_PREFIX_ . 'pscc_message`
            WHERE id_pscc_ticket = ' . (int) $ticketId . ' ORDER BY id_pscc_message ASC') ?: array();
    }

    public static function getSelectedProducts($ticketId)
    {
        return Db::getInstance()->executeS('SELECT od.id_order_detail, od.product_name, od.product_reference, od.product_quantity
            FROM `' . _DB_PREFIX_ . 'pscc_ticket_product` tp
            INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.id_order_detail = tp.id_order_detail
            INNER JOIN `' . _DB_PREFIX_ . 'pscc_ticket` t ON t.id_pscc_ticket = tp.id_pscc_ticket AND t.id_order = od.id_order
            WHERE tp.id_pscc_ticket = ' . (int) $ticketId) ?: array();
    }

    /** The oldest active thread is canonical for each order; historic duplicates stay untouched. */
    public static function getActiveTicketForOrder($orderId, $customerId, $shopId)
    {
        return Db::getInstance()->getRow('SELECT t.id_pscc_ticket, t.status FROM `' . _DB_PREFIX_ . 'pscc_ticket` t
            WHERE t.id_order = ' . (int) $orderId . ' AND t.id_customer = ' . (int) $customerId . '
              AND t.id_shop = ' . (int) $shopId . " AND t.status IN ('open', 'in_progress')"
            . ' ORDER BY t.id_pscc_ticket ASC LIMIT 1', false);
    }

    /** Read-only consolidated timeline; preserves original ticket IDs, authors and timestamps. */
    public static function getOrderHistory($orderId, $customerId, $shopId)
    {
        return Db::getInstance()->executeS('SELECT m.id_pscc_message, m.sender, m.message, m.date_add,
                    t.id_pscc_ticket, t.subject, t.status
            FROM `' . _DB_PREFIX_ . 'pscc_message` m
            INNER JOIN `' . _DB_PREFIX_ . 'pscc_ticket` t ON t.id_pscc_ticket = m.id_pscc_ticket
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = t.id_order
            WHERE t.id_order = ' . (int) $orderId . ' AND t.id_customer = ' . (int) $customerId . '
              AND t.id_shop = ' . (int) $shopId . '
              AND o.id_customer = t.id_customer AND o.id_shop = t.id_shop
            ORDER BY m.date_add ASC, m.id_pscc_message ASC LIMIT 500') ?: array();
    }

    public static function archiveCustomerTicket($ticketId, $customerId, $shopId)
    {
        // A customer may close only their own active ticket; the record remains read-only history.
        return Db::getInstance()->update('pscc_ticket', array(
            'status' => 'archived', 'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pscc_ticket = ' . (int) $ticketId . ' AND id_customer = ' . (int) $customerId
            . ' AND id_shop = ' . (int) $shopId . " AND status IN ('open', 'in_progress')");
    }

    public static function getAdminTickets($shopId)
    {
        return Db::getInstance()->executeS('SELECT t.*, o.reference, c.email, c.firstname, c.lastname,
            (SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pscc_message` m WHERE m.id_pscc_ticket = t.id_pscc_ticket) AS message_count
            FROM `' . _DB_PREFIX_ . 'pscc_ticket` t
            INNER JOIN `' . _DB_PREFIX_ . 'orders` o ON o.id_order = t.id_order
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = t.id_customer
            WHERE t.id_shop = ' . (int) $shopId . ' AND o.id_shop = t.id_shop AND o.id_customer = t.id_customer
            ORDER BY (t.status IN (\'resolved\', \'archived\')) ASC, t.id_order DESC, t.date_upd DESC LIMIT 150') ?: array();
    }

    public static function getAdminOrders($shopId)
    {
        return Db::getInstance()->executeS('SELECT o.id_order, o.reference, o.date_add,
            c.firstname, c.lastname, mt.tracking_number AS manual_tracking, mt.tracking_url AS manual_tracking_url
            FROM `' . _DB_PREFIX_ . 'orders` o
            INNER JOIN `' . _DB_PREFIX_ . 'customer` c ON c.id_customer = o.id_customer
            LEFT JOIN `' . _DB_PREFIX_ . 'pscc_tracking` mt ON mt.id_order = o.id_order AND mt.id_shop = o.id_shop
            WHERE o.id_shop = ' . (int) $shopId . '
            ORDER BY o.date_add DESC LIMIT 30') ?: array();
    }

    public static function recentMessages($customerId, $minutes = 1)
    {
        return (int) Db::getInstance()->getValue('SELECT COUNT(*)
            FROM `' . _DB_PREFIX_ . 'pscc_message` m
            INNER JOIN `' . _DB_PREFIX_ . 'pscc_ticket` t ON t.id_pscc_ticket = m.id_pscc_ticket
            WHERE t.id_customer = ' . (int) $customerId . '
              AND m.sender = \'customer\'
              AND m.date_add >= DATE_SUB(NOW(), INTERVAL ' . (int) $minutes . ' MINUTE)');
    }

    /** Serialized by the order row lock: at most one new active ticket per order. */
    public static function createTicket($orderId, $customerId, $shopId, $subject, $message, $productIds, $requestKey)
    {
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            return false;
        }
        try {
            // Lock the owned order across concurrent requests (InnoDB).
            $locked = $db->getRow('SELECT o.id_order FROM `' . _DB_PREFIX_ . 'orders` o
                WHERE o.id_order = ' . (int) $orderId . ' AND o.id_customer = ' . (int) $customerId . '
                    AND o.id_shop = ' . (int) $shopId . ' FOR UPDATE', false);
            if (!$locked) {
                throw new Exception('Order not owned by customer');
            }
            if (!PsccSubmission::claim($db, $requestKey, $shopId, $customerId, 'create', $orderId)) {
                throw new Exception('Duplicate or invalid request key');
            }
            $active = self::getActiveTicketForOrder($orderId, $customerId, $shopId);
            $now = date('Y-m-d H:i:s');
            if ($active) {
                $ticketId = (int) $active['id_pscc_ticket'];
                $append = 'Asunto adicional: ' . $subject . "\n" . $message;
                $messageId = self::addMessage($ticketId, 'customer', $append, false);
                if (!$messageId) {
                    throw new Exception('Append to active ticket failed');
                }
                foreach ($productIds as $productId) {
                    $productId = (int) $productId;
                    if (!$db->execute('INSERT IGNORE INTO `' . _DB_PREFIX_ . 'pscc_ticket_product` (`id_pscc_ticket`, `id_order_detail`)
                        VALUES (' . $ticketId . ', ' . $productId . ')')) {
                        throw new Exception('Append selected product failed');
                    }
                }
                if (!$db->update('pscc_ticket', array('date_upd' => $now),
                    'id_pscc_ticket = ' . $ticketId . ' AND id_customer = ' . (int) $customerId . ' AND id_shop = ' . (int) $shopId)) {
                    throw new Exception('Ticket date update failed');
                }
            } else {
                if (!$db->insert('pscc_ticket', array(
                    'id_shop' => (int) $shopId,
                    'id_order' => (int) $orderId,
                    'id_customer' => (int) $customerId,
                    'subject' => pSQL($subject),
                    'status' => 'open',
                    'date_add' => $now,
                    'date_upd' => $now,
                ))) {
                    throw new Exception('Ticket insert failed');
                }
                $ticketId = (int) $db->Insert_ID();
                foreach ($productIds as $productId) {
                    if (!$db->insert('pscc_ticket_product', array(
                        'id_pscc_ticket' => $ticketId,
                        'id_order_detail' => (int) $productId,
                    ))) {
                        throw new Exception('Product insert failed');
                    }
                }
                $messageId = self::addMessage($ticketId, 'customer', $message, false);
                if (!$messageId) {
                    throw new Exception('Message insert failed');
                }
            }
            if (!PsccSubmission::finalize($db, $requestKey, $ticketId)) {
                throw new Exception('Submission finalization failed');
            }
            if (!$db->execute('COMMIT')) {
                throw new Exception('Commit failed');
            }
            return array('ticket_id' => $ticketId, 'message_id' => $messageId,
                'reused' => false, 'appended' => (bool) $active);
        } catch (\Throwable $e) {
            $db->execute('ROLLBACK');
            $existingId = PsccSubmission::find($requestKey, $shopId, $customerId, 'create', $orderId);
            return $existingId > 0 ? array('ticket_id' => $existingId, 'message_id' => 0, 'reused' => true) : false;
        }
    }

    /** Customer reply, atomic with its submission key and ticket status update. */
    public static function createReply($ticketId, $customerId, $shopId, $message, $requestKey, $currentStatus)
    {
        $db = Db::getInstance();
        if (!$db->execute('START TRANSACTION')) {
            return false;
        }
        try {
            if (!PsccSubmission::claim($db, $requestKey, $shopId, $customerId, 'reply', $ticketId)) {
                throw new Exception('Duplicate or invalid request key');
            }
            $current = $db->getRow('SELECT status FROM `' . _DB_PREFIX_ . 'pscc_ticket`
                WHERE id_pscc_ticket = ' . (int) $ticketId . ' AND id_customer = ' . (int) $customerId
                . ' AND id_shop = ' . (int) $shopId . ' FOR UPDATE', false);
            if (!$current || !in_array($current['status'], array('open', 'in_progress'), true)) {
                throw new Exception('Cannot reply to a closed case');
            }
            $status = $current['status'];
            $messageId = self::addMessage($ticketId, 'customer', $message, false);
            if (!$messageId) {
                throw new Exception('Reply insert failed');
            }
            if (!$db->update('pscc_ticket', array('status' => pSQL($status), 'date_upd' => date('Y-m-d H:i:s')),
                'id_pscc_ticket = ' . (int) $ticketId . ' AND id_customer = ' . (int) $customerId . ' AND id_shop = ' . (int) $shopId)
                || !PsccSubmission::finalize($db, $requestKey, $messageId)) {
                throw new Exception('Reply finalization failed');
            }
            if (!$db->execute('COMMIT')) {
                throw new Exception('Commit failed');
            }
            return array('message_id' => $messageId, 'reused' => false);
        } catch (\Throwable $e) {
            $db->execute('ROLLBACK');
            $existingId = PsccSubmission::find($requestKey, $shopId, $customerId, 'reply', $ticketId);
            return $existingId > 0 ? array('message_id' => $existingId, 'reused' => true) : false;
        }
    }

    public static function addMessage($ticketId, $sender, $message, $touch = true)
    {
        $db = Db::getInstance();
        if (!$db->insert('pscc_message', array(
            'id_pscc_ticket' => (int) $ticketId,
            'sender' => $sender === 'employee' ? 'employee' : 'customer',
            'message' => pSQL($message, true),
            'email_status' => 'pending',
            'date_add' => date('Y-m-d H:i:s'),
        ))) {
            return false;
        }
        $id = (int) $db->Insert_ID();
        if ($touch) {
            $db->update('pscc_ticket', array('date_upd' => date('Y-m-d H:i:s')),
                'id_pscc_ticket = ' . (int) $ticketId);
        }
        return $id;
    }

    public static function setMessageEmailStatus($messageId, $success)
    {
        return Db::getInstance()->update('pscc_message',
            array('email_status' => $success ? 'sent' : 'failed'),
            'id_pscc_message = ' . (int) $messageId);
    }

    public static function setTicketStatus($ticketId, $status)
    {
        if (!in_array($status, array('open', 'in_progress', 'resolved', 'archived'), true)) {
            return false;
        }
        return Db::getInstance()->update('pscc_ticket', array(
            'status' => pSQL($status),
            'date_upd' => date('Y-m-d H:i:s'),
        ), 'id_pscc_ticket = ' . (int) $ticketId);
    }

    /** Only safe HTTP(S) links may be exposed to customers. Empty means no link. */
    public static function isValidTrackingUrl($url)
    {
        if ($url === '') {
            return true;
        }
        if (strlen($url) > 2048 || preg_match('/[\x00-\x20\x7F]/', $url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        $parts = parse_url($url);
        return is_array($parts) && !empty($parts['host'])
            && isset($parts['scheme']) && in_array(strtolower($parts['scheme']), array('http', 'https'), true)
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    public static function setTracking($orderId, $shopId, $code, $url, $employeeId)
    {
        // Future carrier synchronization can write the same table with source='automatic'.
        return Db::getInstance()->insert('pscc_tracking', array(
            'id_order' => (int) $orderId,
            'id_shop' => (int) $shopId,
            'tracking_number' => pSQL($code),
            'tracking_url' => pSQL($url),
            'source' => 'manual',
            'id_employee' => (int) $employeeId,
            'date_upd' => date('Y-m-d H:i:s'),
        ), false, true, Db::REPLACE);
    }
}
