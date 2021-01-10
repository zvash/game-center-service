<?php

namespace App\Listeners;


use App\Events\UserHasWonTheGame;
use App\Game;
use App\SocialProof;

class SubmitSocialProofListener
{
    public function handle(UserHasWonTheGame $event)
    {
        $userResponse = $event->authService->getUserById($event->userId);
        if ($userResponse['status'] == 200) {
            $user = $userResponse['data'];
            $currency = $user['currency'];
            $sources['games'] = Game::where('user_id', $event->userId)
                ->where('state', 'collected')
                ->pluck('id')
                ->all();
            $playCount = count($sources['games']);
            $balancesResponse = $event->billingService->getSourcesBalances($sources);
            if ($balancesResponse['status'] == 200) {
                $wonAmount = 0;
                $balances = $balancesResponse['data']['games'];
                foreach ($balances as $balance) {
                    if (array_key_exists($currency, $balance)) {
                        $wonAmount += $balance[$currency];
                    }
                }
                $socialProof = SocialProof::where('user_id', $event->userId)->first();
                if ($socialProof) {
                    $socialProof->setAttribute('play_count', $playCount)
                        ->setAttribute('won_amount', $wonAmount)
                        ->save();
                } else {
                    SocialProof::create([
                        'user_id' => $event->userId,
                        'play_count' => $playCount,
                        'won_amount' => $wonAmount,
                        'currency' => $currency
                    ]);
                }
            }
        }
    }

}