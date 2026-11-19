<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Support\MetaTagReader;
use Aratornv1\SeoChecker\XPathFactory;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class MetaTagReaderTest extends TestCase
{
    public function testReadsByNameCaseInsensitivelyInDocumentOrder(): void
    {
        $xpath = (new XPathFactory())->create(
            '<html><head><meta name="Robots" content="a"><meta name="robots" content="b"><meta name="other" content="c"></head></html>',
        );

        self::assertSame(['a', 'b'], (new MetaTagReader())->byName($xpath, 'robots'));
    }

    public function testReadsByProperty(): void
    {
        $xpath = (new XPathFactory())->create(
            '<html><head><meta property="og:title" content="T"><meta name="og:title" content="ignored"></head></html>',
        );

        self::assertSame(['T'], (new MetaTagReader())->byProperty($xpath, 'og:title'));
    }

    public function testTagWithoutContentYieldsEmptyString(): void
    {
        $xpath = (new XPathFactory())->create('<html><head><meta name="description"></head></html>');

        self::assertSame([''], (new MetaTagReader())->byName($xpath, 'description'));
    }

    public function testRejectsUnsafeKeys(): void
    {
        $xpath = (new XPathFactory())->create('<html></html>');

        $this->expectException(InvalidArgumentException::class);

        (new MetaTagReader())->byName($xpath, "x'] | //*[@a='");
    }
}
