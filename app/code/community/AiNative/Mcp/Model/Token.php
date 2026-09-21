<?php

/**
 * MCP access token. The secret is shown once; only its SHA-256 is stored.
 *
 * @method string getName()
 * @method int getAdminUserId()
 * @method string getScope()
 * @method string getTokenPrefix()
 * @license MIT
 */
class AiNative_Mcp_Model_Token extends Mage_Core_Model_Abstract
{
    public const PREFIX = 'omg_';

    protected function _construct(): void
    {
        $this->_init('ainative_mcp/token');
    }

    /**
     * Create a token and return the plaintext secret (never stored).
     */
    public function issue(string $name, int $adminUserId, string $scope, ?array $allowedTools, ?string $expiresAt): string
    {
        $secret = self::PREFIX . bin2hex(random_bytes(20));
        $this->setData([
            'name' => mb_substr($name, 0, 128),
            'admin_user_id' => $adminUserId,
            'token_hash' => hash('sha256', $secret),
            'token_prefix' => substr($secret, 0, 12),
            'scope' => $scope === AiNative_Core_Model_Tool_Context::SCOPE_WRITE ? 'write' : 'read',
            'allowed_tools' => $allowedTools ? json_encode(array_values($allowedTools)) : null,
            'is_active' => 1,
            'expires_at' => $expiresAt ?: null,
        ]);
        $this->save();
        return $secret;
    }

    public function loadBySecret(string $secret): self
    {
        if ($secret === '' || !str_starts_with($secret, self::PREFIX)) {
            return $this;
        }
        $this->load(hash('sha256', $secret), 'token_hash');
        return $this;
    }

    public function isUsable(): bool
    {
        if (!$this->getId() || !(int) $this->getData('is_active')) {
            return false;
        }
        $exp = $this->getData('expires_at');
        return !$exp || strtotime((string) $exp) > time();
    }

    /** @return string[] */
    public function getScopes(): array
    {
        return $this->getScope() === 'write' ? [AiNative_Core_Model_Tool_Context::SCOPE_READ, AiNative_Core_Model_Tool_Context::SCOPE_WRITE] : [AiNative_Core_Model_Tool_Context::SCOPE_READ];
    }

    /** @return string[]|null */
    public function getAllowedTools(): ?array
    {
        $raw = $this->getData('allowed_tools');
        if (!$raw) {
            return null;
        }
        $list = json_decode((string) $raw, true);
        return is_array($list) && $list ? array_values(array_map('strval', $list)) : null;
    }

    public function touch(string $ip): void
    {
        try {
            $this->getResource()->touch((int) $this->getId(), $ip);
        } catch (Throwable) {
        }
    }

    public function getAdminUser(): ?Mage_Admin_Model_User
    {
        $user = Mage::getModel('admin/user')->load((int) $this->getAdminUserId());
        return $user->getId() ? $user : null;
    }
}
