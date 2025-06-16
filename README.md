# About
Live vector cloud map based on live data provided by <https://eumetsat.int>

### <https://clouds.heymoon.cc>
![Preview](screenshot.png)

# Environment variables
* **MAP_TILER_TOKEN** - token from [MapTiler Cloud](https://cloud.maptiler.com/account/keys/) (for base layer)
* **SOURCE_WIDTH** - `4096`(default)/`2048`/`1024`
* **SOURCE_HEIGHT** - `2048`(default)/`1024`/`512`

# Stack
* [heymoon/vector-tile-data-provider](https://packagist.org/packages/heymoon/vector-tile-data-provider)
* [Symfony 7](https://symfony.com/7)
* [libgeos](https://libgeos.org)
* [imagemagick](https://imagemagick.org)
* [MapLibre GL](https://maplibre.org)
* [MapTiler](https://www.maptiler.com)
* [Redis](https://redis.io)
