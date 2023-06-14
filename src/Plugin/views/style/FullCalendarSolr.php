<?php

namespace Drupal\fullcalendar_solr\Plugin\views\style;

use Drupal\core\form\FormStateInterface;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;

/**
 * Style plugin to render a FullCalendar instance compatible with Search API.
 *
 * @ingroup views_style_plugins
 *
 * @ViewsStyle(
 *   id = "fullcalendar_solr",
 *   title = @Translation("FullCalendar Solr"),
 *   help = @Translation("Display results using FullCalendar."),
 *   theme = "views_view_fullcalendar_solr",
 *   display_types = { "normal" }
 * )
 */
class FullCalendarSolr extends StylePluginBase {

  /**
   * {@inheritdoc}
   */
  protected $usesFields = TRUE;

  /**
   * {@inheritdoc}
   */
  protected $usesGrouping = FALSE;

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    $options['date_field'] = ['default' => ''];
    $options['year_field'] = ['default' => ''];
    $options['no_results'] = ['default' => FALSE];
    $options['header_text'] = ['default' => 'All issues for <year>'];
    $options['fullcalendar_options'] = [
      'contains' => [
        'eventBackgroundColor' => ['default' => '#24db3f'],
        'initialDate' => ['default' => ''],
        'initialView' => ['default' => 'multiMonthYear'],
        'multiMonthMinWidth' => ['default' => 200],
        'multiMonthMaxColumns' => ['default' => 4],
        'navLinks' => ['default' => FALSE],
      ],
    ];
    $options['classes'] = ['default' => ''];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $initial_labels = ['' => $this->t('- None -')];
    $view_fields_labels = $this->displayHandler->getFieldLabels();
    $view_fields_labels = array_merge($initial_labels, $view_fields_labels);

    $view_argument_labels = ['' => $this->t('- None -')];
    foreach ($this->displayHandler->getHandlers('argument') as $id => $handler) {
      $view_argument_labels[$id] = $handler->adminLabel();
    }

