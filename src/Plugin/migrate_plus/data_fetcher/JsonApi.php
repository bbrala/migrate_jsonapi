<?php

namespace Drupal\migrate_plus_jsonapi\Plugin\migrate_plus\data_fetcher;

use Drupal\migrate_plus\Plugin\migrate_plus\data_fetcher\Http;
use Drupal\migrate\MigrateException;
use GuzzleHttp\Exception\RequestException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Swis\JsonApi\Client\Parsers\DocumentParser;

/**
 * Retrieve data over an HTTP connection for migration.
 *
 * Example:
 *
 * @code
 * source:
 *   plugin: url
 *   data_fetcher_plugin: http
 *   headers:
 *     Accept: application/json
 *     User-Agent: Internet Explorer 6
 *     Authorization-Key: secret
 *     Arbitrary-Header: foobarbaz
 *   jsonapi_filters:
 *     groups:
 *       -
 *         key: ag
 *         conjunction: OR
 *       -
 *         key: bg
 *         conjunction: and
 *     conditions:
 *       -
 *         key: a
 *         path: value
 *         operator: STARTS_WITH
 *         value: value_a
 *         memberOf: ag
 *       -
 *         key: b
 *         path: value
 *         operator: STARTS_WITH
 *         value: value_b
 *         memberOf: ag
 *       -
 *         key: c
 *         path: value
 *         operator: STARTS_WITH
 *         value: value_c
 *         memberOf: bg
 *       -
 *         key: d
 *         path: value
 *         operator: STARTS_WITH
 *         value: value_d
 * @endcode
 *
 * @DataFetcher(
 *   id = "jsonapi",
 *   title = @Translation("JSON:API")
 * )
 */
class Jsonapi extends Http {

  private $documentParser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $this->documentParser = DocumentParser::create();

    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse($url) {
    try {
      $urlParts = UrlHelper::parse($url);
      if (!empty($this->configuration['jsonapi_filters'])) {
        $filters = $this->createDrupalJsonapiFilter();

        $path = $urlParts['path'];
        $options['query'] = $urlParts['query'];
        $options['fragment'] = $urlParts['fragment'];
        $options['query'] = array_merge($urlParts['query'], $filters);
        $url = Url::fromUri($path, $options)->toString();
      }

      $options = ['headers' => $this->getRequestHeaders()];
      if (!empty($this->configuration['authentication'])) {
        $options = array_merge($options, $this->getAuthenticationPlugin()->getAuthenticationOptions());
      }
      $response = $this->httpClient->get($url, $options);
      if (empty($response)) {
        throw new MigrateException('No response at ' . $url . '.');
      }

      $requiredIncludes = $this->getRequiredIncludesFromResponse($response);

      if(array_key_exists('includes', $options['query'])){
        $requiredIncludes = array_merge($requiredIncludes, $options['query']['includes']);

        if (count($requiredIncludes) !== count($options['query']['includes'])) {
          $options['query']['includes'] = $requiredIncludes;

          $response = $this->httpClient->get($url, $options);
          if (empty($response)) {
            throw new MigrateException('No response at ' . $url . '.');
          }
        }
      }

    }
    catch (RequestException $e) {
      throw new MigrateException('Error message: ' . $e->getMessage() . ' at ' . $url . '.');
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponseContent($url) {
    $response = $this->getResponse($url);
    return $response->getBody();
  }

  /**
   * @return array
   */
  protected function createDrupalJsonapiFilter(): array {
    $filters = [];
    foreach ($this->configuration['jsonapi_filters'] as $key => $value) {
      if ($key == 'groups') {
        foreach ($value as $v) {
          if (isset($v['key']) && isset($v['conjunction'])) {
            $filters['filter'][$v['key']]['group']['conjunction'] = $v['conjunction'];
          }
        }
      }
      if ($key == 'conditions') {
        foreach ($value as $v) {
          if (isset($v['key']) && isset($v['path']) && isset($v['operator'])) {
            $filters['filter'][$v['key']]['condition']['path'] = $v['path'];
            if (isset($v['value'])) {
              $filters['filter'][$v['key']]['condition']['value'] = $v['value'];
            }
            $filters['filter'][$v['key']]['condition']['operator'] = $v['operator'];
            if (isset($v['memberOf'])) {
              $filters['filter'][$v['key']]['condition']['memberOf'] = $v['memberOf'];
            }
          }
        }
      }
    }
    return $filters;
  }

  /**
   * Find all relations in the document and add them
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *
   * @return array
   */
  protected function getRequiredIncludesFromResponse(\Psr\Http\Message\ResponseInterface $response) {
    $responseDocument = $this->documentParser->parse($response->getBody());
    $requiredIncludes = [];
    $responseDocument->getIncluded()->each(
      function (\Swis\JsonApi\Client\Item $item, $key) {
        $relations = $item->getRelations();
        if (!empty($relations)) {
          foreach ($relations as $relation) {
            $relationTypes[$relation->getType()] = TRUE;
          }
        }
      }
    );
    $responseDocument->getData()->each(
      function (\Swis\JsonApi\Client\Item $item, $key) {
        $relations = $item->getRelations();
        if (!empty($relations)) {
          foreach ($relations as $relation) {
            $relationTypes[$relation->getType()] = TRUE;
          }
        }
      }
    );
    if (empty($requiredIncludes)){
        return [];
    }

    return array_flip($requiredIncludes);
  }
}
