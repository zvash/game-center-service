<?php

namespace App;

use App\Events\UserHasWonTheGame;
use App\Exceptions\ActiveLevelNotFoundException;
use App\Exceptions\InsufficientPossessionException;
use App\Exceptions\ServiceException;
use App\Services\AuthService;
use App\Services\BillingService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string transaction_ids
 * @property string state
 * @property int id
 * @property int user_id
 * @property int total_levels
 * @property int current_level_index
 * @property string currency
 * @property float paid_prize
 * @property bool is_active
 * @property \Illuminate\Support\Carbon updated_at
 * @property bool is_expired
 */
class Game extends Model
{

    protected $fillable = ['user_id', 'currency', 'total_levels', 'state', 'is_active'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function levels()
    {
        return $this->hasMany(Level::class);
    }

    /**
     * @param array $config
     * @param float $exchangeRate
     * @return Game
     */
    public function createLevels(array $config, float $exchangeRate)
    {

        for ($levelIndex = 1; $levelIndex <= $config['total_levels'] * 1; $levelIndex++) {
            $possibleChoices = range(1, $config['box_count.level_' . $levelIndex]);
            $winnerIndex = 0;//mt_rand(0, $config['box_count.level_' . $levelIndex] - 1);
            $winnerBox = $possibleChoices[$winnerIndex];
            unset($possibleChoices[$winnerIndex]);
            Level::create([
                'game_id' => $this->id,
                'level_index' => $levelIndex,
                'boxes_count' => $config['box_count.level_' . $levelIndex],
                'winner_box' => $winnerBox,
                'revealable_boxes' => implode(',', $possibleChoices),
                'reveal_price' => $config['reveal_price'],
                'win_prize' => $config['win_prize.level_' . $levelIndex] * $exchangeRate,
                'leave_prize' => $config['leave_prize.level_' . $levelIndex] * $exchangeRate,
                'last_move_time' => date('Y-m-d H:i:s'),
                'state' => 'inactive'
            ]);
        }
        return $this;
    }

    public function start()
    {
        $this->state = 'started';
        $firstLevel = $this
            ->levels()
            ->orderBy('level_index')
            ->first();
        $firstLevel->activate();
        $this->current_level_index = $firstLevel->level_index;
        $this->save();
        return $this;
    }

    /**
     * @param array $config
     * @param BillingService $billingService
     * @return Game
     * @throws InsufficientPossessionException
     * @throws ServiceException
     */
    public function payGameCoins(array $config, BillingService $billingService)
    {
        $amount = $config['game_price'];
        $transactions = [$billingService->withdrawCoin($this->user_id, $amount, 'games', $this->id)];
        $result = $billingService->createTransactions($transactions);
        if ($result['status'] == 200 && $result['data']) {
            $transactionId = $result['data'][0]['id'];
            $this->addTransaction($transactionId);
            return $this;
        } else if (array_key_exists('json', $result['data'])) {
            $error = json_decode($result['data']['json'], 1);
            $error = $error['errors']['data'];
            throw new InsufficientPossessionException(
                'Not enough coins to start a new game',
                [
                    'message' => 'Not enough coins to start a new game',
                    'current_coins' => $error['current_value'],
                    'needed_coins' => $amount
                ]
            );
        } else {
            throw new ServiceException('Failed to create transaction on billing service', []);
        }
    }

    /**
     * @param int $transactionId
     * @return $this
     */
    public function addTransaction(int $transactionId)
    {
        $currentIds = $this->transaction_ids ? explode(',', $this->transaction_ids) : [];
        $currentIds[] = $transactionId;
        $this->transaction_ids = implode(',', $currentIds);
        $this->save();
        return $this;
    }

    /**
     * @param array $config
     * @param int|null $revealed
     * @return array
     */
    public function getGameFlow(array $config, int $revealed = null)
    {
        $game = [];
        $game['id'] = $this->id;
        $game['state'] = $this->state;
        $game['user_id'] = $this->user_id;
        $game['total_levels'] = $this->total_levels * 1;
        $game['current_level'] = $this->current_level_index;
        $game['currency'] = $this->currency;
        $game['revealed'] = $revealed;
        $game['paid_prize'] = $this->paid_prize;
        $game['has_ended'] = $this->state == 'collected';
        $game['end_reason'] = '';
        if ($game['has_ended']) {
            $lastLevel = $this
                ->levels()
                ->whereIn('state', ['won', 'lost', 'collected'])
                ->orderBy('level_index', 'desc')
                ->first();
            if (!$lastLevel) {
                $game['end_reason'] = '';
            } else {
                $game['end_reason'] = in_array($lastLevel->state, ['won', 'collected']) ? 'won' : 'lost';
            }
        }
        $game['level'] = null;
        $activeLevel = $this->levels()->whereIn('state', ['can-collect', 'active'])->first();
        if ($activeLevel) {
            $game['level'] = $activeLevel->getLevel($config);
        }
        $game = $game + $this->expiredStatus($config);
        return $game;
    }

    /**
     * @param array $config
     * @param BillingService $billingService
     * @return int
     * @throws ActiveLevelNotFoundException
     * @throws ServiceException
     */
    public function revealOne(array $config, BillingService $billingService)
    {
        $activeLevel = $this->levels()->where('state', 'active')->first();
        if ($activeLevel) {
            return $activeLevel->revealOne($config, $billingService);
        } else {
            throw new ActiveLevelNotFoundException('Game has no active level', [
                'message' => 'Game has no active level',
                'game_id' => $this->id
            ]);
        }
    }

    /**
     * @param int $answer
     * @param BillingService $billingService
     * @param AuthService $authService
     * @return Game
     * @throws ActiveLevelNotFoundException
     * @throws ServiceException
     */
    public function answer(int $answer, BillingService $billingService, AuthService $authService)
    {
        $userWon = false;
        $activeLevel = $this->levels()->where('state', 'active')->first();
        if ($activeLevel) {
            $activeLevel = $activeLevel->updateStateByAnswer($answer);
            $prizeToPay = 0;
            if ($activeLevel->state == 'lost') {
                $prizeToPay = $activeLevel->leave_prize;
            } else if ($activeLevel->state == 'won') {
                $prizeToPay = $activeLevel->win_prize;
                $userWon = true;
            }
            $this->payPrize($prizeToPay, $billingService);
            if ($userWon) {
                event(new UserHasWonTheGame($this->user_id, $authService, $billingService));
            }
            return $this;
        } else {
            throw new ActiveLevelNotFoundException('Game has no active level', [
                'message' => 'Game has no active level',
                'game_id' => $this->id
            ]);
        }
    }

    /**
     * @param BillingService $billingService
     * @return $this
     * @throws ActiveLevelNotFoundException
     * @throws ServiceException
     */
    public function expireGame(BillingService $billingService)
    {
        $currentLevel = $this->levels()->whereIn('state', ['active', 'can-collect'])->first();
        if ($currentLevel) {
            $currentLevel->updateStateByExpiration();
            $prizeToPay = $currentLevel->winner_box == $currentLevel->chosen_box
                ? $currentLevel->win_prize
                : $currentLevel->leave_prize;
            $this->payPrize($prizeToPay, $billingService);
            return $this;
        } else {
            throw new ActiveLevelNotFoundException('Game has no active level', [
                'message' => 'Game has no active level',
                'game_id' => $this->id
            ]);
        }
    }

    /**
     * @return Game
     * @throws ActiveLevelNotFoundException
     */
    public function pass()
    {
        $activeLevel = $this->levels()->where('state', 'can-collect')->first();
        if ($activeLevel) {
            $activeLevel->passLevel();
            return $this->refresh();
        } else {
            throw new ActiveLevelNotFoundException('Game has no passable level', [
                'message' => 'Game has no passable level',
                'game_id' => $this->id
            ]);
        }
    }

    /**
     * @param BillingService $billingService
     * @return $this
     * @throws ActiveLevelNotFoundException
     * @throws ServiceException
     */
    public function collect(BillingService $billingService)
    {
        $activeLevel = $this->levels()->where('state', 'can-collect')->first();
        if ($activeLevel) {
            $activeLevel = $activeLevel->collect();
            $this->state = 'collected';
            $this->is_active = false;
            $this->save();
            $this->payPrize($activeLevel->win_prize, $billingService);
            return $this;
        } else {
            throw new ActiveLevelNotFoundException('Game has no collectable level', [
                'message' => 'Game has no collectable level',
                'game_id' => $this->id
            ]);
        }
    }

    /**
     * @param float $amount
     * @param BillingService $billingService
     * @return Game
     * @throws ServiceException
     */
    private function payPrize(float $amount, BillingService $billingService)
    {
        if (!$amount) {
            return $this;
        }
        $transactions = [$billingService->depositMoney($this->user_id, $amount, $this->currency, 'games', $this->id)];
        $result = $billingService->createTransactions($transactions);
        if ($result['status'] == 200 && $result['data']) {
            $transactionId = $result['data'][0]['id'];
            $this->addTransaction($transactionId);
            $this->paid_prize = $amount;
            $this->state = 'collected';
            $this->is_active = false;
            $this->save();
            return $this;
        } else {
            throw new ServiceException('Failed to create transaction on billing service', []);
        }
    }

    /**
     * @param array $config
     * @return mixed
     */
    public function expiredStatus(array $config)
    {
        $this->refresh();
        $updatedAt = $this->updated_at;
        $secondsToExpire = $config['seconds_to_play'] - $updatedAt->diffInSeconds(Carbon::now());
        $game['is_expired'] = $secondsToExpire < 0;
        $game['expires_at'] = $updatedAt->addSeconds($config['seconds_to_play'])->timestamp;
        $game['seconds_to_expire'] = $game['is_expired'] ? 0 : $secondsToExpire;
        return $game;
    }
}
