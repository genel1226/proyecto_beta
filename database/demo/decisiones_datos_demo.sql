-- =====================================================================
-- DATOS EXTRA PARA LOS REPORTES DE "DECISIONES"
--
-- CORRER DESPUES de ensayo_datos.sql (no borra L0001-L0006 ni sus pagos).
-- Se puede correr varias veces: borra solo lo que agrega (E009+ y L0007+).
--
-- Agrega:
--   * 17 empresas (E009-E025) en Bolivia, Peru, Chile, Colombia, Mexico y Ecuador
--   * 16 licencias (L0007-L0022) con descuentos de 0 a 25 USD, licencias
--     renovadas varias veces, perdidas (Vencida/Cancelada) y una reemplazada
--     (L0013 -> L0014)
--   * pagos mensuales (venta inicial + renovaciones) hasta hoy
-- =====================================================================

USE proyecto_beta_v5;

SET FOREIGN_KEY_CHECKS = 0;

-- ---- limpiar lo agregado antes (permite re-correr) ----
DELETE FROM licencia_pagos WHERE licencia_id IN (SELECT id FROM licencias WHERE codigo_licencia >= 'L0007');
DELETE FROM licencia_detalle_usuarios WHERE licencia_id IN (SELECT id FROM licencias WHERE codigo_licencia >= 'L0007');
DELETE FROM licencias WHERE codigo_licencia >= 'L0007';
DELETE FROM empresas WHERE cod >= 'E009';

SET FOREIGN_KEY_CHECKS = 1;

-- ---- empresas ----
INSERT INTO empresas
    (cod, razon_social, nombre_comercial, nit, slug, email, telefono, active, pais, direccion, con_licencia, trial_ends_at, created_at, updated_at)
