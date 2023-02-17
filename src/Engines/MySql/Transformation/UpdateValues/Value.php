<?php

declare(strict_types=1);

namespace Driver\Engines\MySql\Transformation\UpdateValues;

class Value
{
    private string $field;
    private string $value;

    public function __construct(string $field, string $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
