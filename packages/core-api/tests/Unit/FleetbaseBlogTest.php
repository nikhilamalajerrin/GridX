<?php

use GridX\Support\GridXBlog;

function gridxBlogRssFixture(): string
{
    return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:media="http://search.yahoo.com/mrss/" version="2.0">
  <channel>
    <item>
      <title><![CDATA[First Ghost Post]]></title>
      <description><![CDATA[<p>First excerpt.</p>]]></description>
      <link>https://blog.gridx.io/first-ghost-post/</link>
      <guid isPermaLink="false">ghost-post-1</guid>
      <dc:creator><![CDATA[GridX Team]]></dc:creator>
      <pubDate>Wed, 06 May 2026 14:31:46 GMT</pubDate>
      <media:content url="https://static.ghost.org/first.jpg" />
      <media:thumbnail url="https://static.ghost.org/first-thumb.jpg" />
    </item>
    <item>
      <title><![CDATA[Second Ghost Post]]></title>
      <description><![CDATA[<p>Second excerpt.</p>]]></description>
      <link>https://blog.gridx.io/second-ghost-post/</link>
      <guid isPermaLink="false">ghost-post-2</guid>
      <dc:creator><![CDATA[GridX Team]]></dc:creator>
      <pubDate>Wed, 06 May 2026 14:30:46 GMT</pubDate>
    </item>
  </channel>
</rss>
XML;
}

test('gridx blog parser maps ghost rss posts to the widget response shape', function () {
    $posts = GridXBlog::parseRss(gridxBlogRssFixture(), 6);

    expect($posts)->toHaveCount(2)
        ->and($posts[0])->toMatchArray([
            'title'           => 'First Ghost Post',
            'link'            => 'https://www.gridx.io/blog/first-ghost-post',
            'guid'            => 'ghost-post-1',
            'description'     => '<p>First excerpt.</p>',
            'pubDate'         => 'Wed, 06 May 2026 14:31:46 GMT',
            'published_at'    => '2026-05-06T14:31:46+00:00',
            'author'          => 'GridX Team',
            'media_content'   => 'https://static.ghost.org/first.jpg',
            'media_thumbnail' => 'https://static.ghost.org/first-thumb.jpg',
        ]);
});

test('gridx blog parser clamps limit to a small safe range', function () {
    $posts = GridXBlog::parseRss(gridxBlogRssFixture(), 1);

    expect($posts)->toHaveCount(1)
        ->and($posts[0]['title'])->toBe('First Ghost Post');
});

test('gridx blog parser returns an empty array for malformed rss', function () {
    $posts = GridXBlog::parseRss('<rss><channel><item>', 6);

    expect($posts)->toBe([]);
});

test('gridx blog link normalization keeps non ghost links unchanged', function () {
    expect(GridXBlog::normalizeLink('https://www.gridx.io/blog/already-canonical'))->toBe('https://www.gridx.io/blog/already-canonical')
        ->and(GridXBlog::normalizeLink('https://gridx.ghost.io/legacy-ghost-post/'))->toBe('https://www.gridx.io/blog/legacy-ghost-post')
        ->and(GridXBlog::normalizeLink('https://blog.gridx.io/ghost-post/'))->toBe('https://www.gridx.io/blog/ghost-post');
});
