# Upgrade Guide

## 2.0.0 - March 17, 2026

### `padLength` renamed to `minimumLength` (breaking)

The package previously used `padLength` to represent the 2nd argument of `str_pad(...)` (the **minimum total length** of the numeric part). This has been renamed to `minimumLength` for clarity.

Update any code using named arguments or config objects/arrays:

- `NextRollNumber::create($name, $prefix, padLength: ...)` → `NextRollNumber::create($name, $prefix, minimumLength: ...)`
- `roll_number($name, $prefix, padLength: ...)` → `roll_number($name, $prefix, minimumLength: ...)`
- `new RollNumberConfig(..., padLength: ...)` → `new RollNumberConfig(..., minimumLength: ...)`
- `RollNumberConfig::from(['padLength' => ...])` → `RollNumberConfig::from(['minimumLength' => ...])`
