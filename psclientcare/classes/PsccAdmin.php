<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

class PsccAdmin
{
    private static function e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }

    private static function status($status)
    {
        $names = array('open' => 'Abierta', 'in_progress' => 'En curso', 'resolved' => 'Resuelta');
        return isset($names[$status]) ? $names[$status] : 'Desconocido';
    }

    /**
     * Module-scoped CSRF token that does not depend on the legacy AdminModules
     * token being regenerated identically by Symfony/legacy BO routes.
     * Tokens are signed with PrestaShop's server-side secret, scoped to the
     * authenticated employee and shop, and rotate each UTC day.
     */
    private static function csrfToken($employeeId, $shopId, $daysAgo = 0)
    {
        $day = gmdate('Ymd', time() - (int) $daysAgo * 86400);
        $payload = 'psclientcare:admin:' . (int) $employeeId . ':' . (int) $shopId . ':' . $day;

        return hash_hmac('sha256', $payload, (string) _COOKIE_KEY_);
    }

    private static function validCsrf($submitted, $employeeId, $shopId)
    {
        if ($employeeId <= 0 || $shopId <= 0 || !is_string($submitted) || strlen($submitted) !== 64) {
            return false;
        }

        // Allow a form opened shortly before UTC midnight to be submitted.
        return hash_equals(self::csrfToken($employeeId, $shopId), $submitted)
            || hash_equals(self::csrfToken($employeeId, $shopId, 1), $submitted);
    }

    public static function render($module)
    {
        $ctx = Context::getContext();
        $shopId = (int) $ctx->shop->id;
        $employeeId = (int) $ctx->employee->id;
        $baseUrl = $ctx->link->getAdminLink('AdminModules', true, array(), array('configure' => $module->name));
        $csrf = self::csrfToken($employeeId, $shopId);
        $notice = '';
        $error = '';
        $isPosted = Tools::isSubmit('pscc_action');
        if ($isPosted && !self::validCsrf((string) Tools::getValue('pscc_nonce', ''), $employeeId, $shopId)) {
            $error = 'Token de seguridad no válido. Recarga la página.';
        } elseif ($isPosted) {
            $action = (string) Tools::getValue('pscc_action');
            if ($action === 'config') {
                $email = trim((string) Tools::getValue('pscc_recipient'));
                if (!Validate::isEmail($email)) {
                    $error = 'Introduce una dirección de correo válida.';
                } elseif (Configuration::updateValue(Psclientcare::CONFIG_RECIPIENT, $email)) {
                    $notice = 'Correo de incidencias guardado.';
                } else {
                    $error = 'No se pudo guardar el correo.';
                }
            } elseif ($action === 'tracking') {
                $orderId = (int) Tools::getValue('pscc_order_id');
                $raw = trim((string) Tools::getValue('pscc_tracking_code'));
                $order = PsccRepository::getAdminOrder($orderId, $shopId);
                if (!$order) {
                    $error = 'Pedido no encontrado en esta tienda.';
                } elseif (Tools::strlen($raw) < 3 || Tools::strlen($raw) > 160 || preg_match('/[<>\x00-\x1F\x7F]/', $raw)) {
                    $error = 'El seguimiento debe contener entre 3 y 160 caracteres válidos.';
                } elseif (PsccRepository::setTracking($orderId, $shopId, $raw, (int) $ctx->employee->id)) {
                    $notice = 'Seguimiento actualizado para el pedido ' . self::e($order['reference']) . '.';
                } else {
                    $error = 'No se pudo guardar el seguimiento.';
                }
            } elseif ($action === 'reply') {
                $ticketId = (int) Tools::getValue('pscc_ticket_id');
                $ticket = PsccRepository::getTicket($ticketId, $shopId);
                $status = (string) Tools::getValue('pscc_status');
                $message = trim(strip_tags((string) Tools::getValue('pscc_reply')));
                if (!$ticket) {
                    $error = 'Incidencia no encontrada.';
                } elseif (!in_array($status, array('open', 'in_progress', 'resolved'), true)) {
                    $error = 'Estado inválido.';
                } elseif (Tools::strlen($message) > 4000) {
                    $error = 'La respuesta supera los 4000 caracteres.';
                } elseif (!PsccRepository::setTicketStatus($ticketId, $status)) {
                    $error = 'No se pudo cambiar el estado.';
                } elseif ($message !== '') {
                    $messageId = PsccRepository::addMessage($ticketId, 'employee', $message);
                    if (!$messageId) {
                        $error = 'Estado guardado, pero no se pudo guardar el mensaje.';
                    } else {
                        $url = $ctx->link->getModuleLink($module->name, 'portal', array('ticket_id' => $ticketId), true);
                        $sent = $module->getMailer()->notifyCustomer($ticket, $message, $url);
                        PsccRepository::setMessageEmailStatus($messageId, $sent);
                        $notice = $sent ? 'Respuesta guardada y correo aceptado por PrestaShop.' :
                            'Respuesta guardada, pero NO se pudo enviar el correo. Revísalo en la incidencia.';
                    }
                } else {
                    $notice = 'Estado actualizado.';
                }
            }
        }

        $out = '<style>.pscc-bo{max-width:1160px}.pscc-bo h3{margin-top:22px}.pscc-bo .panel{padding:20px;background:#fff;border:1px solid #dae2e8;border-radius:8px;margin-bottom:15px}.pscc-bo table{width:100%;border-collapse:collapse}.pscc-bo td,.pscc-bo th{padding:9px;border-bottom:1px solid #e5e9ed;text-align:left}.pscc-bo input[type=text],.pscc-bo input[type=email],.pscc-bo input[type=number],.pscc-bo select,.pscc-bo textarea{width:100%;max-width:550px;padding:8px;border:1px solid #9daebc;border-radius:4px}.pscc-bo label{display:block;margin:9px 0 4px}.pscc-bo button{margin-top:12px;padding:8px 16px;border:0;border-radius:4px;background:#2074a0;color:white}.pscc-bo .muted{color:#596979}.pscc-bo .msg{border-left:4px solid #23855a;background:#e9f7f0;padding:12px}.pscc-bo .err{border-left:4px solid #aa3f3f;background:#fff1f1;padding:12px}.pscc-bo .bubble{padding:10px 12px;background:#f4f7f9;border-radius:7px;margin:8px 0;white-space:pre-wrap}</style>';
        $out .= '<div class="pscc-bo"><h2>Portal del cliente — incidencias y seguimiento</h2>';
        $out .= '<p class="muted">Primera fase: seguimiento manual. Las incidencias se guardan antes de intentar enviar los correos.</p>';
        if ($notice) {
            $out .= '<p class="msg">' . $notice . '</p>';
        }
        if ($error) {
            $out .= '<p class="err">' . self::e($error) . '</p>';
        }
        $out .= '<section class="panel"><h3>Correo de recepción de incidencias</h3><form method="post" action="' . self::e($baseUrl) . '">';
        $out .= self::hidden($csrf, 'config');
        $out .= '<label for="pscc_recipient">Dirección de atención al cliente</label><input id="pscc_recipient" type="email" name="pscc_recipient" required value="' . self::e($module->getRecipient()) . '">';
        $out .= '<div><button type="submit">Guardar correo</button></div></form></section>';

        $out .= '<section class="panel"><h3>Introducir número de seguimiento</h3><form method="post" action="' . self::e($baseUrl) . '">';
        $out .= self::hidden($csrf, 'tracking');
        $out .= '<label for="pscc_order_id">ID interno de pedido (consulta la tabla de abajo)</label><input id="pscc_order_id" type="number" min="1" name="pscc_order_id" required>'; 
        $out .= '<label for="pscc_tracking_code">Código de seguimiento (manual)</label><input id="pscc_tracking_code" type="text" maxlength="160" name="pscc_tracking_code" required>';
        $out .= '<div><button type="submit">Guardar seguimiento</button></div></form>';
        $out .= '<h4>Últimos 30 pedidos</h4><div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>N.º pedido</th><th>Cliente</th><th>Seguimiento manual</th><th>Fecha</th></tr></thead><tbody>';
        foreach (PsccRepository::getAdminOrders($shopId) as $o) {
            $out .= '<tr><td>' . (int) $o['id_order'] . '</td><td>' . self::e($o['reference']) . '</td><td>' . self::e($o['firstname'] . ' ' . $o['lastname']) . '</td><td>' . self::e($o['manual_tracking'] ?: '—') . '</td><td>' . self::e($o['date_add']) . '</td></tr>';
        }
        $out .= '</tbody></table></div></section>';

        $out .= '<section class="panel"><h3>Incidencias recibidas</h3><div style="overflow-x:auto"><table><thead><tr><th>#</th><th>Pedido</th><th>Cliente</th><th>Asunto</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>';
        $tickets = PsccRepository::getAdminTickets($shopId);
        foreach ($tickets as $t) {
            $url = $baseUrl . '&view_pscc_ticket=' . (int) $t['id_pscc_ticket'];
            $out .= '<tr><td>' . (int) $t['id_pscc_ticket'] . '</td><td>' . self::e($t['reference']) . '</td><td>' . self::e($t['firstname'] . ' ' . $t['lastname']) . '</td><td>' . self::e($t['subject']) . '</td><td>' . self::e(self::status($t['status'])) . '</td><td>' . self::e($t['date_upd']) . '</td><td><a href="' . self::e($url) . '">Ver / responder</a></td></tr>';
        }
        if (!$tickets) {
            $out .= '<tr><td colspan="7">No hay incidencias.</td></tr>';
        }
        $out .= '</tbody></table></div></section>';
        $ticketId = (int) Tools::getValue('view_pscc_ticket');
        if (!$ticketId && Tools::isSubmit('pscc_ticket_id')) {
            $ticketId = (int) Tools::getValue('pscc_ticket_id');
        }
        $t = $ticketId ? PsccRepository::getTicket($ticketId, $shopId) : false;
        if ($t) {
            $out .= '<section class="panel" id="pscc-reply"><h3>Incidencia #' . (int) $t['id_pscc_ticket'] . ' · pedido ' . self::e($t['reference']) . '</h3>';
            $out .= '<p><strong>' . self::e($t['subject']) . '</strong><br>' . self::e($t['firstname'] . ' ' . $t['lastname']) . ' · ' . self::e($t['email']) . '</p>';
            $out .= '<p><strong>Todos los productos comprados:</strong></p><ul>';
            foreach (PsccRepository::getOrderItems($t['id_order']) as $line) {
                $out .= '<li>' . self::e($line['product_name']) . ' · ×' . (int) $line['product_quantity'] . ' · ' . self::e($line['product_reference']) . '</li>';
            }
            $out .= '</ul>';
            $products = PsccRepository::getSelectedProducts($ticketId);
            if ($products) {
                $out .= '<p><strong>Productos marcados:</strong> ';
                $names = array();
                foreach ($products as $p) {
                    $names[] = self::e($p['product_name']) . ' (x' . (int) $p['product_quantity'] . ')';
                }
                $out .= implode('; ', $names) . '</p>';
            }
            foreach (PsccRepository::getTicketMessages($ticketId) as $m) {
                $out .= '<div class="bubble"><strong>' . ($m['sender'] === 'customer' ? 'Cliente' : 'Tienda') . '</strong> · ' . self::e($m['date_add']) . '<br>' . nl2br(self::e($m['message'])) . '<br><small>Correo: ' . self::e($m['email_status']) . '</small></div>';
            }
            $out .= '<form method="post" action="' . self::e($baseUrl . '&view_pscc_ticket=' . $ticketId) . '">';
            $out .= self::hidden($csrf, 'reply');
            $out .= '<input type="hidden" name="pscc_ticket_id" value="' . $ticketId . '">';
            $out .= '<label for="pscc_status">Estado</label><select id="pscc_status" name="pscc_status">';
            foreach (array('open', 'in_progress', 'resolved') as $status) {
                $out .= '<option value="' . $status . '"' . ($t['status'] === $status ? ' selected' : '') . '>' . self::status($status) . '</option>';
            }
            $out .= '</select><label for="pscc_reply">Respuesta al cliente (opcional para cambiar solo el estado)</label><textarea id="pscc_reply" name="pscc_reply" rows="5" maxlength="4000"></textarea>';
            $out .= '<div><button type="submit">Guardar y enviar respuesta</button></div></form></section>';
        }
        return $out . '</div>';
    }

    private static function hidden($csrf, $action)
    {
        return '<input type="hidden" name="pscc_nonce" value="' . self::e($csrf) . '"><input type="hidden" name="pscc_action" value="' . self::e($action) . '">';
    }
}