VALUES
 ('E009', 'Minera Altiplano S.A.', 'Minera Altiplano', '20100010', 'demo-e009', 'contacto@e009.demo.test', '7100010', 1, 'Bolivia', 'Direccion demo E009', '1', NULL, NOW(), NOW()),
 ('E010', 'Constructora Illimani Ltda.', 'Constructora Illimani', '20110011', 'demo-e010', 'contacto@e010.demo.test', '7110011', 1, 'Bolivia', 'Direccion demo E010', '1', NULL, NOW(), NOW()),
 ('E011', 'Logistica Pacifico S.A.C.', 'Logistica Pacifico', '20120012', 'demo-e011', 'contacto@e011.demo.test', '7120012', 0, 'Peru', 'Direccion demo E011', '0', NULL, NOW(), NOW()),
 ('E012', 'Agroindustrias Sol S.A.C.', 'Agroindustrias Sol', '20130013', 'demo-e012', 'contacto@e012.demo.test', '7130013', 1, 'Peru', 'Direccion demo E012', '1', NULL, NOW(), NOW()),
 ('E013', 'Transportes Andes SpA', 'Transportes Andes', '20140014', 'demo-e013', 'contacto@e013.demo.test', '7140014', 0, 'Chile', 'Direccion demo E013', '0', NULL, NOW(), NOW()),
 ('E014', 'Cafe Huila S.A.S.', 'Cafe Huila', '20150015', 'demo-e014', 'contacto@e014.demo.test', '7150015', 1, 'Colombia', 'Direccion demo E014', '1', NULL, NOW(), NOW()),
 ('E015', 'Textiles Puebla S.A. de C.V.', 'Textiles Puebla', '20160016', 'demo-e015', 'contacto@e015.demo.test', '7160016', 1, 'Mexico', 'Direccion demo E015', '1', NULL, NOW(), NOW()),
 ('E016', 'Embotelladora Tarija S.R.L.', 'Embotelladora Tarija', '20170017', 'demo-e016', 'contacto@e016.demo.test', '7170017', 1, 'Bolivia', 'Direccion demo E016', '1', NULL, NOW(), NOW()),
 ('E017', 'Quito Motors Cia. Ltda.', 'Quito Motors', '20180018', 'demo-e017', 'contacto@e017.demo.test', '7180018', 0, 'Ecuador', 'Direccion demo E017', '0', NULL, NOW(), NOW()),
 ('E018', 'Panificadora Oruro S.R.L.', 'Panificadora Oruro', '20190019', 'demo-e018', 'contacto@e018.demo.test', '7190019', 1, 'Bolivia', 'Direccion demo E018', '1', NULL, NOW(), NOW()),
 ('E019', 'Mantenimiento Cusco S.A.C.', 'Mantenimiento Cusco', '20200020', 'demo-e019', 'contacto@e019.demo.test', '7200020', 1, 'Peru', 'Direccion demo E019', '1', NULL, NOW(), NOW()),
 ('E020', 'Plasticos Medellin S.A.S.', 'Plasticos Medellin', '20210021', 'demo-e020', 'contacto@e020.demo.test', '7210021', 1, 'Colombia', 'Direccion demo E020', '1', NULL, NOW(), NOW()),
 ('E021', 'Hotel Riviera Demo', 'Hotel Riviera (demo)', '20220022', 'demo-e021', 'contacto@e021.demo.test', '7220022', 1, 'Mexico', 'Direccion demo E021', '1', DATE_ADD(NOW(), INTERVAL 5 DAY), NOW(), NOW()),
 ('E022', 'Taller Guayaquil Demo', 'Taller Guayaquil (demo)', '20230023', 'demo-e022', 'contacto@e022.demo.test', '7230023', 0, 'Ecuador', 'Direccion demo E022', '0', DATE_SUB(NOW(), INTERVAL 4 DAY), NOW(), NOW()),
 ('E023', 'Aceros Monterrey S.A.', 'Aceros Monterrey', '20240024', 'demo-e023', 'contacto@e023.demo.test', '7240024', 0, 'Mexico', 'Direccion demo E023', '0', NULL, NOW(), NOW()),
 ('E024', 'Frigorifico Arequipa S.A.C.', 'Frigorifico Arequipa', '20250025', 'demo-e024', 'contacto@e024.demo.test', '7250025', 0, 'Peru', 'Direccion demo E024', '0', NULL, NOW(), NOW()),
 ('E025', 'Vidrios Valparaiso Ltda.', 'Vidrios Valparaiso', '20260026', 'demo-e025', 'contacto@e025.demo.test', '7260026', 0, 'Chile', 'Direccion demo E025', '0', NULL, NOW(), NOW());

-- ---- licencias (monto en 0: se calcula abajo) ----
INSERT INTO licencias
    (empresa_id, plan_id, codigo_licencia, fecha_inicio, fecha_vencimiento, periodicidad, monto, descuento, moneda, estado, vendedor_id, observaciones, created_at, updated_at)
