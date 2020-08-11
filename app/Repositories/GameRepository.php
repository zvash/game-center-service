<?php

namespace App\Repositories;


use App\Game;
use App\GameConfig;
use App\Level;
use App\Services\BillingService;
use Illuminate\Support\Facades\DB;

class GameRepository
{
    public function createGame(int $userId, BillingService $billingService)
    {
        try {
            DB::beginTransaction();
            $config = GameConfig::all()->pluck('value', 'key')->toArray();

            $game = Game::create([
                'user_id' => $userId,
                'total_levels' => $config['total_levels'],
            ]);

            for ($levelIndex = 1; $levelIndex <= $config['total_levels'] * 1; $levelIndex++) {
                $possibleChoices = range(1, $config['box_count.level_' . $levelIndex]);
                $winnerIndex = mt_rand(0, $config['box_count.level_' . $levelIndex] - 1);
                $winnerBox = $possibleChoices[$winnerIndex];
                unset($possibleChoices[$winnerIndex]);
                Level::create([
                    'game_id' => $game->id,
                    'level_index' => $levelIndex,
                    'boxes_count'=> $config['box_count.level_' . $levelIndex],
                    'winner_box' => $winnerBox,
                    'revealable_boxes' => implode(',', $possibleChoices),
                    'reveal_price' => $config['reveal_price'],
                    'win_prize' => $config['win_prize.level_' . $levelIndex],
                    'leave_prize' => $config['leave_prize.level_' . $levelIndex],
                    'last_move_time' => date('Y-m-d H:i:s'),
                    'status' => 'inactive'
                ]);
            }

            DB::commit();
        } catch (\Exception $exception) {
            DB::rollBack();
        }
    }
}