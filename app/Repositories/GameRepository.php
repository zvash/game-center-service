<?php

namespace App\Repositories;


use App\Exceptions\ActiveLevelNotFoundException;
use App\Exceptions\GameIsExpiredException;
use App\Exceptions\InsufficientPossessionException;
use App\Exceptions\ServiceException;
use App\Game;
use App\GameConfig;
use App\Level;
use App\Services\AuthService;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

class GameRepository
{
    /**
     * @param int $userId
     * @param string $currency
     * @param BillingService $billingService
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return array
     * @throws \Exception
     */
    public function startNewGame(int $userId, string $currency, BillingService $billingService, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();
            $exchangeRate = 1;
            if ($config['prize_currency'] != $currency) {
                $exchangeRate = $euroExchangeRateRepository->exchangeRate($config['prize_currency'], $currency);
            }
            $game = Game::create([
                'user_id' => $userId,
                'currency' => $currency,
                'total_levels' => $config['total_levels'],
            ]);
            $gameFlow = $game->createLevels($config, $exchangeRate)
                ->start()
                ->payGameCoins($config, $billingService)
                ->getGameFlow($config);
            DB::commit();
            return $gameFlow;
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param string $currency
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return mixed
     */
    public function getGamePrizes(string $currency, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $config = GameConfig::all()->pluck('value', 'key')->toArray();
        try {
            if ($config['prize_currency'] != $currency) {
                $exchangeRate = $euroExchangeRateRepository->exchangeRate($config['prize_currency'], $currency);
            } else {
                $exchangeRate = 1;
            }
        } catch (\Exception $exception) {
            $exchangeRate = 1;
            $currency = 'EUR';
        }

        $result['currency'] = $currency;
        $result['prizes'] = [];
        for ($levelIndex = 1; $levelIndex <= $config['total_levels'] * 1; $levelIndex++) {
            $result['prizes'][] = [
                'level_index' => $levelIndex,
                'win_prize' => intval(round($config['win_prize.level_' . $levelIndex] * $exchangeRate)),
            ];
        }
        return $result;
    }

    /**
     * @param string $currency
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return array
     */
    public function allWinnersPayouts(string $currency, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $wonGames = Game::where('state', 'collected')->whereRaw('current_level_index = total_levels')->get();
        $totalWinners = $wonGames->count();
        $totalPayouts = 0;
        foreach ($wonGames as $game) {
            try {
                if ($game->currency != $currency) {
                    $exchangeRate = $euroExchangeRateRepository->exchangeRate($game->currency, $currency);
                } else {
                    $exchangeRate = 1;
                }
            } catch (\Exception $exception) {
                $exchangeRate = 1;
            }
            $totalPayouts += intval(round($game->paid_prize * $exchangeRate));
        }
        return [
            'currency' => $currency,
            'total_payouts' => $totalPayouts,
            'total_winners' => $totalWinners
        ];
    }

    /**
     * @param string $targetCurrency
     * @param BillingService $billingService
     * @param EuroExchangeRateRepository $euroExchangeRateRepository
     * @return array
     * @throws ServiceException
     */
    public function getDepositStatistics(string $targetCurrency, BillingService $billingService, EuroExchangeRateRepository $euroExchangeRateRepository)
    {
        $response = $billingService->getDepositStatistics('games', 'money');
        if ($response['status'] == 200) {
            $deposits = $response['data'];
            $totalPaid = 0;
            foreach ($deposits['sums'] as $currency => $amount) {
                if ($currency == $targetCurrency) {
                    $totalPaid += $amount;
                } else {
                    try {
                        $exchangeRate = $euroExchangeRateRepository->exchangeRate($currency, $targetCurrency);
                    } catch (\Exception $e) {
                        $exchangeRate = 0;
                    }
                    $totalPaid += $amount * $exchangeRate;
                }
            }
            return [
                'total_paid' => ceil($totalPaid),
                'currency' => $targetCurrency,
                'average' => ceil($totalPaid / $deposits['users_count']),
                'last_update' => $deposits['last_update']
            ];
        }
        throw new ServiceException('Could not get deposit statistics.', [
            'message' => 'Could not get deposit statistics.',
            'data' => $response['data'],
            'code' => $response['status']
        ]);
    }

    /**
     * @param int $gameId
     * @return array
     * @throws \Exception
     */
    public function getGame(int $gameId)
    {
        $game = Game::where('id', $gameId)->with(['levels' => function ($query) {
            return $query->where('state', 'active');
        }])->first();
        $result = [];
        if ($game) {
            $result['game_id'] = $game->id;
            $result['state'] = $game->state;
            $result['total_levels'] = $game->total_levels;
            $result['currency'] = $game->currency;
            $result['won_money'] = 0;
            if ($game->levels->first()) {
                $level = $game->levels->first();
                $config = GameConfig::all()->pluck('value', 'key')->toArray();
                $result['level_id'] = $level->id;
                $result['level_index'] = $level->level_index;
                $result['reveal_price'] = $config['reveal_price'];
                $result['allow_reveal'] = count(explode(',', $level->revealable_boxes)) >= $config['reveal_min_boxes'];
                $result['win_prize'] = $level->win_prize;
                $result['leave_prize'] = $level->leave_prize;
            }
            if ($game->state == 'won' || $game->state == 'lost') {
                $endLevel = $game->levels()->where('state', 'played')->orderBy('level_index', 'desc')->first();
                if ($endLevel) {
                    $result['won_money'] = $game->state == 'won' ? $endLevel->win_prize : $endLevel->leave_prize;
                }
            }
            return $result;
        }

        throw new \Exception('Game does not have an active level.');
    }

    /**
     * @param Game $game
     * @param int $answer
     * @param BillingService $billingService
     * @param AuthService $authService
     * @return mixed
     * @throws \Exception
     */
    public function proceedWithAnswer(Game $game, int $answer, BillingService $billingService, AuthService $authService)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();
            $game = $game->answer($answer, $billingService, $authService);
            DB::commit();
            return $game->getGameFlow($config);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param Game $game
     * @param BillingService $billingService
     * @return array
     * @throws \Exception
     */
    public function revealOne(Game $game, BillingService $billingService)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();

            $revealedBox = $game->revealOne($config, $billingService);
            DB::commit();
            return $game->getGameFlow($config, $revealedBox);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param Game $game
     * @param BillingService $billingService
     * @return array
     * @throws \Exception
     */
    public function collectPrize(Game $game, BillingService $billingService)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();

            $game->collect($billingService);
            DB::commit();
            return $game->getGameFlow($config);
        } catch (\Exception $exception) {
            DB::rollBack();
            throw $exception;
        }
    }

    /**
     * @param Game $game
     * @return array
     * @throws \App\Exceptions\ActiveLevelNotFoundException
     */
    public function passLevelToNext(Game $game)
    {
        $game->pass();
        $config = GameConfig::all()->pluck('value', 'key')->toArray();
        return $game->getGameFlow($config);
    }

    /**
     * @param array $games
     * @param BillingService $billingService
     * @return mixed
     * @throws ServiceException
     */
    public function getGamesBalances(array $games, BillingService $billingService)
    {
        $sources['games'] = [];
        foreach ($games as $game) {
            $sources['games'][] = $game['id'];
        }
        if ($sources['games']) {
            $response = $billingService->getSourcesBalances($sources);
            if ($response['status'] == 200) {
                return $response['data'];
            }
        } else {
            return [];
        }
        throw new ServiceException('Could not get balances.', [
            'message' => 'Could not get balances.',
            'data' => $response['data'],
            'code' => $response['status']
        ]);
    }

    /**
     * @param Game $game
     * @return array
     */
    public function gamePrizes(Game $game)
    {
        $gamePrizes = [
            'game_id' => $game->id,
            'currency' => $game->currency,
            'prizes' => []
        ];
        $levels = $game->levels->toArray();
        foreach ($levels as $level) {
            $gamePrizes['prizes'][] = [
                'level_index' => $level['level_index'],
                'win_prize' => $level['win_prize'],
            ];
        }
        return $gamePrizes;
    }

    /**
     * @param Game $game
     * @param BillingService $billingService
     * @return Game
     * @throws GameIsExpiredException
     * @throws ServiceException
     * @throws \App\Exceptions\ActiveLevelNotFoundException
     */
    public function expireGameIfNeeded(Game $game, BillingService $billingService)
    {
        if ($game->is_expired) {
            throw new GameIsExpiredException("Game Is Expired");
        }
        $config = GameConfig::all()->pluck('value', 'key')->toArray();
        $expiredStatus = $game->expiredStatus($config);
        if ($expiredStatus['is_expired']) {
            $game->timestamps = false;
            $game->is_expired = true;
            $game->save();
            try {
                $game->expireGame($billingService);
            } catch (ActiveLevelNotFoundException $e) {
                //No Active Level. Pass.
            }
            $game->timestamps = true;
            throw new GameIsExpiredException("Game Is Expired");
        }
        return $game;
    }
}