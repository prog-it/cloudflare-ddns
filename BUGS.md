# БАГИ

- Не создаются DNS-записи (если отсутствуют) на прошивках LEDE с библиотекой curl 7.52.1-2: https://github.com/curl/curl/issues/1268
Временное решение: создать DNS-записи на Cloudflare вручную, обновление записей работает. Ждать обновления библиотеки curl.