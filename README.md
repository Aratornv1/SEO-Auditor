# SEO Auditor (`seo-checker`)

[![CI](https://github.com/Aratornv1/SEO-Auditor/actions/workflows/ci.yml/badge.svg)](https://github.com/Aratornv1/SEO-Auditor/actions/workflows/ci.yml)

A console SEO auditor for web pages, written in plain PHP. The package downloads a page's HTML by URL, runs it through 10 independent rules, and prints a clear report in the terminal.

The architecture is extensible through the **Strategy** pattern: every check is a separate class implementing `RuleInterface`. Adding a new check doesn't require changing the core.

## Requirements

- PHP 8.1+
- `dom` and `mbstring` extensions
- `curl` extension (recommended; PHP streams are used without it)
- Composer 2

## Installation

```bash
git clone https://github.com/Aratornv1/SEO-Auditor.git
cd SEO-Auditor
composer install
```

### Using it as a local package in another project

Add a `path` repository to the target project's `composer.json`:

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

Then run `composer update aratornv1/seo-checker`. Composer creates a symlink to the package, and the command becomes available as `vendor/bin/seo-check`.

## Usage

```bash
php bin/seo-check https://example.com
```

On Linux/macOS the file is executable, so you can also run it as `./bin/seo-check https://example.com`. The scheme is optional: `seo-check example.com` automatically becomes `https://example.com`. One address is checked per run: a second URL causes an error. To pass an address that starts with `-`, put `--` before it.

| Option | Purpose |
|--------|---------|
| `--max-size=N` | Reject pages larger than N megabytes (default `2`, maximum `100`, fractional values such as `0.5` are allowed). The `--max-size 3` form also works |
| `--format=F` | Output format: `text` (default, the report below) or `json` (one JSON object, see below). The `--format json` form also works |
| `--strict` | Treat warnings as failures: exit code `1` if any rule returns `warning` or `error`. Useful in CI that should allow zero findings |
| `--extra-rules` | Also run the optional rules (see [Optional rules](#optional-rules)) after the standard ten |
| `--no-emoji` | Use text markers `[ OK ] [WARN] [FAIL]` instead of icons |
| `--no-color` | Disable ANSI colors (they are disabled anyway when the output is not a terminal or `NO_COLOR` is set) |
| `-h`, `--help` | Show help |

Exit codes: `0` — no errors (warnings are allowed), `1` — at least one `error` (or a `warning` with `--strict`), `2` — incorrect usage or the page could not be downloaded. This is convenient for CI.

Example report for a page with problems (the report messages are currently in Ukrainian):

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

Statuses: ✅ `success` — all good, ⚠️ `warning` — worth improving, ❌ `error` — a serious problem.

### JSON output

`--format=json` prints one JSON object on a single line, convenient for CI and scripts:

```bash
php bin/seo-check --format=json example.com | jq '.results[] | select(.status == "error")'
```

```json
{"url":"https://example.com","results":[{"rule":"Title","status":"warning","message":"Заголовок занадто короткий (4 символи, рекомендовано 30–60)"}, ...]}
```

If the page cannot be downloaded, stdout still contains JSON: `{"url":"https://example.com","error":"..."}`. The exit codes are the same as in text mode. Argument errors (unknown option, bad value) are printed to stderr as plain text.

## Architecture

```
src/
├── RuleInterface.php          rule contract (Strategy)
├── Analyzer.php               core: addRule(), HTML loading, running the rules
├── HtmlProviderInterface.php  abstraction of the HTML source
├── PageFetcher.php            implementation: HTTP(S) via cURL (fallback: streams)
├── XPathFactory.php           HTML → DOMXPath
├── ReporterInterface.php      output abstraction
├── ConsoleReporter.php        terminal report
├── JsonReporter.php           JSON report (--format=json)
├── Cli.php                    command-line runner: arguments, exit codes (bin/seo-check is a thin wrapper)
├── DefaultRules.php           default set of 10 rules
├── ExtraRules.php             optional rules (--extra-rules)
├── Result.php, Status.php, Outcome.php
├── Exception/                 FetchException, UsageException
├── Support/                   small shared helpers: Text, LengthCheck, MetaTagReader, XPathFunctions, Charset, HttpHeaders
└── Rules/                     10 rules (one class per check)
```

- `RuleInterface::check(DOMXPath $xpath): Outcome` returns a typed `Outcome` (a `Status` and a message). The `Analyzer` adds the rule name and turns it into a `Result`.
- `Analyzer::addRule(RuleInterface $rule)` registers a rule. `Analyzer::analyze($url)` returns the results in registration order.
- The core doesn't know any specific rule, so new rules are added without changing it (Open/Closed).
- A failure in one rule (an exception or a wrong return type) is turned into a result with the `error` status, and the remaining rules keep running.
- `Analyzer` depends on abstractions (`HtmlProviderInterface`), so tests don't need a network (Dependency Inversion).

### Using it as a library

```php
use Aratornv1\SeoChecker\Analyzer;
use Aratornv1\SeoChecker\DefaultRules;
use Aratornv1\SeoChecker\PageFetcher;
use Aratornv1\SeoChecker\Rules\TitleRule;

$analyzer = new Analyzer(new PageFetcher());

// Either only the rules you need, with your own thresholds:
$analyzer->addRule(new TitleRule(minLength: 20, maxLength: 70));

// Or the whole default set:
// foreach (DefaultRules::create() as $rule) { $analyzer->addRule($rule); }

foreach ($analyzer->analyze('https://example.com') as $result) {
    echo "{$result->ruleName}: {$result->status->value} — {$result->message}\n";
}
```

### Adding your own rule

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

    public function check(DOMXPath $xpath): Outcome
    {
        $found = $xpath->query('//meta[@name="author"]')->length > 0;

        return $found
            ? Outcome::success('Author is specified')
            : Outcome::warning('The author meta tag is missing');
    }
}
```

Then register it: `$analyzer->addRule(new AuthorMetaRule());`. `Outcome::success/warning/error` are named constructors for the status, so the rule only writes the message. A rule that returns anything other than an `Outcome` (or throws) is reported as an `error` result and the other rules keep running.

## The 10 SEO metrics and why they matter

| # | Rule | What it checks | Why it matters |
|---|------|----------------|----------------|
| 1 | `TitleRule` | `<title>` in `<head>`: present, single, 30–60 characters long | One of the strongest ranking signals and the snippet headline in search results. An overly long title gets truncated, and one that is too short doesn't convey the topic. Missing or empty — `error`. |
| 2 | `MetaDescriptionRule` | `meta description`: present, single, 70–160 characters long | Doesn't affect ranking directly, but shapes the snippet text and the click-through rate from the results page. |
| 3 | `H1Rule` | Exactly one non-empty `<h1>` (an image with `alt` inside also counts as text) | Marks the page's main topic for search engines and screen readers. No `h1` — `error`, several — `warning`. |
| 4 | `HeadingHierarchyRule` | Headings without skipped levels (`h2` → `h4`) | A logical structure helps crawlers understand the content and improves accessibility. Going back up to a higher level (`h4` → `h2`) is not an error. |
| 5 | `ImageAltRule` | Every `<img>` has an `alt` attribute | `alt` text is indexed for image search and is needed by screen readers. An empty `alt=""` is acceptable for decorative images. If half or more of the images lack `alt` — `error`, otherwise `warning`. |
| 6 | `CanonicalRule` | `<link rel="canonical">` in `<head>`: present, single, absolute URL (search engines ignore it in `<body>`); matches `og:url` if one is set | Points to the primary version of the page and prevents duplicate-content problems (parameters, UTM, www/non-www). If the same page is named by different addresses, there is no main link: several different canonicals or an empty `href` — `error` (the message lists the addresses), `canonical` and `og:url` pointing to different addresses — `warning`. Host case, a trailing `/`, a `#fragment`, and the default port are not treated as differences. |
| 7 | `RobotsMetaRule` | No `noindex` (or `none`) in `meta robots` / `googlebot` | An accidentally left `noindex` removes the page from the index entirely, and it is one of the costliest SEO mistakes. `nofollow` gives a `warning`. |
| 8 | `ViewportRule` | `<meta name="viewport">` with `width=device-width` | Google indexes sites mobile-first, and without a viewport the page is considered not adapted for mobile. Disabling zoom (`user-scalable=no`) is a `warning` because of accessibility. |
| 9 | `OpenGraphRule` | `og:title`, `og:description`, `og:image` (absolute URL) are present | Controls how the link looks on social networks and in messengers, which increases clicks and shares. |
| 10 | `HtmlLangRule` | `lang` attribute on `<html>` with a valid value | Helps determine the content's language and region, and is also needed by screen readers and browser translators. |

All rules work only with the page's DOM, without extra HTTP requests.

### Optional rules

Two more rules are available but are not part of the standard ten. The CLI runs them with `--extra-rules`; in code, register `ExtraRules::create()` next to `DefaultRules::create()`.

| Rule | What it checks | Why it matters |
|------|----------------|----------------|
| `MixedContentRule` | Resources loaded over `http://`: scripts, stylesheets, frames (`error`) and images, video, audio, `srcset` (`warning`). Links (`<a href>`) are not resources and are not checked | On an HTTPS page browsers block insecure scripts and styles, which breaks the page, and warn about insecure images. Rules see only the DOM, not the page URL, so any `http://` resource is reported |
| `WordCountRule` | At least 300 words of visible text in `<body>` (configurable: `new WordCountRule(minWords: 500)`). Text in `<script>`, `<style>`, `<noscript>` and `<template>` is not counted | Pages with very little text ("thin content") rarely rank well, because search engines have nothing to understand the topic from |

## How a page is loaded

`PageFetcher` fetches the HTML by URL and returns it already in UTF-8:

- only `http` and `https` are supported; redirects (up to 5) are followed, and a redirect to `file://` or `ftp://` is forbidden;
- the total time limit per request is 10 s (the same for cURL and for the PHP streams fallback);
- the response must be an HTML page: `text/html` and `application/xhtml+xml` are accepted, while SVG, RSS/Atom, and other XML types are rejected with an explanation (a missing `Content-Type` is allowed);
- the status must be 2xx, while 4xx, 5xx, and an unfinished redirect produce a clear error;
- the encoding is detected from the BOM, the `Content-Type` header, and the `<meta charset>` tag, so sites in `windows-1251`, `KOI8-R`, and `ISO-8859-x` are read correctly.

## Limitations

- **Page size.** A response larger than 2 MB (configurable via `--max-size` or the `maxBytes` parameter of `PageFetcher`) is rejected. A typical page weighs up to 700 KB, and the process then uses ~28–32 MB of memory. The parsed DOM tree takes roughly 30 times the HTML size, so a 4.8 MB page would need ~150 MB. The limit keeps the worst case under control.
- The HTML served by the server is analyzed. Pages rendered by client-side JavaScript may show false errors (for example, a missing `<h1>`).
- `RobotsMetaRule` reads only `<meta name="robots">` and `<meta name="googlebot">`. Directives for other bots (`bingbot`, `googlebot-news`) are not taken into account.
- Indexing directives from the `X-Robots-Tag` HTTP header are not visible from the HTML, so only `<meta>` is checked.
- The rules don't evaluate content quality, speed, or external links.

## Tests

```bash
composer install      # includes PHPUnit, PHP_CodeSniffer and PHPStan
composer test         # tests only (or: php vendor/bin/phpunit)
composer lint         # PSR-12 code style (phpcs.xml.dist)
composer analyse      # PHPStan static analysis, level 10 (phpstan.neon.dist)
composer check        # all of the above
```

GitHub Actions runs the tests on PHP 8.1–8.5 and the style and static analysis checks on every push to `main` and on every pull request (`.github/workflows/ci.yml`).

The tests cover every rule (edge cases, letter case, Cyrillic), the core, the report, encoding detection, CLI argument parsing, a full audit on reference pages, and the fetcher (redirects, limits, timeouts, encodings). For `PageFetcher`, a local built-in PHP server is started automatically, so no internet is needed.

### Note for Windows

PHP for Windows doesn't ship a root certificate bundle, so cURL can't verify HTTPS. In that case `PageFetcher` uses the Windows system certificate store. Certificate verification is not disabled.

## License

MIT
