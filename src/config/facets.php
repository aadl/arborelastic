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
    'Books & Print' => [
      'subsection' => true,
      'a' => 'Book',
      'zb' => 'eBook Download',
      'oe' => 'OverDrive eBook',
      'x' => 'Graphic Novel',
      'l' => 'Large Print',
      'c' => 'Music Score',
      's' => 'Magazines',
      'n' => 'Newspapers'
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
  'Audience' => [
    'name' => 'ages',
    'youth' => 'Kids',
    'teen' => 'Teen',
    'adult' => 'Adult'
  ],
  'Language' => [
    'name' => 'lang',
    'eng' => 'English',
    'ara' => 'Arabic',
    'chi' => 'Chinese',
    'fre' => 'French',
    'ger' => 'German',
    'hin' => 'Hindi',
    'jpn' => 'Japanese',
    'kor' => 'Korean',
    'rus' => 'Russian',
    'spa' => 'Spanish'
  ]
];

$facets['community'] = [
  'Type' => [
    'name' => 'mat_code',
    'photo' => 'Photos',
    'article' => 'Articles',
    'issue' => 'Issues',
    'doc' => 'Documents'
  ],
  'Old News' => [
    'name' => 'old_news_taxonomy',
    '&quot;Old News&quot;' => 'All Old News',
    '&quot;Agenda&quot;' => 'Agenda',
    '&quot;Ann Arbor Argus&quot;' => 'Ann Arbor Argus',
    '&quot;Ann Arbor Argus-Democrat&quot;' => 'Ann Arbor Argus-Democrat',
    '&quot;Ann Arbor Courier&quot;' => 'Ann Arbor Courier',
    '&quot;Ann Arbor News&quot;' => 'Ann Arbor News',
    '&quot;Ann Arbor Sun&quot;' => 'Ann Arbor Sun',
    '&quot;Michigan Argus&quot;' => 'Michigan Argus',
    '&quot;Signal of Liberty&quot;' => 'Signal of Liberty',
  ]
];

$facets['website'] = [
  'Type' => [
    'name' => 'mat_code',
    'page' => 'Page',
    'story' => 'Staff Post',
    'pub_event' => 'Event'
  ],
  'Event Location' => [
    'name' => 'location_name',
    'downtown' => 'Downtown',
    'malletts' => 'Malletts Creek',
    'pittsfield' => 'Pittsfield',
    'traverwood' => 'Traverwood',
    'westgate' => 'Westgate'
  ],
  'Event Age' => [
    'name' => 'brochure_type',
    'adult' => 'Adult',
    'kid,jump' => 'Kid',
    'teen,axis' => 'Teen'
  ],
  'Event Types' => [
    'name' => 'event_categories',
    'art' => 'Art',
    'author events' => 'Author Events',
    'crafts' => 'Crafts',
    'film/video events' => 'Film/Video Events',
    'games' => 'Games',
    'performers' => 'Performers',
    'tournaments' => 'Tournaments',
    'tutoring' => 'Tutoring',
    'workshops &amp; classes' => 'Workshops &amp; Classes',
    'youth play groups' => 'Youth Playgroups',
    'youth storytimes' => 'Youth Storytimes'
  ]
];
