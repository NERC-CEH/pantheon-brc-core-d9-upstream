<?php

namespace Drupal\Tests\menu_item_extras\Kernel;

use Drupal\Tests\menu_link_content\Kernel\PathAliasMenuLinkContentTest;

/**
 * Ensures that the menu tree adapts to path alias changes.
 *
 * @group menu_item_extras
 */
class PathAliasMenuLinkContentOriginTest extends PathAliasMenuLinkContentTest {

  /**
   * {@inheritdoc}
   */
  public function __construct($name = NULL, array $data = [], $dataName = '') {
    static::$modules[] = 'menu_item_extras';
    parent::__construct($name, $data, $dataName);
  }

}
