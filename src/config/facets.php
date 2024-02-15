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
      'om' => 'OverDrive eMagazines',
      'n' => 'Newspapers',
      'zp' => 'Knitting Pattern Download'
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
    'Unusual Stuff' => [
      'subsection' => true,
      'r' => 'Tools',
      'p' => 'Art Print',
      'd' => 'Board Games',
      'q' => 'Puzzles'
    ]
  ],
  'Fiction & Nonfiction' => [
    'name' => 'nonfiction',
    'false' => 'Fiction',
    'true' => 'Nonfiction'
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
    'doc' => 'Documents',
    'media' => 'Media',
    'advertisements' => 'Advertisements'
  ],
  'Old News' => [
    'name' => 'old_news_taxonomy',
    'Old News' => 'All Old News',
    'Agenda' => 'Agenda',
    'Ann Arbor Argus' => 'Ann Arbor Argus',
    'Ann Arbor Argus-Democrat' => 'Ann Arbor Argus-Democrat',
    'Ann Arbor Courier' => 'Ann Arbor Courier',
    'Ann Arbor News' => 'Ann Arbor News',
    'Ann Arbor Sun' => 'Ann Arbor Sun',
    'Michigan Argus' => 'Michigan Argus',
    'Signal of Liberty' => 'Signal of Liberty',
  ]
];

$facets['website'] = [
  'Type' => [
    'name' => 'mat_code',
    'page' => 'Page',
    'story' => 'Staff News & Reviews',
    'pub_event' => 'Event',
    'media' => 'Media'
  ],
  'Past Events' => [
    'name' => 'field_event_end',
    'true' => 'Include Past Events'
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
    'name' => 'event_ages',
    'Babies' => 'Babies',
    'Preschool' => 'Preschool',
    'Elementary' => 'Elementary',
    'Teen' => 'Teen',
    'Adult' => 'Adult'
  ],
  'Event Types' => [
    'name' => 'event_categories',
    'art' => 'Art',
    'author events' => 'Author Events',
    'book discussions' => 'Book Discussions',
    'comics' => 'Comics',
    'computer classes' => 'Computer Classes',
    'concerts' => 'Concerts & Music',
    'contests' => 'Contests',
    'crafts' => 'Crafts',
    'exhibits' => 'Exhibits',
    'fiber arts' => 'Fiber Arts',
    'film' => 'Film/Video Events',
    'chefs and cooking' => 'Food & Cooking',
    'games' => 'Games',
    'health and wellness' => 'Health & Wellness',
    'homework help' => 'Homework Help',
    'lectures' => 'Lectures/Panel Discussions',
    'local creator' => 'Local Creator',
    'local history' => 'Local History',
    'nature' => 'Nature & The Environment',
    'performers' => 'Performers',
    'sci' => 'Sci/Tech',
    'special events' => 'Special Events',
    'tournaments' => 'Tournaments',
    'tutoring' => 'Tutoring',
    'workshops' => 'Workshops &amp; Classes',
    'youth play groups' => 'Youth Playgroups',
    'youth storytimes' => 'Youth Storytimes'
  ]
];
