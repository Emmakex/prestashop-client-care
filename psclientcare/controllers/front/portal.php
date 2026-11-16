<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PsclientcarePortalModuleFrontController extends ModuleFrontController
{
    public $auth = true;
    public $guestAllowed = false;
    public $ssl = true;

    private $psccErrors = array();

    public function setMedia()
    {
        parent::setMedia();
        $this->registerStylesheet('pscc-customer', 'modules/' . $this->module->name . '/views/css/portal.css',
            array('media' => 'all', 'priority' => 150));
    }

    public function postProcess()
    {
        parent::postProcess();
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !Tools::isSubmit('pscc_action')) {
            return;
        }
        if (!$this->context->customer->isLogged()) {
            $this->psccErrors[] = 'Debes iniciar sesión.';
            return;
        }
        $submitted = (string) Tools::getValue('pscc_token', '');
        if (!$submitted || !hash_equals((string) Tools::getToken(false), $submitted)) {
            $this->psccErrors[] = 'La sesión del formulario no es válida. Actualiza la página.';
            return;
        }
        $action = (string) Tools::getValue('pscc_action');
        if ($action === 'archive') {
            $ticketId = (int) Tools::getValue('ticket_id');
            $ticket = PsccRepository::getTicket($ticketId, (int) $this->context->shop->id,
                (int) $this->context->customer->id);
            if (!$ticket) {
                $this->psccErrors[] = 'Incidencia no encontrada en tu cuenta.';
                return;
            }
            if (in_array($ticket['status'], array('archived', 'resolved'), true)) {
                $this->redirectTicket($ticketId, 'archived');
            }
            if (!PsccRepository::archiveCustomerTicket($ticketId, (int) $this->context->customer->id,
                (int) $this->context->shop->id)) {
                $this->psccErrors[] = 'No se ha podido archivar la incidencia.';
                return;
            }
            $this->redirectTicket($ticketId, 'archived');
        }
        $key = (string) Tools::getValue('pscc_request_key', '');
        if (!in_array($action, array('create', 'reply'), true) || !PsccSubmission::validKey($key)) {
            $this->psccErrors[] = 'El formulario ha caducado. Vuelve a abrirlo para enviar el mensaje.';
            return;
        }

        // A browser refresh or a repeated POST must not send a second ticket
        // or message. This check comes BEFORE the rate limit for safe retries.
        $target = (int) Tools::getValue($action === 'create' ? 'id_order' : 'ticket_id');
        $shopId = (int) $this->context->shop->id;
        $customerId = (int) $this->context->customer->id;
        $existing = PsccSubmission::find($key, $shopId, $customerId, $action, $target);
        if ($existing > 0) {
            $this->redirectTicket($action === 'create' ? $existing : $target, 'already_saved');
        }
        if (PsccRepository::recentMessages($customerId) >= 3) {
            $this->psccErrors[] = 'Se han enviado demasiados mensajes. Inténtalo más tarde.';
            return;
        }
        if ($action === 'create') {
            $this->createTicket($key);
        } else {
            $this->replyTicket($key);
        }
    }

    private function redirectTicket($ticketId, $notice)
    {
        Tools::redirect($this->context->link->getModuleLink($this->module->name, 'portal', array(
            'ticket_id' => (int) $ticketId, 'pscc_notice' => $notice,
        ), true));
        exit; // explicit even when a test implementation of Tools::redirect() returns
    }

    /** Notify after committing the ticket. Failures must never turn a successful POST into a 500. */
    private function sendStaffNotice($ticket, $items, $selected, $message, $messageId)
    {
        $sent = false;
        try {
            if (is_array($ticket) && $ticket) {
                $sent = (bool) $this->module->getMailer()->notifyStaff($ticket, $items, $selected, $message);
            }
        } catch (\Throwable $e) {
            // No ticket content, customer data or credentials in the error log.
            try {
                PrestaShopLogger::addLog('psclientcare: mail notification failed after ticket persistence', 2);
            } catch (\Throwable $logError) {
                // Preserve a successful submission even if logging fails.
            }
        }
        try {
            PsccRepository::setMessageEmailStatus((int) $messageId, $sent);
        } catch (\Throwable $e) {
            // Email state update must not block PRG response.
        }
        return $sent;
    }

    private function normalizedMessage()
    {
        return trim(strip_tags((string) Tools::getValue('pscc_message', '')));
    }

    private function validMessage($message)
    {
        if (Tools::strlen($message) < 10 || Tools::strlen($message) > 4000) {
            $this->psccErrors[] = 'El mensaje debe tener entre 10 y 4000 caracteres.';
            return false;
        }
        return true;
    }

    private function createTicket($requestKey)
    {
        $orderId = (int) Tools::getValue('id_order');
        $shopId = (int) $this->context->shop->id;
        $customerId = (int) $this->context->customer->id;
        $order = PsccRepository::getCustomerOrder($orderId, $customerId, $shopId);
        if (!$order) {
            $this->psccErrors[] = 'No se ha encontrado este pedido en tu cuenta.';
            return;
        }
        $subject = trim(strip_tags((string) Tools::getValue('pscc_subject', '')));
        $message = $this->normalizedMessage();
        if (Tools::strlen($subject) < 4 || Tools::strlen($subject) > 200) {
            $this->psccErrors[] = 'Indica un asunto entre 4 y 200 caracteres.';
        }
        if (!$this->validMessage($message)) {
            return;
        }
        $submittedIds = Tools::getValue('pscc_products', array());
        if (!is_array($submittedIds) || count($submittedIds) > 200) {
            $this->psccErrors[] = 'Selección de productos no válida.';
            return;
        }
        $items = PsccRepository::getOrderItems($orderId);
        $allowedIds = array();
        foreach ($items as $item) {
            $allowedIds[(int) $item['id_order_detail']] = true;
        }
        $productIds = array();
        foreach ($submittedIds as $id) {
            if (!is_scalar($id) || !ctype_digit((string) $id) || !isset($allowedIds[(int) $id])) {
                $this->psccErrors[] = 'Has seleccionado un producto que no pertenece al pedido.';
                return;
            }
            $productIds[(int) $id] = (int) $id;
        }
        if ($this->psccErrors) {
            return;
        }
        $result = PsccRepository::createTicket($orderId, $customerId, $shopId, $subject, $message, array_values($productIds), $requestKey);
        if (!$result) {
            $this->psccErrors[] = 'No se ha podido guardar la incidencia. Vuelve a intentarlo.';
            return;
        }
        if (!empty($result['reused'])) {
            $this->redirectTicket($result['ticket_id'], 'already_saved');
        }
        $sent = false;
        try {
            $ticket = PsccRepository::getTicket($result['ticket_id'], $shopId, $customerId);
            $selectedProducts = PsccRepository::getSelectedProducts($result['ticket_id']);
            $sent = $this->sendStaffNotice($ticket, $items, $selectedProducts, $message, $result['message_id']);
        } catch (\Throwable $e) {
            try {
                PsccRepository::setMessageEmailStatus($result['message_id'], false);
            } catch (\Throwable $ignored) {
            }
        }
        $this->redirectTicket($result['ticket_id'], $sent ? (!empty($result['appended']) ? 'appended' : 'created') : 'saved_no_mail');
    }

    private function replyTicket($requestKey)
    {
        $ticketId = (int) Tools::getValue('ticket_id');
        $ticket = PsccRepository::getTicket($ticketId, (int) $this->context->shop->id, (int) $this->context->customer->id);
        if (!$ticket) {
            $this->psccErrors[] = 'Incidencia no encontrada en tu cuenta.';
            return;
        }
        if (!in_array($ticket['status'], array('open', 'in_progress'), true)) {
            $this->psccErrors[] = 'Esta incidencia está cerrada y archivada; no admite respuestas.';
            return;
        }
        $message = $this->normalizedMessage();
        if (!$this->validMessage($message)) {
            return;
        }
        $result = PsccRepository::createReply($ticketId, (int) $this->context->customer->id,
            (int) $this->context->shop->id, $message, $requestKey, (string) $ticket['status']);
        if (!$result) {
            $this->psccErrors[] = 'No se ha podido guardar tu mensaje.';
            return;
        }
        if (!empty($result['reused'])) {
            $this->redirectTicket($ticketId, 'already_saved');
        }
        $sent = false;
        try {
            $items = PsccRepository::getOrderItems($ticket['id_order']);
            $selected = PsccRepository::getSelectedProducts($ticketId);
            $sent = $this->sendStaffNotice($ticket, $items, $selected, $message, $result['message_id']);
        } catch (\Throwable $e) {
            try {
                PsccRepository::setMessageEmailStatus($result['message_id'], false);
            } catch (\Throwable $ignored) {
            }
        }
        $this->redirectTicket($ticketId, $sent ? 'reply' : 'saved_no_mail');
    }

    public function initContent()
    {
        parent::initContent();
        $shopId = (int) $this->context->shop->id;
        $customerId = (int) $this->context->customer->id;
        $selectedOrder = false;
        $items = array();
        $selectedTicket = false;
        $thread = array();
        $selectedProducts = array();
        $orderGroups = array();
        $activeForOrder = false;
        $ticketId = (int) Tools::getValue('ticket_id');
        if ($ticketId > 0) {
            $selectedTicket = PsccRepository::getTicket($ticketId, $shopId, $customerId);
            if (!$selectedTicket) {
                $this->psccErrors[] = 'La incidencia solicitada no existe en tu cuenta.';
            } else {
                $thread = PsccRepository::getOrderHistory((int) $selectedTicket['id_order'], $customerId, $shopId);
                $selectedProducts = PsccRepository::getSelectedProducts($ticketId);
            }
        }
        $orderId = (int) Tools::getValue('id_order');
        if ($orderId > 0) {
            $selectedOrder = PsccRepository::getCustomerOrder($orderId, $customerId, $shopId);
            if (!$selectedOrder) {
                $this->psccErrors[] = 'El pedido solicitado no pertenece a tu cuenta.';
            } else {
                $items = PsccRepository::getOrderItems($orderId);
                $activeForOrder = PsccRepository::getActiveTicketForOrder($orderId, $customerId, $shopId);
                if ($activeForOrder) {
                    $activeForOrder['portal_url'] = $this->context->link->getModuleLink($this->module->name,
                        'portal', array('ticket_id' => (int) $activeForOrder['id_pscc_ticket']), true);
                }
            }
        }
        $orders = PsccRepository::getCustomerOrders($customerId, $shopId);
        foreach ($orders as &$o) {
            $o['tracking_display'] = trim((string) $o['manual_tracking']) !== '' ? $o['manual_tracking'] : $o['native_tracking'];
            $o['tracking_url'] = PsccRepository::isValidTrackingUrl((string) $o['manual_tracking_url']) ? $o['manual_tracking_url'] : '';
            $o['portal_url'] = $this->context->link->getModuleLink($this->module->name, 'portal', array('id_order' => (int) $o['id_order']), true);
        }
        unset($o);
        if ($selectedOrder) {
            $selectedOrder['tracking_display'] = trim((string) $selectedOrder['manual_tracking']) !== '' ? $selectedOrder['manual_tracking'] : $selectedOrder['native_tracking'];
            $selectedOrder['tracking_url'] = PsccRepository::isValidTrackingUrl((string) $selectedOrder['manual_tracking_url']) ? $selectedOrder['manual_tracking_url'] : '';
        }
        $tickets = PsccRepository::getCustomerTickets($customerId, $shopId);
        foreach ($tickets as &$ticket) {
            $ticket['portal_url'] = $this->context->link->getModuleLink($this->module->name, 'portal', array('ticket_id' => (int) $ticket['id_pscc_ticket']), true);
        }
        unset($ticket);
        foreach ($tickets as $ticket) {
            $groupKey = (int) $ticket['id_order'];
            if (!isset($orderGroups[$groupKey])) {
                $orderGroups[$groupKey] = array(
                    'reference' => $ticket['reference'], 'id_order' => $groupKey,
                    'active' => array(), 'archive' => array(), 'total' => 0,
                );
            }
            $orderGroups[$groupKey]['total']++;
            if (in_array($ticket['status'], array('open', 'in_progress'), true)) {
                $orderGroups[$groupKey]['active'][] = $ticket;
            } else {
                $orderGroups[$groupKey]['archive'][] = $ticket;
            }
        }
        $orderGroups = array_values($orderGroups);
        $this->context->smarty->assign(array(
            'pscc_home' => $this->context->link->getModuleLink($this->module->name, 'portal', array(), true),
            'pscc_order_form_url' => $selectedOrder ? $this->context->link->getModuleLink($this->module->name, 'portal', array('id_order' => $orderId), true) : '',
            'pscc_ticket_form_url' => $selectedTicket ? $this->context->link->getModuleLink($this->module->name, 'portal', array('ticket_id' => $ticketId), true) : '',
            'pscc_token' => Tools::getToken(false),
            'pscc_request_create' => PsccSubmission::newKey(),
            'pscc_request_reply' => PsccSubmission::newKey(),
            'pscc_orders' => $orders,
            'pscc_tickets' => $tickets,
            'pscc_order_groups' => $orderGroups,
            'pscc_order_active' => $activeForOrder,
            'pscc_order' => $selectedOrder,
            'pscc_items' => $items,
            'pscc_ticket' => $selectedTicket,
            'pscc_thread' => $thread,
            'pscc_selected_products' => $selectedProducts,
            'pscc_errors' => $this->psccErrors,
            'pscc_notice' => (string) Tools::getValue('pscc_notice'),
        ));
        $this->setTemplate('module:psclientcare/views/templates/front/portal.tpl');
    }
}
