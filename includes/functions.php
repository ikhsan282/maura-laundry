<?php
// CSRF
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_verify(): bool {
    $token = $_POST['csrf_token'] ?? '';
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

// Flash messages
function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function flash_html(): string {
    $f = get_flash();
    if (!$f) return '';
    $map = ['success' => 'success', 'error' => 'danger', 'warning' => 'warning', 'info' => 'info'];
    $cls = $map[$f['type']] ?? 'info';
    return '<div class="alert alert-' . $cls . ' alert-dismissible fade show" role="alert">'
        . htmlspecialchars($f['message'])
        . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// Format currency IDR
function idr(float $amount): string {
    return 'Rp ' . number_format($amount, 0, ',', '.');
}

function transport_fee(string $type, float $pickup_fee, float $delivery_fee): float {
    if (!in_array($type, ['none', 'pickup', 'delivery', 'both'], true)) {
        throw new InvalidArgumentException('Tipe pickup/delivery tidak valid.');
    }
    // NAN/INF slip past `< 0`; decimal(10,2) columns cap at 99999999.99.
    foreach ([$pickup_fee, $delivery_fee] as $fee) {
        if (!is_finite($fee) || $fee < 0 || $fee > 99999999.99) {
            throw new InvalidArgumentException('Biaya pickup/delivery tidak valid.');
        }
    }
    return ($type === 'pickup' || $type === 'both' ? $pickup_fee : 0)
         + ($type === 'delivery' || $type === 'both' ? $delivery_fee : 0);
}

function order_grand_total(float $items_total, string $type, float $pickup_fee, float $delivery_fee): float {
    return $items_total + transport_fee($type, $pickup_fee, $delivery_fee);
}

function deposit_delta(string $type, float $amount): float {
    if ($amount <= 0 || !in_array($type, ['topup', 'debit', 'refund'], true)) {
        throw new InvalidArgumentException('Transaksi deposit tidak valid.');
    }
    return $type === 'debit' ? -$amount : $amount;
}

// Generate order number: ML-YYYYMMDD-XXXX
function generate_order_number(): string {
    $db   = db();
    $date = date('Ymd');
    $stmt = $db->prepare("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return 'ML-' . $date . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
}

// Status badge HTML
function status_badge(string $status): string {
    $map = [
        'diterima'  => 'secondary',
        'dicuci'    => 'primary',
        'disetrika' => 'info',
        'selesai'   => 'success',
        'diambil'   => 'dark',
    ];
    $label = [
        'diterima'  => 'Diterima',
        'dicuci'    => 'Dicuci',
        'disetrika' => 'Disetrika',
        'selesai'   => 'Selesai',
        'diambil'   => 'Diambil',
    ];
    $cls = $map[$status] ?? 'secondary';
    $lbl = $label[$status] ?? ucfirst($status);
    return '<span class="badge bg-' . $cls . '">' . $lbl . '</span>';
}

// Next status
function next_status(string $current): ?string {
    $flow = ['diterima' => 'dicuci', 'dicuci' => 'disetrika', 'disetrika' => 'selesai', 'selesai' => 'diambil'];
    return $flow[$current] ?? null;
}

// Safe redirect
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// h() shorthand
function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

// Send email via PHP mail()
function send_email(string $to, string $subject, string $body): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . MAIL_FROM_NAME . " <" . MAIL_FROM . ">\r\n";
    return mail($to, $subject, $body, $headers);
}

// Pagination helper
function paginate(int $total, int $per_page, int $current_page, string $url_pattern): string {
    $pages = (int) ceil($total / $per_page);
    if ($pages <= 1) return '';
    $html = '<nav><ul class="pagination pagination-sm mb-0">';
    for ($i = 1; $i <= $pages; $i++) {
        $active = $i === $current_page ? ' active' : '';
        $url    = sprintf($url_pattern, $i);
        $html  .= '<li class="page-item' . $active . '"><a class="page-link" href="' . $url . '">' . $i . '</a></li>';
    }
    $html .= '</ul></nav>';
    return $html;
}

// Type label
function type_label(string $type): string {
    return ['kiloan' => 'Kiloan', 'satuan' => 'Satuan', 'express' => 'Express'][$type] ?? $type;
}
