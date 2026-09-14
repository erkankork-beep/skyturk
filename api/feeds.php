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
  ['https://www.aa.com.tr/tr/rss/default?cat=guncel', 'gundem', 'AA'],
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
  // Sözcü
  ['https://www.sozcu.com.tr/feed/',           'gundem',  'Sözcü'],
  ['https://www.sozcu.com.tr/spor/feed/',      'spor',    'Sözcü'],
  // Cumhuriyet
  ['https://www.cumhuriyet.com.tr/rss/son_dakika.xml', 'son-dakika', 'Cumhuriyet'],
];
