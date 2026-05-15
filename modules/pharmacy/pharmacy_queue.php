<?php
/**
 * modules/pharmacy/pharmacy_queue.php
 * MediCare HMS — Manual Pharmacy Queue / Walk-in Dispensing
 * Group 05 | ICT1242 Web Development Practicum
 *
 * Features:
 *   - Live drug search (AJAX) with details: name, stock qty, unit price
 *   - Add multiple drugs to a cart with custom purchase quantities
 *   - Bill preview with line totals and grand total
 *   - Confirm sale → inserts into pharmacy_sales + pharmacy_sale_items,
 *     deducts stock from pharmacy_drugs (all in a transaction)
 *   - Printable receipt
 *   - Today's total income widget (live via AJAX)
 *
 * DB tables used:
 *   pharmacy_drugs       (drug_id, drug_name, category, unit, unit_price, stock_qty, is_active)
 *   pharmacy_sales       (id, sale_ref, cashier, sale_date, total_amount, created_at)
 *   pharmacy_sale_items  (id, sale_id, drug_id, drug_name, unit_price, quantity, line_total)
 */
declare(strict_types=1);

require_once __DIR__ . '/../../includes/db.php';

$pageTitle  = 'Pharmacy Queue';
// $pageCss is intentionally left unset — all styles are inlined below.
// If your header.php outputs a <link> only when $pageCss is set, this is safe.
// If it always outputs one, set this to a path that actually exists on your server:
// $pageCss = '/Web/Hospital-Management-System/modules/pharmacist/phamacist.css';
$useSidebar = true;
$isPublic   = false;

// ══════════════════════════════════════════════════════════════════════════════
//  AJAX ACTIONS
// ══════════════════════════════════════════════════════════════════════════════
$action = trim($_GET['action'] ?? $_POST['action'] ?? '');

