<?php

namespace App\Repositories;


use App\Exceptions\InsufficientPossessionException;
use App\Exceptions\ServiceException;
use App\Game;
use App\GameConfig;
use App\Level;
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
     * @return mixed
     * @throws \Exception
     */
    public function proceedWithAnswer(Game $game, int $answer, BillingService $billingService)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();
            $game = $game->answer($answer, $billingService);
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
     * @return integer
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
}