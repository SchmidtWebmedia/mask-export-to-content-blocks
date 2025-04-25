<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Mask_Export to ContentBlocks',
    'description' => 'TYPO3 extension for semi-automatic migration of your mask export extension to ContentBlocks.',
    'version' => '0.9.0',
    'state' => 'beta',
    'author' => 'Marco Schmidt',
    'author_email' => 'typo@schmidt-webmedia.de',
    'author_company' => 'Marco Schmidt - Webmedia',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.3.99',
            'typo3' => '12.4.0-12.99.99',
            'friendsoftypo3/content-blocks' => '0.7.0-0.7.99'
        ],
    ],
    'autoload' => [
        'psr-4' => ['SchmidtWebmedia\\MaskExportToContentBlocks\\' => 'Classes']
    ],
];
