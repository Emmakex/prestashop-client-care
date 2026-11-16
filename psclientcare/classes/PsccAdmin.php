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
        $names = array('open' => 'Abierta', 'in_progress' => 'En curso', 'resolved' => 'Resuelta / Histórica', 'archived' => 'Cerrada · Archivada');
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
                $trackingUrl = trim((string) Tools::getValue('pscc_tracking_url', ''));
                $order = PsccRepository::getAdminOrder($orderId, $shopId);
                if (!$order) {
                    $error = 'Pedido no encontrado en esta tienda.';
                } elseif (Tools::strlen($raw) < 3 || Tools::strlen($raw) > 160 || preg_match('/[<>\x00-\x1F\x7F]/', $raw)) {
                    $error = 'El seguimiento debe contener entre 3 y 160 caracteres válidos.';
                } elseif (preg_match('~^(?:https?://|www\.)~i', $raw)) {
                    $error = 'Has escrito una URL en el campo Código. Introduce el número de seguimiento como código y pega el enlace en URL del transportista.';
                } elseif (!PsccRepository::isValidTrackingUrl($trackingUrl)) {
                    $error = 'La URL de seguimiento debe ser un enlace HTTP o HTTPS válido (máximo 2048 caracteres).';
                } elseif (PsccRepository::setTracking($orderId, $shopId, $raw, $trackingUrl, (int) $ctx->employee->id)) {
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
                } elseif (!in_array($status, array('open', 'in_progress', 'resolved', 'archived'), true)) {
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
                        try {
                            $sent = $module->getMailer()->notifyCustomer($ticket, $message, $url);
                        } catch (\Throwable $e) {
                            $sent = false;
                        }
                        PsccRepository::setMessageEmailStatus($messageId, $sent);
                        $notice = $sent ? 'Respuesta guardada y correo aceptado por PrestaShop.' :
                            'Respuesta guardada, pero NO se pudo enviar el correo. Revísalo en la incidencia.';
                    }
                } else {
                    $notice = 'Estado actualizado.';
                }
            }
        }

        $out = '<style>.pscc-bo{max-width:1160px}.pscc-bo h3{margin-top:22px}.pscc-bo .panel{padding:20px;background:#fff;border:1px solid #dae2e8;border-radius:8px;margin-bottom:15px}.pscc-bo table{width:100%;border-collapse:collapse}.pscc-bo td,.pscc-bo th{padding:9px;border-bottom:1px solid #e5e9ed;text-align:left}.pscc-bo input[type=text],.pscc-bo input[type=email],.pscc-bo input[type=number],.pscc-bo input[type=url],.pscc-bo select,.pscc-bo textarea{width:100%;max-width:550px;padding:8px;border:1px solid #9daebc;border-radius:4px}.pscc-bo label{display:block;margin:9px 0 4px}.pscc-bo button{margin-top:12px;padding:8px 16px;border:0;border-radius:4px;background:#2074a0;color:white}.pscc-bo .muted{color:#596979}.pscc-bo .msg{border-left:4px solid #23855a;background:#e9f7f0;padding:12px}.pscc-bo .err{border-left:4px solid #aa3f3f;background:#fff1f1;padding:12px}.pscc-bo .bubble{padding:10px 12px;background:#f4f7f9;border-radius:7px;margin:8px 0;white-space:pre-wrap} .pscc-bo .panel{border-radius:14px;box-shadow:0 5px 24px rgba(18,46,70,.055)}.pscc-bo h2{font-weight:750;color:#163248}.pscc-bo h3{font-size:19px;color:#184766}.pscc-bo input[type=text],.pscc-bo input[type=email],.pscc-bo input[type=number],.pscc-bo input[type=url],.pscc-bo select,.pscc-bo textarea{min-height:43px;border-radius:8px}.pscc-bo button{min-height:44px;border-radius:8px;background:#145979;font-weight:700}.pscc-bo button:hover{background:#0d4260}.pscc-bo :is(a,button,input,select,textarea):focus-visible{outline:3px solid #bd6913;outline-offset:2px}.pscc-bo table th{background:#f2f7fa;color:#294758}.pscc-bo .pscc-credit{margin:22px 0;color:#4d6373}.pscc-bo a{color:#125779;text-decoration:underline;overflow-wrap:anywhere}@media(max-width:700px){.pscc-bo .panel{padding:16px}.pscc-bo button{width:100%}} </style>';
        $out .= '<div class="pscc-bo"><h2>Seguimiento de Pedido</h2>';
        $out .= '<p class="muted">Primera fase: seguimiento manual. Las incidencias se guardan antes de intentar enviar los correos.</p>';
        if ($notice) {
            $out .= '<p class="msg" role="status">' . $notice . '</p>';
        }
        if ($error) {
            $out .= '<p class="err" role="alert">' . self::e($error) . '</p>';
        }
        $out .= '<section class="panel"><h3>Correo de recepción de incidencias</h3><form method="post" action="' . self::e($baseUrl) . '">';
        $out .= self::hidden($csrf, 'config');
        $out .= '<label for="pscc_recipient">Dirección de atención al cliente</label><input id="pscc_recipient" type="email" name="pscc_recipient" aria-describedby="pscc-mail-help" required value="' . self::e($module->getRecipient()) . '">';
        $out .= '<p class="muted" id="pscc-mail-help">Dirección de destino de las incidencias. Se conserva para futuras automatizaciones. Los correos se envían mediante la configuración de correo nativa de PrestaShop; no se configura SMTP aquí.</p>';
        $out .= '<div><button type="submit">Guardar correo</button></div></form>';
        $lastMail = (string) Configuration::get('PSCC_LAST_MAIL_DIAGNOSTIC');
        $mailMethod = (string) Configuration::get('PS_MAIL_METHOD');
        $out .= '<p class="muted"><strong>Diagnóstico del envío nativo:</strong> ';
        $out .= $lastMail ? self::e($lastMail) : 'Todavía no hay resultados registrados';
        $out .= '. Método de correo PrestaShop: ' . self::e($mailMethod === '' ? 'No disponible' : $mailMethod) . '.</p>';
        $out .= '<p class="muted">Si aparece mail_send_returned_false o exception, utiliza el correo de prueba de Parámetros avanzados → Correo electrónico de PrestaShop y revisa los registros PHP. No hace falta configurar otro SMTP aquí.</p></section>';

        $out .= '<section class="panel"><h3>Seguimiento de Pedido</h3><form method="post" action="' . self::e($baseUrl) . '">';
        $out .= self::hidden($csrf, 'tracking');
        $out .= '<label for="pscc_order_id">ID interno de pedido (consulta la tabla de abajo)</label><input id="pscc_order_id" type="number" min="1" name="pscc_order_id" required>'; 
        $out .= '<label for="pscc_tracking_code">Número o código de seguimiento</label><input id="pscc_tracking_code" type="text" maxlength="160" name="pscc_tracking_code" aria-describedby="pscc-code-help" placeholder="Ej.: 123456789AB" required>';
        $out .= '<p class="muted" id="pscc-code-help">Introduce aquí solo el código del transportista. Si tienes un enlace web, colócalo en el campo siguiente.</p>';
        $out .= '<label for="pscc_tracking_url">URL de seguimiento del transportista (opcional)</label><input id="pscc_tracking_url" type="url" maxlength="2048" name="pscc_tracking_url" aria-describedby="pscc-url-help" placeholder="https://transportista.example/seguimiento/..."><p class="muted" id="pscc-url-help">El cliente podrá ver la URL completa y abrir el enlace desde Seguimiento de Pedido. Utiliza una URL completa con https:// (o http://).</p>';
        $out .= '<div><button type="submit">Guardar seguimiento</button></div></form>';
        $out .= '<h4>Últimos 30 pedidos</h4><div style="overflow-x:auto"><table><thead><tr><th>ID</th><th>N.º pedido</th><th>Cliente</th><th>Seguimiento manual</th><th>Enlace</th><th>Fecha</th></tr></thead><tbody>';
        foreach (PsccRepository::getAdminOrders($shopId) as $o) {
            $trackingLink = !empty($o['manual_tracking_url']) && PsccRepository::isValidTrackingUrl($o['manual_tracking_url']) ? '<a href="' . self::e($o['manual_tracking_url']) . '" target="_blank" rel="noopener noreferrer">Abrir seguimiento</a>' : '—';
            $out .= '<tr><td>' . (int) $o['id_order'] . '</td><td>' . self::e($o['reference']) . '</td><td>' . self::e($o['firstname'] . ' ' . $o['lastname']) . '</td><td>' . self::e($o['manual_tracking'] ?: '—') . '</td><td>' . $trackingLink . '</td><td>' . self::e($o['date_add']) . '</td></tr>';
        }
        $out .= '</tbody></table></div></section>';

        $out .= '<section class="panel"><h3>Incidencias agrupadas por pedido</h3>';
        $out .= '<p class="muted">Todas las incidencias de un pedido se conservan y se muestran juntas. Los casos archivados permanecen en el histórico, sin borrar conversaciones.</p>';
        $out .= '<div style="overflow-x:auto"><table><thead><tr><th>#</th><th>Pedido</th><th>Cliente</th><th>Asunto</th><th>Estado</th><th>Fecha</th><th></th></tr></thead><tbody>';
        $tickets = PsccRepository::getAdminTickets($shopId);
        $grouped = array();
        foreach ($tickets as $t) {
            $key = (int) $t['id_order'];
            if (!isset($grouped[$key])) {
                $grouped[$key] = array('reference' => $t['reference'], 'tickets' => array());
            }
            $grouped[$key]['tickets'][] = $t;
        }
        foreach ($grouped as $orderGroup) {
            $out .= '<tr class="pscc-admin-order"><th colspan="7">Pedido ' . self::e($orderGroup['reference'])
                . ' · ' . count($orderGroup['tickets']) . ' expediente(s)</th></tr>';
            foreach ($orderGroup['tickets'] as $t) {
                $url = $baseUrl . '&view_pscc_ticket=' . (int) $t['id_pscc_ticket'];
                $out .= '<tr><td>' . (int) $t['id_pscc_ticket'] . '</td><td>' . self::e($t['reference'])
                    . '</td><td>' . self::e($t['firstname'] . ' ' . $t['lastname']) . '</td><td>'
                    . self::e($t['subject']) . '</td><td>' . self::e(self::status($t['status'])) . '</td><td>'
                    . self::e($t['date_upd']) . '</td><td><a href="' . self::e($url) . '">Ver / gestionar</a></td></tr>';
            }
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
            $out .= '<h4>Conversación conjunta del pedido (histórico completo)</h4>';
            foreach (PsccRepository::getOrderHistory((int) $t['id_order'], (int) $t['id_customer'], $shopId) as $m) {
                $out .= '<div class="bubble"><strong>' . ($m['sender'] === 'customer' ? 'Cliente' : 'Tienda') . '</strong> · ' . self::e($m['date_add']) . ' · incidencia #' . (int) $m['id_pscc_ticket'] . '<br>' . nl2br(self::e($m['message'])) . '</div>';
            }
            $out .= '<form method="post" action="' . self::e($baseUrl . '&view_pscc_ticket=' . $ticketId) . '">';
            $out .= self::hidden($csrf, 'reply');
            $out .= '<input type="hidden" name="pscc_ticket_id" value="' . $ticketId . '">';
            $out .= '<label for="pscc_status">Estado</label><select id="pscc_status" name="pscc_status">';
            foreach (array('open', 'in_progress', 'resolved', 'archived') as $status) {
                $out .= '<option value="' . $status . '"' . ($t['status'] === $status ? ' selected' : '') . '>' . self::status($status) . '</option>';
            }
            $out .= '</select><label for="pscc_reply">Respuesta al cliente (opcional para cambiar solo el estado)</label><textarea id="pscc_reply" name="pscc_reply" rows="5" maxlength="4000"></textarea>';
            $out .= '<div><button type="submit">Guardar y enviar respuesta</button></div></form></section>';
        }
        return $out . '<p class="pscc-credit">Módulo desarrollado por <a href="https://www.eduardoyauriluna.com" rel="noopener noreferrer">Eduardo Yauri</a> · v' . self::e($module->version) . '</p></div>';
    }

    private static function hidden($csrf, $action)
    {
        return '<input type="hidden" name="pscc_nonce" value="' . self::e($csrf) . '"><input type="hidden" name="pscc_action" value="' . self::e($action) . '">';
    }
}
