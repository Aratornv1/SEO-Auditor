<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker;

use Aratornv1\SeoChecker\Exception\FetchException;
use Aratornv1\SeoChecker\Exception\UsageException;
use Closure;

/**
 * The command line runner: parses arguments, audits the URL and returns an exit code.
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

    public const FORMATS = ['text', 'json'];

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
        if ($options['extraRules']) {
            foreach (ExtraRules::create() as $rule) {
                $analyzer->addRule($rule);
            }
        }

        $json = $options['format'] === 'json';
        $url = $options['url'];

        try {
            $results = $analyzer->analyze($url);
        } catch (FetchException $e) {
            // In JSON mode stdout stays machine-readable, so the error is a JSON line there too.
            fwrite(
                $json ? $this->out : $this->err,
                $json ? (new JsonReporter())->renderError($url, $e->getMessage()) : $e->getMessage() . PHP_EOL,
            );

            return self::EXIT_USAGE;
        }

        $reporter = $json
            ? new JsonReporter()
            : new ConsoleReporter($options['useEmoji'], $options['useColor'] && $this->colorAllowed);

        fwrite($this->out, $reporter->render($url, $results));

        // Warnings change the exit code only with --strict.
        foreach ($results as $result) {
            if ($result->status === Status::Error || ($options['strict'] && $result->status === Status::Warning)) {
                return self::EXIT_ISSUES;
            }
        }

        return self::EXIT_OK;
    }

    /**
     * @param list<string> $args
     *
     * @return array{
     *     url: string,
     *     maxBytes: int,
     *     format: string,
     *     strict: bool,
     *     extraRules: bool,
     *     useEmoji: bool,
     *     useColor: bool,
     *     help: bool,
     * }
     *
     * @throws UsageException
     */
    private function parseArguments(array $args): array
    {
        $options = [
            'url' => '',
            'maxBytes' => PageFetcher::DEFAULT_MAX_BYTES,
            'format' => 'text',
            'strict' => false,
            'extraRules' => false,
            'useEmoji' => true,
            'useColor' => true,
            'help' => false,
        ];
        $url = null;
        $optionsEnded = false;

        // Walk through the CLI arguments: anything that is not an option is the URL,
        // options (--max-size, --format, ...) set the flags. A plain index loop is
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
                // A second positional argument is an error, not a silent override.
                if ($url !== null) {
                    throw new UsageException("Only one URL can be checked at a time (got: {$url} and {$arg}).");
                }
                $url = $arg;
                continue;
            }

            if ($arg === '--max-size' || str_starts_with($arg, '--max-size=')) {
                $value = $this->optionValue($args, $i, '--max-size');
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

                $options['maxBytes'] = $parsed;
                continue;
            }

            if ($arg === '--format' || str_starts_with($arg, '--format=')) {
                $value = $this->optionValue($args, $i, '--format');

                if (!in_array($value, self::FORMATS, true)) {
                    throw new UsageException($value === ''
                        ? 'Missing value for --format: expected text or json.'
                        : sprintf('Invalid --format value "%s": expected text or json.', $value));
                }

                $options['format'] = $value;
                continue;
            }

            switch ($arg) {
                case '-h':
                case '--help':
                    $options['help'] = true;

                    return $options;
                case '--strict':
                    $options['strict'] = true;
                    break;
                case '--extra-rules':
                    $options['extraRules'] = true;
                    break;
                case '--no-emoji':
                    $options['useEmoji'] = false;
                    break;
                case '--no-color':
                    $options['useColor'] = false;
                    break;
                default:
                    throw new UsageException("Unknown option: {$arg}", true);
            }
        }

        if ($url === null) {
            throw new UsageException('', true);
        }

        // Allow "example.com" without a scheme.
        $options['url'] = preg_match('#^[a-z][a-z0-9+.-]*://#i', $url) ? $url : 'https://' . $url;

        return $options;
    }

    /**
     * Reads the value of an option given as "--name=value" or "--name value".
     *
     * In the second form the next argument is consumed (via $i), unless it is
     * missing or is another option: then '' is returned and nothing is consumed.
     *
     * @param list<string> $args
     */
    private function optionValue(array $args, int &$i, string $name): string
    {
        if ($args[$i] !== $name) {
            return substr($args[$i], strlen($name . '='));
        }

        $next = $args[$i + 1] ?? '';

        // Do not swallow the next token when it is another option.
        if ($next === '' || str_starts_with($next, '-')) {
            return '';
        }

        $i++;

        return $next;
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

        return <<<TXT
        Usage: seo-check [options] <url>

        Options:
          --max-size=N   Reject pages larger than N megabytes (default: {$default}, max: {$ceiling})
          --format=F     Output format: text (default) or json
          --strict       Treat warnings as failures (exit code 1)
          --extra-rules  Also run the optional rules: mixed content, word count
          --no-emoji     Use plain-text markers ([ OK ] [WARN] [FAIL]) instead of emoji
          --no-color     Disable ANSI colors
          -h, --help     Show this help

        Exit codes: 0 - no errors, 1 - at least one rule failed (or a warning with --strict),
                    2 - usage error or the page could not be downloaded

        TXT;
    }
}
