<?php

namespace Drupal\migrate_plus_jsonapi\Plugin\migrate_plus\data_parser;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Url;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use False\MyClass;
use Swis\JsonApi\Client\Collection;
use Swis\JsonApi\Client\Parsers\CollectionParser;
use Swis\JsonApi\Client\Parsers\DocumentParser;
use Swis\JsonApi\Client\Parsers\ErrorCollectionParser;
use Swis\JsonApi\Client\Parsers\ErrorParser;
use Swis\JsonApi\Client\Parsers\ItemParser;
use Swis\JsonApi\Client\Parsers\JsonapiParser;
use Swis\JsonApi\Client\Parsers\LinksParser;
use Swis\JsonApi\Client\Parsers\MetaParser;
use Swis\JsonApi\Client\Parsers\ResponseParser;
use Swis\JsonApi\Client\TypeMapper;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Extension\ModuleHandler;
use Drupal\migrate\MigrateException;
use Drupal\migrate_plus_jsonapi\Form\MigrateSettingsForm;
use Drupal\migrate_plus\DataParserPluginBase;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * Obtain JSON data for migration.
 *
 * @DataParser(
 *   id = "jsonapi",
 *   title = @Translation("JSON:API")
 * )
 */
class JsonApi extends DataParserPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Iterator over the JSON data.
   *
   * @var \Iterator
   */
  protected $dataIterator;

  /**
   * Iterator over the JSON data.
   *
   * @var \Iterator
   */
  protected $includedIterator;

  /**
   * Config Factory.
   *
   * @var [Drupal\Core\Config\ConfigFactory]
   */
  private $configFactory;

  /**
   * Module handler.
   *
   * @var [Drupal\Core\Extension\ModuleHandler]
   */
  private $moduleHandler;

  /**
   * @var \Swis\JsonApi\Client\Parsers\DocumentParser
   */
  private $documentParser;

  /**
   * @var \Symfony\Component\PropertyAccess\PropertyAccessor
   */
  private $propertyAccessor;

  /**
   * @var \Swis\JsonApi\Client\Interfaces\DataInterface
   */
  private $dataCollection;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactory $config_factory, ModuleHandler $module_handler) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
    $this->documentParser = DocumentParser::create();
    $this->propertyAccessor = PropertyAccess::createPropertyAccessorBuilder()
      ->enableMagicCall()
      ->getPropertyAccessor();

    // Just compatible with base module DataParsePluginBase.
    // todo: Rewrite base class or set our real item selector.
    if (!isset($configuration['item_selector'])) {
      $configuration['item_selector'] = NULL;
    }

    if (!isset($configuration['jsonapi_endpoint'])) {
//      throw new MigrateException('JsonAPI endpoint not set.');
    }

    $jsonapi_host = $this->configFactory
      ->get(MigrateSettingsForm::MIGRATE_PLUS_JSONAPI_SETTINGS)
      ->get(MigrateSettingsForm::JSONAPI_REMOTE_HOST);

    if (empty($jsonapi_host) && isset($configuration['jsonapi_host'])) {
      $jsonapi_host = $configuration['jsonapi_host'];
    }

    if (empty($jsonapi_host)) {
//      throw new MigrateException('JSON:API host not set.');
    }

