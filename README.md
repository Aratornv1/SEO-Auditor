# SEO Auditor (`seo-checker`)

Консольний SEO-аудитор веб-сторінок на чистому PHP. Пакет завантажує HTML за URL, прогоняє його через набір незалежних правил і виводить зрозумілий звіт у терміналі.

Архітектура розширювана за патерном **Strategy**: кожна перевірка — окремий клас, що реалізує `RuleInterface`. Щоб додати нову перевірку, ядро змінювати не потрібно.

## Вимоги

- PHP 8.1+
- розширення `dom`, `mbstring`, `curl`
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

На Linux/macOS файл має права на виконання (`chmod +x bin/seo-check`), тому можна запускати так: `./bin/seo-check https://example.com`.

Приклад звіту:

```
SEO-аудит: https://example.com

 ✅ Title            Заголовок має оптимальну довжину (47 символів)
 ⚠️ Meta description Опис занадто короткий (42 символи, рекомендовано 70–160)
 ❌ H1               На сторінці не знайдено жодного <h1>
 ...

Підсумок: 7 ✅ · 2 ⚠️ · 1 ❌
```

Код виходу: `0` — помилок немає, `1` — є хоча б одна `error`. Це зручно для CI. Якщо термінал не підтримує емодзі, використайте `--no-emoji`.

## Архітектура

```
src/
├── RuleInterface.php   контракт правила
├── Analyzer.php        ядро: завантаження HTML, DOMXPath, запуск правил
├── PageFetcher.php     отримання HTML за URL
├── Reporter.php        форматування звіту
└── Rules/              10 незалежних правил (по одному класу на перевірку)
```

- `RuleInterface::check(DOMXPath $xpath): array` повертає `['status' => 'success|warning|error', 'message' => '...']`.
- `Analyzer::addRule(RuleInterface $rule)` реєструє правило.
- Кожне правило відповідає за одну перевірку (принцип SRP).

### Як додати власне правило

```php
namespace Aratornv1\SeoChecker\Rules;

use Aratornv1\SeoChecker\RuleInterface;
use DOMXPath;

final class MyRule implements RuleInterface
{
    public function getName(): string
    {
        return 'My rule';
    }

    public function check(DOMXPath $xpath): array
    {
        $found = $xpath->query('//meta[@name="author"]')->length > 0;

        return $found
            ? ['status' => 'success', 'message' => 'Автора вказано']
            : ['status' => 'warning', 'message' => 'Мета-тег author відсутній'];
    }
}
```

Далі зареєструйте його: `$analyzer->addRule(new MyRule());`.

## 10 SEO-метрик і чому вони важливі

| # | Правило | Що перевіряє | Чому це важливо |
|---|---------|--------------|-----------------|
| 1 | `TitleRule` | `<title>` присутній, довжина 30–60 символів | Один із найсильніших сигналів ранжування і заголовок сніпета у видачі. Задовгий заголовок обрізається, закороткий не розкриває тему. |
| 2 | `MetaDescriptionRule` | `meta description` присутній, довжина 70–160 символів | Не впливає на ранжування напряму, але формує текст сніпета і CTR зі сторінки видачі. |
| 3 | `H1Rule` | Рівно один `<h1>` | Позначає головну тему сторінки для пошуковика і скрінрідерів. Відсутній або дублований `h1` розмиває сигнал. |
| 4 | `HeadingHierarchyRule` | Заголовки без пропуску рівнів (`h2` → `h4`) | Логічна структура допомагає краулерам розуміти контент і покращує доступність. |
| 5 | `ImageAltRule` | Частка `<img>` з непорожнім `alt` | Текст `alt` індексується для пошуку за зображеннями, а ще це потрібно для доступності. |
| 6 | `CanonicalRule` | `<link rel="canonical">` присутній з абсолютним URL | Вказує основну версію сторінки і запобігає проблемам дубльованого контенту (параметри, UTM, www/без www). |
| 7 | `RobotsMetaRule` | У `meta robots` немає `noindex` | Випадково залишений `noindex` повністю прибирає сторінку з індексу, і це одна з найдорожчих SEO-помилок. |
| 8 | `ViewportRule` | `<meta name="viewport">` присутній | Google індексує сайти за принципом mobile-first, а без viewport сторінка вважається не адаптованою для мобільних. |
| 9 | `OpenGraphRule` | Є `og:title`, `og:description`, `og:image` | Керує виглядом посилання в соцмережах і месенджерах, що збільшує кількість переходів і поширень. |
| 10 | `HtmlLangRule` | Атрибут `lang` у `<html>` | Допомагає визначити мову й регіон контенту, а ще потрібен скрінрідерам і браузерним перекладачам. |

Усі правила працюють лише з DOM сторінки, без додаткових HTTP-запитів.

## Ліцензія

MIT
