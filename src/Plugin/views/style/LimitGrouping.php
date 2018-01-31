<?php

namespace Drupal\views_limit_grouping\Plugin\views\style;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Class LimitGrouping.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "limitgroupingview",
 *   title = @Translation("Grouping Field (with Limit)"),
 *   help = @Translation("Limit the number of rows for each grouping field"),
 *   theme = "views_view_limitgrouping",
 *   display_types = {"normal"}
 * )
 */
class LimitGrouping extends StylePluginBase {

  /**
   * Does the style plugin allows to use style plugins.
   *
   * @var bool
   */
  protected $usesRowPlugin = TRUE;

  /**
   * Does the style plugin support custom css class for the rows.
   *
   * @var bool
   */
  protected $usesRowClass = TRUE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['grouping-limit'] = ['default' => 0];
    $options['grouping-offset'] = ['default' => 0];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {

    parent::buildOptionsForm($form, $form_state);

    foreach ($form['grouping'] as $index => $info) {
      $defaults = $this->groupingLimitSettings($index);

      $form['grouping'][$index]['grouping-limit'] = [
        '#type' => 'fieldset',
        '#title' => $this->t('Limit for grouping field Nr.!num', ['!num' => $index + 1]),
        '#collapsible' => TRUE,
        '#collapsed' => TRUE,
        '#states' => [
          'invisible' => [
            "select[name=\"style_options[grouping][{$index}][field]\"]" => ['value' => ''],
          ],
        ],
        'grouping-limit' => [
          '#type' => 'number',
          '#title' => $this->t('Items to display:'),
          '#default_value' => $defaults['grouping-limit'],
          '#size' => 6,
          '#element_validate' => [[get_class($this), 'groupingValidate']],
          '#description' => $this->t('The number of rows to show under each grouping header (only works when "Items to display" in the main view is set to unlimited).'),
        ],
        'grouping-offset' => [
          '#type' => 'number',
          '#title' => $this->t('Offset:'),
          '#default_value' => $defaults['grouping-offset'],
          '#size' => 6,
          '#element_validate' => [[$this, 'groupingValidate']],
          '#description' => $this->t('The row to start on (<em>0</em> means it will start with first row, <em>1</em> means an offset of 1, and so on).'),
        ],
      ];
    }

  }

  /**
   * Validate the added form elements.
   */
  public function groupingValidate($element, FormStateInterface $form_state) {
    // Checking for negative values.
    if ($element['#value'] < 0) {
      $form_state->setError($element, t('%element cannot be negative.', ['%element' => $element['#title']]));
    }
  }

  /**
   * Overrides parent::renderGroupingSets().
   */
  public function renderGroupingSets($records) {
    $sets = parent::renderGroupingSets($records);
    // Apply the offset and limit.
    array_walk($sets, [$this, 'groupLimitRecursive']);
    return $sets;
  }

  /**
   * Recursively limits the number of rows in nested groups.
   *
   * @param array $group_data
   *   A single level of grouped records.
   * @param mixed $key
   *   The key of the array being passed in. Used for when this function is
   *   called from array_walk() and the like. Do not set directly.
   * @param int $level
   *   The current level we are gathering results for. Used for recursive
   *   operations; do not set directly.
   *
   *   return array
   *   An array with a "rows" property that is recursively grouped by the
   *   grouping fields.
   */
  protected function groupLimitRecursive(array &$group_data, $key = NULL, $level = 0) {
    $settings = $this->groupingLimitSettings($level);

    // Slice up the rows according to the offset and limit.
    if (isset($group_data['#rows'])) {
      $group_data['#rows'] = array_slice($group_data['#rows'], $settings['grouping-offset'], $settings['grouping-limit'], TRUE);

      // For each row, if it appears to be another grouping, recurse again.
      foreach ($group_data['#rows'] as &$data) {
        if (is_array($data) && isset($data['#row'])) {
          $this->groupLimitRecursive($data, NULL, $level + 1);
        }
      }
    }

  }

  /**
   * Helper function to retrieve settings for grouping limit.
   *
   * @param int $index
   *   The grouping level to fetch settings for.
   *
   * @return array
   *   Settings for this grouping level.
   */
  protected function groupingLimitSettings($index) {
    if (!isset($this->options['grouping'][$index])) {
      $this->options['grouping'][$index]['grouping-limit'] = [
        'grouping-limit' => 0,
        'grouping-offset' => 0,
      ];
    }
    return $this->options['grouping'][$index]['grouping-limit'];
  }

}
