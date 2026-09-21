<?php

/**
 * Minimal JSON-Schema validator for tool arguments (type, required, enum, min/max, items, properties).
 * Coerces numeric strings and boolean-ish strings, since models sometimes send those.
 * @license MIT
 */
class AiNative_Core_Model_Tool_Schema
{
    /**
     * @return array validated + coerced arguments
     * @throws AiNative_Core_Exception
     */
    public function validate(array $schema, mixed $args, string $path = 'arguments'): array
    {
        if (!is_array($args)) {
            throw new AiNative_Core_Exception("{$path} must be an object");
        }
        $props = (array) ($schema['properties'] ?? []);
        $required = (array) ($schema['required'] ?? []);
        foreach ($required as $key) {
            if (!array_key_exists($key, $args) || $args[$key] === null || $args[$key] === '') {
                throw new AiNative_Core_Exception("Missing required argument \"{$key}\"");
            }
        }
        $out = [];
        foreach ($args as $key => $value) {
            if (!isset($props[$key])) {
                if (($schema['additionalProperties'] ?? true) === false) {
                    continue; // silently drop unknown keys rather than fail the model
                }
                $out[$key] = $value;
                continue;
            }
            $out[$key] = $this->value((array) $props[$key], $value, "{$path}.{$key}");
        }
        return $out;
    }

    private function value(array $schema, mixed $value, string $path): mixed
    {
        if ($value === null) {
            return null;
        }
        $type = $schema['type'] ?? null;
        if (is_array($type)) {
            $type = in_array('null', $type, true) && $value === null ? 'null' : ($type[0] ?? null);
        }
        switch ($type) {
            case 'string':
                if (is_array($value)) {
                    throw new AiNative_Core_Exception("{$path} must be a string");
                }
                $value = (string) $value;
                if (isset($schema['maxLength']) && mb_strlen($value) > (int) $schema['maxLength']) {
                    $value = mb_substr($value, 0, (int) $schema['maxLength']);
                }
                break;
            case 'integer':
                if (!is_numeric($value)) {
                    throw new AiNative_Core_Exception("{$path} must be an integer");
                }
                $value = (int) $value;
                break;
            case 'number':
                if (!is_numeric($value)) {
                    throw new AiNative_Core_Exception("{$path} must be a number");
                }
                $value = (float) $value;
                break;
            case 'boolean':
                $b = is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
                if ($b === null) {
                    throw new AiNative_Core_Exception("{$path} must be a boolean");
                }
                $value = $b;
                break;
            case 'array':
                if (!is_array($value)) {
                    $value = [$value];
                }
                $value = array_values($value);
                if (isset($schema['maxItems']) && count($value) > (int) $schema['maxItems']) {
                    $value = array_slice($value, 0, (int) $schema['maxItems']);
                }
                if (isset($schema['items'])) {
                    foreach ($value as $i => $item) {
                        $value[$i] = $this->value((array) $schema['items'], $item, "{$path}[{$i}]");
                    }
                }
                break;
            case 'object':
                if (!is_array($value)) {
                    throw new AiNative_Core_Exception("{$path} must be an object");
                }
                $value = $this->validate($schema, $value, $path);
                break;
        }
        if (isset($schema['enum']) && !in_array($value, (array) $schema['enum'], false)) {
            throw new AiNative_Core_Exception(sprintf('%s must be one of: %s', $path, implode(', ', array_map('strval', (array) $schema['enum']))));
        }
        if (isset($schema['minimum']) && is_numeric($value) && $value < $schema['minimum']) {
            $value = $schema['minimum'];
        }
        if (isset($schema['maximum']) && is_numeric($value) && $value > $schema['maximum']) {
            $value = $schema['maximum'];
        }
        return $value;
    }
}
