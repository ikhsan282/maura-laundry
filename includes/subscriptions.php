<?php
function subscription_next_due(string $due, string $frequency): string {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $due);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
        throw new InvalidArgumentException('Tanggal jatuh tempo tidak valid.');
    }
    return match ($frequency) {
        'weekly' => $date->modify('+7 days')->format('Y-m-d'),
        'biweekly' => $date->modify('+14 days')->format('Y-m-d'),
        'monthly' => (function () use ($date) {
            $first = $date->modify('first day of next month');
            return $first->setDate((int)$first->format('Y'), (int)$first->format('m'),
                min((int)$date->format('d'), (int)$first->format('t')))->format('Y-m-d');
        })(),
        default => throw new InvalidArgumentException('Frekuensi langganan tidak valid.'),
    };
}

// Turns one subscription into a real order and advances next_due, atomically.
function generate_subscription_order(mysqli $db, int $subscription_id, int $user_id): string {
    if ($subscription_id <= 0 || $user_id <= 0) throw new InvalidArgumentException('Langganan tidak valid.');
    $db->begin_transaction();
    try {
        $stmt = $db->prepare(
            "SELECT s.*, c.phone FROM subscriptions s JOIN customers c ON c.id = s.customer_id
             WHERE s.id = ? AND s.is_active = 1 FOR UPDATE"
        );
        $stmt->bind_param('i', $subscription_id); $stmt->execute();
        $sub = $stmt->get_result()->fetch_assoc(); $stmt->close();
        if (!$sub) throw new RuntimeException('Langganan tidak ditemukan atau nonaktif.');

        $stmt = $db->prepare(
            "SELECT si.service_id, si.quantity, sv.price, sv.duration_days
             FROM subscription_items si JOIN services sv ON sv.id = si.service_id
             WHERE si.subscription_id = ? AND sv.is_active = 1 ORDER BY si.id"
        );
        $stmt->bind_param('i', $subscription_id); $stmt->execute();
        $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();
        if (!$items) throw new RuntimeException('Langganan belum memiliki item layanan aktif.');

        $total = 0.0; $max_days = 0;
        foreach ($items as $it) {
            $total    += (float)$it['price'] * (float)$it['quantity'];
            $max_days  = max($max_days, (int)$it['duration_days']);
        }
        $total = order_grand_total($total, $type, 0, 0);
        $order_number = generate_order_number();
        $estimated    = date('Y-m-d', strtotime("+{$max_days} days"));
        $type         = $sub['service_type'];
        $addr         = $sub['address'] ?: null;
        $pickup_addr  = in_array($type, ['pickup', 'both'], true) ? $addr : null;
        $delivery_addr = in_array($type, ['delivery', 'both'], true) ? $addr : null;
        $pickup_ct    = $pickup_addr ? $sub['phone'] : null;
        $delivery_ct  = $delivery_addr ? $sub['phone'] : null;
        $pickup_st    = $pickup_addr ? 'scheduled' : 'not_required';
        $delivery_st  = $delivery_addr ? 'scheduled' : 'not_required';
        $notes        = 'Dibuat dari langganan #' . $subscription_id . ($sub['notes'] ? ': ' . $sub['notes'] : '');

        $stmt = $db->prepare(
            "INSERT INTO orders (order_number,subscription_id,customer_id,user_id,total_amount,estimated_done,notes,service_type,pickup_address,pickup_contact,pickup_status,delivery_address,delivery_contact,delivery_status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)"
        );
        $stmt->bind_param('siiidsssssssss', $order_number, $subscription_id, $sub['customer_id'], $user_id, $total, $estimated, $notes, $type, $pickup_addr, $pickup_ct, $pickup_st, $delivery_addr, $delivery_ct, $delivery_st);
        $stmt->execute(); $order_id = $db->insert_id; $stmt->close();

        $si = $db->prepare("INSERT INTO order_items (order_id,service_id,quantity,price,subtotal) VALUES (?,?,?,?,?)");
        foreach ($items as $it) {
            $qty = (float)$it['quantity']; $price = (float)$it['price']; $sub_total = $qty * $price;
            $si->bind_param('iiddd', $order_id, $it['service_id'], $qty, $price, $sub_total); $si->execute();
        }
        $si->close();

        $next = subscription_next_due($sub['next_due'], $sub['frequency']);
        $stmt = $db->prepare("UPDATE subscriptions SET next_due = ? WHERE id = ?");
        $stmt->bind_param('si', $next, $subscription_id); $stmt->execute(); $stmt->close();

        $db->commit();
        return $order_number;
    } catch (Throwable $e) {
        $db->rollback();
        throw $e;
    }
}
