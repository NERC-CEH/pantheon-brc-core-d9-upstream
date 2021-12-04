<?php

namespace Drupal\url_redirect\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * Form handler for the Example add and edit forms.
 */
class UrlRedirectForm extends EntityForm {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManagerInterface;

  /**
   * Constructs an UrlRedirectForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManagerInterface
   *  Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManagerInterface) {
    $this->entityTypeManagerInterface = $entityTypeManagerInterface;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    $url_redirect = $this->entity;

    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $url_redirect->label(),
      '#description' => $this->t("Label for the UrlRedirect."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $url_redirect->id(),
      '#machine_name' => array(
        'exists' => array($this, 'exist'),
      ),
      '#disabled' => !$url_redirect->isNew(),
    );
    $form['url'] = array(
      '#type' => 'fieldset',
      '#title' => t('Url Redirect'),
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    );
    $form['url']['path'] = array(
      '#type' => 'textfield',
      '#title' => 'Path',
      '#attributes' => array(
        'placeholder' => 'Enter Path',
      ),
      '#required' => TRUE,
      '#default_value' => $url_redirect->get_path(),
      '#disabled' => !$url_redirect->isNew(),
      '#description' => t('This can be an internal Drupal path such as node/add Enter <front> to link to the front page.'),
    );
    $form['url']['redirect_path'] = array(
      '#type' => 'textfield',
      '#title' => 'Redirect Path',
      '#attributes' => array(
        'placeholder' => 'Enter Redirect Path',
      ),
      '#required' => TRUE,
      '#default_value' => $url_redirect->get_redirect_path(),
      '#description' => t('This redirect path can be internal Drupal path such as node/add Enter <front> to link to the front page.'),
    );

    $form['url']['checked_for'] = array(
      '#type' => 'radios',
      '#title' => t('Select Redirect path for'),
      '#options' => array(
        'Role' => t('Role'),
        'User' => t('User')
      ),
      '#default_value' => $url_redirect->get_checked_for(),
      '#required' => TRUE,
    );
    $form['url']['url_roles'] = array(
      '#type' => 'container',
      '#states' => array(
        'visible' => array(
          ':input[name="checked_for"]' => array('value' => 'Role'),
        ),
      ),
    );
    $user_roles = user_role_names();
    $form['url']['url_roles']['roles'] = array(
      '#type' => 'select',
      '#title' => t('Select Roles'),
      '#options' => $user_roles,
      '#multiple' => TRUE,
      '#tree' => TRUE,
      '#default_value' => $url_redirect->get_roles(),
    );
    $form['url']['url_user'] = array(
      '#type' => 'container',
      '#states' => array(
        'visible' => array(
          ':input[name="checked_for"]' => array('value' => 'User'),
        ),
      ),
    );
    $redirect_users = $url_redirect->get_users();
    if ($redirect_users) {
      $default_users = User::loadMultiple(array_column($redirect_users, 'target_id'));
    }
    else {
      $default_users = '';
    }
    $form['url']['url_user']['user'] = array(
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#default_value' => $default_users,
      '#multiple' => TRUE,
      '#tags' => TRUE,
    );
    $form['url']['negate'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Negate the condition'),
      '#default_value' => $url_redirect->get('negate'),
    );
    $form['url']['message'] = array(
      '#type' => 'radios',
      '#title' => t('Display Message for Redirect'),
      '#required' => TRUE,
      '#description' => t('Show a message for redirect path.'),
      '#options' => array(
        'Yes' => t('Yes'),
        'No' => t('No')
      ),
      '#default_value' => $url_redirect->get_message(),
    );
    $form['url']['status'] = array(
      '#type' => 'radios',
      '#title' => t('Status'),
      '#options' => array(
        0 => t('Disabled'),
        1 => t('Enabled'),
      ),
      '#required' => TRUE,
      '#default_value' => $url_redirect->get_status(),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $path = $values['path'];
    $url_redirect = $this->entity;
    $storage = $this->entityTypeManagerInterface->getStorage('url_redirect');
    $path_check = $storage->getQuery()
        ->condition('path', $path)
        ->execute();
    if ($path_check && $url_redirect->isNew()) {
      $form_state->setErrorByName('path', $this->t("The path '@link_path' already used for redirect.", array('@link_path' => $path)));
    }
    $redirect_path = $values['redirect_path'];
    if (!\Drupal::service('path.validator')->isValid($redirect_path)) {
      $form_state->setErrorByName('redirect_path', $this->t("The redirect path '@link_path' is either invalid or you do not have access to it.", array('@link_path' => $redirect_path)));
    }
    $checked_for = $values['checked_for'];
    // Get Checked for Role.
    if ($checked_for == 'Role') {
      $roles_values = $values['roles'];
      if (!$roles_values) {
        $form_state->setErrorByName('roles', $this->t("Select at least one role for which you want to apply this redirect."));
      }
    }
    if ($checked_for == 'User') {
      $users_values = $values['user'];
      if (!$users_values) {
        $form_state->setErrorByName('user', $this->t("Enter at least one user for which you want to apply this redirect."));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $url_redirect = $this->entity;
    $status = $url_redirect->save();
    if ($status) {
      $this->messenger()->addMessage($this->t('Saved the %label UrlRedirect.', array(
            '%label' => $url_redirect->label(),
      )));
    }
    else {
      $this->messenger()->addMessage($this->t('The %label UrlRedirect was not saved.', array(
            '%label' => $url_redirect->label(),
      )));
    }

    $form_state->setRedirect('entity.url_redirect.collection');
  }

  /**
   * Helper function to check whether an UrlRedirect configuration entity exists.
   */
  public function exist($id) {
    $storage = $this->entityTypeManagerInterface->getStorage('url_redirect');
    $entity = $storage->getQuery()
        ->condition('id', $id)
        ->execute();
    return (bool) $entity;
  }

}
