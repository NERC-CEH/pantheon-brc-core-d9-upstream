<?php

namespace Drupal\tmgmt_transifex\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\tmgmt\Entity\Translator;
use Drupal\tmgmt_transifex\Utils\Helpers;
use Drupal\tmgmt\Tests\TMGMTTestBase;


/**
 * Test for all basic parts of the Transifex drupal module.
 * @group tmgmt_mygengo
 */
class TMGMTTransifexTest extends TMGMTTestBase {
    
    /**
     * @var \Drupal\tmgmt\Entity\Translator $translator
     */
    protected $translator;

    public static $modules = array(
      'tmgmt_transifex'
    );

    public function setUp() {
      parent::setUp();
      $this->translator = Translator::load('transifex');
    }

    /**
     * Test slug extraction.
     */
    public function testExtractSlugFromUrl() {
        $urls = array(
            'http://www.test.com/a/b/' => 'b',
            'http://www.test.com/a/b/c/' => 'c',
            'http://www.test.com/a/b' => 'b',
            'http://www.test.com/a/b/dashboard' => 'b',
            'http://www.test.com/a/b/dashboard/' => 'b'
        );
        foreach ($urls as $url => $slug) {
            $result = Helpers::extractSlugFromUrl($url);
            $this->assertEqual($result, $slug);
        }
    }

    /**
     * Test getting remote translations.
     */
    public function testRemoteTranslations() {
        $translations = array(
            '<div><div class="tx_string" id="test_0"><div class="tx_string_sentence">Hello you</div></div></div>' => array(
                'id' => 'test',
                'offset' => '0',
                'text' => 'Hello you'
            ),
            '<div><div class="tx_string" id="foo_1"><div class="tx_string_sentence">l3tt3Rs</div></div></div>' => array(
                'id' => 'foo',
                'offset' => '1',
                'text' => 'l3tt3Rs'
            ),
            '<div><div class="tx_string" id="bar_10"><div class="tx_string_sentence">Καλησπέρα</div></div></div>' => array(
                'id' => 'bar',
                'offset' => '10',
                'text' => 'Καλησπέρα'
            )
        );

        foreach ($translations as $html => $vals) {
            $parsed = Helpers::parseRemoteTranslations($html);
            $this->assertTrue(array_key_exists($vals['id'], $parsed));
            $this->assertTrue(array_key_exists($vals['offset'], $parsed[$vals['id']]));
            $this->assertEqual($vals['text'], $parsed[$vals['id']][$vals['offset']]['value']['#text']);
        }
    }

    /**
     * Test HTML rendering.
     */
    public function testHTMLRendering() {
        // Test with single text
        $html = Helpers::renderHTML("τίτλος", "κείμενο");
        $this->assertTrue(strpos($html, "id=\"τίτλος\"") > 0);
        $this->assertTrue(strpos($html, "κείμενο") > 0);

        $html = Helpers::renderHTML("test", [
            "κείμενο",
            "Full, sentence.",
            "123"
        ]);
        $this->assertTrue(strpos($html, "id=\"test\"") > 0);
        $this->assertTrue(strpos($html, "κείμενο") > 0);
        $this->assertTrue(strpos($html, "Full, sentence.") > 0);
        $this->assertTrue(strpos($html, "123") > 0);
        $this->assertTrue(strpos($html, "tx_string_sentence") > 0);
    }
}
