<?php

declare(strict_types=1);

namespace Aratornv1\SeoChecker\Tests\Support;

use Aratornv1\SeoChecker\Support\Charset;
use PHPUnit\Framework\TestCase;

final class CharsetTest extends TestCase
{
    private const TEXT = 'Привіт, світе';

    public function testUtf8IsLeftUntouched(): void
    {
        self::assertSame(self::TEXT, Charset::toUtf8(self::TEXT, 'text/html; charset=utf-8'));
        self::assertSame(self::TEXT, Charset::toUtf8(self::TEXT, 'text/html'));
        self::assertSame(self::TEXT, Charset::toUtf8(self::TEXT));
    }

    public function testConvertsByContentTypeHeader(): void
    {
        $cp1251 = mb_convert_encoding(self::TEXT, 'Windows-1251', 'UTF-8');

        self::assertSame(self::TEXT, Charset::toUtf8($cp1251, 'text/html; charset=windows-1251'));
        self::assertSame(self::TEXT, Charset::toUtf8($cp1251, 'text/html; charset="Windows-1251"'));
        self::assertSame(self::TEXT, Charset::toUtf8($cp1251, 'text/html;CHARSET=CP1251'));
    }

    public function testConvertsByMetaCharset(): void
    {
        $page = '<html><head><meta charset="windows-1251"><title>' . self::TEXT . '</title></head></html>';
        $cp1251 = mb_convert_encoding($page, 'Windows-1251', 'UTF-8');

        self::assertSame($page, Charset::toUtf8($cp1251, 'text/html'));
    }

    public function testConvertsByHttpEquivMeta(): void
    {
        // KOI8-R has no Ukrainian "і", so this case uses Russian text.
        $page = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=koi8-r"><title>'
            . 'Привет, мир' . '</title></head></html>';
        $koi8 = mb_convert_encoding($page, 'KOI8-R', 'UTF-8');

        self::assertSame($page, Charset::toUtf8($koi8));
    }

    public function testHttpHeaderWinsOverMeta(): void
    {
        // The header says windows-1251, the meta lies about utf-8: the header decides.
        $page = '<meta charset="utf-8">' . self::TEXT;
        $cp1251 = mb_convert_encoding($page, 'Windows-1251', 'UTF-8');

        self::assertSame($page, Charset::toUtf8($cp1251, 'text/html; charset=windows-1251'));
    }

    public function testStripsUtf8Bom(): void
    {
        self::assertSame(self::TEXT, Charset::toUtf8("\xEF\xBB\xBF" . self::TEXT));
    }

    public function testConvertsUtf16WithBom(): void
    {
        $le = "\xFF\xFE" . mb_convert_encoding(self::TEXT, 'UTF-16LE', 'UTF-8');
        $be = "\xFE\xFF" . mb_convert_encoding(self::TEXT, 'UTF-16BE', 'UTF-8');

        self::assertSame(self::TEXT, Charset::toUtf8($le));
        self::assertSame(self::TEXT, Charset::toUtf8($be));
    }

    public function testLatin1LabelIsTreatedAsWindows1252(): void
    {
        // 0x80 is the euro sign in windows-1252 but a control character in strict ISO-8859-1.
        self::assertSame('€ café', Charset::toUtf8("\x80 caf\xE9", 'text/html; charset=iso-8859-1'));
    }

    public function testUndeclaredInvalidUtf8FallsBackToWindows1252(): void
    {
        self::assertSame('café', Charset::toUtf8("caf\xE9"));
    }

    public function testUnknownCharsetLeavesBodyUnchanged(): void
    {
        self::assertSame('abc', Charset::toUtf8('abc', 'text/html; charset=x-made-up'));
    }

    public function testCharsetFarBeyondSniffWindowIsIgnored(): void
    {
        // A meta tag after 4 KB of padding is not looked at; valid UTF-8 stays as is.
        $page = str_repeat(' ', 5000) . '<meta charset="windows-1251">' . self::TEXT;

        self::assertSame($page, Charset::toUtf8($page));
    }
}
