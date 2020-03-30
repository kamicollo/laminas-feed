<?php

/**
 * @see       https://github.com/laminas/laminas-feed for the canonical source repository
 * @copyright https://github.com/laminas/laminas-feed/blob/master/COPYRIGHT.md
 * @license   https://github.com/laminas/laminas-feed/blob/master/LICENSE.md New BSD License
 */

namespace LaminasTest\Feed\Reader\Integration;

use Laminas\Feed\Reader;
use PHPUnit\Framework\TestCase;

/**
 * @group Laminas_Feed
 * @group Laminas_Feed_Reader
 */
class HOnlineComAtom10Test extends TestCase
{
    protected $feedSamplePath;

    protected function setUp(): void
    {
        Reader\Reader::reset();
        $this->feedSamplePath = dirname(__FILE__) . '/_files/h-online.com-atom10.xml';
    }

    public function testGetsTitle()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals('The H - news feed', $feed->getTitle());
    }

    public function testGetsAuthors()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals([['name' => 'The H']], (array) $feed->getAuthors());
    }

    public function testGetsSingleAuthor()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals(['name' => 'The H'], $feed->getAuthor());
    }

    public function testGetsCopyright()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals(null, $feed->getCopyright());
    }

    public function testGetsDescription()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals('Technology news', $feed->getDescription());
    }

    public function testGetsLanguage()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals(null, $feed->getLanguage());
    }

    public function testGetsLink()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals('http://www.h-online.com', $feed->getLink());
    }

    public function testGetsEncoding()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals('UTF-8', $feed->getEncoding());
    }

    public function testGetsEntryCount()
    {
        $feed = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $this->assertEquals(60, $feed->count());
    }

    /**
     * Entry level testing
     */
    public function testGetsEntryId()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(
            'http://www.h-online.com/security/McAfee-update-brings-systems-down-again--/news/113689/from/rss',
            $entry->getId()
        );
    }

    public function testGetsEntryTitle()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals('McAfee update brings systems down again', $entry->getTitle());
    }

    public function testGetsEntryAuthors()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals([['name' => 'The H']], (array) $entry->getAuthors());
    }

    public function testGetsEntrySingleAuthor()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(['name' => 'The H'], $entry->getAuthor());
    }

    public function testGetsEntryDescription()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();

        /**
         * Note: "’" is not the same as "'" - don't replace in error
         */
        $this->assertEquals(
            'A McAfee signature update is currently causing system failures and a lot of overtime for administrators',
            $entry->getDescription()
        );
    }

    public function testGetsEntryContent()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(
            'A McAfee signature update is currently causing system failures and a lot of overtime for administrators',
            $entry->getContent()
        );
    }

    public function testGetsEntryLinks()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(
            ['http://www.h-online.com/security/McAfee-update-brings-systems-down-again--/news/113689/from/rss'],
            $entry->getLinks()
        );
    }

    public function testGetsEntryLink()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(
            'http://www.h-online.com/security/McAfee-update-brings-systems-down-again--/news/113689/from/rss',
            $entry->getLink()
        );
    }

    public function testGetsEntryPermaLink()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals(
            'http://www.h-online.com/security/McAfee-update-brings-systems-down-again--/news/113689/from/rss',
            $entry->getPermaLink()
        );
    }

    public function testGetsEntryEncoding()
    {
        $feed  = Reader\Reader::importString(
            file_get_contents($this->feedSamplePath)
        );
        $entry = $feed->current();
        $this->assertEquals('UTF-8', $entry->getEncoding());
    }
}