VALUES
 ((SELECT id FROM empresas WHERE cod = 'E009'), (SELECT id FROM plans WHERE nombre = 'Ultra' LIMIT 1), 'L0007', DATE_SUB(CURDATE(), INTERVAL 170 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'M', 0, 0, 'USD', 'V', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E010'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0008', DATE_SUB(CURDATE(), INTERVAL 150 DAY), DATE_ADD(CURDATE(), INTERVAL 0 DAY), 'M', 0, 5, 'USD', 'X', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E011'), (SELECT id FROM plans WHERE nombre = 'Lite' LIMIT 1), 'L0009', DATE_SUB(CURDATE(), INTERVAL 140 DAY), DATE_SUB(CURDATE(), INTERVAL 80 DAY), 'M', 0, 0, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E012'), (SELECT id FROM plans WHERE nombre = 'Insight' LIMIT 1), 'L0010', DATE_SUB(CURDATE(), INTERVAL 110 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'M', 0, 15, 'USD', 'V', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E013'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0011', DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 70 DAY), 'M', 0, 25, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E014'), (SELECT id FROM plans WHERE nombre = 'Ultra' LIMIT 1), 'L0012', DATE_SUB(CURDATE(), INTERVAL 85 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'M', 0, 10, 'USD', 'X', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E015'), (SELECT id FROM plans WHERE nombre = 'Lite' LIMIT 1), 'L0013', DATE_SUB(CURDATE(), INTERVAL 75 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'M', 0, 0, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E015'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0014', DATE_SUB(CURDATE(), INTERVAL 14 DAY), DATE_ADD(CURDATE(), INTERVAL 16 DAY), 'M', 0, 0, 'USD', 'V', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E016'), (SELECT id FROM plans WHERE nombre = 'Insight' LIMIT 1), 'L0015', DATE_SUB(CURDATE(), INTERVAL 55 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), 'M', 0, 20, 'USD', 'X', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E017'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0016', DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), 'M', 0, 0, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E018'), (SELECT id FROM plans WHERE nombre = 'Lite' LIMIT 1), 'L0017', DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), 'M', 0, 5, 'USD', 'V', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E019'), (SELECT id FROM plans WHERE nombre = 'Ultra' LIMIT 1), 'L0018', DATE_SUB(CURDATE(), INTERVAL 28 DAY), DATE_ADD(CURDATE(), INTERVAL 2 DAY), 'M', 0, 0, 'USD', 'X', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E020'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0019', DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), 'M', 0, 10, 'USD', 'V', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E023'), (SELECT id FROM plans WHERE nombre = 'Lite' LIMIT 1), 'L0020', DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), 'M', 0, 0, 'USD', 'C', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E024'), (SELECT id FROM plans WHERE nombre = 'Lite' LIMIT 1), 'L0021', DATE_SUB(CURDATE(), INTERVAL 160 DAY), DATE_SUB(CURDATE(), INTERVAL 70 DAY), 'M', 0, 0, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW()),
 ((SELECT id FROM empresas WHERE cod = 'E025'), (SELECT id FROM plans WHERE nombre = 'Connect' LIMIT 1), 'L0022', DATE_SUB(CURDATE(), INTERVAL 130 DAY), DATE_SUB(CURDATE(), INTERVAL 70 DAY), 'M', 0, 0, 'USD', 'N', (SELECT MIN(id) FROM users), 'Demo decisiones', NOW(), NOW());

-- licencia reemplazada (L0013 se cambio por L0014)
UPDATE licencias n JOIN licencias o ON o.codigo_licencia = 'L0013' SET n.reemplaza_a_licencia_id = o.id WHERE n.codigo_licencia = 'L0014';

-- ---- usuarios de cada licencia (precio = catalogo actual) ----
INSERT INTO licencia_detalle_usuarios (licencia_id, tipo_usuario_id, cantidad, precio_unitario_aplicado, created_at, updated_at)
SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, t.id, 4, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0009'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0009'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, t.id, 5, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0011'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0011'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, t.id, 6, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0013'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0013'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0014'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0014'
UNION ALL SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0014'
UNION ALL SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0015'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0015'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0015'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0016'
UNION ALL SELECT l.id, t.id, 3, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0016'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0017'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0017'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0017'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0018'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0018'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0018'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0019'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0019'
UNION ALL SELECT l.id, t.id, 4, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0019'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0020'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0020'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0021'
UNION ALL SELECT l.id, t.id, 2, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0021'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-1' WHERE l.codigo_licencia = 'L0022'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-2' WHERE l.codigo_licencia = 'L0022'
UNION ALL SELECT l.id, t.id, 1, t.precio_unitario, NOW(), NOW() FROM licencias l JOIN tipos_usuario t ON t.codigo = 'GT-3' WHERE l.codigo_licencia = 'L0022';

-- ---- monto = plan + usuarios - descuento (misma formula del formulario) ----
UPDATE licencias l
JOIN plans p ON p.id = l.plan_id
LEFT JOIN (SELECT licencia_id, SUM(cantidad * precio_unitario_aplicado) AS subtotal FROM licencia_detalle_usuarios GROUP BY licencia_id) d ON d.licencia_id = l.id
SET l.monto = GREATEST(p.monto + COALESCE(d.subtotal, 0) - l.descuento, 0)
WHERE l.codigo_licencia >= 'L0007';

