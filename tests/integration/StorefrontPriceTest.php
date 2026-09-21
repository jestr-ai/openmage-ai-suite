<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Grouped/bundle products have no own price; the card must use the price index, never 0.00.
 */
final class StorefrontPriceTest extends TestCase
{
    public function testGroupedProductCardShowsIndexedFromPrice(): void
    {
        $store = Mage::app()->getStore(1);
        $collection = Mage::getResourceModel('catalog/product_collection')->setStoreId(1)
            ->addAttributeToSelect(['name', 'type_id', 'small_image', 'short_description'])
            ->addAttributeToFilter('type_id', Mage_Catalog_Model_Product_Type::TYPE_GROUPED)
            ->addPriceData(Mage_Customer_Model_Group::NOT_LOGGED_IN_ID, (int) $store->getWebsiteId())
            ->setPageSize(1);
        $product = $collection->getFirstItem();
        if (!$product->getId()) {
            self::markTestSkipped('no grouped product in this catalog');
        }
        $tool = new AiNative_Assistant_Model_Tool_SearchCatalog();
        $method = new ReflectionMethod($tool, 'productCard');
        $method->setAccessible(true);
        $card = $method->invoke($tool, $product, $store);
        self::assertNotSame('$0.00', $card['price'], 'grouped products must not be priced at zero');
        self::assertTrue($card['price_is_from'], 'grouped product prices are "from" prices');
        self::assertMatchesRegularExpression('/[1-9]/', $card['price'], 'price must carry a non-zero amount');
    }
}
