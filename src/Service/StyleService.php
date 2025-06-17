<?php

namespace App\Service;

use Symfony\Component\Filesystem\Filesystem;

readonly class StyleService
{
    protected const HEIGHT = [
        'a' => [16000, 17000],
        'b' => [17500, 18000]
    ];
    protected const TRANSITION = ['duration' => 800];

    public function __construct(private string $stylePath,
                                private string $mapTilerToken,
                                private Filesystem $filesystem) {}

    public function getStyle(string $host, string ...$preload): array
    {
        $filter = [];
        foreach ($preload as $i => $item) {
            $filter[$i] = ['==', 'time', $item];
        }
        return array_merge_recursive(json_decode($this->filesystem->readFile($this->stylePath), true), [
            'glyphs' => "https://api.maptiler.com/fonts/{fontstack}/{range}.pbf?key=$this->mapTilerToken",
            'sources' => [
                'contours' => [
                    'url' => "https://api.maptiler.com/tiles/contours-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'maptiler_planet' => [
                    'url' => "https://api.maptiler.com/tiles/v3/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'outdoor' => [
                    'url' => "https://api.maptiler.com/tiles/outdoor/tiles.json?key=$this->mapTilerToken",
                    'type' => 'vector'
                ],
                'terrain-rgb' => [
                    'url' => "https://api.maptiler.com/tiles/terrain-rgb-v2/tiles.json?key=$this->mapTilerToken",
                    'type' => 'raster-dem'
                ],
                'clouds' => [
                    'type' => 'vector',
                    'tiles' => [
                        "$host/clouds?x={x}&y={y}&z={z}"
                    ],
                    'minZoom' => 0,
                    'maxZoom' => 22,
                    'attribution' => 'Contains modified <a href="https://www.eumetsat.int" target="_blank">EUMETSAT</a> data '.date('Y')
                ]
            ],
            'layers' => array_merge(...array_map(fn(int $transition) => array_merge(...array_map(fn(string $stage) => [
                [
                    'id' => "cloud_shadow_{$stage}_$transition",
                    'type' => 'fill',
                    'source' => 'clouds',
                    'source-layer' => "clouds_$stage",
                    'paint' => [
                        'fill-color' => '#000',
                        'fill-translate' => [1, 1],
                        'fill-opacity' => $transition ? 0 : 0.1,
                        'fill-opacity-transition' => static::TRANSITION
                    ],
                    'filter' => $filter[$transition]
                ],
                [
                    'id' => "cloud_sky_{$stage}_$transition",
                    'type' => 'fill-extrusion',
                    'source' => 'clouds',
                    'source-layer' => "clouds_$stage",
                    'paint' => [
                        'fill-extrusion-base' => min(static::HEIGHT[$stage]),
                        'fill-extrusion-base-transition' => static::TRANSITION,
                        'fill-extrusion-height' => max(static::HEIGHT[$stage]),
                        'fill-extrusion-height-transition' => static::TRANSITION,
                        'fill-extrusion-color' => '#fff',
                        'fill-extrusion-opacity' => $transition ? 0 : 0.3,
                        'fill-extrusion-opacity-transition' => static::TRANSITION
                    ],
                    'filter' => $filter[$transition]
                ]
                ], array_keys(static::HEIGHT))), array_keys($preload)))
        ]);
    }
}
