-- ============================================================
-- MIGRACIÓN: Permiso "Descuento desde recibo de caja"
-- ============================================================
-- Archivo: 002_add_permiso_descuento_recibo.sql
-- Descripción: Crea el permiso "Descuento desde recibo de caja"
--              dentro del módulo "Facturación" (permisos_botones),
--              para controlar la visibilidad del campo "Descuento %"
--              al pagar una factura pendiente (recibo de caja).
--
-- IMPORTANTE: Es IDEMPOTENTE. No duplica el permiso si ya existe.
--             No se hardcodea el id: la app lo resuelve por nombre,
--             así funciona igual en todos los clientes (multi-tenant).
-- ============================================================

DELIMITER //

DROP PROCEDURE IF EXISTS add_permiso_descuento_recibo//

CREATE PROCEDURE add_permiso_descuento_recibo()
BEGIN
    DECLARE v_modulo_id INT DEFAULT NULL;

    -- Id del módulo "Facturación" (tolera con/sin tilde: 'Facturación' / 'Facturacion').
    SELECT id INTO v_modulo_id
      FROM permisos_modulo
     WHERE nombre_modulo LIKE 'Facturaci_n'
     LIMIT 1;

    IF v_modulo_id IS NULL THEN
        SELECT 'No se encontró el módulo Facturación; no se creó el permiso' AS resultado;
    ELSEIF EXISTS (SELECT 1 FROM permisos_botones WHERE nombre_permiso = 'Descuento desde recibo de caja') THEN
        SELECT 'El permiso ya existe, sin cambios' AS resultado;
    ELSE
        INSERT INTO permisos_botones (id_modulo, nombre_permiso)
        VALUES (v_modulo_id, 'Descuento desde recibo de caja');
        SELECT 'Permiso "Descuento desde recibo de caja" creado' AS resultado;
    END IF;
END//

DELIMITER ;

CALL add_permiso_descuento_recibo();

DROP PROCEDURE IF EXISTS add_permiso_descuento_recibo;

-- ============================================================
-- FIN DE MIGRACIÓN
-- ============================================================
