<?php

/**
 * AJAX endpoints for in-admin generation (product/category edit pages, order view).
 */
class AiNative_Copilot_Adminhtml_Ainative_CopilotController extends Mage_Adminhtml_Controller_Action
{
    public function generateAction(): void
    {
        $this->json(function (): array {
            $type = (string) $this->getRequest()->getParam('type', 'product');
            $id = (int) $this->getRequest()->getParam('id');
            $storeId = (int) $this->getRequest()->getParam('store', 0);
            $fields = array_filter(array_map('trim', explode(',', (string) $this->getRequest()->getParam('fields', 'description'))));
            $options = [
                'tone' => (string) $this->getRequest()->getParam('tone', '') ?: null,
                'hints' => (string) $this->getRequest()->getParam('hints', ''),
                'reference_product_id' => (int) $this->getRequest()->getParam('reference_product_id', 0) ?: null,
            ];
            $options = array_filter($options, fn($v) => $v !== null && $v !== '');
            $context = $this->context();
            $generator = Mage::getModel('ainative_copilot/generator');
            if ($type === 'category') {
                $this->assert('admin/catalog/categories');
                $category = Mage::getModel('catalog/category')->setStoreId($storeId)->load($id);
                if (!$category->getId()) {
                    throw new AiNative_Core_Exception('Category not found. Save it once before generating.');
                }
                $result = $generator->forCategory($category, $fields, $options, $context);
            } else {
                $this->assert('admin/catalog/products');
                $product = Mage::getModel('catalog/product')->setStoreId($storeId)->load($id);
                if (!$product->getId()) {
                    // unsaved product: build a transient one from posted name/sku
                    $product = Mage::getModel('catalog/product')->setStoreId($storeId)
                        ->setName((string) $this->getRequest()->getParam('name', ''))
                        ->setSku((string) $this->getRequest()->getParam('sku', ''))
                        ->setTypeId('simple')->setAttributeSetId((int) $this->getRequest()->getParam('set', 4));
                    if ($product->getName() === '') {
                        throw new AiNative_Core_Exception('Enter a product name first.');
                    }
                }
                $result = $generator->forProduct($product, $fields, $options, $context);
            }
            return ['fields' => $result['fields'], 'tokens' => $result['tokens_in'] + $result['tokens_out']];
        });
    }

    public function orderReplyAction(): void
    {
        $this->json(function (): array {
            $this->assert('admin/sales/order/actions/view');
            $order = Mage::getModel('sales/order')->load((int) $this->getRequest()->getParam('order_id'));
            if (!$order->getId()) {
                throw new AiNative_Core_Exception('Order not found.');
            }
            $intent = trim((string) $this->getRequest()->getParam('intent', ''));
            if ($intent === '') {
                throw new AiNative_Core_Exception('Describe what the reply should say.');
            }
            $result = Mage::getModel('ainative_copilot/generator')->orderReply($order, $intent, [], $this->context());
            return ['text' => $result['text'], 'tokens' => $result['tokens_in'] + $result['tokens_out']];
        });
    }

    public function searchProductsAction(): void
    {
        $this->json(function (): array {
            $this->assert('admin/catalog/products');
            $q = trim((string) $this->getRequest()->getParam('q', ''));
            $collection = Mage::getModel('catalog/product')->getCollection()->addAttributeToSelect(['name', 'sku'])->setPageSize(10);
            if ($q !== '') {
                $collection->addAttributeToFilter([['attribute' => 'name', 'like' => "%{$q}%"], ['attribute' => 'sku', 'like' => "%{$q}%"]]);
            }
            $items = [];
            foreach ($collection as $p) {
                $items[] = ['id' => (int) $p->getId(), 'label' => $p->getSku() . ' — ' . $p->getName()];
            }
            return ['items' => $items];
        });
    }

    private function context(): AiNative_Core_Model_Tool_Context
    {
        $user = Mage::getSingleton('admin/session')->getUser();
        return AiNative_Core_Model_Tool_Context::forAdmin('copilot', $user, [AiNative_Core_Model_Tool_Context::SCOPE_READ], (int) $this->getRequest()->getParam('store', 0));
    }

    private function assert(string $resource): void
    {
        if (!Mage::getSingleton('admin/session')->isAllowed($resource)) {
            throw new AiNative_Core_Exception('Access denied.');
        }
    }

    private function json(callable $fn): void
    {
        $response = $this->getResponse()->setHeader('Content-Type', 'application/json', true);
        if (!$this->_validateFormKey()) {
            $response->setHttpResponseCode(403)->setBody(json_encode(['error' => 'Invalid form key. Reload the page.']));
            return;
        }
        if (!Mage::helper('ainative_copilot')->isEnabled()) {
            $response->setHttpResponseCode(400)->setBody(json_encode(['error' => 'AI Copilot is disabled (AI Suite > Admin Copilot).']));
            return;
        }
        try {
            $response->setBody(json_encode($fn(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } catch (AiNative_Core_Exception $e) {
            $response->setHttpResponseCode(400)->setBody(json_encode(['error' => $e->getMessage()]));
        } catch (Throwable $e) {
            Mage::helper('ainative_core')->log('copilot error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()], Zend_Log::ERR);
            $response->setHttpResponseCode(500)->setBody(json_encode(['error' => 'Generation failed. See var/log/ainative.log.']));
        }
    }

    protected function _isAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('ainative/copilot');
    }
}
