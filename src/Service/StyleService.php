<?php

namespace App\Service;

use App\Repository\TileRepository;
use Symfony\Component\Filesystem\Filesystem;

readonly class StyleService
{
    protected const HEIGHT = [
        'a' => [16000, 17000],
        'b' => [17500, 18000]
    ];
    protected const TRANSITION = ['duration' => 800];

    public function __construct(private string $stylePath,
                                private TileRepository $tileRepository,
                                private Filesystem $filesystem) {}

    public function getStyle(string $host): array
    {
        return array_merge_recursive(json_decode($this->filesystem->readFile($this->stylePath), true), [
            'sources' => [
                'clouds' => [
                    'type' => 'vector',
                    'tiles' => [
                        "$host/clouds?x={x}&y={y}&z={z}"
                    ],
                    'minZoom' => 0,
                    'maxZoom' => 18,
                    'attribution' => 'Contains modified <a href="https://www.eumetsat.int" target="_blank">EUMETSAT</a> data '.date('Y')
                ]
            ],
            'layers' => array_merge(...array_map(fn(int $transition) => array_merge(...array_map(fn(string $stage) => [
                [
                    'id' => "cloud_shadow_{$stage}_{$this->tileRepository->getCurrentTime($transition * 60)}",
                    'type' => 'fill',
                    'source' => 'clouds',
                    'source-layer' => "clouds_$stage",
                    'paint' => [
                        'fill-color' => '#000',
                        'fill-translate' => [1, 1],
                        'fill-opacity' => $transition ? 0 : ['stops' => [[0, 0.3], [7, 0.1]]],
                        'fill-opacity-transition' => static::TRANSITION
                    ],
                    'filter' => $this->getFilter($transition)
                ],
                [
                    'id' => "cloud_sky_{$stage}_{$this->tileRepository->getCurrentTime($transition * 60)}",
                    'type' => 'fill-extrusion',
                    'source' => 'clouds',
                    'source-layer' => "clouds_$stage",
                    'paint' => [
                        'fill-extrusion-base' => min(static::HEIGHT[$stage]),
                        'fill-extrusion-base-transition' => static::TRANSITION,
                        'fill-extrusion-height' => max(static::HEIGHT[$stage]),
                        'fill-extrusion-height-transition' => static::TRANSITION,
                        'fill-extrusion-color' => '#fff',
                        'fill-extrusion-opacity' => $transition ? 0 : ['stops' => [
                            [0, 0.7],
                            [7, 0.3],
                            [10, 0.3],
                            [11, 0.1],
                            [12, 0]
                        ]],
                        'fill-extrusion-opacity-transition' => static::TRANSITION
                    ],
                    'filter' => $this->getFilter($transition)
                ]
                ], array_keys(static::HEIGHT))), range(0, -23)))
        ]);
    }

    private function getFilter(int $advance)
    {
        return ['==', 'time', $this->tileRepository->getCurrentTime($advance * 60)];
    }
}
