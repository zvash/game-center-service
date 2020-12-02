<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GameIsExpiredException;
use App\Exceptions\ServiceException;
use App\Game;
use App\GameConfig;
use App\Http\Controllers\Controller;
use App\Level;
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
                    $gameRepository->expireGameIfNeeded($game, $billingService);
                    $gameFlow = $gameRepository->proceedWithAnswer($game, $request->get('answer'), $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (GameIsExpiredException $exception) {
                    $config = GameConfig::all()->pluck('value', 'key')->toArray();
                    $game->refresh();
                    $gameFlow = $game->getGameFlow($config);
                    return $this->success($gameFlow);
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
                    $gameRepository->expireGameIfNeeded($game, $billingService);
                    $gameFlow = $gameRepository->revealOne($game, $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (GameIsExpiredException $exception) {
                    $config = GameConfig::all()->pluck('value', 'key')->toArray();
                    $game->refresh();
                    $gameFlow = $game->getGameFlow($config);
                    return $this->success($gameFlow);
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
                    $gameRepository->expireGameIfNeeded($game, $billingService);
                    $gameFlow = $gameRepository->collectPrize($game, $billingService);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (GameIsExpiredException $exception) {
                    $config = GameConfig::all()->pluck('value', 'key')->toArray();
                    $game->refresh();
                    $gameFlow = $game->getGameFlow($config);
                    return $this->success($gameFlow);
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
    public function pass(Request $request, int $gameId, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                try {
                    $gameRepository->expireGameIfNeeded($game, $billingService);
                    $gameFlow = $gameRepository->passLevelToNext($game);
                    return $this->success($gameFlow);
                } catch (ServiceException $exception) {
                    return $this->failData($exception->getData(), 400);
                } catch (GameIsExpiredException $exception) {
                    $config = GameConfig::all()->pluck('value', 'key')->toArray();
                    $game->refresh();
                    $gameFlow = $game->getGameFlow($config);
                    return $this->success($gameFlow);
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
     * @throws ServiceException
     * @throws \App\Exceptions\ActiveLevelNotFoundException
     */
    public function get(Request $request, int $gameId, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                $config = GameConfig::all()->pluck('value', 'key')->toArray();
                try {
                    $gameRepository->expireGameIfNeeded($game, $billingService);
                } catch (GameIsExpiredException $exception) {
                    $game->refresh();
                }
                $gameFlow = $game->getGameFlow($config);
                return $this->success($gameFlow);
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param GameRepository $gameRepository
     * @param BillingService $billingService
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function summary(Request $request, GameRepository $gameRepository, BillingService $billingService)
    {
        $user = Auth::user();
        if ($user) {
            $games = Game::where('user_id', $user->id)
                ->where('is_active', false)
                ->orderBy('id', 'DESC')
                ->paginate(10)
                ->toArray();
            if ($games) {
                $config = GameConfig::all()->pluck('value', 'key')->toArray();
                $gameFlows = [];
                foreach ($games['data'] as $gameData) {
                    $game = Game::find($gameData['id']);
                    $gameFlows[] = $game->getGameFlow($config);
                }
                $games['data'] = $gameFlows;
                try {
                    $balances = $gameRepository->getGamesBalances($games['data'], $billingService);
                    foreach ($games['data'] as $index => $game) {
                        if (isset($balances['games'][$game['id']])) {
                            foreach ($balances['games'][$game['id']] as $currency => $amount) {
                                $games['data'][$index]['balances'][] = [
                                    'currency' => $currency,
                                    'amount' => $amount * 1
                                ];
                            }
                        }
                    }
                    return $this->success($games);
                } catch (ServiceException $e) {
                    return $this->failData($e->getData(), 400);
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
    public function prizes(Request $request, int $gameId, GameRepository $gameRepository)
    {
        $user = Auth::user();
        if ($user) {
            $game = Game::where('user_id', $user->id)
                ->where('id', $gameId)
                ->first();
            if ($game) {
                $prizes = $gameRepository->gamePrizes($game);
                return $this->success($prizes);
            }
            return $this->failMessage('Content not found.', 404);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param GameRepository $gameRepository
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function prizesFromConfig(Request $request, GameRepository $gameRepository, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $user = Auth::user();
        if ($user) {
            $currency = $user->currency;
            $prizes = $gameRepository->getGamePrizes($currency, $euroExchangeRateRepository);
            return $this->success($prizes);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param GameRepository $gameRepository
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function winners(Request $request, GameRepository $gameRepository, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $user = Auth::user();
        if ($user) {
            $currency = $user->currency;
            $payouts = $gameRepository->allWinnersPayouts($currency, $euroExchangeRateRepository);
            return $this->success($payouts);
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param GameRepository $gameRepository
     * @param BillingService $billingService
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function statistics(Request $request, GameRepository $gameRepository, BillingService $billingService, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $user = Auth::user();
        if ($user) {
            try {
                $deposits = $gameRepository->getDepositStatistics($user->currency, $billingService, $euroExchangeRateRepository);
                return $this->success($deposits);
            } catch (ServiceException $e) {
                return $this->failData($e->getData(), 400);
            }
        }
        return $this->failMessage('Content not found.', 404);
    }

    /**
     * @param Request $request
     * @param int $gameId
     * @return \Illuminate\Http\Response|\Laravel\Lumen\Http\ResponseFactory
     */
    public function cheat(Request $request, $gameId)
    {
        if ($gameId == 'last') {
            $user = Auth::user();
            $game = Game::where('user_id', $user->id)->orderBy('id', 'DESC')->first();
            if (!$game) {
                $game = Game::orderBy('id', 'DESC')->first();
            }
            if ($game) {
                $gameId = $game->id;
            } else {
                $gameId = 0;
            }
        }
        $answers = Level::where('game_id', $gameId)->pluck('winner_box', 'level_index')->toArray();
        return $this->success($answers);
    }
}
