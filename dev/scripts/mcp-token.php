<?php
// Enables AI Suite + MCP in config and prints a fresh admin token. Run inside OpenMage root.
require getcwd() . '/app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->getCacheInstance()->flush();
Mage::getConfig()->reinit();
Mage_Core_Model_Resource_Setup::applyAllUpdates();
$cfg = Mage::getConfig();
$scope = $argv[1] ?? 'read';
$writeAllowed = ($argv[2] ?? '0') === '1' ? 1 : 0;
foreach ([
    'ainative/general/enabled' => 1,
    'ainative_mcp/general/enabled' => 1,
    'ainative_mcp/sql/enabled' => 1,
    'ainative/security/allow_write_tools' => $writeAllowed,
    'ainative_mcp/general/allowed_origins' => "https://claude.ai\nhttp://localhost:6274",
] as $path => $value) {
    $cfg->saveConfig($path, $value);
}
$cfg->reinit();
Mage::app()->getCacheInstance()->flush();
$user = Mage::getModel('admin/user')->loadByUsername('admin');
$secret = Mage::getModel('ainative_mcp/token')->issue('smoke-' . $scope, (int) $user->getId(), $scope, null, null);
echo $secret, "\n";
