# PS Client Care — Módulo para PrestaShop

Módulo independiente de atención al cliente: incidencias asociadas a pedidos y seguimiento manual de envíos.

## Alcance de la primera beta (v0.1.0)

- Acceso desde **Mi cuenta** del cliente.
- Pedidos y líneas históricas compradas; selección opcional de productos afectados.
- Alta de incidencias, conversación y estado, con notificación mediante correo transaccional de PrestaShop.
- Bandeja para el personal desde la configuración del módulo.
- Número de seguimiento manual por pedido, sin modificar el tracking nativo.
- Preparación del campo `source` para una futura integración automática con transportistas.

## Reglas

1. Sin cambios al core de PrestaShop, sin overrides y sin integración con Salesforce.
2. Todas las consultas de cliente deben respetar `id_customer` e `id_shop`.
3. No incluir secretos, credenciales, datos reales de clientes ni volcados de base de datos.
4. La compatibilidad objetivo con PrestaShop 1.7.8, 8 y 9 debe validarse antes de producción.
5. No publicar una release como estable ni instalarla en producción sin pruebas de instalación, flujo de correo y autorizaciones.
6. Desarrollo incremental mediante rama y pull request; CI proporcional al cambio.

## Instalación

La fuente del módulo reside bajo `psclientcare/`; el ZIP instalable debe contener esa carpeta en su raíz.

## Estado

Beta inicial pendiente de integración y pruebas reales en una tienda de staging.

## Notas de privacidad

Antes de incorporar configuración o integraciones privadas, comprobar que el repositorio sea **privado**. Los datos de incidencias deben permanecer en la base de datos de PrestaShop, no en GitHub.

## Propiedad intelectual y licencia

**Copyright © 2026 Eduardo Yauri. Todos los derechos reservados.**

El módulo PS Client Care se distribuye bajo **licencia propietaria**. La visibilidad pública del repositorio no concede derechos generales de modificación, copia, redistribución o comercialización. Consulta el archivo [LICENSE](LICENSE) para conocer las condiciones y los usos autorizados.

Los componentes de terceros y los permisos válidamente otorgados en versiones anteriores mantienen sus condiciones correspondientes. Autor: [Eduardo Yauri](https://www.eduardoyauriluna.com).
