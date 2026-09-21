<?php

/**
 * /ainative-discovery/feed/products/store/{code}.json|csv  — serves the prebuilt feed (builds it on first request).
 * /ainative-discovery/feed/build/store/{code}              — rebuild (requires the feed token when one is set).
 */
class AiNative_Discovery_FeedController extends Mage_Core_Controller_Front_Action
{
    public function preDispatch()
    {
        $this->setFlag('', self::FLAG_NO_START_SESSION, true);
        AiNative_Core_Model_Visitor_Noop::install();
        Mage::app()->setUseSessionInUrl(false);
        return parent::preDispatch();
    }

    public function productsAction(): void
    {
        $helper = Mage::helper('ainative_discovery');
        [$store, $format] = $this->resolve();
        if (!$store || !$helper->isEnabled((int) $store->getId())) {
            $this->norouteAction();
            return;
        }
        if (!$helper->checkFeedToken($this->getRequest())) {
            $this->getResponse()->setHttpResponseCode(401)->setBody('feed token required');
            return;
        }
        $path = $helper->getFeedPath($store, $format);
        if (!is_file($path) || filemtime($path) < time() - 86400) {
            Mage::getModel('ainative_discovery/feed')->build($store);
        }
        $this->getResponse()
            ->setHeader('Content-Type', $format === 'csv' ? 'text/csv; charset=utf-8' : 'application/x-ndjson; charset=utf-8', true)
            ->setHeader('Last-Modified', gmdate('D, d M Y H:i:s', (int) filemtime($path)) . ' GMT', true)
            ->setHeader('Cache-Control', 'public, max-age=3600', true)
            ->setHeader('X-Feed-Rows', (string) max(0, (int) trim((string) shell_exec_safe($path))), true)
            ->setBody((string) file_get_contents($path));
    }

    public function buildAction(): void
    {
        $helper = Mage::helper('ainative_discovery');
        [$store] = $this->resolve();
        if (!$store || !$helper->isEnabled((int) $store->getId())) {
            $this->norouteAction();
            return;
        }
        if ($helper->cfg('feed_token') === '' || !$helper->checkFeedToken($this->getRequest())) {
            $this->getResponse()->setHttpResponseCode(401)->setBody('feed token required to trigger a rebuild');
            return;
        }
        $result = Mage::getModel('ainative_discovery/feed')->build($store);
        $this->getResponse()->setHeader('Content-Type', 'application/json', true)->setBody(json_encode(['store' => $store->getCode(), 'rows' => $result['products']]));
    }

    /** @return array{0: ?Mage_Core_Model_Store, 1: string} */
    private function resolve(): array
    {
        $raw = (string) $this->getRequest()->getParam('store', '');
        $format = 'json';
        if (preg_match('/^(.*)\.(json|csv)$/', $raw, $m)) {
            $raw = $m[1];
            $format = $m[2];
        }
        try {
            $store = $raw !== '' ? Mage::app()->getStore($raw) : Mage::app()->getStore();
        } catch (Throwable) {
            return [null, $format];
        }
        return [$store, $format];
    }
}

/** Count lines without loading the whole file in memory. */
function shell_exec_safe(string $path): string
{
    $n = 0;
    $h = fopen($path, 'r');
    if ($h) {
        while (fgets($h) !== false) {
            $n++;
        }
        fclose($h);
    }
    return (string) $n;
}
