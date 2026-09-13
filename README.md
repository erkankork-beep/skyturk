# SKYTÜRK — haber sitesi + CMS

- `index.html` — site · `cms/index.html` — yönetim paneli · `api/` — PHP köprüsü ve RSS otomasyonu
- `.cpanel.yml` — cPanel Git Version Control ile `public_html`'e dağıtım (data.json ve config.php'ye dokunmaz)

Güncelleme akışı: depoya commit → cPanel › Git Version Control › Manage › Pull or Deploy › **Update from Remote** → **Deploy HEAD Commit**.
