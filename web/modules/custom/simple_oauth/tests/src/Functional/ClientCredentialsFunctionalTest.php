<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;

/**
 * The client credentials test.
 *
 * @group simple_oauth
 */
class ClientCredentialsFunctionalTest extends TokenBearerFunctionalTestBase {

  /**
   * Test the valid ClientCredentials grant.
   */
  public function testClientCredentialsGrant() {
    // 1. Test the valid response.
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, FALSE);

    // 2. Test the valid without scopes.
    $payload_no_scope = $valid_payload;
    unset($payload_no_scope['scope']);
    $response = $this->post($this->url, $payload_no_scope);
    $this->assertValidTokenResponse($response, FALSE);

  }

  /**
   * Test invalid ClientCredentials grant.
   */
  public function testMissingClientCredentialsGrant() {
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];

    $data = [
      'grant_type' => [
        'error' => 'invalid_grant',
        'code' => 400,
      ],
      'client_id' => [
        'error' => 'invalid_request',
        'code' => 400,
      ],
      'client_secret' => [
        'error' => 'invalid_client',
        'code' => 401,
      ],
    ];
    foreach ($data as $key => $value) {
      $invalid_payload = $valid_payload;
      unset($invalid_payload[$key]);
      $response = $this->post($this->url, $invalid_payload);
      $parsed_response = Json::decode((string) $response->getBody());
      $this->assertSame($value['error'], $parsed_response['error'], sprintf('Correct error code %s for %s.', $value['error'], $key));
      $this->assertSame($value['code'], $response->getStatusCode(), sprintf('Correct status code %d for %s.', $value['code'], $key));
    }
  }

  /**
   * Test invalid ClientCredentials grant.
   */
  public function testInvalidClientCredentialsGrant() {
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->uuid(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];

    $data = [
      'grant_type' => [
        'error' => 'invalid_grant',
        'code' => 400,
      ],
      'client_id' => [
        'error' => 'invalid_client',
        'code' => 401,
      ],
      'client_secret' => [
        'error' => 'invalid_client',
        'code' => 401,
      ],
    ];
    foreach ($data as $key => $value) {
      $invalid_payload = $valid_payload;
      $invalid_payload[$key] = $this->getRandomGenerator()->string();
      $response = $this->post($this->url, $invalid_payload);
      $parsed_response = Json::decode((string) $response->getBody());
      $this->assertSame($value['error'], $parsed_response['error'], sprintf('Correct error code %s for %s.', $value['error'], $key));
      $this->assertSame($value['code'], $response->getStatusCode(), sprintf('Correct status code %d for %s.', $value['code'], $key));
    }
  }

}
