<?php

namespace Drupal\Tests\migrate_plus_jsonapi\Kernel\Plugin\migrate_plus\data_parser;

use Drupal\KernelTests\KernelTestBase;

/**
 * Test of the data_parser jsonapi plugin.
 *
 * @group migrate_plus_jsonapi
 */
class JsonApiTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['migrate', 'migrate_plus', 'migrate_plus_jsonapi'];

  /**
   * Tests missing properties in json file.
   *
   * @param string $file
   *   File name in tests/data/ directory of this module.
   * @param array $ids
   *   Array of ids to pass to the plugin.
   * @param array $fields
   *   Array of fields to pass to the plugin.
   * @param array $expected
   *   Expected array from json decoded file.
   *
   * @dataProvider jsonBaseDataProvider
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   * @throws \Exception
   */
  public function test($file, array $ids, array $fields, array $expected): void {
    $path = $this->container
      ->get('module_handler')
      ->getModule('migrate_plus_jsonapi')
      ->getPath();
    $url = $path . '/tests/data/' . $file;

    /** @var \Drupal\migrate_plus\DataParserPluginManager $plugin_manager */
    $plugin_manager = $this->container
      ->get('plugin.manager.migrate_plus.data_parser');
    $conf = [
      'plugin' => 'url',
      'data_fetcher_plugin' => 'file',
      'data_parser_plugin' => 'jsonapi',
      'destination' => 'node',
      'urls' => [$url],
      'ids' => $ids,
      'fields' => $fields,
      'item_selector' => NULL,
      'jsonapi_host' => $url,
      'jsonapi_endpoint' => '',
    ];
    $jsonapi_parser = $plugin_manager->createInstance('jsonapi', $conf);

    $data = [];
    foreach ($jsonapi_parser as $item) {
      $data[] = $item;
    }

    $this->assertEquals($expected, $data);
  }

  /**
   * Provides multiple test cases for the testMissingProperty method.
   *
   * @return array
   *   The test cases.
   */
  public function jsonBaseDataProvider(): array {
    return [
      'base fields test' => [
        'file' => 'jsonapi_document.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => 'id',
          ],
          [
            'name' => 'title',
            'label' => 'Title',
            'selector' => 'title',
          ],
        ],
        'expected' => [
          [
            'id' => 'a542e833-edfe-44a3-a6f1-7358b115af4b',
            'title' => 'Frankfurter salad with mustard dressing',
          ],
          [
            'id' => '84cfaa18-faca-471f-bfa5-fbb8c199d039',
            'title' => 'Majorcan vegetable bake',
          ],
        ],
      ],
      'field nested values test' => [
        'file' => 'jsonapi_document.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => 'id',
          ],
          [
            'name' => 'langcode',
            'label' => 'Path langcode nested',
            'selector' => 'path.langcode',
          ],
        ],
        'expected' => [
          [
            'id' => 'a542e833-edfe-44a3-a6f1-7358b115af4b',
            'langcode' => 'en',
          ],
          [
            'id' => '84cfaa18-faca-471f-bfa5-fbb8c199d039',
            'langcode' => 'en',
          ],
        ],
      ],
      'array values test' => [
        'file' => 'jsonapi_document.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => 'id',
          ],
          [
            'name' => 'ingredient_list',
            'label' => 'Ingredient array',
            'selector' => 'ingredients',
          ],
          [
            'name' => 'ingredient_first',
            'label' => 'First ingredient in array',
            'selector' => 'ingredients[0]',
          ]
        ],
        'expected' => [
          [
            'id' => 'a542e833-edfe-44a3-a6f1-7358b115af4b',
            'ingredient_list' => [
              '675 g (1.5 lb) small new salad potatoes such as Pink Fir Apple',
              '3 eggs (not too fresh for hard-boiled eggs)',
              '350 g (12 oz) frankfurters - Siamese cats love Frankfurters!',
              '1 lettuce',
              ' chopped.  Any type will do',
              ' a Chinese cabbage worked well the last time I made this.',
              '1 packet of young spinach leaves or rocket leaves',
              ' about 225 g (8 oz)',
              'A handful of chives',
              ' chopped (if in season)',
              'Dressing',
              '3 tablespoons (30 ml) olive oil',
              '1 tablespoon (15 ml) white wine vinegar',
              'Pinch of sugar',
              'Pinch of salt',
              'Dash of lemon juice',
              '2 teaspoons (10 ml) American squeezy mustard',
              '1 teaspoon (5 ml) caraway seeds',
              ' crushed in a pestle and mortar'
            ],
            'ingredient_first' => '675 g (1.5 lb) small new salad potatoes such as Pink Fir Apple',
          ],
          [
            'id' => '84cfaa18-faca-471f-bfa5-fbb8c199d039',
            'ingredient_list' => [
              '400g can peeled plum tomatoes',
              ' chopped',
              '1 tablespoon fresh oregano or marjoram',
              ' chopped',
              '3 tablespoon olive oil plus a little more to dress the dish with',
              '6 fat Cloves of garlic (or to taste)',
              ' half peeled and crushed',
              ' the remaining half peeled and roughly chopped',
              '1 aubergine',
              ' sliced',
              '1 sweet bell pepper of any colour',
              '1 large onion',
              ' peeled and sliced',
              '650g potatoes',
              ' peeled and sliced: Desiree',
              ' King Edward',
              ' Lady Balfour',
              ' Maris Piper',
              ' Melody etc.',
              'sea salt and freshly ground black pepper'
            ],
            'ingredient_first' => '400g can peeled plum tomatoes',
          ],
        ],
      ],
      'relation fields test' => [
        'file' => 'jsonapi_document.json',
        'ids' => ['id' => ['type' => 'integer']],
        'fields' => [
          [
            'name' => 'id',
            'label' => 'Id',
            'selector' => 'id',
          ],
          [
            'name' => 'first_tag_name',
            'label' => 'Tag relation field first value',
            'selector' => 'tags[0].name',
          ],
          [
            'name' => 'first_tag_path_langcode',
            'label' => 'Tag relation value of nested field in relation',
            'selector' => 'tags[0].path.langcode',
          ],
          [
            'name' => 'tag_name',
            'label' => 'Tag relation value of nested field in relation',
            'selector' => 'tags[*].name',
          ],
        ],
        'expected' => [
          [
            'id' => 'a542e833-edfe-44a3-a6f1-7358b115af4b',
            'first_tag_name' => 'German',
            'first_tag_path_langcode' => 'en',
            'tag_name' => [
              'German'
            ],
          ],
          [
            'id' => '84cfaa18-faca-471f-bfa5-fbb8c199d039',
            'first_tag_name' => 'Vegetable',
            'first_tag_path_langcode' => 'en',
            'tag_name' => [
              'Vegetable',
              'Vegetarian',
              'Vegan',
              'Spanish',
            ],
          ],
        ],
      ],
    ];
  }

}
