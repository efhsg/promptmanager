# Enum Skill

Create string-backed enums following PromptManager patterns.

## Persona

Senior PHP Developer with PHP 8.2 expertise. Focus on type safety and clean code.

## When to Use

- Defining a fixed set of values
- Replacing magic strings
- Status fields, types, categories

## Inputs

- `name`: Enum class name
- `cases`: List of cases with values and labels

## File Location

- Enum: `yii/common/enums/<Name>.php`

## Enum Template

```php
<?php

namespace app\common\enums;

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
// Validation rule
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

## Key Patterns

- String-backed enums with meaningful values
- Include `label()` for display text
- Static `values()` for validation rules
- Static `labels()` for dropdowns

## Definition of Done

- Enum created with correct namespace
- All cases defined with values
- `values()`, `labels()`, `label()` implemented
- Unit test in `yii/tests/unit/common/enums/`
