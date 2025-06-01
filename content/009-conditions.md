# Условия

В живом проекте требования неизменно растут, а вместе с ними и сложность кода.
Особенно это видно в длинных условиях внутри `if` или `while`. 
Давайте разберём несколько вариантов, чтобы понять, как сделать такие участки действительно удобными для чтения, отладки и изменения.

Есть следующий фрагмент:

```php
while (File::where('status', '=', File::STATUS_NEW)->count()) {
   // ...
}
```

На первый взгляд всё в порядке: мы берём блокировку и входим в цикл, пока есть новые файлы. 

Затем к нему дописывается еще условие:

```php
while (File::where('event_guid', '=', $event->document_id)->where('status', '=', File::STATUS_NEW)->count()) {
   // ...
}
```

Потом еще, немного подправиться:
Но что случится, если завтра нужно изменить одно сравнение:

```diff
-while (File::query()->where('event_guid', '=', $event->document_id)->where('status', '=', File::STATUS_NEW)->count()) {
+while (File::query()->where('event_guid', $event->document_id)->where('status', File::STATUS_NEW)->count()) {
       // ...
}
```

Такой `diff` неудобно читать: сложно сразу заметить, что именно поменялось. 
Вам не хочется разбираться, что конкретно изменилось, только побыстрее пройти мимо. 

Но и более развернутый вариант с переносами строк — лишь чуть улучшает diff, но не облегчает жизнь при отладке:

```php
while (
    File::where('event_guid', $event->document_id)
        ->where('status', File::STATUS_NEW)
        ->count()
) {
    // ...
}
```

Допустим мы видим эту часть кода, как нам узнать сколько записей вернулось? 
Придётся скопировать весь запрос, передать его в `dd()` или функцию логирования.

А если условие будет не просто наличие не равное 0, а больше порогового значения из другого метода.
Тогда нам нужно будет копировать уже дважды:

```php
$count = File::where('event_guid', $event->document_id)
    ->where('status', File::STATUS_NEW)
    ->count();

dd([
    'count'  => $count,
    'secret' => $secret,
]);

while (
    File::query()
        ->where('event_guid', $event->document_id)
        ->where('status', File::STATUS_NEW)
        ->count()
        >= $secret
) {
    // ...
}
```

~~Вместо этого воспользуемся ранним выходом с которым мы познакомились ранее и вынесем условие:~~

```php
while (true) {
    $count = File::where('event_guid', $event->document_id)
        ->where('status', File::STATUS_NEW)
        ->count();

    if ($count =< $secret) {
        break;
    }

    // ...
}
```


### Избегай "мудреных" решений


```php
// Плохо ❌ 
return $cache ?: ($compute ?: $default);
```

```php
// Хорошо ✅
if ($cache) {
    return $cache;
}

if ($computed) {
    return $compute;
}

return $default;
```
