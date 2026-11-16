# PS Client Care — v0.1.6 (beta instalable)

Módulo **independiente** para PrestaShop; no requiere Salesforce, CRM externo, overrides ni cambios en la base del core.

## Funcionalidades

- `Mi cuenta` → **Seguimiento de Pedido** mediante el hook `displayCustomerAccount`.
- Lista de los últimos 80 pedidos **del cliente autenticado** y **de la tienda actual**. Enlaces a pedido individual.
- Cada pedido muestra número/referencia, estado, seguimiento y **todos los artículos adquiridos** con cantidades/referencias.
- Formulario para abrir incidencia con asunto y descripción; selección opcional de artículos afectados. En cada email se incluyen **todos** los artículos del pedido.
- Historial de incidencias (hasta 100) y conversación con respuestas y reapertura automática al responder a una incidencia resuelta.
- Back Office → Módulos → Configurar «Portal del cliente»: bandeja de incidencias, respuestas, cambios de estado, email receptor y alta manual de códigos de seguimiento por ID de pedido.
- El Back Office permite guardar una **URL HTTP(S)** de seguimiento por pedido; el cliente ve un enlace para abrirla en el transportista.
- Prioridad de visualización de tracking: **código manual del módulo**, después código nativo `order_carrier` si existe, después «Pendiente». La escritura manual **no altera** tablas de envío nativas.
- Correos en plantillas `es`, `en`, `ca`, `pt`; se usa el email transaccional configurado en PrestaShop. Notificaciones de apertura/respuesta del cliente a la tienda y respuesta del personal al cliente.

## Actualización desde v0.1.0 / v0.1.1 / v0.1.2

Sube el ZIP v0.1.6 desde el gestor de módulos o utiliza la actualización habitual de PrestaShop. **No desinstales v0.1.0**: los pedidos, las incidencias y los códigos de seguimiento conservan sus tablas. v0.1.2 añade una columna `tracking_url` con migración `upgrade/upgrade-0.1.2.php`, conservando códigos de seguimiento, incidencias y mensajes existentes. No desinstales ni reinicies el módulo. v0.1.6 corrige la tarjeta en Mi cuenta (tema Classic), la separación de navegación y estadísticas y el diseño de las filas de pedidos. No modifica las tablas ni la configuración del correo. Vuelve a cargar la página de configuración tras actualizar.

## Instalación

1. Haz una copia de seguridad de pruebas de la tienda.
2. En **Back Office → Módulos → Gestor de módulos → Subir un módulo**, instala el ZIP `psclientcare-v0.1.6.zip` tal cual, con la carpeta `psclientcare/` en su raíz.
3. Ve a **Configurar** y comprueba/cambia el email de atención al cliente: **el destinatario continúa siendo editable para futuras automatizaciones**. No necesitas ajustar otro SMTP; usa las opciones nativas de PrestaShop.
4. Comprueba el correo transaccional/SMTP de PrestaShop enviando un correo de prueba desde su configuración.
5. Con una cuenta cliente con pedidos, abre **Mi cuenta → Seguimiento de Pedido** y crea una incidencia de prueba.
6. Desde la configuración del módulo consulta ese caso, respóndelo y asigna un número de seguimiento a un pedido (usa su ID interno, visible en la tabla).
7. Confirma que el cliente ve el tracking y la respuesta. Prueba con un segundo cliente que no pueda acceder a pedidos/incidencias del primero.

## Seguridad y límites

- El controlador es SSL, requiere autenticación, valida token de formulario y propiedad del pedido y de la incidencia para cada lectura/escritura.
- Se verifica que cada artículo seleccionado **pertenece al pedido**. Texto plano, salida HTML escapada, máximo 4000 caracteres por mensaje, límite básico de 3 mensajes/minuto por cliente.
- Las incidencias se guardan primero y **después** se intenta el envío por correo. Si PrestaShop no acepta el correo, el mensaje **se conserva** con `email_status=failed`; **no existe reintento automático en esta versión**.
- Para evitar modificaciones accidentales, desinstalar el módulo **conserva las tablas y las conversaciones**. El responsable deberá definir una política de conservación y supresión RGPD; no borres la base de datos sin exportar primero lo legalmente necesario.
- Fase 1 limitada a una tienda activa por contexto de administración. Si usas multitienda, selecciona una tienda concreta al configurar. No se garantiza que el hook exista en un tema personalizado; en ese caso añade `{hook h='displayCustomerAccount'}` donde corresponda.
- No incluye adjuntos, SLA, notificaciones de tracking, integración dinámica del transportista, paginación avanzada ni transportistas automatizados. Quedan fuera de alcance de v0.1.6.
- Compatibilidad **objetivo**, no certificada en entorno real: PrestaShop `1.7.8.x`, `8.x`, `9.x`; PHP según versión de PrestaShop. Las pruebas de sintaxis no sustituyen instalación/flujo SMTP en una tienda de pruebas.

