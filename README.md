# Ballistics Drag Functions PHP Library

## Usage

```php
use ballistics\Ballistics;

$ballistics = new Ballistics();
$ballistics->getRangeData($weather, $target, $firearm, $round)
```
## Functions

`getRangeData($weather, $target, $firearm, $round)` - Returns an array of 'Range' objects each of which represents a row on the ballistics table
