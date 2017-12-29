<?php

$facets['catalog'] = [
  'On Shelf At' => [
    'name' => 'available_branches',
    'any' => 'Any Location',
    'downtown' => 'Downtown',
    'malletts' => 'Malletts Creek',
    'pittsfield' => 'Pittsfield',
    'traverwood' => 'Traverwood',
    'westgate' => 'Westgate'
  ],
  'Format' => [
    'has_subsections' => true,
    'name' => 'mat_code',
    'Books' => [
      'subsection' => true,
      'a' => 'Book',
      'zb' => 'eBook Download',
      'oe' => 'OverDrive eBook',
      'x' => 'Graphic Novel',
      'l' => 'Large Print',
      'c' => 'Music Score'
    ],
    'Audiobooks' => [
      'subsection' => true,
      'i' => 'Audiobook',
      'za' => 'Audiobook Download',
      'oa' => 'OverDrive eAudiobook'
    ],
    'Movies & TV' => [
      'subsection' => true,
      'g' => 'DVD',
      'u' => 'Blu-ray',
      'zm' => 'Streaming Video'
    ],
    'Music & Sound' => [
      'subsection' => true,
      'j' => 'CD',
      'h' => 'LP Record',
      'z' => 'Music Download',
    ],
    'Tools' => [
      'subsection' => true,
      'r' => 'Tools',
      'p' => 'Art Print'
    ]
  ],
  'New Arrivals' => [
    'name' => 'sort',
    'past_week' => 'Last 7 Days',
    'past_month' => 'Last 30 Days'
  ],
  'Fiction / Nonfiction' => [
    'name' => 'content',
    'fiction' => 'Fiction',
    'nonfiction' => 'Nonfiction'
  ],
  'Audience' => [
    'name' => 'ages',
    'youth' => 'Kids',
    'teen' => 'Teen',
    'adult' => 'Adult'
  ]
];

$facets['collections'] = [

];

$facets['website'] = [

];
