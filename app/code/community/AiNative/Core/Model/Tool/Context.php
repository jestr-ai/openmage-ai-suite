<?php

/**
 * Who is calling, from where, with which rights.
 * @license MIT
 */
final class AiNative_Core_Model_Tool_Context
{
    public const ACTOR_ADMIN = 'admin';
    public const ACTOR_CUSTOMER = 'customer';
    public const ACTOR_GUEST = 'guest';
    public const ACTOR_SYSTEM = 'system';

    public const SCOPE_READ = 'read';
    public const SCOPE_WRITE = 'write';

    private string $requestId;
    private array $meta = [];

    /**
     * @param string[] $scopes
     */
    public function __construct(
        private readonly string $channel,
        private readonly string $actorType,
        private readonly ?int $actorId,
        private readonly string $actorLabel,
        private readonly int $storeId,
        private readonly array $scopes = [self::SCOPE_READ],
        private readonly ?Mage_Admin_Model_User $adminUser = null,
        private readonly ?Mage_Customer_Model_Customer $customer = null,
        ?string $requestId = null,
    ) {
        $this->requestId = $requestId ?: Mage::helper('ainative_core')->newRequestId();
    }

    public static function forAdmin(string $channel, Mage_Admin_Model_User $user, array $scopes, ?int $storeId = null, ?string $requestId = null): self
    {
        return new self(
            $channel,
            self::ACTOR_ADMIN,
            (int) $user->getId(),
            (string) $user->getUsername(),
            $storeId ?? Mage_Core_Model_App::ADMIN_STORE_ID,
            $scopes,
            $user,
            null,
            $requestId,
        );
    }

    public static function forCustomer(string $channel, Mage_Customer_Model_Customer $customer, int $storeId, ?string $requestId = null): self
    {
        return new self(
            $channel,
            self::ACTOR_CUSTOMER,
            (int) $customer->getId(),
            'customer#' . $customer->getId(),
            $storeId,
            [self::SCOPE_READ],
            null,
            $customer,
            $requestId,
        );
    }

    public static function forGuest(string $channel, int $storeId, string $label = 'guest', ?string $requestId = null): self
    {
        return new self($channel, self::ACTOR_GUEST, null, $label, $storeId, [self::SCOPE_READ], null, null, $requestId);
    }

    public static function forSystem(string $channel, ?int $storeId = null): self
    {
        return new self($channel, self::ACTOR_SYSTEM, null, 'system', $storeId ?? Mage_Core_Model_App::ADMIN_STORE_ID, [self::SCOPE_READ, self::SCOPE_WRITE]);
    }

    public function getChannel(): string
    {
        return $this->channel;
    }

    public function getActorType(): string
    {
        return $this->actorType;
    }

    public function getActorId(): ?int
    {
        return $this->actorId;
    }

    public function getActorLabel(): string
    {
        return $this->actorLabel;
    }

    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function isAdmin(): bool
    {
        return $this->actorType === self::ACTOR_ADMIN;
    }

    public function getAdminUser(): ?Mage_Admin_Model_User
    {
        return $this->adminUser;
    }

    public function getCustomer(): ?Mage_Customer_Model_Customer
    {
        return $this->customer;
    }

    public function hasScope(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }

    public function canWrite(): bool
    {
        return $this->hasScope(self::SCOPE_WRITE);
    }

    public function getRequestId(): string
    {
        return $this->requestId;
    }

    public function withMeta(string $key, mixed $value): self
    {
        $this->meta[$key] = $value;
        return $this;
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return $this->meta[$key] ?? $default;
    }

    /** Actor key for rate limiting */
    public function getRateKey(): string
    {
        return $this->actorType . ':' . ($this->actorId ?? $this->actorLabel);
    }
}
