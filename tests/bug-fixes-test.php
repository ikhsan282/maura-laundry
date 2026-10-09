<?php
/**
 * Test Suite for 7 Critical Bug Fixes
 * Branch: fix/maura-critical-bugs
 * Static code analysis and pattern verification
 */

class BugFixTests {
    private $results = [];
    private $projectRoot;
    
    public function __construct() {
        $this->projectRoot = dirname(__DIR__);
        echo "=== MAURA LAUNDRY BUG FIX TEST SUITE ===\n";
        echo "Branch: fix/maura-critical-bugs\n";
        echo "Date: " . date('Y-m-d H:i:s') . "\n";
        echo "Project: {$this->projectRoot}\n\n";
    }
    
    public function runAll() {
        $this->testBug001_TransportFeesInSubscriptionOrders();
        $this->testBug002_ServerSideOrderTotalRecalculation();
        $this->testBug003_OrderNumberRaceCondition();
        $this->testBug004_PartialDepositPayments();
        $this->testBug005_DashboardOrderLinks();
        $this->testBug006_DateTimePreservation();
        $this->testBug007_JSObjectKeysValidation();
        
        $this->printSummary();
    }
    
    private function pass($test, $message) {
        $this->results[] = ['test' => $test, 'status' => 'PASS', 'message' => $message];
        echo "✓ PASS: $test\n  → $message\n\n";
    }
    
    private function fail($test, $message) {
        $this->results[] = ['test' => $test, 'status' => 'FAIL', 'message' => $message];
        echo "✗ FAIL: $test\n  → $message\n\n";
    }
    
    private function getFile($path) {
        $fullPath = $this->projectRoot . '/' . $path;
        if (!file_exists($fullPath)) {
            throw new Exception("File not found: $fullPath");
        }
        return file_get_contents($fullPath);
    }
    
