<?php
namespace Lms\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $messages = [];
    private array $errors = [];
    private array $aliases = [];
    private array $validated = [];

    public function __construct(array $data, array $rules, array $messages = [], array $aliases = [])
    {
        $this->data     = $data;
        $this->rules    = $rules;
        $this->messages = $messages;
        $this->aliases  = $aliases;

        $this->validate();
    }

    public static function make(array $data, array $rules, array $messages = [], array $aliases = []): self
    {
        return new self($data, $rules, $messages, $aliases);
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function errorMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $messages[$field] = implode(', ', $fieldErrors);
        }
        return $messages;
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleSet) {
            $value = $this->data[$field] ?? null;
            $label = $this->aliases[$field] ?? $field;

            $this->validated[$field] = $value;

            $rules = is_string($ruleSet) ? explode('|', $ruleSet) : $ruleSet;

            foreach ($rules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $methodName = 'rule' . str_replace('_', '', ucwords($rule, '_'));
                if (method_exists($this, $methodName)) {
                    $error = $this->$methodName($field, $value, $label, $params);
                    if ($error) {
                        $this->errors[$field][] = $error;
                    }
                }
            }
        }
    }

    private function addError(string $field, string $message): void
    {
        $this->errors[$field][] = $message;
        unset($this->validated[$field]);
    }

    // ─── Rules ────────────────────────────────────────────────────────────

    private function ruleRequired(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return $this->message($field, 'required', "The {$label} field is required.");
        }
        return null;
    }

    private function ruleEmail(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return $this->message($field, 'email', "The {$label} must be a valid email address.");
        }
        return null;
    }

    private function ruleMin(string $field, mixed $value, string $label, array $params): ?string
    {
        $min = (int) ($params[0] ?? 0);
        if (is_string($value) && strlen($value) < $min) {
            return $this->message($field, 'min', "The {$label} must be at least {$min} characters.");
        }
        if (is_numeric($value) && (float) $value < $min) {
            return $this->message($field, 'min', "The {$label} must be at least {$min}.");
        }
        return null;
    }

    private function ruleMax(string $field, mixed $value, string $label, array $params): ?string
    {
        $max = (int) ($params[0] ?? 0);
        if (is_string($value) && strlen($value) > $max) {
            return $this->message($field, 'max', "The {$label} must not exceed {$max} characters.");
        }
        if (is_numeric($value) && (float) $value > $max) {
            return $this->message($field, 'max', "The {$label} must not exceed {$max}.");
        }
        return null;
    }

    private function ruleNumeric(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            return $this->message($field, 'numeric', "The {$label} must be a number.");
        }
        return null;
    }

    private function ruleInteger(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            return $this->message($field, 'integer', "The {$label} must be an integer.");
        }
        return null;
    }

    private function ruleString(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && !is_string($value)) {
            return $this->message($field, 'string', "The {$label} must be a string.");
        }
        return null;
    }

    private function ruleArray(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && !is_array($value)) {
            return $this->message($field, 'array', "The {$label} must be an array.");
        }
        return null;
    }

    private function ruleBoolean(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && !in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
            return $this->message($field, 'boolean', "The {$label} must be true or false.");
        }
        return null;
    }

    private function ruleIn(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !in_array((string) $value, $params, true)) {
            $allowed = implode(', ', $params);
            return $this->message($field, 'in', "The {$label} must be one of: {$allowed}.");
        }
        return null;
    }

    private function ruleDate(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && strtotime($value) === false) {
            return $this->message($field, 'date', "The {$label} is not a valid date.");
        }
        return null;
    }

    private function ruleUrl(string $field, mixed $value, string $label, array $params): ?string
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            return $this->message($field, 'url', "The {$label} must be a valid URL.");
        }
        return null;
    }

    private function ruleUnique(string $field, mixed $value, string $label, array $params): ?string
    {
        if (empty($value)) return null;

        [$table, $column] = $params;
        $except = $params[2] ?? null;

        $sql = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
        $queryParams = [$value];

        if ($except !== null) {
            $sql .= " AND id != ?";
            $queryParams[] = $except;
        }

        $stmt = Database::connection('lms')->prepare($sql);
        $stmt->execute($queryParams);

        if ((int) $stmt->fetchColumn() > 0) {
            return $this->message($field, 'unique', "The {$label} has already been taken.");
        }

        return null;
    }

    private function ruleConfirmed(string $field, mixed $value, string $label, array $params): ?string
    {
        $confirmationField = $field . '_confirmation';
        $confirmationValue = $this->data[$confirmationField] ?? null;

        if ($value !== $confirmationValue) {
            return $this->message($field, 'confirmed', "The {$label} confirmation does not match.");
        }
        return null;
    }

    private function ruleBetween(string $field, mixed $value, string $label, array $params): ?string
    {
        $min = (float) ($params[0] ?? 0);
        $max = (float) ($params[1] ?? PHP_FLOAT_MAX);

        if (is_string($value)) {
            $len = strlen($value);
            if ($len < $min || $len > $max) {
                return $this->message($field, 'between', "The {$label} must be between {$min} and {$max} characters.");
            }
        } elseif (is_numeric($value)) {
            $val = (float) $value;
            if ($val < $min || $val > $max) {
                return $this->message($field, 'between', "The {$label} must be between {$min} and {$max}.");
            }
        }
        return null;
    }

    private function message(string $field, string $rule, string $default): string
    {
        return $this->messages["{$field}.{$rule}"] ?? $this->messages[$field] ?? $default;
    }
}
