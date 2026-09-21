<?php
// Run inside the OpenMage root: php /var/www/suite/dev/scripts/smoke-core.php
require getcwd() . '/app/Mage.php';
Mage::app('admin')->setUseSessionInUrl(false);
Mage::app()->getCacheInstance()->flush();
Mage::getConfig()->reinit();
Mage::getConfig()->getNode()->modules; // force load
Mage_Core_Model_Resource_Setup::applyAllUpdates();
Mage_Core_Model_Resource_Setup::applyAllDataUpdates();

$out = [];
foreach (['AiNative_Core','AiNative_Mcp','AiNative_Copilot','AiNative_Assistant','AiNative_Discovery'] as $m) {
    $out['modules'][$m] = (string) Mage::getConfig()->getModuleConfig($m)->active . '/' . (string) Mage::getConfig()->getModuleConfig($m)->version;
}
$conn = Mage::getSingleton('core/resource')->getConnection('core_read');
foreach (['ainative_audit','ainative_usage','ainative_rate_limit'] as $t) {
    $out['tables'][$t] = $conn->isTableExists(Mage::getSingleton('core/resource')->getTableName($t));
}
$h = Mage::helper('ainative_core');
$out['helper'] = get_class($h) . ' enabled=' . var_export($h->isEnabled(), true) . ' provider=' . $h->getProviderCode();
foreach (['anthropic','openai','gemini'] as $p) {
    $prov = $h->getProvider($p);
    $out['providers'][$p] = get_class($prov) . ' model=' . $prov->getModel() . ' configured=' . var_export($prov->isConfigured(), true);
}
$out['redact'] = Mage::helper('ainative_core/redact')->text('Contact john.doe@example.com or +1 (555) 123-4567 about SKU 12345 costing 99.90');
$schema = Mage::getSingleton('ainative_core/tool_schema');
$out['schema'] = $schema->validate(['type'=>'object','properties'=>['q'=>['type'=>'string'],'limit'=>['type'=>'integer','maximum'=>50],'active'=>['type'=>'boolean'],'ids'=>['type'=>'array','items'=>['type'=>'integer']]],'required'=>['q'],'additionalProperties'=>false], ['q'=>'shoes','limit'=>'500','active'=>'true','ids'=>['1','2'],'junk'=>1]);
try { $schema->validate(['type'=>'object','properties'=>['q'=>['type'=>'string']],'required'=>['q']], []); } catch (AiNative_Core_Exception $e) { $out['schema_required_error'] = $e->getMessage(); }
$out['registry_admin'] = array_keys(Mage::getSingleton('ainative_core/tool_registry')->getTools('admin'));
$ctx = AiNative_Core_Model_Tool_Context::forGuest('assistant', 1);
$out['ratelimit'] = 'ok'; try { for ($i=0;$i<3;$i++) Mage::getSingleton('ainative_core/rateLimit')->hit('smoke', 2); } catch (AiNative_Core_Exception $e) { $out['ratelimit'] = 'limited on 3rd: ' . $e->getMessage(); }
Mage::getModel('ainative_core/audit')->error($ctx, 'smoke_tool', ['a'=>1], 'smoke', 5);
$out['audit_rows'] = Mage::getResourceModel('ainative_core/audit_collection')->getSize();
Mage::getSingleton('ainative_core/usage')->record('anthropic','claude-opus-5','copilot',10,20);
$out['usage_month_total'] = Mage::getSingleton('ainative_core/usage')->getMonthTotal();
$user = Mage::getModel('admin/user')->loadByUsername('admin');
$out['admin_user'] = $user->getId() ? 'found id=' . $user->getId() . ' role=' . $user->getAclRole() : 'not found';
if ($user->getId()) {
    $actx = AiNative_Core_Model_Tool_Context::forAdmin('mcp', $user, ['read']);
    $out['acl_catalog'] = Mage::getSingleton('ainative_core/acl')->isAllowed($actx, 'admin/catalog/products');
    $out['acl_bogus'] = Mage::getSingleton('ainative_core/acl')->isAllowed($actx, 'admin/nonexistent/thing');
}
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
