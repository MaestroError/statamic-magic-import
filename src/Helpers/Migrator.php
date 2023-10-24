<?php

namespace maestroerror\StatamicMagicImport\Helpers;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Statamic\Assets\Asset;
use Statamic\Facades\AssetContainer;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;
use Statamic\Facades\Term;
use Statamic\Facades\Taxonomy;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Migrator
{
    /**
     * The migration array
     *
     * @var array
     */
    private $migration;

    /**
     * The summary array
     *
     * @var array
     */
    private $summary;

    /**
     * Perform the migration
     *
     * @param $migration
     * @param $summary
     */
    public function migrate($migration, $summary, $collectionPairs, $collectionFieldPairs)
    {
        $this->migration = $this->prepareMigration($migration);
        $this->summary = $summary;
        $this->collectionPairs = $collectionPairs;
        $this->collectionFieldPairs = $collectionFieldPairs;

        $this->createTaxonomies();
        $this->createTaxonomyTerms();

        // dd($this->collectionFieldPairs);
        $this->handleExistingCollectionImport();
        $this->removePairsFromJsonCollections();
        // dd($this->migration);

        $this->createCollections();
        $this->createEntries();

        $this->createPages();

        Stache::clear();
    }

    /**
     * Prepare the migration
     *
     * @param array $migration
     * @return array
     */
    private function prepareMigration($migration)
    {
        $migration['pages'] = collect(
            $this->sortDeepest(
                array_get($migration, 'pages', [])->all()
            )
        );

        return $migration;
    }

    /**
     * Sort an array by folder depth (amount of slashes)
     *
     * @param  array $arr An array with paths for keys
     * @return array      The sorted array
     */
    private function sortDeepest($arr)
    {
        uksort($arr, function ($a, $b) {
            return (substr_count($a, '/') >= substr_count($b, '/')) ? 1 : -1;
        });

        // Move homepage to top
        if (isset($arr['/'])) {
            $arr = ['/' => $arr['/']] + $arr;
        }

        return $arr;
    }

    /**
     * Create taxonomies
     *
     * @return void
     */
    private function createTaxonomies()
    {
        foreach (array_get($this->migration, 'taxonomies', []) as $taxonomy_slug => $taxonomy_data) {
            $taxonomy = Taxonomy::findByHandle($taxonomy_slug);

            if (!$taxonomy) {
                $taxonomy = Taxonomy::make($taxonomy_slug);
            }

            foreach ($taxonomy_data as $key => $value) {
                $taxonomy->title($value);
                // Has not set method, title is only field to set
                // $taxonomy->set($key, $value);
            }

            $taxonomy->save();
        }
    }

    /**
     * Create taxonomy terms
     *
     * @return void
     */
    private function createTaxonomyTerms()
    {
        foreach (array_get($this->migration, 'terms', []) as $taxonomy_slug => $terms) {
            foreach ($terms as $term_slug => $term_data) {
                // Skip if this term was not checked in the summary.
                if (!$this->summary['taxonomies'][$taxonomy_slug]['terms'][$term_slug]['_checked']) {
                    continue;
                }

                $term = Term::findByUri($term_slug);

                if (!$term) {
                    $term = Term::make($term_slug)->taxonomy($taxonomy_slug);
                }

                foreach ($term_data as $key => $value) {
                    $term->set($key, $value);
                }

                $term->save();
            }
        }
    }

    /**
     * Create collections
     *
     * @return void
     */
    private function createCollections()
    {
        foreach (array_get($this->migration, 'collections', []) as $handle => $data) {
            $collection = Collection::findByHandle($handle);

            if (!$collection) {
                $collection = Collection::make($handle);
            }

            $collection->dated(true);
            $collection->sortDirection('desc');
            $collection->futureDateBehavior('private');
            $collection->pastDateBehavior('public');
            $collection->save();
        }
    }

    /**
     * Create entries
     *
     * @return void
     */
    private function createEntries()
    {
        foreach ($this->migration['entries'] as $collection => $entries) {
            foreach ($entries as $slug => $meta) {
                // Skip if this entry was not checked in the summary.
                if (!$this->summary['collections'][$collection]['entries'][$slug]['_checked']) {
                    continue;
                }

                $entry = Entry::query()->where('collection', $collection)->where('slug', $slug)->first();

                if (!$entry) {
                    $entry = Entry::make()->collection($collection)->slug($slug);
                }

                if (isset($meta['order']) && isset($meta['date'])) {
                    $collection = Collection::findByHandle($collection);
                    $collection->dated(true);
                    $collection->save();
                    $entry->date($meta['order']);
                }

                array_set($meta, 'data.slug', $slug);

                foreach ($meta['data'] as $key => $value) {
                    if ($this->isImageUrl($value)) {
                        $asset = $this->downloadAndReturnAsset($key, $value, $collection, $slug);
                        if ($asset) {
                            // Set asset
                            $entry = $this->setAsset($entry, $key, $asset);
                        }
                    } else {
                        $entry = $this->setFieldData($entry, $key, $value);
                        $entry->set($key, $value);
                    }
                }

                $entry->save();
            }
        }
    }

    /**
     * Create pages
     *
     * @return void
     */
    private function createPages()
    {
        foreach ($this->migration['pages'] as $url => $meta) {
            // Skip if this page was not checked in the summary.
            if (!$this->summary['pages'][$url]['_checked']) {
                continue;
            }

            $urlParts = explode('/', $url);
            $slug = array_pop($urlParts);

            $page = Entry::query()->where('collection', 'pages')->where('slug', $slug)->first();

            if (!$page) {
                $page = Entry::make()->collection('pages')->slug($slug);
            }

            array_set($meta, 'data.slug', $slug);

            foreach ($meta['data'] as $key => $value) {
                $page->set($key, $value);
            }

            if (config('statamic-magic-import.download_images')) {
                $asset = $this->downloadAsset($meta['data']['featured_image_url'] ?? '', 'pages', $slug);

                if ($asset) {
                    $page->set('featured_image', $asset->path() . config('statamic-magic-import.image_suffix'));
                }
            }

            $page->save();
        }
    }

    /**
     * Create an asset from a URL
     *
     * @param string|null $url
     * @return Asset|bool
     */
    private function downloadAsset(string $url = null, string $collection, string $slug): Asset|bool
    {
        if (!$url) {
            return false;
        }

        try {
            $image = Http::retry(3, 500)->get($url)->body();

            $originalImageName = basename($url);

            Storage::put($tempFile = 'temp', $image);

            $assetContainer = AssetContainer::findByHandle(config('statamic-magic-import.assets_container'));

            $asset = $assetContainer->makeAsset("{$collection}/{$slug}/{$originalImageName}");

            if ($asset->exists() && config('statamic-magic-import.skip_existing_images')) {
                return $asset;
            }

            if ($asset->exists() && config('statamic-magic-import.overwrite_images')) {
                $asset->delete();
            }

            $asset->upload(
                new UploadedFile(
                    Storage::path($tempFile),
                    $originalImageName,
                )
            );

            $asset->save();

            return $asset->fresh();
        } catch (Exception $e) {
            // Log the error
            logger('Image download failed: ' . $e->getMessage());
            return false;
        }
    }

    private function removePairsFromJsonCollections() {
        if ($this->collectionPairs) {
            foreach ($this->collectionPairs as $jsonCollection => $sttmCollection) {
                unset($this->migration['collections'][$jsonCollection]); 
                unset($this->migration['entries'][$jsonCollection]); 
            }
        }
    }

    // collectionPairs
    // collectionFieldPairs
    private function handleExistingCollectionImport() {

        foreach ($this->migration['entries'] as $collection => $entries) {
            foreach ($entries as $slug => $meta) {
                // Skip if this entry was not checked in the summary.
                if (!$this->summary['collections'][$collection]['entries'][$slug]['_checked']) {
                    continue;
                }
                // Skip if collection isn't paired
                if (!isset($this->collectionPairs[$collection])) {
                    continue;
                }
                if (!$this->collectionPairs[$collection]) {
                    continue;
                }
                // Find existing collection from summary
                $existingCollection = $this->collectionPairs[$collection];

                $entry = Entry::query()->where('collection', $existingCollection)->where('slug', $slug)->first();

                if (!$entry) {
                    $entry = Entry::make()->collection($existingCollection)->slug($slug);
                }

                
                if (isset($meta['order']) && isset($meta['date'])) {
                    $collection = Collection::findByHandle($existingCollection);
                    $collection->dated(true);
                    $collection->save();
                    $entry->date($meta['order']);
                }

                array_set($meta, 'data.slug', $slug);

                foreach ($meta['data'] as $key => $value) {
                    if (isset($this->collectionFieldPairs[$collection][$key]) && $this->collectionFieldPairs[$collection][$key]) {
                        // If field is image URL
                        if ($this->isImageUrl($value)) {
                            // Download an asset
                            $asset = $this->downloadAndReturnAsset($key, $value, $collection, $slug);
                            if ($asset) {
                                // Set asset
                                $entry = $this->setAsset($entry, $this->collectionFieldPairs[$collection][$key], $asset);
                            }
                        } else {
                            $entry = $this->setFieldData($entry, $this->collectionFieldPairs[$collection][$key], $value);
                        }
                    }
                }
                
                $entry->save();
            }
        }
    }

    private function downloadAndReturnAsset($key, $value, $collection, $slug) {
        if (config('statamic-magic-import.download_images')) {
            $asset = $this->downloadAsset($value, $collection, $slug);

            if ($asset) {
                return $asset;
            }
        }

        return false;
    }

    private function isImageUrl($url) {
        if (is_array($url)) {
            return false;
        }
        $imageExtensions = ['jpeg', 'jpg', 'png', 'gif', 'bmp', 'webp', 'svg']; // Add or remove file extensions as needed
    
        // Parse the URL to get just the path
        $path = parse_url($url, PHP_URL_PATH);
    
        if ($path) {
            // Get the file extension from the path
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    
            // Check if the file extension is in the list of image extensions
            return in_array($extension, $imageExtensions);
        }
    
        return false;
    }

    // Set field as in CP controller
    private function setUsingFields($entry, $key, $value) {
        $data = [$key => $value];

        // Get fields and add values
        $fields = $entry
            ->blueprint()
            ->ensureField('published', ['type' => 'toggle'])
            ->fields()
            ->addValues($data);

        // Process values by fields
        $values = $fields->process()->values();

        // Merge with entry data
        $entry->merge($values->only($key));

        $entry->save();

        return $entry->fresh();
    }

    // Sets asset by "set_images_as" config
    private function setAsset($entry, $key, $asset) {
        if (config('statamic-magic-import.set_images_as') == 'object') {
            // As Asset object
            $entry = $this->setFieldData($entry,  $key, $asset);
        } elseif(config('statamic-magic-import.set_images_as') == 'id') {
            // As array of IDs
            $entry = $this->setFieldData($entry,  $key, [config('statamic-magic-import.assets_container') . "::" . $asset->path() . config('statamic-magic-import.image_suffix')]);
        } else {
            // As asset's path
            $entry = $this->setFieldData($entry,  $key, $asset->path() . config('statamic-magic-import.image_suffix'));
        }
        return $entry;
    }

    // Sets field data by "set_data_using_fields" config
    private function setFieldData($entry, $key, $value) {
        if ($value) {
            if (config('statamic-magic-import.set_data_using_fields')) {
                $entry = $this->setUsingFields($entry, $key, $value);
            } else {
                $entry->set($key, $value);
            }
        }
        return $entry;
    }
}
