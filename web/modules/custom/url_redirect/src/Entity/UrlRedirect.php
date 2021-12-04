<?php

namespace Drupal\url_redirect\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\url_redirect\UrlRedirectInterface;

/**
 * Defines the Url Redirect entity.
 *
 * @ConfigEntityType(
 *   id = "url_redirect",
 *   label = @Translation("Url Redirect"),
 *   handlers = {
 *     "list_builder" = "Drupal\url_redirect\Controller\UrlRedirectListBuilder",
 *     "form" = {
 *       "add" = "Drupal\url_redirect\Form\UrlRedirectForm",
 *       "edit" = "Drupal\url_redirect\Form\UrlRedirectForm",
 *       "delete" = "Drupal\url_redirect\Form\UrlRedirectDeleteForm",
 *     }
 *   },
 *   config_prefix = "url_redirect",
 *   admin_permission = "access url redirect settings page",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid",
 *     "path" = "path",
 *     "redirect_path" = "redirect_path",
 *     "redirect_for" = "redirect_for",
 *     "negate" = "negate",
 *     "roles" = "roles",
 *     "users" = "users",
 *     "message" = "message",
 *     "status" = "status"
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/system/url_redirect/{url_redirect}",
 *     "delete-form" = "/admin/config/system/url_redirect/{url_redirect}/delete",
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "uuid",
 *     "message",
 *     "status",
 *     "path",
 *     "redirect_path",
 *     "checked_for",
 *     "negate",
 *     "user",
 *     "roles"
 *    }
 * )
 */
class UrlRedirect extends ConfigEntityBase implements UrlRedirectInterface {

  /**
   * The UrlRedirect ID.
   *
   * @var string
   */
  public $id;

  /**
   * The UrlRedirect label.
   *
   * @var string
   */
  public $label;

  /**
   * The UrlRedirect path url.
   *
   * @var string
   */
  protected $path;

  /**
   * The CAS Site(s) details  username.
   *
   * @var string
   */
  protected $redirect_path;

  /**
   * The CAS Site(s) details password.
   *
   * @var string
   */
  protected $checked_for;

  /**
   * The CAS Site(s) details password.
   *
   * @var string
   */
  protected $roles;

  /**
   * The CAS Site(s) details password.
   *
   * @var string
   */
  protected $user;

  /**
   * Integer to indicate if negation of the condition is necessary.
   *
   * @var boolean
   */
  protected $negate;

  /**
   * The CAS Site(s) details password.
   *
   * @var string
   */
  protected $message;

  /**
   * The CAS Site(s) details password.
   *
   * @var string
   */
  protected $status;

  /**
   * {@inheritdoc}
   */
  public function get_path() {
    return $this->path;
  }

  /**
   * {@inheritdoc}
   */
  public function get_redirect_path() {
    return $this->redirect_path;
  }

  /**
   * {@inheritdoc}
   */
  public function get_checked_for() {
    return $this->checked_for;
  }

  /**
   * {@inheritdoc}
   */
  public function get_roles() {
    return $this->roles;
  }

  /**
   * {@inheritdoc}
   */
  public function get_users() {
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function get_message() {
    return $this->message;
  }

  /**
   * {@inheritdoc}
   */
  public function get_status() {
    return $this->status;
  }

  /**
   * Return negate item.
   *
   * @return int
   */
  public function get_negate() {
    return $this->negate;
  }

}
