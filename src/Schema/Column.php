<?php

declare(strict_types=1);

namespace Eccube2\Migration\Schema;

class Column
{
    private string $name;
    private string $type;
    private array $options;
    private bool $nullable = true;
    private bool $isPrimary = false;
    private $default = null;
    private bool $hasDefault = false;

    public function __construct(string $name, string $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getOption(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    public function isNullable(): bool
    {
        return $this->nullable;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function hasDefault(): bool
    {
        return $this->hasDefault;
    }

    public function getDefault()
    {
        return $this->default;
    }

    // =====================
    // Modifiers (Fluent Interface)
    // =====================

    public function nullable(): self
    {
        $this->nullable = true;
        return $this;
    }

    public function notNull(): self
    {
        $this->nullable = false;
        return $this;
    }

    public function primary(): self
    {
        $this->isPrimary = true;
        $this->nullable = false;
        return $this;
    }

    public function default($value): self
    {
        $this->hasDefault = true;
        $this->default = $value;
        return $this;
    }

    public function unsigned(): self
    {
        $this->options['unsigned'] = true;
        return $this;
    }
}
