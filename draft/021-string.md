## Работа со строками

Даже для простых типов данных — строк и чисел — стоит использовать объекты.
Мы уже видели пример класса `Temperature`, который скрывает от нас работу с единицами измерения.
Теперь посмотрим на три типичные ошибки при работе со строками и способы их избежать.

Часто обработку строки записывают через вложенные вызовы:
```php
// Плохо [✗]
echo strtoupper(trim(substr($input, 0, 10)));
```

Код работает, но превращается в «матрёшку функций» из-за их чрезмерного количества вложенных функций.
Читать его приходится справа налево, что совсем не свойственно для латиницы на которой мы пишем код.
Это снова увеличивает когнитивную нагрузку и скрывает намерение.

Для чтения удобнее сделать, класс:

```php
// Хорошо [✓]
// Хорошо [✓]
class Text implements Stringable {
    public function __construct(private string $value) {}

    public function cut(int $length): static 
    {
        if ($length < 0) {
            throw new \InvalidArgumentException('Length must be non-negative.');
        }
        
        return new static(substr($this->value, 0, $length));
    }

    public function trim(): static 
    {
        return new static(trim($this->value));
    }

    public function upper(): static 
    {
        return new static(strtoupper($this->value));
    }

    public function value(): string 
    {
        return $this->value;
    }
    
    public function __toString(): string
    {
        return $this->value();
    }
}
```

С таким классом обработка становится читаемой и выразительной:

```php
echo (new Text($input))
    ->cut(10)
    ->trim()
    ->upper()
    ->value();
```

Теперь мы читаем цепочку шагов, а не пытаемся расшифровать вложенные функции.

> **Обратите внимание на иммутабельность.**
> Каждый шаг возвращает новый объект при котором не будет скрытых побочных эффектов.


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
