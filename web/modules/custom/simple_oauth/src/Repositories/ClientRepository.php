<?php

namespace Drupal\simple_oauth\Repositories;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Drupal\simple_oauth\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface {

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Password\PasswordInterface
   */
  protected $passwordChecker;

  /**
   * Constructs a ClientRepository object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, PasswordInterface $password_checker) {
    $this->entityTypeManager = $entity_type_manager;
    $this->passwordChecker = $password_checker;
  }

  /**
   * {@inheritdoc}
   */
  public function getClientEntity($client_identifier) {
    $client_drupal_entities = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['uuid' => $client_identifier]);

    // Check if the client is registered.
    if (empty($client_drupal_entities)) {
      return NULL;
    }
    /** @var \Drupal\consumers\Entity\Consumer $client_drupal_entity */
    $client_drupal_entity = reset($client_drupal_entities);

    return new ClientEntity($client_drupal_entity);
  }

  /**
   * @{inheritdoc}
   */
  public function validateClient($client_identifier, $client_secret, $grant_type) {
    $client_drupal_entities = $this->entityTypeManager
      ->getStorage('consumer')
      ->loadByProperties(['uuid' => $client_identifier]);

    /** @var \Drupal\consumers\Entity\Consumer $client_drupal_entity */
    $client_drupal_entity = reset($client_drupal_entities);
    $secret = $client_drupal_entity->get('secret')->value;

    // @todo check the grant type?

    if ($client_drupal_entity->get('confidential')->value) {
      return $this->passwordChecker->check($client_secret, $secret);
    }

    return FALSE;
  }

}
