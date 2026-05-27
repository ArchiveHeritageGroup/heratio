<?php
/**
 * Package config defaults for ahg-biblio-bf (BIBFRAME 2.0 serializer).
 * Operators who want non-default behaviour can publish + override these.
 */
return [
    'formats' => [
        'turtle' => [
            'enabled' => true,
            'content_type' => 'text/turtle; charset=utf-8',
        ],
    ],
    'defaults' => [
        'culture' => 'en',
    ],
];
