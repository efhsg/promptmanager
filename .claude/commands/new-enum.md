---
allowed-tools: Read, Edit, Write
description: Create an enum following PromptManager patterns
---

# Create Enum

Create an enum following PromptManager patterns.

## Patterns

- Location: `yii/common/enums/<Name>.php`
- String-backed enums with meaningful values
- Include `label()` method for display
- Include static `values()` and `labels()` helpers

## Example Structure

```php
<?php

namespace common\enums;

enum <Name>: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Archived = 'archived';

    public static function values(): array
    {
        return array_map(static fn(self $type): string => $type->value, self::cases());
    }

    public static function labels(): array
    {
        $labels = [];
        foreach (self::cases() as $type) {
            $labels[$type->value] = $type->label();
        }

        return $labels;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::Inactive => 'Inactive',
            self::Archived => 'Archived',
        };
    }
}
```

## Usage in Models

```php
// In ActiveRecord - validation rule
public function rules(): array
{
    return [
        [['status'], 'in', 'range' => <Name>::values()],
    ];
}

// Casting to enum
public function getStatusEnum(): ?<Name>
{
    return $this->status ? <Name>::from($this->status) : null;
}
```

## Task

Create enum: $ARGUMENTS

Include unit test in `yii/tests/unit/common/enums/`
