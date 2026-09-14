<?php
// SKYTÜRK — RSS kaynakları. Satır ekleyip çıkararak kaynakları yönetin.
// Biçim: [ 'RSS adresi', 'kategori-id', 'Kaynak adı' ]
// Kategori id'leri: son-dakika, gundem, politika, dunya, ekonomi, spor, kultur-sanat, saglik, yasam, teknoloji, egitim, genel, ankara, istanbul
return [
  ['https://www.ntv.com.tr/son-dakika.rss', 'son-dakika', 'NTV'],
  ['https://www.ntv.com.tr/gundem.rss',     'gundem',     'NTV'],
  ['https://www.ntv.com.tr/turkiye.rss',    'genel',      'NTV'],
  ['https://www.ntv.com.tr/dunya.rss',      'dunya',      'NTV'],
  ['https://www.ntv.com.tr/ekonomi.rss',    'ekonomi',    'NTV'],
  ['https://www.ntv.com.tr/spor.rss',       'spor',       'NTV'],
  ['https://www.ntv.com.tr/teknoloji.rss',  'teknoloji',  'NTV'],
  ['https://www.ntv.com.tr/yasam.rss',      'yasam',      'NTV'],
  ['https://www.ntv.com.tr/saglik.rss',     'saglik',     'NTV'],
  ['https://www.ntv.com.tr/sanat.rss',      'kultur-sanat','NTV'],
  ['https://www.ntv.com.tr/egitim.rss',     'egitim',     'NTV'],
  ['https://www.aa.com.tr/tr/rss/default?cat=guncel', 'gundem', 'AA'],
  // DHA Ajanda — DHA'nın haber siteleri için açtığı ücretsiz besleme (ajanda.dha.com.tr/rss-linkler)
  ['https://ajanda.dha.com.tr/feed/',            'genel',  'DHA Ajanda'],
  ['https://ajanda.dha.com.tr/feed/?post_type=post', 'genel', 'DHA Ajanda'],
  ['https://www.dha.com.tr/rss.asp',              'gundem', 'DHA'],
];
