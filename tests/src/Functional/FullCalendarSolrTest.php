<?php

namespace Drupal\Tests\fullcalendar_solr\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\search_api\Entity\Index;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the FullCalendar Solr style plugin.
 *
 * @group fullcalendar_solr
 */
class FullCalendarSolrTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'views',
    'views_ui',
    'fullcalendar_solr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create admin user.
    $user = $this->drupalCreateUser([
      'access administration pages',
      'administer views',
      'administer search_api',
    ]);
    $this->drupalLogin($user);
  }

  /**
   * Tests integration with Views.
   */
  public function testAddView() {
    // Create custom content type.
    $content_type = 'my_custom_content_type';
    $this->drupalCreateContentType([
      'type' => $content_type,
      'name' => $content_type,
    ]);

    // Create year and date fields.
    $date_field = 'field_date_test';
    $year_field = 'field_year_test';
    $this->createContentTypeField($content_type, $date_field, $date_field, 'string');
    $this->createContentTypeField($content_type, $year_field, $year_field, 'integer');

    // Create Search API index.
    $index_id = 'my_custom_index';
    Index::create([
      'name' => 'My Custom Index',
      'id' => $index_id,
      'status' => TRUE,
      'datasource_settings' => [
        'entity:node' => [],
      ],
      'field_settings' => [
        $date_field => [
          'label' => $date_field,
          'type' => 'string',
          'datasource_id' => 'entity:node',
          'property_path' => $date_field,
          'indexed_locked' => TRUE,
          'type_locked' => TRUE,
        ],
        $year_field => [
          'label' => $year_field,
          'type' => 'integer',
          'datasource_id' => 'entity:node',
          'property_path' => $year_field,
          'indexed_locked' => TRUE,
          'type_locked' => TRUE,
        ],
      ],
    ])->save();

    // Create test view.
    $edit = [
      'id' => 'test',
      'label' => 'test',
      'show[wizard_key]' => "standard:search_api_index_$index_id",
      'show[sort]' => 'none',
      'page[create]' => TRUE,
      'page[title]' => 'Test',
      'page[path]' => 'test/year',
      'page[style][style_plugin]' => 'fullcalendar_solr',
    ];
    $this->drupalGet('admin/structure/views/add');
    $this->submitForm($edit, 'Save and edit');
    $this->assertSession()->pageTextContains('The view test has been saved.');

    // Assert the options of our exported view display correctly.
    $this->drupalGet('admin/structure/views/view/test/edit/page_1');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('FullCalendar Solr');

    // Add date field.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test/page_1/field');
    $this->assertSession()->pageTextContains($date_field);
    $edit = [
      "name[search_api_index_my_custom_index.$date_field]" => TRUE,
    ];
    $this->submitForm($edit, 'Add and configure fields');

    // Add contextual filter for the year.
    $this->drupalGet('admin/structure/views/nojs/add-handler/test/page_1/argument');
    $this->assertSession()->pageTextContains($year_field);
    $edit = [
      "name[search_api_index_my_custom_index.$year_field]" => TRUE,
    ];
    $this->submitForm($edit, 'Add and configure contextual filters');

    // Configure FullCalendar Solr style settings.
    $this->drupalGet('admin/structure/views/nojs/display/test/page_1/style_options');
    $this->assertSession()->fieldExists('style_options[date_field]');
    $this->assertSession()->fieldExists('style_options[year_field]');
    $edit = [
      'style_options[date_field]' => $date_field,
      'style_options[year_field]' => $year_field,
    ];
    $this->submitForm($edit, 'Apply');

    // Confirm style options were saved.
    $this->drupalGet('admin/structure/views/nojs/display/test/page_1/style_options');
    $this->assertSession()->fieldValueEquals('style_options[date_field]', $date_field);
    $this->assertSession()->fieldValueEquals('style_options[year_field]', $year_field);

    // Save the view.
    $this->drupalGet('admin/structure/views/view/test/edit/page_1');
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('The view test has been saved.');
  }

  /**
   * Adds a field to a content type.
   *
   * @param string $bundle
   *   The type of content type.
   * @param string $field_name
   *   The field name.
   * @param string $field_label
   *   The field label.
   * @param string $type
   *   The field type.
   */
  protected function createContentTypeField($bundle, $field_name, $field_label, $type) {
    FieldStorageConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'type' => $type,
    ])->save();
    FieldConfig::create([
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => $bundle,
      'label' => $field_label,
    ])->save();
  }

}
