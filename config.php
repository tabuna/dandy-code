<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Book Title
    |--------------------------------------------------------------------------
    |
    | This value will be written into the "Title" property of generated PDF
    | documents. It does not control any visible content, but is stored as
    | metadata inside the PDF file for identification purposes.
    |
    */

    'title' => 'Dandy Code',

    /*
    |--------------------------------------------------------------------------
    | Author Name
    |--------------------------------------------------------------------------
    |
    | This value will be written into the "Author" property of generated PDF
    | documents. Like the title, it is stored as internal metadata and will
    | not be displayed in the visible content of the document itself.
    |
    */

    'author' => 'Chernyaev Alexandr',

    /*
    |--------------------------------------------------------------------------
    | Available Fonts
    |--------------------------------------------------------------------------
    |
    | Here you may specify the list of fonts available for use when rendering
    | documents with mPDF. The keys represent the font names, while the
    | values should point to the corresponding font file names.
    |
    */

    'fonts' => [
        'JetBrains Mono' => 'JetBrainsMono.ttf',
        'EB Garamond'    => 'EBGaramond.ttf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Document Dimensions
    |--------------------------------------------------------------------------
    |
    | These options define the dimensions and margins used when generating
    | documents with mPDF. The keys map directly to mPDF configuration
    | parameters, so you may safely adjust them to your needs.
    |
    */

    'document' => [
        'format'        => [148, 210],
        'margin_left'   => 15,
        'margin_right'  => 15,
        'margin_top'    => 12,
        'margin_bottom' => 12,
    ],

];
