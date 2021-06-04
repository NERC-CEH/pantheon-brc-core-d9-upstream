<?php

/**
* Provides Transifex connector plugin.
*/

namespace Drupal\tmgmt_transifex\Plugin\tmgmt\Translator;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\tmgmt\ContinuousTranslatorInterface;
use Drupal\tmgmt\TranslatorPluginBase;
use Drupal\tmgmt\TranslatorInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt\Translator\AvailableResult;
use Drupal\tmgmt\Translator\TranslatableResult;
use Drupal\tmgmt\TMGMTException;
use Drupal\Core\Url;
use Drupal\tmgmt_transifex\Transifex\TransifexAPI;
use Drupal\tmgmt_transifex\Utils\Sentence;
use Drupal\tmgmt_transifex\Utils\Helpers;
use Drupal\tmgmt\Entity\Job;
use Drupal\Component\Serialization\Json;

/**
* Transifex translator plugin.
*
* The docstring below will create a new translator entry in the database for the
* Transifex plugin, when the user installs the module for the first time.
*
* *
* @TranslatorPlugin(
*   id = "transifex",
*   label = @Translation("Transifex"),
*   description = @Translation("Transifex Drupal connector."),
*   ui = "Drupal\tmgmt_transifex\TransifexTranslatorUi",
*   logo = "icons/transifex.png"
* )
*/
class TransifexTranslator extends TranslatorPluginBase implements ContainerFactoryPluginInterface, ContinuousTranslatorInterface
{

    /**
    * Constructor
    * @param array $configuration
    *   A configuration array containing information about the plugin.
    * @param string $plugin_id
    *   The plugin ID for the plugin.
    * @param mixed $plugin_definition
    *   The plugin implementation definition.
    */
    public function __construct(array $configuration, $plugin_id, array $plugin_definition)
    {
        parent::__construct($configuration, $plugin_id, $plugin_definition);
    }

    /**
    * {@inheritdoc}
    */
    public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
    {
        return new static(
            $configuration,
            $plugin_id,
            $plugin_definition
      );
    }

    /**
    * Check if the Transifex integration is available to be used. This should be
    * extended to involve a test call to our service, but for now it just checks if
    * the API key is available.
    *
    * {@inheritdoc}
    */
    public function checkAvailable(TranslatorInterface $translator)
    {
        if ($this->isAvailable($translator)) {
            $result = AvailableResult::yes();
        } else {
            $result = AvailableResult::no(t('@translator is not configured. Please <a href=:configured>configure</a> the Transifex connector first.', [
              '@translator' =>  $translator->label(),
              ':configured' =>  $translator->url()
          ]));
        }
        return $result;
    }

    /**
    * Check if the source and translation languages are being supported by the Transifex project.
    *
    * {@inheritdoc}
    */
    public function checkTranslatable(TranslatorInterface $translator, JobInterface $job)
    {
        try {
            $languages = $this->getSupportedRemoteLanguages($translator);

            $source = $translator->mapToRemoteLanguage($job->getSourceLanguage()->getId());

            if (!isset($languages[$source])) {
                return TranslatableResult::no(t('The source language @language is not the Transifex source language', ['@language' => $job->source_language]));
            } elseif (!array_key_exists($translator->mapToRemoteLanguage($job->getTargetLanguage()->getId()), $languages)) {
                return TranslatableResult::no(t('The target language @language is not configured in the Transifex project', ['@language' => $job->target_language]));
            }
            return parent::checkTranslatable($translator, $job);
        } catch (TMGMTException $e) {
            return TranslatableResult::no(t('An error occured while checking the supported languages'));
        }
    }

    /**
    * Check for checkout specific settings.
    *
    * {@inheritdoc}
    */
    public function hasCheckoutSettings(JobInterface $job)
    {
        return false;
    }

    /**
    * Checks if the translation instance has a value for the the API setting.
    * If it does, it means that the integration has been setup.
    *
    * @param TranslatorInterface $translator The Translator instance
    * @return boolean True if the API settings exists, False othersise.
    */
    public function isAvailable(TranslatorInterface $translator)
    {
        if ($translator->getSetting('api')) {
            return true;
        }
        return false;
    }

