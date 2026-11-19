# SEO Auditor (`seo-checker`)

Консольний SEO-аудитор веб-сторінок на чистому PHP. Пакет завантажує HTML за URL, прогоняє його через 10 незалежних правил і виводить зрозумілий звіт у терміналі.

Архітектура розширювана за патерном **Strategy**: кожна перевірка — окремий клас, що реалізує `RuleInterface`. Щоб додати нову перевірку, ядро змінювати не потрібно.

## Вимоги

- PHP 8.1+
- розширення `dom`, `mbstring`
- розширення `curl` (рекомендовано; без нього використовуються PHP streams)
- Composer 2

## Встановлення

```bash
git clone https://github.com/Aratornv1/SEO-Auditor.git
cd SEO-Auditor
composer install
```

### Підключення як локального пакета в іншому проєкті

У `composer.json` цільового проєкту додайте репозиторій типу `path`:

```json
{
  "repositories": [
    { "type": "path", "url": "../SEO-Auditor" }
  ],
  "require": {
    "aratornv1/seo-checker": "*@dev"
  }
}
```

Потім виконайте `composer update aratornv1/seo-checker`. Composer створить символічне посилання на пакет, а команда буде доступна як `vendor/bin/seo-check`.

## Використання

```bash
php bin/seo-check https://example.com
```

На Linux/macOS файл має права на виконання, тож можна запускати і так: `./bin/seo-check https://example.com`. Схему можна не вказувати: `seo-check example.com` автоматично стане `https://example.com`. За раз перевіряється одна адреса: другий URL дає помилку. Щоб передати адресу, що починається з `-`, поставте перед нею `--`.

| Опція | Призначення |
|-------|-------------|
| `--max-size=N` | Відхиляти сторінки більші за N мегабайт (за замовчуванням `2`, максимум `100`, допускаються дробові значення, наприклад `0.5`). Підходить і запис `--max-size 3` |
| `--no-emoji` | Текстові маркери `[ OK ] [WARN] [FAIL]` замість іконок |
| `--no-color` | Вимкнути ANSI-кольори (вони й так вимикаються, якщо вивід не в терміналі або задано `NO_COLOR`) |
| `-h`, `--help` | Довідка |

Код виходу: `0` — помилок немає (попередження допускаються), `1` — є хоча б одна `error`, `2` — неправильне використання або сторінку не вдалося завантажити. Це зручно для CI.

Приклад звіту для сторінки з проблемами:

```
SEO-аудит: http://127.0.0.1:8099/bad.html

 ⚠️  Title              Заголовок занадто короткий (4 символи, рекомендовано 30–60)
 ⚠️  Meta description   Мета-тег description відсутній
 ⚠️  H1                 Знайдено 2 теги <h1>, має бути один
 ⚠️  Heading hierarchy  Пропущено рівні заголовків: h2 → h4
 ❌ Image alt          Зображень без атрибута alt: 2 із 3
 ⚠️  Canonical          Тег <link rel="canonical"> відсутній
 ❌ Robots meta        Сторінку закрито від індексації (noindex у meta robots)
 ❌ Viewport           Мета-тег viewport відсутній (сторінка не адаптована під мобільні)
 ⚠️  Open Graph         Open Graph не налаштовано (немає og:title, og:description, og:image)
 ⚠️  HTML lang          Атрибут lang у тегу <html> відсутній або порожній

Підсумок: 0 ✅ · 7 ⚠️ · 3 ❌
```

Статуси: ✅ `success` — усе гаразд, ⚠️ `warning` — варто покращити, ❌ `error` — серйозна проблема.

## Архітектура

```
src/
├── RuleInterface.php          контракт правила (Strategy)
├── Analyzer.php               ядро: addRule(), завантаження HTML, запуск правил
├── HtmlProviderInterface.php  абстракція джерела HTML
├── PageFetcher.php            реалізація: HTTP(S) через cURL (запасний варіант — streams)
├── XPathFactory.php           HTML → DOMXPath
├── ReporterInterface.php      абстракція виводу
├── ConsoleReporter.php        звіт у терміналі
├── DefaultRules.php           стандартний набір із 10 правил
├── Result.php, Status.php, Outcome.php
├── Exception/FetchException.php
├── Support/                   Text, MetaTagReader (спільні дрібні помічники)
└── Rules/                     10 правил (по одному класу на перевірку)
```

