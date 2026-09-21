<?php
require getcwd() . '/app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);
Mage::setIsDeveloperMode(true);
Mage::app()->getCacheInstance()->flush();
Mage::getConfig()->reinit();
Mage_Core_Model_Resource_Setup::applyAllUpdates();
$cfg = Mage::getConfig();
$cfg->saveConfig('ainative/general/provider', 'mock');
$cfg->saveConfig('ainative_copilot/general/enabled', 1);
$cfg->reinit();
$out = [];
$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
foreach (['ainative_job','ainative_conversation','ainative_message'] as $t) { $out['tables'][$t] = $conn->isTableExists($t); }
$user = Mage::getModel('admin/user')->loadByUsername('admin');
$ctx = AiNative_Core_Model_Tool_Context::forAdmin('copilot', $user, ['read', 'write']);
// 1) generator on a product
$product = Mage::getModel('catalog/product')->setStoreId(0)->load(400);
$gen = Mage::getModel('ainative_copilot/generator');
$r = $gen->forProduct($product, ['short_description', 'meta_title'], ['tone' => 'playful'], $ctx);
$out['generator_product'] = $r['fields'];
// 2) category
$cat = Mage::getModel('catalog/category')->setStoreId(0)->load(4);
$out['generator_category'] = $gen->forCategory($cat, ['description'], [], $ctx)['fields'];
// 3) order reply
$order = Mage::getModel('sales/order')->loadByIncrementId('145000004');
$out['order_reply'] = mb_substr($gen->orderReply($order, 'apologise for delay', [], $ctx)['text'], 0, 80);
// 4) job pipeline
$job = Mage::getModel('ainative_copilot/job');
$job->setData(['type' => 'product_content', 'entity_id' => 400, 'store_id' => 0, 'fields' => 'meta_description,meta_keyword', 'options_json' => '{}', 'status' => 'pending', 'created_by' => $user->getId()])->save();
Mage::getModel('ainative_copilot/cron')->processJobs();
$job->load($job->getId());
$out['job'] = ['status' => $job->getStatus(), 'draft' => $job->getDraft(), 'error' => $job->getData('error')];
$job->apply((int) $user->getId(), ['meta_keyword' => 'mock, keywords, applied']);
$out['job_applied'] = Mage::getModel('catalog/product')->load(400)->getMetaKeyword();
// 5) agent loop with a scripted tool call through the admin registry (same as Ask)
$tools = Mage::getSingleton('ainative_core/tool_registry')->getToolsFor('admin', $ctx);
$out['tools_for_admin_rw'] = count($tools);
$conv = AiNative_Core_Model_Conversation::create('system')->addUser('please [[call:get_stock {"skus":["hde013"]}]]');
$agent = Mage::getModel('ainative_core/agent');
$res = $agent->run($conv, $tools, $ctx);
$out['agent'] = ['text' => mb_substr($res['text'], 0, 120), 'tool_calls' => $res['tool_calls'], 'pending' => $res['pending_confirmations']];
// 6) write proposal gating
$conv2 = AiNative_Core_Model_Conversation::create('system')->addUser('[[call:update_stock {"sku":"hde013","qty":26}]]');
$agent2 = Mage::getModel('ainative_core/agent');
$agent2->confirmWriteWith(fn() => false);
$res2 = $agent2->run($conv2, $tools, $ctx);
$out['agent_write_gated'] = ['pending' => array_column($res2['pending_confirmations'], 'name'), 'qty_unchanged' => (float) Mage::getModel('cataloginventory/stock_item')->loadByProduct(Mage::getModel('catalog/product')->load(400))->getQty()];
// 7) conversation persistence
$c = Mage::getModel('ainative_copilot/conversation');
$c->setData(['channel' => 'copilot', 'actor_type' => 'admin', 'actor_id' => $user->getId(), 'store_id' => 0])->save();
$c->addMessage('user', 'hi'); $c->addMessage('assistant', 'hello', ['tools' => []], 0, 5, 6); $c->setState($conv)->save();
$out['conversation'] = ['id' => (int) $c->getId(), 'messages' => count($c->getMessages()), 'title' => $c->getData('title'), 'state_msgs' => Mage::getModel('ainative_copilot/conversation')->load($c->getId())->getState()->count()];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), "\n";
