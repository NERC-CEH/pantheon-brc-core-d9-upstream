<?php

namespace Drupal\menu_item_extras\EventSubscriber;

use Drupal\Core\Config\ConfigEvents;
use Drupal\Core\Config\StorageTransformEvent;
use Drupal\Core\Config\ConfigInstallerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a ConfigSubscriber that adds a module dependency for all
 * configurations related to the view_mode field on menu_link_content entities
 * during a config export.
 */
class ConfigSubscriber implements EventSubscriberInterface {

  /**
   * The Config Installer.
   *
   * @var \Drupal\Core\Config\ConfigInstallerInterface
   */
  protected $configInstaller;

  /**
   * Constructs a ConfigSubscriber object.
   *
   * @param \Drupal\Core\Config\ConfigInstallerInterface $configInstaller
   *   The Config Installer.
   */
  public function __construct(ConfigInstallerInterface $configInstaller) {
    $this->configInstaller = $configInstaller;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::STORAGE_TRANSFORM_EXPORT][] = ['onConfigExport'];
    return $events;
  }

  /**
   * Adds a module dependency for all config files that are associated with a
   * view_mode field on the menu_link_content entity during the config export.
   *
   * @param \Drupal\Core\Config\StorageTransformEvent $event
   *   The configuration event.
   */
  public function onConfigExport(StorageTransformEvent $event) {
    $storage = $event->getStorage();

    $export_configs = $storage->listAll();

    // add module dependency to all matching configs
    foreach ($export_configs as $config_name) {
      if (preg_match('@^.*\.menu_link_content\..*view_mode$@', $config_name)) {
        $config = $storage->read($config_name);

        // do not duplicate an existing module dependency
        if (empty($config['dependencies']['module']) || !in_array('menu_item_extras', $config['dependencies']['module'], TRUE)) {
          $config['dependencies']['module'][] = 'menu_item_extras';
          $storage->write($config_name, $config);
        }
      }
    }
  }

}
