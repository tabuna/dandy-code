# Управляющие конструкции

В живом проекте требования неизменно растут. Это нормально: бизнес двигается, пользователи чего-то хотят, а нам приходится подкручивать код, чтобы он всё это выдержал. Но вместе с требованиями растёт и сложность. И одна из самых тихих, но опасных зон роста — это управляющие конструкции.

Они сначала выглядят безобидно. Один `if`, один `while`, пара сравнений. Всё понятно. Но потом приходит ещё один параметр. Потом фильтр. Потом проверка статуса, даты, порогового значения. И вроде бы всё ещё нормально — но код уже не читается.

Посмотрим на конкретный пример:

```php
while (File::where('status', '=', File::STATUS_NEW)->count()) {
   // ...
}
```
На первый взгляд всё просто и понятно: пока есть новые файлы — продолжаем обработку. Всё логично.

Через пару недель появляется задача обработать только файлы, связанные с определённым событием:
```php
while (File::where('event_guid', '=', $event->document_id)->where('status', '=', File::STATUS_NEW)->count()) {
   // ...
}
```

А ещё через день кто-то убирает лишние `=` из условий. Или добавляет:

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
// Хорошо ✅
while (true) {
    $count = File::where('event_guid', $event->document_id)
        ->where('status', File::STATUS_NEW)
        ->count();

    if ($count <= $secret) {
        break;
    }

    // ...
}
```


А ещё лучше спрятать проверку как только условие перестаёт быть тривиальным — вынести его в отдельный метод с говорящим именем:

```php
// Хорошо ✅
while ($this->hasTooManyNewFiles()) {
    // ...
}
```

И где-нибудь в коде:

```php
private function newFilesQuery(): bool
{
    return File::where('event_guid', $this->event->document_id)
        ->where('status', File::STATUS_NEW);
}

private function hasTooManyNewFiles(Event $event): bool
{
    return $this->newFilesQuery()->count() > $this->threshold();
}
```


### Избегай "мудреных" решений

Сложные условия не всегда находятся только в if или while.
Иногда они маскируются под "краткость" — особенно в тернарных или null coalesce-выражениях.

```php
// Плохо ❌ 
return $cache ?: ($computed ?: $default);
```

```php
// Хорошо ✅
if ($cache) {
    return $cache;
}

if ($computed) {
    return $computed;
}

return $default;
```


> TODO: сделать и рассказать про if($name = $this->getName()) { ... } и про то, что это не хорошо
