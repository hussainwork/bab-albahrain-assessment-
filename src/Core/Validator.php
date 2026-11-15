<?php

declare(strict_types=1);

namespace App\Core;

final class Validator
{
    private array $errors = [];

    public function __construct(private readonly array $input)
    {
    }

    public function value(string $field): string
    {
        $value = $this->input[$field] ?? '';

        return is_scalar($value) ? trim((string) $value) : '';
    }

    public function required(string $field): self
    {
        if ($this->value($field) === '') {
            $this->errors[$field] ??= t('validation.required');
        }

        return $this;
    }

    public function maxLength(string $field, int $max): self
    {
        if (mb_strlen($this->value($field)) > $max) {
            $this->errors[$field] ??= t('validation.too_long', ['max' => $max]);
        }

        return $this;
    }

    public function minLength(string $field, int $min): self
    {
        if ($this->value($field) !== '' && mb_strlen($this->value($field)) < $min) {
            $this->errors[$field] ??= t('validation.too_short', ['min' => $min]);
        }

        return $this;
    }

    public function email(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->errors[$field] ??= t('validation.email');
        }

        return $this;
    }

    public function bahrainMobile(string $field): self
    {
        $value = $this->value($field);
        if ($value !== '' && !preg_match('/^[136]\d{7}$/', self::normaliseMobile($value))) {
            $this->errors[$field] ??= t('validation.mobile');
        }

        return $this;
    }

    public function integerBetween(string $field, int $min, int $max): self
    {
        $value = filter_var($this->value($field), FILTER_VALIDATE_INT);
        if ($value === false || $value < $min || $value > $max) {
            $this->errors[$field] ??= t('validation.between', ['min' => $min, 'max' => $max]);
        }

        return $this;
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public static function normaliseMobile(string $mobile): string
    {
        $digits = preg_replace('/\D+/', '', $mobile) ?? '';

        return str_starts_with($digits, '973') && strlen($digits) === 11 ? substr($digits, 3) : $digits;
    }
}