    $form['date_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Date Field'),
      '#required' => TRUE,
      '#options' => $view_fields_labels,
      '#description' => $this->t('The selected field should contain a string representing a date in YYYY-MM-DD format.'),
      '#default_value' => $this->options['date_field'],
    ];

    $form['year_field'] = [
      '#type' => 'select',
      '#title' => $this->t('Year Field'),
      '#required' => TRUE,
      '#options' => $view_argument_labels,
      '#description' => $this->t('The selected field should contain a string or integer representing a year in YYYY format.'),
      '#default_value' => $this->options['year_field'],
    ];

    $form['header_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Header Text Template'),
      '#description' => $this->t('Available placeholders: <code>@patterns</code>', [
        '@patterns' => '<year>',
      ]),
      '#default_value' => $this->options['header_text'],
    ];

    $form['fullcalendar_options']['multiMonthMinWidth'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum month (pixel) width'),
      '#default_value' => $this->options['fullcalendar_options']['multiMonthMinWidth'],
    ];

    $form['fullcalendar_options']['multiMonthMaxColumns'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number of months per row in year view.'),
      '#default_value' => $this->options['fullcalendar_options']['multiMonthMaxColumns'],
    ];

    $form['fullcalendar_options']['eventBackgroundColor'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Date Highlight Color'),
      '#default_value' => $this->options['fullcalendar_options']['eventBackgroundColor'],
      '#description' => $this->t('The specified color can be in any of the CSS color formats such #f00, #ff0000, rgb(255,0,0), or red.'),
    ];

    $form['fullcalendar_options']['navLinks'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Navigation Links to Day View'),
      '#default_value' => $this->options['fullcalendar_options']['navLinks'],
      '#description' => $this->t('Link to a day view when a highlighted date is clicked. The day view must have the same path as this view except the last component should be "day" instead of "year". i.e. if this view has path "a/b/c/year", then the day view should have path "a/b/c/day".'),
    ];

    $form['no_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display calendar even if there are no results.'),
      '#default_value' => $this->options['no_results'],
    ];

    // Extra CSS classes.
    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CSS classes'),
      '#default_value' => $this->options['classes'],
      '#description' => $this->t('CSS classes for further customization of the calendar.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->options['date_field'])) {
      $this->messenger()->addWarning($this->t('The Date field mapping cannot be empty in FullCalendar Solr format settings.'));
      return;
    }
    if (empty($this->options['year_field'])) {
      $this->messenger()->addWarning($this->t('The Year field mapping cannot be empty in FullCalendar Solr format settings.'));
      return;
    }
    if (!in_array('year', explode('/', $this->view->getPath()))) {
      $this->messenger()->addWarning($this->t('The view path must contain "year" as the last component.'));
      return;
    }

    // Render the fields.  If it isn't done now then the row_index will be unset
    // the first time that getField() is called, resulting in an undefined
    // property exception.
    $this->renderFields($this->view->result);

    // Have to count the dates manually since Search API doesn't support
    // aggregation, and grouping by date will group items with same date
    // but different time separately.
    $date_counts = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $date = $this->buildDate($this->options['date_field']);
      if (empty($date)) {
        continue;
      }
      $date = $date->format('Y-m-d');
      if (!isset($date_counts[$date])) {
        $date_counts[$date] = 0;
      }
      $date_counts[$date]++;
    }
    unset($this->view->row_index);

    // Create the path to the day view.
    $path = explode('/', $this->view->getUrl()->toString());
    $year_index = array_search('year', array_reverse($path, TRUE));
    $day_path = array_slice($path, 0, $year_index);
    $day_path[] = 'day';
    $day_path = implode('/', $day_path);

    // Format event data into the format required by the FullCalendar.
    $events = [];
    foreach ($date_counts as $date => $count) {
      // Set event id to the date since we have at most 1 event per day.
      $event = [
        'id' => $date,
        'start' => $date,
        'count' => $count,
      ];
      if ($this->options['fullcalendar_options']['navLinks']) {
        $event['url'] = $day_path . '/' . $date;
      }
      $events[] = $event;
    }

    // Get list of years with search results.
    $year_field = $this->options['year_field'];
    $year_facets = $this->getYearFacets($year_field);
    $years = [];
    foreach ($year_facets as $facet_data) {
      $years[] = trim($facet_data['filter'], '"');
      // trim($facet_data['count'], '"') to get result count.
    }
    sort($years);

    // Get the calendar's initial year.
    // Check if a year is provided in the path.
    if (isset($path[$year_index + 1]) && is_numeric($path[$year_index + 1])) {
      $this->options['fullcalendar_options']['initialDate'] = $path[$year_index + 1];
    }
    else {
      // If no year provided in the path, use the earliest year with results.
      // If no years have results, use the current year.
      $this->options['fullcalendar_options']['initialDate'] = !empty($years) ? $years[0] : (new \DateTime())->format('Y-m-d');
    }

    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => [
        'fullcalendar_options' => $this->options['fullcalendar_options'],
        'header_text' => $this->options['header_text'],
      ],
      '#rows' => [
        'events' => $events,
        'years' => $years,
      ],
    ];
  }

  /**
   * Builds a date from the current data row.
   *
   * @param string $field
   *   The machine name of the date field.
   *
   * @return \DateTime|null
   *   A date object or NULL if the start date could not be parsed.
   */
  protected function buildDate($field) {
    try {
      $date_markup = $this->getField($this->view->row_index, $field);
      if (empty($date_markup)) {
        return NULL;
      }
      // Store the date string so that it can be used in the error message, if
      // necessary.  Strip HTML tags from dates so users don't run into problems
      // like Date fields wrapping their output with metadata.
      $date_string = strip_tags($date_markup->__toString());

      // Check if date contains only year value.
      if (is_numeric($date_string)) {
        $date_string .= '-01-01';
      }
      $date = new \DateTime($date_string);
    }
    catch (\Exception $e) {
      // Return NULL if the field didn't contain a parseable date string.
      $this->messenger()->addMessage($this->t('The date "@date" does not conform to a <a href="@php-manual">PHP supported date and time format</a>.', [
        '@date' => $date_string,
        '@php-manual' => 'http://php.net/manual/en/datetime.formats.php',
      ]));
      $date = NULL;
    }
    return $date;
  }

  /**
   * Gets facet data for the specified year field.
   *
   * The conditions of the query executed by the view are preserved except for
   * the given year field. This gives us a list of all years that have results.
   *
   * @param string $year_field
   *   The Search API field ID of the year field to facet on.
   * @param int|null $limit
   *   The maximum number of filters to retrive for the facet.
   * @param int|null $min_count
   *   The minimum count a filter/value must have been returned.
   * @param bool|null $missing
   *   Whether to retrive a facet for "missing" values.
   */
  protected function getYearFacets($year_field, $limit = -1, $min_count = 1, $missing = FALSE) {
    $year_facets = [];

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->view->query->getIndex();

    /** @var \Drupal\search_api\ServerInterface|null $server */
    $server = $index->getServerInstance();

    if ($server->supportsFeature('search_api_facets')) {
      // Copy of the query executed by view.
      /** @var \Drupal\search_api\Query\QueryInterface|null $query */
      $query = clone $this->view->query->getSearchApiQuery();
      $query->range(0, 0);

      // Remove existing condition filtering by year.
      /** @var \Drupal\search_api\Query\ConditionGroupInterface $condition_group */
      $condition_group = $query->getConditionGroup();
      $this->deleteCondition($condition_group, $year_field, '=');

      // If the query already has a search_api_facets entry,
      // this will override it.
      $query->setOption('search_api_facets', [
        $year_field => [
          'field' => $year_field,
          'limit' => $limit,
          'min_count' => $min_count,
          'missing' => $missing,
        ],
      ]);

      // Execute the query.
      $query->getIndex()->getServerInstance()->search($query);
      $year_facets = $query->getResults()->getExtraData('search_api_facets', [])[$year_field] ?? [];
    }
    // @todo Should this throw an exception if the server doesn't support search_api_facets?
    return $year_facets;
  }

  /**
   * Deletes the condition containing the given field and operator.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group object.
   * @param string $field
   *   The target field.
   * @param string $operator
   *   The target operator.
   */
  protected function deleteCondition(ConditionGroupInterface &$condition_group, $field, $operator) {
    if (!isset($condition_group) || !isset($field)) {
      return;
    }

    /** @var \Drupal\search_api\Query\ConditionInterface[]|\Drupal\search_api\Query\ConditionGroupInterface[] $conditions */
    $conditions = &$condition_group->getConditions();
    foreach ($conditions as $i => $condition) {
      // Check if the condition contains the target field.
      if (strpos($condition, $field) === FALSE) {
        continue;
      }
      if ($condition instanceof ConditionGroupInterface) {
        $this->deleteCondition($condition, $field, $operator);
      }
      elseif ($condition->getField() === $field && $condition->getOperator() === $operator) {
        unset($conditions[$i]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function evenEmpty() {
    // An empty calendar should be displayed if there are no calendar items.
    return $this->options['no_results'];
  }

}
