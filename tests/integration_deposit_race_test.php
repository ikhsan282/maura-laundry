<?php
/**
 * Integration test: Deposit payment race condition
 * 
 * Race scenario: Two concurrent sessions attempt to use the same customer's deposit balance:
 * - Session A: Reads balance = 50000, starts payment (amount=50000)
 * - Session B: Reads balance = 50000, starts top-up debit (amount=50000) 
 * - Both proceed concurrently
 * - Expected: One succeeds, one fails with insufficient balance
 * - Bug: If balance check happens before row lock, both may succeed (overdraft)
 * 
 * Test requires: mysqli, db_maura_laundry database, schema loaded
 * Run: php tests/integration_deposit_race_test.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

function assert_eq($expected, $actual, string $msg): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$msg}\n  Expected: " . var_export($expected, true) . "\n  Got: " . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_true($cond, string $msg): void {
    if (!$cond) {
        fwrite(STDERR, "FAIL: {$msg}\n");
        exit(1);
    }
}

if (!extension_loaded('mysqli')) {
    fwrite(STDERR, "SKIP: mysqli extension not loaded\n");
    exit(0);
}

$db = @db();
if (!$db || $db->connect_error) {
    fwrite(STDERR, "SKIP: Cannot connect to database\n");
    exit(0);
}

// Setup: Create test customer with deposit balance
$db->query("START TRANSACTION");
$db->query("DELETE FROM customer_deposit_transactions WHERE customer_id IN (SELECT id FROM customers WHERE phone='TEST_RACE_CUST')");
$db->query("DELETE FROM customers WHERE phone='TEST_RACE_CUST'");
$db->query("INSERT INTO customers (name, phone) VALUES ('Race Test Customer', 'TEST_RACE_CUST')");
$cust_id = $db->insert_id;
$db->query("INSERT INTO customer_deposit_transactions (customer_id, user_id, type, amount, method) VALUES ({$cust_id}, 1, 'topup', 50000, 'cash')");
$db->query("COMMIT");

// Verify initial balance
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
$stmt->bind_param('i', $cust_id);
$stmt->execute();
$initial_balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
$stmt->close();
assert_eq(50000.0, $initial_balance, 'Initial balance should be 50000');

// Simulate race: Two sessions both try to debit 50000
// Session A: Payment debit
// Session B: Manual debit
// Both read balance=50000 outside lock, both think they can proceed

$fork_supported = function_exists('pcntl_fork');
if (!$fork_supported) {
    echo "INFO: pcntl_fork not available, testing sequentially\n";
    // Sequential test: Just verify the lock prevents double-spend
    $db->begin_transaction();
    $lock = $db->prepare("SELECT id FROM customers WHERE id=? FOR UPDATE");
    $lock->bind_param('i', $cust_id);
    $lock->execute();
    $lock->close();
    
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
    $stmt->bind_param('i', $cust_id);
    $stmt->execute();
    $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
    $stmt->close();
    
    $debit_amount = -50000.0;
    if ($balance + $debit_amount >= 0) {
        $stmt = $db->prepare("INSERT INTO customer_deposit_transactions (customer_id, user_id, type, amount, method, notes) VALUES (?,1,'debit',?,'internal','Test debit')");
        $stmt->bind_param('id', $cust_id, $debit_amount);
        $stmt->execute();
        $stmt->close();
        $db->commit();
        
        // Try second debit - should fail
        $db->begin_transaction();
        $lock = $db->prepare("SELECT id FROM customers WHERE id=? FOR UPDATE");
        $lock->bind_param('i', $cust_id);
        $lock->execute();
        $lock->close();
        
        $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
        $stmt->bind_param('i', $cust_id);
        $stmt->execute();
        $balance_after = (float)$stmt->get_result()->fetch_assoc()['balance'];
        $stmt->close();
        
        $can_debit_again = ($balance_after + $debit_amount >= 0);
        $db->rollback();
        
        assert_true(!$can_debit_again, 'Second concurrent debit should be rejected after first succeeds');
    }
} else {
    // Fork test: True concurrent access
    $pid = pcntl_fork();
    if ($pid === -1) {
        die("Fork failed\n");
    } elseif ($pid === 0) {
        // Child: Session B - manual debit. Must open its own connection; a forked
        // child sharing the parent's mysqli socket would corrupt both sessions.
        usleep(10000);
        $db_child = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $db_child->set_charset('utf8mb4');
        try {
            $db_child->begin_transaction();
            $lock = $db_child->prepare("SELECT id FROM customers WHERE id=? FOR UPDATE");
            $lock->bind_param('i', $cust_id);
            $lock->execute();
            $lock->close();
            
            $stmt = $db_child->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
            $stmt->bind_param('i', $cust_id);
            $stmt->execute();
            $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
            $stmt->close();
            
            $debit = -50000.0;
            if ($balance + $debit < 0) {
                $db_child->rollback();
                exit(2); // Signal insufficient balance
            }
            
            $stmt = $db_child->prepare("INSERT INTO customer_deposit_transactions (customer_id,user_id,type,amount,method,notes) VALUES (?,1,'debit',?,'internal','Child debit')");
            $stmt->bind_param('id', $cust_id, $debit);
            $stmt->execute();
            $stmt->close();
            $db_child->commit();
            exit(0); // Success
        } catch (Throwable $e) {
            $db_child->rollback();
            exit(1); // Error
        }
    } else {
        // Parent: Session A - payment debit
        try {
            $db->begin_transaction();
            $lock = $db->prepare("SELECT id FROM customers WHERE id=? FOR UPDATE");
            $lock->bind_param('i', $cust_id);
            $lock->execute();
            $lock->close();
            
            $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
            $stmt->bind_param('i', $cust_id);
            $stmt->execute();
            $balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
            $stmt->close();
            
            usleep(50000); // Hold lock while child waits
            
            $debit = -50000.0;
            if ($balance + $debit < 0) {
                $db->rollback();
                $parent_success = false;
            } else {
                $stmt = $db->prepare("INSERT INTO customer_deposit_transactions (customer_id,user_id,type,amount,method,notes) VALUES (?,1,'debit',?,'internal','Parent debit')");
                $stmt->bind_param('id', $cust_id, $debit);
                $stmt->execute();
                $stmt->close();
                $db->commit();
                $parent_success = true;
            }
        } catch (Throwable $e) {
            $db->rollback();
            $parent_success = false;
        }
        
        pcntl_waitpid($pid, $status);
        $child_exit = pcntl_wexitstatus($status);
        
        // Exactly one should succeed
        $child_success = ($child_exit === 0);
        assert_true($parent_success XOR $child_success, 'Exactly one of two concurrent debits should succeed');
        
        // mysqli connections cannot be reused safely after pcntl_fork.
        // Re-open the parent connection before the final verification/cleanup.
        $db->close();
        $db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
        $db->set_charset('utf8mb4');
    }
}

// Verify final balance is not negative
$stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS balance FROM customer_deposit_transactions WHERE customer_id=?");
$stmt->bind_param('i', $cust_id);
$stmt->execute();
$final_balance = (float)$stmt->get_result()->fetch_assoc()['balance'];
$stmt->close();
assert_true($final_balance >= 0, 'Final balance should never be negative after concurrent operations');

// Cleanup
$db->query("DELETE FROM customer_deposit_transactions WHERE customer_id={$cust_id}");
$db->query("DELETE FROM customers WHERE id={$cust_id}");

echo "deposit race: OK\n";
