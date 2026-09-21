<?php

/**
 * Test bootstrap. Requires an installed OpenMage root; set OPENMAGE_ROOT (default: dev/openmage).
 * Unit tests only need the class files; integration tests need the database.
 */
declare(strict_types=1);

$root = getenv('OPENMAGE_ROOT') ?: __DIR__ . '/../dev/openmage';
if (!is_file($root . '/app/Mage.php')) {
    fwrite(STDERR, "OpenMage not found at {$root}. Run dev/setup-openmage.sh or set OPENMAGE_ROOT.\n");
    exit(1);
}
if (is_file($root . '/vendor/autoload.php')) {
    require_once $root . '/vendor/autoload.php';
}
require_once $root . '/app/Mage.php';
Mage::setIsDeveloperMode(true);
Mage::app('admin')->setUseSessionInUrl(false);
define('AINATIVE_TEST_ROOT', $root);
