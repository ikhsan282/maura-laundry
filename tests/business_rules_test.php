<?php
require_once __DIR__ . '/../includes/functions.php';

function same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

same(0.0, transport_fee('none', 10000, 15000), 'none has no transport fee');
same(10000.0, transport_fee('pickup', 10000, 15000), 'pickup fee');
same(15000.0, transport_fee('delivery', 10000, 15000), 'delivery fee');
same(25000.0, transport_fee('both', 10000, 15000), 'both fees');
same(50000.0, order_grand_total(25000, 'both', 10000, 15000), 'grand total includes transport');
same(50000.0, deposit_delta('topup', 50000), 'topup credits balance');
same(50000.0, deposit_delta('refund', 50000), 'refund credits balance');
same(-50000.0, deposit_delta('debit', 50000), 'debit reduces balance');

$thrown = false;
try { transport_fee('invalid', 0, 0); } catch (InvalidArgumentException) { $thrown = true; }
same(true, $thrown, 'invalid service type rejected');
$thrown = false;
try { deposit_delta('debit', 0); } catch (InvalidArgumentException) { $thrown = true; }
same(true, $thrown, 'non-positive deposit amount rejected');

echo "business rules: OK\n";
