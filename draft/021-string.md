## Работа со строками

Строки — один из самых распространённых типов данных в PHP. 
Но именно из-за частоты их использования легко скатиться в запутанный и плохо читаемый код. 
Рассмотрим три типичных ошибки и способы их избежать.

Часто встречающийся пример:

```php
// Плохо [✗]
echo strtoupper(trim(substr($input, 0, 10)));
```

Код работает, но превращается в «матрёшку функций». 
Чтение требует разбирать его справа налево, и при малейшем изменении логики всё рассыпается.

**Лучший подход** — использовать объекты или специализированные классы для работы со строками:

```php
// Хорошо [✓]
class Text {
    public function __construct(private string $value) {}

    public function cut(int $length): self {
        $this->value = substr($this->value, 0, $length);
        return $this;
    }

    public function trim(): self {
        $this->value = trim($this->value);
        return $this;
    }

    public function upper(): self {
        $this->value = strtoupper($this->value);
        return $this;
    }

    public function value(): string {
        return $this->value;
    }
}

echo (new Text($input))
    ->cut(10)
    ->trim()
    ->upper()
    ->value();
```

Теперь мы читаем цепочку шагов, а не пытаемся расшифровать вложенные функции.

### Конкатенация строк

Конкатенация с помощью `.` выглядит невинно, но быстро теряет читаемость:

```php
// Плохо [✗]
$message = 'Hello, ' . $name . '! Today is ' . date('Y-m-d');
```

С ростом количества переменных строка становится трудночитаемой.

**Лучше использовать `sprintf`:**

```php
// Хорошо [✓]
$message = sprintf(
    'Hello, %s! Today is %s',
    $name,
    date('Y-m-d')
);
```

Такой код проще читать, поддерживать и переводить.

### Регулярные выражения

Регулярки часто превращаются в «магические строки»:

```php
// Плохо [✗]
if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $matches)) {
    $year  = $matches[1];
    $month = $matches[2];
    $day   = $matches[3];
}
```

Через месяц уже непонятно, что означает каждая группа.

**Используем именованные группы для читаемости:**

```php
// Хорошо [✓]
if (preg_match(
    '/^(?<year>\d{4})-(?<month>\d{2})-(?<day>\d{2})$/',
    $date,
    $matches
)) {
    $year  = $matches['year'];
    $month = $matches['month'];
    $day   = $matches['day'];
}
```

Теперь регулярное выражение документирует себя само.