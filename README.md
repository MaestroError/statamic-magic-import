# Statamic Magic Import

your go-to solution for effortless data imports. With a user-friendly interface and support for **JSON** data formats, importing data into Statamic becomes as easy as a wave of a wand. Experience the magic of hassle-free data management

_This addon can work with JSON files extracted using a [statamic exporter Wordpress plugin](https://github.com/maestroerror/wordpress-to-statamic-exporter)._

## Json import

[EXAMPLE OF JSON file]

## Features

Json import:

- Create taxonomies and terms
- Create pages
- Create collections and entries
  - includes fields choosing feature for importing in existing collections

_Any meta data key prefixed with an underscore will be ignored._

### Events

The addon is using the builtin methods for creating and saving content. As such, the normal events are dispatched which you can hook into for additional work according to your setup. That means you can listen on the following events to customize the import:

- `Statamic\Events\CollectionCreated`
- `Statamic\Events\CollectionSaved`
- `Statamic\Events\EntrySaving`
- `Statamic\Events\EntryCreated`
- `Statamic\Events\EntrySaved`
- `Statamic\Events\TaxonomySaved`
- `Statamic\Events\TermSaved`
- `Statamic\Events\AssetSaved`
- `Statamic\Events\AssetUploaded`

By the time you read this there might be others. Consult [the documentation](https://statamic.dev/extending/events#available-events) to learn more.

### Images

All URLs including image extensions (.png, .jpg, .webp and etc) will be downloaded. Featured images will be downloaded to the "assets" container by default (change in config), into a folder called "{collection_handle}/{entry_slug}", and saved in a [assets](https://statamic.dev/fieldtypes/assets) field.

## How to Install

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

```bash
composer require maestroerror/statamic-magic-import
```

## How to Use

Go to the `Tools > Magic Import` section and upload the json file.

For collections, the summary will show you 2 options: creating a new collection or importing in existing one.

When importing in existing collection, you can choose JSON field for each of your collection fields.

Even when creating new collection, there might be the collection with same name. If you choose to import it anyway, the content will be overwritten, but you can (De)Select anything you want (by titles) and click "Import".

[ADD SCREENSHOT HERE]

_Note: You might get timeout errors if you're importing large datasets and/or many images. _

## Config

The content of the config file looks like this:

```php
<?php

return [

    /*
     * Enable downloading images. The default is 'true'.
     */
    'download_images' => true,

    /**
     * The name of the assets container where images should be downloaded.
     */
    'assets_container' => 'assets',

    /*
     * Whether to skip download of an image if it already exist. The default is 'false'.
     */
    'skip_existing_images' => false,

    /*
     * Enable image overwriting. When set to false, a new image are created with a timestamp suffix, if the image already exists. The default is 'false'.
     */
    'overwrite_images' => false,

    /*
     * Filter out meta data keys prefixed with '_'. The default is 'true'.
     */
    'exclude_underscore_data' => true,

];

```

You can publish it with the command:

`php artisan vendor:publish --tag=statamic-magic-import`