<?php
// Real-provider smoke test. Key comes from env ANTHROPIC_KEY; never printed.
require getcwd() . '/app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);
$key = getenv('ANTHROPIC_KEY');
if ($key) {
    $cfg = Mage::getConfig();
    $cfg->saveConfig('ainative/anthropic/api_key', Mage::helper('core')->encrypt($key));
    $cfg->saveConfig('ainative/general/provider', 'anthropic');
    $cfg->saveConfig('ainative/anthropic/model', getenv('MODEL') ?: 'claude-opus-5');
    $cfg->saveConfig('ainative/anthropic/effort', 'low');
    $cfg->saveConfig('ainative/security/debug_log', 0);
    Mage::app()->getCacheInstance()->flush();
    $cfg->reinit();
}
$h = Mage::helper('ainative_core');
$p = $h->getProvider();
echo "provider: ", $p->getCode(), " model: ", $p->getModel(), " configured: ", var_export($p->isConfigured(), true), "\n\n";
$user = Mage::getModel('admin/user')->loadByUsername('admin');
$t0 = microtime(true);

// 1) Ask your store (admin tools, read)
$ctx = AiNative_Core_Model_Tool_Context::forAdmin('copilot', $user, ['read']);
$tools = Mage::getSingleton('ainative_core/tool_registry')->getToolsFor('admin', $ctx);
$conv = AiNative_Core_Model_Conversation::create('You are the operations assistant of an OpenMage store. Use tools for every number. Be concise. Dates in store timezone.')
    ->addUser('What were total sales and order count in April 2013, and what were the top 3 bestselling products that month?');
$r = Mage::getModel('ainative_core/agent')->run($conv, $tools, $ctx);
printf("== ASK (%.1fs, %d tool calls, %d/%d tokens)\n%s\n\n", microtime(true) - $t0, $r['tool_calls'], $r['tokens_in'], $r['tokens_out'], $r['text']);

// 3) Copilot generation
$t0 = microtime(true);
$product = Mage::getModel('catalog/product')->setStoreId(0)->load(400);
$gen = Mage::getModel('ainative_copilot/generator')->forProduct($product, ['short_description', 'meta_title', 'meta_description'], ['tone' => 'friendly'], $ctx);
printf("== COPILOT (%.1fs, %d/%d tokens)\n%s\n\n", microtime(true) - $t0, $gen['tokens_in'], $gen['tokens_out'], json_encode($gen['fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "month tokens so far: ", Mage::getSingleton('ainative_core/usage')->getMonthTotal(), "\n";
