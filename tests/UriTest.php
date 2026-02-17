<?php

declare(strict_types=1);

namespace AsceticSoft\Psr7\Tests;

use AsceticSoft\Psr7\Uri;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uri::class)]
final class UriTest extends TestCase
{
    public function testParsesFullUri(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?query=1#fragment');

        self::assertSame('https', $uri->getScheme());
        self::assertSame('user:pass', $uri->getUserInfo());
        self::assertSame('example.com', $uri->getHost());
        self::assertSame(8080, $uri->getPort());
        self::assertSame('/path', $uri->getPath());
        self::assertSame('query=1', $uri->getQuery());
        self::assertSame('fragment', $uri->getFragment());
        self::assertSame('user:pass@example.com:8080', $uri->getAuthority());
    }

    public function testEmptyUri(): void
    {
        $uri = new Uri();

        self::assertSame('', $uri->getScheme());
        self::assertSame('', $uri->getHost());
        self::assertNull($uri->getPort());
        self::assertSame('', $uri->getPath());
        self::assertSame('', $uri->getQuery());
        self::assertSame('', $uri->getFragment());
        self::assertSame('', $uri->getAuthority());
        self::assertSame('', (string) $uri);
    }

    public function testInvalidUriThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Uri('http:///');
    }

    public function testSchemeIsNormalized(): void
    {
        $uri = new Uri('HTTP://example.com');

        self::assertSame('http', $uri->getScheme());
    }

    public function testHostIsNormalized(): void
    {
        $uri = new Uri('http://EXAMPLE.COM');

        self::assertSame('example.com', $uri->getHost());
    }

    public function testDefaultPortIsOmitted(): void
    {
        $uri = new Uri('http://example.com:80');
        self::assertNull($uri->getPort());

        $uri = new Uri('https://example.com:443');
        self::assertNull($uri->getPort());
    }

    public function testNonDefaultPortIsPreserved(): void
    {
        $uri = new Uri('http://example.com:8080');
        self::assertSame(8080, $uri->getPort());
    }

    public function testWithScheme(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withScheme('https');

        self::assertNotSame($uri, $new);
        self::assertSame('https', $new->getScheme());
        self::assertSame('http', $uri->getScheme());
    }

    public function testWithSchemeReturnsSameForIdentical(): void
    {
        $uri = new Uri('http://example.com');
        self::assertSame($uri, $uri->withScheme('http'));
    }

    public function testWithHost(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withHost('other.com');

        self::assertSame('other.com', $new->getHost());
    }

    public function testWithPort(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withPort(9090);

        self::assertSame(9090, $new->getPort());
    }

    public function testWithPortNull(): void
    {
        $uri = new Uri('http://example.com:8080');
        $new = $uri->withPort(null);

        self::assertNull($new->getPort());
    }

    public function testWithPortInvalidThrows(): void
    {
        $uri = new Uri('http://example.com');

        $this->expectException(\InvalidArgumentException::class);
        $uri->withPort(70000);
    }

    public function testWithPath(): void
    {
        $uri = new Uri('http://example.com/old');
        $new = $uri->withPath('/new');

        self::assertSame('/new', $new->getPath());
    }

    public function testWithQuery(): void
    {
        $uri = new Uri('http://example.com?old=1');
        $new = $uri->withQuery('new=2');

        self::assertSame('new=2', $new->getQuery());
    }

    public function testWithFragment(): void
    {
        $uri = new Uri('http://example.com#old');
        $new = $uri->withFragment('new');

        self::assertSame('new', $new->getFragment());
    }

    public function testWithUserInfo(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withUserInfo('user', 'pass');

        self::assertSame('user:pass', $new->getUserInfo());
        self::assertSame('user:pass@example.com', $new->getAuthority());
    }

    public function testWithUserInfoWithoutPassword(): void
    {
        $uri = new Uri('http://example.com');
        $new = $uri->withUserInfo('user');

        self::assertSame('user', $new->getUserInfo());
    }

    public function testWithUserInfoRemoves(): void
    {
        $uri = new Uri('http://user:pass@example.com');
        $new = $uri->withUserInfo('');

        self::assertSame('', $new->getUserInfo());
    }

    public function testToStringFull(): void
    {
        $uri = new Uri('https://user:pass@example.com:8080/path?q=1#frag');

        self::assertSame('https://user:pass@example.com:8080/path?q=1#frag', (string) $uri);
    }

    public function testToStringCachesResult(): void
    {
        $uri = new Uri('http://example.com/path');
        $str1 = (string) $uri;
        $str2 = (string) $uri;

        self::assertSame($str1, $str2);
        self::assertSame('http://example.com/path', $str1);
    }

    public function testToStringInvalidatesCacheOnWith(): void
    {
        $uri = new Uri('http://example.com/path');
        $str1 = (string) $uri;

        $new = $uri->withPath('/other');
        $str2 = (string) $new;

        self::assertSame('http://example.com/path', $str1);
        self::assertSame('http://example.com/other', $str2);
    }

    public function testPathPrefixedWithSlashWhenAuthorityPresent(): void
    {
        $uri = new Uri()
            ->withHost('example.com')
            ->withPath('path');

        self::assertSame('//example.com/path', (string) $uri);
    }

    public function testPathLeadingSlashesReducedWhenNoAuthority(): void
    {
        $uri = new Uri()->withPath('//path');

        self::assertSame('/path', (string) $uri);
    }

    public function testPathEncodingDoesNotDoubleEncode(): void
    {
        $uri = new Uri('http://example.com/p%61th');

        self::assertSame('/p%61th', $uri->getPath());
    }

    public function testQueryEncodingDoesNotDoubleEncode(): void
    {
        $uri = new Uri('http://example.com?q=%61');

        self::assertSame('q=%61', $uri->getQuery());
    }

    public function testSchemeChangeRemovesDefaultPort(): void
    {
        $uri = new Uri('http://example.com:443');
        self::assertSame(443, $uri->getPort());

        $new = $uri->withScheme('https');
        self::assertNull($new->getPort());
    }

    public function testAuthorityEmptyWhenNoHost(): void
    {
        $uri = new Uri('/path?query');

        self::assertSame('', $uri->getAuthority());
    }
}
