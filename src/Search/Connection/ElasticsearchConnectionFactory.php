<?php

namespace Drupal\arborelastic\Search\Connection;

use Elasticsearch\Client;
use Elasticsearch\ClientBuilder;

class ElasticsearchConnectionFactory
{
  public function create(): Client
  {
    $hosts = [
      \Drupal::config('arborelastic.settings')->get('arborelastic_host') . ':' . \Drupal::config('arborelastic.settings')->get('arborelastic_port'),
    ];
    return ClientBuilder::create()->setHosts($hosts)->build();
  }
}
