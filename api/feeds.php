<?php
// SKYTÜRK — RSS kaynakları. Biçim: [ 'RSS adresi', 'kategori-id', 'Kaynak adı' ]
// Kategoriler: son-dakika, gundem, politika, dunya, ekonomi, spor, kultur-sanat, saglik, yasam, teknoloji, egitim, genel, ankara, istanbul
return [
  // NTV (çalışan akışlar)
  ['https://www.ntv.com.tr/son-dakika.rss', 'son-dakika', 'NTV'],
  ['https://www.ntv.com.tr/gundem.rss',     'gundem',     'NTV'],
  ['https://www.ntv.com.tr/turkiye.rss',    'genel',      'NTV'],
  ['https://www.ntv.com.tr/dunya.rss',      'dunya',      'NTV'],
  ['https://www.ntv.com.tr/ekonomi.rss',    'ekonomi',    'NTV'],
  ['https://www.ntv.com.tr/teknoloji.rss',  'teknoloji',  'NTV'],
  ['https://www.ntv.com.tr/yasam.rss',      'yasam',      'NTV'],
  ['https://www.ntv.com.tr/saglik.rss',     'saglik',     'NTV'],
  ['https://www.ntv.com.tr/egitim.rss',     'egitim',     'NTV'],
  // Anadolu Ajansı (açık akış)
  ['https://www.aa.com.tr/tr/rss/default?cat=guncel',          'gundem',       'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=politika',        'politika',     'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=dunya',           'dunya',        'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=ekonomi',         'ekonomi',      'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=spor',            'spor',         'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=bilim-teknoloji', 'teknoloji',    'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=saglik',          'saglik',       'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=egitim',          'egitim',       'AA'],
  ['https://www.aa.com.tr/tr/rss/default?cat=yasam',           'yasam',        'AA'],
  // AA Teyit Hattı (doğrulama haberleri)
  ['https://www.aa.com.tr/tr/teyithatti/rss/news?cat=0',       'gundem',       'AA Teyit Hattı'],
  // TRT Haber
  ['https://www.trthaber.com/sondakika_articles.rss', 'son-dakika', 'TRT Haber'],
  ['https://www.trthaber.com/manset_articles.rss',    'gundem',     'TRT Haber'],
  ['https://www.trthaber.com/spor_articles.rss',      'spor',       'TRT Haber'],
  ['https://www.trthaber.com/ekonomi_articles.rss',   'ekonomi',    'TRT Haber'],
  ['https://www.trthaber.com/kultur_sanat_articles.rss','kultur-sanat','TRT Haber'],
  // Hürriyet
  ['https://www.hurriyet.com.tr/rss/anasayfa', 'gundem',  'Hürriyet'],
  ['https://www.hurriyet.com.tr/rss/spor',     'spor',    'Hürriyet'],
  ['https://www.hurriyet.com.tr/rss/ekonomi',  'ekonomi', 'Hürriyet'],
  ['https://www.hurriyet.com.tr/rss/dunya',    'dunya',   'Hürriyet'],
  // Cumhuriyet
  ['https://www.cumhuriyet.com.tr/rss/son_dakika.xml', 'son-dakika', 'Cumhuriyet'],
  // Mynet
  ['https://www.mynet.com/magazin/rss', 'magazin', 'Mynet'],
];