    /**
    * Returns the supported target languages for the current project
    * for the Transifex translator.
    *
    * {@inheritdoc}
    */
    public function getSupportedTargetLanguages(TranslatorInterface $translator, $source_language)
    {
        return $this->getSupportedRemoteLanguages($translator);
    }

    /**
    * Gets all supported languages of the translator plugin.
    * It is used to show the remote language list in the configuration page.
    *
    * {@inheritdoc}
    */
    public function getSupportedRemoteLanguages(TranslatorInterface $translator)
    {
        $tx = new TransifexApi($translator);
        $request = $tx->doRequest('GET', 'languages');
        $languages = array();
        foreach (json_decode($request) as $language) {
            $languages[$language->code] = $language->code;
        }

        return $languages;
    }

    /**
    * Check in Transifex for translations for a specific job.
    *
    * This function is being used in various places in the front end
    * (when checking for translation status).
    *
    * @param JobInterface $job
    */
    public function checkForTranslations(JobInterface $job)
    {
        $translator = $job->getTranslator();
        $language = $translator->mapToRemoteLanguage($job->getTargetLanguage()->getId());
        $tx = new TransifexApi($translator);
        foreach ($job->getItems() as $tjiid => $job_item) {
            $target_resource = Helpers::slugForItem($job_item);
            if ($this->shouldManualUpdateTranslations(
                $translator, $target_resource, $language
            )){
                $this->updateJobWithTranslations(
                    $translator, $target_resource, $language, false
                );
            }
        }
    }

    /**
    * {@inheritdoc}
    */
    public function getDefaultRemoteLanguagesMappings()
    {
        return array(
            'zh-hans' => 'zh-CHS',
            'zh-hant' => 'zh-CHT',
        );
    }

    /**
    * Used when requesting a new job.
    *
    * {@inheritdoc}
    */
    public function requestTranslation(JobInterface $job)
    {
        try {
            $translator = $job->getTranslator();
            $tx = new TransifexApi($translator);
            $job_count = 0;
            foreach ($job->getItems() as $job_item) {
                $title = $job_item->label();
                $strings = Helpers::extractStringsFromNode($job_item);
                $payload = Helpers::renderHTMLFromStrings($strings);
                $slug = Helpers::slugForItem($job_item);
                $res = $tx->upsertResource($slug, $title, $job->id(), $payload);
                /*
                * Function upsertResource() either creates a new resource or updates an
                * existing one. Depending on return data form the API, we notify the user
                * accordingly.
                */
                if (isset($res[0])) {
                    $job_count++;
                    drupal_set_message('Added ' . $res[0] . ' strings to resource named: ' . $title, 'status');
                } elseif (isset($res['strings_added'])) {
                    $job_count++;
                    drupal_set_message("Updated resource " . $title . " with " . $res['strings_added'] . " new strings.", 'status');
                }
            }
            $job->submitted($job_count . ' translation job(s) have been submitted or updated to Transifex.');

            # See if we need to accept translations immediately
            if ($this->shouldManualUpdateTranslations(
                $translator, $slug, $job->getTargetLanguage()->getId()
            )) {
                drupal_set_message(
                    'Job ' . $slug . ' -> ' . $job->getRemoteTargetLanguage() . ' already has translations, fetching'
                );
                $this->updateJobWithTranslations(
                    $translator,
                    $slug,
                    $job->getTargetLanguage()->getId(),
                    false
                );
            }
        } catch (TMGMTException $e) {
            \Drupal::logger('tmgmt_transifex')->error($e);
            $job->rejected('Job has been rejected with following error: @error', array('@error' => $e->getMessage()), 'error');
        }
    }

    /**
    * {@inheritdoc}
    */
    public function requestJobItemsTranslation(array $job_items)
    {
        /** @var \Drupal\tmgmt\Entity\JobItem $job_item */
        foreach ($job_items as $job_item) {
            $this->checkForTranslations($job_item->getJob());
        }
    }