//    $configuration['urls'] = [$jsonapi_host . $configuration['jsonapi_endpoint']];

    parent::__construct($configuration, $plugin_id, $plugin_definition);

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('module_handler')
    );
  }

  /**
   * Retrieves the PSR-7 response document and parse the JSON:API document.
   *
   * @param string $url
   *   URL of a JSON feed.
   *
   * @return \Swis\JsonApi\Client\Interfaces\DocumentInterface
   *   The selected data to be iterated.
   *
   * @throws \GuzzleHttp\Exception\RequestException
   */
  protected function getResponseDocument($url) {
    $response = $this->getDataFetcherPlugin()->getResponseContent($url);
    return $this->documentParser->parse($response);
  }

  /**
   * {@inheritdoc}
   */
  protected function openSourceUrl($url) {
    $url = $this->modifySourceUrl($url);

    // (Re)open the provided URL.
    $responseDocument = $this->getResponseDocument($url);
    $data = $responseDocument->getData();
    $this->dataCollection = $data;
    $this->dataIterator = $this->dataCollection->getIterator();

    if ($responseDocument->getLinks()->offsetExists('next')){
      $this->urls[] = $responseDocument->getLinks()->offsetGet('next')->getHref();
    }

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function fetchNextRow() {
    /** @var \Swis\JsonApi\Client\Item $current */
    $current = $this->dataIterator->current();
    if ($current) {
      foreach ($this->fieldSelectors() as $field_name => $selector) {
        if (strpos($selector, '[*]') !== FALSE && substr_count($selector, '[*]') === 1) {
          $field_value = $this->fetchArrayOfRelationValues($selector, $current);
        } else {
          if (!$this->propertyAccessor->isReadable($current, $selector)) {
            throw new MigrateException('migrate_plus_jsonapi: Cannot get value with selector "' . $selector . '"');
          }
          $field_value = $this->propertyAccessor->getValue($current, $selector);
        }

        $this->currentItem[$field_name] = $field_value;
      }
      if (!empty($this->configuration['include_raw_data'])) {
        $this->currentItem['raw'] = $current;
      }
      $this->dataIterator->next();
    }
  }

  /**
   * @return array
   */
  protected function createDrupalJsonapiFilter(): array {
    $filters = [];
    foreach ($this->configuration['jsonapi_drupal_filters'] as $key => $value) {
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
   * @param string $url
   *
   * @return \Drupal\Core\GeneratedUrl|string
   */
  protected function modifySourceUrl(string $url) {
    $parts = UrlHelper::parse($url);
    $path = $parts['path'];

    $options = [];
    $options['query'] = $parts['query'];
    $options['fragment'] = $parts['fragment'];

    if (!empty($this->configuration['jsonapi_drupal_filters'])) {
      $filters = $this->createDrupalJsonapiFilter();
      $options['query'] = array_merge($options['query'], $filters);
    }

    // Add hook_migrate_plus_data_parser_jsonapi_pre_request_alter() to update jsonapi filter or relationships.
    if (method_exists($this->moduleHandler, 'alter')) {
      $this->moduleHandler->alter(
        'migrate_plus_data_parser_jsonapi_pre_request',
        $path,
        $options,
        $this
      );
    }

    try {
      $url = Url::fromUri($path, $options)->toString();
    } catch (\InvalidArgumentException $e){

    }
    return $url;
  }


  /**
   * Fetched an array of values from a relation with the field[*] selector.
   *
   * @param string $selector
   * @param \Swis\JsonApi\Client\Item $current
   *
   * @return array
   * @throws \Drupal\migrate\MigrateException
   */
  protected function fetchArrayOfRelationValues(string $selector, \Swis\JsonApi\Client\Item $current): array {
    $field_value = [];
    [$collectionSelector, $valueSelector] = explode('[*]', $selector);
    $valueSelector = ltrim($valueSelector, '.');

    if (!$this->propertyAccessor->isReadable($current, $collectionSelector)) {
      throw new MigrateException('migrate_plus_jsonapi: Cannot source collection of "' . $selector . '" with selector "' . $collectionSelector . '"');
    }

    // Get the collection based on selector
    $collection = $this->propertyAccessor->getValue($current, $collectionSelector);
    if ($collection instanceof Collection) {
      foreach ($collection as $item) {
        $field_value[] = $this->propertyAccessor->getValue($item, $valueSelector);
      }
    }
    else {
      throw new MigrateException('migrate_plus_jsonapi: Collection selector [*] didn\'t find the collection: "' . $selector . '"');
    }
    return $field_value;
  }

}
