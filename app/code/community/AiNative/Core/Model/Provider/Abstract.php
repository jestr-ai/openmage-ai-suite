<?php

/**
 * Shared cURL transport, retries, config and tool-schema normalisation for providers.
 * @license MIT
 */
abstract class AiNative_Core_Model_Provider_Abstract implements AiNative_Core_Model_Provider_Interface
{
    protected ?int $storeId = null;

    abstract public function getCode(): string;

    public function setStoreId(?int $storeId): AiNative_Core_Model_Provider_Interface
    {
        $this->storeId = $storeId;
        return $this;
    }

    protected function helper(): AiNative_Core_Helper_Data
    {
        return Mage::helper('ainative_core');
    }

    protected function config(string $key): string
    {
        return (string) $this->helper()->getProviderConfig($this->getCode(), $key, $this->storeId);
    }

    public function getModel(): string
    {
        return $this->config('model');
    }

    protected function getApiKey(): string
    {
        return $this->helper()->getApiKey($this->getCode(), $this->storeId);
    }

    protected function getBaseUrl(): string
    {
        return rtrim($this->config('base_url'), '/');
    }

    public function isConfigured(): bool
    {
        return $this->getApiKey() !== '' && $this->getModel() !== '';
    }

    /**
     * Normalise tools into [{name, description, input_schema}] arrays.
     *
     * @return list<array{name:string,description:string,input_schema:array}>
     */
    protected function normalizeTools(array $tools): array
    {
        $out = [];
        foreach ($tools as $tool) {
            if ($tool instanceof AiNative_Core_Model_Tool_Interface) {
                $out[] = [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'input_schema' => $this->objectifySchema($tool->getInputSchema()),
                ];
            } elseif (is_array($tool) && isset($tool['name'])) {
                $out[] = [
                    'name' => (string) $tool['name'],
                    'description' => (string) ($tool['description'] ?? ''),
                    'input_schema' => $this->objectifySchema((array) ($tool['input_schema'] ?? ['type' => 'object', 'properties' => []])),
                ];
            }
        }
        return $out;
    }

    /**
     * Empty PHP arrays encode as JSON lists; every API requires `properties` (and nested objects) to be JSON objects.
     */
    protected function objectifySchema(array $schema): array
    {
        if (array_key_exists('properties', $schema)) {
            if (empty($schema['properties'])) {
                $schema['properties'] = new stdClass();
            } else {
                foreach ($schema['properties'] as $k => $v) {
                    if (is_array($v)) {
                        $schema['properties'][$k] = $this->objectifySchema($v);
                    }
                }
            }
        }
        if (isset($schema['items']) && is_array($schema['items'])) {
            $schema['items'] = $this->objectifySchema($schema['items']);
        }
        return $schema;
    }

    /**
     * POST JSON and decode. Retries on 408/409/429/5xx and transport errors with backoff.
     *
     * @param  array<string, string> $headers
     * @throws AiNative_Core_Exception
     */
    protected function postJson(string $url, array $body, array $headers): array
    {
        $helper = $this->helper();
        $payload = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($payload === false) {
            throw new AiNative_Core_Exception('Could not encode provider request: ' . json_last_error_msg());
        }
        $helper->debug(sprintf('[%s] POST %s', $this->getCode(), $url), $body);

        $hdr = ['Content-Type: application/json', 'Accept: application/json', 'User-Agent: OpenMage-AI-Suite/1.0 (+https://github.com/ainative/openmage-ai-suite)'];
        foreach ($headers as $k => $v) {
            $hdr[] = $k . ': ' . $v;
        }
        $timeout = $helper->getTimeout($this->storeId);
        $attempts = 0;
        $maxAttempts = 3;
        $lastError = '';
        $lastStatus = 0;
        while ($attempts < $maxAttempts) {
            $attempts++;
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => $hdr,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_FOLLOWLOCATION => false,
            ]);
            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $curlErr = curl_error($ch);
            curl_close($ch);

            if ($raw === false || $raw === '') {
                $lastError = 'transport error: ' . ($curlErr ?: 'empty response');
                $lastStatus = $status;
            } else {
                $decoded = json_decode((string) $raw, true);
                if ($status >= 200 && $status < 300 && is_array($decoded)) {
                    $helper->debug(sprintf('[%s] %d', $this->getCode(), $status), $decoded);
                    return $decoded;
                }
                $lastError = sprintf('HTTP %d: %s', $status, $helper->summarize(is_array($decoded) ? ($decoded['error'] ?? $decoded) : $raw, 600));
                $lastStatus = $status;
                // 529 is Anthropic's "overloaded"; 408/409/429 and 5xx are all worth another attempt.
                $retryable = in_array($status, [408, 409, 429, 529], true) || $status >= 500;
                if (!$retryable) {
                    break;
                }
            }
            if ($attempts < $maxAttempts) {
                usleep((int) (250000 * (2 ** ($attempts - 1)) + random_int(0, 100000)));
            }
        }
        $helper->log(sprintf('[%s] request failed after %d attempt(s): %s', $this->getCode(), $attempts, $lastError), null, Zend_Log::ERR);
        throw new AiNative_Core_Exception_Provider(
            sprintf('%s API error — %s', ucfirst($this->getCode()), $lastError),
            $lastStatus,
            $lastStatus === 0 || in_array($lastStatus, [408, 409, 429, 529], true) || $lastStatus >= 500,
        );
    }

    /**
     * Remove JSON-Schema keywords some providers reject; keep it a plain object schema.
     */
    protected function sanitizeSchema(array $schema, array $strip = []): array
    {
        foreach ($strip as $key) {
            unset($schema[$key]);
        }
        foreach ($schema as $k => $v) {
            if ($v instanceof stdClass) {
                continue;
            }
            if (is_array($v) && ($k === 'properties' || $k === 'items' || $k === 'anyOf' || $k === 'oneOf' || $k === 'allOf' || is_int($k) || isset($v['type']))) {
                $schema[$k] = $this->sanitizeSchema($v, $strip);
            }
        }
        return $schema;
    }

    protected function newCallId(): string
    {
        return 'call_' . bin2hex(random_bytes(8));
    }

    /**
     * Guard: decode arguments that may have arrived as a JSON string.
     */
    protected function decodeArguments(mixed $args): array
    {
        if (is_array($args)) {
            return $args;
        }
        if (is_string($args) && $args !== '') {
            $decoded = json_decode($args, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
