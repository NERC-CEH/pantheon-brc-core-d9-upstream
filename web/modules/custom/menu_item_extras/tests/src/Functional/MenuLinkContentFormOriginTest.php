<?php

namespace Drupal\Tests\menu_item_extras\Functional;

use Drupal\Tests\menu_link_content\Functional\MenuLinkContentFormTest;

/**
 * Tests the menu link content UI.
 *
 * @group menu_item_extras
 */
class MenuLinkContentFormOriginTest extends MenuLinkContentFormTest {

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    static::$modules[] = 'menu_item_extras';
    parent::__construct($name, $data, $dataName);
  }

}
