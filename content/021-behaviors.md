# Заметки

> TODO: Далее идут заметки, что еще нужно и будет добавлено лаконично в разные главы.

### Поведение живёт не там, где нужно

Разработчики стремятся "решить всё сразу": вызвать сервис, сохранить данные, отправить уведомление, обновить кэш,
залогировать событие. Это создаёт жёсткие зависимости, смешение уровней абстракции и трудности в модификации поведения.

```php
// Плохо ❌
class PostController 
{
    public function publish(Post $post): void
    {
        // бизнес-логика
        $post->published_at = now();
        $post->save();

        // побочные эффекты - отправка почты, очистка кеша, логирование
        Mail::to($post->author)->send(new PostPublishedMail($post));
        Cache::forget("post.{$post->id}");
        Log::info("Post published", ['id' => $post->id]);
    }
}
```

На первый взгляд, метод кажется эффективным: он делает всю работу. 
Но это — иллюзия контроля. Проблемы появляются сразу:

- Метод трудно протестировать: для каждого действия нужны свои моки.
- Метод трудно переиспользовать: нельзя вызвать только часть логики.
- Метод трудно изменять: добавление нового действия требует вмешательства в основной код.


Если поведение не является ядром предметной области, оно должно быть:

- изолировано,
- абстрагировано,
- либо делегировано.

Всё лишнее должно быть вынесено в отдельные компоненты, которые можно включить или исключить независимо от основной бизнес-логики.

### Action-класс

Можно выделать логику в отдельный класс — одна задача = один класс.
Это удобно для повторного использования, тестирования и упрощения контроллера.

Повторное использование (например, в контроллерах, событиях, задачах)

```php
namespace App\Actions\Post;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\PostPublishedMail;

class PublishPost
{
    public function execute(Post $post): void
    {
        $post->published_at = now();
        $post->save();

        Mail::to($post->author)->send(new PostPublishedMail($post));

        Cache::forget("post.{$post->id}");

        Log::info("Post published", ['id' => $post->id]);
    }
}
```

и затем уже этот класс можно использовать как контроллере, консольной команде, отложенной задаче или в тесте:

```php
public function publish(Post $post, PublishPost $action)
{
    $action->execute($post);
}
```

> Многие фреймворки самостоятельно внедрят PublishPost через контейнер, если используешь метод-контроллер с DI.

### Декоратор

Одним из решением являеться "Декоратор" который позволяет расширять поведение без модификации существующего кода.

```php
interface Publisher
{
    public function publish(Post $post): void;
}

class CorePublisher implements Publisher
{
    public function publish(Post $post): void
    {
        $post->published_at = now();
        $post->save();
    }
}


class LoggingPublisher implements Publisher
{
    public function __construct(private Publisher $inner) {}

    public function publish(Post $post): void
    {
        $this->inner->publish($post);
        Log::info("Post published", ['id' => $post->id]);
    }
}


class NotifyingPublisher implements Publisher
{
    public function __construct(private Publisher $inner) {}

    public function publish(Post $post): void
    {
        $this->inner->publish($post);
        Mail::to($post->author)
            ->send(new PostPublishedMail($post));
    }
}
```

Теперь поведение настраивается через композицию:

```php
$publisher = new NotifyingPublisher(
    new LoggingPublisher(
        new CorePublisher()
    )
);
```


Теперь мы имеет изолированное, поведение которое легко протестировать или изменить порядок.


### События

Другой подход — отделение побочного поведения через события. Мы сигнализируем, что нечто произошло, и позволяем другим компонентам реагировать.

```php
class PostService
{
    public function publish(Post $post): void
    {
        $post->published_at = now();
        $post->save();

        event(new PostPublished($post));
    }
}
```


Обработчики подписываются на событие `PostPublished` и выполняют свои задачи:

```php
class SendPostPublishedNotification
{
    public function handle(PostPublished $event): void
    {
        Mail::to($event->post->author)
            ->send(new PostPublishedMail($event->post));
    }
}
```

При таком подходе мы можем расширять поведение без модификации исходного кода, и компоненты остаются слабо связанными.


### Фундаментальное правило CQS

Метод либо **команда** — меняет состояние, либо **запрос** — возвращает данные, но не совмещает оба действия.

### Инструменты обеспечения качества в PHP

1. **PHPStan / Psalm** — статический анализ кода, выявление ошибок типов и потенциальных дефектов на этапе разработки.
2. **PHP\_CodeSniffer (PHPCS)** — анализ соответствия кода стандартам форматирования (например, PSR-12).
3. **PHP-CS-Fixer** — автоматическое исправление форматирования кода согласно заданным правилам.
4. **PHPUnit** — фреймворк для модульного тестирования.
5. **Infection** — инструмент для мутационного тестирования, оценивающий эффективность тестов.
6. **phpmetrics** — генерация отчётов о метриках качества кода (сложность, связность, покрытия и др.).
7. **phploc** — сбор статистики по структуре и объёму проекта (строки кода, методы, классы и т. д.).
8. **Deptrac** — анализ архитектурных зависимостей между слоями приложения.
9. **Rector** — инструмент для автоматизированного рефакторинга и миграции кода.
10. **Composer Audit** — проверка зависимостей проекта на наличие известных уязвимостей.