- `RuleInterface::check(DOMXPath $xpath): array` повертає `['status' => 'success|warning|error', 'message' => '...']`.
- `Analyzer::addRule(RuleInterface $rule)` реєструє правило. `Analyzer::analyze($url)` повертає результати в порядку реєстрації.
- Ядро не знає жодного конкретного правила, тож нові правила додаються без змін у ньому (Open/Closed).
- Збій одного правила (виняток чи некоректна відповідь) перетворюється на результат зі статусом `error`, а решта правил виконується далі.
- `Analyzer` залежить від абстракцій (`HtmlProviderInterface`), тому в тестах мережа не потрібна (Dependency Inversion).

### Використання як бібліотеки

```php
use Aratornv1\SeoChecker\Analyzer;
use Aratornv1\SeoChecker\DefaultRules;
use Aratornv1\SeoChecker\PageFetcher;
use Aratornv1\SeoChecker\Rules\TitleRule;

$analyzer = new Analyzer(new PageFetcher());

// Або лише потрібні правила, зі своїми порогами:
$analyzer->addRule(new TitleRule(minLength: 20, maxLength: 70));

// Або весь стандартний набір:
// foreach (DefaultRules::create() as $rule) { $analyzer->addRule($rule); }

foreach ($analyzer->analyze('https://example.com') as $result) {
    echo "{$result->ruleName}: {$result->status->value} — {$result->message}\n";
}
```

### Як додати власне правило

```php
namespace App\Seo;

use Aratornv1\SeoChecker\Outcome;
use Aratornv1\SeoChecker\RuleInterface;
use DOMXPath;

final class AuthorMetaRule implements RuleInterface
{
    public function getName(): string
    {
        return 'Author meta';
    }

    public function check(DOMXPath $xpath): array
    {
        $found = $xpath->query('//meta[@name="author"]')->length > 0;

        return $found
            ? Outcome::success('Автора вказано')
            : Outcome::warning('Мета-тег author відсутній');
    }
}
```

Далі зареєструйте його: `$analyzer->addRule(new AuthorMetaRule());`. `Outcome::success/warning/error` — зручні конструктори того самого масиву `['status' => …, 'message' => …]`, який можна повернути й вручну.

## 10 SEO-метрик і чому вони важливі

| # | Правило | Що перевіряє | Чому це важливо |
|---|---------|--------------|-----------------|
| 1 | `TitleRule` | `<title>` у `<head>`: є, один, довжина 30–60 символів | Один із найсильніших сигналів ранжування і заголовок сніпета у видачі. Задовгий заголовок обрізається, закороткий не розкриває тему. Відсутній або порожній — `error`. |
| 2 | `MetaDescriptionRule` | `meta description`: є, один, довжина 70–160 символів | Не впливає на ранжування напряму, але формує текст сніпета і CTR зі сторінки видачі. |
| 3 | `H1Rule` | Рівно один непорожній `<h1>` (зображення з `alt` усередині теж рахується текстом) | Позначає головну тему сторінки для пошуковика і скрінрідерів. Немає `h1` — `error`, кілька — `warning`. |
| 4 | `HeadingHierarchyRule` | Заголовки без пропуску рівнів (`h2` → `h4`) | Логічна структура допомагає краулерам розуміти контент і покращує доступність. Повернення на вищий рівень (`h4` → `h2`) не вважається помилкою. |
| 5 | `ImageAltRule` | Кожен `<img>` має атрибут `alt` | Текст `alt` індексується для пошуку за зображеннями і потрібен скрінрідерам. Порожній `alt=""` допустимий для декоративних зображень. Якщо без `alt` половина й більше зображень — `error`, інакше `warning`. |
| 6 | `CanonicalRule` | `<link rel="canonical">` у `<head>`: є, один, абсолютний URL (у `<body>` пошуковики його ігнорують); збігається з `og:url`, якщо той заданий | Вказує основну версію сторінки і запобігає проблемам дубльованого контенту (параметри, UTM, www/без www). Якщо одну й ту саму сторінку названо різними адресами, головного посилання немає: кілька різних canonical або порожній `href` — `error` (у повідомленні перелічено адреси), `canonical` і `og:url` на різні адреси — `warning`. Регістр хоста, кінцевий `/`, `#фрагмент` і стандартний порт за різницю не вважаються. |
| 7 | `RobotsMetaRule` | У `meta robots` / `googlebot` немає `noindex` (і `none`) | Випадково залишений `noindex` повністю прибирає сторінку з індексу, і це одна з найдорожчих SEO-помилок. `nofollow` дає `warning`. |
| 8 | `ViewportRule` | `<meta name="viewport">` з `width=device-width` | Google індексує сайти за принципом mobile-first, а без viewport сторінка вважається не адаптованою для мобільних. Заборона масштабування (`user-scalable=no`) — `warning` через доступність. |
| 9 | `OpenGraphRule` | Є `og:title`, `og:description`, `og:image` (абсолютна адреса) | Керує виглядом посилання в соцмережах і месенджерах, що збільшує кількість переходів і поширень. |
| 10 | `HtmlLangRule` | Атрибут `lang` у `<html>` з коректним значенням | Допомагає визначити мову й регіон контенту, а ще потрібен скрінрідерам і браузерним перекладачам. |

