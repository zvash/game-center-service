<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ServiceException;
use App\Game;
use App\GameConfig;
use App\Http\Controllers\Controller;
use App\Repositories\EuroExchangeRateRepository;
use App\Repositories\GameRepository;
use App\Services\BillingService;
use App\Traits\ResponseMaker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class GameController extends Controller
{
    use ResponseMaker;

    /**
     * @param Request $request
     * @param BillingService $billingService
     * @param GameRepository $gameRepository
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function create(
        Request $request,
        BillingService $billingService,
        GameRepository $gameRepository,
        EuroExchangeRateRepository $euroExchangeRateRepository
    )
    {
        $user = Auth::user();
        if ($user) {
            try {
                $gameFlow = $gameRepository->startNewGame($user->id, $user->currency, $billingService, $euroExchangeRateRepository);
                return $this->success($gameFlow);
            } catch (\Exception $e) {
                if ($e instanceof ServiceException) {
                    return $this->failData($e->getData(), 400);
                }
                return $this->failMessage($e->getMessage(), 400);
            }

        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @param GameRepository $gameRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function answer(Request $request, int $gameId, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                try {
                    $gameFlow = $gameRepository->proceedWithAnswer($game, $request->get('answer'), $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (\Exception $e) {
                    return $this->failMessage($e->getMessage(), 400);
                }
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @param GameRepository $gameRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function reveal(Request $request, int $gameId, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                try {
                    $gameFlow = $gameRepository->revealOne($game, $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (\Exception $e) {
                    return $this->failMessage($e->getMessage(), 400);
                }
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @param GameRepository $gameRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function collect(Request $request, int $gameId, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                try {
                    $gameFlow = $gameRepository->collectPrize($game, $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (\Exception $e) {
                    return $this->failMessage($e->getMessage(), 400);
                }
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @param GameRepository $gameRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function pass(Request $request, int $gameId, GameRepository $gameRepository)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                try {
                    $gameFlow = $gameRepository->passLevelToNext($game);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (\Exception $e) {
                    return $this->failMessage($e->getMessage(), 400);
                }
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function get(Request $request, int $gameId)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                $config = GameConfig::all()->pluck('value', 'key')->toArray();
                $gameFlow = $game->getGameFlow($config);
                return $this->success($gameFlow);
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }
}
