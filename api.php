<?php
/**
 * API RESTful JSON para Persistência no MySQL
 * Sistema de Gestão de Estoque e Endereçamento 2D - SENAI
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'get_all');
$pdo = getDbConnection();

try {
    switch ($action) {
        case 'health':
            echo json_encode(['success' => true, 'status' => 'online', 'database' => DB_NAME]);
            break;

        case 'get_all':
            handleGetAll($pdo);
            break;

        case 'save_state':
            handleSaveState($pdo);
            break;

        case 'reset_all':
            handleResetAll($pdo);
            break;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Ação desconhecida: '$action'"]);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

// -----------------------------------------------------------------------------
// Controladores de Ação
// -----------------------------------------------------------------------------

function handleGetAll(PDO $pdo) {
    // 1. Produtos
    $stmtProd = $pdo->query("SELECT * FROM `products` ORDER BY `name` ASC");
    $rawProds = $stmtProd->fetchAll();
    $products = array_map(function($p) {
        return [
            'id'             => (string)$p['id'],
            'sku'            => (string)$p['sku'],
            'name'           => (string)$p['name'],
            'category'       => (string)$p['category'],
            'unit'           => (string)$p['unit'],
            'minStock'       => (int)$p['min_stock'],
            'maxStock'       => $p['max_stock'] !== null ? (int)$p['max_stock'] : null,
            'defaultExpiry'  => (string)($p['default_expiry'] ?? ''),
            'storage'        => (string)($p['storage'] ?? ''),
            'flagPerecivel'  => (bool)$p['flag_perecivel'],
            'flagEpi'        => (bool)$p['flag_epi'],
            'flagControlado' => (bool)$p['flag_controlado'],
            'photo'          => (string)($p['photo'] ?? ''),
            'observations'   => (string)($p['observations'] ?? '')
        ];
    }, $rawProds);

    // 2. Slots (Mapa de Posições)
    $stmtSlots = $pdo->query("SELECT * FROM `slots`");
    $rawSlots = $stmtSlots->fetchAll();
    $slots = [];
    foreach ($rawSlots as $s) {
        if ((int)$s['qty'] > 0 && !empty($s['product_id'])) {
            $slots[$s['slot_code']] = [
                'productId'    => (string)$s['product_id'],
                'qty'          => (int)$s['qty'],
                'entryDate'    => (string)($s['entry_date'] ?? ''),
                'expiryDate'   => (string)($s['expiry_date'] ?? ''),
                'lot'          => (string)($s['lot'] ?? ''),
                'observations' => (string)($s['observations'] ?? '')
            ];
        }
    }

    // 3. Lotes não alocados (Estoque Geral)
    $stmtLots = $pdo->query("SELECT * FROM `unallocated_lots` WHERE `qty` > 0");
    $rawLots = $stmtLots->fetchAll();
    $unallocatedLots = array_map(function($l) {
        return [
            'id'           => (string)$l['id'],
            'productId'    => (string)$l['product_id'],
            'qty'          => (int)$l['qty'],
            'entryDate'    => (string)($l['entry_date'] ?? ''),
            'expiryDate'   => (string)($l['expiry_date'] ?? ''),
            'observations' => (string)($l['observations'] ?? '')
        ];
    }, $rawLots);

    // 4. Slots Customizados
    $stmtCustom = $pdo->query("SELECT * FROM `custom_slots`");
    $rawCustom = $stmtCustom->fetchAll();
    $customSlots = array_map(function($c) {
        return [
            'code'  => (string)$c['code'],
            'label' => (string)$c['label']
        ];
    }, $rawCustom);

    // 5. Histórico (limite dos 250 mais recentes)
    $stmtHist = $pdo->query("SELECT * FROM `history` ORDER BY `created_at` DESC LIMIT 250");
    $rawHist = $stmtHist->fetchAll();
    $history = array_map(function($h) {
        return [
            'id'          => (string)$h['id'],
            'timestamp'   => (string)$h['timestamp'],
            'type'        => (string)$h['type'],
            'productName' => (string)$h['product_name'],
            'slotCode'    => (string)$h['slot_code'],
            'qtyText'     => (string)$h['qty_text'],
            'note'        => (string)($h['note'] ?? '-')
        ];
    }, $rawHist);

    echo json_encode([
        'success' => true,
        'data'    => [
            'products'        => $products,
            'slots'           => (object)$slots,
            'unallocatedLots' => $unallocatedLots,
            'customSlots'     => $customSlots,
            'history'         => $history,
            'serverTime'      => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handleSaveState(PDO $pdo) {
    $rawInput = file_get_contents('php://input');
    $payload = json_decode($rawInput, true);

    if (!$payload || !is_array($payload)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Corpo JSON inválido ou vazio']);
        return;
    }

    $dirtyKeys = $payload['dirtyKeys'] ?? ['products', 'slots', 'lots', 'history'];
    if (!is_array($dirtyKeys) || empty($dirtyKeys)) {
        $dirtyKeys = ['products', 'slots', 'lots', 'history'];
    }

    $pdo->beginTransaction();

    try {
        // 1. Salvar Produtos
        if (in_array('products', $dirtyKeys) && isset($payload['products']) && is_array($payload['products'])) {
            $existingIds = [];
            $stmtUpsert = $pdo->prepare("
                INSERT INTO `products` (`id`, `sku`, `name`, `category`, `unit`, `min_stock`, `max_stock`, `default_expiry`, `storage`, `flag_perecivel`, `flag_epi`, `flag_controlado`, `photo`, `observations`)
                VALUES (:id, :sku, :name, :category, :unit, :min_stock, :max_stock, :default_expiry, :storage, :flag_perecivel, :flag_epi, :flag_controlado, :photo, :observations)
                ON DUPLICATE KEY UPDATE
                    `sku` = VALUES(`sku`),
                    `name` = VALUES(`name`),
                    `category` = VALUES(`category`),
                    `unit` = VALUES(`unit`),
                    `min_stock` = VALUES(`min_stock`),
                    `max_stock` = VALUES(`max_stock`),
                    `default_expiry` = VALUES(`default_expiry`),
                    `storage` = VALUES(`storage`),
                    `flag_perecivel` = VALUES(`flag_perecivel`),
                    `flag_epi` = VALUES(`flag_epi`),
                    `flag_controlado` = VALUES(`flag_controlado`),
                    `photo` = VALUES(`photo`),
                    `observations` = VALUES(`observations`)
            ");

            foreach ($payload['products'] as $p) {
                if (empty($p['id'])) continue;
                $existingIds[] = $p['id'];
                $stmtUpsert->execute([
                    ':id'              => (string)$p['id'],
                    ':sku'             => (string)($p['sku'] ?? 'PRD-000'),
                    ':name'            => (string)($p['name'] ?? 'Sem Nome'),
                    ':category'        => (string)($p['category'] ?? 'Geral'),
                    ':unit'            => (string)($p['unit'] ?? 'un'),
                    ':min_stock'       => (int)($p['minStock'] ?? 0),
                    ':max_stock'       => isset($p['maxStock']) && $p['maxStock'] !== '' ? (int)$p['maxStock'] : null,
                    ':default_expiry'  => (string)($p['defaultExpiry'] ?? ''),
                    ':storage'         => (string)($p['storage'] ?? ''),
                    ':flag_perecivel'  => !empty($p['flagPerecivel']) ? 1 : 0,
                    ':flag_epi'        => !empty($p['flagEpi']) ? 1 : 0,
                    ':flag_controlado' => !empty($p['flagControlado']) ? 1 : 0,
                    ':photo'           => (string)($p['photo'] ?? ''),
                    ':observations'    => (string)($p['observations'] ?? '')
                ]);
            }

            // Remove produtos que foram excluídos da lista
            if (!empty($existingIds)) {
                $inClause = implode(',', array_fill(0, count($existingIds), '?'));
                $stmtDel = $pdo->prepare("DELETE FROM `products` WHERE `id` NOT IN ($inClause)");
                $stmtDel->execute($existingIds);
            } else {
                $pdo->exec("DELETE FROM `products`");
            }
        }

        // 2. Salvar Slots (Posições do Mapa)
        if (in_array('slots', $dirtyKeys) && isset($payload['slots']) && is_array($payload['slots'])) {
            $pdo->exec("DELETE FROM `slots`");
            $stmtSlot = $pdo->prepare("
                INSERT INTO `slots` (`slot_code`, `product_id`, `qty`, `entry_date`, `expiry_date`, `lot`, `observations`)
                VALUES (:slot_code, :product_id, :qty, :entry_date, :expiry_date, :lot, :observations)
            ");

            foreach ($payload['slots'] as $code => $slot) {
                if (!$slot || empty($slot['productId']) || (int)($slot['qty'] ?? 0) <= 0) continue;
                $stmtSlot->execute([
                    ':slot_code'    => (string)$code,
                    ':product_id'   => (string)$slot['productId'],
                    ':qty'          => (int)$slot['qty'],
                    ':entry_date'   => (string)($slot['entryDate'] ?? ''),
                    ':expiry_date'  => (string)($slot['expiryDate'] ?? ''),
                    ':lot'          => (string)($slot['lot'] ?? ''),
                    ':observations' => (string)($slot['observations'] ?? '')
                ]);
            }

            // Custom Slots
            if (isset($payload['customSlots']) && is_array($payload['customSlots'])) {
                $pdo->exec("DELETE FROM `custom_slots`");
                $stmtCustom = $pdo->prepare("INSERT INTO `custom_slots` (`code`, `label`) VALUES (:code, :label)");
                foreach ($payload['customSlots'] as $cs) {
                    if (empty($cs['code'])) continue;
                    $stmtCustom->execute([
                        ':code'  => (string)$cs['code'],
                        ':label' => (string)($cs['label'] ?? $cs['code'])
                    ]);
                }
            }
        }

        // 3. Salvar Lotes Não Alocados
        if (in_array('lots', $dirtyKeys) && isset($payload['unallocatedLots']) && is_array($payload['unallocatedLots'])) {
            $pdo->exec("DELETE FROM `unallocated_lots`");
            $stmtLot = $pdo->prepare("
                INSERT INTO `unallocated_lots` (`id`, `product_id`, `qty`, `entry_date`, `expiry_date`, `observations`)
                VALUES (:id, :product_id, :qty, :entry_date, :expiry_date, :observations)
            ");

            foreach ($payload['unallocatedLots'] as $lot) {
                if (!$lot || empty($lot['productId']) || (int)($lot['qty'] ?? 0) <= 0) continue;
                $stmtLot->execute([
                    ':id'           => (string)($lot['id'] ?? ('lot-' . uniqid())),
                    ':product_id'   => (string)$lot['productId'],
                    ':qty'          => (int)$lot['qty'],
                    ':entry_date'   => (string)($lot['entryDate'] ?? ''),
                    ':expiry_date'  => (string)($lot['expiryDate'] ?? ''),
                    ':observations' => (string)($lot['observations'] ?? '')
                ]);
            }
        }

        // 4. Salvar Histórico
        if (in_array('history', $dirtyKeys) && isset($payload['history']) && is_array($payload['history'])) {
            $stmtHist = $pdo->prepare("
                INSERT INTO `history` (`id`, `timestamp`, `type`, `product_name`, `slot_code`, `qty_text`, `note`)
                VALUES (:id, :timestamp, :type, :product_name, :slot_code, :qty_text, :note)
                ON DUPLICATE KEY UPDATE `id` = `id`
            ");

            // Insere os 250 mais recentes
            $sliceHist = array_slice($payload['history'], 0, 250);
            foreach ($sliceHist as $h) {
                if (empty($h['id'])) continue;
                $stmtHist->execute([
                    ':id'           => (string)$h['id'],
                    ':timestamp'    => (string)($h['timestamp'] ?? date('d/m/Y H:i:s')),
                    ':type'         => (string)($h['type'] ?? 'GERAL'),
                    ':product_name' => (string)($h['productName'] ?? '-'),
                    ':slot_code'    => (string)($h['slotCode'] ?? '-'),
                    ':qty_text'     => (string)($h['qtyText'] ?? '-'),
                    ':note'         => (string)($h['note'] ?? '-')
                ]);
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Dados persistidos com sucesso no MySQL']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function handleResetAll(PDO $pdo) {
    $pdo->beginTransaction();
    try {
        $pdo->exec("DELETE FROM `slots`");
        $pdo->exec("DELETE FROM `unallocated_lots`");
        $pdo->exec("DELETE FROM `custom_slots`");
        $pdo->exec("DELETE FROM `history`");
        $pdo->exec("DELETE FROM `products`");
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Banco de dados MySQL reiniciado com sucesso.']);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}
