<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\Exception\UsageException;
use Closure;

/**
 * The command line runner: parses arguments, audits each URL and returns an exit code.
 *
 * It never calls exit() and writes only to the streams it is given, so tests can
 * run it in-process with a stub HTML provider and in-memory streams.
 */
final class Cli
{
    public const EXIT_OK = 0;
    public const EXIT_ISSUES = 1;
    public const EXIT_USAGE = 2;

    // Upper bound for --max-size, in megabytes. Memory use grows roughly 30x
    // with the HTML size, so absurd values are rejected as a typo guard.
    public const MAX_SIZE_CEILING_MB = 100;

    // How many URLs can be audited in one run. The pages are fetched one by one.
    public const MAX_URLS = 5;

    /** @var Closure(int): HtmlProviderInterface builds the HTML source for a size limit in bytes */
    private readonly Closure $providerFactory;

    /** @var resource */
    private $out;

    /** @var resource */
    private $err;

    /**
     * @param Closure(int): HtmlProviderInterface|null $providerFactory receives the --max-size limit in bytes
     * @param resource|null                            $out             report stream, STDOUT by default
     * @param resource|null                            $err             error stream, STDERR by default
     * @param bool                                     $colorAllowed    false when not a terminal or NO_COLOR is set
     */
    public function __construct(
        ?Closure $providerFactory = null,
        $out = null,
        $err = null,
        private readonly bool $colorAllowed = false,
    ) {
        $this->providerFactory = $providerFactory
            ?? static fn (int $maxBytes): HtmlProviderInterface => new PageFetcher(maxBytes: $maxBytes);
        $this->out = $out ?? STDOUT;
        $this->err = $err ?? STDERR;
    }

    /**
     * @param list<string> $args command line arguments without the script name
     *
     * @return int one of the EXIT_* constants
     */
    public function run(array $args): int
    {
        try {
            $options = $this->parseArguments($args);
        } catch (UsageException $e) {
            if ($e->getMessage() !== '') {
                fwrite($this->err, $e->getMessage() . PHP_EOL);
            }
            if ($e->showUsage) {
                fwrite($this->out, $this->usage());
            }

            return self::EXIT_USAGE;
        }

        if ($options['help']) {
            fwrite($this->out, $this->usage());

            return self::EXIT_OK;
        }

        // The engine knows nothing about concrete rules. The standard set lives in
        // DefaultRules; add your own with $analyzer->addRule(new MyRule()).
        $analyzer = new Analyzer(($this->providerFactory)($options['maxBytes']));
        foreach (DefaultRules::create() as $rule) {
            $analyzer->addRule($rule);
        }

        $reporter = new ConsoleReporter($options['useEmoji'], $options['useColor'] && $this->colorAllowed);
        $exitCode = self::EXIT_OK;

        // Pages are audited one by one. A page that cannot be downloaded does not stop
        // the others, but it makes the whole run exit with EXIT_USAGE.
        foreach ($options['urls'] as $url) {
            try {
                $results = $analyzer->analyze($url);
            } catch (FetchException $e) {
                fwrite($this->err, $e->getMessage() . PHP_EOL);
                $exitCode = self::EXIT_USAGE;
                continue;
            }

            fwrite($this->out, $reporter->render($url, $results));

            foreach ($results as $result) {
                // EXIT_ISSUES must not hide an earlier EXIT_USAGE.
                if ($result->status === Status::Error && $exitCode === self::EXIT_OK) {
                    $exitCode = self::EXIT_ISSUES;
                    break;
                }
            }
        }

        return $exitCode;
    }

    /**
     * @param list<string> $args
     *
     * @return array{urls: list<string>, maxBytes: int, useEmoji: bool, useColor: bool, help: bool}
     *
     * @throws UsageException
     */
    private function parseArguments(array $args): array
    {
        $useEmoji = true;
        $useColor = true;
        $maxBytes = PageFetcher::DEFAULT_MAX_BYTES;
        $urls = [];

        $optionsEnded = false;

        // Walk through the CLI arguments: anything that is not an option is a URL,
        // options (--max-size, --no-emoji, ...) set the flags. A plain index loop is
        // used so "--max-size 3" can consume the next argument via $i++.
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];

