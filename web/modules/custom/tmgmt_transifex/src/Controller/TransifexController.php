<?php

/**
 * Provides Transifex callback controller.
 */
namespace Drupal\tmgmt_transifex\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Component\Serialization\Json;
use Drupal\tmgmt\Entity\Job;
use Drupal\tmgmt\Entity\Translator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for Transifex webhooks.
 */
class TransifexController extends ControllerBase
{
    
    /**
     * Webhook callback.
     * @param Request $request The HTTP request provided by Drupal.
     */
    public function callback(Request $request)
    {
        $translator = Translator::load('transifex');
        
        \Drupal::logger('tmgmt_transifex')->info('Webhook - Received webhook');
        if (!$translator->getSetting('secret')) {
            echo "Webhook not activated";
            \Drupal::logger('tmgmt_transifex')->info('Webhook - Missing secret');
            return;
        }

        $received_json = file_get_contents("php://input", true);
        $webhook = Json::decode($received_json);

        if (!isset($_SERVER['HTTP_X_TX_SIGNATURE_V2']) ||
                !isset($_SERVER['HTTP_X_TX_URL']) ||
                !isset($_SERVER['HTTP_DATE'])
        ) {
            echo 'Missing headers';
            \Drupal::logger('tmgmt_transifex')->error('Webhook - Missing headers');
            return;
        }

        /*
        * At this point we should verify the Transifex webhook (more info can be found
        * at https://docs.transifex.com/integrations/webhooks#verifying-a-webhook). If
        * the webhook is valid, we can procceed with updating the job with the new
        * translations.
        */
        $webhook_sig = $_SERVER['HTTP_X_TX_SIGNATURE_V2'];
        $http_url_path = $_SERVER['HTTP_X_TX_URL'];
        $http_gmt_date = $_SERVER['HTTP_DATE'];
        $content_md5 = md5($received_json);
        $msg = join(PHP_EOL, array('POST', $http_url_path, $http_gmt_date, $content_md5));
        $sig = base64_encode(hash_hmac('sha256', $msg, $translator->getSetting('secret'), true));

        if ($sig == $webhook_sig) {
            $event = $webhook['event'];
            $resource = $webhook['resource'];
            $remote_mappings = $translator->getRemoteLanguagesMappings();

            if (in_array($webhook['language'], $remote_mappings)) {
                $local_language = array_search($webhook['language'], $remote_mappings);
                $dp_language = $local_language;
            } else {
                $dp_language = $webhook['language'];
            }

            \Drupal::logger('tmgmt_transifex')->info(
                'Received ' . $event . ' webhook for resource:' . $resource . ' and language: ' . $dp_language
            );
            if ($translator->getPlugin()->shouldWebhookUpdateTranslations(
                $translator, $webhook
            )) {
                $translator->getPlugin()->updateJobWithTranslations(
                    $translator,
                    $resource,
                    $dp_language,
                    false
                );
            }
        } else {
            \Drupal::logger('tmgmt_transifex')->error('Webhook - Invalid');
        }
        return new Response();
    }
}
