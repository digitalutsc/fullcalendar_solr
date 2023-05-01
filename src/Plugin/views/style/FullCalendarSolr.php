<?php

namespace Drupal\fullcalendar_solr\Plugin\views\style;

// @todo remove unused imports
use DateTime;
use Drupal\core\form\FormStateInterface;
use Drupal\views\Plugin\views\style\StylePluginBase;
use Drupal\fullcalendar_solr\FullCalendar\FullCalendar;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\facets\FacetManager\DefaultFacetManager;
use Drupal\search_api\Query\Query;
use Drupal\search_api\Query\ConditionGroupInterface;

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
   * Constructs a FullCalendarSolr object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ImmutableConfig $module_configuration
   *   The Views TimelineJS module's configuration.
   */
  // public function __construct(array $configuration, $plugin_id, $plugin_definition, ImmutableConfig $module_configuration, DefaultFacetManager $facets_manager) {
  //   parent::__construct($configuration, $plugin_id, $plugin_definition);
  // }

  /**
   * {@inheritdoc}
   */
  // public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
  //   $module_configuration = $container->get('config.factory')->get('fullcalendar_solr.settings');
  //   return new static($configuration, $plugin_id, $plugin_definition, $module_configuration);
  // }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();

    // @todo organize these better
    $options['date'] = ['default' => ''];
    $options['type'] = ['default' => 'multiMonthYear'];
    $options['year_field'] = ['default' => ''];

    $options['day_links'] = ['default' => TRUE];
    $options['day_path'] = ['default' => ''];

    $options['fullcalendar_config'] = [
      'contains' => [
        'initialView' => ['default' => 'multiMonthYear'],
        'multiMonthMinWidth' => ['default' => 200],
        'multiMonthMaxColumns' => ['default' => 4],
      ]
    ];

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

    $form['date'] = [
      '#type' => 'select',
      '#title' => t('Date Field'),
      '#required' => TRUE,
      '#options' => $view_fields_labels,
      '#description' => $this->t('The selected field should contain a string representing a date.'),
      '#default_value' => $this->options['date'],
    ];

    $form['year_field'] = [
      '#type' => 'select',
      '#title' => t('Year Field'),
      '#required' => TRUE,
      '#options' => $view_fields_labels,
      '#description' => $this->t('The selected field should contain a string or integer representing a date.'),
      '#default_value' => $this->options['year_field'],
    ];

    // @todo add more options
    $fullcalendar_displays = [
      'multiMonthYear' => $this->t('Year'),
      // 'dayGridMonth' => $this->t('Month'),
    ];

    $form['type'] = [
      '#type' => 'radios',
      '#title' => t('Type of Calendar'),
      '#required' => TRUE,
      '#options' => $fullcalendar_displays,
      '#default_value' => $this->options['type'],
    ];

    $form['fullcalendar_config']['multiMonthMinWidth'] = [
      '#type' => 'number',
      '#title' => t('Minimum month (pixel) width'),
      '#default_value' => $this->options['fullcalendar_config']['multiMonthMinWidth'],
    ];

    $form['day_links'] = [
      '#type' => 'checkbox',
      '#title' => t('Day Links'),
      '#default_value' => $this->options['day_links'],
      '#description' => t('Link to a day view when a highlighted date is clicked.')
    ];

    // @todo make this required if day_links is true
    $form['day_path'] = [
      '#type' => 'textfield',
      '#title' => t('Path to Day View'),
      '#default_value' => $this->options['day_path'],
      // '#disabled' => !$this->options['day_links'], // @todo need ajax callback
      '#description' => t('The view with this path should be configured such that it has a contextual filter that expects a string date of the form YYYY-MM-DD. The contextual filter should be the last component of the path. E.g. if the path is "calendar/day", it will redirect to "calendar/day/YYYY-MM-DD".'),
    ];

    // Extra CSS classes.
    $form['classes'] = [
      '#type' => 'textfield',
      '#title' => t('CSS classes'),
      '#default_value' => (isset($this->options['classes'])),
      '#description' => t('CSS classes for further customization of the calendar.'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    if (empty($this->options['date'])) {
      $this->messenger()->addWarning(t('The Date field mapping cannot be empty in FullCalendar Solr format settings.'));
      return;
    }
    // @todo should this be optional?
    if (empty($this->options['year_field'])) {
      $this->messenger()->addWarning(t('The Year field mapping cannot be empty in FullCalendar Solr format settings.'));
      return;
    }

    // Render the fields.  If it isn't done now then the row_index will be unset
    // the first time that getField() is called, resulting in an undefined
    // property exception.
    $this->renderFields($this->view->result);

    // Have to count the dates manually since Search API doesn't support aggregation,
    // and grouping by date will group items with same date but different time separately.
    $date_counts = [];
    foreach ($this->view->result as $row_index => $row) {
      $this->view->row_index = $row_index;
      $date = $this->buildDate($this->options['date']);
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

    // Format event data into the format required by the FullCalendar
    $events = [];
    foreach ($date_counts as $date => $count) {
      $events[] = [
        'id' => $date, // Set event id to the date since we have at most 1 events per day.
        'start' => $date,
        'count' => $count,
      ];
    }

    // \Drupal::logger("hi")->notice(json_encode(array_keys($this->view->argument))); // contextual filter field names
    $year_field = $this->options['year_field'];
    $year_facets = $this->getYearFacets($year_field);
    $years = [];
    foreach ($year_facets as $facet_data) {
      $years[] = trim($facet_data['filter'], '"');
      // trim($facet_data['count'], '"') to get result count
    }
    sort($years);

    // Skip theming if the view is being edited or previewed.
    if ($this->view->preview) {
      return '<pre>' . print_r($events, 1) . '</pre>';
    }

    return [
      '#theme' => $this->themeFunctions(),
      '#view' => $this->view,
      '#options' => [
        'fullcalendar_options' => $this->options['fullcalendar_config'],
        'day_links' => $this->options['day_links'],
        'day_path' => $this->options['day_path'],
      ],
      '#rows' => [
        'events' => ['events' => $events],
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
      $date = new DateTime($date_string);
    } catch (Exception $e) {
      // Return NULL if the field didn't contain a parseable date string.
      $this->messenger()->addMessage($this->t('The date "@date" does not conform to a <a href="@php-manual">PHP supported date and time format</a>.', ['@date' => $date_string, '@php-manual' => 'http://php.net/manual/en/datetime.formats.php']));
      $date = NULL;
    }
    return $date;
  }

  /**
   * Returns an array of facet data over all documents
   */
  protected function getYearFacets($year_field, $limit = -1, $min_count = 1, $missing = FALSE) {
    $year_facets = [];
    $index = $this->view->query->getIndex();
    $server = $index->getServerInstance();
    if ($server->supportsFeature('search_api_facets')) {
      // Copy of the query executed by view.
      $query = clone $this->view->query->getSearchApiQuery();
      $query->range(0, 0);

      // Remove existing condition filtering by year.
      $condition_group = $query->getConditionGroup();
      $this->deleteCondition($condition_group, $year_field, '=');

      // If the query already has a search_api_facets entry, this will override it.
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
    // @todo Should this through an exception if the server doesn't support search_api_facets?
    return $year_facets;
  }

  /**
   * Deletes the condition containing the given field and operator
   */
  protected function deleteCondition(&$condition_group, $field, $operator) {
    if (!isset($condition_group) || !isset($field)) {
      return;
    }

    $conditions = &$condition_group->getConditions();
    foreach ($conditions as $i => $condition) {
      // Check if the condition contains the target field
      if (strpos($condition, $field) === FALSE) {
        continue;
      }
      if ($condition instanceof ConditionGroupInterface) {
        $this->deleteCondition($condition, $field, $operator);
      } elseif ($condition->getField() === $field && $condition->getOperator() === $operator) {
        unset($conditions[$i]);
      }
    }
  }
}
