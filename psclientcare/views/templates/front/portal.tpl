{extends file='page.tpl'}
{* The hero provides the single visible h1. Avoid an extra theme-generated title. *}
{block name='page_title'}{/block}
{block name='page_content'}
<section class="pscc-portal" aria-label="Seguimiento de Pedido y atención al cliente">
  <div class="pscc-hero">
    <div><span class="pscc-eyebrow">ATENCIÓN AL CLIENTE</span><h1>Seguimiento de Pedido</h1>
      <p>Consulta el seguimiento de tus pedidos y comunícanos cualquier incidencia con los productos comprados.</p></div>
    <a class="pscc-btn pscc-btn-light" href="{$urls.pages.my_account|escape:'htmlall':'UTF-8'}">Volver a mi cuenta</a>
  </div>
  {foreach $pscc_errors as $error}
    <div class="pscc-alert pscc-alert-error" role="alert">{$error|escape:'htmlall':'UTF-8'}</div>
  {/foreach}
  {if $pscc_notice == 'created'}
    <div class="pscc-alert pscc-alert-success" role="status">Incidencia registrada. La notificación por correo ha sido aceptada por PrestaShop.</div>
  {elseif $pscc_notice == 'reply'}
    <div class="pscc-alert pscc-alert-success" role="status">Mensaje guardado. La notificación por correo ha sido aceptada por PrestaShop.</div>
  {elseif $pscc_notice == 'saved_no_mail'}
    <div class="pscc-alert pscc-alert-warning" role="alert">Tu mensaje está guardado, pero no se pudo enviar la notificación por correo. La tienda puede consultarlo en administración.</div>
  {/if}
  <nav class="pscc-quicknav" aria-label="Secciones del portal">
    <a href="#pscc-orders">Mis pedidos</a>
    <a href="#pscc-tickets">Mis incidencias</a>
  </nav>
  <div class="pscc-summary">
    <div><strong>{$pscc_orders|count}</strong><span>Pedidos recientes</span></div>
    <div><strong>{$pscc_tickets|count}</strong><span>Incidencias</span></div>
    <div class="pscc-summary-note"><i class="material-icons" aria-hidden="true">verified_user</i><span>Solo puedes acceder a tus propios pedidos.</span></div>
  </div>

  {if $pscc_ticket}
    <section class="pscc-panel" id="incidencia">
      <div class="pscc-panel-head"><div><a href="{$pscc_home|escape:'htmlall':'UTF-8'}" class="pscc-back">← Todas las incidencias</a><h2>Incidencia #{$pscc_ticket.id_pscc_ticket|intval}</h2><p>Pedido {$pscc_ticket.reference|escape:'htmlall':'UTF-8'} · {$pscc_ticket.subject|escape:'htmlall':'UTF-8'}</p></div>
        <span class="pscc-pill">{if $pscc_ticket.status == 'open'}Abierta{elseif $pscc_ticket.status == 'in_progress'}En curso{else}Resuelta{/if}</span></div>
      {if $pscc_selected_products}
        <p><strong>Productos relacionados:</strong> {foreach $pscc_selected_products as $p name=affected}{$p.product_name|escape:'htmlall':'UTF-8'} (×{$p.product_quantity|intval}){if !$smarty.foreach.affected.last}; {/if}{/foreach}</p>
      {/if}
      <div class="pscc-thread" role="log" aria-label="Mensajes de la incidencia">
        {foreach $pscc_thread as $msg}
          <div class="pscc-message {if $msg.sender == 'customer'}pscc-from-customer{else}pscc-from-shop{/if}">
            <div class="pscc-message-head"><strong>{if $msg.sender == 'customer'}Tú{else}Atención al cliente{/if}</strong><time>{$msg.date_add|escape:'htmlall':'UTF-8'}</time></div>
            <p>{$msg.message|escape:'htmlall':'UTF-8'|nl2br nofilter}</p>
          </div>
        {/foreach}
      </div>
      <form method="post" action="{$pscc_ticket_form_url|escape:'htmlall':'UTF-8'}" class="pscc-form">
        <input type="hidden" name="pscc_action" value="reply">
        <input type="hidden" name="ticket_id" value="{$pscc_ticket.id_pscc_ticket|intval}">
        <input type="hidden" name="pscc_token" value="{$pscc_token|escape:'htmlall':'UTF-8'}">
        <label for="pscc_message_reply">Añadir un mensaje</label>
        <p class="pscc-field-help" id="pscc-reply-help">De 10 a 4.000 caracteres. Tu mensaje quedará guardado en la incidencia.</p>
        <textarea name="pscc_message" id="pscc_message_reply" rows="4" minlength="10" maxlength="4000" aria-describedby="pscc-reply-help" required placeholder="Explica cualquier detalle adicional..."></textarea>
        <button class="pscc-btn" type="submit">Enviar mensaje</button>
      </form>
    </section>
  {/if}

  {if $pscc_order}
    <section class="pscc-panel" id="nueva-incidencia">
      <div class="pscc-panel-head"><div><a href="{$pscc_home|escape:'htmlall':'UTF-8'}" class="pscc-back">← Todos los pedidos</a><h2>Pedido {$pscc_order.reference|escape:'htmlall':'UTF-8'}</h2><p>Fecha: {$pscc_order.date_add|escape:'htmlall':'UTF-8'} · {$pscc_order.state_name|escape:'htmlall':'UTF-8'}</p></div>
        <span class="pscc-pill">Seguimiento: {if $pscc_order.tracking_display}{$pscc_order.tracking_display|escape:'htmlall':'UTF-8'}{else}Pendiente{/if}</span></div>
      {if $pscc_order.tracking_url}
        <div class="pscc-tracking-card" aria-label="Seguimiento del envío del pedido seleccionado">
          <p class="pscc-tracking-kicker">ENLACE DEL TRANSPORTISTA</p>
          <p class="pscc-tracking-address-label">URL de seguimiento</p>
          <a class="pscc-tracking-address" href="{$pscc_order.tracking_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$pscc_order.tracking_url|escape:'htmlall':'UTF-8'}</a>
          <a class="pscc-btn pscc-btn-outline pscc-tracking-action" href="{$pscc_order.tracking_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="Consultar envío del pedido {$pscc_order.reference|escape:'htmlall':'UTF-8'} en una pestaña nueva">Consultar seguimiento del envío <span aria-hidden="true">↗</span></a>
          <p class="pscc-field-help">El enlace se abrirá en una pestaña nueva del transportista.</p>
        </div>
      {/if}
      <h3>Productos comprados</h3>
      <form method="post" action="{$pscc_order_form_url|escape:'htmlall':'UTF-8'}" class="pscc-form">
        <input type="hidden" name="pscc_action" value="create">
        <input type="hidden" name="id_order" value="{$pscc_order.id_order|intval}">
        <input type="hidden" name="pscc_token" value="{$pscc_token|escape:'htmlall':'UTF-8'}">
        <div class="pscc-products">
          {foreach $pscc_items as $item}
            <label class="pscc-product">
              <input type="checkbox" name="pscc_products[]" value="{$item.id_order_detail|intval}">
              <span><strong>{$item.product_name|escape:'htmlall':'UTF-8'}</strong><small>Referencia: {$item.product_reference|escape:'htmlall':'UTF-8'|default:'—'} · Cantidad: {$item.product_quantity|intval}</small></span>
            </label>
          {/foreach}
        </div>
        <p class="pscc-help" id="pscc-products-help">Marca los productos afectados si corresponde. El correo incluirá igualmente todos los productos del pedido.</p>
        <label for="pscc_subject">Asunto de la incidencia</label>
        <input type="text" id="pscc_subject" name="pscc_subject" minlength="4" maxlength="200" autocomplete="off" required placeholder="Ej.: Falta un producto del pedido">
        <label for="pscc_message_new">Explica qué ha ocurrido</label>
        <p class="pscc-field-help" id="pscc-message-help">De 10 a 4.000 caracteres. Describe lo ocurrido para que podamos ayudarte.</p>
        <textarea id="pscc_message_new" name="pscc_message" rows="5" minlength="10" maxlength="4000" aria-describedby="pscc-message-help" required placeholder="Describe la incidencia con el máximo detalle posible..."></textarea>
        <button class="pscc-btn" type="submit">Registrar incidencia</button>
      </form>
    </section>
  {/if}

  <section class="pscc-panel" id="pscc-orders" aria-labelledby="pscc-orders-heading">
    <div class="pscc-panel-head"><div><h2 id="pscc-orders-heading">Seguimiento de Pedido</h2><p>Selecciona un pedido para comunicar una incidencia.</p></div><i class="material-icons" aria-hidden="true">local_shipping</i></div>
    {if !$pscc_orders}<div class="pscc-empty">Todavía no tienes pedidos disponibles.</div>{/if}
    <div class="pscc-order-list">
      {foreach $pscc_orders as $o}
        <article class="pscc-order-row">
          <div class="pscc-order-main"><strong>Pedido {$o.reference|escape:'htmlall':'UTF-8'}</strong><small>{$o.date_add|escape:'htmlall':'UTF-8'} · {$o.state_name|escape:'htmlall':'UTF-8'}</small></div>
          <div class="pscc-track"><span>Seguimiento</span><strong>{if $o.tracking_display}{$o.tracking_display|escape:'htmlall':'UTF-8'}{else}Pendiente de asignación{/if}</strong>{if $o.tracking_url}<span class="pscc-tracking-address-label">URL de seguimiento</span><a class="pscc-tracking-address" href="{$o.tracking_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer">{$o.tracking_url|escape:'htmlall':'UTF-8'}</a><a class="pscc-tracking-link" href="{$o.tracking_url|escape:'htmlall':'UTF-8'}" target="_blank" rel="noopener noreferrer" aria-label="Abrir seguimiento del pedido {$o.reference|escape:'htmlall':'UTF-8'} en otra pestaña">Consultar envío <span aria-hidden="true">↗</span></a>{/if}</div>
          <a class="pscc-btn pscc-btn-outline" href="{$o.portal_url|escape:'htmlall':'UTF-8'}">Ver productos / Incidencia</a>
        </article>
      {/foreach}
    </div>
  </section>
  <section class="pscc-panel" id="pscc-tickets" aria-labelledby="pscc-tickets-heading">
    <div class="pscc-panel-head"><div><h2 id="pscc-tickets-heading">Mis incidencias</h2><p>Historial de mensajes y respuestas de la tienda.</p></div><i class="material-icons" aria-hidden="true">forum</i></div>
    {if !$pscc_tickets}<div class="pscc-empty">Aún no has registrado incidencias.</div>{/if}
    <div class="pscc-order-list">
      {foreach $pscc_tickets as $t}
        <article class="pscc-order-row"><div class="pscc-order-main"><strong>#{$t.id_pscc_ticket|intval} · {$t.subject|escape:'htmlall':'UTF-8'}</strong><small>Pedido {$t.reference|escape:'htmlall':'UTF-8'} · {$t.date_upd|escape:'htmlall':'UTF-8'}</small></div>
          <span class="pscc-pill">{if $t.status == 'open'}Abierta{elseif $t.status == 'in_progress'}En curso{else}Resuelta{/if}</span>
          <a class="pscc-btn pscc-btn-outline" href="{$t.portal_url|escape:'htmlall':'UTF-8'}">Ver conversación</a>
        </article>
      {/foreach}
    </div>
  </section>
</section>
{/block}
