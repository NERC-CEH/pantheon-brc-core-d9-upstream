<?php

namespace Drupal\Tests\menu_item_extras\Kernel;

use Drupal\Tests\menu_link_content\Kernel\MenuLinkContentCacheabilityBubblingTest;

/**
 * Ensures that rendered menu links bubble the necessary bubbleable metadata.
 *
 * For outbound path/route processing.
 *
 * @group menu_item_extras
 */
class MenuLinkContentCacheabilityBubblingOriginTest extends MenuLinkContentCacheabilityBubblingTest {

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    static::$modules[] = 'menu_item_extras';
    parent::__construct($name, $data, $dataName);
  }

}