    /**
    * Check if the incoming webhook should trigger updates in the tmgmt job.
    * This is depended both on the type of the webhook and the settings on the
    * tmgmt_transifex plugin level
    *
    * @param TranslatorInterface $translator
    * @param array $webhook The action that triggered the update
    *
    * @return boolean
    */
    public function shouldWebhookUpdateTranslations($translator, $webhook)
    {
        $event = $webhook['event'];
        if (!$translator->getSetting('onlyreviewed') && $event == 'translation_completed') {
            return true;
        }

        if (!$translator->getSetting('onlyreviewed') && $event == 'translation_completed_updated') {
            return true;
        }

        if ($translator->getSetting('onlyreviewed') && $event == 'review_completed') {
            return $webhook['is_final'];
        }

        if ($translator->getSetting('onlyreviewed') && $event == 'proofread_completed') {
            return $webhook['is_final'];
        }

        return false;
    }

    /**
    * Check if the manual sync should trigger updates in the tmgmt job.
    * This is depended both on the resource/language stats and the
    * settings on the tmgmt_transifex plugin level
    *
    * @param TranslatorInterface $translator
    * @param string $resource Resource's slug
    * @param string $language Language's code
    *
    * @return boolean
    */
    public function shouldManualUpdateTranslations($translator, $resource, $language)
    {
        $tx = new TransifexApi($translator);
        $stats = $tx->getStats($resource, $language);
        if (!$stats) {
            drupal_set_message('Missing resource with slug: ' . $resource);
            return false;
        }
        if (
          $stats->untranslated_entities == 0 &&
          $stats->translated_entities != 0 &&
          !$translator->getSetting('onlyreviewed')
        ) {
            return true;
        }
        if ($stats->reviewed_percentage == '100%' && $translator->getSetting('onlyreviewed')) {
            return true;
        }

        if ($translator->getSetting('onlyreviewed')) {
            drupal_set_message('Resource with slug: ' . $resource . ' missing review.');
        } else {
            drupal_set_message('Resource with slug: ' . $resource . ' missing translations.');
        }

        return false;
    }

    /**
    * Check if there are any translations in Transifex, then update the tmgmt job.
    *
    * @param TranslatorInterface $translator
    * @param string $resource Resource's slug
    * @param string $language Language's code
    * @param boolean $clean
    *
    * @return array|null
    */
    public function updateJobWithTranslations($translator, $resource, $language, $clean)
    {
        $tx = new TransifexApi($translator);
        $translations = $tx->getRemoteTranslations($translator, $resource, $language);
        // Transifex slug has the format 'txdrupal_nodeId_itemType'. We need to extract the nodeId
        // to match it with the appropriate node.
        $nodeInfo = Helpers::nodeInfoFromSlug($resource);
        $tx_resource = $tx->getResource($resource);

        // categories: ['tjid:1', 'tjid:2', 'other']
        $categories = $tx_resource->categories;
        if (!$categories) { $categories = array(); }

        foreach ($categories as $category) {
            // match: ['tjid:1', '1']
            preg_match('/^tjid:(\d+)$/', $category, $match);
            if (count($match) != 2) { continue; }
            $tjid = $match[1];

            $job = Job::load(intval($tjid));
            if ($job && $translator->mapToRemoteLanguage($job->getTargetLanguage()->getId()) == $language) {
                \Drupal::logger('tmgmt_transifex')->info('Applying ' . $language . ' translations for job with id: ' . $tjid);
                foreach ($job->getItems() as $tjiid => $job_item) {
                    // Check if node id and node item type match
                    if ($job_item->getItemId() == $nodeInfo['nodeId'] &&
                        $job_item->getItemType() == $nodeInfo['nodeItemType']) {
                        if (!$job_item->isActive() && !$job_item->isNeedsReview()) {
                            $job->submitted();
                            $job_item->active();
                        }
                        $job->addTranslatedData(array($tjiid => $translations));
                    }
                }
                unset($categories[array_search('tjid:' . $tjid, $categories)]);
            }
        }
        if ($clean) {
            $categories = implode(',', $categories);
            $tx->doRequestToProject('PUT', 'resource/' . $resource, array(
                'categories' => $categories
            ));
        }
    }
}
