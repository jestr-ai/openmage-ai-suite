<?php

class AiNative_Mcp_Model_Tool_Sales_AddOrderComment extends AiNative_Mcp_Model_Tool_Sales_GetOrder
{
    public function getName(): string
    {
        return 'add_order_comment';
    }

    public function getDescription(): string
    {
        return 'Add a comment to an order\'s history, optionally changing its status (must be a status valid for the order\'s current state) and optionally emailing the comment to the customer. WRITE tool — confirm with the user first.';
    }

    public function getInputSchema(): array
    {
        return $this->schema([
            'order_number' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'comment' => ['type' => 'string', 'maxLength' => 4000],
            'status' => ['type' => 'string', 'description' => 'New status code, or omit to keep current.'],
            'notify_customer' => ['type' => 'boolean', 'description' => 'Send the order update email. Default false.'],
            'visible_on_front' => ['type' => 'boolean', 'description' => 'Show in customer account. Default false.'],
        ], ['comment']);
    }

    public function getAclResource(): ?string
    {
        return 'admin/sales/order/actions/comment';
    }

    public function isWrite(): bool
    {
        return true;
    }

    public function execute(array $args, AiNative_Core_Model_Tool_Context $context): array
    {
        $order = $this->loadOrder($args);
        $comment = $this->str($args, 'comment');
        $status = $this->str($args, 'status') ?: $order->getStatus();
        if ($status !== $order->getStatus()) {
            $allowed = $order->getConfig()->getStateStatuses($order->getState());
            if (!isset($allowed[$status])) {
                $this->fail(sprintf('Status "%s" is not valid for state "%s". Valid: %s', $status, $order->getState(), implode(', ', array_keys($allowed))));
            }
        }
        $notify = $this->bool($args, 'notify_customer');
        $history = $order->addStatusHistoryComment($comment, $status);
        $history->setIsVisibleOnFront($this->bool($args, 'visible_on_front') ? 1 : 0);
        $history->setIsCustomerNotified($notify ? 1 : 0);
        $order->save();
        if ($notify) {
            try {
                $order->sendOrderUpdateEmail(true, $comment);
            } catch (Throwable $e) {
                return ['order_number' => $order->getIncrementId(), 'status' => $order->getStatus(), 'comment_added' => true, 'email_sent' => false, 'email_error' => $e->getMessage()];
            }
        }
        Mage::helper('ainative_core')->log(sprintf('add_order_comment %s by %s', $order->getIncrementId(), $context->getActorLabel()));
        return ['order_number' => $order->getIncrementId(), 'status' => $order->getStatus(), 'comment_added' => true, 'email_sent' => $notify];
    }
}
