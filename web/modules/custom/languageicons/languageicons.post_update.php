<?php

/**
 * @file
 * Post-update hooks for the Language Icons module.
 */

declare(strict_types = 1);

/**
 * Remove form state values from saved config.
 */
function languageicons_post_update_remove_form_state_values_from_config() {
  $config = \Drupal::configFactory()->getEditable('languageicons.settings');

  $keys_to_delete = [
    'actions',
    'form_build_id',
    'form_id',
    'form_token',
    'show',
  ];

  foreach ($keys_to_delete as $key) {
    $config->clear($key);
  }

  $config->save();
}
