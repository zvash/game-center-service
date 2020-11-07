<?php

namespace App;

use App\Exceptions\InsufficientPossessionException;
use App\Exceptions\LevelIsNotActiveException;
use App\Exceptions\LevelIsNotRevealableException;
use App\Exceptions\ServiceException;
use App\Services\BillingService;
use Illuminate\Database\Eloquent\Model;

/**
 * @property integer id
 * @property integer game_id
 * @property integer level_index
 * @property integer boxes_count
 * @property integer winner_box
 * @property integer chosen_box
 * @property string revealable_boxes
 * @property double win_prize
 * @property double leave_prize
 * @property string last_move_time
 * @property string state
 * @property string transaction_ids
 * @property mixed reveal_price
 * @property Game game
 */
class Level extends Model
{
    protected $fillable = [
        'game_id',
        'level_index',
        'boxes_count',
        'winner_box',
        'revealable_boxes',
        'reveal_price',
        'win_prize',
        'leave_prize',
        'last_move_time',
        'state'
    ];

    protected $states = [
        'inactive',
        'can-collect',
        'collected',
        'passed',
        'active',
        'won',
        'lost'
    ];

    protected $activeStates = ['can-collect', 'active'];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function game()
    {
        return $this->belongsTo(Game::class);
    }

    public function getLevel(array $config)
    {
        $revealable = explode(',', $this->revealable_boxes);
        $revealableCount = count($revealable) + 1;
        $allBoxes = range(1, $this->boxes_count);
        $answer = $this->winner_box;
        $revealedBoxes = array_filter($allBoxes, function ($item) use ($answer, $revealable) {
            return !(
                $item == $answer ||
                in_array($item, $revealable)
            );
        });

        $revealedBoxes = array_values($revealedBoxes);

        $level = [];
        $level['index'] = $this->level_index;
        $level['possible_answers'] = array_values(array_filter($allBoxes, function ($item) use ($revealedBoxes) {
            return !in_array($item, $revealedBoxes);
        }));
        $level['allow_reveal'] = $this->state == 'active' && !$this->isLastLevel($config) && $revealableCount >= $config['reveal_min_boxes'];
        $level['reveal_price'] = $this->reveal_price;
        $level['revealed_boxes'] = $revealedBoxes;
        $level['win_prize'] = $this->win_prize;
        $level['state'] = $this->state;
        $level['playable'] = $this->state == 'active';
        $level['payable'] = $this->state == 'can-collect';
        $nextLevel = $this->getNext();
        if ($nextLevel) {
            $level['next_level_win_prize'] = $nextLevel->win_prize;
        } else {
            $level['next_level_win_prize'] = null;
        }
        return $level;
    }

    /**
     * @return Level|null
     */
    public function getNext()
    {
        $nextLevelIndex = $this->level_index + 1;
        $nextLevel = Level::where('game_id', $this->game_id)
            ->where('level_index', $nextLevelIndex)
            ->first();
        return $nextLevel;
    }

    /**
     * @return $this
     */
    public function activate()
    {
        $this->state = 'active';
        $this->last_move_time = date('Y-m-d H:i:s');
        $this->save();
        $game = $this->game()->first();
        $game->current_level_index = $this->level_index;
        $game->save();
        return $this;
    }

    /**
     * @param array $config
     * @param BillingService $billingService
     * @return mixed
     * @throws InsufficientPossessionException
     * @throws LevelIsNotRevealableException
     * @throws ServiceException
     */
    public function revealOne(array $config, BillingService $billingService)
    {
        $revealable = explode(',', $this->revealable_boxes);
        $revealableCount = count($revealable) + 1;
        if (!$this->isLastLevel($config) && $revealableCount >= $config['reveal_min_boxes']) {
            $amount = $config['reveal_price'];
            $userId = $this->game->user_id;
            $transactions = [$billingService->withdrawCoin($userId, $amount, 'games', $this->game->id)];
            $result = $billingService->createTransactions($transactions);
            if ($result['status'] == 200 && $result['data']) {
                $transactionId = $result['data'][0]['id'];
                $this->game->addTransaction($transactionId);
                $this->addTransaction($transactionId);
                $randomIndex = mt_rand(0, count($revealable) - 1);
                $revealedBox = $revealable[$randomIndex];
                unset($revealable[$randomIndex]);
                $this->revealable_boxes = implode(',', $revealable);
                $this->save();
                $this->game->touch();
                return $revealedBox;
            } else if (array_key_exists('json', $result['data'])) {
                $error = json_decode($result['data']['json'], 1);
                throw new InsufficientPossessionException(
                    'Not enough coins to reveal a box',
                    [
                        'message' => 'Not enough coins to reveal a box',
                        'current_coins' => $error['current_value'],
                        'needed_coins' => $amount
                    ]
                );
            } else {
                throw new ServiceException('Failed to create transaction on billing service', []);
            }
        } else {
            throw new LevelIsNotRevealableException('No box to reveal.', [
                'message' => 'Cannot reveal any boxes.',
                'remained_boxes_count' => $revealableCount,
                'reveal_limit' => $config['reveal_min_boxes'],
                'last_level' => $this->isLastLevel($config)
            ]);
        }
    }

    /**
     * @param int $answer
     * @return $this
     * @throws LevelIsNotActiveException
     */
    public function updateStateByAnswer(int $answer)
    {
        $this->confirmLevelIsActive();
        $rightAnswer = $answer == $this->winner_box;
        $this->chosen_box = $answer;
        $nextLevel = $this->getNext();
        $this->state = $rightAnswer ?
            $nextLevel ? 'can-collect' : 'won'
            : 'lost';
        $this->save();
        $this->game->touch();
        return $this;
    }

    /**
     * @return $this
     */
    public function updateStateByExpiration()
    {
        $this->state = 'lost';
        $this->save();
        return $this;
    }

    /**
     * @return null|Level
     * @throws LevelIsNotActiveException
     */
    public function passLevel()
    {
        $this->confirmLevelIsCollectable();
        $this->state = 'passed';
        $this->save();
        $this->game->touch();
        $nextLevel = $this->getNext();
        if ($nextLevel) {
            $nextLevel->activate();
        }
        return $nextLevel;
    }

    /**
     * @return Level
     * @throws LevelIsNotActiveException
     */
    public function collect()
    {
        $this->confirmLevelIsCollectable();
        $this->state = 'collected';
        $this->save();
        $this->game->touch();
        return $this;
    }

    /**
     * @throws LevelIsNotActiveException
     */
    private function confirmLevelIsActive()
    {
        if ($this->state != 'active') {
            throw new LevelIsNotActiveException('Level is not active.', [
                'message' => 'Level is not active.',
                'id' => $this->id,
                'state' => $this->state
            ]);
        }
    }

    /**
     * @throws LevelIsNotActiveException
     */
    private function confirmLevelIsCollectable()
    {
        if ($this->state != 'can-collect') {
            throw new LevelIsNotActiveException('Level is not passable.', [
                'message' => 'Level is not passable.',
                'id' => $this->id,
                'state' => $this->state
            ]);
        }
    }

    /**
     * @param int $transactionId
     * @return $this
     */
    private function addTransaction(int $transactionId)
    {
        $currentIds = $this->transaction_ids ? explode(',', $this->transaction_ids) : [];
        $currentIds[] = $transactionId;
        $this->transaction_ids = implode(',', $currentIds);
        return $this;
    }

    /**
     * @param array $config
     * @return bool
     */
    private function isLastLevel(array $config)
    {
        return $this->level_index == $config['total_levels'];
    }
}