if ($action !== '') {
    header('Content-Type: application/json');

    // ── 1. search_drugs ──────────────────────────────────────────────────────
    if ($action === 'search_drugs') {
        $q = trim($_GET['q'] ?? '');
        try {
            $like   = '%' . $q . '%';
            $stmt   = db()->prepare("
                SELECT drug_id, drug_name, category, unit, unit_price, stock_qty
                FROM   pharmacy_drugs
                WHERE  is_active = 1
                  AND  stock_qty > 0
                  AND  (drug_name LIKE ? OR drug_id LIKE ? OR category LIKE ?)
                ORDER  BY drug_name ASC
                LIMIT  30
            ");
            $stmt->execute([$like, $like, $like]);
            $drugs = $stmt->fetchAll();
            echo json_encode(['ok' => true, 'drugs' => $drugs]);
        } catch (Throwable $e) {
            error_log('[PQ] search_drugs: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Search failed.']);
        }
        exit;
    }

    // ── 2. confirm_sale ──────────────────────────────────────────────────────
    if ($action === 'confirm_sale' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $raw   = json_decode(file_get_contents('php://input'), true);
        $items = $raw['items'] ?? [];

        if (empty($items)) {
            echo json_encode(['ok' => false, 'error' => 'No items in cart.']);
            exit;
        }

        $cashier = $_SESSION['full_name'] ?? 'Pharmacy Counter';
        $saleRef = 'SALE-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

        try {
            $pdo = db();

            // Auto-create tables if they don't exist yet
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pharmacy_sales (
                    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sale_ref      VARCHAR(40)    NOT NULL UNIQUE,
                    cashier       VARCHAR(120)   NOT NULL DEFAULT 'Pharmacy Counter',
                    sale_date     DATE           NOT NULL,
                    total_amount  DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
                    created_at    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_sale_date (sale_date)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS pharmacy_sale_items (
                    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    sale_id     INT UNSIGNED   NOT NULL,
                    drug_id     VARCHAR(20)    NOT NULL,
                    drug_name   VARCHAR(200)   NOT NULL,
                    unit_price  DECIMAL(10,2)  NOT NULL,
                    quantity    INT UNSIGNED   NOT NULL,
                    line_total  DECIMAL(12,2)  NOT NULL,
                    CONSTRAINT fk_psi_sale FOREIGN KEY (sale_id)
                        REFERENCES pharmacy_sales(id) ON DELETE CASCADE,
                    INDEX idx_psi_sale (sale_id),
                    INDEX idx_psi_drug (drug_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");

            $pdo->beginTransaction();

            // Validate stock & compute total
            $total      = 0.0;
            $validated  = [];
            foreach ($items as $item) {
                $drugId = (string)($item['drug_id'] ?? '');
                $qty    = (int)($item['quantity']   ?? 0);
                if ($drugId === '' || $qty < 1) {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => 'Invalid item data.']);
                    exit;
                }
                $ds = $pdo->prepare("SELECT drug_name, unit_price, stock_qty FROM pharmacy_drugs WHERE drug_id = ? AND is_active = 1 FOR UPDATE");
                $ds->execute([$drugId]);
                $drug = $ds->fetch();
                if (!$drug) {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => "Drug '{$drugId}' not found or inactive."]);
                    exit;
                }
                if ($drug['stock_qty'] < $qty) {
                    $pdo->rollBack();
                    echo json_encode(['ok' => false, 'error' => "Insufficient stock for {$drug['drug_name']}. Available: {$drug['stock_qty']}."]);
                    exit;
                }
                $lineTotal   = $qty * (float)$drug['unit_price'];
                $total      += $lineTotal;
                $validated[] = [
                    'drug_id'    => $drugId,
                    'drug_name'  => $drug['drug_name'],
                    'unit_price' => (float)$drug['unit_price'],
                    'quantity'   => $qty,
                    'line_total' => $lineTotal,
                ];
            }

            // Insert sale header
            $ins = $pdo->prepare("
                INSERT INTO pharmacy_sales (sale_ref, cashier, sale_date, total_amount, created_at)
                VALUES (?, ?, CURDATE(), ?, NOW())
            ");
            $ins->execute([$saleRef, $cashier, $total]);
            $saleId = (int)$pdo->lastInsertId();

            // Insert line items & deduct stock
            $li  = $pdo->prepare("
                INSERT INTO pharmacy_sale_items (sale_id, drug_id, drug_name, unit_price, quantity, line_total)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $upd = $pdo->prepare("
                UPDATE pharmacy_drugs SET stock_qty = stock_qty - ? WHERE drug_id = ?
            ");
            foreach ($validated as $v) {
                $li->execute([$saleId, $v['drug_id'], $v['drug_name'], $v['unit_price'], $v['quantity'], $v['line_total']]);
                $upd->execute([$v['quantity'], $v['drug_id']]);
            }

            $pdo->commit();

            echo json_encode([
                'ok'       => true,
                'sale_ref' => $saleRef,
                'total'    => $total,
                'items'    => $validated,
                'cashier'  => $cashier,
                'date'     => date('d M Y · H:i'),
            ]);
        } catch (Throwable $e) {
            try { db()->rollBack(); } catch (Throwable $_) {}
            error_log('[PQ] confirm_sale: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'error' => 'Sale failed: ' . $e->getMessage()]);
        }
        exit;
    }

    // ── 3. daily_income ─────────────────────────────────────────────────────
    if ($action === 'daily_income') {
        try {
            $row = db()->query("
                SELECT
                    COALESCE(SUM(total_amount), 0)  AS income,
                    COUNT(*)                         AS sales_count
                FROM pharmacy_sales
                WHERE sale_date = CURDATE()
            ")->fetch();
            echo json_encode(['ok' => true, 'income' => $row['income'], 'count' => $row['sales_count']]);
        } catch (Throwable $e) {
            error_log('[PQ] daily_income: ' . $e->getMessage());
            echo json_encode(['ok' => false, 'income' => 0, 'count' => 0]);
        }
        exit;
    }

    echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
    exit;
}

// ══════════════════════════════════════════════════════════════════════════════
//  RENDER PAGE
// ══════════════════════════════════════════════════════════════════════════════
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.10.0/dist/tabler-icons.min.css">

<style>
/* ── CSS Variables (matches existing phamacist.css palette) ── */
:root {
    --color-primary:#0C447C;        --color-primary-light:#E6F1FB;
    --color-primary-mid:#85B7EB;    --color-primary-hover:#B5D4F4;
    --color-primary-dark:#185FA5;
    --color-success-bg:#EAF3DE;     --color-success-text:#3B6D11;
    --color-success-dark:#27500A;   --color-success-border:#97C459;
    --color-success-fill:#C0DD97;
    --color-warn-bg:#FFF8E6;        --color-warn-text:#92600A;
    --color-warn-border:#F5C842;
    --color-danger-bg:#FCEBEB;      --color-danger-text:#A32D2D;
    --color-danger-border:#F09595;
    --color-bg:#FFFFFF;             --color-bg-sec:#F5F7FA;
    --color-bg-page:#EEF1F6;        --color-border:#DDE3EC;
    --color-text:#0F1924;           --color-muted:#5A6677;
    --color-info:#1A6FC4;
    --radius-sm:6px; --radius-md:10px; --radius-lg:14px;
    --font:'DM Sans',system-ui,sans-serif;
    --font-display:'Playfair Display',Georgia,serif;
}
*, *::before, *::after { box-sizing:border-box; margin:0; padding:0; }
body { font-family:var(--font); background:var(--color-bg-page); color:var(--color-text); }

/* ── Layout ── */
.main-content   { flex:1; overflow-y:auto; min-width:0; }
.pq-wrap        { max-width:1160px; margin:0 auto; padding:26px 22px 70px; }

/* ── Breadcrumb ── */
.breadcrumb     { display:flex; align-items:center; gap:6px; font-size:12px; color:var(--color-muted); margin-bottom:18px; }
.breadcrumb a   { color:var(--color-primary); text-decoration:none; }
.breadcrumb a:hover { text-decoration:underline; }
.breadcrumb .sep{ opacity:.4; }

/* ── Tab strip (same as drugs.php) ── */
.ph-tab-strip { display:flex; gap:4px; margin-bottom:20px; border-bottom:1.5px solid var(--color-border); padding-bottom:0; }
.ph-tab {
    display:inline-flex; align-items:center; gap:6px;
    font-size:13px; font-weight:500; text-decoration:none;
    color:var(--color-muted); padding:8px 16px 10px;
    border-radius:var(--radius-sm) var(--radius-sm) 0 0;
    border:0.5px solid transparent; border-bottom:2px solid transparent;
    margin-bottom:-1.5px; transition:color .15s, background .15s;
}
.ph-tab:hover { color:var(--color-primary); background:var(--color-primary-light); }
.ph-tab.active {
    color:var(--color-primary); background:var(--color-bg);
    border-color:var(--color-border); border-bottom-color:var(--color-bg); font-weight:600;
}

/* ── Page title ── */
.page-title-row { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; margin-bottom:20px; flex-wrap:wrap; }
.page-title h1  { font-size:20px; font-weight:600; line-height:1.3; }
.page-title p   { font-size:13px; color:var(--color-muted); margin-top:3px; }

/* ── Daily income banner ── */
.income-banner {
    display:flex; align-items:center; gap:16px; flex-wrap:wrap;
    background:linear-gradient(135deg, var(--color-primary) 0%, var(--color-primary-dark) 100%);
    border-radius:var(--radius-lg); padding:16px 22px; margin-bottom:22px;
    color:#fff; box-shadow:0 4px 18px rgba(12,68,124,.22);
}
.income-icon {
    width:48px; height:48px; border-radius:var(--radius-md);
    background:rgba(255,255,255,.15);
    display:flex; align-items:center; justify-content:center; font-size:22px; flex-shrink:0;
}
.income-label  { font-size:11px; opacity:.7; text-transform:uppercase; letter-spacing:.5px; }
.income-amount { font-size:26px; font-weight:700; line-height:1.1; }
.income-sub    { font-size:12px; opacity:.7; margin-top:2px; }
.income-refresh {
    margin-left:auto; padding:7px 14px; font-size:12px; font-weight:500;
    background:rgba(255,255,255,.18); color:#fff;
    border:1px solid rgba(255,255,255,.3); border-radius:var(--radius-sm);
    cursor:pointer; display:flex; align-items:center; gap:5px; transition:background .15s;
}
.income-refresh:hover { background:rgba(255,255,255,.28); }

/* ── Two-col grid ── */
.pq-grid { display:grid; grid-template-columns:1fr 420px; gap:18px; align-items:start; }
@media(max-width:900px){ .pq-grid { grid-template-columns:1fr; } }

/* ── Cards ── */
.pq-card {
    background:var(--color-bg); border:0.5px solid var(--color-border);
    border-radius:var(--radius-lg); overflow:hidden;
}
.pq-card-head {
    display:flex; align-items:center; gap:10px; padding:14px 18px;
    border-bottom:0.5px solid var(--color-border);
    background:var(--color-bg-sec);
}
.pq-card-head .icon {
    width:32px; height:32px; border-radius:var(--radius-sm);
    background:var(--color-primary-light); color:var(--color-primary);
    display:flex; align-items:center; justify-content:center; font-size:16px;
}
.pq-card-head h2 { font-size:14px; font-weight:600; }
.pq-card-head span { font-size:11px; color:var(--color-muted); }
.pq-card-body { padding:16px 18px; }

/* ── Search box ── */
.drug-search-wrap { position:relative; margin-bottom:12px; }
.drug-search-wrap i {
    position:absolute; left:11px; top:50%; transform:translateY(-50%);
    color:var(--color-muted); font-size:15px; pointer-events:none;
}
.drug-search-wrap input {
    width:100%; height:40px; padding:0 12px 0 36px;
    border:0.5px solid var(--color-border); border-radius:var(--radius-md);
    font-family:var(--font); font-size:13px; color:var(--color-text);
    background:var(--color-bg-sec); outline:none; transition:border-color .15s, box-shadow .15s;
}
.drug-search-wrap input:focus {
    border-color:var(--color-primary-mid); background:#fff;
    box-shadow:0 0 0 3px rgba(12,68,124,.08);
}

/* ── Drug result list ── */
.drug-results { max-height:340px; overflow-y:auto; border:0.5px solid var(--color-border); border-radius:var(--radius-md); }
.drug-results:empty { display:none; }
.dr-item {
    display:grid; grid-template-columns:1fr auto auto;
    align-items:center; gap:12px;
    padding:10px 14px; border-bottom:0.5px solid var(--color-border);
    cursor:pointer; transition:background .12s;
}
.dr-item:last-child { border-bottom:none; }
.dr-item:hover { background:var(--color-primary-light); }
.dr-name    { font-size:13px; font-weight:600; }
.dr-cat     { font-size:11px; color:var(--color-muted); margin-top:1px; }
.dr-price   { font-size:13px; font-weight:600; color:var(--color-primary); white-space:nowrap; }
.dr-stock   { font-size:11px; color:var(--color-muted); text-align:right; }
.dr-add-btn {
    width:30px; height:30px; border-radius:var(--radius-sm);
    background:var(--color-primary); color:#fff; border:none;
    display:flex; align-items:center; justify-content:center;
    font-size:16px; cursor:pointer; flex-shrink:0; transition:background .12s;
}
.dr-add-btn:hover { background:var(--color-primary-dark); }

/* state rows */
.dr-state {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:28px 16px; color:var(--color-muted); font-size:13px; gap:8px; text-align:center;
}
.dr-state i { font-size:26px; opacity:.4; }

/* ── Qty modal overlay ── */
.qty-overlay {
    display:none; position:fixed; inset:0; z-index:999;
    background:rgba(15,25,36,.45); align-items:center; justify-content:center;
}
.qty-overlay.show { display:flex; }
.qty-modal {
    background:#fff; border-radius:var(--radius-lg); padding:24px 28px;
    min-width:320px; box-shadow:0 12px 40px rgba(0,0,0,.18);
}
.qty-modal h3 { font-size:15px; font-weight:600; margin-bottom:4px; }
.qty-modal p  { font-size:12px; color:var(--color-muted); margin-bottom:16px; }
.qty-row      { display:flex; gap:10px; align-items:center; margin-bottom:16px; }
.qty-input {
    width:100px; height:38px; border:0.5px solid var(--color-border);
    border-radius:var(--radius-sm); padding:0 10px; font-size:14px;
    font-weight:600; text-align:center; outline:none;
    transition:border-color .15s, box-shadow .15s;
}
.qty-input:focus { border-color:var(--color-primary-mid); box-shadow:0 0 0 3px rgba(12,68,124,.08); }
.qty-avail    { font-size:12px; color:var(--color-muted); }
.qty-actions  { display:flex; gap:8px; justify-content:flex-end; }
.btn          { display:inline-flex; align-items:center; gap:5px; padding:8px 16px; border-radius:var(--radius-sm); font-family:var(--font); font-size:13px; font-weight:500; cursor:pointer; border:none; transition:background .15s, box-shadow .12s; }
.btn-primary  { background:var(--color-primary); color:#fff; }
.btn-primary:hover  { background:var(--color-primary-dark); }
.btn-ghost    { background:var(--color-bg-sec); color:var(--color-text); border:0.5px solid var(--color-border); }
.btn-ghost:hover    { background:var(--color-border); }
.btn-danger   { background:var(--color-danger-bg); color:var(--color-danger-text); border:0.5px solid var(--color-danger-border); }
.btn-danger:hover   { background:#f7d7d7; }
.btn-success  { background:#3B6D11; color:#fff; }
.btn-success:hover  { background:var(--color-success-dark); }
.btn:disabled { opacity:.5; cursor:not-allowed; }

/* ── Bill / cart card ── */
.cart-empty {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    padding:40px 20px; color:var(--color-muted); font-size:13px; gap:10px; text-align:center;
}
.cart-empty i { font-size:34px; opacity:.3; }

/* cart table */
.cart-tbl { width:100%; border-collapse:collapse; font-size:13px; }
.cart-tbl th {
    text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.4px;
    color:var(--color-muted); padding:8px 10px; background:var(--color-bg-sec);
    border-bottom:0.5px solid var(--color-border);
}
.cart-tbl td { padding:9px 10px; border-bottom:0.5px solid var(--color-border); }
.cart-tbl tr:last-child td { border-bottom:none; }
.cart-tbl tr:nth-child(even) td { background:var(--color-bg-sec); }
.drug-nm { font-weight:600; }
.drug-ct { font-size:11px; color:var(--color-muted); }
.qty-ctrl { display:flex; align-items:center; gap:4px; }
.qty-btn  {
    width:24px; height:24px; border-radius:4px; border:0.5px solid var(--color-border);
    background:var(--color-bg-sec); color:var(--color-text); font-size:14px;
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    transition:background .12s;
}
.qty-btn:hover { background:var(--color-primary-light); color:var(--color-primary); }
.qty-num  { font-weight:600; font-size:13px; min-width:26px; text-align:center; }
.rm-btn   {
    width:26px; height:26px; border-radius:4px; border:none;
    background:var(--color-danger-bg); color:var(--color-danger-text);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    font-size:14px; transition:background .12s;
}
.rm-btn:hover { background:#f7d7d7; }
.line-total { font-weight:600; color:var(--color-primary); }

/* totals */
.cart-totals { padding:14px 18px; border-top:0.5px solid var(--color-border); background:var(--color-bg-sec); }
.total-row   { display:flex; justify-content:space-between; align-items:center; font-size:13px; padding:3px 0; }
.total-row.grand { font-size:16px; font-weight:700; color:var(--color-primary); margin-top:8px; padding-top:10px; border-top:1.5px solid var(--color-border); }

/* confirm bar */
.confirm-bar {
    display:flex; gap:10px; padding:14px 18px;
    border-top:0.5px solid var(--color-border);
}
.confirm-bar .btn { flex:1; justify-content:center; }

/* ── Receipt overlay ── */
.receipt-overlay {
    display:none; position:fixed; inset:0; z-index:1000;
    background:rgba(15,25,36,.55); align-items:flex-start; justify-content:center;
    overflow-y:auto; padding:30px 16px;
}
.receipt-overlay.show { display:flex; }

/* receipt card */
.receipt-card {
    background:#fff; border-radius:var(--radius-lg);
    width:100%; max-width:560px;
    box-shadow:0 16px 50px rgba(0,0,0,.22); overflow:hidden;
}
.rx-head {
    background:linear-gradient(135deg, #0C447C 0%, #185FA5 100%);
    padding:20px 24px; color:#fff;
    display:flex; align-items:center; justify-content:space-between;
}
.rx-hosp-name { font-size:17px; font-weight:700; font-family:var(--font-display); letter-spacing:.3px; }
.rx-hosp-sub  { font-size:11px; opacity:.65; margin-top:2px; }
.rx-ref-block { text-align:right; }
.rx-ref-no    { font-size:14px; font-weight:700; }
.rx-ref-date  { font-size:11px; opacity:.65; margin-top:2px; }

.rx-section   { padding:16px 24px; border-bottom:0.5px solid var(--color-border); }
.rx-section:last-of-type { border-bottom:none; }
.rx-sec-label { font-size:10px; text-transform:uppercase; letter-spacing:.5px; color:var(--color-muted); margin-bottom:8px; }

.rx-cashier-row { display:flex; gap:6px; align-items:center; font-size:13px; }
.rx-cashier-row i { color:var(--color-primary); }

/* item table inside receipt */
.rx-tbl { width:100%; border-collapse:collapse; font-size:12px; }
.rx-tbl th {
    text-align:left; font-size:10px; text-transform:uppercase; letter-spacing:.4px;
    color:var(--color-muted); padding:6px 8px; background:var(--color-bg-sec);
    border-bottom:0.5px solid var(--color-border);
}
.rx-tbl td { padding:8px; border-bottom:0.5px solid var(--color-border); }
.rx-tbl tr:last-child td { border-bottom:none; }
.rx-tbl .amt { text-align:right; font-weight:600; }

/* receipt totals */
.rx-totals { padding:14px 24px; background:var(--color-bg-sec); border-top:0.5px solid var(--color-border); }
.rx-total-row { display:flex; justify-content:space-between; font-size:13px; padding:3px 0; }
.rx-total-row.grand {
    font-size:16px; font-weight:700; color:var(--color-primary);
    margin-top:8px; padding-top:10px; border-top:1.5px solid var(--color-border);
}

.rx-footer {
    padding:14px 24px; text-align:center; font-size:11px; color:var(--color-muted);
    background:var(--color-bg-sec); border-top:0.5px solid var(--color-border);
}

.receipt-actions {
    display:flex; gap:10px; padding:16px 24px;
    border-top:0.5px solid var(--color-border); background:#fff;
}
.receipt-actions .btn { flex:1; justify-content:center; }

/* ── Toast ── */
.toast {
    position:fixed; bottom:24px; right:24px; z-index:2000;
    min-width:280px; max-width:400px; padding:12px 16px;
    border-radius:var(--radius-md); box-shadow:0 8px 24px rgba(0,0,0,.18);
    display:flex; align-items:center; gap:10px; font-size:13px;
    transform:translateY(80px); opacity:0; transition:transform .3s, opacity .3s;
    pointer-events:none;
}
.toast.show { transform:translateY(0); opacity:1; }
.toast.success { background:var(--color-success-bg); color:var(--color-success-dark); border:0.5px solid var(--color-success-border); }
.toast.error   { background:var(--color-danger-bg);  color:var(--color-danger-text);  border:0.5px solid var(--color-danger-border); }
.toast i { font-size:18px; }

/* ── Spinner ── */
@keyframes spin { to { transform:rotate(360deg); } }
.spin { display:inline-block; animation:spin 1s linear infinite; }

/* ── Print ── */
@media print {
    /* Hide everything except the receipt card */
    body > *:not(#receiptOverlay),
    .no-print, .topbar, .sidebar, .site-footer,
    main, .pq-wrap, .toast { display:none !important; }

    /* Flatten the overlay so the card fills the page */
    .receipt-overlay {
        position:static !important;
        background:none !important;
        display:block !important;
        padding:0 !important;
        overflow:visible !important;
    }
    .receipt-card {
        box-shadow:none !important;
        border-radius:0 !important;
        width:100% !important;
        max-width:100% !important;
    }
    /* Force header gradient to print */
    .rx-head {
        background:#0C447C !important;
        -webkit-print-color-adjust:exact !important;
        print-color-adjust:exact !important;
        color:#fff !important;
    }
    /* Hide modal action buttons on print */
    .receipt-actions { display:none !important; }
    @page { size:A5; margin:1cm; }
}
</style>

<!-- ══════════════════════════════════════════════════════════════════════════
     PAGE HTML
═══════════════════════════════════════════════════════════════════════════ -->
<main class="main-content">
<div class="pq-wrap">

    <!-- Breadcrumb -->
    <nav class="breadcrumb no-print">
        <a href="#">Dashboard</a>
        <span class="sep">›</span>
        <a href="#">Pharmacy</a>
        <span class="sep">›</span>
        <span>Pharmacy Queue</span>
    </nav>

    <!-- Tab strip (mirrors drug_catalog.php) -->
    <div class="ph-tab-strip no-print">
        <a href="prescriptions.php"  class="ph-tab"><i class="ti ti-prescription"></i> Dispensing</a>
        <a href="drugs.php"          class="ph-tab"><i class="ti ti-pill"></i> Drug Catalog</a>
        <a href="pharmacy_queue.php" class="ph-tab active"><i class="ti ti-shopping-cart"></i> Pharmacy Queue</a>
    </div>

    <!-- Page title -->
    <div class="page-title-row no-print">
        <div class="page-title">
            <h1>Pharmacy Queue</h1>
            <p>Manually dispense medicines — search, add to bill, confirm sale &amp; print receipt</p>
        </div>
    </div>

    <!-- Daily income banner -->
    <div class="income-banner no-print">
        <div class="income-icon"><i class="ti ti-cash"></i></div>
        <div>
            <div class="income-label">Today's Income</div>
            <div class="income-amount" id="incomeAmt">LKR —</div>
            <div class="income-sub" id="incomeSub">Loading…</div>
        </div>
        <button class="income-refresh" onclick="fetchDailyIncome()">
            <i class="ti ti-refresh"></i> Refresh
        </button>
    </div>

    <!-- Two-column grid -->
    <div class="pq-grid">

        <!-- LEFT: Drug search -->
        <div class="pq-card no-print">
            <div class="pq-card-head">
                <div class="icon"><i class="ti ti-search"></i></div>
                <div>
                    <h2>Search Medicine</h2>
                    <span>Type to find available drugs</span>
                </div>
            </div>
            <div class="pq-card-body">
                <div class="drug-search-wrap">
                    <i class="ti ti-pill"></i>
                    <input type="text" id="drugSearch"
                           placeholder="Search by drug name, ID or category…"
                           autocomplete="off"
                           oninput="onDrugSearch()">
                </div>
                <div class="drug-results" id="drugResults">
                    <div class="dr-state">
                        <i class="ti ti-search"></i>
                        <span>Type at least 2 characters to search for a drug.</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Bill / Cart -->
        <div class="pq-card" id="cartCard">
            <div class="pq-card-head">
                <div class="icon"><i class="ti ti-receipt"></i></div>
                <div>
                    <h2>Current Bill</h2>
                    <span id="cartCountLabel">0 items</span>
                </div>
                <button class="btn btn-ghost no-print" style="margin-left:auto;padding:5px 10px;font-size:12px;" onclick="clearCart()">
                    <i class="ti ti-trash"></i> Clear
                </button>
            </div>

            <!-- Cart body -->
            <div id="cartBody">
                <div class="cart-empty" id="cartEmpty">
                    <i class="ti ti-shopping-cart-off"></i>
                    <span>No medicines added yet.<br>Search and add drugs from the left.</span>
                </div>
                <div id="cartTableWrap" style="display:none; overflow-x:auto;">
                    <table class="cart-tbl">
                        <thead>
                            <tr>
                                <th>Drug</th>
                                <th style="width:100px">Qty</th>
                                <th style="width:80px">Unit Price</th>
                                <th style="width:90px">Total</th>
                                <th style="width:36px"></th>
                            </tr>
                        </thead>
                        <tbody id="cartRows"></tbody>
                    </table>
                </div>
            </div>

            <!-- Totals -->
            <div class="cart-totals" id="cartTotals" style="display:none;">
                <div class="total-row">
                    <span>Subtotal</span>
                    <span id="tSub">LKR 0.00</span>
                </div>
                <div class="total-row grand">
                    <span>Grand Total</span>
                    <span id="tGrand">LKR 0.00</span>
                </div>
            </div>

            <!-- Confirm buttons -->
            <div class="confirm-bar no-print" id="confirmBar" style="display:none;">
                <button class="btn btn-ghost" onclick="clearCart()"><i class="ti ti-x"></i> Clear</button>
                <button class="btn btn-success" onclick="confirmSale()">
                    <i class="ti ti-check"></i> Confirm &amp; Generate Bill
                </button>
            </div>
        </div>

    </div><!-- /.pq-grid -->
</div><!-- /.pq-wrap -->
</main>

<!-- ═══════════════════════════════════════════════════
     QTY MODAL
═══════════════════════════════════════════════════ -->
<div class="qty-overlay no-print" id="qtyOverlay" onclick="closeQtyModal(event)">
    <div class="qty-modal">
        <h3 id="qtyDrugName">Drug Name</h3>
        <p id="qtyDrugMeta">Unit Price · Stock available</p>
        <div class="qty-row">
            <input type="number" class="qty-input" id="qtyInput" min="1" value="1"
                   onkeydown="if(event.key==='Enter')addToCartConfirm()">
            <span class="qty-avail" id="qtyAvail"></span>
        </div>
        <div class="qty-actions">
            <button class="btn btn-ghost" onclick="closeQtyModal()">Cancel</button>
            <button class="btn btn-primary" onclick="addToCartConfirm()"><i class="ti ti-plus"></i> Add to Bill</button>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     RECEIPT OVERLAY
═══════════════════════════════════════════════════ -->
<div class="receipt-overlay" id="receiptOverlay">
    <div class="receipt-card" id="receiptCard">

        <!-- Header -->
        <div class="rx-head">
            <div>
                <div class="rx-hosp-name">MediCare Hospital</div>
                <div class="rx-hosp-sub">Pharmacy Department · Walk-in Dispensing</div>
            </div>
            <div class="rx-ref-block">
                <div class="rx-ref-no" id="rxRefNo">—</div>
                <div class="rx-ref-date" id="rxRefDate">—</div>
            </div>
        </div>

        <!-- Cashier info -->
        <div class="rx-section">
            <div class="rx-sec-label">Issued By</div>
            <div class="rx-cashier-row">
                <i class="ti ti-user"></i>
                <span id="rxCashier">—</span>
                <span style="margin-left:auto; color:var(--color-muted); font-size:12px;" id="rxDateTime">—</span>
            </div>
        </div>

        <!-- Items -->
        <div class="rx-section" style="padding-bottom:0;">
            <div class="rx-sec-label">Dispensed Medicines</div>
            <table class="rx-tbl">
                <thead>
                    <tr>
                        <th>Drug Name</th>
                        <th style="text-align:right">Qty</th>
                        <th style="text-align:right">Unit Price</th>
                        <th style="text-align:right">Total</th>
                    </tr>
                </thead>
                <tbody id="rxItems"></tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="rx-totals">
            <div class="rx-total-row">
                <span>Subtotal</span>
                <span id="rxSubtotal">—</span>
            </div>
            <div class="rx-total-row grand">
                <span>Grand Total</span>
                <span id="rxGrand">—</span>
            </div>
        </div>

        <!-- Footer note -->
        <div class="rx-footer">
            Thank you for choosing MediCare Hospital.<br>
            Keep this receipt for your records. For queries, contact the pharmacy counter.
        </div>

        <!-- Actions -->
        <div class="receipt-actions no-print">
            <button class="btn btn-ghost" onclick="closeReceipt()"><i class="ti ti-x"></i> Close</button>
            <button class="btn btn-primary" onclick="window.print()"><i class="ti ti-printer"></i> Print Receipt</button>
            <button class="btn btn-success" onclick="newSale()"><i class="ti ti-plus"></i> New Sale</button>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════
     TOAST
═══════════════════════════════════════════════════ -->
<div class="toast no-print" id="toast">
    <i class="ti" id="toastIcon"></i>
    <span id="toastMsg"></span>
</div>

<!-- ═══════════════════════════════════════════════════
     JAVASCRIPT
═══════════════════════════════════════════════════ -->
<script>
'use strict';

// ── State ────────────────────────────────────────────────────────────────────
let cart       = [];          // {drug_id, drug_name, category, unit, unit_price, stock_qty, quantity}
let _searchTmr = null;
let _pendingDrug = null;      // drug being qty-selected

// ── Helpers ──────────────────────────────────────────────────────────────────
const fmtLKR = n => 'LKR ' + parseFloat(n).toFixed(2);

function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                          .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function toast(msg, type = 'success') {
    const el  = document.getElementById('toast');
    const ico = document.getElementById('toastIcon');
    ico.className = 'ti ' + (type === 'success' ? 'ti-circle-check' : 'ti-alert-circle');
    document.getElementById('toastMsg').textContent = msg;
    el.className = 'toast no-print ' + type + ' show';
    setTimeout(() => { el.classList.remove('show'); }, 3500);
}

// ── Daily Income ─────────────────────────────────────────────────────────────
async function fetchDailyIncome() {
    try {
        const res  = await fetch('?action=daily_income', { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const data = await res.json();
        document.getElementById('incomeAmt').textContent = fmtLKR(data.income || 0);
        document.getElementById('incomeSub').textContent =
            (data.count || 0) + ' sale' + ((data.count||0) !== 1 ? 's' : '') + ' today';
    } catch(e) {
        document.getElementById('incomeSub').textContent = 'Could not load';
    }
}

// ── Drug Search ──────────────────────────────────────────────────────────────
function onDrugSearch() {
    clearTimeout(_searchTmr);
    const q = document.getElementById('drugSearch').value.trim();
    if (q.length < 2) {
        document.getElementById('drugResults').innerHTML =
            '<div class="dr-state"><i class="ti ti-search"></i><span>Type at least 2 characters.</span></div>';
        return;
    }
    document.getElementById('drugResults').innerHTML =
        '<div class="dr-state"><i class="ti ti-loader-2 spin"></i><span>Searching…</span></div>';
    _searchTmr = setTimeout(() => searchDrugs(q), 280);
}

async function searchDrugs(q) {
    try {
        const res  = await fetch('?action=search_drugs&q=' + encodeURIComponent(q),
                                 { headers:{'X-Requested-With':'XMLHttpRequest'} });
        const data = await res.json();
        if (!data.ok) { renderDrugResults([]); return; }
        renderDrugResults(data.drugs);
    } catch(e) {
        document.getElementById('drugResults').innerHTML =
            '<div class="dr-state"><i class="ti ti-alert-circle" style="color:#A32D2D"></i><span style="color:#A32D2D">Network error.</span></div>';
    }
}

// Drug lookup map — populated on each search render, keyed by index string.
// Avoids injecting JSON into onclick attributes (breaks on special chars / quotes).
const _drugMap = {};

function renderDrugResults(drugs) {
    const el = document.getElementById('drugResults');
    if (!drugs.length) {
        el.innerHTML = '<div class="dr-state"><i class="ti ti-mood-empty"></i><span>No in-stock drugs found.</span></div>';
        return;
    }

    // Clear and repopulate the map
    Object.keys(_drugMap).forEach(k => delete _drugMap[k]);
    drugs.forEach((d, i) => { _drugMap[i] = d; });

    el.innerHTML = drugs.map((d, i) => {
        const stock  = parseInt(d.stock_qty)    || 0;
        const price  = parseFloat(d.unit_price) || 0;
        const inCart = cart.find(c => c.drug_id === d.drug_id);
        return `<div class="dr-item" data-drug-idx="${i}">
            <div>
                <div class="dr-name">${esc(d.drug_name)}</div>
                <div class="dr-cat">${esc(d.category || '—')} · ${esc(d.unit || '')}</div>
            </div>
            <div>
                <div class="dr-price">${fmtLKR(price)}</div>
                <div class="dr-stock">Stock: ${stock}</div>
            </div>
            <button class="dr-add-btn" title="Add to bill">
                <i class="ti ti-${inCart ? 'check' : 'plus'}"></i>
            </button>
        </div>`;
    }).join('');

    // Attach click listeners after innerHTML is set — no inline handlers
    el.querySelectorAll('.dr-item').forEach(row => {
        row.addEventListener('click', () => {
            const idx = row.getAttribute('data-drug-idx');
            openQtyModal(_drugMap[idx]);
        });
    });
}

// ── Qty Modal ────────────────────────────────────────────────────────────────
function openQtyModal(drugObj) {
    _pendingDrug = drugObj;
    const inCart   = cart.find(c => c.drug_id === _pendingDrug.drug_id);
    const maxAvail = (parseInt(_pendingDrug.stock_qty) || 0) - (inCart ? inCart.quantity : 0);

    document.getElementById('qtyDrugName').textContent = _pendingDrug.drug_name;
    document.getElementById('qtyDrugMeta').textContent =
        fmtLKR(_pendingDrug.unit_price) + ' per ' + (_pendingDrug.unit || 'unit') +
        ' · ' + (parseInt(_pendingDrug.stock_qty) || 0) + ' in stock';
    document.getElementById('qtyAvail').textContent = 'Max: ' + maxAvail;

    const inp = document.getElementById('qtyInput');
    inp.value = 1;
    inp.max   = maxAvail;
    document.getElementById('qtyOverlay').classList.add('show');
    setTimeout(() => inp.select(), 80);
}

function closeQtyModal(e) {
    if (e && e.target !== document.getElementById('qtyOverlay')) return;
    document.getElementById('qtyOverlay').classList.remove('show');
    _pendingDrug = null;
}

function addToCartConfirm() {
    if (!_pendingDrug) return;
    const qty = parseInt(document.getElementById('qtyInput').value) || 0;
    if (qty < 1) { alert('Please enter a quantity of at least 1.'); return; }

    const maxStock = parseInt(_pendingDrug.stock_qty) || 0;
    const existing = cart.find(c => c.drug_id === _pendingDrug.drug_id);
    const already  = existing ? existing.quantity : 0;
    if (already + qty > maxStock) {
        alert('Not enough stock. Available: ' + (maxStock - already));
        return;
    }

    if (existing) {
        existing.quantity += qty;
    } else {
        cart.push({ ..._pendingDrug, quantity: qty });
    }

    const addedName = _pendingDrug.drug_name;   // capture before clearing
    document.getElementById('qtyOverlay').classList.remove('show');
    _pendingDrug = null;
    renderCart();
    toast(addedName + ' added to bill.', 'success');
    // re-render search results to show checkmark
    const q = document.getElementById('drugSearch').value.trim();
    if (q.length >= 2) searchDrugs(q);
}

// ── Cart ─────────────────────────────────────────────────────────────────────
function renderCart() {
    const isEmpty = cart.length === 0;

    document.getElementById('cartEmpty').style.display     = isEmpty ? 'flex' : 'none';
    document.getElementById('cartTableWrap').style.display = isEmpty ? 'none' : 'block';
    document.getElementById('cartTotals').style.display    = isEmpty ? 'none' : 'block';
    document.getElementById('confirmBar').style.display    = isEmpty ? 'none' : 'flex';

    document.getElementById('cartCountLabel').textContent =
        cart.length + ' item' + (cart.length !== 1 ? 's' : '');

    let subtotal = 0;

    document.getElementById('cartRows').innerHTML = cart.map((item, idx) => {
        const lineTotal = item.quantity * parseFloat(item.unit_price);
        subtotal += lineTotal;
        return `<tr>
            <td>
                <div class="drug-nm">${esc(item.drug_name)}</div>
                <div class="drug-ct">${esc(item.category || '—')}</div>
            </td>
            <td>
                <div class="qty-ctrl">
                    <button class="qty-btn" onclick="changeQty(${idx}, -1)">−</button>
                    <span class="qty-num">${item.quantity}</span>
                    <button class="qty-btn" onclick="changeQty(${idx}, 1)">+</button>
                </div>
            </td>
            <td>${fmtLKR(item.unit_price)}</td>
            <td class="line-total">${fmtLKR(lineTotal)}</td>
            <td><button class="rm-btn" onclick="removeItem(${idx})" title="Remove"><i class="ti ti-trash"></i></button></td>
        </tr>`;
    }).join('');

    document.getElementById('tSub').textContent   = fmtLKR(subtotal);
    document.getElementById('tGrand').textContent = fmtLKR(subtotal);
}

function changeQty(idx, delta) {
    const item    = cart[idx];
    const newQty  = item.quantity + delta;
    const maxStock = parseInt(item.stock_qty) || 0;
    if (newQty < 1) { removeItem(idx); return; }
    if (newQty > maxStock) { toast('Max stock is ' + maxStock, 'error'); return; }
    item.quantity = newQty;
    renderCart();
}

function removeItem(idx) {
    cart.splice(idx, 1);
    renderCart();
    const q = document.getElementById('drugSearch').value.trim();
    if (q.length >= 2) searchDrugs(q);
}

function clearCart() {
    cart = [];
    renderCart();
    const q = document.getElementById('drugSearch').value.trim();
    if (q.length >= 2) searchDrugs(q);
}

// ── Confirm Sale ─────────────────────────────────────────────────────────────
async function confirmSale() {
    if (cart.length === 0) { toast('Cart is empty.', 'error'); return; }

    const btn = document.querySelector('#confirmBar .btn-success');
    btn.disabled = true;
    btn.innerHTML = '<i class="ti ti-loader-2 spin"></i> Processing…';

    try {
        const payload = {
            items: cart.map(c => ({ drug_id: c.drug_id, quantity: c.quantity }))
        };
        const res  = await fetch('?action=confirm_sale', {
            method: 'POST',
            headers: { 'Content-Type':'application/json', 'X-Requested-With':'XMLHttpRequest' },
            body: JSON.stringify(payload)
        });
        const data = await res.json();

        if (!data.ok) {
            toast(data.error || 'Sale failed.', 'error');
            return;
        }

        // success → show receipt
        showReceipt(data);
        fetchDailyIncome();
        cart = [];
        renderCart();
        // Refresh search results so updated stock quantities are shown
        const q = document.getElementById('drugSearch').value.trim();
        if (q.length >= 2) searchDrugs(q);

    } catch(e) {
        toast('Network error. Please try again.', 'error');
        console.error(e);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="ti ti-check"></i> Confirm &amp; Generate Bill';
    }
}

// ── Receipt ──────────────────────────────────────────────────────────────────
function showReceipt(data) {
    document.getElementById('rxRefNo').textContent    = data.sale_ref;
    document.getElementById('rxRefDate').textContent  = data.date;
    document.getElementById('rxCashier').textContent  = data.cashier;
    document.getElementById('rxDateTime').textContent = data.date;

    const rows = data.items.map((item, i) => `
        <tr style="${i % 2 === 1 ? 'background:var(--color-bg-sec)' : ''}">
            <td>${esc(item.drug_name)}</td>
            <td style="text-align:right">${item.quantity}</td>
            <td style="text-align:right">${fmtLKR(item.unit_price)}</td>
            <td class="amt">${fmtLKR(item.line_total)}</td>
        </tr>`).join('');
    document.getElementById('rxItems').innerHTML = rows;

    document.getElementById('rxSubtotal').textContent = fmtLKR(data.total);
    document.getElementById('rxGrand').textContent    = fmtLKR(data.total);

    document.getElementById('receiptOverlay').classList.add('show');
}

function closeReceipt() {
    document.getElementById('receiptOverlay').classList.remove('show');
}

function newSale() {
    closeReceipt();
    document.getElementById('drugSearch').value = '';
    document.getElementById('drugResults').innerHTML =
        '<div class="dr-state"><i class="ti ti-search"></i><span>Type at least 2 characters to search.</span></div>';
    cart = [];
    renderCart();
    fetchDailyIncome();
}

// ── Init ─────────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    fetchDailyIncome();
    renderCart();

    // Close qty modal on Escape
    document.addEventListener('keydown', e => {
        if (e.key === 'Escape') {
            document.getElementById('qtyOverlay').classList.remove('show');
            document.getElementById('receiptOverlay').classList.remove('show');
        }
    });
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
