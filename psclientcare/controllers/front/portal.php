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
        if (PsccRepository::recentMessages((int) $this->context->customer->id) >= 3) {
            $this->psccErrors[] = 'Se han enviado demasiados mensajes. Inténtalo más tarde.';
            return;
        }
        $action = (string) Tools::getValue('pscc_action');
        if ($action === 'create') {
            $this->createTicket();
        } elseif ($action === 'reply') {
            $this->replyTicket();
        }
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

    private function createTicket()
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
        $result = PsccRepository::createTicket($orderId, $customerId, $shopId, $subject, $message, array_values($productIds));
        if (!$result) {
            $this->psccErrors[] = 'No se ha podido guardar la incidencia. Vuelve a intentarlo.';
            return;
        }
        $ticket = PsccRepository::getTicket($result['ticket_id'], $shopId, $customerId);
        $sent = $this->module->getMailer()->notifyStaff($ticket, $items,
            PsccRepository::getSelectedProducts($result['ticket_id']), $message);
        PsccRepository::setMessageEmailStatus($result['message_id'], $sent);
        $url = $this->context->link->getModuleLink($this->module->name, 'portal', array(
            'ticket_id' => (int) $result['ticket_id'], 'pscc_notice' => $sent ? 'created' : 'saved_no_mail',
        ), true);
        Tools::redirect($url);
    }

    private function replyTicket()
    {
        $ticketId = (int) Tools::getValue('ticket_id');
        $ticket = PsccRepository::getTicket($ticketId, (int) $this->context->shop->id, (int) $this->context->customer->id);
        if (!$ticket) {
            $this->psccErrors[] = 'Incidencia no encontrada en tu cuenta.';
            return;
        }
        $message = $this->normalizedMessage();
        if (!$this->validMessage($message)) {
            return;
        }
        $idMessage = PsccRepository::addMessage($ticketId, 'customer', $message);
        if (!$idMessage) {
            $this->psccErrors[] = 'No se ha podido guardar tu mensaje.';
            return;
        }
        if ($ticket['status'] === 'resolved') {
            PsccRepository::setTicketStatus($ticketId, 'open');
        }
        $items = PsccRepository::getOrderItems($ticket['id_order']);
        $sent = $this->module->getMailer()->notifyStaff($ticket, $items,
            PsccRepository::getSelectedProducts($ticketId), $message);
        PsccRepository::setMessageEmailStatus($idMessage, $sent);
        Tools::redirect($this->context->link->getModuleLink($this->module->name, 'portal', array(
            'ticket_id' => $ticketId, 'pscc_notice' => $sent ? 'reply' : 'saved_no_mail',
        ), true));
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
        $ticketId = (int) Tools::getValue('ticket_id');
        if ($ticketId > 0) {
            $selectedTicket = PsccRepository::getTicket($ticketId, $shopId, $customerId);
            if (!$selectedTicket) {
                $this->psccErrors[] = 'La incidencia solicitada no existe en tu cuenta.';
            } else {
                $thread = PsccRepository::getTicketMessages($ticketId);
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
        $this->context->smarty->assign(array(
            'pscc_home' => $this->context->link->getModuleLink($this->module->name, 'portal', array(), true),
            'pscc_order_form_url' => $selectedOrder ? $this->context->link->getModuleLink($this->module->name, 'portal', array('id_order' => $orderId), true) : '',
            'pscc_ticket_form_url' => $selectedTicket ? $this->context->link->getModuleLink($this->module->name, 'portal', array('ticket_id' => $ticketId), true) : '',
            'pscc_token' => Tools::getToken(false),
            'pscc_orders' => $orders,
            'pscc_tickets' => $tickets,
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
