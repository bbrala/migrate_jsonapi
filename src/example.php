<?php
require '../vendor/autoload.php';

use Swis\JsonApi\Client\Parsers\CollectionParser;
use Swis\JsonApi\Client\Parsers\DocumentParser;
use Swis\JsonApi\Client\Parsers\ErrorCollectionParser;
use Swis\JsonApi\Client\Parsers\ErrorParser;
use Swis\JsonApi\Client\Parsers\ItemParser;
use Swis\JsonApi\Client\Parsers\JsonapiParser;
use Swis\JsonApi\Client\Parsers\LinksParser;
use Swis\JsonApi\Client\Parsers\MetaParser;
use Swis\JsonApi\Client\TypeMapper;

$apiClient = new \Swis\JsonApi\Client\Client();

// I choose not to set base url because the "NEXT" links normally contain the whole url
// $apiClient->setBaseUri($baseurl);

/**
 * @return \Swis\JsonApi\Client\Parsers\ResponseParser
 */
function createResponseParser() {
  $metaParser = new MetaParser();
  $linksParser = new LinksParser($metaParser);
  $itemParser = new ItemParser(new TypeMapper(), $linksParser, $metaParser);
  $errorCollectionParser = new ErrorCollectionParser(
    new ErrorParser($linksParser, $metaParser)
  );

  $documentParser = new DocumentParser(
    $itemParser,
    new CollectionParser($itemParser),
    $errorCollectionParser,
    $linksParser,
    new JsonapiParser($metaParser),
    $metaParser
  );

  return new \Swis\JsonApi\Client\Parsers\ResponseParser(
    $documentParser
  );
}
$client = new \Swis\JsonApi\Client\DocumentClient($apiClient, createResponseParser());
$endpointUrl = 'https://cms.contentacms.io/api/recipes';
$endpointUrl = 'https://rdambouwt.acceptatie.swis.nl/api/project';
$response = $client->get($endpointUrl);

/** @var Swis\JsonApi\Client\Collection $collection */
$collection = $response->getData();

// While there is a "next" link there are more pages
while ($response->getLinks()->offsetExists('next')) {
  $response = $client->get($response->getLinks()->offsetGet('next')->getHref());

  // Merge the next page with the current collection
  $collection = $collection->merge($response->getData());
}


/** @var \Swis\JsonApi\Client\Item $item */
foreach ($collection as $item) {
  // Do stuff with the items
  if ($item->hasAttribute('title')) {
    echo $item->title . '<br>';
  }
}
