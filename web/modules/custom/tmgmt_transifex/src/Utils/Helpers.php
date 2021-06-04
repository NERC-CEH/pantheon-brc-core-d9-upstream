<?php
namespace Drupal\tmgmt_transifex\Utils;

use Drupal\tmgmt\JobInterface;

/**
* Class that holds helper functions
*
* Currently it includes functions that have do with slug creation, parsing and handling
* HTML from / to strings. 
*/
class Helpers
{
    const PATH_KEY = "path";
    /**
    * Parse the project URL from settings and extract the project slug.
    *
    * @param string $url The url to check
    * @return string The project slug
    */
    public static function extractSlugFromUrl($url)
    {
        $exploded = array_filter(explode('/', $url));
        if (strcmp(end($exploded), 'dashboard') == 0) {
            array_pop($exploded);
        }
        return end($exploded);
    }

    /**
    * Construct resource slug for a tmgmt job
    *
    * The slug will have the format `txdrupal_nodeId_nodeType`.
    *
    * @param JobItem $job_item
    * @return string The slug for a specific job item
    */
    public static function slugForItem(\Drupal\tmgmt\Entity\JobItem $job_item)
    {
        $prefix = "txdrupal";
        /* The slug prefix needs to hold information about the item id and its type, so
           we can map back a resource to the correct node. If two jobs however have to
           do with the same node (for example translating both the copy and the link title), the
           two resources will have the same slug and overwrite one the other. For this reason
           we need to add another parameter to the slug to distinguish two jobs -- the
           item type. 
        */
        return join(
            '_',
            array($prefix, str_replace(':', '_', $job_item->getItemId() . "_" . 
                  $job_item->getItemType()
              )
            )
        );
    }
    
    /**
    * Extract the node id and item type from the Transifex slug
    *
    * Resource's slug in Transifex has the following format `txdrupal_nodeId_nodeType`
    *
    * @param string $slug The resource's slug that holds information about the node id
    * @return string The node id
    */
    public static function nodeInfoFromSlug($slug){
        $infoArray = explode('_', $slug, 3);
        return array(
            "nodeId" => str_replace('_', ':', $infoArray[1]),
            "nodeItemType" =>$infoArray[2]
        );
    }