## Arquitectura de datos

- `PREFIX_pscc_ticket` (pedido, cliente, tienda, estado, asunto).
- `PREFIX_pscc_message` (historial y resultado del intento de notificación).
- `PREFIX_pscc_ticket_product` (productos afectados como claves a las líneas **históricas** del pedido).
- `PREFIX_pscc_tracking` (`id_order` único, código, `source=manual`, empleado, modificación). **Punto futuro**: adaptadores externos podrán actualizar esta tabla con `source=automatic`, sin cambiar la interfaz del cliente.

Los registros históricos de productos y cantidades **se obtienen de `order_detail`**, no del catálogo actual: así una modificación del producto en tienda no cambia la compra original.

## Corrección de incidencias duplicadas — v0.1.6

- Evita el HTTP 500 posterior al alta cuando el notificador de correo lanza una excepción u otro error; las incidencias se mantienen y la página redirige mediante POST/Redirect/GET.
- Un identificador aleatorio por formulario se registra con índice único en `PREFIX_pscc_submission` y se confirma en la **misma transacción** que el ticket o mensaje. Repetir el POST no crea nuevos registros ni reenvía el correo.
- Se protege también la respuesta del cliente a una incidencia ya abierta.
- Migración aditiva `upgrade/upgrade-0.1.5.php`; sin cambios de esquema en tickets, mensajes, tracking ni en la configuración del correo `PSCC_RECIPIENT`.
- La causa exacta del 500 inicial debe corroborarse con el registro de errores PHP del entorno afectado; los test automatizados no replican el hosting.

## Pruebas mínimas antes de producción

- [ ] Instalación / desinstalación y reinstalación sobre staging.
- [ ] Prueba real PrestaShop 1.7.8.11 y 9.x, tema de tienda incluido, y PHP/SMTP del hosting.
- [ ] Verificar que no es posible acceder a pedidos/incidencias de otro cliente cambiando `id_order` o `ticket_id`.
- [ ] Validación de asunto/mensaje, token de formulario, productos no pertenecientes al pedido, y límite de envíos.
- [ ] Apertura, respuesta del cliente, respuesta del personal y cambio de estado.
- [ ] Alta manual de tracking, preferencia frente al nativo, persistencia tras actualizar el pedido.
- [ ] Correo enviado y caso de fallo SMTP con mensaje persistido.
- [ ] Evaluar cumplimiento RGPD, retención, exportación/borrado por cliente y accesibilidad antes de desplegar.

## Revisión UX y accesibilidad v0.1.6

- Tarjeta nativa del dashboard de Mi cuenta (Classic): wrapper de columna y span link-item.
- Navegación interna aislada de las tarjetas de estadísticas, sin solapamientos.
- Filas de pedidos: paneles responsive separados para referencia, estado, código, URL del transportista y acciones.
- Validación en administración que advierte si se pega una dirección web en el campo del código.
- Sin cambios en PSCC_RECIPIENT ni en Mail::Send().


- Una sola cabecera H1 dentro de la vista del módulo; navegación interna entre pedidos e incidencias.
- URL completa de seguimiento visible y enlazada, además de botón claro para abrir la web del transportista.
- Área de interacción mínima de 44 px, foco visible, etiquetas asociadas, ayuda accesible en los mensajes y diseño responsive de 320 px en adelante.
- Mensajes de éxito y error con `role=status` / `role=alert`; `target=_blank` acompañado de aviso de nueva pestaña.
- Las comprobaciones de código no equivalen a una auditoría WCAG 2.2 completa con lector de pantalla ni a una prueba en el tema real.

**Autor:** Eduardo Yauri — https://www.eduardoyauriluna.com

## Fuentes técnicas

- https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/displaycustomeraccount/
- https://devdocs.prestashop-project.org/9/modules/concepts/controllers/front-controllers/
- https://devdocs.prestashop-project.org/9/development/mail/
- https://devdocs.prestashop-project.org/9/development/components/database/db/

## v0.1.6 — cierre e histórico unificado por pedido

- Una sola conversación activa por pedido; nuevas solicitudes simultáneas se serializan con bloqueo de fila InnoDB.
- Un formulario antiguo enviado durante un expediente activo agrega el mensaje a ese expediente en vez de abrir otro.
- Cierre por el cliente y opción **Cerrada · Archivada** en administración; el expediente deja de aceptar respuestas sin borrar su historial.
- Agrupación de los expedientes anteriores por pedido y cronología conjunta de sus mensajes, sin fusionar ni eliminar registros originales.
- Diagnóstico seguro del último resultado de correo en administración, usando siempre Mail::Send y PSCC_RECIPIENT.
- Actualización sin modificar tablas existentes ni códigos de seguimiento.
