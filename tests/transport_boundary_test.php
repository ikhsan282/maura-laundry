<?php
/**
 * Transport fee boundary validation test
 * 
 * Tests edge cases for transport_fee() and order_grand_total():
 * - Negative fees (should reject or sanitize)
 * - Zero fees (valid)
 * - Very large fees (overflow check)
 * - Invalid service types
 * - Boundary arithmetic (float precision)
 * 
 * Run: php tests/transport_boundary_test.php
 */

require_once __DIR__ . '/../includes/functions.php';

function assert_eq($expected, $actual, string $msg): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Got: " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_throws(callable $fn, string $expected_exception, string $msg): void {
    $thrown = false;
    try {
        $fn();
    } catch (Throwable $e) {
        if (get_class($e) === $expected_exception || is_a($e, $expected_exception)) {
            $thrown = true;
        } else {
            fwrite(STDERR, "FAIL: {$msg} - wrong exception type: " . get_class($e) . "\n");
            exit(1);
        }
    }
    if (!$thrown) {
        fwrite(STDERR, "FAIL: {$msg} - no exception thrown\n");
        exit(1);
    }
}

// Valid cases
assert_eq(0.0, transport_fee('none', 10000, 15000), 'none type has zero fee');
assert_eq(10000.0, transport_fee('pickup', 10000, 15000), 'pickup type charges pickup fee only');
assert_eq(15000.0, transport_fee('delivery', 10000, 15000), 'delivery type charges delivery fee only');
assert_eq(25000.0, transport_fee('both', 10000, 15000), 'both type charges both fees');

// Zero fees (valid)
assert_eq(0.0, transport_fee('pickup', 0, 0), 'zero pickup fee is valid');
assert_eq(0.0, transport_fee('delivery', 0, 0), 'zero delivery fee is valid');
assert_eq(0.0, transport_fee('both', 0, 0), 'both with zero fees is valid');

// Negative fees are rejected at the boundary, even when unused by the chosen type
assert_throws(fn() => transport_fee('pickup', -5000, 0), 'InvalidArgumentException', 'negative pickup fee rejected');
assert_throws(fn() => transport_fee('none', 0, -5000), 'InvalidArgumentException', 'negative delivery fee rejected for type none');
assert_throws(fn() => order_grand_total(1000, 'both', -1, 0), 'InvalidArgumentException', 'negative fee rejected in grand total');
assert_throws(fn() => transport_fee('pickup', NAN, 0), 'InvalidArgumentException', 'NAN fee rejected');
assert_throws(fn() => transport_fee('delivery', 0, INF), 'InvalidArgumentException', 'INF fee rejected');
assert_throws(fn() => transport_fee('pickup', 99999999.99 + 0.01, 0), 'InvalidArgumentException', 'fee above decimal(10,2) rejected');
assert_eq(99999999.99, transport_fee('pickup', 99999999.99, 0), 'max storable fee accepted');

// Invalid service type
assert_throws(
    fn() => transport_fee('invalid', 0, 0),
    'InvalidArgumentException',
    'invalid service type should throw'
);

assert_throws(
    fn() => transport_fee('', 0, 0),
    'InvalidArgumentException',
    'empty service type should throw'
);

// Very large fees must be rejected, not produce INF
assert_throws(fn() => transport_fee('both', PHP_FLOAT_MAX / 2, PHP_FLOAT_MAX / 2), 'InvalidArgumentException', 'huge fees rejected, no INF');

// Order grand total
assert_eq(75000.0, order_grand_total(50000, 'both', 10000, 15000), 'grand total = items + transport');
assert_eq(50000.0, order_grand_total(50000, 'none', 10000, 15000), 'grand total ignores fees when type=none');

// Float precision boundaries
assert_eq(0.01, transport_fee('pickup', 0.01, 0), 'handles fractional fees');
assert_eq(0.02, transport_fee('both', 0.01, 0.01), 'handles fractional sum');

echo "transport boundary: OK\n";