            // "--" ends option parsing, so a URL that starts with "-" can still be passed.
            if (!$optionsEnded && $arg === '--') {
                $optionsEnded = true;
                continue;
            }

            $isOption = !$optionsEnded && str_starts_with($arg, '-');

            if (!$isOption) {
                // Too many URLs is an error, not a silent truncation.
                if (count($urls) >= self::MAX_URLS) {
                    throw new UsageException(
                        'At most ' . self::MAX_URLS . " URLs can be checked at a time (extra: {$arg}).",
                    );
                }
                $urls[] = $arg;
                continue;
            }

            // Support both "--max-size=3" and "--max-size 3".
            if ($arg === '--max-size' || str_starts_with($arg, '--max-size=')) {
                if ($arg === '--max-size') {
                    // Do not swallow the next token when it is another option.
                    $next = $args[$i + 1] ?? '';
                    $value = ($next === '' || str_starts_with($next, '-')) ? '' : $next;
                    if ($value !== '') {
                        $i++;
                    }
                } else {
                    $value = substr($arg, strlen('--max-size='));
                }

                $parsed = $this->parseMaxSize($value);

                if ($parsed === null) {
                    throw new UsageException($value === ''
                        ? 'Missing value for --max-size: expected a number of megabytes.'
                        : sprintf(
                            'Invalid --max-size value "%s": expected a number of megabytes between 0 and %d.',
                            $value,
                            self::MAX_SIZE_CEILING_MB,
                        ));
                }

                $maxBytes = $parsed;
                continue;
            }

            switch ($arg) {
                case '-h':
                case '--help':
                    return [
                        'urls' => [],
                        'maxBytes' => $maxBytes,
                        'useEmoji' => $useEmoji,
                        'useColor' => $useColor,
                        'help' => true,
                    ];
                case '--no-emoji':
                    $useEmoji = false;
                    break;
                case '--no-color':
                    $useColor = false;
                    break;
                default:
                    throw new UsageException("Unknown option: {$arg}", true);
            }
        }

        if ($urls === []) {
            throw new UsageException('', true);
        }

        // Allow "example.com" without a scheme.
        $urls = array_map(
            static fn (string $url): string => preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://' . $url,
            $urls,
        );

        return [
            'urls' => $urls,
            'maxBytes' => $maxBytes,
            'useEmoji' => $useEmoji,
            'useColor' => $useColor,
            'help' => false,
        ];
    }

    /**
     * Parses the --max-size value (megabytes, decimals allowed) into bytes.
     *
     * @return int|null null when the value is not a valid size
     */
    private function parseMaxSize(string $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }

        $megabytes = (float) $value;

        if ($megabytes <= 0 || $megabytes > self::MAX_SIZE_CEILING_MB) {
            return null;
        }

        return max(1, (int) round($megabytes * 1048576));
    }

    private function usage(): string
    {
        $default = PageFetcher::DEFAULT_MAX_BYTES / 1048576;
        $ceiling = self::MAX_SIZE_CEILING_MB;
        $maxUrls = self::MAX_URLS;

        return <<<TXT
        Usage: seo-check [options] <url> [<url> ...]

        Up to {$maxUrls} URLs are checked one after another.

        Options:
          --max-size=N  Reject pages larger than N megabytes (default: {$default}, max: {$ceiling})
          --no-emoji    Use plain-text markers ([ OK ] [WARN] [FAIL]) instead of emoji
          --no-color    Disable ANSI colors
          -h, --help    Show this help

        Exit codes: 0 - no errors, 1 - at least one rule failed, 2 - usage error or a page could not be downloaded

        TXT;
    }
}
