-- =============================================================================
-- SISTEMA DE GESTÃO DE ESTOQUE E ENDEREÇAMENTO 2D - SENAI
-- Schema do Banco de Dados Relacional MySQL
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `senai_estoque_2d` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `senai_estoque_2d`;

-- -----------------------------------------------------------------------------
-- 1. Tabela: products (Catálogo Mestre de Produtos)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `sku` VARCHAR(32) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `category` VARCHAR(64) NOT NULL,
    `unit` VARCHAR(16) NOT NULL DEFAULT 'un',
    `min_stock` INT NOT NULL DEFAULT 5,
    `max_stock` INT NULL DEFAULT NULL,
    `default_expiry` VARCHAR(10) NULL DEFAULT NULL,
    `storage` VARCHAR(128) NULL DEFAULT NULL,
    `flag_perecivel` TINYINT(1) NOT NULL DEFAULT 0,
    `flag_epi` TINYINT(1) NOT NULL DEFAULT 0,
    `flag_controlado` TINYINT(1) NOT NULL DEFAULT 0,
    `photo` TEXT NULL,
    `observations` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_sku` (`sku`),
    INDEX `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. Tabela: slots (Posições Físicas do Mapa 2D)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `slots` (
    `slot_code` VARCHAR(16) NOT NULL PRIMARY KEY,
    `product_id` VARCHAR(64) NOT NULL,
    `qty` INT NOT NULL DEFAULT 0,
    `entry_date` VARCHAR(10) NULL DEFAULT NULL,
    `expiry_date` VARCHAR(10) NULL DEFAULT NULL,
    `lot` VARCHAR(64) NULL DEFAULT NULL,
    `observations` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_product_id` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. Tabela: unallocated_lots (Estoque Geral / Reserva não alocada no mapa)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `unallocated_lots` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `product_id` VARCHAR(64) NOT NULL,
    `qty` INT NOT NULL DEFAULT 0,
    `entry_date` VARCHAR(10) NULL DEFAULT NULL,
    `expiry_date` VARCHAR(10) NULL DEFAULT NULL,
    `observations` TEXT NULL,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_unalloc_prod` (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. Tabela: custom_slots (Posições Físicas Customizadas Adicionais)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `custom_slots` (
    `code` VARCHAR(16) NOT NULL PRIMARY KEY,
    `label` VARCHAR(128) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. Tabela: history (Registro Histórico e Auditoria de Movimentações)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `history` (
    `id` VARCHAR(64) NOT NULL PRIMARY KEY,
    `timestamp` VARCHAR(32) NOT NULL,
    `type` VARCHAR(32) NOT NULL,
    `product_name` VARCHAR(255) NOT NULL,
    `slot_code` VARCHAR(64) NOT NULL,
    `qty_text` VARCHAR(64) NOT NULL,
    `note` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_hist_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- DADOS INICIAIS DE REFERÊNCIA (SENAI)
-- =============================================================================

INSERT INTO `products` (`id`, `sku`, `name`, `category`, `unit`, `min_stock`, `max_stock`, `default_expiry`, `storage`, `flag_perecivel`, `flag_epi`, `flag_controlado`, `observations`) VALUES
('prod-001', 'FAR-001', 'Farinha de Trigo Especial 1kg', 'Farinhas & Amidos', 'kg', 10, 50, '2027-06-30', 'Ambiente Seco / Arejado', 1, 0, 0, 'Uso em aulas de panificação'),
('prod-002', 'FAR-002', 'Fermento Biológico Seco 500g', 'Farinhas & Amidos', 'un', 5, 30, '2027-03-31', 'Ambiente Seco / Arejado', 1, 0, 0, 'Conservar em local seco'),
('prod-003', 'DOC-001', 'Chocolate em Barra 1kg 50% Cacau', 'Doces & Chocolates', 'kg', 6, 25, '2027-08-31', 'Temperatura Controlada (18°C–22°C)', 1, 0, 0, 'Para confeitaria fina'),
('prod-004', 'DOC-002', 'Açúcar Cristal 5kg', 'Doces & Chocolates', 'pct', 8, 40, '2027-11-30', 'Ambiente Seco / Arejado', 0, 0, 0, 'Armazenamento sobre estrado'),
('prod-005', 'BEB-001', 'Café Superior Torrado e Moído 500g', 'Bebidas', 'pct', 12, 60, '2027-04-30', 'Ambiente Seco / Arejado', 1, 0, 0, 'Consumo no refeitório e eventos'),
('prod-006', 'LAT-001', 'Leite Integral UHT 1L', 'Laticínios & Ovos', 'L', 15, 60, '2027-03-15', 'Ambiente Seco / Arejado', 1, 0, 0, 'Após aberto refrigerar'),
('prod-007', 'HIG-001', 'Álcool 70% Líquido 1L', 'Higiene & Limpeza', 'fr', 8, 30, '2028-11-30', 'Armário Ventilado / Longe de Calor', 0, 0, 1, 'Item inflamável, armazenado em A1'),
('prod-008', 'HIG-002', 'Detergente Neutro 5L', 'Higiene & Limpeza', 'gl', 4, 16, '2027-11-30', 'Ambiente Seco / Arejado', 0, 0, 0, 'Sanitização de bancadas'),
('prod-009', 'EPI-001', 'Luva de Procedimento Látex (M) c/100', 'Higiene & Limpeza', 'cx', 5, 20, '2028-11-30', 'Ambiente Seco / Arejado', 0, 1, 0, 'EPI obrigatório para manipulação'),
('prod-010', 'DES-001', 'Papel Toalha Interfolhado 2 Dobras', 'Descartáveis & Embalagens', 'pct', 10, 50, '', 'Ambiente Seco / Arejado', 0, 0, 0, 'Suprimento das pias de higienização')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `slots` (`slot_code`, `product_id`, `qty`, `entry_date`, `expiry_date`, `lot`, `observations`) VALUES
('M1', 'prod-001', 20, '2026-11-25', '2027-06-30', 'LOTE-FAR2026-01', 'Estante Madeira 1'),
('M2', 'prod-002', 12, '2026-11-25', '2027-03-31', 'LOTE-FER2026-02', 'Estante Madeira 2'),
('M3', 'prod-003', 8, '2026-11-25', '2027-08-31', 'LOTE-CHO2026-01', 'Estante Madeira 3'),
('G1', 'prod-009', 6, '2026-11-25', '2028-11-30', 'LOTE-EPI2026-A', 'Gaveteiro Baixo'),
('E1', 'prod-008', 4, '2026-11-25', '2027-11-30', 'LOTE-DET2026', 'Estante Aço Esquerda'),
('F1', 'prod-005', 15, '2026-11-25', '2027-04-30', 'LOTE-CAF2026', 'Estante Fundo 1'),
('I1', 'prod-006', 24, '2026-11-25', '2027-03-15', 'LOTE-LEI2026', 'Ilha Central'),
('P1', 'prod-010', 18, '2026-11-25', '', 'LOTE-PAP2026', 'Área de Palete'),
('D1', 'prod-004', 10, '2026-11-25', '2027-11-30', 'LOTE-ACU2026', 'Estante Aço Direita 1'),
('A1', 'prod-007', 12, '2026-11-25', '2028-11-30', 'LOTE-ALC2026', 'Armário Fechado 2 Portas')
ON DUPLICATE KEY UPDATE `slot_code`=`slot_code`;

INSERT INTO `unallocated_lots` (`id`, `product_id`, `qty`, `entry_date`, `expiry_date`, `observations`) VALUES
('lot-seed-1', 'prod-001', 5, '2026-11-25', '2027-06-30', 'Lote de reserva geral')
ON DUPLICATE KEY UPDATE `id`=`id`;

INSERT INTO `history` (`id`, `timestamp`, `type`, `product_name`, `slot_code`, `qty_text`, `note`) VALUES
('hist-seed-1', '25/11/2026 18:00:00', 'ENTRADA', 'Carga Inicial do Sistema', 'SENAI-BASE', '10 produtos', 'Migração concluída para MySQL + PHP')
ON DUPLICATE KEY UPDATE `id`=`id`;