-- ---- pagos: venta inicial + una renovacion por cada ciclo siguiente ----
INSERT INTO licencia_pagos (licencia_id, fecha_pago, monto, fecha_vencimiento_anterior, fecha_vencimiento_nueva, registrado_por, created_at)
SELECT l.id, DATE_SUB(NOW(), INTERVAL 170 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 140 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 140 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 140 DAY), DATE_SUB(CURDATE(), INTERVAL 110 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 110 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 110 DAY), DATE_SUB(CURDATE(), INTERVAL 80 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 80 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 80 DAY), DATE_SUB(CURDATE(), INTERVAL 50 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 50 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 20 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0007'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 150 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 120 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 120 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 120 DAY), DATE_SUB(CURDATE(), INTERVAL 90 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 90 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 90 DAY), DATE_SUB(CURDATE(), INTERVAL 60 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 60 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 60 DAY), DATE_SUB(CURDATE(), INTERVAL 30 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 30 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 30 DAY), CURDATE(), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0008'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 140 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 110 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0009'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 110 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 110 DAY), DATE_SUB(CURDATE(), INTERVAL 80 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0009'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 110 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 80 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 80 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 80 DAY), DATE_SUB(CURDATE(), INTERVAL 50 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 50 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 50 DAY), DATE_SUB(CURDATE(), INTERVAL 20 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 20 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 20 DAY), DATE_ADD(CURDATE(), INTERVAL 10 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0010'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 100 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 70 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0011'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 85 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 55 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 55 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 55 DAY), DATE_SUB(CURDATE(), INTERVAL 25 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 25 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0012'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 75 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 45 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0013'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 45 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 45 DAY), DATE_SUB(CURDATE(), INTERVAL 15 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0013'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 14 DAY), l.monto, NULL, DATE_ADD(CURDATE(), INTERVAL 16 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0014'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 55 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 25 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0015'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 25 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 25 DAY), DATE_ADD(CURDATE(), INTERVAL 5 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0015'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 45 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 15 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0016'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 40 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 10 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0017'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 10 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 10 DAY), DATE_ADD(CURDATE(), INTERVAL 20 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0017'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 28 DAY), l.monto, NULL, DATE_ADD(CURDATE(), INTERVAL 2 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0018'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 20 DAY), l.monto, NULL, DATE_ADD(CURDATE(), INTERVAL 10 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0019'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 100 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 70 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0020'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 70 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 70 DAY), DATE_SUB(CURDATE(), INTERVAL 40 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0020'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 40 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 40 DAY), DATE_SUB(CURDATE(), INTERVAL 10 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0020'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 160 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 130 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0021'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 130 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 130 DAY), DATE_SUB(CURDATE(), INTERVAL 100 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0021'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 100 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 70 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0021'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 130 DAY), l.monto, NULL, DATE_SUB(CURDATE(), INTERVAL 100 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0022'
UNION ALL SELECT l.id, DATE_SUB(NOW(), INTERVAL 100 DAY), l.monto, DATE_SUB(CURDATE(), INTERVAL 100 DAY), DATE_SUB(CURDATE(), INTERVAL 70 DAY), (SELECT MIN(id) FROM users), NOW() FROM licencias l WHERE l.codigo_licencia = 'L0022';

-- ---- verificacion ----
SELECT l.codigo_licencia, e.pais, l.estado, l.fecha_vencimiento, l.descuento, l.monto, u.name AS vendedor,
       (SELECT COUNT(*) FROM licencia_pagos p WHERE p.licencia_id = l.id) AS pagos
FROM licencias l JOIN empresas e ON e.id = l.empresa_id LEFT JOIN users u ON u.id = l.vendedor_id
ORDER BY l.codigo_licencia;