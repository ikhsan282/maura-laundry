<?php
require_once __DIR__ . '/../includes/subscriptions.php';

function same_value($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}; expected " . var_export($expected, true) . ', got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

same_value('2026-01-15', subscription_next_due('2026-01-08', 'weekly'), 'weekly advances seven days');
same_value('2026-01-22', subscription_next_due('2026-01-08', 'biweekly'), 'biweekly advances fourteen days');
same_value('2026-02-28', subscription_next_due('2026-01-31', 'monthly'), 'monthly clamps to the last valid day');

$thrown = false;
try { subscription_next_due('2026-01-08', 'daily'); } catch (InvalidArgumentException) { $thrown = true; }
same_value(true, $thrown, 'invalid frequency rejected');

echo "subscriptions: OK\n";