Усі правила працюють лише з DOM сторінки, без додаткових HTTP-запитів.

## Як завантажується сторінка

`PageFetcher` бере HTML за URL і повертає його вже в UTF-8:

- підтримуються лише `http` і `https`; редіректи (до 5) виконуються, а перехід на `file://` чи `ftp://` заборонено;
- загальний ліміт часу на запит 10 с (однаково для cURL і для запасного варіанту на PHP streams);
- відповідь має бути HTML-сторінкою: приймаються `text/html` і `application/xhtml+xml`, а SVG, RSS/Atom та інші XML-типи відхиляються з поясненням (відсутній `Content-Type` допускається);
- статус має бути 2xx, а 4xx, 5xx і незавершений редірект дають зрозумілу помилку;
- кодування визначається за BOM, заголовком `Content-Type` і тегом `<meta charset>`, тож сайти у `windows-1251`, `KOI8-R`, `ISO-8859-x` читаються правильно.

## Обмеження

- **Розмір сторінки.** Відповідь більша за 2 МБ (змінюється через `--max-size` або параметр `maxBytes` у `PageFetcher`) відхиляється. Типова сторінка важить до 700 КБ, і тоді процес займає ~28–32 МБ пам'яті. Розібране DOM-дерево займає приблизно в 30 разів більше за розмір HTML, тому сторінка на 4,8 МБ потребувала б ~150 МБ. Ліміт тримає найгірший випадок під контролем.
- Аналізується HTML, який віддає сервер. Сторінки, що формуються JavaScript-ом на клієнті, можуть показати зайві помилки (наприклад, відсутній `<h1>`).
- `RobotsMetaRule` читає лише `<meta name="robots">` і `<meta name="googlebot">`. Директиви для інших ботів (`bingbot`, `googlebot-news`) не враховуються.
- Директиви індексації з HTTP-заголовка `X-Robots-Tag` не видно з HTML, тому перевіряється лише `<meta>`.
- Правила не оцінюють якість контенту, швидкість і зовнішні посилання.

## Тести

```bash
composer install      # разом із PHPUnit
composer test         # або: php vendor/bin/phpunit
```

Тести охоплюють кожне правило (граничні випадки, регістр, кирилицю), ядро, звіт, визначення кодування, розбір аргументів CLI, повний аудит на еталонних сторінках і завантажувач (редіректи, ліміти, таймаути, кодування). Для `PageFetcher` автоматично запускається локальний вбудований сервер PHP, тож інтернет не потрібен.

### Примітка для Windows

PHP для Windows не містить пакета кореневих сертифікатів, тому cURL не може перевірити HTTPS. `PageFetcher` у такому разі використовує системне сховище сертифікатів Windows. Перевірка сертифіката при цьому не вимикається.

## Ліцензія

MIT
