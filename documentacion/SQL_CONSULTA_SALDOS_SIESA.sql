SELECT 
                        sal.f353_rowid AS RowId,
                        CONCAT(salmov.f354_id_tipo_docto_cruce, '-', salmov.f354_consec_docto_cruce) AS FacturaSiesa,
                        ter.f200_nit AS CedulaDeudor,
                        ter.f200_razon_social AS Nombre,
                        LEFT(
                            COALESCE(
                                MIN(NULLIF(salmov.f354_notas, '')),
                                MIN(NULLIF(sal.f353_notas, ''))
                            ),
                            CHARINDEX(
                                ',',
                                COALESCE(
                                    MIN(NULLIF(salmov.f354_notas, '')),
                                    MIN(NULLIF(sal.f353_notas, ''))
                                ) + ','
                            ) - 1
                        ) AS Factura,	
                        aux.f681_id_auxiliar AS Cuenta,
                        aux.f681_descripcion_auxiliar AS NombreCuenta,
                        -- SALDO CAPITAL calculado desde movimientos
                        SUM(CASE 
                                WHEN aux.f681_id_auxiliar IN ('1305050015', '1305050035', '1305050016', '1305050036', '8325100100') 
                                THEN ISNULL(salmov.f354_valor_db, 0) - ISNULL(salmov.f354_valor_cr, 0)
                                ELSE 0 
                            END
                            ) AS SaldoCapital,
                        -- INTERÉS CORRIENTE calculado desde movimientos
                        CASE 
                            WHEN aux.f681_id_auxiliar IN ('1305050025', '1305050026', '1305050043')						
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS InteresCorriente,
                        -- INTERES DIFERIDO calculado desde movimientos
                        CASE 
                            WHEN aux.f681_id_auxiliar IN ('1305050027') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS InteresDiferido,
                        -- SEGURO DEUDOR calculado desde movimientos
                        CASE 
                            WHEN aux.f681_id_auxiliar IN ('1305050106') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS SeguroDeudor,
                        -- SEGURO AP calculado desde movimientos
                        CASE 
                            WHEN aux.f681_id_auxiliar IN ('1305050105') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS SeguroAP,
                        -- SEGURO MOTOS calculado desde movimientos
                        CASE 
                            WHEN aux.f681_id_auxiliar IN ('1305050296') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS SeguroMotos,
                        -- FONDO DE GARANTÍA calculado desde movimientos
                        CASE
                            WHEN aux.f681_id_auxiliar IN ('1305050093', '1305050095') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS FondoGarantia,
                        -- COMISION MICROCREDITO calculado desde movimientos
                        CASE
                            WHEN aux.f681_id_auxiliar IN ('1345150101') 
                                THEN ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)
                            ELSE 0 
                        END AS ComisionMicro,
                        MIN(con.f015_direccion1) AS DireccionDeudor,
                        MIN(con.f015_id_barrio) AS BarrioDeudor,
                        MIN(con.f015_telefono) AS TelefonoDeudor,
                        MIN(con.f015_email) AS EmailDeudor,
                        MIN(dep.f012_descripcion) AS Departamento,
                        MIN(ciu.f013_descripcion) AS Ciudad,
                        CASE 
                            WHEN MIN(ter.f200_id_tipo_ident) = 'C' THEN 'CC'
                            ELSE MIN(ter.f200_id_tipo_ident)
                        END AS TipoDocumento,
                        COALESCE(
                            MIN(NULLIF(salmov.f354_notas, '')),
                            MIN(NULLIF(sal.f353_notas, ''))
                        ) AS Observaciones,
                        (ISNULL(SUM(salmov.f354_valor_db), 0) - ISNULL(SUM(salmov.f354_valor_cr), 0)) AS TotalSaldo
                    FROM UNOEEARAR..t353_co_saldo_abierto sal
                    INNER JOIN UNOEEARAR..t354_co_mov_saldo_abierto salmov ON salmov.f354_rowid_sa = sal.f353_rowid AND CONVERT(DATE, salmov.f354_fecha) <= '2026-07-31'
                    INNER JOIN UNOEEARAR..t681_in_auxiliares_bi aux ON sal.f353_rowid_auxiliar = aux.f681_rowid_auxiliar AND f681_id_plan='NIC' AND f353_id_cia = f681_id_cia
                    INNER JOIN UNOEEARAR..t200_mm_terceros ter ON sal.f353_rowid_tercero = ter.f200_rowid
                    INNER JOIN UNOEEARAR.dbo.t201_mm_clientes cli ON ter.f200_rowid = cli.f201_rowid_tercero
                    INNER JOIN UNOEEARAR.dbo.t015_mm_contactos con ON ter.f200_rowid_contacto = con.f015_rowid
                    INNER JOIN UNOEEARAR.dbo.t012_mm_deptos dep ON con.f015_id_depto = dep.f012_id AND con.f015_id_pais = dep.f012_id_pais
                    INNER JOIN UNOEEARAR.dbo.t013_mm_ciudades ciu ON con.f015_id_ciudad = ciu.f013_id AND dep.f012_id = ciu.f013_id_depto
WHERE sal.f353_id_cia=7 AND salmov.f354_id_tipo_docto_cruce IN ('FAT', 'OPE')
    AND ter.f200_nit = '1122121626' AND salmov.f354_notas LIKE '%7582%' 
GROUP BY sal.f353_rowid, ter.f200_nit, ter.f200_razon_social, aux.f681_id_auxiliar, aux.f681_descripcion_auxiliar, salmov.f354_consec_docto_cruce, salmov.f354_id_tipo_docto_cruce
ORDER BY ter.f200_nit, RowId ASC;