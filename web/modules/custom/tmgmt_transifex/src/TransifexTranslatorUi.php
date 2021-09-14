<?php
namespace Drupal\tmgmt_transifex;

use Drupal\tmgmt\TranslatorPluginUiBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\tmgmt\JobInterface;
use Drupal\tmgmt_transifex\Transifex\TransifexAPI;

/**
 * Transifex translator UI.
 */
class TransifexTranslatorUi extends TranslatorPluginUiBase
{

    /**
     * {@inheritdoc}
     */
    public function checkoutInfo(JobInterface $job)
    {
        $info = array();
        $info['actions'] = array('#type' => 'actions');

        if ($job->isActive()) {
            $info['actions']['poll'] = array(
                '#type' => 'submit',
                '#value' => t('Check for updates'),
                '#description' => t('Check for updated translations on the Transifex server.'),
                '#submit' => array('tmgmTransifexPollTranslations'),
            );
        }

        return $info;
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state)
    {
        $form = parent::buildConfigurationForm($form, $form_state);

        /** @var \Drupal\tmgmt\TranslatorInterface $translator */
        $translator = $form_state->getFormObject()->getEntity();
        $instruction_url = 'https://transifex.com/docs';
        $form['api'] = array(
            '#type' => 'textfield',
            '#title' => t('Transifex API Token'),
            '#default_value' => $translator->getSetting('api'),
            '#required' => true,
            '#description' => t(
                'You can generate a Transifex API token <a href="@link" target="_blank">here</a>. For more details, visit our <a href="@documentation" target="_blank">documentation</a>.',
                array(
                    '@link' => 'https://www.transifex.com/user/settings/api/',
                    '@documentation' => 'https://docs.transifex.com/api/introduction#authentication'
                )
            ),
        );
        $form['project'] = array(
            '#type' => 'textfield',
            '#title' => t('Transifex project URL'),
            '#default_value' => $translator->getSetting('project'),
            '#required' => true,
            '#description' => t('Enter the URL of the Transifex project you wish to use for translating this Drupal site.'),
        );
        $form['secret'] = array(
            '#type' => 'textfield',
            '#title' => t('Transifex webhook secret'),
            '#default_value' => $translator->getSetting('secret'),
            '#required' => false,
            '#description' => t('Leave empty to deactivate webhook listener'),
        );
        $form['onlyreviewed'] = array(
            '#type' => 'checkbox',
            '#title' => t('Pull only when a language is 100% reviewed'),
            '#default_value' => $translator->getSetting('onlyreviewed'),
            '#description' => t('By default, translations for a language are imported to Drupal once it is 100% translated'),
        );

        return $form;
    }
    public function validateConfigurationForm(array &$form, FormStateInterface $form_state)
    {
        $settings = $form_state->getValues()['settings'];
        // Only validate the API key if one was provided.
        if (empty($settings['api'])) {
            return;
        }

        $translator = $form_state->getFormObject()->getEntity();
        $txApi = new TransifexApi($translator);
    
        // Update translator settings with form data.
        $translator->setSettings($settings);
        if (!$txApi->getProject($translator)) {
            $form_state->setErrorByName(
                'settings][api',
                t('@message', array(
                    '@message' => 'The "Transifex API Key" is not valid or the target transifex project does not exist'
                ))
            );
        }
    }
}
