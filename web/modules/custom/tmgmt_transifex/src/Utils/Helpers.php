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
        $slug = $prefix . '_' . $job_item->getItemId() . "_" . $job_item->getItemType();
        $slug = str_replace(':', '_', $slug);
        return $slug;
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
            list($context, $comment) = Helpers::get_context_and_comment($job_item, $fieldName);

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
                            'value' => $value[$propCount]['value']['#text'],
                            'context' => $context,
                            'developer_comment' => $comment
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
                            'value' => $Sentence->split($value[$propCount]['alias']['#text']),
                            'context' => $context,
                            'developer_comment' => $comment
                        );
                        // Create a string about the URL langcode
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount . '_langcode',
                            'value' => $Sentence->split($value[$propCount]['langcode']['#text']),
                            'context' => $context,
                            'developer_comment' => $comment
                        );
                    } else {
                        $Sentence	= new Sentence;
                        $translation_payload[] = array(
                            'key' => $fieldName . '_' . $propCount,
                            'value' => $Sentence->split($value[$propCount]['value']['#text']),
                            'context' => $context,
                            'developer_comment' => $comment
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
                        'value' => html_entity_decode($value['#text']),
                        'context' => $context,
                        'developer_comment' => $comment
                    );
                } else {
                    $Sentence = new Sentence;
                    $translation_payload[] = [
                        'key' => $fieldName,
                        'value' => $Sentence->split($value['#text']),
                        'context' => $context,
                        'developer_comment' => $comment
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
            $html .= Helpers::renderHTML(
                'title_field_0',
                $strings[$title_index]['value'],
                $strings[$title_index]['context'],
                $strings[$title_index]['developer_comment']
            );
            unset($strings[$title_index]);
        }
        // Get the rest
        foreach ($strings as $string) {
            $html .= Helpers::renderHTML(
                $string['key'],
                $string['value'],
                $string['context'],
                $string['developer_comment']
            );
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
    public static function renderHTML($label, $text, $context, $developer_comment)
    {
        if (!is_array($text)) {
            $result = "<div class=\"tx_string\" id=\"" . $label . "\"";
            if ($context) {
              $result = $result . " tx-context=\"" . $context . "\"";
            }
            if ($developer_comment) {
              $result = $result . " tx-comment=\"" . $developer_comment . "\"";
            }
            $result = $result . ">" . $text . "</div>";
            return $result;

          } else {
            $result = "<div class=\"tx_string\" id=\"" . $label . "\"";
            if ($context) {
              $result = $result . " tx-context=\"" . $context . "\"";
            }
            if ($developer_comment) {
              $result = $result . " tx-comment=\"" . $developer_comment . "\"";
            }
            $result = $result . ">";

            foreach ($text as $sentence) {
              $result .= "<div class=\"tx_string_sentence\">" . nl2br($sentence) . '</div>';
            }
            return $result . "</div>";
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
        $doc->loadHTML(mb_convert_encoding(
            Helpers::escape_brackets($content), 'HTML-ENTITIES', 'UTF-8'
          ));
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
            $tr = Helpers::unescape_brackets($tr);
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

    protected static function escape_brackets($content) {
        $result = str_replace('[', '__TX_OPENING_SQUARE_BRACKET__', $content);
        $result = str_replace(']', '__TX_CLOSING_SQUARE_BRACKET__', $result);
        return $result;
    }

    protected static function unescape_brackets($content) {
        $result = str_replace('__TX_OPENING_SQUARE_BRACKET__', '[', $content);
        $result = str_replace('__TX_CLOSING_SQUARE_BRACKET__', ']', $result);
        return $result;
    }

    protected static function get_context_and_comment($job_item, $fieldName) {
        if ($job_item->plugin == 'locale') {
            $locale_object = Helpers::getLocaleObject($job_item);
            return array($locale_object->context, $locale_object->location);
        } elseif ($job_item->plugin == 'i18n_string') {
            list(, $type, $object_id) = explode(':', $job_item->item_id, 3);
            $wrapper = tmgmt_i18n_string_get_wrapper(
                $job_item->item_type,
                (object) array('type' => $type, 'objectid' => $object_id)
            );
            $i18n_strings = $wrapper->get_strings();
            $i18n_string = NULL;
            foreach ($i18n_strings as $key => $value) {
                if ($key == $fieldName) {
                    $i18n_string = $value;
                    break;
                }
            }
            return array($i18n_string->context, $i18n_string->location);
        } else {
            return array('', '');
        }
    }

    protected static function getLocaleObject(TMGMTJobItem $job_item) {
        # Copied from tmgmt/sources/locale/tmgmt_locale.plugin.inc
        $locale_lid = $job_item->item_id;

        // Check existence of assigned lid.
        $exists = db_query("SELECT COUNT(lid) FROM {locales_source} WHERE lid = :lid", array(':lid' => $locale_lid))->fetchField();
        if (!$exists) {
            throw new TMGMTException(t('Unable to load locale with id %id', array('%id' => $job_item->item_id)));
        }

        // This is necessary as the method is also used in the getLabel() callback
        // and for that case the job is not available in the cart.
        if (!empty($job_item->tjid)) {
            $source_language = $job_item->getJob()->source_language;
        }
        else {
            $source_language = $job_item->getSourceLangCode();
        }

        if ($source_language == 'en') {
            $query = db_select('locales_source', 'ls');
            $query
                ->fields('ls')
                ->condition('ls.lid', $locale_lid);
            $locale_object = $query
                ->execute()
                ->fetchObject();

            $locale_object->language = 'en';

            if (empty($locale_object)) {
                return null;
            }

            $locale_object->origin = 'source';
        }
        else {
            $query = db_select('locales_target', 'lt');
            $query->join('locales_source', 'ls', 'ls.lid = lt.lid');
            $query
                ->fields('lt')
                ->fields('ls')
                ->condition('lt.lid', $locale_lid)
                ->condition('lt.language', $source_language);
            $locale_object = $query
                ->execute()
                ->fetchObject();

            if (empty($locale_object)) {
                return null;
            }

            $locale_object->origin = 'target';
        }

        return $locale_object;
    }
}
