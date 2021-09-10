<?php

namespace Drupal\simple_oauth\Server;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use League\OAuth2\Server\ResourceServer as LeageResourceServer;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * The resource server.
 */
class ResourceServer implements ResourceServerInterface {

  /**
   * The decorated resource server.
   *
   * @var \League\OAuth2\Server\ResourceServer
   */
  protected $subject;

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $messageFactory;

  /**
   * The HTTP foundation factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface
   */
  protected $foundationFactory;

  /**
   * The file system.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * ResourceServer constructor.
   */
  public function __construct(
    AccessTokenRepositoryInterface $access_token_repository,
    ConfigFactoryInterface $config_factory,
    HttpMessageFactoryInterface $message_factory,
    HttpFoundationFactoryInterface $foundation_factory
  ) {
    try {
      $public_key = $config_factory->get('simple_oauth.settings')
        ->get('public_key');
      $public_key_real = $this->fileSystem()->realpath($public_key);
      if ($public_key && $public_key_real) {
        $this->subject = new LeageResourceServer(
          $access_token_repository,
          $public_key_real
        );
      }
    } catch (\LogicException $exception) {
      trigger_error($exception, E_USER_WARNING);
    }
    $this->messageFactory = $message_factory;
    $this->foundationFactory = $foundation_factory;
  }

  /**
   * Lazy loads the file system.
   *
   * @return \Drupal\Core\File\FileSystemInterface
   *   The file system service.
   */
  protected function fileSystem(): FileSystemInterface {
    if (!isset($this->fileSystem)) {
      $this->fileSystem = \Drupal::service('file_system');
    }
    return $this->fileSystem;
  }

  /**
   * {@inheritdoc}
   */
  public function validateAuthenticatedRequest(Request $request) {
    if (!$this->subject) {
      throw new \LogicException(
        'Unable to create resource server. Make sure public and private keys are correctly configured.'
      );
    }
    // Create a PSR-7 message from the request that is compatible with the OAuth
    // library.
    $psr7_request = $this->messageFactory->createRequest($request);
    // Augment the request with the access token's decoded data or throw an
    // exception if authentication is unsuccessful.
    $output_psr7_request = $this
      ->subject
      ->validateAuthenticatedRequest($psr7_request);

    // Convert back to the Drupal/Symfony HttpFoundation objects.
    return $this->foundationFactory->createRequest($output_psr7_request);
  }

}
