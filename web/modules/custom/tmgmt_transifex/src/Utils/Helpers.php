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
    const PATH_SEP = "__txsep__";
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

    public static function slug_escape($string) {
        $result = $string;
        $result = str_replace('_', '_tx_', $result);
        $result = str_replace(':', '__tx__', $result);
        $result = str_replace('.', '___tx___', $result);
        return $result;
    }

    public static function slug_unescape($string) {
        $result = $string;
        $result = str_replace('___tx___', '.', $result);
        $result = str_replace('__tx__', ':', $result);
        $result = str_replace('_tx_', '_', $result);
        return $result;
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
        $slug = $prefix . self::PATH_SEP . self::slug_escape($job_item->getItemId()) . self::PATH_SEP . self::slug_escape($job_item->getItemType());
        return $slug;
    }

    /**
    * Extract the node id and item type from the Transifex slug
    *
    * Resource's slug in Transifex has the following format `txdrupal_nodeId_nodeType`
    *
    * @param string $slug The resource's slug that holds information about the node id
    * @return string|array The node id
    */
    public static function nodeInfoFromSlug($slug){
        $slug = self::slug_unescape($slug);
        if (str_contains($slug, 'txdrupal_views')) {
            // Retract prefix and postfix
            $nodeId = substr($slug, 9);
            $nodeId = substr($nodeId, 0, -5);
            $nodeItemType = 'view';
        } else {
            $infoArray = explode(self::PATH_SEP, $slug, 3);
            $nodeId = $infoArray[1];
            $nodeItemType = $infoArray[2];
        }

        return array(
            "nodeId" => $nodeId,
            "nodeItemType" => $nodeItemType
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
    public static function extractStringsFromNode(\Drupal\tmgmt\Entity\JobItem $job_item) {
        $translation_payload = array();
        $ignore_keys_array = Helpers::getKeysToIgnore();
        // We are doing the first step of the recursion here in order to
        // capture the context and comment
        foreach ($job_item->getData() as $fieldName => $value) {
            if (in_array($fieldName, $ignore_keys_array)) {
                continue;
            }
            list($context, $comment) = Helpers::get_context_and_comment($job_item, $fieldName);
            $translation_payload = array_merge(
                $translation_payload,
                Helpers::recursiveExtract($value, $fieldName, $context, $comment)
            );
        }
        return $translation_payload;
    }

    public static function recursiveExtract($body, $path, $context, $comment) {
        /*  We generally want to capture structures like:
          *
          *   'label': { '#text': "source string" }
          * */
        $ignore_keys_array = Helpers::getKeysToIgnore();
        if (! is_array($body)) {
            return array();
        }
        if (
            array_key_exists('format', $body) &&
            array_key_exists('#text', $body['format']) &&
            in_array($body['format']['#text'],
                     array('full_html', 'filtered_html')) &&
            array_key_exists('value', $body) &&
            array_key_exists('#text', $body['value'])
        ) {
            /*  If we encounter:
              *
              *   'label': { 'format': { '#text': "full/filtered_html" },
              *              'value': { '#text': "source string" } }
              *
              * we want to capture the value as-is and let the Transifex parser
              * do the segmentation.
              * */
            return array(
                array(
                    'key' => $path,
                    'value' => $body['value']['#text'],
                    'context' => $context,
                    'developer_comment' => $comment
                )
            );
        }
        if (
            array_key_exists('alias', $body) &&
            array_key_exists('#text', $body['alias']) &&
            array_key_exists('langcode', $body) &&
            array_key_exists('#text', $body['langcode'])
        ) {
            /*  If we encounter:
              *
              *   'label': { 'alias': { '#text': "source string" },
              *              'langcode': { '#text': "source string" } }
              *
              * we want to capture both texts using the $Sentence->split method
              * */
            $Sentence = new Sentence;
            return array(
                array(
                    'key' => $path . self::PATH_SEP . 'alias',
                    'value' => $Sentence->split($body['alias']['#text']),
                    'context' => $context,
                    'developer_comment' => $comment
                ),
                array(
                    'key' => $path . self::PATH_SEP . 'langcode',
                    'value' => $Sentence->split($body['langcode']['#text']),
                    'context' => $context,
                    'developer_comment' => $comment
                )
            );
        }
        if (array_key_exists('#text', $body)) {
            /*  Here we capture the generic form as shown in the original code
              * comment. If the text is not HTML, we want to use the
              * $Sentence->split method, otherwise, we pass the text as-is and
              * let the Transifex parser do the segmentation
              * */
            $parts = explode(self::PATH_SEP, $path);
            $last = $parts[count($parts) - 1];
            if (
                is_numeric($last) ||
                $body['#text'] == strip_tags($body['#text'])
            ) {
                $Sentence = new Sentence;
                $value = $Sentence->split($body['#text']);
            } else {
                $value = html_entity_decode($body['#text']);
            }
            return array(
                array(
                    'key' => $path,
                    'value' => $value,
                    'context' => $context,
                    'developer_comment' => $comment
                )
            );
        }
        if (is_array($body)) {
            /*  Here we do the recursion. We join the path so far with the key
              * in order to not lose information on how the original
              * $job_item->getData() was structured.
              * */
            $result = array();
            foreach ($body as $key => $value) {
                if (in_array($key, $ignore_keys_array)) {
                    continue;
                }
                $result = array_merge(
                    $result,
                    Helpers::recursiveExtract($value, $path . self::PATH_SEP . $key, $context, $comment)
                );
            }
            return $result;
        }
        return array();
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

            if (strlen(trim($tr)) != 0) {
                $key = $translation->getAttribute('id');
                $split = explode(self::PATH_SEP, $key);

                /*  Here we attempt to construct our $translations to mirror
                  * the structure of the original $job_item->getData(). We used
                  * self::PATH_SEP in the id to record the full path to the
                  * node that housed the original source string, so we do the
                  * reverse to place the translation in the appropriate node,
                  * no matter how deep it is.
                  * */
                $translation_node =& $translations;
                foreach($split as $part) {
                    if (! array_key_exists($part, $translation_node)) {
                        $translation_node[$part] = Array();
                    }
                    $translation_node =& $translation_node[$part];
                }

                $translation_node['#text'] = $tr;
                $translation_node['#status'] = 2;
                $translation_node['#origin'] = 'transifex';
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
