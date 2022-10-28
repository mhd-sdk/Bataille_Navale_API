<?php

namespace App\Controller;

use App\Entity\Game;
use App\Entity\Player;
use DateTimeImmutable;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use JMS\Serializer\SerializerInterface;
use JMS\Serializer\Serializer;
use JMS\Serializer\SerializationContext;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;

class GameController extends AbstractController
{

    public function __construct(ManagerRegistry $doctrine)
    {
        $this->em = $doctrine->getManager();
        $this->gameRepository = $doctrine->getRepository(Game::class);
    }

    #[Route('api/game/{idGame}', name: 'game.get', methods: ['GET'])]
    #[ParamConverter("game", options: ["id" => "idGame"], class: 'App\Entity\Game')]
    public function getGame(Game $game, SerializerInterface $serializer): JsonResponse
    {
        if ($game->isStatus()) {
            $context = SerializationContext::create()->setGroups(['getGame']);
            $jsonGame = $serializer->serialize($game, 'json', $context);
            return new JsonResponse($jsonGame, Response::HTTP_OK, ['accept' => 'json'], true);
        }
        return new JsonResponse(['message' => 'Game not found'], Response::HTTP_NOT_FOUND, ['accept' => 'json'], true);
    }

    #[Route('api/game/filter/{date}', name: 'game.filter', methods: ['GET'])]
    public function filterGame($date, SerializerInterface $serializer): JsonResponse
    {


        $games = $this->gameRepository->filterByDate($date);
        $games = array_filter($games, function ($game) {
            return $game->isStatus();
        });
        $context = SerializationContext::create()->setGroups(['getGame']);
        $jsonGames = $serializer->serialize($games, 'json', $context);
        return new JsonResponse($jsonGames, Response::HTTP_OK, ['accept' => 'json'], true);
    }

    /**
     * Register new game
     */
    #[Route('/api/game/create', name: 'game.create', methods: ['POST'])]
    public function createGame(SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ManagerRegistry $doctrine): JsonResponse
    {
        $playerId = $this->getUser()->getId();

        $playerRepository = $doctrine->getRepository(Player::class);
        $currentPlayer = $playerRepository->find($playerId);
        $game = new Game();
        $game->setStatus(true);
        $game->addPlayer($currentPlayer);
        $codeGame = rand(1000, 10000);
        $game->setGameCode($codeGame);
        $game->setCreatedAt(new DateTimeImmutable());
        $this->em->persist($game);
        $this->em->flush();
        $context = SerializationContext::create()->setGroups(['getGame']);
        $jsonGame = $serializer->serialize($game, 'json', $context);
        $location = $urlGenerator->generate('game.get', ['idGame' => $game->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonGame, Response::HTTP_CREATED, ["Location" => $location], true);
    }
    // route to join a game
    #[Route('/api/game/join/{codeGame}', name: 'game.join', methods: ['POST'])]
    // paramconverter to get the game with the code
    #[ParamConverter("game", options: ["mapping" => ["codeGame" => "gameCode"]], class: 'App\Entity\Game')]
    public function joinGame(Game $game, SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ManagerRegistry $doctrine): JsonResponse
    {
        $playerId = $this->getUser()->getId();
        $playerRepository = $doctrine->getRepository(Player::class);
        $currentPlayer = $playerRepository->find($playerId);
        $game->addPlayer($currentPlayer);
        $this->em->persist($game);
        $this->em->flush();
        $context = SerializationContext::create()->setGroups(['getGame']);
        $jsonGame = $serializer->serialize($game, 'json', $context);
        $location = $urlGenerator->generate('game.get', ['idGame' => $game->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonGame, Response::HTTP_CREATED, ["Location" => $location], true);
    }
    // route to leave a game
    #[Route('/api/game/leave', name: 'game.leave', methods: ['POST'])]
    // paramconverter to get the game with the code
    public function leaveGame(SerializerInterface $serializer, UrlGeneratorInterface $urlGenerator, ManagerRegistry $doctrine): JsonResponse
    {
        $playerId = $this->getUser()->getId();
        $playerRepository = $doctrine->getRepository(Player::class);
        $currentPlayer = $playerRepository->find($playerId);
        $game = $currentPlayer->getGame();
        $game->removePlayer($currentPlayer);
        if ($game->getPlayers()->isEmpty()) {
            $game->setStatus(false);
        }
        $this->em->flush();
        $context = SerializationContext::create()->setGroups(['getGame']);
        $jsonGame = $serializer->serialize($game, 'json', $context);
        $location = $urlGenerator->generate('game.get', ['idGame' => $game->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        return new JsonResponse($jsonGame, Response::HTTP_CREATED, ["Location" => $location], true);
    }
}