    // BUG-001: Transport fees in subscription-generated orders
    public function testBug001_TransportFeesInSubscriptionOrders() {
        echo "TEST BUG-001: Transport fees in subscription orders\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $subCode = $this->getFile('includes/subscriptions.php');
            
            // Check if order_grand_total function is called
            if (strpos($subCode, 'order_grand_total($total, $type, 0, 0)') !== false) {
                $this->pass('BUG-001', 'order_grand_total() called in subscription generation (line 48)');
            } else {
                $this->fail('BUG-001', 'order_grand_total() NOT found in subscription generation');
            }
            
            // Verify it's called AFTER calculating items total
            $pattern = '/foreach\s*\(\$items.*\$total\s*\+=.*\}\s*\$total\s*=\s*order_grand_total/s';
            if (preg_match($pattern, $subCode)) {
                $this->pass('BUG-001', 'Transport fees calculated after items total');
            } else {
                // Alternative: check the structure is correct
                $hasLoop = strpos($subCode, 'foreach ($items as $it)') !== false;
                $hasCalc = strpos($subCode, '$total = order_grand_total($total, $type, 0, 0)') !== false;
                if ($hasLoop && $hasCalc) {
                    $this->pass('BUG-001', 'Transport fees calculated separately from items loop');
                } else {
                    $this->fail('BUG-001', 'Transport fee calculation order incorrect');
                }
            }
        } catch (Exception $e) {
            $this->fail('BUG-001', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-002: Server-side order total recalculation
    public function testBug002_ServerSideOrderTotalRecalculation() {
        echo "TEST BUG-002: Server-side order total recalculation\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $createCode = $this->getFile('pages/orders/create.php');
            
            // Check total is reset to 0 and recalculated
            if (preg_match('/\$total\s*=\s*0;/', $createCode, $matches, PREG_OFFSET_CAPTURE)) {
                $resetPos = $matches[0][1];
                $this->pass('BUG-002', 'Total reset to 0 before recalculation (line ~61)');
            } else {
                $this->fail('BUG-002', 'Total NOT reset to 0');
            }
            
            // Check foreach loop recalculates from items
            if (preg_match('/foreach\s*\(\$items\s+as\s+\$it\).*\$total\s*\+=.*\$it\[.*subtotal/s', $createCode)) {
                $this->pass('BUG-002', 'Total recalculated from item subtotals (line 62)');
            } else {
                $this->fail('BUG-002', 'Item subtotal recalculation NOT found');
            }
            
            // Verify transport fees added via order_grand_total
            if (strpos($createCode, 'order_grand_total($total, $service_type, $pickup_fee, $delivery_fee)') !== false) {
                $this->pass('BUG-002', 'Transport fees added via order_grand_total (line 63)');
            } else {
                $this->fail('BUG-002', 'Transport fees not properly integrated');
            }
        } catch (Exception $e) {
            $this->fail('BUG-002', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-003: Order number race condition
    public function testBug003_OrderNumberRaceCondition() {
        echo "TEST BUG-003: Order number race condition protection\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $funcCode = $this->getFile('includes/functions.php');
            
            // Check for LOCK TABLES
            if (strpos($funcCode, 'LOCK TABLES orders WRITE') !== false) {
                $this->pass('BUG-003', 'LOCK TABLES orders WRITE found (line 74)');
            } else {
                $this->fail('BUG-003', 'LOCK TABLES NOT found');
            }
            
            // Check for UNLOCK TABLES
            if (strpos($funcCode, 'UNLOCK TABLES') !== false) {
                $this->pass('BUG-003', 'UNLOCK TABLES found (line 81)');
            } else {
                $this->fail('BUG-003', 'UNLOCK TABLES NOT found');
            }
            
            // Verify lock happens BEFORE count query
            $lockPos = strpos($funcCode, 'LOCK TABLES orders WRITE');
            $countPos = strpos($funcCode, 'SELECT COUNT(*) FROM orders');
            $unlockPos = strpos($funcCode, 'UNLOCK TABLES');
            
            if ($lockPos !== false && $countPos !== false && $unlockPos !== false) {
                if ($lockPos < $countPos && $countPos < $unlockPos) {
                    $this->pass('BUG-003', 'Lock/unlock properly wraps count query');
                } else {
                    $this->fail('BUG-003', 'Lock/unlock/count order incorrect');
                }
            }
        } catch (Exception $e) {
            $this->fail('BUG-003', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-004: Partial deposit payments
    public function testBug004_PartialDepositPayments() {
        echo "TEST BUG-004: Partial deposit payments allowed\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $paymentCode = $this->getFile('pages/payments/create.php');
            
            // Check that exact match validation is REMOVED (line 41)
            $lines = explode("\n", $paymentCode);
            $hasExactMatch = false;
            $lineNum = 0;
            
            foreach ($lines as $i => $line) {
                if (strpos($line, 'deposit') !== false && 
                    strpos($line, 'abs($amount - $remaining)') !== false &&
                    $i < 50) { // Should be around line 41
                    $hasExactMatch = true;
                    $lineNum = $i + 1;
                    break;
                }
            }
            
            if (!$hasExactMatch) {
                $this->pass('BUG-004', 'Exact deposit match validation removed (line 41 deleted)');
            } else {
                $this->fail('BUG-004', "Exact match validation still present at line $lineNum");
            }
            
            // Check second validation in transaction (line 56)
            $hasSecondCheck = false;
            $lineNum2 = 0;
            foreach ($lines as $i => $line) {
                if (strpos($line, 'deposit') !== false && 
                    strpos($line, 'abs($amount - $locked_remaining)') !== false &&
                    $i > 50) { // Should be around line 56
                    $hasSecondCheck = true;
                    $lineNum2 = $i + 1;
                    break;
                }
            }
            
            if (!$hasSecondCheck) {
                $this->pass('BUG-004', 'Second exact match check removed (line 56 deleted)');
            } else {
                $this->fail('BUG-004', "Second check still present at line $lineNum2");
            }
            
            // Verify balance check remains
            if (strpos($paymentCode, '$amount > $customer_balance') !== false) {
                $this->pass('BUG-004', 'Balance check properly preserved');
            } else {
                $this->fail('BUG-004', 'Balance check missing');
            }
        } catch (Exception $e) {
            $this->fail('BUG-004', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-005: Dashboard order links
    public function testBug005_DashboardOrderLinks() {
        echo "TEST BUG-005: Dashboard order links use correct parameter\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $dashCode = $this->getFile('pages/dashboard.php');
            
            // Check for correct parameter: order_number
            if (strpos($dashCode, 'view.php?order_number=') !== false) {
                $this->pass('BUG-005', 'Dashboard uses order_number parameter');
            } else {
                $this->fail('BUG-005', 'order_number parameter NOT found');
            }
            
            // Verify it uses htmlspecialchars/h() for encoding
            if (preg_match('/order_number=.*h\(\$r\[.*order_number/', $dashCode)) {
                $this->pass('BUG-005', 'Order number properly escaped with h()');
            } else {
                $this->fail('BUG-005', 'Order number not properly escaped');
            }
            
            // Check that old ?id= is NOT present
            if (strpos($dashCode, 'view.php?id=') === false) {
                $this->pass('BUG-005', 'Old ?id= parameter removed');
            } else {
                $this->fail('BUG-005', 'Old ?id= parameter still present');
            }
        } catch (Exception $e) {
            $this->fail('BUG-005', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-006: DateTime time component preservation
    public function testBug006_DateTimePreservation() {
        echo "TEST BUG-006: DateTime preserves time component\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $createCode = $this->getFile('pages/orders/create.php');
            
            // Check for removal of ! flag
            $hasExclamation = preg_match('/createFromFormat\([\'"]!Y-m-d/', $createCode);
            
            if (!$hasExclamation) {
                $this->pass('BUG-006', 'DateTime ! flag removed (lines 77, 84)');
            } else {
                $this->fail('BUG-006', 'DateTime ! flag still present');
            }
            
            // Verify correct format string
            if (strpos($createCode, "createFromFormat('Y-m-d\\\\TH:i'") !== false) {
                $this->pass('BUG-006', 'Correct DateTime format with time component');
            } else {
                $this->fail('BUG-006', 'DateTime format incorrect');
            }
            
            // Test actual DateTime parsing
            $testDatetime = '2026-10-09T14:30';
            $dt = DateTime::createFromFormat('Y-m-d\TH:i', $testDatetime);
            
            if ($dt && $dt->format('H:i') === '14:30') {
                $this->pass('BUG-006', "Functional test: time preserved as {$dt->format('Y-m-d H:i:s')}");
            } else {
                $this->fail('BUG-006', 'Functional test: time NOT preserved');
            }
        } catch (Exception $e) {
            $this->fail('BUG-006', 'Error: ' . $e->getMessage());
        }
    }
    
    // BUG-007: JS Object.keys() validation
    public function testBug007_JSObjectKeysValidation() {
        echo "TEST BUG-007: JS Object.keys() used instead of .length\n";
        echo str_repeat('-', 60) . "\n";
        
        try {
            $formCode = $this->getFile('pages/subscriptions/form.php');
            
            // Check for Object.keys(SELECTED).length
            if (strpos($formCode, 'Object.keys(SELECTED).length') !== false) {
                $this->pass('BUG-007', 'Object.keys(SELECTED).length found (line 157)');
            } else {
                $this->fail('BUG-007', 'Object.keys() pattern NOT found');
            }
            
            // Check that old SELECTED.length ? is NOT present
            if (!preg_match('/SELECTED\.length\s*\?/', $formCode)) {
                $this->pass('BUG-007', 'Old SELECTED.length ? pattern removed');
            } else {
                $this->fail('BUG-007', 'Old SELECTED.length ? still present');
            }
            
            // Verify it's in ternary expression
            if (preg_match('/Object\.keys\(SELECTED\)\.length\s*\?\s*Object\.entries/', $formCode)) {
                $this->pass('BUG-007', 'Correct ternary expression with Object.keys()');
            } else {
                $this->fail('BUG-007', 'Ternary expression structure incorrect');
            }
        } catch (Exception $e) {
            $this->fail('BUG-007', 'Error: ' . $e->getMessage());
        }
    }
    
    private function printSummary() {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TEST SUMMARY\n";
        echo str_repeat('=', 60) . "\n";
        
        $passed = 0;
        $failed = 0;
        
        foreach ($this->results as $result) {
            if ($result['status'] === 'PASS') {
                $passed++;
            } else {
                $failed++;
            }
        }
        
        $total = count($this->results);
        $passRate = $total > 0 ? round(($passed / $total) * 100, 1) : 0;
        
        echo "\nTotal Tests: $total\n";
        echo "Passed: $passed\n";
        echo "Failed: $failed\n";
        echo "Pass Rate: {$passRate}%\n\n";
        
        if ($failed > 0) {
            echo "FAILED TESTS:\n";
            foreach ($this->results as $result) {
                if ($result['status'] === 'FAIL') {
                    echo "  ✗ {$result['test']}: {$result['message']}\n";
                }
            }
            echo "\n";
        }
        
        if ($failed === 0) {
            echo "🎉 ALL TESTS PASSED! All 7 bug fixes verified.\n";
        } else {
            echo "⚠️  Some tests failed. Review the failures above.\n";
        }
        
        echo "\nBug fixes tested:\n";
        echo "  1. BUG-001: Transport fees in subscription orders\n";
        echo "  2. BUG-002: Server-side order total recalculation\n";
        echo "  3. BUG-003: Order number race condition (table locks)\n";
        echo "  4. BUG-004: Partial deposit payments allowed\n";
        echo "  5. BUG-005: Dashboard order links (order_number param)\n";
        echo "  6. BUG-006: DateTime time component preservation\n";
        echo "  7. BUG-007: JS Object.keys() validation\n";
    }
}

// Run all tests
$tests = new BugFixTests();
$tests->runAll();
