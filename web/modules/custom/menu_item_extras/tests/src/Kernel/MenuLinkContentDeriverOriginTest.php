<?php

namespace Drupal\Tests\menu_item_extras\Kernel;

use Drupal\Tests\menu_link_content\Kernel\MenuLinkContentDeriverTest;

/**
 * Tests the menu link content deriver.
 *
 * @group menu_item_extras
 */
class MenuLinkContentDeriverOriginTest extends MenuLinkContentDeriverTest {

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    static::$modules[] = 'menu_item_extras';
    parent::__construct($name, $data, $dataName);
  }

}
