<?php

namespace Drupal\simple_oauth\Entities;

use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AccessTokenEntity implements AccessTokenEntityInterface {

  use AccessTokenTrait, TokenEntityTrait, EntityTrait;

  /**
   * {@inheritdoc}
   */
  public function convertToJWT() {
    $private_claims = [];
    \Drupal::moduleHandler()
      ->alter('simple_oauth_private_claims', $private_claims, $this);
    if (!is_array($private_claims)) {
      $message = 'An implementation of hook_simple_oauth_private_claims_alter ';
      $message .= 'returns an invalid $private_claims value. $private_claims ';
      $message .= 'must be an array.';
      throw new \InvalidArgumentException($message);
    }

    $id = $this->getIdentifier();
    $now = new \DateTimeImmutable('@' . \Drupal::time()->getCurrentTime());
    $key_path = $this->privateKey->getKeyPath();
    $key = InMemory::file($key_path);
    $config = Configuration::forSymmetricSigner(new Sha256(), $key);

    $builder = $config->builder()
      ->permittedFor($this->getClient()->getIdentifier())
      ->identifiedBy($id)
      ->withHeader('jti', $id)
      ->issuedAt($now)
      ->canOnlyBeUsedAfter($now)
      ->expiresAt($this->getExpiryDateTime())
      ->relatedTo($this->getUserIdentifier())
      ->withClaim('scope', $this->getScopes());
	  
    if (isset($private_claims['iss'])) {
      $builder->issuedBy($private_claims['iss']);
    }
    foreach ($private_claims as $claim_name => $value) {
      if (in_array($claim_name, RegisteredClaims::ALL)) {
        // Skip registered claims, as they are added above already.
        continue;
      }
      try {
        $builder->withClaim($claim_name, $value);
      }
      catch (\Exception $e) {
        \Drupal::logger('simple_oauth')
          ->error('Could not add private claim @claim_name to token: @error_message', [
            '@claim_name' => $claim_name,
            '@error_message' => $e->getMessage(),
          ]);
      }
    }	  
    return $builder->getToken($config->signer(), $config->signingKey());
  }
}
