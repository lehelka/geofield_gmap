<?php

/**
 * @file
 * Contains \Drupal\geofield_gmap\Plugin\Field\FieldWidget\GeofieldGmapWidget.
 */

namespace Drupal\geofield_gmap\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Plugin implementation of the 'geofield_gmap' widget.
 *
 * @FieldWidget(
 *   id = "geofield_gmap",
 *   label = @Translation("Google Map"),
 *   field_types = {
 *     "geofield"
 *   }
 * )
 */
class GeofieldGmapWidget extends WidgetBase {

  /**
   * Lat Lon widget components.
   *
   * @var array
   */
  public $components = ['lon', 'lat'];

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'map_type' => 'ROADMAP',
      'zoom_level' => 1,
      'confirm_center_marker' => FALSE,
      'click_to_place_marker' => FALSE,
      'html5_geolocation' => FALSE,
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['map_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Map type'),
      '#default_value' => $this->getSetting('map_type'),
      '#options' => [
        'ROADMAP' => $this->t('Roadmap'),
        'SATELLITE' => $this->t('Satellite'),
        'HYBRID' => $this->t('Hybrid'),
        'TERRAIN' => $this->t('Terrain'),
      ],
    ];
    $elements['zoom_level'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Default zoom level'),
      '#default_value' => $this->getSetting('zoom_level'),
      '#required' => FALSE,
    ];
    $elements['confirm_center_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Confirm center marker'),
      '#default_value' => $this->getSetting('confirm_center_marker'),
    ];
    $elements['click_to_place_marker'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Click to place marker'),
      '#default_value' => $this->getSetting('click_to_place_marker'),
    ];
    $elements['html5_geolocation'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('HTML5 geolocation'),
      '#default_value' => $this->getSetting('html5_geolocation'),
    ];

    return $elements;

  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      t('Map type is @type', ['@type' => $this->getSetting('map_type')]),
      t('Zoom level: @zoom', ['@zoom' => $this->getSetting('zoom_level')]),
      t('HTML5 Geolocation is @state', array('@state' => $this->getSetting('html5_geolocation') ? t('enabled') : t('disabled')))
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $latlon_value = [];

    foreach ($this->components as $component) {
      $latlon_value[$component] = isset($items[$delta]->{$component}) ? floatval($items[$delta]->{$component}) : '';
    }

    // @TODO the following D7 code was not ported. Check if still needed.
//    // Try to fetch data from form state if lon/lat is empty.
//    if (isset($form_state['input'][$field['field_name']][$langcode][$delta]['geom'])) {
//      $latlon_value = $form_state['input'][$field['field_name']][$langcode][$delta]['geom'];
//    }
//    // If we still have empty lat and long, use the field's default values.
//    if (!$latlon_value['lat'] || !$latlon_value['lon']) {
//      if ($default_field_value = field_get_default_value($base['#entity_type'], $base['#bundle'], $field, $instance, $langcode)) {
//        if ($default_geom_value = $default_field_value[0]['geom']) {
//          $latlon_value = $default_geom_value;
//        }
//      }
//    }

    $element += [
      '#type' => 'geofield_latlon',
      '#default_value' => $latlon_value,
      '#geolocation' => $this->getSetting('html5_geolocation'),
      '#geofield_gmap_geolocation_override' => $this->getSetting('html5_geolocation'),
      '#zoom_level' => $this->getSetting('zoom_level'),
      '#gmap_map_type' => $this->getSetting('map_type'),
      '#gmap_confirm_center_marker' => $this->getSetting('confirm_center_marker'),
      '#gmap_click_to_place_marker' => $this->getSetting('click_to_place_marker'),
      '#gmap_id' => $this->getMapElementId($element),
      '#error_label' => !empty($element['#title']) ? $element['#title'] : $this->fieldDefinition->getLabel(),
    ];

    // $element['input_format'] is not a db field, but we use it determine how
    // to parse/calculate values in our widget.
    $element['input_format'] = [
      '#type' => 'value',
      '#attributes' => ['class' => array('geofield_input_format')],
      '#value' => GEOFIELD_INPUT_LAT_LON,
    ];

    $element['master_column']['#value'] = 'latlon';

    return ['value' => $element];
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as $delta => $value) {
      foreach ($this->components as $component) {
        if (empty($value['value'][$component]) || !is_numeric($value['value'][$component])) {
          $values[$delta]['value'] = '';
          continue 2;
        }
      }
      $components = $value['value'];
      $values[$delta]['value'] = \Drupal::service('geofield.wkt_generator')->WktBuildPoint(array($components['lon'], $components['lat']));
    }

    return $values;
  }

  /**
   * After-build handler for field elements in a form.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state) {

    $element = parent::afterBuild($element, $form_state);

    // Attach our main js.
    $element['#attached']['library'][] = 'geofield_gmap/geofield_gmap.main';

    $id = $element[0]['value']['#gmap_id'];
    $gmapid = 'gmap-' . $id;
    $markup = '<div class="form-item">';
    $markup .= '<label>' . t("Geocode address") . '</label><input size="64" id="search-' . $id . '" class="form-text form-autocomplete geofield-gmap-search" type="text"/>';
    $markup .= '<div id="' . $gmapid . '" class="geofield-gmap-cnt"></div>';
    $markup .= '<div class="geofield-gmap-buttons">';
    $markup .= '<label>' . t("Drag the marker to narrow the location") . '</label>';
    $markup .= '<button class="geofield-gmap-center" onclick="geofield_gmap_center(\'' . $gmapid . '\'); return false;">' . t('Find marker') . '</button>';
    $markup .= '<button class="geofield-gmap-marker" onclick="geofield_gmap_marker(\'' . $gmapid . '\'); return false;">' . t('Place marker here') . '</button>';
    $markup .= '</div>';
    $markup .= '</div>';

    array_unshift($element[0]['value'], ['gmap_markup' => [
      '#markup' => $markup,
      '#allowed_tags' => ['div', 'label', 'input', 'button'],
      '#weight' => 0.0019,
    ]]);

    // Attach JS settings.
    $settings = array(
      $gmapid => array(
        'lat' => floatval($element[0]['value']['lat']['#default_value']),
        'lng' => floatval($element[0]['value']['lon']['#default_value']),
        'zoom' => $element[0]['value']['#zoom_level'],
        'latid' => $element[0]['value']['lat']['#id'],
        'lngid' => $element[0]['value']['lon']['#id'],
        'searchid' => 'search-' . $id,
        'mapid' => $gmapid,
        'widget' => TRUE,
        'map_type' => $element[0]['value']['#gmap_map_type'],
        'confirm_center_marker' => !empty($element[0]['value']['#gmap_confirm_center_marker']) ? 'true' : 'false',
        'click_to_place_marker' => !empty($element[0]['value']['#gmap_click_to_place_marker']) ? 'true' : 'false',
      ),
    );
    $element['#attached']['drupalSettings']['geofield_gmap'] = $settings;

    if (!empty($element[0]['value']['#geofield_gmap_geolocation_override'])) {
      // Add override behavior.
      $element['#attached']['library'][] = 'geofield_gmap/geofield_gmap.geolocation_override';
    }

    return $element;
  }

  /**
   * Generate a unique id for the map element.
   *
   * @param array $element
   *   The widget element.
   *
   * @return string
   *   The generated id string.
   */
  private function getMapElementId(array $element) {
    $name = $this->fieldDefinition->getName();
    $delta = !empty($element['#delta']) ? $element['#delta'] : '0';
    $id = array_merge($element['#field_parents'], [
      $name,
      $delta,
    ]);
    return strtr(implode('-', $id), '_', '-');
  }

}
