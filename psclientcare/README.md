# PS Client Care — v0.1.0 (beta instalable)

Módulo **independiente** para PrestaShop; no requiere Salesforce, CRM externo, overrides ni cambios en la base del core.

## Funcionalidades

- `Mi cuenta` → **Incidencias y seguimiento** mediante el hook `displayCustomerAccount`.
- Lista de los últimos 80 pedidos **del cliente autenticado** y **de la tienda actual**. Enlaces a pedido individual.
- Cada pedido muestra número/referencia, estado, seguimiento y **todos los artículos adquiridos** con cantidades/referencias.
- Formulario para abrir incidencia con asunto y descripción; selección opcional de artículos afectados. En cada email se incluyen **todos** los artículos del pedido.
- Historial de incidencias (hasta 100) y conversación con respuestas y reapertura automática al responder a una incidencia resuelta.
- Back Office → Módulos → Configurar «Portal del cliente»: bandeja de incidencias, respuestas, cambios de estado, email receptor y alta manual de códigos de seguimiento por ID de pedido.
- Prioridad de visualización de tracking: **código manual del módulo**, después código nativo `order_carrier` si existe, después «Pendiente». La escritura manual **no altera** tablas de envío nativas.
- Correos en plantillas `es`, `en`, `ca`, `pt`; se usa el email transaccional configurado en PrestaShop. Notificaciones de apertura/respuesta del cliente a la tienda y respuesta del personal al cliente.

## Instalación

1. Haz una copia de seguridad de pruebas de la tienda.
2. En **Back Office → Módulos → Gestor de módulos → Subir un módulo**, instala el ZIP `psclientcare-v0.1.0.zip` tal cual, con la carpeta `psclientcare/` en su raíz.
3. Ve a **Configurar** y comprueba/cambia el email de atención al cliente.
4. Comprueba el correo transaccional/SMTP de PrestaShop enviando un correo de prueba desde su configuración.
5. Con una cuenta cliente con pedidos, abre **Mi cuenta → Incidencias y seguimiento** y crea una incidencia de prueba.
6. Desde la configuración del módulo consulta ese caso, respóndelo y asigna un número de seguimiento a un pedido (usa su ID interno, visible en la tabla).
7. Confirma que el cliente ve el tracking y la respuesta. Prueba con un segundo cliente que no pueda acceder a pedidos/incidencias del primero.

## Seguridad y límites

- El controlador es SSL, requiere autenticación, valida token de formulario y propiedad del pedido y de la incidencia para cada lectura/escritura.
- Se verifica que cada artículo seleccionado **pertenece al pedido**. Texto plano, salida HTML escapada, máximo 4000 caracteres por mensaje, límite básico de 3 mensajes/minuto por cliente.
- Las incidencias se guardan primero y **después** se intenta el envío por correo. Si PrestaShop no acepta el correo, el mensaje **se conserva** con `email_status=failed`; **no existe reintento automático en esta versión**.
- Para evitar modificaciones accidentales, desinstalar el módulo **conserva las tablas y las conversaciones**. El responsable deberá definir una política de conservación y supresión RGPD; no borres la base de datos sin exportar primero lo legalmente necesario.
- Fase 1 limitada a una tienda activa por contexto de administración. Si usas multitienda, selecciona una tienda concreta al configurar. No se garantiza que el hook exista en un tema personalizado; en ese caso añade `{hook h='displayCustomerAccount'}` donde corresponda.
- No incluye adjuntos, SLA, notificaciones de tracking, enlace dinámico del transportista, paginación avanzada ni transportistas automatizados. Quedan fuera de alcance de v0.1.0.
- Compatibilidad **objetivo**, no certificada en entorno real: PrestaShop `1.7.8.x`, `8.x`, `9.x`; PHP según versión de PrestaShop. Las pruebas de sintaxis no sustituyen instalación/flujo SMTP en una tienda de pruebas.

## Arquitectura de datos

- `PREFIX_pscc_ticket` (pedido, cliente, tienda, estado, asunto).
- `PREFIX_pscc_message` (historial y resultado del intento de notificación).
- `PREFIX_pscc_ticket_product` (productos afectados como claves a las líneas **históricas** del pedido).
- `PREFIX_pscc_tracking` (`id_order` único, código, `source=manual`, empleado, modificación). **Punto futuro**: adaptadores externos podrán actualizar esta tabla con `source=automatic`, sin cambiar la interfaz del cliente.

Los registros históricos de productos y cantidades **se obtienen de `order_detail`**, no del catálogo actual: así una modificación del producto en tienda no cambia la compra original.

## Pruebas mínimas antes de producción

- [ ] Instalación / desinstalación y reinstalación sobre staging.
- [ ] Prueba real PrestaShop 1.7.8.11 y 9.x, tema de tienda incluido, y PHP/SMTP del hosting.
- [ ] Verificar que no es posible acceder a pedidos/incidencias de otro cliente cambiando `id_order` o `ticket_id`.
- [ ] Validación de asunto/mensaje, token de formulario, productos no pertenecientes al pedido, y límite de envíos.
- [ ] Apertura, respuesta del cliente, respuesta del personal y cambio de estado.
- [ ] Alta manual de tracking, preferencia frente al nativo, persistencia tras actualizar el pedido.
- [ ] Correo enviado y caso de fallo SMTP con mensaje persistido.
- [ ] Evaluar cumplimiento RGPD, retención, exportación/borrado por cliente y accesibilidad antes de desplegar.

## Fuentes técnicas

- https://devdocs.prestashop-project.org/9/modules/concepts/hooks/list-of-hooks/displaycustomeraccount/
- https://devdocs.prestashop-project.org/9/modules/concepts/controllers/front-controllers/
- https://devdocs.prestashop-project.org/9/development/mail/
- https://devdocs.prestashop-project.org/9/development/components/database/db/
