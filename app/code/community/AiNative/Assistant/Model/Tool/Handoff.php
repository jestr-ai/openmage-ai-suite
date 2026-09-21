<?php

class AiNative_Assistant_Model_Tool_Handoff extends AiNative_Assistant_Model_Tool_Abstract
{
    public function getName(): string
    {
        return 'handoff';
    }

    public function getDescription(): string
    {
        return 'Hand the shopper to a human: returns the store\'s contact page link (and phone/e-mail if published). Use when you cannot help, the shopper asks for a person, or the request involves refunds, payments, account changes or complaints.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'reason' => ['type' => 'string', 'description' => 'Short internal reason (logged for the merchant).'],
        ]);
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $store = $this->store($context);
        $helper = Mage::helper('ainative_assistant');
        $context->withMeta('handoff', true);
        return [
            'contact_url' => $helper->getContactUrl((int) $store->getId()),
            'phone' => Mage::getStoreConfig('general/store_information/phone', $store) ?: null,
            'email' => Mage::getStoreConfig('trans_email/ident_support/email', $store) ?: null,
            'reason' => $this->str($args, 'reason'),
        ];
    }
}
