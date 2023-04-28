<?php

namespace Drupal\fullcalendar_solr\EventSubscriber;

use Drupal\search_api_solr\Event\PostConvertedQueryEvent;
use Drupal\search_api_solr\Event\SearchApiSolrEvents;
use Drupal\search_api\Query\QueryInterface as SapiQueryInterface;
use Solarium\Core\Query\QueryInterface as SolariumQueryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Alters the query where necessary to implement business logic.
 *
 * @package Drupal\fullcalendar_solr\EventSubscriber
 */
class SolrQueryAlterEventSubscriber implements EventSubscriberInterface {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SearchApiSolrEvents::POST_CONVERT_QUERY => 'postConvertQuery',
    ];
  }
  /**
   * {@inheritdoc}
   */
  public function postConvertQuery(PostConvertedQueryEvent $event): void {
    $query = $event->getSearchApiQuery();
    $solarium_query = $event->getSolariumQuery();
    // drupal_log("here");
    // drupal_log($solarium_query->);
    // TODO format contextual filter date here
    // $solarium_query->setQuery("ds_field_edtf_date_created_1:[2023-04-11T00:00:00Z TO 2023-04-11T23:59:59Z]");
  }
}