    /**
    * Generate HTML from a DOM element.
    *
    * @param \DOMNode $element
    * @return string The inner HTML
    */
    public static function DOMinnerHTML(\DOMNode $element)
    {
        $innerHTML = "";

        foreach ($element->childNodes as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    /**
    * Given a node, generate an array of strings that will be sent to Transifex
    * for translation.
    *
    * @param JobItem $job_item
    *
    * @return array
    * The array of strings existing in the node in the form of:
    * array(
    *   array(
    *     'key' => 'node_key',
    *     'value' => 'node content'
    *   )
    * )
    */
    public static function extractStringsFromNode(\Drupal\tmgmt\Entity\JobItem $job_item)
    {
        $translation_payload = array();
        $ignore_keys_array = Helpers::getKeysToIgnore();
        foreach ($job_item->getData() as $fieldName => $value) {

            // Ignore any keys that we don't care about translating
            if (in_array($fieldName, $ignore_keys_array)) {
                continue;
            }
            $propCount = 0;
            if (isset($value[$propCount])) {
                while (isset($value[$propCount])) {
                    if (in_array($value[$propCount]['format']['#text'], array('full_html', 'filtered_html'))) {
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount,
                            'value' => $value[$propCount]['value']['#text']
                        );
                    } elseif (Helpers::isPathKey($fieldName)) {
                        /*
                        The URL path has a different object structure than the rest and consists
                        of two translatable strings, URL alias and URL langcode.
                        */
                        $Sentence	= new Sentence;
                        // Create a string about the URL alias
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount . '_alias',
                            'value' => $Sentence->split($value[$propCount]['alias']['#text'])
                        );
                        // Create a string about the URL langcode
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount . '_langcode',
                            'value' => $Sentence->split($value[$propCount]['langcode']['#text'])
                        );
                    } else {
                        $Sentence	= new Sentence;
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount,
                            'value' => $Sentence->split($value[$propCount]['value']['#text'])
                        );
                    }
                    $propCount++;
                }
            } else {
                /*
                Not all job items include information about the text_format, in fact,
                most I18n String items do not. For those cases, we do a simple check
                to see if the text has html tags, and if it does, we handle it as a
                full_html text format instead of plain_text.
                */
                //Check if text has html tags. If it does, treat as full_html.
                if ($value['#text'] != strip_tags($value['#text'])) {
                    $translation_payload[] = array(
                        'key' => $fieldName,
                        'value' => html_entity_decode($value['#text'])
                    );
                } else {
                    $Sentence = new Sentence;
                    $translation_payload[] = [
                        'key' => $fieldName,
                        'value' => $Sentence->split($value['#text'])
                    ];
                }
            }
        }
        return $translation_payload;
    }
  
    /**
    * Check if a given key is matches the path node
    *
    * @param $key The name of the key to check
    * @return boolean True is the key is the path, false otherwise
    */
    private static function isPathKey($key)
    {
        if ($key == self::PATH_KEY) {
            return true;
        }
        return false;
    }

    /**
    * Generate HTML from an array of strings.
    *
    * @param $strings
    * An array of key, value strings to be encoded as an html document
    *
    * @return string
    * The final html containing all the strings as <div> elements with the
    * content in the div body and the key as the id attribute of the div
    */
    public static function renderHTMLFromStrings($strings)
    {
        $html = '';
        $title_index = array_search(
            'title_field_0',
            array_column($strings, 'key')
        );
        if ($title_index) {
            $html .= Helpers::renderHTML('title_field_0', $strings[$title_index]['value']);
            unset($strings[$title_index]);
        }
        // Get the rest
        foreach ($strings as $string) {
            $html .= Helpers::renderHTML($string['key'], $string['value']);
        }
        return $html;
    }
  
    /**
    * Return an array of keys to ignore when extracting strings for a node
    *
    * @return array
    */
    private static function getKeysToIgnore()
    {
        return array(
            'revision_translation_affected',
            'content_translation_created',
            'content_translation_status',
            'content_translation_uid',
            'default_langcode',
            'content_translation_outdated',
            'changed', 'status',
            'field_tags',
            'comment', 'field_image',
            'sticky', 'uid',
            'promote', 'created',
        );
    }

    /**
    * Generate a <div> HTML element, given a label and its text.
    *
    * Examples of generated HTML elements
    * Example 1:
    * <div class="tx_string" id="label">Text</div>
    *
    * Example 2:
    * <div class="tx_string" id="label">
    *     <div class="tx_string_sentence">Text</div>
    *     <div class="tx_string_sentence">Text2</div>
    * </div>
    *
    * @param string $label
    * @param string $text
    * @return string The <div> HTML element.
    */
    public static function renderHTML($label, $text)
    {
        if (!is_array($text)) {
            return "<div class=\"tx_string\" id=\"" . $label . "\">" . $text . "</div>";
        } else {
            $ret = "<div class=\"tx_string\" id=\"" . $label . "\">";
            foreach ($text as $sentence) {
                $ret .= "<div class=\"tx_string_sentence\">" . nl2br($sentence) . "</div>";
            }
            return $ret . "</div>";
        }
    }
  
    /**
    * Given HTML content taken from Transifex, extract and return an array of translations
    *
    * @param sting $content The HTML content to parse
    * @return array $translations The array of translations for the given content
    */
    public static function parseRemoteTranslations($content)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new \DOMXPath($doc);

        $translations = [];
        foreach ($xpath->query("//*[@class='tx_string']") as $translation) {
            $sentences = [];
            # if content is part of a sentence get text of each node and join using a space.
            foreach ($xpath->query("*[@class='tx_string_sentence']", $translation) as $node) {
                array_push($sentences, $node->nodeValue);
            }
            if (count($sentences) == 0) {
                $tr = Helpers::DOMinnerHTML($translation);
            } else {
                $tr = join(' ', $sentences);
            }
            $key = $translation->getAttribute('id');
            $split = explode('_', $key);
            $propCount = array_pop($split);
            $propName = join('_', $split);
            // If the translation is not empty
            if (strlen(trim($tr)) != 0) {
                // Add translation
                if (strlen($propName) != 0) {
                    /** If the current node holds information about the path, then we need to
                    * save it in a different but similar array format in order tmgmt to save it
                    * back to the original object. So far only the path needs this "special"
                    * treatment.
                    */
                    if ($propName == "path_0") {
                        $split = explode('_', $propName);
                        $translations[$split[0]][$split[1]][$propCount] = array(
                            '#text' => $tr, '#status' => 2, '#origin' => 'transifex'
                        );
                    } else {
                        $translations[$propName][$propCount]['value']['#text'] = $tr;
                        // Mark as reviewed
                        $translations[$propName][$propCount]['value']['#status'] = 2;
                        $translations[$propName][$propCount]['value']['#origin'] = 'transifex';
                    }
                } else {
                    $translations[$key]['#text'] = $tr;
                    // Mark as reviewed
                    $translations[$key]['#status'] = 2;
                    $translations[$key]['#origin'] = 'transifex';
                }
            }
        }
        return $translations;
    }
}
