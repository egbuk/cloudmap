<?php

namespace App\Controller;

use App\Repository\TileRepository;
use App\Service\StyleService;
use DateTime;
use Exception;
use HeyMoon\VectorTileDataProvider\Entity\TilePosition;
use Psr\Cache\InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

class MapController extends AbstractController
{
    public function __construct(
        private readonly StyleService $styleService,
        private readonly TileRepository $tileRepository
    ) {}

    /**
     * @throws Exception
     */
    #[Route('/', methods: ['GET'])]
    public function map(): Response
    {
        $time = $this->tileRepository->getCurrentTime();
        return $this->render('map.html.twig', [
            'time' => (new DateTime($time))->getTimestamp(),
            'display' => $time
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/clouds', methods: ['GET'])]
    public function clouds(#[MapQueryParameter] int $x,
                           #[MapQueryParameter] int $y,
                           #[MapQueryParameter] int $z): Response
    {
        return new Response($this->tileRepository->get(TilePosition::xyzFlip($x, $y, $z)), 200, [
            'Content-Type' => 'application/x-protobuf',
            'Content-Encoding' => 'gzip',
            'Access-Control-Allow-Origin' => '*'
        ]);
    }

    #[Route('/style', methods: ['GET'])]
    public function style(Request $request): JsonResponse
    {
        return $this->json($this->styleService->getStyle(
            $request->getSchemeAndHttpHost(), $this->tileRepository->getCurrentTime()
        ));
    }
}
