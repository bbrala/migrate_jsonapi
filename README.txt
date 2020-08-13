Migrate source json api module

Provide source parser plugin to migrate data from source of jsonapi.

Use this module

First install this module and this config your migrate yml file like below:

source:
  plugin: url
  jsonapi_host: http://host.com/jsonapi/
  jsonapi_endpoint: node/article
  jsonapi_filters:
    conditions:
      -
        key: tags_filter
        path: field_tags.name
        operator: =
        value: t1
  data_parser_plugin: jsonapi
  data_fetcher_plugin: http
  ids:
    nid:
      type: integer
  fields:
    -
      name: nid
      selector: /attributes/drupal_internal__nid
    -
      name: title
      selector: /attributes/title
    -
      name: tags
      selector: /attributes/drupal_internal__tid
      relationship: field_tags
      multiple: true

There are some points you should know above.
1. source plugin using 'url'
2. jsonapi_host define jsonapi host. You can set host in yml or set it in background.
3. jsonapi_endpoint define jsonapi endpoint. ref https://www.drupal.org/docs/8/modules/jsonapi/api-overview
4. jsonapi_filters is can add filters for jsonapi. ref to https://www.drupal.org/docs/8/modules/jsonapi/filtering
5. data_parser_plugin is jsonapi which this module providing.
6. data_fetcher_plugin is http, using http to access jsonapi source.
7. fields include two types, simple type like nid, title which are attributes in jsonapi, and the complex one is reference field in relationship section. For reference filed you should define relationship attribute.

when you add config above the migrate plugin will generate a jsonapi request http://http://host.com/jsonapi/node/article?include=field_tags&filter[tag-filter][condition][path]=field_tags.name&filter[tag-filter][condition][operator]==&filter[tag-filter][condition][value]=t1
Add all fields you want will be got in source row. You can use them in process mapping as you want.
