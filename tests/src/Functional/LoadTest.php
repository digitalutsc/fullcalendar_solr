<?php

namespace Drupal\Tests\fullcalendar_solr\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests to ensure that main page loads with module enabled.
 *
 * @group fullcalendar_solr
 */
class LoadTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'fullcalendar_solr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Set the front page to "/node".
    \Drupal::configFactory()
      ->getEditable('system.site')
      ->set('page.front', '/node')
      ->save(TRUE);
  }

  /**
   * Make sure the site still works. For now just check the front page.
   */
  public function testTheSiteStillWorks() {
    // Load the front page.
    $this->drupalGet('<front>');

    // Confirm that the site didn't throw a server error or something else.
    $this->assertSession()->statusCodeEquals(200);
  }

}
