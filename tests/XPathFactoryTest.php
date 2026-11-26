<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests;

use Aratornv1\SeoChecker\Support\XPathFunctions;
use Aratornv1\SeoChecker\XPathFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class XPathFactoryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function declaredCharsetProvider(): iterable
    {
        yield 'no declaration' => [''];
        yield 'utf-8 meta' => ['<meta charset="utf-8">'];
        yield 'stale windows-1251 meta on a UTF-8 page' => ['<meta charset="windows-1251">'];
        yield 'stale http-equiv koi8-u' => ['<meta http-equiv="Content-Type" content="text/html; charset=koi8-u">'];
        yield 'latin-1 meta' => ['<meta charset="iso-8859-1">'];
    }

    #[DataProvider('declaredCharsetProvider')]
    public function testCyrillicSurvivesWhateverTheMetaTagClaims(string $meta): void
    {
        $xpath = (new XPathFactory())->create(
            "<html><head>{$meta}<title>Привіт, світе — тест €</title></head><body><h1>Їжак</h1></body></html>",
        );

        self::assertSame('Привіт, світе — тест €', $xpath->evaluate('string(//head/title)'));
        self::assertSame('Їжак', $xpath->evaluate('string(//h1)'));
    }

    public function testNonAsciiAttributeValuesAreKept(): void
    {
        $xpath = (new XPathFactory())->create('<html><body><img alt="Логотип «Потік»" src="a.png"></body></html>');

        self::assertSame('Логотип «Потік»', $xpath->evaluate('string(//img/@alt)'));
    }

    public function testCharactersOutsideTheBasicPlaneAreKept(): void
    {
        $xpath = (new XPathFactory())->create('<html><head><title>Тест 😀 𝒜</title></head></html>');

        self::assertSame('Тест 😀 𝒜', $xpath->evaluate('string(//head/title)'));
    }

    public function testEmptyAndGarbageInputDoNotThrow(): void
    {
        self::assertSame(0.0, (new XPathFactory())->create('')->evaluate('count(//p)'));
        self::assertSame(1.0, (new XPathFactory())->create('<p>unclosed <b>tags')->evaluate('count(//p)'));
    }

    public function testInvalidUtf8BytesDoNotThrow(): void
    {
        $xpath = (new XPathFactory())->create("<html><head><title>bad \xFF\xFE bytes</title></head></html>");

        self::assertStringContainsString('bad', XPathFunctions::string($xpath, '//head/title'));
    }
}
