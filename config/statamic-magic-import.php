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
