<?php
namespace Drupal\tmgmt_transifex\Transifex;

use Drupal\Component\Serialization\Json;
use Drupal\tmgmt_transifex\Utils\Helpers;
use Drupal\tmgmt\TranslatorInterface;

/**
* Responsible for requests to the Transifex API.
*
* Currently allowed calls have to do with projects, resources, stats and translations.
* The class get all information about the Transifex project and its API settings, the
* moment it gets inittiated.
*/

const ENDPOINT_URL = 'https://www.transifex.com/api/2/'; # for production
//const ENDPOINT_URL = 'http://nginx:8000/api/2/'; # for local

class TransifexApi
{
    const METADATA_NAME = "version";
    const METADATA_NAMESPACE = "__drupal__";
    public $translator;

    /**
    * Constructor
    *
    * @param TranslatorInterface $translator
    */
    public function __construct($translator)
    {
        $this->translator = $translator;
    }

    /**
    * Makes a request to the Transifex API.
    *
    * @param string $method The HTTP method
    * @param string $url The URL to make the request
    * @param array $data Array that holds the post data
    */
    public function doRequest($method, $url, $data = null)
    {
        $data_string = json_encode($data);
        $url = join('', array(
            ENDPOINT_URL,
            $url
        ));
        $agent = array(
            "tmgmt-transifex-drupal-version/" . \Drupal::VERSION,
            "tmgmt-transifex-user/" . md5($this->translator->getSetting('api'))
        );
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, implode(" ", $agent));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_string),
            'Authorization: ' . 'Basic ' . base64_encode('api:' . $this->translator->getSetting('api'))
        ));

        $response = curl_exec($ch);
        if (curl_error($ch)) {
            \Drupal::logger('tmgmt_transifex')->error(
            'Curl error %ce',
             array(
                 '%ce' => curl_error($ch)),
                 $link = null
            );
            return curl_error($ch);
        }
        curl_close($ch);
        return $response;
    }

    /**
    * Make a request for a  specific Transifex project
    * @param $method The requets method
    * @param $url The project URL
    * @param null $data
    * @param array $headers Headers to add to the request
    *
    * @return mixed|string
    */
    public function doRequestToProject($method, $url, $data=null, $headers = array())
    {
        $url = join('', ['project/', Helpers::extractSlugFromUrl(
            $this->translator->getSetting('project')
        ), '/', $url
        ]);
        return $this->doRequest($method, $url, $data);
    }

    /**
    * Get information about a Transifex resource
    *
    * @param $slug
    *
    * @return mixed
    */
    public function getResource($slug)
    {
        $url = 'resource/' . $slug . '/';
        return json_decode($this->doRequestToProject('GET', $url));
    }

    /**
    * Get stats about a Transifex resource
    *
    * @param string $slug The resource's slug
    * @param string $tx_language The langage code to get stats for
    *
    * @return mixed
    */
    public function getStats($slug, $tx_language)
    {
        $url = 'resource/' . $slug . '/stats/' . $tx_language . '/';
        return json_decode($this->doRequestToProject('GET', $url));
    }

    /**
    * Get translations for a given Transifex resource
    *
    * @param $slug The resource's slug
    * @param $tx_language$ The langage code to get translations for
    *
    * @return mixed
    */
    public function getTranslations($slug, $tx_language)
    {
        $url = 'resource/' . $slug . "/" . "translation/" . $tx_language . '/';
        return json_decode($this->doRequestToProject('GET', $url));
    }

    /**
    * Create a new resource in Transifex associated with the given translation
    * job id
    *
    * @param $slug The resource's slug
    * @param $name The resource's name
    * @param $tjid Tmgmt's job id
    * @param $content Resource's content
    *
    * @return mixed|string
    */
    public function createResource($slug, $name, $tjid, $content)
    {
        return $this->doRequestToProject('POST', 'resources', array(
            'name' => $name,
            'slug' => $slug,
            'content' => $content,
            'i18n_type' => 'HTML',
            'category' => 'tjid:' . $tjid,
            'metadata' => json_encode(
                                array(
                                    array(
                                        'namespace' => self::METADATA_NAMESPACE,
                                        'name' => self::METADATA_NAME,
                                        'value' => \Drupal::VERSION
                                    )
                                )
                            )
        ));
    }

    /**
    * Update an existing resource in Transifex
    *
    * @param $slug The resource's slug
    * @param $name The resource's name
    * @param $categories
    * @param $content Resource's content
    *
    * @return mixed|string
    */
    public function updateResource($slug, $name, $categories, $content)
    {
        // First update resource details
        $this->doRequestToProject('PUT', 'resource/' . $slug, array(
      'name' => $name,
      'categories' => $categories
    ));
        // Then update source strings
        return $this->doRequestToProject(
            'PUT', 'resource/' . $slug . '/content', array(
                'content' => $content,
                'metadata' => json_encode(
                    array(
                        array(
                            'namespace' => self::METADATA_NAMESPACE,
                            'name' => self::METADATA_NAME,
                            'value' => \Drupal::VERSION
                        )
                    )
                )
    ));
    }

    /**
    * Create or update resource with the given slug on Transifex
    *
    * @param $slug The resource's slug
    * @param $name The resource's name
    * @param $tjid Tmgmt's job id
    * @param $content Resource's content
    *
    * @return mixed|string
    */
    public function upsertResource($slug, $name, $tjid, $content)
    {
        $ret = Json::decode($this->createResource($slug, $name, $tjid, $content));
        if (!isset($ret)) {
            $ret = $this->getResource($slug);
            if ($ret->categories === null || !in_array('tjid:' . $tjid, $ret->categories)) {
                $ret->categories[] = 'tjid:' . $tjid;
            }
            $ret = $this->updateResource($slug, $name, $ret->categories, $content);
            $ret = Json::decode($ret);
        }
        return $ret;
    }

    /**
    * Return information about a Transifex project
    *
    * @param TranslatorInterface translator
    * @return mixed
    */
    public function getProject(TranslatorInterface $translator)
    {
        $request = $this->doRequestToProject('GET', '');
        return json_decode($request);
    }

    public function getRemoteTranslations($translator, $resource, $tx_language)
    {
        $content = $this->getTranslations($resource, $tx_language)->content;
        return Helpers::parseRemoteTranslations($content);
    }
}
