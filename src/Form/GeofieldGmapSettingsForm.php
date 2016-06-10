<?php

/**
 * @file
 * Contains \Drupal\geofield_gmap\Form\GeofieldGmapSettingsForm.
 */

namespace Drupal\geofield_gmap\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;


/**
 * Class GeofieldGmapSettingsForm
 *
 * @package Drupal\geofield_gmap\Form
 */
class GeofieldGmapSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'geofield_gmap_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['geofield_gmap.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('geofield_gmap.settings');

    $form['geofield_gmap_google_api_key'] = array(
      '#type' => 'textfield',
      '#title' => $this->t("Google Api Key"),
      '#default_value' => $config->get('geofield_gmap_google_api_key'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Save the config values.
    $this->config('geofield_gmap.settings')
      ->set('geofield_gmap_google_api_key', $form_state->getValue('geofield_gmap_google_api_key'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
